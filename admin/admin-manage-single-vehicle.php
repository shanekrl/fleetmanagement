<?php
session_start();
include('vendor/inc/config.php');
include('vendor/inc/checklogin.php');
check_login();
$aid = require_admin();

$mysqli->set_charset('utf8mb4');

/* ----------------- helpers ----------------- */
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
function vehicle_image_url($raw){
  if (!$raw) return '';
  if (preg_match('~^(https?:)?//~',$raw) || strpos($raw,'/')===0) return $raw;
  if (strpos($raw,'vendor/')===0) return $raw;
  return 'vendor/img/vehicles/'.ltrim($raw,'/');
}

/* ----- feature flags / categories ----- */
$hasVehAssigns = table_exists($mysqli,'vehicle_assignments');
$hasAccounts   = table_exists($mysqli,'accounts');

$default_cats  = ['Bus','Sedan','SUV','Van'];
$cat_table     = table_exists($mysqli, 'tms_vehicle_categories');
$cat_soft      = $cat_table && column_exists($mysqli,'tms_vehicle_categories','deleted_at');

function fetch_categories(mysqli $db, bool $cat_table, bool $cat_soft, array $fallback): array {
  if (!$cat_table) return array_map(fn($n)=>['id'=>null,'name'=>$n], $fallback);
  $where = $cat_soft ? "WHERE deleted_at IS NULL AND is_active=1" : "WHERE is_active=1";
  $out = [];
  if ($q = $db->query("SELECT id,name FROM tms_vehicle_categories $where ORDER BY name")) {
    while ($r = $q->fetch_assoc()) $out[] = $r;
  }
  return $out ?: array_map(fn($n)=>['id'=>null,'name'=>$n], $fallback);
}

/* ----------------- resolve id ----------------- */
$vehId = isset($_GET['v_id']) ? (int)$_GET['v_id'] : 0;
if ($vehId <= 0) { header('Location: admin-manage-vehicle.php'); exit; }

$succ = $err = '';

/* ----------------- update vehicle (fields + assignment) ----------------- */
if (isset($_POST['update_veh'])) {
  $v_name     = trim($_POST['v_name'] ?? '');
  $v_reg_no   = trim($_POST['v_reg_no'] ?? '');
  $v_category = trim($_POST['v_category'] ?? '');
  $v_status   = trim($_POST['v_status'] ?? '');
  $v_dpic     = $_POST['__current_dpic'] ?? '';

  // assignment from the main form (numeric id or "0" for none)
  $assign_driver_id = isset($_POST['assign_driver_id']) && ctype_digit((string)$_POST['assign_driver_id'])
                      ? (int)$_POST['assign_driver_id'] : 0;

  // optional image upload
  if (!empty($_FILES['v_dpic']['name']) && is_uploaded_file($_FILES['v_dpic']['tmp_name'])) {
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $mime = @mime_content_type($_FILES['v_dpic']['tmp_name']);
    if (isset($allowed[$mime])) {
      $ext  = $allowed[$mime];
      $dir  = __DIR__ . '/vendor/img/vehicles';
      if (!is_dir($dir)) @mkdir($dir, 0775, true);
      $fname = 'veh_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
      $dest  = $dir . '/' . $fname;
      if (@move_uploaded_file($_FILES['v_dpic']['tmp_name'], $dest)) {
        $v_dpic = 'vendor/img/vehicles/' . $fname;
      }
    }
  }

  $mysqli->begin_transaction();
  try {
    // 1) update vehicle fields
    if ($s=$mysqli->prepare("UPDATE tms_vehicle SET v_name=?, v_reg_no=?, v_category=?, v_dpic=?, v_status=? WHERE v_id=?")) {
      $s->bind_param('sssssi',$v_name,$v_reg_no,$v_category,$v_dpic,$v_status,$vehId);
      $s->execute(); $s->close();
    }

    // 2) update assignment (if tables present)
    if ($hasVehAssigns && $hasAccounts) {
      if ($assign_driver_id === 0) {
        // clear current assignment for this vehicle
        if ($s=$mysqli->prepare("UPDATE vehicle_assignments SET end_at=NOW() WHERE vehicle_id=? AND end_at IS NULL")) {
          $s->bind_param('i',$vehId); $s->execute(); $s->close();
        }
      } else {
        // move driver to this vehicle: close any open for either side, then create a new one
        if ($s=$mysqli->prepare("UPDATE vehicle_assignments SET end_at=NOW() WHERE (driver_id=? OR vehicle_id=?) AND end_at IS NULL")) {
          $s->bind_param('ii',$assign_driver_id,$vehId); $s->execute(); $s->close();
        }
        if ($s=$mysqli->prepare("INSERT INTO vehicle_assignments(vehicle_id,driver_id,assigned_by,start_at) VALUES(?,?,?,NOW())")) {
          $s->bind_param('iii',$vehId,$assign_driver_id,$aid); $s->execute(); $s->close();
        }
      }
    }

    $mysqli->commit();
    $succ = "Vehicle updated.";
  } catch(Throwable $e){
    $mysqli->rollback();
    $err = "Please try again later.";
  }
}

/* ----------------- fetch vehicle & current assignment ----------------- */
$vehicle = null;
$sql = "SELECT v.v_id, v.v_name, v.v_reg_no, v.v_category, v.v_status, v.v_dpic
        FROM tms_vehicle v WHERE v.v_id=? LIMIT 1";
if ($s=$mysqli->prepare($sql)) {
  $s->bind_param('i',$vehId);
  $s->execute();
  $vehicle = $s->get_result()->fetch_assoc();
  $s->close();
}
if (!$vehicle) { header('Location: admin-manage-vehicle.php'); exit; }

$current_driver_id = 0;
$current_driver_name = '';
if ($hasVehAssigns) {
  $q = $mysqli->prepare("
    SELECT va.driver_id, a.name
      FROM vehicle_assignments va
      JOIN accounts a ON a.id = va.driver_id
     WHERE va.vehicle_id=? AND va.end_at IS NULL
     LIMIT 1
  ");
  $q->bind_param('i',$vehId); $q->execute();
  $q->bind_result($current_driver_id,$current_driver_name); $q->fetch(); $q->close();
}

/* ----------------- driver list from accounts (available or on this car) ----------------- */
$drivers_acc = [];
if ($hasAccounts) {
  $q = $mysqli->query("
    SELECT a.id, a.name,
           (SELECT vehicle_id FROM vehicle_assignments WHERE driver_id=a.id AND end_at IS NULL LIMIT 1) AS current_vehicle_id
      FROM accounts a
     WHERE a.role='driver' AND a.is_active=1
     ORDER BY a.name, a.id DESC
  ");
  if ($q) while($r=$q->fetch_assoc()) $drivers_acc[]=$r;
}

/* ----------------- categories for EDIT select ----------------- */
$categories_active = fetch_categories($mysqli, $cat_table, $cat_soft, $default_cats);

/* ----------------- image url ----------------- */
$img = vehicle_image_url($vehicle['v_dpic'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<?php include('vendor/inc/head.php'); ?>
<body id="page-top">
  <?php include("vendor/inc/nav.php"); ?>
  <div id="wrapper">
    <?php include("vendor/inc/sidebar.php"); ?>

    <div id="content-wrapper">
      <div class="container-fluid">

        <h1 class="kaya-page-title">Edit Vehicle</h1>

        <div class="kaya-toolbar d-flex align-items-center mb-3">
          <div class="ml-auto">
            <a href="admin-manage-vehicle.php" class="btn btn-outline-secondary">
              <i class="fas fa-arrow-left mr-1"></i> Back to Vehicles
            </a>
          </div>
        </div>

        <?php if($succ): ?><script>setTimeout(function(){ swal("Success!", "<?= h($succ) ?>", "success"); }, 80);</script><?php endif; ?>
        <?php if($err):  ?><script>setTimeout(function(){ swal("Failed!", "<?= h($err) ?>", "error"); }, 80);</script><?php endif; ?>

        <section class="kaya-card p-3 p-md-4">
          <form method="POST" enctype="multipart/form-data">
            <div class="row">
              <div class="col-lg-8">
                <div class="form-group">
                  <label class="font-weight-semibold">Vehicle Name</label>
                  <input type="text" name="v_name" required class="form-control" value="<?= h($vehicle['v_name'] ?? '') ?>">
                </div>

                <div class="form-group">
                  <label class="font-weight-semibold">Vehicle Registration Number</label>
                  <input type="text" name="v_reg_no" class="form-control" value="<?= h($vehicle['v_reg_no'] ?? '') ?>">
                </div>

                <div class="form-group">
                  <label class="font-weight-semibold">Driver</label>
                  <select class="form-control" name="assign_driver_id">
                    <option value="0">— None —</option>
                    <?php foreach($drivers_acc as $d):
                      if (empty($d['current_vehicle_id']) || (int)$d['current_vehicle_id']===$vehId):
                        $sel = ((int)$d['id'] === (int)$current_driver_id) ? 'selected' : '';
                    ?>
                      <option value="<?= (int)$d['id'] ?>" <?= $sel ?>><?= h($d['name'] ?: ('Driver #'.(int)$d['id'])) ?></option>
                    <?php endif; endforeach; ?>
                  </select>
                </div>

                <div class="form-row">
                  <div class="form-group col-md-6">
                    <label class="font-weight-semibold">Vehicle Category</label>
                    <select class="form-control" name="v_category">
                      <?php
                        $curr = $vehicle['v_category'] ?? '';
                        foreach ($categories_active as $c) {
                          $name = $c['name']; $sel = ($name===$curr)?'selected':'';
                          echo "<option $sel>".h($name)."</option>";
                        }
                      ?>
                    </select>
                    <?php if (!$cat_table): ?>
                      <small class="text-muted d-block mt-1">Tip: create <code>tms_vehicle_categories</code> to manage category list.</small>
                    <?php endif; ?>
                  </div>

                  <div class="form-group col-md-6">
                    <label class="font-weight-semibold">Vehicle Status</label>
                    <select class="form-control" name="v_status">
                      <?php
                        $statuses = ['Available','Booked','UnderMaintenance'];
                        $curS = $vehicle['v_status'] ?? '';
                        foreach ($statuses as $s) {
                          $sel = ($s===$curS)?'selected':'';
                          echo "<option $sel>".h($s)."</option>";
                        }
                      ?>
                    </select>
                  </div>
                </div>

                <div class="mt-3">
                  <input type="hidden" name="__current_dpic" value="<?= h($vehicle['v_dpic'] ?? '') ?>">
                  <button type="submit" name="update_veh" class="btn btn-kaya-primary">
                    <i class="fas fa-save mr-1"></i> Update Vehicle
                  </button>
                  <a href="admin-manage-vehicle.php" class="btn btn-outline-secondary ml-2">Cancel</a>
                </div>
              </div>

              <div class="col-lg-4 mt-4 mt-lg-0">
                <div class="card shadow-sm" style="border-radius:.75rem; overflow:hidden;">
                  <?php if ($img): ?>
                    <img src="<?= h($img) ?>" class="card-img-top" alt="Vehicle photo">
                  <?php else: ?>
                    <div class="d-flex align-items-center justify-content-center"
                         style="height:220px;background:#f8fafc;color:#6b7280">
                      <i class="fas fa-image fa-2x mr-2"></i> No photo
                    </div>
                  <?php endif; ?>
                  <div class="card-body">
                    <label class="font-weight-semibold d-block mb-2">Vehicle Picture</label>
                    <input type="file" class="form-control-file" name="v_dpic" accept="image/*">
                    <small class="text-muted d-block mt-2">Leave blank to keep the current image.</small>
                  </div>
                </div>
              </div>
            </div>
          </form>
        </section>

      </div>
      <?php include("vendor/inc/footer.php"); ?>
    </div>
  </div>

  <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

  <style>
    footer.sticky-footer{ background:transparent!important; height:0!important; border:0!important; box-shadow:none!important; }
    footer.sticky-footer .container, footer.sticky-footer .copyright{ display:none!important; }
    #wrapper #content-wrapper{ padding-bottom:0!important; }
    .form-group label{ color:#0f172a; }
  </style>
</body>
</html>
