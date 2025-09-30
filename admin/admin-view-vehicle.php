<?php
session_start();
include('vendor/inc/config.php');
include('vendor/inc/checklogin.php');
check_login();

$mysqli->set_charset('utf8mb4');

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
function col_exists(mysqli $db, $t, $c){
  $t = $db->real_escape_string($t);
  $c = $db->real_escape_string($c);
  $r = $db->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
  return $r && $r->num_rows>0;
}

/* ---------- feature flags ---------- */
$softMake  = col_exists($mysqli,'tms_vehicle_makes','deleted_at');
$softModel = col_exists($mysqli,'tms_vehicle_models','deleted_at');

function vehicle_image_url($raw){
  if (!$raw) return '';
  if (preg_match('~^(https?:)?//~', $raw) || strpos($raw, '/') === 0) return $raw;
  if (strpos($raw, 'vendor/') === 0) return $raw;
  return 'vendor/img/vehicles/'.ltrim($raw,'/');
}
function vehicle_display_name(array $veh): string {
  $make  = trim((string)($veh['make_name']  ?? ''));
  $model = trim((string)($veh['model_name'] ?? ''));
  $combo = trim("$make $model");
  return $combo !== '' ? $combo : trim((string)($veh['v_name'] ?? ''));
}

/* ---------- get id ---------- */
$vId = isset($_GET['v_id']) ? (int)$_GET['v_id'] : 0;
if ($vId <= 0) { header('Location: admin-manage-vehicle.php'); exit; }

/* ---------- fetch vehicle + current driver (accounts) + fallback (add_driver) ---------- */
$sql = "SELECT
          v.v_id, v.v_name, v.v_reg_no, v.v_category, v.v_status, v.v_dpic, v.default_driver_id,
          mk.name AS make_name, md.name AS model_name
        FROM tms_vehicle v
        LEFT JOIN tms_vehicle_makes  mk ON mk.id=v.make_id ".($softMake  ? "AND mk.deleted_at IS NULL " : "")."
        LEFT JOIN tms_vehicle_models md ON md.id=v.model_id ".($softModel ? "AND md.deleted_at IS NULL " : "")."
        WHERE v.v_id=?";
$veh=null;
if($st=$mysqli->prepare($sql)){
  $st->bind_param('i',$vId);
  $st->execute();
  $veh=$st->get_result()->fetch_assoc();
  $st->close();
}
if(!$veh){ header('Location: admin-manage-vehicle.php'); exit; }
$vehName = vehicle_display_name($veh);

/* active assignment (vehicle_assignments) */
$assign = ['driver_id'=>null,'name'=>null,'email'=>null,'phone'=>null];
$q = $mysqli->prepare("
  SELECT a.id AS driver_id, a.name, a.email, a.phone
  FROM vehicle_assignments va
  JOIN accounts a ON a.id=va.driver_id
  WHERE va.vehicle_id=? AND va.end_at IS NULL
  LIMIT 1
");
$q->bind_param('i',$vId);
$q->execute();
if($r=$q->get_result()->fetch_assoc()) $assign=$r;
$q->close();

/* fallback from tms_user_add_driver (legacy) */
$fallback = null;
if(!empty($veh['default_driver_id'])){
  $dId = (int)$veh['default_driver_id'];
  if($rs=$mysqli->query("SELECT u_fname,u_lname,u_email AS email,u_phone AS phone
                         FROM tms_user_add_driver WHERE d_u_id={$dId}")){
    $fallback=$rs->fetch_assoc();
  }
}

/* build image url */
$img = vehicle_image_url($veh['v_dpic'] ?? '');

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
      <h1 class="kaya-page-title">Vehicle Details</h1>

      <div class="kaya-toolbar d-flex align-items-center mb-3">
        <div class="ml-auto kaya-actions">
          <a href="admin-manage-vehicle.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left mr-1"></i> Back
          </a>
          <a href="admin-manage-single-vehicle.php?v_id=<?= (int)$veh['v_id'] ?>" class="btn btn-kaya-primary">
            <i class="fas fa-pen mr-1"></i> Edit
          </a>
        </div>
      </div>

      <section class="kaya-card p-3 p-md-4">
        <div class="row">
          <div class="col-lg-8">
            <div class="mb-3">
              <h2 class="mb-1" style="font-weight:700;color:#000047">
                <?= h($vehName !== '' ? $vehName : 'Untitled Vehicle') ?>
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
                  <td><?= h($vehName !== '' ? $vehName : '—') ?></td>
                </tr>
                <tr>
                  <th style="color:#6b7280;">Registration Number</th>
                  <td><?= h($veh['v_reg_no'] ?: '—') ?></td>
                </tr>
                <tr>
                  <th style="color:#6b7280;">Driver</th>
                  <td>
                    <?php if($assign['driver_id']): ?>
                      <div class="mb-1"><strong><?= h($assign['name']) ?></strong></div>
                      <div class="text-muted small">
                        <?= h($assign['phone'] ?: '') ?>
                        <?= ($assign['phone'] && $assign['email'])?' · ':'' ?>
                        <?= h($assign['email'] ?: '') ?>
                      </div>
                    <?php elseif($fallback): ?>
                      <div class="mb-1"><strong><?= h(trim(($fallback['u_fname']??'').' '.($fallback['u_lname']??''))) ?></strong></div>
                      <div class="text-muted small">
                        <?= h(($fallback['phone']??'')) ?>
                        <?= (!empty($fallback['phone']) && !empty($fallback['email']))?' · ':'' ?>
                        <?= h(($fallback['email']??'')) ?>
                        <span class="badge badge-light ml-2">legacy</span>
                      </div>
                    <?php else: ?>
                      —
                    <?php endif; ?>
                  </td>
                </tr>
                <tr>
                  <th style="color:#6b7280;">Category</th>
                  <td><?= h($veh['v_category'] ?: '—') ?></td>
                </tr>
                <tr>
                  <th style="color:#6b7280;">Status</th>
                  <td><span class="<?= status_badge_class($veh['v_status']) ?> px-2 py-1"><?= h($veh['v_status'] ?: 'Unknown') ?></span></td>
                </tr>
                </tbody>
              </table>
            </div>
          </div>

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
