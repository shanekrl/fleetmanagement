<?php
// SSE stream of unread notifications for admins
session_start();
require_once __DIR__ . '/../vendor/inc/config.php';
require_once __DIR__ . '/../vendor/inc/checklogin.php';
$aid = require_admin();  // ensure admin-like session

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

// last id the browser saw (EventSource handles reconnections)
$lastId = 0;
if (!empty($_SERVER['HTTP_LAST_EVENT_ID'])) $lastId = (int)$_SERVER['HTTP_LAST_EVENT_ID'];
if (isset($_GET['last_id'])) $lastId = max($lastId, (int)$_GET['last_id']);

// simple 25s loop; browser auto-reconnects
$start = time();
ignore_user_abort(true);
while (!connection_aborted() && (time() - $start) < 25) {
  // broadcast to all admins OR targeted to this admin
  $sql = "SELECT id, type, ref_id, title, body, url, created_at
            FROM notifications
           WHERE audience='admin'
             AND (target_account_id IS NULL OR target_account_id=?)
             AND id > ?
           ORDER BY id ASC";
  if ($st = $mysqli->prepare($sql)) {
    $st->bind_param('ii', $aid, $lastId);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
      $lastId = (int)$row['id'];
      $payload = json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
      // send as default event
      echo "id: {$lastId}\n";
      echo "data: {$payload}\n\n";
      @ob_flush(); @flush();
    }
    $st->close();
  }

  // heartbeat every 10s to keep connection warm
  echo "event: ping\n";
  echo "data: {}\n\n";
  @ob_flush(); @flush();

  sleep(2);
}
