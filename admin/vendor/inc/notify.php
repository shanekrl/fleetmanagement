<?php
function notify($mysqli, array $n) {
  $sql = "INSERT INTO notifications
            (audience, target_account_id, type, ref_id, title, body, url)
          VALUES (?,?,?,?,?,?,?)";
  if ($st = $mysqli->prepare($sql)) {
    $aud   = $n['audience'] ?? 'admin';
    $tgt   = $n['target_account_id'] ?? null;
    $type  = $n['type'] ?? 'info';
    $ref   = $n['ref_id'] ?? null;
    $title = $n['title'] ?? '';
    $body  = $n['body'] ?? null;
    $url   = $n['url'] ?? null;
    $st->bind_param('sisssss', $aud, $tgt, $type, $ref, $title, $body, $url);
    $st->execute(); $st->close();
  }
}
