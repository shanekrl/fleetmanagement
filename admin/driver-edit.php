<?php
// ========== KAYA · Edit Driver (no Vehicle/Type input; read-only Assigned Vehicle) ==========
session_start();
include('vendor/inc/config.php');
include('vendor/inc/checklogin.php');
check_login();
$aid = require_admin();

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
$has_soft_delete = column_exists($mysqli,'tms_user_add_driver','deleted_at');

/* Helper copied: find assigned vehicle label */
/* Helper: resolve assigned vehicle using the view (with legacy fallbacks) */
function find_assigned_vehicle(mysqli $db, array $drv): ?array {
  $mk = fn($row)=>['id'=>(int)$row['v_id'],
                   'label'=>trim(($row['v_name']?:'Vehicle').' ('.$row['v_reg_no'].')')];

  if (!table_exists($db,'tms_vehicle')) return null;

  $legacyId = (int)($drv['d_u_id'] ?? 0);
  $email    = strtolower(trim($drv['u_email'] ?? ''));
  $accId    = null;

  // resolve accounts.id for this driver (by email)
  if ($email && table_exists($db,'accounts')) {
    if ($st = $db->prepare("SELECT id FROM accounts WHERE LOWER(email)=? LIMIT 1")) {
      $st->bind_param('s',$email); $st->execute(); $st->bind_result($accId); $st->fetch(); $st->close();
    }
  }

  // --- NEW: prefer the driver→vehicle view ---
  if ($accId && table_exists($db,'v_driver_current_vehicle')) {
    if ($q=$db->prepare("SELECT v_id, v_name, v_reg_no FROM v_driver_current_vehicle WHERE driver_account_id=? LIMIT 1")) {
      $q->bind_param('i',$accId); $q->execute(); $res=$q->get_result();
      if ($v=$res->fetch_assoc()) { $q->close(); return $mk($v); }
      $q->close();
    }
  }

  // Fallback: live row in vehicle_assignments
  if ($accId && table_exists($db,'vehicle_assignments')) {
    $sql = "SELECT v.v_id, v.v_name, v.v_reg_no
              FROM vehicle_assignments va
              JOIN tms_vehicle v ON v.v_id = va.vehicle_id
             WHERE va.driver_id=? AND va.end_at IS NULL
             ORDER BY va.start_at DESC LIMIT 1";
    if ($q = $db->prepare($sql)) {
      $q->bind_param('i',$accId); $q->execute(); $res=$q->get_result();
      if ($v=$res->fetch_assoc()) { $q->close(); return $mk($v); }
      $q->close();
    }
  }

  // Legacy fallbacks (keep as-is)
  if ($legacyId > 0 && column_exists($db,'tms_vehicle','default_driver_id')) {
    if ($q=$db->prepare("SELECT v_id, v_name, v_reg_no FROM tms_vehicle WHERE default_driver_id=? LIMIT 1")) {
      $q->bind_param('i',$legacyId); $q->execute(); $res=$q->get_result();
      if ($v=$res->fetch_assoc()) { $q->close(); return $mk($v); }
      $q->close();
    }
  }
  if ($legacyId > 0 && column_exists($db,'tms_vehicle','driver_user_id')) {
    if ($q=$db->prepare("SELECT v_id, v_name, v_reg_no FROM tms_vehicle WHERE driver_user_id=? LIMIT 1")) {
      $q->bind_param('i',$legacyId); $q->execute(); $res=$q->get_result();
      if ($v=$res->fetch_assoc()) { $q->close(); return $mk($v); }
      $q->close();
    }
  }

  // Optional: last booking fallbacks (unchanged)
  if ($accId && table_exists($db,'bookings')) {
    $sql="SELECT v.v_id, v.v_name, v.v_reg_no
            FROM bookings b JOIN tms_vehicle v ON v.v_id=b.vehicle_id
           WHERE b.driver_id=? AND b.vehicle_id IS NOT NULL
           ORDER BY COALESCE(b.updated_at,b.created_at) DESC LIMIT 1";
    if ($q=$db->prepare($sql)) {
      $q->bind_param('i',$accId); $q->execute(); $res=$q->get_result();
      if ($v=$res->fetch_assoc()) { $q->close(); return $mk($v); }
      $q->close();
    }
  }
  if ($accId && table_exists($db,'tms_bookings')) {
    $sql="SELECT v.v_id, v.v_name, v.v_reg_no
            FROM tms_bookings b JOIN tms_vehicle v ON v.v_id=b.vehicle_id
           WHERE b.driver_id=? AND b.vehicle_id IS NOT NULL
           ORDER BY b.scheduled_at DESC LIMIT 1";
    if ($q=$db->prepare($sql)) {
      $q->bind_param('i',$accId); $q->execute(); $res=$q->get_result();
      if ($v=$res->fetch_assoc()) { $q->close(); return $mk($v); }
      $q->close();
    }
  }
  return null;
}




/* SAVE (we no longer edit vehicle/type — set it to empty) */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_driver'])) {
  $fname  = trim($_POST['u_fname'] ?? '');
  $lname  = trim($_POST['u_lname'] ?? '');
  $phone  = trim($_POST['u_phone'] ?? '');
  $addr   = trim($_POST['u_addr'] ?? '');
  $lic    = trim($_POST['u_car_regno'] ?? '');
  $status = trim($_POST['u_car_book_status'] ?? '');
  $email  = trim($_POST['u_email'] ?? '');
  $ctype  = ''; // drop vehicle/type usage

  if ($s = $mysqli->prepare("UPDATE tms_user_add_driver
                             SET u_fname=?, u_lname=?, u_phone=?, u_addr=?, u_car_type=?, u_car_regno=?, u_car_book_status=?, u_email=?
                             WHERE d_u_id=?".($has_soft_delete?" AND (deleted_at IS NULL OR deleted_at='')":"")." LIMIT 1")) {
    $s->bind_param('ssssssssi',$fname,$lname,$phone,$addr,$ctype,$lic,$status,$email,$did);
    $ok = $s->execute(); $s->close();
    if ($ok) { header("Location: admin-view-driver.php?src=add&d_u_id=".$did); exit; }
    $err = "Update failed. Please try again.";
  } else { $err = "DB error while preparing update."; }
}

/* FETCH current */
$drv = null;
$q = "SELECT d_u_id,u_fname,u_lname,u_phone,u_addr,u_car_regno,u_car_book_status,u_email".
     ($has_soft_delete?",deleted_at":"")."
      FROM tms_user_add_driver WHERE d_u_id=? LIMIT 1";
if ($s=$mysqli->prepare($q)) {
  $s->bind_param('i',$did); $s->execute(); $r=$s->get_result(); $drv=$r->fetch_assoc(); $s->close();
}
if (!$drv) { header('Location: admin-manage-driver.php'); exit; }
if ($has_soft_delete && !empty($drv['deleted_at'])) {
  header('Location: admin-view-driver.php?src=add&d_u_id='.$did); exit;
}
$assignedVehicle = find_assigned_vehicle($mysqli,$drv);
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

      <h1 class="kaya-page-title">Edit Driver</h1>

      <div class="kaya-toolbar d-flex align-items-center mb-3">
        <div class="ml-auto kaya-actions">
          <a href="admin-view-driver.php?src=add&d_u_id=<?= (int)$drv['d_u_id'] ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Back
          </a>
          <a href="admin-manage-driver.php" class="btn btn-kaya-danger-outline">Drivers</a>
        </div>
      </div>

      <?php if(!empty($err)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <?= h($err) ?>
          <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
      <?php endif; ?>

      <div class="kaya-card">
        <form method="post" autocomplete="off">
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>First Name</label>
              <input type="text" name="u_fname" class="form-control" required value="<?= h($drv['u_fname']) ?>">
            </div>
            <div class="form-group col-md-6">
              <label>Last Name</label>
              <input type="text" name="u_lname" class="form-control" value="<?= h($drv['u_lname']) ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Contact #</label>
              <input type="tel" name="u_phone" class="form-control" maxlength="32" value="<?= h($drv['u_phone']) ?>">
            </div>
            <div class="form-group col-md-6">
              <label>Email</label>
              <input type="email" name="u_email" class="form-control" value="<?= h($drv['u_email']) ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Address</label>
              <input type="text" name="u_addr" class="form-control" value="<?= h($drv['u_addr']) ?>">
            </div>
            <div class="form-group col-md-6">
              <label>Assigned Vehicle</label>
              <input type="text" class="form-control" value="<?= h($assignedVehicleLabel) ?>" readonly>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-6">
              <label>License #</label>
              <input type="text" name="u_car_regno" class="form-control" value="<?= h($drv['u_car_regno']) ?>">
            </div>
            <div class="form-group col-md-6">
              <label>Status</label>
              <select name="u_car_book_status" class="form-control">
                <?php
                  $statuses = ['Available','On Trip','Not Available'];
                  foreach ($statuses as $s) {
                    $sel = (stripos($drv['u_car_book_status'],$s)!==false || $drv['u_car_book_status']===$s) ? 'selected':''; echo "<option $sel>".h($s)."</option>";
                  }
                ?>
              </select>
            </div>
          </div>

          <div class="mt-3">
            <button class="btn btn-kaya-primary" name="save_driver" type="submit">Save Changes</button>
            <a class="btn btn-outline-secondary" href="admin-view-driver.php?src=add&d_u_id=<?= (int)$drv['d_u_id'] ?>">Cancel</a>
          </div>
        </form>
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
