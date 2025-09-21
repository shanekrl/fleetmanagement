<?php
session_start();
include('vendor/inc/config.php');
include('vendor/inc/checklogin.php');
check_login();

$driver_id = (int)($_SESSION['u_id'] ?? 0);

/* Fetch grid similar to admin – use the unified view if available */
$sql = "
  SELECT
    b.id               AS booking_id,
    b.booking_type,
    b.created_by,
    b.driver_id,
    b.vehicle_id,
    b.pax,
    COALESCE(b.contact_name,'') AS client_name,
    b.contact_phone,
    b.pickup_point AS pickup,
    b.dropoff_point AS dropoff,
    b.scheduled_start_at AS scheduled_at,
    b.status,
    tv.v_reg_no AS vehicle_reg_no
  FROM bookings b
  LEFT JOIN tms_vehicle tv ON tv.v_id=b.vehicle_id
  WHERE (b.driver_id=? OR (b.booking_type='personal' AND b.created_by=?))
  ORDER BY COALESCE(b.scheduled_start_at,b.created_at) DESC
";
$rows = [];
if ($st = $mysqli->prepare($sql)) {
  $st->bind_param('ii',$driver_id,$driver_id);
  $st->execute();
  $res = $st->get_result();
  while ($r = $res->fetch_assoc()) $rows[] = $r;
  $st->close();
}
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES,'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<?php include('vendor/inc/head.php'); ?>
<body id="page-top">
<?php include('vendor/inc/nav.php'); ?>
<div id="wrapper">
  <?php include('vendor/inc/sidebar.php'); ?>
  <div id="content-wrapper">
    <div class="container-fluid">

      <div class="d-flex align-items-center mb-3">
        <ol class="breadcrumb flex-grow-1 mb-0">
          <li class="breadcrumb-item"><a href="user-dashboard.php">Dashboard</a></li>
          <li class="breadcrumb-item active">Trips</li>
        </ol>
        <a href="driver-trip-new.php" class="btn btn-primary"><i class="fas fa-plus mr-1"></i> New Trip</a>
      </div>

      <div class="card">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-hover mb-0" id="driverTripsTbl">
              <thead class="thead-light">
                <tr>
                  <th>#</th>
                  <th>When</th>
                  <th>Type</th>
                  <th>Pax</th>
                  <th>Pickup</th>
                  <th>Dropoff</th>
                  <th>Vehicle</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php $i=1; foreach($rows as $r): ?>
                <tr>
                  <td><?php echo $i++; ?></td>
                  <td><?php echo h($r['scheduled_at'] ?: '—'); ?></td>
                  <td><?php echo strtoupper(h($r['booking_type'])); ?></td>
                  <td><?php echo (int)$r['pax']; ?></td>
                  <td><?php echo h($r['pickup']); ?></td>
                  <td><?php echo h($r['dropoff']); ?></td>
                  <td><?php echo h($r['vehicle_reg_no'] ?: '—'); ?></td>
                  <td><?php echo h($r['status']); ?></td>
                  <td>
                    <?php
                      $mine = ($r['booking_type']==='personal' && (int)$r['created_by']===$driver_id);
                      $canEdit   = $mine && in_array($r['status'], ['pending','accepted']);
                      $canCancel = $mine && in_array($r['status'], ['pending','accepted']);
                      $canStart  = ($r['driver_id']===$driver_id && $r['status']==='accepted');
                      $canFinish = ($r['driver_id']===$driver_id && $r['status']==='in_progress');
                    ?>
                    <div class="btn-group btn-group-sm" role="group">
                      <?php if ($canEdit): ?>
                        <a class="btn btn-outline-secondary" href="driver-trip-edit.php?id=<?php echo (int)$r['booking_id']; ?>" title="Edit"><i class="fas fa-pencil-alt"></i></a>
                      <?php endif; ?>
                      <?php if ($canCancel): ?>
                        <form method="post" action="driver-trip-cancel.php" onsubmit="return confirm('Cancel this trip?');">
                          <input type="hidden" name="id" value="<?php echo (int)$r['booking_id']; ?>">
                          <button class="btn btn-outline-danger" title="Cancel"><i class="fas fa-times"></i></button>
                        </form>
                      <?php endif; ?>

                      <?php if ($r['booking_type']==='admin' && $r['status']==='awaiting_driver'): ?>
                        <a class="btn btn-success" href="driver-offer-response.php?id=<?php echo (int)$r['booking_id']; ?>&do=accept" title="Accept"><i class="fas fa-check"></i></a>
                        <a class="btn btn-danger"  href="driver-offer-response.php?id=<?php echo (int)$r['booking_id']; ?>&do=reject" title="Reject"><i class="fas fa-ban"></i></a>
                      <?php endif; ?>

                      <?php if ($canStart): ?>
                        <a class="btn btn-primary" href="driver-trip-start.php?id=<?php echo (int)$r['booking_id']; ?>" title="Start / Pickup"><i class="fas fa-play"></i></a>
                      <?php endif; ?>
                      <?php if ($canFinish): ?>
                        <a class="btn btn-primary" href="driver-trip-complete.php?id=<?php echo (int)$r['booking_id']; ?>" title="Complete / Dropoff"><i class="fas fa-flag-checkered"></i></a>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
    <?php include('vendor/inc/footer.php'); ?>
  </div>
</div>

<a class="scroll-to-top rounded" href="#page-top"><i class="fas fa-angle-up"></i></a>

<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="vendor/datatables/jquery.dataTables.js"></script>
<script src="vendor/datatables/dataTables.bootstrap4.js"></script>
<script src="vendor/js/sb-admin.min.js"></script>

<script>
  $('#driverTripsTbl').DataTable({
    pageLength: 10,
    order: [[1,'desc']],
    columnDefs: [{targets: -1, orderable:false, searchable:false}]
  });
</script>
<!-- sidebar toggle helper -->
<script>
  (function(){var b=document.getElementById('sidebarToggle');if(b)b.addEventListener('click',function(){document.body.classList.toggle('sidebar-toggled');var s=document.querySelector('.sidebar');if(s)s.classList.toggle('toggled');});})();
</script>
</body>
</html>
