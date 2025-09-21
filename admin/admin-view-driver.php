<?php
// ========== KAYA · Driver Details (auto Assigned Vehicle, status sync) ==========
session_start();
include('vendor/inc/config.php');
include('vendor/inc/checklogin.php');
check_login();
$aid = require_admin();

$src = isset($_GET['src']) && $_GET['src']==='user' ? 'user' : 'add';
$did = isset($_GET['d_u_id']) ? (int)$_GET['d_u_id'] : 0;
if ($did <= 0) { header('Location: admin-manage-driver.php'); exit; }

$mysqli->set_charset('utf8mb4');

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function column_exists(mysqli $db, string $table, string $col): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
  return $r && $r->num_rows > 0;
}
function table_exists(mysqli $db, string $table): bool {
  $t = $db->real_escape_string($table);
  $r = $db->query("SHOW TABLES LIKE '{$t}'");
  return $r && $r->num_rows > 0;
}
function status_badge_from_text($txt){
  $class = 'badge badge-secondary';
  if (stripos($txt,'avail')!==false) $class='badge badge-success';
  elseif (stripos($txt,'trip')!==false) $class='badge badge-primary';
  return '<span class="'.$class.' px-2 py-1">'.h($txt).'</span>';
}

/** Resolve assigned vehicle for a driver record (best-effort across schemas). */
function find_assigned_vehicle(mysqli $db, array $drv): ?array {
  // 1) If we can map the driver to an account via email, prefer vehicles.driver_id/default_driver_id
  $email = strtolower(trim($drv['u_email'] ?? ''));
  if ($email && table_exists($db,'accounts') && table_exists($db,'tms_vehicle')) {
    if ($st = $db->prepare("SELECT id FROM accounts WHERE LOWER(email)=? LIMIT 1")) {
      $st->bind_param('s',$email); $st->execute(); $st->bind_result($accId);
      if ($st->fetch()) { $st->close();
        // Try vehicles.driver_id
        if (column_exists($db,'tms_vehicle','driver_id')) {
          if ($q=$db->prepare("SELECT v_id, v_name, v_reg_no FROM tms_vehicle WHERE driver_id=? LIMIT 1")) {
            $q->bind_param('i',$accId); $q->execute(); $res=$q->get_result();
            if ($v=$res->fetch_assoc()) return ['id'=>(int)$v['v_id'], 'label'=>trim(($v['v_name']?:'Vehicle').' ('.$v['v_reg_no'].')')];
            $q->close();
          }
        }
        // Try vehicles.default_driver_id
        if (column_exists($db,'tms_vehicle','default_driver_id')) {
          if ($q=$db->prepare("SELECT v_id, v_name, v_reg_no FROM tms_vehicle WHERE default_driver_id=? LIMIT 1")) {
            $q->bind_param('i',$accId); $q->execute(); $res=$q->get_result();
            if ($v=$res->fetch_assoc()) return ['id'=>(int)$v['v_id'], 'label'=>trim(($v['v_name']?:'Vehicle').' ('.$v['v_reg_no'].')')];
            $q->close();
          }
        }
      } else { $st->close(); }
    }
  }

  // 2) Legacy link: tms_vehicle.driver_user_id -> this driver's legacy id
  if (table_exists($db,'tms_vehicle') && column_exists($db,'tms_vehicle','driver_user_id')) {
    $legacyId = (int)($drv['d_u_id'] ?? 0);
    if ($legacyId > 0) {
      if ($q=$db->prepare("SELECT v_id, v_name, v_reg_no FROM tms_vehicle WHERE driver_user_id=? LIMIT 1")) {
        $q->bind_param('i',$legacyId); $q->execute(); $res=$q->get_result();
        if ($v=$res->fetch_assoc()) return ['id'=>(int)$v['v_id'], 'label'=>trim(($v['v_name']?:'Vehicle').' ('.$v['v_reg_no'].')')];
        $q->close();
      }
    }
  }

  // 3) Fallback: last booking vehicle for this driver (new schema)
  if ($email && table_exists($db,'accounts') && table_exists($db,'bookings') && table_exists($db,'tms_vehicle')) {
    if ($st = $db->prepare("SELECT id FROM accounts WHERE LOWER(email)=? LIMIT 1")) {
      $st->bind_param('s',$email); $st->execute(); $st->bind_result($accId);
      if ($st->fetch()) { $st->close();
        $sql = "SELECT v.v_id, v.v_name, v.v_reg_no
                FROM bookings b
                JOIN tms_vehicle v ON v.v_id = b.vehicle_id
                WHERE b.driver_id=? AND b.vehicle_id IS NOT NULL
                ORDER BY COALESCE(b.updated_at,b.created_at) DESC LIMIT 1";
        if ($q=$db->prepare($sql)) {
          $q->bind_param('i',$accId); $q->execute(); $res=$q->get_result();
          if ($v=$res->fetch_assoc()) return ['id'=>(int)$v['v_id'], 'label'=>trim(($v['v_name']?:'Vehicle').' ('.$v['v_reg_no'].')')];
          $q->close();
        }
      } else { $st->close(); }
    }
  }

  // 4) Fallback: last legacy booking (tms_bookings)
  if ($email && table_exists($db,'accounts') && table_exists($db,'tms_bookings') && table_exists($db,'tms_vehicle')) {
    if ($st = $db->prepare("SELECT id FROM accounts WHERE LOWER(email)=? LIMIT 1")) {
      $st->bind_param('s',$email); $st->execute(); $st->bind_result($accId);
      if ($st->fetch()) { $st->close();
        $sql = "SELECT v.v_id, v.v_name, v.v_reg_no
                FROM tms_bookings b
                JOIN tms_vehicle v ON v.v_id = b.vehicle_id
                WHERE b.driver_id=? AND b.vehicle_id IS NOT NULL
                ORDER BY b.scheduled_at DESC LIMIT 1";
        if ($q=$db->prepare($sql)) {
          $q->bind_param('i',$accId); $q->execute(); $res=$q->get_result();
          if ($v=$res->fetch_assoc()) return ['id'=>(int)$v['v_id'], 'label'=>trim(($v['v_name']?:'Vehicle').' ('.$v['v_reg_no'].')')];
          $q->close();
        }
      } else { $st->close(); }
    }
  }

  return null;
}

$has_soft_delete = $src==='add' && column_exists($mysqli,'tms_user_add_driver','deleted_at');

$succ = ''; $err = '';

/* DELETE / RESTORE for add-table only */
if ($src==='add' && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['__action']) && $_POST['__action']==='delete_or_restore') {
  if (isset($_POST['delete_driver']) && (int)$_POST['d_u_id']===$did) {
    if ($has_soft_delete) {
      if ($s=$mysqli->prepare("UPDATE tms_user_add_driver SET deleted_at=NOW(), deleted_by=? WHERE d_u_id=?")) {
        $s->bind_param('ii',$aid,$did); $s->execute(); $s->close();
      }
    } else {
      if ($s=$mysqli->prepare("DELETE FROM tms_user_add_driver WHERE d_u_id=? LIMIT 1")) {
        $s->bind_param('i',$did); $s->execute(); $s->close();
      }
    }
    header('Location: admin-manage-driver.php'); exit;
  }
  if ($has_soft_delete && isset($_POST['restore_driver']) && (int)$_POST['d_u_id']===$did) {
    if ($s=$mysqli->prepare("UPDATE tms_user_add_driver SET deleted_at=NULL, deleted_by=NULL WHERE d_u_id=?")) {
      $s->bind_param('i',$did); $s->execute(); $s->close();
    }
    header('Location: admin-view-driver.php?src=add&d_u_id='.$did.'&restored=1'); exit;
  }
}

/* FETCH record */
$drv = null; $is_deleted = false;
if ($src==='add') {
  $cols = "d_u_id,u_fname,u_lname,u_phone,u_addr,u_car_regno,u_car_book_status,u_email"; // dropped u_car_type
  if ($has_soft_delete) $cols .= ",deleted_at";
  if ($s=$mysqli->prepare("SELECT $cols FROM tms_user_add_driver WHERE d_u_id=? LIMIT 1")) {
    $s->bind_param('i',$did); $s->execute(); $r=$s->get_result(); $drv=$r->fetch_assoc(); $s->close();
    if ($drv && $has_soft_delete) $is_deleted = !empty($drv['deleted_at']);
  }
} else {
  if ($s=$mysqli->prepare("SELECT u_id AS d_u_id,u_fname,u_lname,u_phone,u_addr,'' AS u_car_regno,
                                  COALESCE(NULLIF(u_car_book_status,''),'Available') AS u_car_book_status, u_email
                           FROM tms_user WHERE u_id=? LIMIT 1")) {
    $s->bind_param('i',$did); $s->execute(); $r=$s->get_result(); $drv=$r->fetch_assoc(); $s->close();
  }
}
if (!$drv) { header('Location: admin-manage-driver.php'); exit; }

/* Override status via driver_profile if present */
if (table_exists($mysqli,'accounts') && table_exists($mysqli,'driver_profile')) {
  $em = strtolower(trim($drv['u_email'] ?? ''));
  if ($em && ($s=$mysqli->prepare("SELECT dp.current_status FROM driver_profile dp JOIN accounts a ON a.id=dp.account_id WHERE LOWER(a.email)=? LIMIT 1"))) {
    $s->bind_param('s',$em);
    $s->execute(); $s->bind_result($cs);
    if ($s->fetch()) {
      $map = ['available'=>'Available','on_trip'=>'On Trip','off'=>'Not Available'];
      if (isset($map[strtolower($cs)])) $drv['u_car_book_status'] = $map[strtolower($cs)];
    }
    $s->close();
  }
}

/* Find assigned vehicle (computed) */
$assignedVehicle = find_assigned_vehicle($mysqli, $drv);
$assignedVehicleLabel = $assignedVehicle ? $assignedVehicle['label'] : '—';

?>
<!DOCTYPE html>
<html lang="en">
<?php include('vendor/inc/head.php'); ?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  html,body{font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
  .kaya-page-title{font-weight:800;font-size:2rem;line-height:1.1;color:#000047;margin:0 0 1rem}
  .kaya-card{background:#fff;border-radius:1rem;border:1px solid #e5e7eb;box-shadow:0 8px 24px rgba(0,0,0,.06);padding:1rem}
  .detail-row{display:flex;align-items:center;padding:.75rem 0;border-top:1px solid #f1f5f9}
  .detail-row:first-child{border-top:0}
  .detail-row .label{width:240px;color:#6b7280;font-weight:600}
  .kaya-toolbar .kaya-actions{display:flex;gap:.5rem;flex-wrap:wrap}
  .kaya-toolbar .btn{padding:.5rem .9rem;border-radius:.5rem;font-weight:600}
  .btn-kaya-primary{background:#0A0F2C;border:1px solid #0A0F2C;color:#fff}
  .btn-kaya-primary:hover{background:#0c1438;border-color:#0c1438}
  .btn-kaya-danger-outline{background:#fff;border:1px solid #dc2626;color:#dc2626}
  .btn-kaya-danger-outline:hover{background:#fee2e2}
</style>
<body id="page-top">
<?php include('vendor/inc/nav.php'); ?>
<div id="wrapper">
  <?php include('vendor/inc/sidebar.php'); ?>

  <div id="content-wrapper">
    <div class="container-fluid">

      <h1 class="kaya-page-title">
        Driver Details
        <?php if ($is_deleted): ?><span class="badge badge-danger ml-2">Deleted</span><?php endif; ?>
        <?php if ($src==='user'): ?><span class="badge badge-secondary ml-2">from tms_user</span><?php endif; ?>
      </h1>

      <?php if(!empty($succ)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <?= h($succ) ?>
          <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
      <?php endif; ?>
      <?php if(!empty($err)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <?= h($err) ?>
          <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
      <?php endif; ?>

      <div class="kaya-toolbar d-flex align-items-center mb-3">
        <div class="ml-auto kaya-actions">
          <a href="admin-manage-driver.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i>Back</a>
          <?php if ($src==='add'): ?>
            <a href="driver-edit.php?d_u_id=<?= (int)$drv['d_u_id'] ?>" class="btn btn-kaya-primary"><i class="fas fa-pen mr-1"></i>Edit</a>
            <?php if ($is_deleted): ?>
              <form method="post" class="d-inline" style="margin:0;">
                <input type="hidden" name="__action" value="delete_or_restore">
                <input type="hidden" name="d_u_id" value="<?= (int)$drv['d_u_id'] ?>">
                <button name="restore_driver" class="btn btn-success"><i class="fas fa-undo mr-1"></i>Restore</button>
              </form>
            <?php else: ?>
              <form method="post" class="d-inline" onsubmit="return confirm('Delete this driver?');" style="margin:0;">
                <input type="hidden" name="__action" value="delete_or_restore">
                <input type="hidden" name="d_u_id" value="<?= (int)$drv['d_u_id'] ?>">
                <button name="delete_driver" class="btn btn-kaya-danger-outline"><i class="fas fa-trash mr-1"></i>Delete</button>
              </form>
            <?php endif; ?>
          <?php endif; ?>

          <?php if (!empty($drv['u_email'])): ?>
            <button class="btn btn-outline-secondary" data-toggle="modal" data-target="#resetPwdModal">
              <i class="fas fa-key mr-1"></i> Reset Password
            </button>
          <?php endif; ?>
        </div>
      </div>

      <div class="kaya-card">
        <div class="mb-2 font-weight-bold" style="font-size:1.125rem;">
          <?= h(trim(($drv['u_fname']??'').' '.($drv['u_lname']??''))) ?>
          <span class="ml-2 align-middle"><?= status_badge_from_text($drv['u_car_book_status'] ?? '') ?></span>
        </div>

        <div class="detail-row"><div class="label">Email</div><div><?= h($drv['u_email'] ?: '—') ?></div></div>
        <div class="detail-row"><div class="label">Contact #</div><div><?= h($drv['u_phone'] ?: '—') ?></div></div>
        <div class="detail-row"><div class="label">Address</div><div><?= h($drv['u_addr'] ?: '—') ?></div></div>
        <div class="detail-row"><div class="label">Assigned Vehicle</div><div><?= h($assignedVehicleLabel) ?></div></div>
        <div class="detail-row"><div class="label">License #</div><div><?= h($drv['u_car_regno'] ?: '—') ?></div></div>
        <div class="detail-row"><div class="label">Status</div><div><?= status_badge_from_text($drv['u_car_book_status'] ?? '') ?></div></div>
      </div>

      <!-- Reset Password Modal -->
      <div class="modal fade" id="resetPwdModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
          <form method="post" autocomplete="off">
            <input type="hidden" name="__action" value="reset_password">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Reset Driver Password</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
              </div>
              <div class="modal-body">
                <div class="form-group">
                  <label>Email (login)</label>
                  <input type="email" class="form-control" value="<?= h($drv['u_email'] ?: '') ?>" readonly>
                </div>
                <div class="form-group">
                  <label>New Password</label>
                  <input type="password" name="new_password" class="form-control" minlength="6" required>
                </div>
                <div class="form-group">
                  <label>Confirm Password</label>
                  <input type="password" name="confirm_password" class="form-control" minlength="6" required>
                </div>
                <small class="text-muted d-block">This updates the account in <code>accounts</code>. If none exists, one will be created and linked in <code>driver_profile</code>.</small>
              </div>
              <div class="modal-footer">
                <button type="submit" class="btn btn-kaya-primary"><i class="fas fa-check mr-1"></i>Save</button>
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
              </div>
            </div>
          </form>
        </div>
      </div>

    </div>
    <?php include('vendor/inc/footer.php'); ?>
  </div>
</div>

<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="vendor/jquery-easing/jquery.easing.min.js"></script>
</body>
</html>
