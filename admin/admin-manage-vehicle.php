<?php
/**
 * KAYA • Manage Vehicles (+ Vehicle Categories CRUD)
 * Driver assignment uses vehicle_assignments + accounts (no add-table fallback in UI)
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
$hasSoftDelete = column_exists($mysqli,'tms_vehicle','deleted_at');
$hasVehAssigns = table_exists($mysqli,'vehicle_assignments');
$hasAccounts   = table_exists($mysqli,'accounts');

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

/** fetch all categories for Manage modal (includes deleted/active) */
function fetch_all_categories(mysqli $db, bool $cat_table): array {
  if (!$cat_table) return [];
  $out = [];
  if ($q = $db->query("SELECT id,name,is_active,deleted_at FROM tms_vehicle_categories ORDER BY name")) {
    while ($r=$q->fetch_assoc()) $out[] = $r;
  }
  return $out;
}

/* ----------------- view mode ----------------- */
$view = (isset($_GET['view']) && $_GET['view']==='trash' && $hasSoftDelete) ? 'trash' : 'active';

/* ----------------- CREATE vehicle (POST) ----------------- */
if (isset($_POST['create_vehicle'])) {
  $v_name     = trim($_POST['v_name'] ?? '');
  $v_reg_no   = trim($_POST['v_reg_no'] ?? '');
  $v_pass_no  = (int)($_POST['v_pass_no'] ?? 0);
  $v_category = trim($_POST['v_category'] ?? 'Sedan');
  $v_status   = trim($_POST['v_status'] ?? 'Available');

  // Selected driver: "", "acc:ID"
  $rawDriver  = trim($_POST['driver_pick'] ?? '');
  $driverSrc  = ''; $driverVal = '';
  if ($rawDriver !== '') { [$driverSrc,$driverVal] = array_pad(explode(':',$rawDriver,2),2,''); }

  // image
  $v_dpic_path = '';
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
    // Insert vehicle
    $sql = "INSERT INTO tms_vehicle (v_name,v_reg_no,v_pass_no,v_category,v_status,v_dpic, v_driver, driver_user_id, default_driver_id)
            VALUES (?,?,?,?,?,?, '', NULL, NULL)";
    $s = $mysqli->prepare($sql);
    $s->bind_param('ssisss', $v_name,$v_reg_no,$v_pass_no,$v_category,$v_status,$v_dpic_path);
    if (!$s->execute()) throw new Exception('insert vehicle failed');
    $vehicleId = (int)$mysqli->insert_id;
    $s->close();

    // Optional initial driver (accounts only)
    if ($rawDriver !== '' && $driverSrc === 'acc' && ctype_digit($driverVal)) {
      $driverId = (int)$driverVal;
      if ($hasVehAssigns) {
        if ($x=$mysqli->prepare("UPDATE vehicle_assignments SET end_at=NOW() WHERE (driver_id=? OR vehicle_id=?) AND end_at IS NULL")) {
          $x->bind_param('ii',$driverId,$vehicleId); $x->execute(); $x->close();
        }
        if ($x=$mysqli->prepare("INSERT INTO vehicle_assignments(vehicle_id,driver_id,assigned_by,start_at) VALUES(?,?,?,NOW())")) {
          $x->bind_param('iii',$vehicleId,$driverId,$aid); $x->execute(); $x->close();
        }
      }
    }

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

/* ----------------- ASSIGN / CHANGE driver (POST) ----------------- */
if (isset($_POST['assign_driver'])) {
  $vehicleId = (int)($_POST['assign_vehicle_id'] ?? 0);
  $raw       = trim($_POST['assign_driver_id'] ?? ''); // '0' | 'acc:ID'

  $mysqli->begin_transaction();
  try {
    if ($raw === '0' || $raw === '') {
      // Clear active assignment for this vehicle
      if ($hasVehAssigns) {
        if ($s=$mysqli->prepare("UPDATE vehicle_assignments SET end_at=NOW() WHERE vehicle_id=? AND end_at IS NULL")) {
          $s->bind_param('i',$vehicleId); $s->execute(); $s->close();
        }
      }
      // Keep default_driver_id untouched; UI no longer uses it.
    } else {
      [$src,$val] = array_pad(explode(':',$raw,2),2,'');
      if ($src==='acc' && ctype_digit($val)) {
        $driverId = (int)$val;

        if ($hasVehAssigns) {
          if ($s=$mysqli->prepare("UPDATE vehicle_assignments SET end_at=NOW() WHERE (driver_id=? OR vehicle_id=?) AND end_at IS NULL")) {
            $s->bind_param('ii',$driverId,$vehicleId); $s->execute(); $s->close();
          }
          if ($s=$mysqli->prepare("INSERT INTO vehicle_assignments(vehicle_id,driver_id,assigned_by,start_at) VALUES(?,?,?,NOW())")) {
            $s->bind_param('iii',$vehicleId,$driverId,$aid); $s->execute(); $s->close();
          }
        }
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
  if ($hasSoftDelete) {
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
if ($hasSoftDelete && isset($_POST['restore_vehicle'])) {
  $id = (int)($_POST['restore_vehicle_id'] ?? 0);
  if ($s = $mysqli->prepare("UPDATE tms_vehicle SET deleted_at=NULL, deleted_by=NULL WHERE v_id=?")) {
    $s->bind_param('i', $id); $s->execute(); $s->close();
  }
  echo "<script>setTimeout(function(){ location.href='admin-manage-vehicle.php?restored=1'; }, 100);</script>";
}
if ($hasSoftDelete && isset($_POST['purge_vehicle'])) {
  $id = (int)($_POST['purge_vehicle_id'] ?? 0);
  if ($s = $mysqli->prepare("DELETE FROM tms_vehicle WHERE v_id=?")) {
    $s->bind_param('i', $id); $s->execute(); $s->close();
  }
  echo "<script>setTimeout(function(){ location.href='admin-manage-vehicle.php?view=trash&purged=1'; }, 100);</script>";
}

/* ----------------- FETCH vehicles ----------------- */
$where = $hasSoftDelete ? ($view==='trash' ? "v.deleted_at IS NOT NULL" : "v.deleted_at IS NULL") : "1=1";
$vehicles = [];
$sql = "
SELECT v.v_id, v.v_name, v.v_reg_no, v.v_pass_no, v.v_category, v.v_status, v.v_dpic,
       ".($hasSoftDelete ? "v.deleted_at," : "")."
       va.driver_id       AS driver_account_id,
       a.name             AS driver_name
FROM tms_vehicle v
LEFT JOIN vehicle_assignments va
       ON va.vehicle_id = v.v_id AND va.end_at IS NULL
LEFT JOIN accounts a
       ON a.id = va.driver_id
WHERE $where
ORDER BY v.v_id DESC
";
if ($stmt = $mysqli->prepare($sql)) {
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) $vehicles[] = $row;
  $stmt->close();
}

/* ----------------- FETCH drivers (accounts only) ----------------- */
$drivers_acc = [];
if ($hasAccounts) {
  $q = $mysqli->query("
    SELECT a.id,
           a.name,
           (SELECT vehicle_id FROM vehicle_assignments 
             WHERE driver_id=a.id AND end_at IS NULL LIMIT 1) AS current_vehicle_id
      FROM accounts a
     WHERE a.role='driver' AND a.is_active=1
     ORDER BY a.name, a.id DESC
  ");
  if ($q) while($r=$q->fetch_assoc()) $drivers_acc[]=$r;
}

/* ----------------- CATEGORIES for UI ----------------- */
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
              <?php if ($hasSoftDelete): ?>
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
                  $vid   = (int)$v['v_id'];
                  $title = $v['v_name'] ?: ($v['v_reg_no'] ?: '—');
                  $plate = $v['v_reg_no'] ?: '';
                  $img   = vehicle_image_url($v['v_dpic'] ?? '');
                  $driverLabel = !empty($v['driver_name']) ? $v['driver_name'] : '—';
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
                      <button type="button" class="btn btn-sm btn-outline-secondary btn-icon"
                              data-toggle="modal" data-target="#assignDriverModal"
                              data-vehicle-id="<?= $vid ?>"
                              data-current-driver-id="<?= (int)($v['driver_account_id']??0) ?>"
                              title="Assign / Change Driver">
                        <i class="fas fa-exchange-alt"></i><span class="sr-only">Assign</span>
                      </button>
                      <a href="admin-view-syslogs.php?PlateNo=<?= urlencode($v['v_reg_no']) ?>" class="btn btn-sm btn-outline-secondary btn-icon" data-toggle="tooltip" title="Monitor">
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

                  <div class="form-row">
                    <div class="form-group col-md-6">
                      <label>Assign Driver (optional)</label>
                      <select class="form-control" name="driver_pick">
                        <option value="">— None —</option>
                        <?php foreach($drivers_acc as $d): if (empty($d['current_vehicle_id'])): ?>
                          <option value="acc:<?= (int)$d['id'] ?>"><?= h($d['name'] ?: ('Driver #'.(int)$d['id'])) ?></option>
                        <?php endif; endforeach; ?>
                      </select>
                    </div>
                    <div class="form-group col-md-6">
                      <label>Vehicle Picture</label>
                      <input type="file" class="form-control" name="v_dpic" accept="image/*">
                    </div>
                  </div>
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
        <?php if ($view!=='trash'): ?>
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
                  </select>
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
                  <p class="mb-2"><?= $hasSoftDelete ? 'This will move the vehicle to Trash.' : 'This will permanently delete the vehicle.' ?></p>
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

  <!-- Drivers data for JS (accounts only) -->
  <script>
    const DRIVERS_ACC = <?= json_encode($drivers_acc, JSON_UNESCAPED_UNICODE) ?>; // {id, name, current_vehicle_id}
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

    // Build driver options for assign modal (accounts only)
    $('#assignDriverModal').on('show.bs.modal', function(e){
      var $btn = $(e.relatedTarget);
      var vehicleId = parseInt($btn.data('vehicle-id'),10) || 0;
      var currentDriverId = parseInt($btn.data('current-driver-id'),10) || 0;

      $('#assign_vehicle_id').val(vehicleId);
      var $sel = $('#assign_driver_id').empty().append($('<option/>').val('0').text('— None —'));

      DRIVERS_ACC
        .filter(d => !d.current_vehicle_id || parseInt(d.current_vehicle_id,10) === vehicleId)
        .sort((a,b)=>(a.name||'').localeCompare(b.name||''))
        .forEach(d=>{
          var opt = $('<option/>').val('acc:'+d.id).text(d.name||('Driver #'+d.id));
          if (parseInt(d.id,10) === currentDriverId) opt.attr('selected', true);
          $sel.append(opt);
        });
    });
  </script>
</body>
</html>
