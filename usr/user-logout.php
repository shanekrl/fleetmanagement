<?php
// usr/user-logout.php

// Absolutely no output before headers:
declare(strict_types=1);

// Kill session cleanly
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

// Build the app base URL from the current URL:
// e.g. /flt-web/clean-flt/usr/user-logout.php  ->  /flt-web/clean-flt/
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

// SCRIPT_NAME -> /flt-web/clean-flt/usr/user-logout.php
$script = $_SERVER['SCRIPT_NAME'] ?? '/';
// dirname(dirname(...)) moves us up from /usr/file.php to the app root
$basePath = rtrim(dirname(dirname($script)), '/\\'); // /flt-web/clean-flt

// Redirect specifically to driver-login.php at the app root:
$target = $scheme . '://' . $host . $basePath . '/driver-login.php';

// If your login is a folder with its own index, use this instead:
// $target = $scheme . '://' . $host . $basePath . '/driver-login/';

// Do the redirect
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Location: ' . $target);
exit;

// Fallback if headers already sent (shouldn't happen)
// echo '<script>location.href='.json_encode($target).';</script><noscript><meta http-equiv="refresh" content="0;url='.htmlspecialchars($target,ENT_QUOTES).'"></noscript>';
