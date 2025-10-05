<?php
session_start();
require_once __DIR__.'/admin/vendor/inc/audit.php';
include('admin/vendor/inc/config.php');

// Settings
const LOCKOUT_THRESHOLD = 5;   // number of bad tries before lockout
const LOCKOUT_MINUTES   = 15;  // lockout window (minutes)

/* verify against bcrypt/argon/md5/plain (legacy compatibility) */
function verify_any($plain, $stored){
  if ($stored===null) return false;
  $s = (string)$stored;
  if (preg_match('/^\$2[ayb]\$|\$argon2(id|i)\$/',$s)) return password_verify($plain,$s);
  if (ctype_xdigit($s) && strlen($s)===32) return md5($plain)===strtolower($s); // legacy
  return hash_equals($s,$plain); // last-ditch legacy
}

function bounce($msg, $to='index.php'){
  $_SESSION['error'] = $msg;
  header("Location: {$to}");
  exit;
}


if ($_SERVER['REQUEST_METHOD']!=='POST') {
  bounce('Invalid request.');
}

$mysqli->set_charset('utf8mb4');

$email      = trim($_POST['email'] ?? '');
$pass       = (string)($_POST['password'] ?? '');
$expect     = trim($_POST['expect_role'] ?? '');   // 'admin' | 'driver' (from your forms)
$legacyType = trim($_POST['user_type'] ?? '');     // 'admin' | 'user' (legacy forms)
$wantedRole = $expect ?: ($legacyType === 'user' ? 'driver' : $legacyType);

if ($email==='' || $pass==='') {
  $fallback = $wantedRole==='admin' ? 'admin-login.php' : ($wantedRole==='driver' ? 'driver-login.php' : 'index.php');
  bounce('Please enter email and password.', $fallback);
}

// Try new "accounts" first
$acc = null;
if ($st = $mysqli->prepare("SELECT id, role, name, email, password_hash, is_active, failed_attempts, locked_until, COALESCE(twofa_enabled,0) AS twofa_enabled FROM accounts WHERE LOWER(email)=LOWER(?) AND deleted_at IS NULL LIMIT 1")) {
  $st->bind_param('s', $email);
  $st->execute();
  $res = $st->get_result();
  $acc = $res->fetch_assoc();
  $st->close();
}

$adminPortal = ($wantedRole==='admin'); // admin pages expect Admin or SuperAdmin

if ($acc) {
  $id   = (int)$acc['id'];
  $role = (string)$acc['role'];

  // Gate by portal type:
  if ($wantedRole === 'driver' && $role !== 'driver') {
    bounce("This page is for drivers. Your account role is '{$role}'.", 'driver-login.php');
  }
  if ($adminPortal && !in_array($role, ['admin','superadmin'], true)) {
    bounce("This page is for admins. Your account role is '{$role}'.", 'admin-login.php');
  }

  // Active check
  if ((int)$acc['is_active'] !== 1) {
    audit_log($mysqli, null, 'login_blocked_inactive', $id, [
      'email'  => $email,
      'status' => 'failure'
    ]);
    bounce('Your account is disabled. Contact an administrator.', $adminPortal ? 'admin-login.php' : 'driver-login.php');
  }

  // Lockout window
  $now = new DateTimeImmutable('now');
  $lockedUntil = !empty($acc['locked_until']) ? new DateTimeImmutable($acc['locked_until']) : null;
  if ($lockedUntil && $now < $lockedUntil) {
    audit_log($mysqli, null, 'login_blocked_lockout', $id, [
      'email'        => $email,
      'locked_until' => $acc['locked_until'],
      'status'       => 'failure'
    ]);
    bounce('Too many failed attempts. Please try again later.', $adminPortal ? 'admin-login.php' : 'driver-login.php');
  }

  // Verify password
  if (verify_any($pass, $acc['password_hash'])) {
    // Reset counters
    if ($u = $mysqli->prepare("UPDATE accounts SET failed_attempts=0, locked_until=NULL WHERE id=?")) {
      $u->bind_param('i', $id);
      $u->execute();
      $u->close();
    }

    // 2FA hook (enable later: require TOTP for admin/superadmin)
    $requires2FA = ((int)$acc['twofa_enabled'] === 1) && in_array($role, ['admin','superadmin'], true);
    if ($requires2FA) {
      $_SESSION['preauth_id']   = $id;
      $_SESSION['preauth_role'] = $role;
      $_SESSION['preauth_name'] = $acc['name'];
      // Redirect to your TOTP page when you add it
      header('Location: verify-2fa.php'); exit;
    }

    // Finalize session
    $_SESSION['account_id'] = $id;
    $_SESSION['role']       = $role;
    $_SESSION['email']      = $acc['email'];
    $_SESSION['name']       = $acc['name'];
    session_regenerate_id(true);

    // Log to auth_logins
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($i = $mysqli->prepare("INSERT INTO auth_logins (account_id, ip_addr, user_agent) VALUES (?, INET6_ATON(?), ?)")) {
      $i->bind_param('iss', $id, $ip, $ua);
      $i->execute();
      $i->close();
    }

    // Audit success
    audit_log($mysqli, $id, 'login_success', $id, [
      'role'   => $role,
      'status' => 'success'
    ]);

    // Routing
    if ($adminPortal && in_array($role, ['admin','superadmin'], true)) {
      // convenience for legacy includes that check a_id/user_type
      $_SESSION['a_id']      = $id;
      $_SESSION['user_type'] = 'admin';
      header('Location: admin/admin-dashboard.php'); exit;
    }
    if ($role === 'driver') {
      $_SESSION['driver_account_id'] = $id;
      $_SESSION['user_type'] = 'driver';

      // Optional legacy shim: map to tms_user.u_id if exists
      if ($s = $mysqli->prepare("SELECT u_id FROM tms_user WHERE LOWER(u_email)=LOWER(?) LIMIT 1")) {
        $s->bind_param('s', $acc['email']);
        $s->execute(); $s->bind_result($legacyUid);
        if ($s->fetch()) $_SESSION['u_id'] = (int)$legacyUid;
        $s->close();
      }
      header('Location: usr/user-dashboard.php'); exit;
    }

    bounce('Your role is not allowed to sign in here.', 'index.php');
  } else {
    // Bad password → increment counters & maybe lock
    $failed = (int)$acc['failed_attempts'] + 1;
    $threshold = LOCKOUT_THRESHOLD;

    // Lock when failed >= threshold
    if ($u = $mysqli->prepare("UPDATE accounts SET failed_attempts=?, locked_until=CASE WHEN ? >= ? THEN DATE_ADD(NOW(), INTERVAL ? MINUTE) ELSE NULL END WHERE id=?")) {
      $u->bind_param('iiiii', $failed, $failed, $threshold, LOCKOUT_MINUTES, $id);
      $u->execute();
      $u->close();
    }

    audit_log($mysqli, $id, 'login_failed', $id, [
      'email'           => $email,
      'failed_attempts' => $failed,
      'status'          => 'failure'
    ]);
    $fallback = $adminPortal ? 'admin-login.php' : ($wantedRole==='driver' ? 'driver-login.php' : 'index.php');
    bounce('Invalid email or password.', $fallback);
  }
}

// No match
bounce('Invalid email or password.', $adminPortal ? 'admin-login.php' : ($wantedRole==='driver' ? 'driver-login.php' : 'index.php'));
