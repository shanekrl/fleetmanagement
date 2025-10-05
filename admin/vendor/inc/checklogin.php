<?php
/**
 * Centralized auth guard (role-first, works with legacy too)
 * - Admin pages call:   require_admin()          // allows admin + superadmin
 * - Superadmin pages:   require_superadmin()     // superadmin only
 * - Driver pages call:  require_driver()
 * - Shared pages call:  require_any()
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

/* Safer redirect (relative, no scheme/host hardcoding) */
function _redirect_to(string $to='index.php'){
  // Clear session
  $_SESSION = [];
  if (session_id() !== '') {
    if (ini_get('session.use_cookies')) {
      $p = session_get_cookie_params();
      setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
  }
  header("Location: {$to}");
  exit;
}

/* ---- Role helpers (new model) ---- */
/* Read role from either key: 'role' (new) or 'a_role' (legacy) */
function session_role(): ?string {
  if (!empty($_SESSION['role']))   return strtolower((string)$_SESSION['role']);
  if (!empty($_SESSION['a_role'])) return strtolower((string)$_SESSION['a_role']);
  return null;
}

/* Admin-like means admin OR superadmin (plus legacy fallback) */
function is_admin_like(): bool {
  $r = session_role();
  if ($r === 'admin' || $r === 'superadmin') return true;

  // Legacy compatibility (older pages still set these)
  return (isset($_SESSION['user_type'], $_SESSION['a_id'])
          && $_SESSION['user_type'] === 'admin'
          && (int)$_SESSION['a_id'] > 0);
}

function is_superadmin(): bool {
  return session_role() === 'superadmin';
}

function is_driver_like(): bool {
  $r = session_role();
  if ($r === 'driver') return true;

  // Legacy compatibility: some pages still use user_type === 'user'
  return (isset($_SESSION['user_type'], $_SESSION['u_id'])
          && $_SESSION['user_type'] === 'user'
          && (int)$_SESSION['u_id'] > 0);
}

/* IDs with graceful fallback */
function current_account_id(): ?int {
  if (!empty($_SESSION['account_id'])) return (int)$_SESSION['account_id'];
  if (!empty($_SESSION['a_id']))       return (int)$_SESSION['a_id'];    // legacy admin fallback
  return null;
}

/* Require guards */

/* Allows admin OR superadmin */
function require_admin(): int {
  if (!is_admin_like()) _redirect_to('admin-login.php');
  return current_account_id() ?? (int)($_SESSION['a_id'] ?? 0);
}

/* Superadmin only (nice UX: signed-in admins get bounced to dashboard) */
function require_superadmin(): int {
  if (!is_superadmin()) {
    if (is_admin_like()) { header('Location: dashboard.php'); exit; }
    _redirect_to('admin-login.php');
  }
  return current_account_id() ?? (int)($_SESSION['a_id'] ?? 0);
}

function require_driver(): int {
  if (!is_driver_like()) _redirect_to('index.php');
  // prefer accounts.id if present; otherwise legacy u_id
  return !empty($_SESSION['account_id'])
    ? (int)$_SESSION['account_id']
    : (int)($_SESSION['u_id'] ?? 0);
}

function require_any(): int {
  if (is_admin_like() || is_driver_like()) return current_account_id() ?? 0;
  _redirect_to('index.php'); // not logged in
}

/* Back-compat single entry (keeps old calls working)
 * - check_login('admin'|'user'|'driver'|'any')
 */
function check_login(string $required='admin'){
  $required = strtolower($required);
  if ($required === 'admin')  return require_admin();
  if ($required === 'driver' || $required === 'user') return require_driver();
  return require_any();
}

/* Optional: quick checks used by templates */
function is_admin(){ return is_admin_like(); }
function is_user(){  return is_driver_like(); }
