<?php
// admin/notifications-stream.php
// Server-Sent Events stream for admin notifications

session_start();
require_once __DIR__ . '/vendor/inc/config.php';
require_once __DIR__ . '/vendor/inc/checklogin.php';
check_login();

// allow only admin/superadmin
$role = strtolower($_SESSION['role'] ?? '');
if (!in_array($role, ['admin','superadmin'], true)) {
  http_response_code(403); exit;
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // nginx: disable buffering if available
@set_time_limit(0);

$adminId = (int)($_SESSION['account_id'] ?? 0);
$lastId  = 0;

// support Last-Event-ID header or query ?last_id=
if (!empty($_SERVER['HTTP_LAST_EVENT_ID'])) {
  $lastId = (int)$_SERVER['HTTP_LAST_EVENT_ID'];
} elseif (isset($_GET['last_id'])) {
  $lastId = (int)$_GET['last_id'];
}

$flush = function() {
  @ob_flush(); @flush();
};

// short-lived stream (~30s) then the browser auto-reconnects
$start = time();
while (time() - $start < 30) {
  // any new notifications since last id for this admin (or audience-wide)
  if ($st = $mysqli->prepare("
        SELECT id, type, title, url, JSON_EXTRACT(payload,'$') AS payload, created_at
          FROM notifications
         WHERE id > ?
           AND audience = 'admin'
           AND (audience_id IS NULL OR audience_id = ?)
         ORDER BY id ASC
      ")) {
    $st->bind_param('ii', $lastId, $adminId);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
      $lastId = (int)$row['id'];
      $data = [
        'id'        => $lastId,
        'type'      => $row['type'],
        'title'     => $row['title'],
        'url'       => $row['url'],
        'payload'   => $row['payload'] ? json_decode($row['payload'], true) : null,
        'created_at'=> $row['created_at'],
      ];
      echo "id: {$lastId}\n";
      echo "event: {$row['type']}\n";
      echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n\n";
    }
    $st->close();
  }

  $flush();
  if (connection_aborted()) break;
  sleep(3);
}

echo ": ping\n\n";
$flush();
