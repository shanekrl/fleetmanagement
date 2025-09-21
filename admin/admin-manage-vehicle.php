<?php
/**
 * KAYA • Manage Vehicles (+ Vehicle Categories CRUD)
 */
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
function status_txt_and_class(?string $raw): array {
  $raw = (string)$raw;
  $txt = $raw ?: 'Available';
  $cls = 'status-service';
  if (stripos($raw,'avail')!==false){ $txt='Available';   $cls='status-available'; }
  if (stripos($raw,'service')!==false){ $txt='In Service'; $cls='status-service'; }
  if (stripos($raw,'maint')!==false){ $txt='Maintenance'; $cls='status-maint'; }
  if (stripos($raw,'book')!==false){ $txt='Booked';       $cls='status-service'; }
  return [$txt,$cls];
}
function vehicle_image_url($raw){
  if (!$raw) return '';
  if (preg_match('~^(https?:)?//~',$raw) || strpos($raw,'/')===0) return $raw;
  if (strpos($raw,'vendor/')===0) return $raw;
  return 'vendor/img/vehicles/'.ltrim($raw,'/');
}

/* ----------------- feature flags ----------------- */
$has_driver_fk   = column_exists($mysqli, 'tms_vehicle', 'driver_user_id');
$has_soft_delete = column_exists($mysqli, 'tms_vehicle', 'deleted_at');

/* ====== VEHICLE CATEGORIES support ====== */
$cat_table     = table_exists($mysqli, 'tms_vehicle_categories');
$cat_soft      = $cat_table && column_exists($mysqli,'tms_vehicle_categories','deleted_at');
$default_cats  = ['Bus','Sedan','SUV','Van'];

/** fetch active categories (or defaults) */
function fetch_categories(mysqli $db, bool $cat_table, bool $cat_soft, array $fallback): array {
  if (!$cat_table) return array_map(fn($n)=>['id'=>null,'name'=>$n], $fallback);
  $where = $cat_soft ? "WHERE deleted_at IS NULL AND is_active=1" : "WHERE is_active=1";
  $out = [];
  if ($q = $db->query("SELECT id,name FROM tms_vehicle_categories $where ORDER BY name")) {
    while ($r = $q->fetch_assoc()) $out[] = $r;
  }
  return $out ?: array_map(fn($n)=>['id'=>null,'name'=>$n], $fallback);
}

/** fetch all categories for Manage modal (includes deleted/active for visibility) */
function fetch_all_categories(mysqli $db, bool $cat_table): array {
  if (!$cat_table) return [];
  $out = [];
  if ($q = $db->query("SELECT id,name,is_active,deleted_at FROM tms_vehicle_categories ORDER BY name")) {
    while ($r=$q->fetch_assoc()) $out[] = $r;
  }
  return $out;
}

/* ----- Category POST: create ----- */
if (isset($_POST['create_category']) && $cat_table) {
  $name = trim($_POST['category_name'] ?? '');
  if ($name !== '') {
    if ($cat_soft) {
      if ($s=$mysqli->prepare("UPDATE tms_vehicle_categories SET deleted_at=NULL, is_active=1 WHERE name=?")) {
        $s->bind_param('s',$name); $s->execute(); $s->close();
        if ($mysqli->affected_rows>0) goto cat_done;
      }
    }
    if ($s=$mysqli->prepare("INSERT IGNORE INTO tms_vehicle_categories(name,is_active) VALUES(?,1)")) {
      $s->bind_param('s',$name); $s->execute(); $s->close();
    }
  }
  cat_done:
  echo "<script>setTimeout(function(){ location.href='admin-manage-vehicle.php'; }, 50);</script>";
  exit;
}

/* ----- Category POST: delete/restore ----- */
if (isset($_POST['delete_category']) && $cat_table) {
  $id = (int)($_POST['category_id'] ?? 0);
  if ($id>0) {
    if ($cat_soft) {
      if ($s=$mysqli->prepare("UPDATE tms_vehicle_categories SET deleted_at=NOW(), is_active=0 WHERE id=?")) {
        $s->bind_param('i',$id); $s->execute(); $s->close();
      }
    } else {
      if ($s=$mysqli->prepare("DELETE FROM tms_vehicle_categories WHERE id=?")) {
        $s->bind_param('i',$id); $s->execute(); $s->close();
      }
    }
  }
  echo "<script>setTimeout(function(){ location.href='admin-manage-vehicle.php'; }, 50);</script>";
  exit;
}
if ($cat_soft && isset($_POST['restore_category']) && $cat_table) {
  $id = (int)($_POST['category_id'] ?? 0);
  if ($id>0) {
    if ($s=$mysqli->prepare("UPDATE tms_vehicle_categories SET deleted_at=NULL, is_active=1 WHERE id=?")) {
      $s->bind_param('i',$id); $s->execute(); $s->close();
    }
  }
  echo "<script>setTimeout(function(){ location.href='admin-manage-vehicle.php'; }, 50);</script>";
  exit;
}

/* ----------------- view mode ----------------- */
$view = isset($_GET['view']) && $_GET['view']==='trash' && $has_soft_delete ? 'trash' : 'active';

/* ----------------- CREATE vehicle (POST) ----------------- */
if (isset($_POST['create_vehicle'])) {
  $v_name     = trim($_POST['v_name'] ?? '');
  $v_reg_no   = trim($_POST['v_reg_no'] ?? '');
  $v_pass_no  = (int)($_POST['v_pass_no'] ?? 0);
  $v_category = trim($_POST['v_category'] ?? 'Sedan');
  $v_status   = trim($_POST['v_status'] ?? 'Available');

  // driver coming from union list -> may be usr:ID or add:ID or empty
  $raw_driver = trim($_POST['driver_user_id'] ?? '');
  $driver_tag = $raw_driver !== '' ? explode(':', $raw_driver, 2) : [];
  $driver_src = $driver_tag[0] ?? '';
  $driver_val = $driver_tag[1] ?? '';

  $driver_u_id = null; // final tms_user.u_id to persist (or NULL)

  // image
  $v_dpic_path = null;
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
        $v_dpic_path = 'vendor/img/vehicles/' . $fname;
      }
    }
  }

  $mysqli->begin_transaction();
  try {
    if ($has_driver_fk) {
      // resolve driver source
      if ($driver_src === 'usr' && ctype_digit($driver_val)) {
        $driver_u_id = (int)$driver_val;
      } elseif ($driver_src === 'add' && ctype_digit($driver_val)) {
        // create a shadow tms_user row from tms_user_add_driver
        if ($s=$mysqli->prepare("SELECT u_fname,u_lname,u_phone,u_addr,u_email FROM tms_user_add_driver WHERE d_u_id=? LIMIT 1")) {
          $d_id = (int)$driver_val;
          $s->bind_param('i',$d_id); $s->execute();
          $s->bind_result($fn,$ln,$ph,$ad,$em);
          if ($s->fetch()) {
            $s->close();
            // tms_user requires many NOT NULL text columns; fill with blanks.
            $pwd = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
            $sql = "INSERT INTO tms_user
                    (u_fname,u_lname,u_car_pax,u_phone,u_addr,u_category,u_email,u_pwd,u_car_type,u_car_driver,u_car_regno,u_car_bookdate,u_car_pickup,u_car_destination,u_car_book_status,u_car_date,u_car_time)
                    VALUES (?,?, '', ?, ?, 'Driver', ?, ?, '', '', '', '', '', '', '', '', '')";
            if ($x=$mysqli->prepare($sql)) {
              $x->bind_param('ssssss',$fn,$ln,$ph,$ad,$em,$pwd);
              $x->execute(); $driver_u_id = $x->insert_id; $x->close();
            }
          } else { $s->close(); }
        }
      }

      // uniqueness: a driver must not be on another vehicle
      if ($driver_u_id) {
        if ($s = $mysqli->prepare("UPDATE tms_vehicle SET driver_user_id=NULL WHERE driver_user_id=?")) {
          $s->bind_param('i', $driver_u_id); $s->execute(); $s->close();
        }
      }
    }

    // insert vehicle
    if ($has_driver_fk) {
      $sql = "INSERT INTO tms_vehicle (v_name, v_reg_no, v_pass_no, v_category, v_status, v_dpic, driver_user_id)
              VALUES (?,?,?,?,?,?,?)";
      $s = $mysqli->prepare($sql);
      $s->bind_param('ssisssi', $v_name, $v_reg_no, $v_pass_no, $v_category, $v_status, $v_dpic_path, $driver_u_id);
    } else {
      $sql = "INSERT INTO tms_vehicle (v_name, v_reg_no, v_pass_no, v_category, v_status, v_dpic)
              VALUES (?,?,?,?,?,?)";
      $s = $mysqli->prepare($sql);
      $s->bind_param('ssisss', $v_name, $v_reg_no, $v_pass_no, $v_category, $v_status, $v_dpic_path);
    }
    $ok = $s->execute(); $s->close();
    if (!$ok) throw new Exception('insert failed');

    $mysqli->commit();
    echo "<script>
            setTimeout(function(){ swal('Created!','Vehicle has been added.','success'); }, 120);
            setTimeout(function(){ window.location.href='admin-manage-vehicle.php'; }, 900);
          </script>";
  } catch (Throwable $e) {
    $mysqli->rollback();
    echo "<script>setTimeout(function(){ swal('Error','Could not create vehicle.','error'); }, 120);</script>";
  }
}

/* ----------------- ASSIGN driver (POST) ----------------- */
if ($has_driver_fk && isset($_POST['assign_driver'])) {
  $vehicle_id = (int)($_POST['assign_vehicle_id'] ?? 0);
  $raw        = trim($_POST['assign_driver_id'] ?? ''); // '' | '0' | 'usr:ID' | 'add:ID'

  $driver_u_id = null;
  if ($raw !== '' && $raw !== '0') {
    [$src,$val] = array_pad(explode(':',$raw,2),2,'');
    if ($src==='usr' && ctype_digit($val)) {
      $driver_u_id = (int)$val;
    } elseif ($src==='add' && ctype_digit($val)) {
      // create shadow tms_user as above
      if ($s=$mysqli->prepare("SELECT u_fname,u_lname,u_phone,u_addr,u_email FROM tms_user_add_driver WHERE d_u_id=? LIMIT 1")) {
        $did = (int)$val;
        $s->bind_param('i',$did); $s->execute(); $s->bind_result($fn,$ln,$ph,$ad,$em);
        if ($s->fetch()) {
          $s->close();
          $pwd = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
          $sql = "INSERT INTO tms_user
                  (u_fname,u_lname,u_car_pax,u_phone,u_addr,u_category,u_email,u_pwd,u_car_type,u_car_driver,u_car_regno,u_car_bookdate,u_car_pickup,u_car_destination,u_car_book_status,u_car_date,u_car_time)
                  VALUES (?,?, '', ?, ?, 'Driver', ?, ?, '', '', '', '', '', '', '', '', '')";
          if ($x=$mysqli->prepare($sql)) {
            $x->bind_param('ssssss',$fn,$ln,$ph,$ad,$em,$pwd);
            $x->execute(); $driver_u_id = $x->insert_id; $x->close();
          }
        } else { $s->close(); }
      }
    }
  }

  $mysqli->begin_transaction();
  try {
    // clear current assign for this vehicle
    if ($s = $mysqli->prepare("UPDATE tms_vehicle SET driver_user_id=NULL WHERE v_id=?")) {
      $s->bind_param('i', $vehicle_id); $s->execute(); $s->close();
    }
    if ($driver_u_id) {
      // ensure driver isn't elsewhere
      if ($s = $mysqli->prepare("UPDATE tms_vehicle SET driver_user_id=NULL WHERE driver_user_id=?")) {
        $s->bind_param('i', $driver_u_id); $s->execute(); $s->close();
      }
      if ($s = $mysqli->prepare("UPDATE tms_vehicle SET driver_user_id=? WHERE v_id=?")) {
        $s->bind_param('ii', $driver_u_id, $vehicle_id); $s->execute(); $s->close();
      }
    }
    $mysqli->commit();
    echo "<script>
            setTimeout(function(){ swal('Saved','Assignment updated.','success'); }, 120);
            setTimeout(function(){ location.href='admin-manage-vehicle.php'; }, 900);
          </script>";
  } catch (Throwable $e) {
    $mysqli->rollback();
    echo "<script>setTimeout(function(){ swal('Error','Could not assign driver.','error'); }, 120);</script>";
  }
}

/* --- DELETE / RESTORE / PURGE vehicle --- */
if (isset($_POST['delete_vehicle'])) {
  $id = (int)($_POST['delete_vehicle_id'] ?? 0);
  if ($has_soft_delete) {
    if ($s = $mysqli->prepare("UPDATE tms_vehicle SET deleted_at=NOW(), deleted_by=? WHERE v_id=?")) {
      $s->bind_param('ii', $aid, $id); $s->execute(); $s->close();
    }
  } else {
    if ($s = $mysqli->prepare("DELETE FROM tms_vehicle WHERE v_id=?")) {
      $s->bind_param('i', $id); $s->execute(); $s->close();
    }
  }
  echo "<script>setTimeout(function(){ location.href='admin-manage-vehicle.php".($view==='trash'?"?view=trash":"")."'; }, 100);</script>";
}
if ($has_soft_delete && isset($_POST['restore_vehicle'])) {
  $id = (int)($_POST['restore_vehicle_id'] ?? 0);
  if ($s = $mysqli->prepare("UPDATE tms_vehicle SET deleted_at=NULL, deleted_by=NULL WHERE v_id=?")) {
    $s->bind_param('i', $id); $s->execute(); $s->close();
  }
  echo "<script>setTimeout(function(){ location.href='admin-manage-vehicle.php?restored=1'; }, 100);</script>";
}
if ($has_soft_delete && isset($_POST['purge_vehicle'])) {
  $id = (int)($_POST['purge_vehicle_id'] ?? 0);
  if ($s = $mysqli->prepare("DELETE FROM tms_vehicle WHERE v_id=?")) {
    $s->bind_param('i', $id); $s->execute(); $s->close();
  }
  echo "<script>setTimeout(function(){ location.href='admin-manage-vehicle.php?view=trash&purged=1'; }, 100);</script>";
}

/* ----------------- FETCH vehicles ----------------- */
$where = $has_soft_delete ? ($view==='trash' ? "v.deleted_at IS NOT NULL" : "v.deleted_at IS NULL") : "1=1";
$vehicles = [];
$sql = "SELECT v.v_id, v.v_name, v.v_reg_no, v.v_pass_no, v.v_category, v.v_status, v.v_dpic,
               ".($has_soft_delete ? "v.deleted_at," : "")."
               u.u_id AS driver_id, u.u_fname, u.u_lname
        FROM tms_vehicle v
        LEFT JOIN tms_user u ON ".($has_driver_fk ? "u.u_id = v.driver_user_id" : "0")."
        WHERE $where
        ORDER BY v.v_id DESC";
if ($stmt = $mysqli->prepare($sql)) {
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) $vehicles[] = $row;
  $stmt->close();
}

/* ----------------- FETCH drivers (UNION) ----------------- */
$drivers_usr = [];
$q1 = $mysqli->query("
  SELECT u.u_id,
         TRIM(CONCAT(COALESCE(u.u_fname,''),' ',COALESCE(u.u_lname,''))) AS name,
         (SELECT v_id FROM tms_vehicle WHERE driver_user_id=u.u_id LIMIT 1) AS current_vehicle_id
    FROM tms_user u
   WHERE u.u_category='Driver'
   ORDER BY name, u.u_id DESC
");
if ($q1) while($r=$q1->fetch_assoc()) $drivers_usr[]=$r;

$drivers_add = [];
$q2 = $mysqli->query("
  SELECT d_u_id,
         TRIM(CONCAT(COALESCE(u_fname,''),' ',COALESCE(u_lname,''))) AS name
    FROM tms_user_add_driver
   WHERE (deleted_at IS NULL OR deleted_at='')
     AND u_category='Driver'
   ORDER BY name, d_u_id DESC
");
if ($q2) while($r=$q2->fetch_assoc()) $drivers_add[]=$r;

/* ----------------- FETCH categories for UI ----------------- */
$categories_active = fetch_categories($mysqli, $cat_table, $cat_soft, $default_cats);
$categories_all    = fetch_all_categories($mysqli, $cat_table);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include('vendor/inc/head.php'); ?>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    html,body{font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
    .kaya-page-title{font-weight:800;font-size:2rem;line-height:1.1;color:#000047;margin:0 0 1rem}
    .kaya-card{background:#fff;border-radius:1rem;box-shadow:0 8px 24px rgba(0,0,0,.06);padding:1rem;border:1px solid #e5e7eb}
    .kaya-table thead th{font-weight:600;color:#6b7280;border:0}
    .kaya-table tbody td{border-top:1px solid #f1f5f9;vertical-align:middle}
    .btn-icon{width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;border-radius:.5rem;padding:0}
    .actions .btn-icon + .btn-icon{margin-left:.25rem}
    .status-available{color:#16a34a;font-weight:600}
    .status-service{color:#2563eb;font-weight:600}
    .status-maint{color:#dc2626;font-weight:600}
    .btn-kaya-primary{background:#0A0F2C;border:1px solid #0A0F2C;color:#fff}
    .btn-kaya-primary:hover{background:#0c1438;border-color:#0c1438;color:#fff}
    .veh-title{display:flex;align-items:center;gap:.5rem}
    .veh-thumb{width:40px;height:28px;object-fit:cover;border-radius:.25rem;border:1px solid #e5e7eb}
    .veh-sub{color:#6b7280;font-size:.85rem}
    .muted{color:#6b7280}
  </style>
  
</head>
<body id="page-top">
  <?php include('vendor/inc/nav.php'); ?>
  <div id="wrapper">
    <?php include('vendor/inc/sidebar.php'); ?>
    <div id="content-wrapper">
      <div class="container-fluid">

        <h1 class="kaya-page-title">Manage Vehicles <?= $view==='trash' ? '<span class="badge badge-danger ml-2">Trash</span>' : '' ?></h1>

        <div class="kaya-toolbar d-flex align-items-center mb-3">
          <div class="ml-auto">
            <?php if ($view==='trash'): ?>
              <a class="btn btn-outline-secondary mr-2" href="admin-manage-vehicle.php">
                <i class="fas fa-arrow-left mr-1"></i> Back to List
              </a>
            <?php else: ?>
              <?php if ($has_soft_delete): ?>
                <a class="btn btn-outline-secondary mr-2" href="admin-manage-vehicle.php?view=trash">
                  <i class="fas fa-trash mr-1"></i> Trash
                </a>
              <?php endif; ?>
              <button class="btn btn-outline-secondary mr-2" data-toggle="modal" data-target="#manageCategoriesModal">
                <i class="fas fa-tags mr-1"></i> Manage Categories
              </button>
              <button class="btn btn-kaya-primary" data-toggle="modal" data-target="#createVehicleModal">
                <i class="fas fa-plus mr-1"></i> New Vehicle
              </button>
            <?php endif; ?>
          </div>
        </div>

        <div class="kaya-card">
          <div class="table-responsive">
            <table id="vehiclesTable" class="table kaya-table table-hover table-borderless align-middle">
              <thead class="thead-light">
                <tr>
                  <th style="width:56px">#</th>
                  <th>Vehicle</th>
                  <th>Driver</th>
                  <th>Type</th>
                  <th>Status</th>
                  <th class="actions">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php $n=1; foreach($vehicles as $v):
                  $vid = (int)$v['v_id'];
                  $title = $v['v_name'] ?: ($v['v_reg_no'] ?: '—');
                  $plate = $v['v_reg_no'] ?: '';
                  $img   = vehicle_image_url($v['v_dpic'] ?? '');
                  $driverLabel = ($has_driver_fk && $v['driver_id']) ? trim(($v['u_fname']??'').' '.($v['u_lname']??'')) : '—';
                  list($statusTxt,$statusCls) = status_txt_and_class($v['v_status'] ?? '');
                ?>
                <tr>
                  <td><?= $n++; ?></td>
                  <td>
                    <div class="veh-title">
                      <?php if($img): ?><img class="veh-thumb" src="<?= h($img) ?>" alt=""><?php endif; ?>
                      <div>
                        <div class="font-weight-semibold"><?= h($title) ?></div>
                        <div class="veh-sub"><?= $plate ? h($plate) : '&nbsp;' ?></div>
                      </div>
                    </div>
                  </td>
                  <td><?= h($driverLabel) ?></td>
                  <td><?= h($v['v_category']) ?></td>
                  <td class="<?= $statusCls ?>"><?= h($statusTxt) ?></td>
                  <td class="actions">
                    <a href="admin-view-vehicle.php?v_id=<?= $vid ?>" class="btn btn-sm btn-outline-secondary btn-icon" data-toggle="tooltip" title="View">
                      <i class="fas fa-info-circle"></i><span class="sr-only">View</span>
                    </a>
                    <?php if ($view!=='trash'): ?>
                      <a href="admin-manage-single-vehicle.php?v_id=<?= $vid ?>" class="btn btn-sm btn-outline-secondary btn-icon" data-toggle="tooltip" title="Edit">
                        <i class="fas fa-pencil-alt"></i><span class="sr-only">Edit</span>
                      </a>
                      <?php if ($has_driver_fk): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary btn-icon"
                                data-toggle="modal" data-target="#assignDriverModal"
                                data-vehicle-id="<?= $vid ?>"
                                data-current-driver-id="<?= (int)($v['driver_id']??0) ?>"
                                title="Assign / Change Driver">
                          <i class="fas fa-exchange-alt"></i><span class="sr-only">Assign</span>
                        </button>
                      <?php endif; ?>
                      <a href="admin-view-syslogs.php?reg=<?= urlencode($v['v_reg_no']) ?>" class="btn btn-sm btn-outline-secondary btn-icon" data-toggle="tooltip" title="Monitor">
                        <i class="fas fa-eye"></i><span class="sr-only">Monitor</span>
                      </a>
                      <button type="button" class="btn btn-sm btn-outline-danger btn-icon"
                              data-toggle="modal" data-target="#deleteVehicleModal"
                              data-vehicle-id="<?= $vid ?>" title="Delete">
                        <i class="fas fa-trash"></i><span class="sr-only">Delete</span>
                      </button>
                    <?php else: ?>
                      <form method="post" class="d-inline">
                        <input type="hidden" name="restore_vehicle_id" value="<?= $vid ?>">
                        <button name="restore_vehicle" class="btn btn-sm btn-success btn-icon" title="Restore">
                          <i class="fas fa-undo"></i><span class="sr-only">Restore</span>
                        </button>
                      </form>
                      <form method="post" class="d-inline" onsubmit="return confirm('Permanently delete this vehicle?');">
                        <input type="hidden" name="purge_vehicle_id" value="<?= $vid ?>">
                        <button name="purge_vehicle" class="btn btn-sm btn-outline-danger btn-icon" title="Purge">
                          <i class="fas fa-times"></i><span class="sr-only">Purge</span>
                        </button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <?php if ($view!=='trash'): ?>
        <!-- Create Vehicle Modal -->
        <div class="modal fade" id="createVehicleModal" tabindex="-1" role="dialog" aria-hidden="true">
          <div class="modal-dialog modal-lg" role="document">
            <form method="POST" enctype="multipart/form-data">
              <div class="modal-content" style="background:#f8fafc;color:#0f172a">
                <div class="modal-header">
                  <h5 class="modal-title">Create New Vehicle</h5>
                  <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                  <input type="hidden" name="create_vehicle" value="1">
                  <div class="form-row">
                    <div class="form-group col-md-6">
                      <label>Name</label>
                      <input type="text" required class="form-control" name="v_name">
                    </div>
                    <div class="form-group col-md-6">
                      <label>Plate Number</label>
                      <input type="text" class="form-control" name="v_reg_no">
                    </div>
                  </div>

                  <div class="form-row">
                    <div class="form-group col-md-4">
                      <label>Pax</label>
                      <input type="number" class="form-control" name="v_pass_no" min="0" value="0">
                    </div>
                    <div class="form-group col-md-4">
                      <label>Vehicle Category</label>
                      <select class="form-control" name="v_category">
                        <?php foreach($categories_active as $c): ?>
                          <option><?= h($c['name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <?php if (!$cat_table): ?>
                        <small class="muted">Tip: create <code>tms_vehicle_categories</code> table to manage these.</small>
                      <?php endif; ?>
                    </div>
                    <div class="form-group col-md-4">
                      <label>Status</label>
                      <select class="form-control" name="v_status">
                        <option>Available</option><option>Booked</option><option>UnderMaintenance</option>
                      </select>
                    </div>
                  </div>

                  <?php if ($has_driver_fk): ?>
                  <div class="form-row">
                    <div class="form-group col-md-6">
                      <label>Assign Driver (optional)</label>
                      <select class="form-control" name="driver_user_id">
                        <option value="">— None —</option>
                        <!-- existing tms_user drivers -->
                        <?php foreach($drivers_usr as $d): if (empty($d['current_vehicle_id'])): ?>
                          <option value="usr:<?= (int)$d['u_id'] ?>">👤 <?= h($d['name'] ?: ('Driver #'.(int)$d['u_id'])) ?></option>
                        <?php endif; endforeach; ?>
                        <!-- add-table drivers (will auto-create shadow on submit) -->
                        <?php foreach($drivers_add as $d): ?>
                          <option value="add:<?= (int)$d['d_u_id'] ?>">➕ <?= h($d['name'] ?: ('Driver+'.(int)$d['d_u_id'])) ?></option>
                        <?php endforeach; ?>
                      </select>
                      <small class="text-muted">“➕” items will be synced to <code>tms_user</code> automatically.</small>
                    </div>
                    <div class="form-group col-md-6">
                      <label>Vehicle Picture</label>
                      <input type="file" class="form-control" name="v_dpic" accept="image/*">
                    </div>
                  </div>
                  <?php else: ?>
                  <div class="form-row">
                    <div class="form-group col-md-12">
                      <label>Vehicle Picture</label>
                      <input type="file" class="form-control" name="v_dpic" accept="image/*">
                    </div>
                  </div>
                  <?php endif; ?>
                </div>

                <div class="modal-footer">
                  <button type="submit" class="btn btn-kaya-primary">Create Vehicle</button>
                  <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
                </div>
              </div>
            </form>
          </div>
        </div>

        <!-- Manage Categories Modal -->
        <div class="modal fade" id="manageCategoriesModal" tabindex="-1" role="dialog" aria-hidden="true">
          <div class="modal-dialog" role="document">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Vehicle Categories</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
              </div>
              <div class="modal-body">
                <?php if ($cat_table): ?>
                  <form method="post" class="mb-3 d-flex" style="gap:.5rem;align-items:center;">
                    <input type="hidden" name="create_category" value="1">
                    <input type="text" name="category_name" class="form-control" placeholder="New category name" required>
                    <button class="btn btn-kaya-primary" type="submit">Add</button>
                  </form>

                  <div class="list-group">
                    <?php if (!$categories_all): ?>
                      <div class="text-muted">No categories yet.</div>
                    <?php endif; ?>
                    <?php foreach ($categories_all as $c): ?>
                      <div class="list-group-item d-flex align-items-center justify-content-between">
                        <div>
                          <strong><?= h($c['name']) ?></strong>
                          <?php if (!empty($c['deleted_at'])): ?>
                            <span class="badge badge-danger ml-2">Deleted</span>
                          <?php elseif (!$c['is_active']): ?>
                            <span class="badge badge-secondary ml-2">Inactive</span>
                          <?php endif; ?>
                        </div>
                        <div>
                          <?php if (!empty($c['deleted_at'])): ?>
                            <form method="post" class="d-inline">
                              <input type="hidden" name="restore_category" value="1">
                              <input type="hidden" name="category_id" value="<?= (int)$c['id'] ?>">
                              <button class="btn btn-sm btn-success">Restore</button>
                            </form>
                          <?php else: ?>
                            <form method="post" onsubmit="return confirm('Remove this category?');" class="d-inline m-0">
                              <input type="hidden" name="delete_category" value="1">
                              <input type="hidden" name="category_id" value="<?= (int)$c['id'] ?>">
                              <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                          <?php endif; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <small class="text-muted d-block mt-2">
                    <?= $cat_soft ? 'Deleting moves a category to Trash (soft delete). You can restore it here.' : 'Deleting removes it permanently.' ?>
                    Vehicles already using a deleted category keep their text value.
                  </small>
                <?php else: ?>
                  <div class="alert alert-info mb-0">
                    To manage categories here, create the table <code>tms_vehicle_categories</code> with a UNIQUE <code>name</code> column.
                    Until then, we’ll use defaults: <?= h(implode(', ', $default_cats)) ?>.
                  </div>
                <?php endif; ?>
              </div>
              <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>

        <!-- ========== Assign/Change Driver Modal ========== -->
        <?php if ($has_driver_fk && $view!=='trash'): ?>
        <div class="modal fade" id="assignDriverModal" tabindex="-1" role="dialog" aria-hidden="true">
          <div class="modal-dialog" role="document">
            <form method="POST">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Assign / Change Driver</h5>
                  <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                  <input type="hidden" name="assign_driver" value="1">
                  <input type="hidden" name="assign_vehicle_id" id="assign_vehicle_id">
                  <label>Driver</label>
                  <select class="form-control" name="assign_driver_id" id="assign_driver_id">
                    <option value="0">— None —</option>
                    <!-- populate with JS (unassigned usr: + all add:) -->
                  </select>
                  <small class="text-muted d-block mt-2">Items with “➕” will be created in <code>tms_user</code> first, then assigned.</small>
                </div>
                <div class="modal-footer">
                  <button type="submit" class="btn btn-kaya-primary">Save</button>
                  <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                </div>
              </div>
            </form>
          </div>
        </div>
        <?php endif; ?>

        <!-- ========== Delete Vehicle Modal ========== -->
        <?php if ($view!=='trash'): ?>
        <div class="modal fade" id="deleteVehicleModal" tabindex="-1" role="dialog" aria-hidden="true">
          <div class="modal-dialog" role="document">
            <form method="POST">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Delete Vehicle</h5>
                  <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                  <p class="mb-2"><?= $has_soft_delete ? 'This will move the vehicle to Trash.' : 'This will permanently delete the vehicle.' ?></p>
                  <select class="form-control" name="delete_vehicle_id" id="delete_vehicle_id">
                    <?php foreach($vehicles as $v): ?>
                      <option value="<?= (int)$v['v_id'] ?>"><?= h(($v['v_name'] ?: ($v['v_reg_no'] ?: 'Vehicle')).' — '.$v['v_category']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="modal-footer">
                  <button type="submit" name="delete_vehicle" class="btn btn-outline-danger">Delete</button>
                  <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                </div>
              </div>
            </form>
          </div>
        </div>
        <?php endif; ?>

      </div>
      <?php include('vendor/inc/footer.php'); ?>
    </div>
  </div>

  <a class="scroll-to-top rounded" href="#page-top"><i class="fas fa-angle-up"></i></a>

  <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
  <script src="vendor/datatables/jquery.dataTables.js"></script>
  <script src="vendor/datatables/dataTables.bootstrap4.js"></script>
  <script src="js/sb-admin.min.js"></script>

  <!-- Drivers data for JS (usr + add) -->
  <script>
    const DRIVERS_USR = <?= json_encode($drivers_usr, JSON_UNESCAPED_UNICODE) ?>;
    const DRIVERS_ADD = <?= json_encode($drivers_add, JSON_UNESCAPED_UNICODE) ?>;
  </script>

  <script>
    $(function(){ $('[data-toggle="tooltip"]').tooltip(); });

    $('#vehiclesTable').DataTable({
      pageLength: 10,
      lengthMenu: [10,25,50,100],
      order: [[0,'desc']],
      columnDefs: [{targets:-1, orderable:false, searchable:false}]
    });

    // Pre-fill Delete modal
    $('#deleteVehicleModal').on('show.bs.modal', function (e) {
      var id = $(e.relatedTarget).data('vehicle-id');
      if (id) $('#delete_vehicle_id').val(id);
    });

    // Build driver options for assign modal
    $('#assignDriverModal').on('show.bs.modal', function(e){
      var $btn = $(e.relatedTarget);
      var vehicleId = parseInt($btn.data('vehicle-id'),10) || 0;
      var currentDriverId = parseInt($btn.data('current-driver-id'),10) || 0;

      $('#assign_vehicle_id').val(vehicleId);
      var $sel = $('#assign_driver_id').empty().append($('<option/>').val('0').text('— None —'));

      // 1) Unassigned existing usr drivers OR the one currently on this car
      DRIVERS_USR
        .filter(d => !d.current_vehicle_id || d.current_vehicle_id == vehicleId)
        .sort((a,b)=>(a.name||'').localeCompare(b.name||''))
        .forEach(d=>{
          var opt = $('<option/>').val('usr:'+d.u_id).text('👤 '+(d.name||('Driver #'+d.u_id)));
          if (d.u_id == currentDriverId) opt.attr('selected', true);
          $sel.append(opt);
        });

      // 2) All add-table drivers (will be created on submit)
      DRIVERS_ADD
        .sort((a,b)=>(a.name||'').localeCompare(b.name||''))
        .forEach(d=>{
          $sel.append($('<option/>').val('add:'+d.d_u_id).text('➕ '+(d.name||('Driver+'+d.d_u_id))));
        });
    });
  </script>
</body>
</html>
