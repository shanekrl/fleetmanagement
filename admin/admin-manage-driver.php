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
function status_text_and_class($raw){
  $raw = (string)$raw;
  $txt='Not Available'; $cls='status-off';
  if ($raw==='' || stripos($raw,'avail')!==false){ $txt='Available'; $cls='status-available'; }
  elseif (stripos($raw,'trip')!==false || stripos($raw,'book')!==false || stripos($raw,'service')!==false){ $txt='On Trip'; $cls='status-trip'; }
  return [$txt,$cls];
}
function profile_status_to_text_class($s){
  $s = strtolower((string)$s);
  if ($s==='available') return ['Available','status-available'];
  if ($s==='on_trip')   return ['On Trip','status-trip'];
  return ['Not Available','status-off'];
}

$has_soft_delete = column_exists($mysqli,'tms_user_add_driver','deleted_at');
$view = ($has_soft_delete && isset($_GET['view']) && $_GET['view']==='trash') ? 'trash' : 'active';

$HAS_ACCOUNTS = table_exists($mysqli,'accounts');
$HAS_PROFILE  = table_exists($mysqli,'driver_profile');

/* ----------------- CREATE (Add Driver + Account) ----------------- */
if (isset($_POST['add_driver'])) {
  $fname   = trim($_POST['u_fname'] ?? '');
  $lname   = trim($_POST['u_lname'] ?? '');
  $phone   = trim($_POST['u_phone'] ?? '');
  $addr    = trim($_POST['u_addr'] ?? '');
  $ctype   = ''; // vehicle/type not used anymore
  $lic     = trim($_POST['u_car_regno'] ?? '');
  $status  = trim($_POST['u_car_book_status'] ?? 'Available');
  $email   = trim($_POST['u_email'] ?? '');
  $pwd     = (string)($_POST['login_password'] ?? '');
  $cat     = 'Driver';
  $upwd    = ''; // legacy (NOT NULL), keep empty; logins use accounts

  if (!$email || !$pwd) {
    $err = "Email and password are required to create the driver account.";
  } elseif (!$HAS_ACCOUNTS || !$HAS_PROFILE) {
    $err = "User accounts or driver profiles table is missing.";
  } else {
    try {
      $mysqli->begin_transaction();

      // accounts
      $accId = null;
      if ($s = $mysqli->prepare("INSERT INTO accounts (role,name,email,password_hash,phone,is_active) VALUES ('driver',?,?,?,?,1)")) {
        $name = trim($fname.' '.$lname);
        $hash = password_hash($pwd, PASSWORD_BCRYPT);
        $s->bind_param('ssss',$name,$email,$hash,$phone);
        $s->execute();
        $accId = (int)$s->insert_id;
        $s->close();
      } else { throw new Exception('Failed to prepare accounts insert'); }

      // driver_profile
      if ($s = $mysqli->prepare("INSERT INTO driver_profile (account_id, license_no, address, current_status) VALUES (?,?,?, 'available')")) {
        $s->bind_param('iss',$accId,$lic,$addr);
        $s->execute();
        $s->close();
      } else { throw new Exception('Failed to prepare driver_profile insert'); }

      // legacy insert (IMPORTANT: include u_id = $accId; fix bind types count)
      if ($s = $mysqli->prepare("INSERT INTO tms_user_add_driver
          (u_id,u_fname,u_lname,u_phone,u_addr,u_car_type,u_car_regno,u_car_book_status,u_category,u_email,u_pwd)
          VALUES (?,?,?,?,?,?,?,?,?,?,?)")) {
        $s->bind_param(
          'issssssssss',
          $accId, $fname, $lname, $phone, $addr, $ctype, $lic, $status, $cat, $email, $upwd
        );
        $s->execute();
        $s->close();
      } else { throw new Exception('Failed to prepare legacy driver insert'); }

      $mysqli->commit();
      $succ = "Driver and account created.";
    } catch (Throwable $e) {
      $mysqli->rollback();
      if ($mysqli->errno === 1062) {
        $err = "That email is already in use. Please use a different email.";
      } else {
        $err = "Creation failed. ".$e->getMessage();
      }
    }
  }
}


/* ----------------- DELETE / RESTORE / PURGE ----------------- */
if (isset($_POST['delete_driver'])) {
  $id = (int)($_POST['delete_driver_id'] ?? 0);
  if ($id > 0) {
    if ($has_soft_delete) {
      // move to trash
      if ($s=$mysqli->prepare("UPDATE tms_user_add_driver SET deleted_at=NOW(), deleted_by=? WHERE d_u_id=?")) {
        $s->bind_param('ii',$aid,$id); $s->execute(); $s->close();
      }
      // also DEACTIVATE the linked driver account
      $mysqli->query("
        UPDATE accounts a
        JOIN tms_user_add_driver d ON d.u_id = a.id
        SET a.is_active = 0
        WHERE d.d_u_id = {$id} AND a.role='driver'
      ");
      $succ = "Driver moved to Trash and account deactivated.";
    } else {
      // hard delete (legacy)
      // optionally deactivate or delete the account/profile here as policy
      if ($s=$mysqli->prepare("DELETE FROM tms_user_add_driver WHERE d_u_id=?")) {
        $s->bind_param('i',$id); $s->execute(); $s->close();
      }
      $succ = "Driver deleted.";
    }
  }
}

if ($has_soft_delete && isset($_POST['restore_driver'])) {
  $id = (int)($_POST['restore_driver_id'] ?? 0);
  if ($id>0) {
    if ($s=$mysqli->prepare("UPDATE tms_user_add_driver SET deleted_at=NULL, deleted_by=NULL WHERE d_u_id=?")) {
      $s->bind_param('i',$id); $s->execute(); $s->close();
    }
    // also ACTIVATE the linked driver account
    $mysqli->query("
      UPDATE accounts a
      JOIN tms_user_add_driver d ON d.u_id = a.id
      SET a.is_active = 1
      WHERE d.d_u_id = {$id} AND a.role='driver'
    ");
    $succ = "Driver restored and account activated.";
  }
}

if ($has_soft_delete && isset($_POST['purge_driver'])) {
  $id = (int)($_POST['purge_driver_id'] ?? 0);
  if ($id>0) {
    // pick policy: keep account but deactivate (safer)
    $accId = null;
    if ($s=$mysqli->prepare("SELECT u_id FROM tms_user_add_driver WHERE d_u_id=?")) {
      $s->bind_param('i',$id); $s->execute(); $s->bind_result($accId); $s->fetch(); $s->close();
    }
    if ($s=$mysqli->prepare("DELETE FROM tms_user_add_driver WHERE d_u_id=?")) {
      $s->bind_param('i',$id); $s->execute(); $s->close();
    }
    if ($accId) {
      if ($p=$mysqli->prepare("DELETE FROM driver_profile WHERE account_id=?")) {
        $p->bind_param('i',$accId); $p->execute(); $p->close();
      }
      $mysqli->query("UPDATE accounts SET is_active=0 WHERE id={$accId} AND role='driver'");
    }
    $succ = "Driver permanently deleted (account deactivated).";
  }
}


/* ----------------- FETCH LISTS ----------------- */
$drivers_add = [];
$whereAdd = $has_soft_delete ? ($view==='trash' ? "driver.deleted_at IS NOT NULL" : "driver.deleted_at IS NULL") : "1=1";
  if ($s=$mysqli->prepare("
      SELECT driver.d_u_id, driver.u_fname, driver.u_lname, driver.u_phone, driver.u_addr,
            '' AS u_car_type, driver.u_car_regno, driver.u_car_book_status, driver.u_email
      FROM tms_user_add_driver AS driver
      WHERE u_category='Driver' AND $whereAdd
      ORDER BY d_u_id DESC")) {
  $s->execute();
  $r=$s->get_result();
  while($row=$r->fetch_assoc()){ $row['_src']='add'; $drivers_add[]=$row; }
  $s->close();
}

$drivers_user = [];
$INCLUDE_LEGACY_TMS_USER = false;
if ($INCLUDE_LEGACY_TMS_USER && $mysqli->query("SHOW TABLES LIKE 'tms_user'")->num_rows) {
  if ($s=$mysqli->prepare("
      SELECT u_id, u_fname, u_lname, u_phone, u_addr, u_email,
             COALESCE(NULLIF(u_car_book_status,''),'Available') AS status_text
      FROM tms_user
      WHERE u_category='Driver'
      ORDER BY u_id DESC")) {
    $s->execute();
    $r=$s->get_result();
    while($row=$r->fetch_assoc()){
      $drivers_user[] = [
        'd_u_id'            => (int)$row['u_id'],
        'u_fname'           => $row['u_fname'],
        'u_lname'           => $row['u_lname'],
        'u_phone'           => $row['u_phone'],
        'u_addr'            => $row['u_addr'],
        'u_car_type'        => '',
        'u_car_regno'       => '',
        'u_car_book_status' => $row['status_text'],
        'u_email'           => $row['u_email'],
        '_src'              => 'user'
      ];
    }
    $s->close();
  }
}

// profile override
$profileStatusByEmail = [];
if ($HAS_ACCOUNTS && $HAS_PROFILE) {
  $sql = "SELECT a.email, dp.current_status
          FROM driver_profile dp
          JOIN accounts a ON a.id = dp.account_id";
  if ($q = $mysqli->query($sql)) {
    while ($row = $q->fetch_assoc()) {
      $emailKey = strtolower(trim($row['email'] ?? ''));
      if ($emailKey) $profileStatusByEmail[$emailKey] = $row['current_status'];
    }
    $q->close();
  }
}

$drivers = [];
$seen = [];
foreach ($drivers_add as $d) {
  $key = strtolower(trim($d['u_email'] ?: $d['u_phone'] ?: ($d['u_fname'].'|'.$d['u_lname'])));
  $seen[$key] = true;
  $em = strtolower(trim($d['u_email'] ?? ''));
  if ($em && isset($profileStatusByEmail[$em])) {
    [$txt,$cls] = profile_status_to_text_class($profileStatusByEmail[$em]);
    $d['u_car_book_status'] = $txt;
    $d['_status_class_override'] = $cls;
  }
  $drivers[] = $d;
}
foreach ($drivers_user as $d) {
  $key = strtolower(trim($d['u_email'] ?: $d['u_phone'] ?: ($d['u_fname'].'|'.$d['u_lname'])));
  if (isset($seen[$key])) continue;
  if ($view==='trash') continue;
  $em = strtolower(trim($d['u_email'] ?? ''));
  if ($em && isset($profileStatusByEmail[$em])) {
    [$txt,$cls] = profile_status_to_text_class($profileStatusByEmail[$em]);
    $d['u_car_book_status'] = $txt;
    $d['_status_class_override'] = $cls;
  }
  $drivers[] = $d;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include('vendor/inc/head.php'); ?>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    html,body{font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
    .kaya-page-title{font-weight:800;font-size:2rem;color:#000047;margin:0 0 1rem}
    .kaya-card{background:#fff;border-radius:1rem;border:1px solid #e5e7eb;box-shadow:0 8px 24px rgba(0,0,0,.06);padding:1rem}
    .kaya-table thead th{font-weight:600;color:#6b7280;border:0}
    .kaya-table tbody td{border-top:1px solid #f1f5f9;vertical-align:middle}
    .btn-kaya-primary{background:#0A0F2C;border:1px solid #0A0F2C;color:#fff}
    .btn-kaya-primary:hover{background:#0c1438;border-color:#0c1438}
    .btn-kaya-danger-outline{background:#fff;border:1px solid #dc2626;color:#dc2626}
    .btn-kaya-danger-outline:hover{background:#fee2e2}
    .kaya-toolbar .btn{padding:.5rem .9rem;border-radius:.5rem;font-weight:600}
    .status-available{color:#16a34a;font-weight:600}
    .status-trip{color:#2563eb;font-weight:600}
    .status-off{color:#dc2626;font-weight:600}
    .actions{display:flex;gap:.4rem}
    .actions .btn{padding:.375rem .5rem;border-radius:.5rem}
    .muted{color:#6b7280}
  </style>
</head>
<body id="page-top">
<?php include('vendor/inc/nav.php'); ?>
<div id="wrapper">
  <?php include('vendor/inc/sidebar.php'); ?>
  <div id="content-wrapper">
    <div class="container-fluid">

      <h1 class="kaya-page-title">Manage Drivers <?= $view==='trash' ? '<span class="badge badge-danger ml-2">Trash</span>' : '' ?></h1>

      <?php if(!empty($succ)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <?= h($succ) ?>
          <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span>&times;</span></button>
        </div>
      <?php endif; ?>
      <?php if(!empty($err)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <?= h($err) ?>
          <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span>&times;</span></button>
        </div>
      <?php endif; ?>

      <div class="kaya-toolbar d-flex align-items-center mb-3">
        <div class="ml-auto">
          <?php if ($view==='trash'): ?>
            <a class="btn btn-outline-secondary mr-2" href="admin-manage-driver.php">
              <i class="fas fa-arrow-left mr-1"></i> Back to List
            </a>
          <?php else: ?>
            <?php if ($has_soft_delete): ?>
              <a class="btn btn-outline-secondary mr-2" href="admin-manage-driver.php?view=trash">
                <i class="fas fa-trash mr-1"></i> Trash
              </a>
            <?php endif; ?>
            <button class="btn btn-kaya-primary" data-toggle="modal" data-target="#addDriverModal">
              <i class="fas fa-plus mr-1"></i> New Driver
            </button>
          <?php endif; ?>
        </div>
      </div>

      <div class="kaya-card">
        <div class="table-responsive">
          <table id="driversTable" class="table kaya-table table-borderless">
            <thead>
              <tr>
                <th style="width:56px">#</th>
                <th>Driver</th>
                <th class="d-none d-md-table-cell">License #</th>
                <th>Contact #</th>
                <th>Status</th>
                <th style="width:240px">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php $n=1; foreach($drivers as $d):
                $name = trim(($d['u_fname']??'').' '.($d['u_lname']??''));
                $src  = $d['_src']; $id = (int)$d['d_u_id'];
                if (!empty($d['_status_class_override'])) {
                  $cls = $d['_status_class_override']; $txt = $d['u_car_book_status'];
                } else {
                  [$txt,$cls] = status_text_and_class($d['u_car_book_status'] ?? '');
                }
              ?>
              <tr>
                <td><?= $n++; ?></td>
                <td class="font-weight-semibold">
                  <?= h($name ?: '—') ?>
                  <?php if ($src==='user'): ?><div class="muted small">from <code>tms_user</code></div><?php endif; ?>
                </td>
                <td class="d-none d-md-table-cell"><?= h($d['u_car_regno'] ?: '—') ?></td>
                <td><?= h($d['u_phone'] ?: '—') ?></td>
                <td class="<?= $cls ?>"><?= h($txt) ?></td>
                <td class="actions">
                  <a class="btn btn-outline-secondary" title="View"
                     href="admin-view-driver.php?src=<?= $src ?>&d_u_id=<?= $id ?>"><i class="fas fa-info-circle"></i></a>
                  <?php if ($view!=='trash'): ?>
                    <?php if ($src==='add'): ?>
                      <a class="btn btn-outline-secondary" title="Edit"
                         href="driver-edit.php?d_u_id=<?= $id ?>"><i class="fas fa-pen"></i></a>
                    <?php endif; ?>
                    <!--<a class="btn btn-outline-secondary"  title="Monitor"
                       href="admin-view-syslogs.php?PlateNo=<?= $v_reg_no ?>"><i class="fas fa-eye"></i></a> -->
                    <?php if ($src==='add'): ?>
                      <button class="btn btn-outline-danger" title="Delete"
                              data-toggle="modal" data-target="#deleteDriverModal"
                              data-driver-id="<?= $id ?>">
                        <i class="fas fa-trash"></i>
                      </button>
                    <?php endif; ?>
                  <?php else: ?>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="restore_driver_id" value="<?= $id ?>">
                      <button name="restore_driver" class="btn btn-sm btn-success" title="Restore">
                        <i class="fas fa-undo"></i>
                      </button>
                    </form>
                    <form method="post" class="d-inline" onsubmit="return confirm('Permanently delete this driver?');">
                      <input type="hidden" name="purge_driver_id" value="<?= $id ?>">
                      <button name="purge_driver" class="btn btn-sm btn-outline-danger" title="Purge">
                        <i class="fas fa-times"></i>
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
      <!-- Add Driver Modal (no Vehicle/Type input) -->
      <div class="modal fade" id="addDriverModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
          <form method="POST" autocomplete="off">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Add Driver</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
              </div>
              <div class="modal-body">
                <div class="form-row">
                  <div class="form-group col-md-6"><label>First Name</label><input type="text" name="u_fname" class="form-control" required></div>
                  <div class="form-group col-md-6"><label>Last Name</label><input type="text" name="u_lname" class="form-control"></div>
                </div>
                <div class="form-row">
                  <div class="form-group col-md-6"><label>Contact #</label><input type="tel" name="u_phone" class="form-control" maxlength="32" placeholder="+63 912 345 6789"></div>
                  <div class="form-group col-md-6"><label>Email (login)</label><input type="email" name="u_email" class="form-control" required></div>
                </div>
                <div class="form-row">
                  <div class="form-group col-md-6"><label>Address</label><input type="text" name="u_addr" class="form-control"></div>
                  <div class="form-group col-md-6"><label>License #</label><input type="text" name="u_car_regno" class="form-control"></div>
                </div>
                <div class="form-row">
                  <div class="form-group col-md-6">
                    <label>Display Status</label>
                    <select name="u_car_book_status" class="form-control"><option>Available</option><option>On Trip</option><option>Not Available</option></select>
                  </div>
                </div>

                <hr class="my-3">
                <div class="form-row">
                  <div class="form-group col-md-6">
                    <label>Set Password (login)</label>
                    <input type="password" name="login_password" class="form-control" minlength="6" required>
                  </div>
                  <!--<div class="form-group col-md-6">
                    <small class="text-muted d-block mt-4">
                      A driver account will be created in <code>accounts</code> and linked in <code>driver_profile</code>.
                    </small>
                  </div> -->
                </div>

                <input type="hidden" name="u_category" value="Driver">
              </div>
              <div class="modal-footer">
                <button type="submit" name="add_driver" class="btn btn-kaya-primary">Add Driver</button>
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Delete Driver Modal -->
      <div class="modal fade" id="deleteDriverModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
          <form method="POST">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title"><?= $has_soft_delete ? 'Move to Trash' : 'Delete Driver' ?></h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
              </div>
              <div class="modal-body">
                <p class="mb-2"><?= $has_soft_delete ? 'This will move the driver to Trash.' : 'This will permanently delete the driver.' ?></p>
                <select class="form-control" name="delete_driver_id" id="delete_driver_id">
                  <?php foreach($drivers as $d): if ($d['_src']!=='add') continue; ?>
                    <option value="<?= (int)$d['d_u_id'] ?>"><?= h(($d['u_fname'].' '.$d['u_lname']).($d['u_car_regno']?' — '.$d['u_car_regno']:'')) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="modal-footer">
                <button type="submit" name="delete_driver" class="btn btn-kaya-danger-outline">Delete</button>
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

<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="vendor/datatables/jquery.dataTables.js"></script>
<script src="vendor/datatables/dataTables.bootstrap4.js"></script>
<script>
  $('#driversTable').DataTable({ pageLength:10, order:[[0,'asc']], columnDefs:[{orderable:false,targets:[5]}] });
  $('#deleteDriverModal').on('show.bs.modal', function (e) {
    var id = $(e.relatedTarget).data('driver-id');
    if (id) $('#delete_driver_id').val(id);
  });
</script>
<style>
  footer.sticky-footer{ background:transparent!important; height:0!important; border:0!important; box-shadow:none!important; }
  footer.sticky-footer .container, footer.sticky-footer .copyright{ display:none!important; }
  #wrapper #content-wrapper{ padding-bottom:0!important; }
  .status-available{color:#16a34a;font-weight:600}
  .status-trip{color:#2563eb;font-weight:600}
  .status-off{color:#dc2626;font-weight:600}
</style>
</body>
</html>
