<?php
session_start();
include('admin/vendor/inc/config.php');

/* verify against bcrypt/argon/md5/plain (kept from your version) */
function verify_any($plain, $stored){
  if ($stored===null) return false;
  $s = (string)$stored;
  if (preg_match('/^\$2[ayb]\$|\$argon2(id|i)\$/',$s)) return password_verify($plain,$s);
  if (ctype_xdigit($s) && strlen($s)===32) return md5($plain)===strtolower($s);
  return hash_equals($s,$plain);
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

$email  = trim($_POST['email'] ?? '');
$pass   = (string)($_POST['password'] ?? '');
$expect = trim($_POST['expect_role'] ?? '');           // 'admin' | 'driver'
$legacy = trim($_POST['user_type'] ?? '');             // 'admin' | 'user'
$wantedRole = $expect ?: ($legacy === 'user' ? 'driver' : $legacy);

if ($email==='' || $pass==='') {
  $fallback = $wantedRole==='admin' ? 'admin-login.php' : ($wantedRole==='driver' ? 'driver-login.php' : 'index.php');
  bounce('Please enter email and password.', $fallback);
}

/* ---- Try new `accounts` first ---- */
$acc = null;
if ($st = $mysqli->prepare("SELECT id, role, name, email, password_hash, is_active FROM accounts WHERE LOWER(email)=LOWER(?) LIMIT 1")) {
  $st->bind_param('s', $email);
  $st->execute();
  $res = $st->get_result();
  $acc = $res->fetch_assoc();
  $st->close();
}

if ($acc && (int)$acc['is_active'] === 1 && verify_any($pass, $acc['password_hash'])) {
  if ($wantedRole && $acc['role'] !== $wantedRole) {
    $to = $wantedRole==='admin' ? 'admin-login.php' : 'driver-login.php';
    bounce("This page is for {$wantedRole}s. Your account role is '{$acc['role']}'.", $to);
  }

  // Sessions (single source of truth)
  $_SESSION['account_id'] = (int)$acc['id'];
  $_SESSION['role']       = $acc['role'];
  $_SESSION['email']      = $acc['email'];
  $_SESSION['name']       = $acc['name'];

  if ($acc['role'] === 'admin') {
    $_SESSION['a_id']      = (int)$acc['id'];
    $_SESSION['user_type'] = 'admin';
    header('Location: admin/admin-dashboard.php'); exit;
  }

  if ($acc['role'] === 'driver') {
    $_SESSION['driver_account_id'] = (int)$acc['id'];  // for convenience
    $_SESSION['user_type'] = 'driver';

    // Optional legacy shim: only set if a real tms_user row exists
    if ($s = $mysqli->prepare("SELECT u_id FROM tms_user WHERE LOWER(u_email)=LOWER(?) LIMIT 1")) {
      $s->bind_param('s', $acc['email']);
      $s->execute(); $s->bind_result($legacyUid);
      if ($s->fetch()) $_SESSION['u_id'] = (int)$legacyUid;
      $s->close();
    }

    header('Location: usr/user-dashboard.php'); exit;
  }

  bounce('Your role is not allowed to sign in here.', 'index.php');
}

/* ---- Optional legacy ADMIN fallback (tms_admin) ---- */
if ($wantedRole === 'admin') {
  if ($s = $mysqli->prepare("SELECT a_id, a_name, a_email, a_pwd FROM tms_admin WHERE LOWER(a_email)=LOWER(?) LIMIT 1")) {
    $s->bind_param('s', $email);
    $s->execute();
    $s->bind_result($a_id,$a_name,$a_email,$a_pwd);
    if ($s->fetch() && verify_any($pass, $a_pwd)) {
      $s->close();
      $_SESSION['a_id']      = (int)$a_id;
      $_SESSION['a_name']    = $a_name;
      $_SESSION['a_email']   = $a_email;
      $_SESSION['user_type'] = 'admin';
      header('Location: admin/admin-dashboard.php'); exit;
    }
    $s->close();
  }
}

bounce('Invalid email or password.', $wantedRole==='admin' ? 'admin-login.php' : ($wantedRole==='driver' ? 'driver-login.php' : 'index.php'));
