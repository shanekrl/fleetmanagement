<?php
// admin-manage-admins.php
session_start();
include('vendor/inc/config.php');
include('vendor/inc/checklogin.php');
require_once 'vendor/inc/audit.php';
require_superadmin(); // superadmin only

$mysqli->set_charset('utf8mb4');
$errors = [];
$ok = null;

// Prefer the helper if present
$actorId = function_exists('current_account_id')
  ? (int)(current_account_id() ?? 0)
  : (int)($_SESSION['account_id'] ?? $_SESSION['a_id'] ?? 0);

/**
 * Safe audit wrapper:
 * - Always provides a non-null status ('success' | 'failure' | 'info')
 * - Adapts to different audit_log() signatures via reflection
 * - Fills actor_email/actor_role from session keys (new + legacy)
 * - Never lets an audit failure crash the page
 */
function audit_wrap($mysqli, $actorId, $action, $entityId, $beforeArr, $afterArr, $status = 'success', $message = null, $entityType = 'account') {
  try {
    if (!function_exists('audit_log')) {
      error_log('audit_log() not found');
      return false;
    }
    $ref  = new ReflectionFunction('audit_log');
    $argc = $ref->getNumberOfParameters();

    // Normalize arrays
    $beforeArr = is_array($beforeArr) ? $beforeArr : ($beforeArr === null ? null : ['value' => $beforeArr]);
    $afterArr  = is_array($afterArr)  ? $afterArr  : ($afterArr  === null ? null : ['value' => $afterArr]);

    // Guard status
    $status = $status ?: 'success';
    if (!in_array($status, ['success','failure','info'], true)) $status = 'info';

    // Pull actor email/role from session (support new + legacy keys)
    $actorEmail = $_SESSION['email']   ?? $_SESSION['a_email'] ?? null;
    $actorRole  = $_SESSION['role']    ?? $_SESSION['a_role']  ?? null;
    if (is_string($actorRole)) $actorRole = strtolower($actorRole);

    // Newest signature includes actor_email + actor_role at the end
    if ($argc >= 11) {
      // ($db,$actor_id,$action,$entity_id,$before,$after,$status,$message,$entity_type,$actor_email,$actor_role)
      return audit_log($mysqli, $actorId, $action, $entityId, $beforeArr, $afterArr, $status, $message, $entityType, $actorEmail, $actorRole);
    } elseif ($argc >= 9) {
      // ($db,$actor_id,$action,$entity_id,$before,$after,$status,$message,$entity_type)
      return audit_log($mysqli, $actorId, $action, $entityId, $beforeArr, $afterArr, $status, $message, $entityType);
    } elseif ($argc >= 7) {
      // ($db,$actor_id,$action,$entity_id,$before,$after,$status)
      return audit_log($mysqli, $actorId, $action, $entityId, $beforeArr, $afterArr, $status);
    } else {
      // Legacy: ($db,$actor_id,$action,$entity_id,$before,$after)
      $afterArr = ($afterArr ?? []);
      $afterArr['_status']      = $status;
      if ($message !== null)    $afterArr['_message'] = $message;
      if ($entityType)          $afterArr['_entity_type'] = $entityType;
      if ($actorEmail !== null) $afterArr['_actor_email'] = $actorEmail;
      if ($actorRole !== null)  $afterArr['_actor_role']  = $actorRole;
      return audit_log($mysqli, $actorId, $action, $entityId, $beforeArr, $afterArr);
    }
  } catch (Throwable $e) {
    error_log('audit_log failed: '.$e->getMessage());
    return false;
  }
}

// Create admin (POST)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_admin'])) {
  $name   = trim($_POST['name'] ?? '');
  $email  = trim($_POST['email'] ?? '');
  $role  = trim($_POST['roles'] ?? '');
  $pwd    = (string)($_POST['password'] ?? '');
  $active = isset($_POST['is_active']) ? 1 : 0;

  // basic validation
  if ($name==='')  $errors[] = 'Name is required.';
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
  if (strlen($pwd) < 8) $errors[] = 'Password must be at least 8 characters.';

  if (!$errors) {
    if ($st = $mysqli->prepare("SELECT 1 FROM accounts WHERE LOWER(email)=LOWER(?) LIMIT 1")) {
      $st->bind_param('s',$email);
      $st->execute();
      $st->store_result();
      if ($st->num_rows) $errors[] = 'Email already exists.';
      $st->close();
    } else {
      $errors[] = 'Could not check email uniqueness.';
    }
  }

  if (!$errors) {
    $hash = password_hash($pwd, PASSWORD_DEFAULT);
    if ($ins = $mysqli->prepare("
      INSERT INTO accounts (role, name, email, password_hash, is_active, created_at, updated_at)
      VALUES (?, ?, ?, ?, ?, NOW(), NOW())
    ")) {
      $ins->bind_param('ssssi', $role, $name, $email, $hash, $active);
      $execOk = $ins->execute();
      $newId  = (int)$ins->insert_id;
      $ins->close();

      if ($execOk && $newId) {
        $ok = 'Admin created.';
        audit_wrap(
          $mysqli, $actorId, 'admin_create', $newId,
          ['request'=>['name'=>$name,'email'=>$email,'is_active'=>$active]],
          ['after'=>['id'=>$newId,'role'=>'admin','name'=>$name,'email'=>$email,'is_active'=>$active]],
          'success', 'Created admin user', 'account'
        );
      } else {
        $ok = 'Failed to create admin.';
        audit_wrap(
          $mysqli, $actorId, 'admin_create', null,
          ['request'=>['name'=>$name,'email'=>$email,'is_active'=>$active]],
          ['error'=>'Insert returned failure'],
          'failure', 'Insert returned failure', 'account'
        );
      }
    } else {
      $errors[] = 'Failed to create admin (prepare failed).';
      audit_wrap(
        $mysqli, $actorId, 'admin_create', null,
        ['request'=>['name'=>$name,'email'=>$email,'is_active'=>$active]],
        ['error'=>'Insert prepare failed'],
        'failure', 'Insert prepare failed', 'account'
      );
    }
  } else {
    audit_wrap(
      $mysqli, $actorId, 'admin_create', null,
      ['request'=>['name'=>$name,'email'=>$email,'is_active'=>$active]],
      ['errors'=>$errors],
      'failure', 'Validation/uniqueness failed', 'account'
    );
  }
}

// Toggle active (optional)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['toggle_active'], $_POST['id'])) {
  $id = (int)$_POST['id'];
  $to = (int)$_POST['toggle_active'] ? 1 : 0;

  // Get BEFORE row for audit
  $beforeRow = null;
  if ($g = $mysqli->prepare("SELECT id, role, name, email, is_active FROM accounts WHERE id=? AND role='admin' LIMIT 1")) {
    $g->bind_param('i',$id);
    $g->execute();
    $res = $g->get_result();
    $beforeRow = $res->fetch_assoc() ?: null;
    $g->close();
  }

  if (!$beforeRow) {
    $ok = null; // silent in UI
    audit_wrap(
      $mysqli, $actorId, 'admin_toggle_active', $id,
      ['to'=>$to],
      ['error'=>'Target not found or not admin'],
      'failure', 'Target not found or not admin', 'account'
    );
  } else {
    if ($u = $mysqli->prepare("UPDATE accounts SET is_active=?, updated_at=NOW() WHERE id=? AND role='admin'")) {
      $u->bind_param('ii',$to,$id);
      $execOk  = $u->execute();
      $changed = ($execOk && $u->affected_rows > 0);
      $u->close();

      $ok = $changed ? 'Status updated.' : 'No changes applied.';

      // Fetch AFTER row
      $afterRow = null;
      if ($h = $mysqli->prepare("SELECT id, role, name, email, is_active FROM accounts WHERE id=? LIMIT 1")) {
        $h->bind_param('i',$id);
        $h->execute();
        $res2 = $h->get_result();
        $afterRow = $res2->fetch_assoc() ?: null;
        $h->close();
      }

      audit_wrap(
        $mysqli, $actorId, 'admin_toggle_active', $id,
        ['from'=>$beforeRow['is_active'], 'to'=>$to],
        ['before'=>['is_active'=>$beforeRow['is_active']], 'after'=>$afterRow ? ['is_active'=>$afterRow['is_active']] : null],
        $execOk ? ($changed ? 'success' : 'info') : 'failure',
        $execOk ? ($changed ? 'Toggled active state' : 'Update executed, no change') : 'Update execute failed',
        'account'
      );
    } else {
      $ok = 'Failed to update status.';
      audit_wrap(
        $mysqli, $actorId, 'admin_toggle_active', $id,
        ['from'=>$beforeRow['is_active'], 'to'=>$to],
        ['error'=>'Update prepare failed', 'before'=>['is_active'=>$beforeRow['is_active']]],
        'failure', 'Update prepare failed', 'account'
      );
    }
  }
}

// Fetch admins
$admins = [];
if ($rs = $mysqli->query("SELECT id,role,name,email,is_active,created_at FROM accounts ORDER BY name")) {
  while ($r=$rs->fetch_assoc()) $admins[]=$r;
}
?>
<!DOCTYPE html>
<html lang="en">
<?php include('vendor/inc/head.php'); ?>
<body id="page-top">
<?php include('vendor/inc/nav.php'); ?>
<div id="wrapper">
  <?php include('vendor/inc/sidebar.php'); ?>
  <div id="content-wrapper"><div class="container-fluid">

    <h1 class="kaya-page-title">Admin Users</h1>

    <?php if ($ok): ?>
      <div class="alert alert-<?= stripos($ok,'fail')!==false ? 'danger':'success' ?>"><?= htmlspecialchars($ok) ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul>
      </div>
    <?php endif; ?>

    <div class="card mb-4">
      <div class="card-header font-weight-bold">Add Admin</div>
      <div class="card-body">
        <form method="post" class="form">
          <input type="hidden" name="create_admin" value="1">
          <div class="form-row">
            <div class="form-group col-md-4">
              <label>Name</label>
              <input name="name" class="form-control" required>
            </div>
            <div class="form-group col-md-4">
              <label>Email</label>
              <input name="email" type="email" class="form-control" required>
            </div>
            <div class="form-group col-md-3">
              <label>Password</label>
              <input name="password" type="password" class="form-control" minlength="8" required>
              <small class="text-muted">Will be hashed (bcrypt/argon).</small>
            </div>
            <div class="form-group col-md-1 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                <label class="form-check-label" for="is_active">Active</label>
              </div>
            </div>
            <div class="form-group col-md-3">

                <label>Role:</label>
                <select name="roles" class="form-control">
                  <option value="superadmin">Super Admin</option>
                  <option value="admin">Admin</option>
                  <option value="driver">Driver</option>
                </select>
            </div>
          </div>
          <button class="btn btn-kaya-primary"><i class="fas fa-user-plus mr-1"></i>Create</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-header font-weight-bold">Existing Admins</div>
      <div class="card-body table-responsive">
        <table class="table table-hover">
          <thead><tr>
            <th>#</th><th>Role</th><th>Name</th><th>Email</th><th>Active</th><th>Created</th><th>Actions</th>
          </tr></thead>
          <tbody>
          <?php foreach ($admins as $i=>$a): ?>
            <tr>
              <td><?= $i+1 ?></td>
              <td><?= htmlspecialchars($a['role']) ?></td>
              <td><?= htmlspecialchars($a['name']) ?></td>
              <td><?= htmlspecialchars($a['email']) ?></td>
              <td>
                <span class="badge badge-<?= $a['is_active']?'success':'secondary' ?>">
                  <?= $a['is_active']?'Yes':'No' ?>
                </span>
              </td>
              <td><?= htmlspecialchars(date('Y-m-d', strtotime($a['created_at']))) ?></td>
              <td>
                <form method="post" class="d-inline">
                  <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                  <input type="hidden" name="toggle_active" value="<?= $a['is_active']?0:1 ?>">
                  <button class="btn btn-sm btn-outline-<?= $a['is_active']?'secondary':'success' ?>">
                    <?= $a['is_active']?'Deactivate':'Activate' ?>
                  </button>
                </form>
              </td>
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
