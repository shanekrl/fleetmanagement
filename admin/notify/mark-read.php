<?php
session_start();
require_once __DIR__ . '/../vendor/inc/config.php';
require_once __DIR__ . '/../vendor/inc/checklogin.php';
require_admin();

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
  if ($st = $mysqli->prepare("UPDATE notifications SET is_read=1 WHERE id=?")) {
    $st->bind_param('i', $id); $st->execute(); $st->close();
  }
}
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
