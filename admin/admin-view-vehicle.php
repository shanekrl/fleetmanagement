<?php
session_start();
include('vendor/inc/config.php');
include('vendor/inc/checklogin.php');
check_login();

/* ---------- helpers ---------- */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function status_badge_class($s){
  $s = strtolower(trim($s ?? ''));
  if ($s==='available') return 'badge badge-success';
  if ($s==='booked') return 'badge badge-primary';
  if ($s==='undermaintenance' || $s==='under maintenance') return 'badge badge-warning';
  if ($s==='maintenance') return 'badge badge-warning';
  return 'badge badge-secondary';
}
function column_exists(mysqli $db, string $table, string $col): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
  return $r && $r->num_rows > 0;
}

/* ---------- get id ---------- */
$vId = isset($_GET['v_id']) ? (int)$_GET['v_id'] : 0;
if ($vId <= 0) { header('Location: admin-manage-vehicle.php'); exit; }

/* ---------- soft delete support ---------- */
$has_soft_delete = column_exists($mysqli, 'tms_vehicle', 'deleted_at');
$aid = 0;
if (function_exists('require_admin')) { $aid = require_admin(); }

/* ---------- POST: delete / restore ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (isset($_POST['delete_vehicle']) && isset($_POST['v_id'])) {
    $toDel = (int)$_POST['v_id'];
    if ($has_soft_delete) {
      // soft delete
      if ($stmt = $mysqli->prepare("UPDATE tms_vehicle SET deleted_at = NOW(), deleted_by = ? WHERE v_id = ?")) {
        $stmt->bind_param('ii', $aid, $toDel);
        $stmt->execute();
        $stmt->close();
      }
      header('Location: admin-manage-vehicle.php?deleted=1'); exit;
    } else {
      // hard delete (fallback)
      if ($stmt = $mysqli->prepare("DELETE FROM tms_vehicle WHERE v_id=?")) {
        $stmt->bind_param('i', $toDel);
        $stmt->execute();
        $stmt->close();
      }
      header('Location: admin-manage-vehicle.php?deleted=1'); exit;
    }
  }

  if ($has_soft_delete && isset($_POST['restore_vehicle']) && isset($_POST['v_id'])) {
    $toRes = (int)$_POST['v_id'];
    if ($stmt = $mysqli->prepare("UPDATE tms_vehicle SET deleted_at = NULL, deleted_by = NULL WHERE v_id = ?")) {
      $stmt->bind_param('i', $toRes);
      $stmt->execute();
      $stmt->close();
    }
    header('Location: admin-view-vehicle.php?v_id='.$toRes.'&restored=1'); exit;
  }
}

/* ---------- fetch vehicle + driver ---------- */
$veh = null;
$sql = "SELECT v.v_id, v.v_name, v.v_reg_no, v.v_category, v.v_status, v.v_dpic,
               v.v_pass_no, v.driver_user_id,
               ".($has_soft_delete ? "v.deleted_at, v.deleted_by," : "")."
               u.u_id AS driver_id, u.u_fname, u.u_lname, u.u_phone, u.u_email
        FROM tms_vehicle v
        LEFT JOIN tms_user u ON u.u_id = v.driver_user_id
        WHERE v.v_id = ? LIMIT 1";
if ($s = $mysqli->prepare($sql)) {
  $s->bind_param('i', $vId);
  $s->execute();
  $veh = $s->get_result()->fetch_assoc();
  $s->close();
}
if (!$veh) { header('Location: admin-manage-vehicle.php'); exit; }

$is_deleted = $has_soft_delete && !empty($veh['deleted_at']);

/* ---------- image url resolver ---------- */
function vehicle_image_url($raw){
  if (!$raw) return '';
  // if it already looks like /path or http(s), return as-is
  if (preg_match('~^(https?:)?//~', $raw) || strpos($raw, '/') === 0) return $raw;
  // our create form saves like "vendor/img/vehicles/xxx.jpg"
  if (strpos($raw, 'vendor/') === 0) return $raw;
  // legacy: only filename -> assume vehicles folder
  return 'vendor/img/vehicles/'.ltrim($raw,'/');
}
$img = vehicle_image_url($veh['v_dpic'] ?? '');
$driverName = ($veh['driver_id']) ? trim(($veh['u_fname'] ?? '').' '.($veh['u_lname'] ?? '')) : '—';
$driverContact = [];
if (!empty($veh['u_phone'])) $driverContact[] = $veh['u_phone'];
if (!empty($veh['u_email'])) $driverContact[] = $veh['u_email'];
$driverContact = implode(' · ', $driverContact);
?>
<!DOCTYPE html>
<html lang="en">
<?php include('vendor/inc/head.php'); ?>
<body id="page-top">

<?php include('vendor/inc/nav.php'); ?>

<div id="wrapper">
  <?php include('vendor/inc/sidebar.php'); ?>

  <div id="content-wrapper">
    <div class="container-fluid">

      <!-- Title -->
      <h1 class="kaya-page-title">
        Vehicle Details
        <?php if ($is_deleted): ?>
          <span class="badge badge-danger ml-2">Deleted</span>
        <?php endif; ?>
      </h1>

      <!-- Toolbar -->
      <div class="kaya-toolbar d-flex align-items-center mb-3">
        <div class="ml-auto kaya-actions">
          <a href="admin-manage-vehicle.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Back
          </a>

          <a href="admin-manage-single-vehicle.php?v_id=<?= (int)$veh['v_id'] ?>" class="btn btn-kaya-primary">
            <i class="fas fa-pen mr-1"></i> Edit
          </a>

          <?php if ($is_deleted): ?>
            <form method="post" class="d-inline" style="margin:0;">
              <input type="hidden" name="v_id" value="<?= (int)$veh['v_id'] ?>">
              <button name="restore_vehicle" class="btn btn-success">
                <i class="fas fa-undo mr-1"></i> Restore
              </button>
            </form>
          <?php else: ?>
            <form method="post" class="d-inline" style="margin:0;"
                  onsubmit="return confirm('Delete this vehicle? You can restore it later.');">
              <input type="hidden" name="v_id" value="<?= (int)$veh['v_id'] ?>">
              <button name="delete_vehicle" class="btn btn-kaya-danger-outline">
                <i class="fas fa-trash mr-1"></i> Delete
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <!-- Details -->
      <section class="kaya-card p-3 p-md-4">
        <div class="row">
          <!-- Left: meta/details -->
          <div class="col-lg-8">
            <div class="mb-3">
              <h2 class="mb-1" style="font-weight:700;color:#000047">
                <?= h($veh['v_name'] ?: 'Untitled Vehicle') ?>
              </h2>
              <div class="d-flex align-items-center" style="gap:.5rem;">
                <span class="text-muted">Reg No.</span>
                <span class="font-weight-semibold"><?= h($veh['v_reg_no'] ?: '—') ?></span>
                <span class="<?= status_badge_class($veh['v_status']) ?> ml-2 px-2 py-1">
                  <?= h($veh['v_status'] ?: 'Unknown') ?>
                </span>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-borderless kaya-table mb-0">
                <tbody>
                  <tr>
                    <th style="width:220px;color:#6b7280;">Vehicle Name</th>
                    <td><?= h($veh['v_name'] ?: '—') ?></td>
                  </tr>
                  <tr>
                    <th style="color:#6b7280;">Registration Number</th>
                    <td><?= h($veh['v_reg_no'] ?: '—') ?></td>
                  </tr>
                  <tr>
                    <th style="color:#6b7280;">Driver</th>
                    <td>
                      <?= h($driverName) ?>
                      <?php if ($veh['driver_id']): ?>
                        <?php if ($driverContact): ?>
                          <div class="text-muted small"><?= h($driverContact) ?></div>
                        <?php endif; ?>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <tr>
                    <th style="color:#6b7280;">Category</th>
                    <td><?= h($veh['v_category'] ?: '—') ?></td>
                  </tr>
                  <tr>
                    <th style="color:#6b7280;">Capacity (Pax)</th>
                    <td><?= h((string)($veh['v_pass_no'] ?? '—')) ?></td>
                  </tr>
                  <tr>
                    <th style="color:#6b7280;">Status</th>
                    <td>
                      <span class="<?= status_badge_class($veh['v_status']) ?> px-2 py-1">
                        <?= h($veh['v_status'] ?: 'Unknown') ?>
                      </span>
                      <?php if ($is_deleted): ?>
                        <span class="badge badge-danger ml-2">Deleted</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Right: image -->
          <div class="col-lg-4 mt-4 mt-lg-0">
            <div class="card shadow-sm" style="border-radius:.75rem; overflow:hidden;">
              <?php if ($img): ?>
                <img src="<?= h($img) ?>" class="card-img-top" alt="Vehicle photo">
              <?php else: ?>
                <div class="d-flex align-items-center justify-content-center"
                     style="height:260px;background:#f8fafc;color:#6b7280">
                  <i class="fas fa-image fa-2x mr-2"></i> No photo
                </div>
              <?php endif; ?>
              <div class="card-body">
                <div class="small text-muted">Vehicle Picture</div>
                <div class="text-muted">You can update or replace this image from the Edit screen.</div>
              </div>
            </div>
          </div>
        </div>
      </section>

    </div>
    <?php include('vendor/inc/footer.php'); ?>
  </div>
</div>

<!-- Vendor JS -->
<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="vendor/jquery-easing/jquery.easing.min.js"></script>

<style>
  .kaya-toolbar .kaya-actions{ display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
  .kaya-toolbar .kaya-actions .btn{ padding:.5rem .9rem; border-radius:.5rem; font-weight:600; }
  footer.sticky-footer{ background:transparent!important; height:0!important; border:0!important; box-shadow:none!important; }
  footer.sticky-footer .container, footer.sticky-footer .copyright{ display:none!important; }
  #wrapper #content-wrapper{ padding-bottom:0!important; }
</style>
</body>
</html>
