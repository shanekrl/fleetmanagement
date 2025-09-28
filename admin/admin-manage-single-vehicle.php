<?php
session_start();
include('vendor/inc/config.php');
include('vendor/inc/checklogin.php');
check_login();
$aid = require_admin();

$mysqli->set_charset('utf8mb4');

/* ----------------- helpers ----------------- */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function table_exists(mysqli $db, string $table): bool {
  $t = $db->real_escape_string($table);
  $r = $db->query("SHOW TABLES LIKE '{$t}'");
  return $r && $r->num_rows > 0;
}
function view_exists(mysqli $db, string $view): bool { return table_exists($db,$view); }
function column_exists(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $r=$db->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'"); return $r && $r->num_rows>0;
}
function vehicle_image_url($raw){
  if (!$raw) return '';
  if (preg_match('~^(https?:)?//~',$raw) || strpos($raw,'/')===0) return $raw;
  if (strpos($raw,'vendor/')===0) return $raw;
  return 'vendor/img/vehicles/'.ltrim($raw,'/');
}

/* ----- feature flags / lists ----- */
$hasVehAssigns = table_exists($mysqli,'vehicle_assignments');
$hasAccounts   = table_exists($mysqli,'accounts');
$hasViewVehDrv = view_exists($mysqli,'v_vehicle_current_driver');

$default_cats  = ['Bus','Sedan','SUV','Van'];
$cat_table     = table_exists($mysqli,'tms_vehicle_categories');
$cat_soft      = $cat_table && column_exists($mysqli,'tms_vehicle_categories','deleted_at');

$make_table    = table_exists($mysqli,'tms_vehicle_makes');
$model_table   = table_exists($mysqli,'tms_vehicle_models');

function fetch_categories(mysqli $db, bool $has, bool $soft, array $fallback): array {
  if (!$has) return array_map(fn($n)=>['id'=>null,'name'=>$n], $fallback);
  $where = $soft ? "WHERE deleted_at IS NULL AND is_active=1" : "WHERE is_active=1";
  $out=[]; if($q=$db->query("SELECT id,name FROM tms_vehicle_categories $where ORDER BY name")) while($r=$q->fetch_assoc()) $out[]=$r;
  return $out ?: array_map(fn($n)=>['id'=>null,'name'=>$n], $fallback);
}
function fetch_makes(mysqli $db): array {
  $out=[]; if($q=$db->query("SELECT id,name FROM tms_vehicle_makes WHERE is_active=1 AND deleted_at IS NULL ORDER BY name")) while($r=$q->fetch_assoc()) $out[]=$r;
  return $out;
}
function fetch_models_by_make(mysqli $db, int $makeId): array {
  $out=[]; if($q=$db->query("SELECT id,make_id,name FROM tms_vehicle_models WHERE make_id={$makeId} AND is_active=1 AND deleted_at IS NULL ORDER BY name")) while($r=$q->fetch_assoc()) $out[]=$r;
  return $out;
}

/* ----------------- resolve id ----------------- */
$vehId = isset($_GET['v_id']) ? (int)$_GET['v_id'] : 0;
if ($vehId <= 0) { header('Location: admin-manage-vehicle.php'); exit; }

$succ = $err = '';

/* ----------------- update vehicle (fields + assignment) ----------------- */
if (isset($_POST['update_veh'])) {
  $v_reg_no   = trim($_POST['v_reg_no'] ?? '');
  $v_category = trim($_POST['v_category'] ?? '');
  $color      = trim($_POST['color'] ?? '');
  $make_id    = ctype_digit((string)($_POST['make_id'] ?? '')) ? (int)$_POST['make_id'] : null;
  $model_id   = ctype_digit((string)($_POST['model_id'] ?? '')) ? (int)$_POST['model_id'] : null;
  $v_dpic     = $_POST['__current_dpic'] ?? '';

  // assignment from the main form (numeric id or "0" for none)
  $assign_driver_id = isset($_POST['assign_driver_id']) && ctype_digit((string)$_POST['assign_driver_id'])
                      ? (int)$_POST['assign_driver_id'] : 0;

  // image upload
  if (!empty($_FILES['v_dpic']['name']) && is_uploaded_file($_FILES['v_dpic']['tmp_name'])) {
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $mime=@mime_content_type($_FILES['v_dpic']['tmp_name']);
    if(isset($allowed[$mime])){
      $ext=$allowed[$mime]; $dir=__DIR__.'/vendor/img/vehicles'; if(!is_dir($dir)) @mkdir($dir,0775,true);
      $fname='veh_'.time().'_'.mt_rand(1000,9999).'.'.$ext; $dest=$dir.'/'.$fname;
      if(@move_uploaded_file($_FILES['v_dpic']['tmp_name'],$dest)) $v_dpic='vendor/img/vehicles/'.$fname;
    }
  }

  $mysqli->begin_transaction();
  try {
    if ($s=$mysqli->prepare("UPDATE tms_vehicle SET v_reg_no=?, v_category=?, color=?, make_id=?, model_id=?, v_dpic=? WHERE v_id=?")) {
      $s->bind_param('sssissi',$v_reg_no,$v_category,$color,$make_id,$model_id,$v_dpic,$vehId);
      $s->execute(); $s->close();
    }

    if ($hasVehAssigns && $hasAccounts) {
      if ($assign_driver_id === 0) {
        if ($s=$mysqli->prepare("UPDATE vehicle_assignments SET end_at=NOW() WHERE vehicle_id=? AND end_at IS NULL")) { $s->bind_param('i',$vehId); $s->execute(); $s->close(); }
      } else {
        if ($s=$mysqli->prepare("UPDATE vehicle_assignments SET end_at=NOW() WHERE (driver_id=? OR vehicle_id=?) AND end_at IS NULL")) { $s->bind_param('ii',$assign_driver_id,$vehId); $s->execute(); $s->close(); }
        if ($s=$mysqli->prepare("INSERT INTO vehicle_assignments(vehicle_id,driver_id,assigned_by,start_at) VALUES(?,?,?,NOW())")) { $s->bind_param('iii',$vehId,$assign_driver_id,$aid); $s->execute(); $s->close(); }
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
$vehicle=null;
if ($s=$mysqli->prepare("SELECT v_id, v_reg_no, v_category, color, make_id, model_id, v_dpic FROM tms_vehicle WHERE v_id=? LIMIT 1")) {
  $s->bind_param('i',$vehId); $s->execute(); $vehicle=$s->get_result()->fetch_assoc(); $s->close();
}
if (!$vehicle) { header('Location: admin-manage-vehicle.php'); exit; }

$current_driver_id=0; $current_driver_name='';
if ($hasViewVehDrv) {
  if ($q=$mysqli->prepare("SELECT driver_account_id FROM v_vehicle_current_driver WHERE v_id=? LIMIT 1")) {
    $q->bind_param('i',$vehId); $q->execute(); $q->bind_result($current_driver_id); $q->fetch(); $q->close();
  }
  if ($current_driver_id && $hasAccounts) {
    if ($q=$mysqli->prepare("SELECT COALESCE(NULLIF(name,''), CONCAT('Driver #',id)) FROM accounts WHERE id=? LIMIT 1")) {
      $q->bind_param('i',$current_driver_id); $q->execute(); $q->bind_result($current_driver_name); $q->fetch(); $q->close();
    }
  }
} elseif ($hasVehAssigns && $hasAccounts) {
  $q=$mysqli->prepare("SELECT va.driver_id, a.name FROM vehicle_assignments va JOIN accounts a ON a.id=va.driver_id WHERE va.vehicle_id=? AND va.end_at IS NULL LIMIT 1");
  $q->bind_param('i',$vehId); $q->execute(); $q->bind_result($current_driver_id,$current_driver_name); $q->fetch(); $q->close();
}

/* ----------------- driver list (available or on this car) ----------------- */
$drivers_acc=[];
if ($hasAccounts) {
  if ($hasViewVehDrv) {
    $q=$mysqli->query("SELECT a.id,a.name,(SELECT v_id FROM v_vehicle_current_driver WHERE driver_account_id=a.id LIMIT 1) AS current_vehicle_id
                       FROM accounts a WHERE a.role='driver' AND a.is_active=1 ORDER BY a.name,a.id DESC");
  } else {
    $q=$mysqli->query("SELECT a.id,a.name,(SELECT vehicle_id FROM vehicle_assignments WHERE driver_id=a.id AND end_at IS NULL LIMIT 1) AS current_vehicle_id
                       FROM accounts a WHERE a.role='driver' AND a.is_active=1 ORDER BY a.name,a.id DESC");
  }
  if ($q) while($r=$q->fetch_assoc()) $drivers_acc[]=$r;
}

/* ----------------- lists for selects ----------------- */
$categories_active = fetch_categories($mysqli,$cat_table,$cat_soft,$default_cats);
$makes_active      = $make_table ? fetch_makes($mysqli) : [];
$models_for_make   = ($model_table && !empty($vehicle['make_id'])) ? fetch_models_by_make($mysqli,(int)$vehicle['make_id']) : [];

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
          <div class="ml-auto"><a href="admin-manage-vehicle.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i> Back to Vehicles</a></div>
        </div>

        <?php if($succ): ?><script>setTimeout(function(){ swal("Success!", "<?= h($succ) ?>", "success"); }, 80);</script><?php endif; ?>
        <?php if($err):  ?><script>setTimeout(function(){ swal("Failed!", "<?= h($err) ?>", "error"); }, 80);</script><?php endif; ?>

        <section class="kaya-card p-3 p-md-4">
          <form method="POST" enctype="multipart/form-data">
            <div class="row">
              <div class="col-lg-8">
                <div class="form-row">
                  <div class="form-group col-md-6">
                    <label class="font-weight-semibold">Plate Number</label>
                    <input type="text" name="v_reg_no" class="form-control" value="<?= h($vehicle['v_reg_no'] ?? '') ?>">
                  </div>
                  <div class="form-group col-md-6">
                    <label class="font-weight-semibold">Color</label>
                    <input type="text" name="color" class="form-control" value="<?= h($vehicle['color'] ?? '') ?>">
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group col-md-6">
                    <label class="font-weight-semibold">Vehicle Category</label>
                    <select class="form-control" name="v_category">
                      <?php $curr=$vehicle['v_category'] ?? ''; foreach($categories_active as $c){ $name=$c['name']; $sel=$name===$curr?'selected':''; echo "<option $sel>".h($name)."</option>"; } ?>
                    </select>
                  </div>
                  <div class="form-group col-md-6">
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
                </div>

                <div class="form-row">
                  <div class="form-group col-md-6">
                    <label class="font-weight-semibold">Make</label>
                    <select class="form-control" name="make_id" id="make_id_edit">
                      <option value="">— Select make —</option>
                      <?php $curMake=(int)($vehicle['make_id'] ?? 0);
                        foreach($makes_active as $m){ $sel = ($curMake===(int)$m['id'])?'selected':''; echo '<option value="'.(int)$m['id'].'" '.$sel.'>'.h($m['name']).'</option>'; }
                      ?>
                    </select>
                  </div>
                  <div class="form-group col-md-6">
                    <label class="font-weight-semibold">Model</label>
                    <select class="form-control" name="model_id" id="model_id_edit" <?= empty($curMake)?'disabled':'' ?>>
                      <option value="">— Select model —</option>
                      <?php $curModel=(int)($vehicle['model_id'] ?? 0);
                        foreach($models_for_make as $mo){ $sel = ($curModel===(int)$mo['id'])?'selected':''; echo '<option value="'.(int)$mo['id'].'" '.$sel.'>'.h($mo['name']).'</option>'; }
                      ?>
                    </select>
                  </div>
                </div>

                <div class="mt-3">
                  <input type="hidden" name="__current_dpic" value="<?= h($vehicle['v_dpic'] ?? '') ?>">
                  <button type="submit" name="update_veh" class="btn btn-kaya-primary"><i class="fas fa-save mr-1"></i> Update Vehicle</button>
                  <a href="admin-manage-vehicle.php" class="btn btn-outline-secondary ml-2">Cancel</a>
                </div>
              </div>

              <div class="col-lg-4 mt-4 mt-lg-0">
                <div class="card shadow-sm" style="border-radius:.75rem; overflow:hidden;">
                  <?php if ($img): ?>
                    <img src="<?= h($img) ?>" class="card-img-top" alt="Vehicle photo">
                  <?php else: ?>
                    <div class="d-flex align-items-center justify-content-center" style="height:220px;background:#f8fafc;color:#6b7280">
                      <i class="fas fa-image fa-2x mr-2"></i> No photo
                    </div>
                  <?php endif; ?>
                  <div class="card-body">
                    <label class="font-weight-semibold d-block mb-2">Vehicle Image</label>
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

  <script>
    // For dependent models on Edit page
    const MODELS_BY_MAKE = {};
    <?php
      // preload all active models grouped by make for quick client filtering
      $mm = $mysqli->query("SELECT id,make_id,name FROM tms_vehicle_models WHERE is_active=1 AND deleted_at IS NULL ORDER BY name");
      $grouped = [];
      if ($mm) while($r=$mm->fetch_assoc()){ $grouped[(int)$r['make_id']][] = ['id'=>(int)$r['id'],'name'=>$r['name']]; }
      echo "Object.assign(MODELS_BY_MAKE, ".json_encode($grouped, JSON_UNESCAPED_UNICODE).");";
    ?>

    function rebuildModelOptions($modelSel, makeId, currentId){
      $modelSel.prop('disabled', !makeId);
      $modelSel.empty().append($('<option/>').val('').text('— Select model —'));
      if(!makeId) return;
      (MODELS_BY_MAKE[makeId]||[]).forEach(m=>{
        const opt=$('<option/>').val(m.id).text(m.name);
        if (parseInt(currentId,10)===parseInt(m.id,10)) opt.attr('selected',true);
        $modelSel.append(opt);
      });
    }

    $(function(){
      $('#make_id_edit').on('change', function(){
        rebuildModelOptions($('#model_id_edit'), parseInt(this.value||0,10), 0);
      });
    });
  </script>

  <style>
    footer.sticky-footer{ background:transparent!important; height:0!important; border:0!important; box-shadow:none!important; }
    footer.sticky-footer .container, footer.sticky-footer .copyright{ display:none!important; }
    #wrapper #content-wrapper{ padding-bottom:0!important; }
    .form-group label{ color:#0f172a; }
  </style>
</body>
</html>
