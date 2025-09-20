<?php
// ---- DB credentials ----
$DB_HOST = 'localhost';   // use 127.0.0.1 on Windows to avoid socket issues
$DB_PORT = 3306;          // change if your MySQL uses a different port
$DB_USER = 'u424609672_admin';
$DB_PASS = 'Fleetmanagement1234.';
$DB_NAME = 'u424609672_carbookings';

// ---- Turn on mysqli exceptions (helpful during dev) ----
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $mysqli = mysqli_init();
  // Optional: set a short timeout (seconds)
  mysqli_options($mysqli, MYSQLI_OPT_CONNECT_TIMEOUT, 5);

  // Connect
  if (!@mysqli_real_connect($mysqli, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT)) {
    throw new Exception('Database connection failed.');
  }

  // Always set UTF-8 (handles emojis, multi-byte chars)
  if (!mysqli_set_charset($mysqli, 'utf8mb4')) {
    throw new Exception('Failed to set connection charset to utf8mb4.');
  }

} catch (Throwable $e) {
  // Friendly message for users + detailed log for you
  http_response_code(500);
  // In production, log $e->getMessage() to a file instead of echoing it
  exit('Sorry, we are having database issues right now.');
}

 