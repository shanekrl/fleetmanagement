<?php
session_start();

include('vendor/inc/config.php');
include('vendor/inc/checklogin.php');
require_admin(); // only admins/superadmins

$Q = [
  'q'      => trim($_GET['q'] ?? ''),
  'action' => trim($_GET['action'] ?? ''),
  'days'   => max(1, (int)($_GET['days'] ?? 30)),
  'status' => trim($_GET['status'] ?? ''), // success | failure | info
];

$where = ["occurred_at >= NOW() - INTERVAL ? DAY"];
$types = 'i';
$vals  = [$Q['days']];

if ($Q['action'] !== '') { $where[]="action = ?"; $types.='s'; $vals[]=$Q['action']; }
if ($Q['status'] !== '') { $where[]="status = ?"; $types.='s'; $vals[]=$Q['status']; }
if ($Q['q']      !== '') {
  // Search in JSON payloads and common text columns
  $where[]="(
      JSON_SEARCH(details_json, 'one', ?, NULL, '$**') IS NOT NULL
   OR JSON_SEARCH(before_json,  'one', ?, NULL, '$**') IS NOT NULL
   OR JSON_SEARCH(after_json,   'one', ?, NULL, '$**') IS NOT NULL
   OR action  LIKE CONCAT('%',?,'%')
   OR message LIKE CONCAT('%',?,'%')
   OR actor_email LIKE CONCAT('%',?,'%')
  )";
  $types .= 'ssssss';
  array_push($vals, $Q['q'], $Q['q'], $Q['q'], $Q['q'], $Q['q'], $Q['q']);
}

$sql = "
  SELECT
    id,
    occurred_at,
    actor_id,
    actor_email,
    actor_role,
    action,
    entity_type,
    entity_id,
    status,
    message,
    INET6_NTOA(ip_address) AS ip_str,
    user_agent,
    details_json
  FROM audit_logs
  WHERE ".implode(' AND ', $where)."
  ORDER BY occurred_at DESC, id DESC
  LIMIT 500
";

$rows = [];
$st = $mysqli->prepare($sql);
$st->bind_param($types, ...$vals);
$st->execute();
$res = $st->get_result();
while ($r = $res->fetch_assoc()) $rows[] = $r;
$st->close();

function jget($row, $key, $fallback='') {
  $obj = json_decode($row['details_json'] ?? '[]', true) ?: [];
  return htmlspecialchars($obj[$key] ?? $fallback);
}
?>
<!doctype html><html lang="en">
<?php include('vendor/inc/head.php'); ?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<body id="page-top">
<?php include('vendor/inc/nav.php'); ?>
<div id="wrapper">
  <?php include('vendor/inc/sidebar.php'); ?>
  <div id="content-wrapper"><div class="container-fluid">

    <h1 class="kaya-page-title">Audit Logs</h1>

    <form class="form-inline mb-3">
      <input name="q" class="form-control mr-2" placeholder="Search details/action/email/message" value="<?= htmlspecialchars($Q['q']) ?>">
      <select name="action" class="form-control mr-2">
        <option value="">Any action</option>
        <?php foreach (['login_success','login_failed','login_blocked_inactive','login_blocked_lockout','admin_create','admin_toggle_active'] as $a): ?>
          <option value="<?= $a ?>" <?= $Q['action']===$a?'selected':'' ?>><?= $a ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" class="form-control mr-2">
        <option value="">Any status</option>
        <?php foreach (['success','failure','info'] as $s): ?>
          <option value="<?= $s ?>" <?= $Q['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="days" class="form-control mr-2">
        <?php foreach ([1,7,30,90,180,365] as $d): ?>
          <option value="<?= $d ?>" <?= (int)$Q['days']===$d?'selected':'' ?>>Last <?= $d ?>d</option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-kaya-primary">Filter</button>
    </form>

    <div class="card">
      <div class="card-body table-responsive">
        <table class="table table-sm table-hover">
          <thead>
            <tr>
              <th>When</th>
              <th>Actor</th>
              <th>Action</th>
              <th>Entity</th>
              <th>Status</th>
              <th>Role</th>
              <th>IP</th>
              <th>User Agent</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <?php
              $badge = $r['status']==='success' ? 'success' : ($r['status']==='failure' ? 'danger' : 'secondary');
              $actor = trim(($r['actor_id'] ?? '').' '.($r['actor_email'] ? "({$r['actor_email']})" : ''));
              $entity = trim(($r['entity_type'] ?? '').($r['entity_id']!==null ? " #{$r['entity_id']}" : ''));
            ?>
            <tr title="<?= htmlspecialchars($r['message'] ?? '') ?>">
              <td><?= htmlspecialchars($r['occurred_at']) ?></td>
              <td><?= $actor !== '' ? htmlspecialchars($actor) : '—' ?></td>
              <td><code><?= htmlspecialchars($r['action']) ?></code></td>
              <td><?= $entity !== '' ? htmlspecialchars($entity) : '—' ?></td>
              <td><span class="badge badge-<?= $badge ?>"><?= htmlspecialchars($r['status'] ?: '—') ?></span></td>
              <td><?= htmlspecialchars($r['actor_role'] ?: (jget($r,'portal','—'))) ?></td>
              <td><?= htmlspecialchars($r['ip_str'] ?? '') ?></td>
              <td title="<?= htmlspecialchars($r['user_agent'] ?? '') ?>"><?= htmlspecialchars(mb_strimwidth($r['user_agent'] ?? '', 0, 40, '…')) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div><?php include('vendor/inc/footer.php'); ?></div>
</div>
<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body></html>
