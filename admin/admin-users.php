<?php
// admin/admin-users.php — SuperAdmin-only administration for admin accounts
session_start();
require_once __DIR__.'/vendor/inc/config.php';
require_once __DIR__.'/vendor/inc/checklogin.php';
check_login();

if (($_SESSION['role'] ?? '') !== 'superadmin') {
  http_response_code(403);
  exit('Forbidden');
}

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

function audit_log(mysqli $db, int $actorId = null, string $action, ?int $targetId, array $details = []) {
  $json = json_encode($details, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
  $ua   = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
  $stmt = $db->prepare("INSERT INTO admin_audit_logs (actor_id, action, target_id, details, ip_addr, user_agent) VALUES (?,?,?,?,?,?)");
  $stmt->bind_param('isisss', $actorId, $action, $targetId, $json, $ip, $ua);
  $stmt->execute();
}

// ----- handle actions -----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    http_response_code(419); exit('CSRF token mismatch');
  }

  $aid = (int)($_POST['id'] ?? 0);
  $act = $_POST['action'] ?? '';
  if (!$aid) { header('Location: admin-users.php'); exit; }

  // Safety: cannot perform certain actions on yourself to avoid lockouts
  $self = ($aid === (int)$_SESSION['account_id']);

  switch ($act) {
    case 'deactivate':
      if ($self) break; // don't allow self-deactivation
      $stmt = $mysqli->prepare("UPDATE accounts SET is_active=0 WHERE id=? AND role IN ('admin','superadmin')");
      $stmt->bind_param('i', $aid);
      $stmt->execute();
      audit_log($mysqli, (int)$_SESSION['account_id'], 'deactivate_account', $aid, []);
      break;

    case 'reactivate':
      $stmt = $mysqli->prepare("UPDATE accounts SET is_active=1 WHERE id=? AND role IN ('admin','superadmin')");
      $stmt->bind_param('i', $aid);
      $stmt->execute();
      audit_log($mysqli, (int)$_SESSION['account_id'], 'reactivate_account', $aid, []);
      break;

    case 'resetpw':
      if ($self) break; // avoid forced resets on self from this page
      $newpw = bin2hex(random_bytes(5)).'A!2'; // 12-ish chars, tweak policy as needed
      $hash  = password_hash($newpw, PASSWORD_DEFAULT);
      $stmt  = $mysqli->prepare("UPDATE accounts SET password_hash=? WHERE id=? AND role IN ('admin','superadmin')");
      $stmt->bind_param('si', $hash, $aid);
      if ($stmt->execute()) {
        audit_log($mysqli, (int)$_SESSION['account_id'], 'reset_password_admin', $aid, []);
        $_SESSION['flash_pw_'.$aid] = $newpw; // show once in UI (copy it and send securely out-of-band)
      }
      break;

    // Optional, strict role change: superadmin can demote other superadmins to admin (not self)
    case 'demote_to_admin':
      if ($self) break;
      $stmt = $mysqli->prepare("UPDATE accounts SET role='admin' WHERE id=? AND role='superadmin'");
      $stmt->bind_param('i', $aid);
      $stmt->execute();
      audit_log($mysqli, (int)$_SESSION['account_id'], 'demote_superadmin', $aid, []);
      break;

    default:
      // no-op
  }

  header('Location: admin-users.php');
  exit;
}

// filters
$status = $_GET['status'] ?? 'all';
$where  = "role IN ('admin','superadmin')";
if ($status === 'active')   $where .= " AND is_active=1";
if ($status === 'inactive') $where .= " AND is_active=0";

$q = "SELECT id, role, name, email, is_active, created_at, updated_at FROM accounts WHERE $where ORDER BY role DESC, name ASC";
$list = $mysqli->query($q);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <title>Admin Users</title>
  <style>
    .badge { padding:2px 6px; border-radius:6px; font-size:12px; }
    .green { background:#DCFCE7; color:#166534; }
    .red   { background:#FEE2E2; color:#991B1B; }
    .gray  { background:#E5E7EB; color:#374151; }
    table { width:100%; border-collapse: collapse; }
    th, td { padding:8px 10px; border-bottom:1px solid #eee; }
    .actions form { display:inline; margin-right:6px; }
    .audit-box { background:#fafafa; border:1px solid #eee; padding:8px; margin-top:6px; max-height:220px; overflow:auto;}
  </style>
</head>
<body>
<?php include __DIR__.'/nav.php'; ?>
<div class="container" style="max-width:980px;margin:20px auto;">
  <h1>Admin Users</h1>
  <div style="margin:10px 0;">
    <a href="admin-users.php?status=all">All</a> |
    <a href="admin-users.php?status=active">Active</a> |
    <a href="admin-users.php?status=inactive">Inactive</a>
    <span class="gray badge" style="margin-left:8px;">SuperAdmin-only</span>
  </div>

  <table>
    <thead>
      <tr>
        <th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th>Updated</th><th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php while($row = $list->fetch_assoc()): ?>
        <tr>
          <td><?= (int)$row['id'] ?></td>
          <td><?= htmlspecialchars($row['name']) ?></td>
          <td><?= htmlspecialchars($row['email']) ?></td>
          <td><span class="badge <?= $row['role']==='superadmin'?'gray':'green' ?>"><?= htmlspecialchars($row['role']) ?></span></td>
          <td>
            <?php if ((int)$row['is_active']===1): ?>
              <span class="badge green">active</span>
            <?php else: ?>
              <span class="badge red">inactive</span>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($row['created_at']) ?></td>
          <td><?= htmlspecialchars($row['updated_at']) ?></td>
          <td class="actions">
            <?php if ((int)$row['is_active']===1): ?>
              <?php if ($row['id'] != $_SESSION['account_id']): ?>
              <form method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <input type="hidden" name="action" value="deactivate">
                <button type="submit">Deactivate</button>
              </form>
              <?php endif; ?>
            <?php else: ?>
              <form method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <input type="hidden" name="action" value="reactivate">
                <button type="submit">Reactivate</button>
              </form>
            <?php endif; ?>

            <?php if ($row['id'] != $_SESSION['account_id']): ?>
              <form method="post" onsubmit="return confirm('Reset password for <?= htmlspecialchars($row['email']) ?>? You must deliver the new password securely.')">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <input type="hidden" name="action" value="resetpw">
                <button type="submit">Reset PW</button>
              </form>
            <?php endif; ?>

            <?php if ($row['role']==='superadmin' && $row['id'] != $_SESSION['account_id']): ?>
              <form method="post" onsubmit="return confirm('Demote this superadmin to admin?')">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <input type="hidden" name="action" value="demote_to_admin">
                <button type="submit">Demote</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php if (!empty($_SESSION['flash_pw_'.$row['id']])): ?>
          <tr><td colspan="8">
            <div class="audit-box">
              <strong>One-time password (copy now):</strong>
              <code><?= htmlspecialchars($_SESSION['flash_pw_'.$row['id']]) ?></code>
              <?php unset($_SESSION['flash_pw_'.$row['id']]); ?>
            </div>
          </td></tr>
        <?php endif; ?>

        <tr><td colspan="8">
          <details>
            <summary>Recent audit for #<?= (int)$row['id'] ?></summary>
            <div class="audit-box">
              <?php
                $aid = (int)$row['id'];
                $aud = $mysqli->query("SELECT action, details, created_at FROM admin_audit_logs WHERE target_id={$aid} ORDER BY id DESC LIMIT 20");
                if ($aud && $aud->num_rows):
              ?>
                <ul>
                  <?php while($a = $aud->fetch_assoc()): ?>
                    <li><strong><?= htmlspecialchars($a['action']) ?></strong> — <?= htmlspecialchars($a['created_at']) ?>
                      <?php if ($a['details']): ?>
                        <pre style="white-space:pre-wrap"><?= htmlspecialchars($a['details']) ?></pre>
                      <?php endif; ?>
                    </li>
                  <?php endwhile; ?>
                </ul>
              <?php else: ?>
                <em>No recent audit entries.</em>
              <?php endif; ?>
            </div>
          </details>
        </td></tr>

      <?php endwhile; ?>
    </tbody>
  </table>

  <hr style="margin:24px 0;">

  <h2>Create Admin</h2>
  <form method="post" action="admin-create-user.php">
    <button type="submit">Go to Create Admin page →</button>
  </form>
</div>
</body>
</html>
