<?php
session_start();
include('vendor/inc/config.php');
include('vendor/inc/checklogin.php');
check_login();

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

/* ----- category helpers ----- */
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

/* New-schema link flag */
$has_driver_fk = column_exists($mysqli, 'tms_vehicle', 'default_driver_id');

$succ = $err = '';

/* ----------------- create driver (modal POST) — NEW SCHEMA ----------------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_driver'])) {
  $name = trim(($_POST['u_fname'] ?? '').' '.($_POST['u_lname'] ?? ''));
  $name = trim($name);
  $phone = trim($_POST['u_phone'] ?? '');
  $email = trim($_POST['u_email'] ?? '');
  $assignNow = !empty($_POST['assign_to_vehicle']) && $has_driver_fk;

  if ($name === '' && $email === '') {
    $err = 'Please enter a name or email for the driver.';
  } elseif (!table_exists($mysqli,'accounts')) {
    $err = 'Accounts table is missing.';
  } else {
    $mysqli->begin_transaction();
    try {
      // avoid duplicate emails
      $accId = null;
      if ($email) {
        if ($st=$mysqli->prepare("SELECT id FROM accounts WHERE LOWER(email)=LOWER(?) LIMIT 1 FOR UPDATE")) {
          $st->bind_param('s',$email); $st->execute(); $st->bind_result($accId); $st->fetch(); $st->close();
        }
      }
      if (!$accId) {
        $pwdHash = password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT);
        if ($st=$mysqli->prepare("INSERT INTO accounts(role,name,email,password_hash,phone,is_active) VALUES('driver',?,?,?,?,1)")) {
          $st->bind_param('ssss',$name,$email,$pwdHash,$phone);
          $st->execute(); $accId=$st->insert_id; $st->close();
        } else { throw new Exception('prepare accounts insert failed'); }

        // optional driver_profile seed
        if (table_exists($mysqli,'driver_profile')) {
          if ($p=$mysqli->prepare("INSERT INTO driver_profile(account_id,current_status) VALUES(?, 'available')")) {
            $p->bind_param('i',$accId); $p->execute(); $p->close();
          }
        }
      }

      if ($assignNow && $accId) {
        // ensure one-vehicle-per-driver (optional)
        if ($c=$mysqli->prepare("UPDATE tms_vehicle SET default_driver_id=NULL WHERE default_driver_id=? AND v_id<>?")) {
          $c->bind_param('ii',$accId,$vehId); $c->execute(); $c->close();
        }
        if ($u=$mysqli->prepare("UPDATE tms_vehicle SET default_driver_id=? WHERE v_id=?")) {
          $u->bind_param('ii',$accId,$vehId); $u->execute(); $u->close();
        }
      }

      $mysqli->commit();
      $succ = 'Driver '.($email?:$name?:('ID#'.$accId)).' created'.($assignNow?' and assigned to this vehicle.':'.');
    } catch(Throwable $e){
      $mysqli->rollback();
      // 1062 duplicate key (email)
      if ($mysqli->errno === 1062) $err = 'That email is already in use.';
      else $err = 'Could not create driver. Please try again.';
    }
  }
}

/* ----------------- update vehicle (POST) — NEW SCHEMA ----------------- */
if (isset($_POST['update_veh']) || isset($_POST['upate_veh'])) { // keep typo-compatible
  $v_name     = trim($_POST['v_name'] ?? '');
  $v_reg_no   = trim($_POST['v_reg_no'] ?? '');
  $v_category = trim($_POST['v_category'] ?? '');
  $v_status   = trim($_POST['v_status'] ?? '');
  $v_dpic     = $_POST['__current_dpic'] ?? ''; // hidden field to keep current

  // upload (optional)
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

  // driver from accounts.id
  $default_driver_id = null;
  if ($has_driver_fk) {
    $raw = trim($_POST['default_driver_id'] ?? '');
    if ($raw !== '' && ctype_digit($raw)) $default_driver_id = (int)$raw;
  }

  $mysqli->begin_transaction();
  try {
    if ($has_driver_fk) {
      if ($default_driver_id) {
        if ($c=$mysqli->prepare("UPDATE tms_vehicle SET default_driver_id=NULL WHERE default_driver_id=? AND v_id<>?")) {
          $c->bind_param('ii',$default_driver_id,$vehId); $c->execute(); $c->close();
        }
      }
      $sql = "UPDATE tms_vehicle
              SET v_name=?, v_reg_no=?, v_category=?, v_dpic=?, v_status=?, default_driver_id=?
              WHERE v_id=?";
      if ($s=$mysqli->prepare($sql)) {
        $drv = $default_driver_id ?: null;
        $s->bind_param('ssssssi',$v_name,$v_reg_no,$v_category,$v_dpic,$v_status,$drv,$vehId);
        $s->execute(); $s->close();
      }
    } else {
      // If column truly not there, just save other fields
      $sql = "UPDATE tms_vehicle
              SET v_name=?, v_reg_no=?, v_category=?, v_dpic=?, v_status=?
              WHERE v_id=?";
      if ($s=$mysqli->prepare($sql)) {
        $s->bind_param('sssssi',$v_name,$v_reg_no,$v_category,$v_dpic,$v_status,$vehId);
        $s->execute(); $s->close();
      }
    }

    $mysqli->commit();
    $succ = "Vehicle updated.";
  } catch(Throwable $e){
    $mysqli->rollback();
    $err = "Please try again later.";
  }
}

/* ----------------- fetch vehicle (with driver via accounts) ----------------- */
$vehicle = null;
$sql = "SELECT v.v_id, v.v_name, v.v_reg_no, v.v_category, v.v_status, v.v_dpic,
               ".($has_driver_fk ? "v.default_driver_id" : "NULL AS default_driver_id").",
               a.name AS driver_name, a.email AS driver_email
        FROM tms_vehicle v
        LEFT JOIN accounts a ON ".($has_driver_fk ? "a.id = v.default_driver_id" : "0")."
        WHERE v.v_id=? LIMIT 1";
if ($s=$mysqli->prepare($sql)) {
  $s->bind_param('i',$vehId);
  $s->execute();
  $vehicle = $s->get_result()->fetch_assoc();
  $s->close();
}
if (!$vehicle) { header('Location: admin-manage-vehicle.php'); exit; }

/* ----------------- driver list from accounts ----------------- */
$drivers_accounts = [];
if (table_exists($mysqli,'accounts')) {
  $q = $mysqli->query("
    SELECT id, COALESCE(NULLIF(TRIM(name),''), email, CONCAT('Driver #',id)) AS label
    FROM accounts
    WHERE role='driver' AND is_active=1
    ORDER BY label
  ");
  if ($q) while ($r=$q->fetch_assoc()) $drivers_accounts[] = $r;
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

        <?php if($succ): ?>
          <script>setTimeout(function(){ swal("Success!", "<?= h($succ) ?>", "success"); }, 80);</script>
        <?php endif; ?>
        <?php if($err): ?>
          <script>setTimeout(function(){ swal("Failed!", "<?= h($err) ?>", "error"); }, 80);</script>
        <?php endif; ?>

        <section class="kaya-card p-3 p-md-4">
          <form method="POST" enctype="multipart/form-data">
            <div class="row">
              <!-- Left column -->
              <div class="col-lg-8">
                <div class="form-group">
                  <label class="font-weight-semibold">Vehicle Name</label>
                  <input type="text" name="v_name" required class="form-control"
                         value="<?= h($vehicle['v_name'] ?? '') ?>">
                </div>

                <div class="form-group">
                  <label class="font-weight-semibold">Vehicle Registration Number</label>
                  <input type="text" name="v_reg_no" class="form-control"
                         value="<?= h($vehicle['v_reg_no'] ?? '') ?>">
                </div>

                <?php if ($has_driver_fk): ?>
                  <div class="form-group">
                    <label class="font-weight-semibold">Driver</label>
                    <div class="d-flex" style="gap:.5rem;align-items:center;">
                      <select class="form-control" name="default_driver_id" id="default_driver_id">
                        <option value="">— None —</option>
                        <?php
                          $current = (int)($vehicle['default_driver_id'] ?? 0);
                          foreach ($drivers_accounts as $d) {
                            $sel = ($current===(int)$d['id']) ? 'selected' : '';
                            echo '<option value="'.(int)$d['id'].'" '.$sel.'>'.h($d['label']).'</option>';
                          }
                        ?>
                      </select>
                      <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="modal" data-target="#createDriverModal">
                        <i class="fas fa-user-plus mr-1"></i> New
                      </button>
                    </div>
                    <small class="text-muted d-block mt-1">
                      Uses <code>accounts</code> (role=<em>driver</em>) and saves to <code>tms_vehicle.default_driver_id</code>.
                    </small>
                  </div>
                <?php else: ?>
                  <div class="form-group">
                    <label class="font-weight-semibold">Driver (unlinked)</label>
                    <input type="text" class="form-control" placeholder="Add column tms_vehicle.default_driver_id to link a driver">
                  </div>
                <?php endif; ?>

                <div class="form-row">
                  <div class="form-group col-md-6">
                    <label class="font-weight-semibold">Vehicle Category</label>
                    <select class="form-control" name="v_category">
                      <?php
                        $curr = $vehicle['v_category'] ?? '';
                        foreach ($categories_active as $c) {
                          $name = $c['name'];
                          $sel = ($name===$curr)?'selected':'';
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

              <!-- Right column: image -->
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

  <!-- Create Driver Modal (accounts) -->
  <div class="modal fade" id="createDriverModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <form method="post">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Create Driver</h5>
            <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="create_driver" value="1">
            <div class="form-row">
              <div class="form-group col-md-6">
                <label>First Name</label>
                <input type="text" class="form-control" name="u_fname">
              </div>
              <div class="form-group col-md-6">
                <label>Last Name</label>
                <input type="text" class="form-control" name="u_lname">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Phone</label>
                <input type="text" class="form-control" name="u_phone">
              </div>
              <div class="form-group col-md-6">
                <label>Email</label>
                <input type="email" class="form-control" name="u_email">
              </div>
            </div>
            <?php if ($has_driver_fk): ?>
              <div class="custom-control custom-checkbox">
                <input type="checkbox" class="custom-control-input" id="assign_to_vehicle" name="assign_to_vehicle" checked>
                <label class="custom-control-label" for="assign_to_vehicle">Assign to this vehicle after creating</label>
              </div>
            <?php endif; ?>
            <small class="text-muted d-block mt-2">
              Creates a driver in <code>accounts</code> (role=<em>driver</em>) and optionally links it to this vehicle.
            </small>
          </div>
          <div class="modal-footer">
            <button class="btn btn-kaya-primary" type="submit">Create</button>
            <button class="btn btn-outline-secondary" type="button" data-dismiss="modal">Cancel</button>
          </div>
        </div>
      </form>
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
