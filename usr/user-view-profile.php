<?php
session_start();
require_once __DIR__ . '/../admin/vendor/inc/config.php';
require_once __DIR__ . '/../admin/vendor/inc/checklogin.php';

$accountId = require_driver(); // ensure driver auth and get accounts.id
$legacyUid = (int)($_SESSION['u_id'] ?? 0);

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$profile = null;

/* Try modern schema first */
if ($accountId > 0) {
  if ($q = $mysqli->prepare("
      SELECT COALESCE(NULLIF(TRIM(a.name),''),'Driver') AS name,
             COALESCE(a.email,'')  AS email,
             COALESCE(a.phone,'')  AS phone,
             COALESCE(dp.address,'')        AS address,
             COALESCE(dp.license_no,'')     AS license_no,
             COALESCE(dp.current_status,'') AS current_status
        FROM accounts a
   LEFT JOIN driver_profile dp ON dp.account_id = a.id
       WHERE a.id = ?
       LIMIT 1
  ")) {
    $q->bind_param('i', $accountId);
    $q->execute();
    $res = $q->get_result();
    $profile = $res ? $res->fetch_assoc() : null;
    $q->close();
  }
}

/* Fallback to legacy tms_user if not found */
if (!$profile && $legacyUid > 0) {
  if ($q = $mysqli->prepare("
      SELECT TRIM(CONCAT(COALESCE(u_fname,''),' ',COALESCE(u_lname,''))) AS name,
             COALESCE(u_email,'')  AS email,
             COALESCE(u_phone,'')  AS phone,
             COALESCE(u_addr,'')   AS address
        FROM tms_user
       WHERE u_id = ?
       LIMIT 1
  ")) {
    $q->bind_param('i', $legacyUid);
    $q->execute();
    $res = $q->get_result();
    $legacy = $res ? $res->fetch_assoc() : null;
    $q->close();

    if ($legacy) {
      $profile = [
        'name'           => $legacy['name'] ?: 'Driver',
        'email'          => $legacy['email'] ?? '',
        'phone'          => $legacy['phone'] ?? '',
        'address'        => $legacy['address'] ?? '',
        'license_no'     => '',
        'current_status' => ''
      ];
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
  <?php include __DIR__ . '/vendor/inc/head.php'; ?>
  <body id="page-top">
    <?php include __DIR__ . '/vendor/inc/nav.php'; ?>

    <div id="wrapper">
      <?php include __DIR__ . '/vendor/inc/sidebar.php'; ?>

      <div id="content-wrapper">
        <div class="container-fluid">

          <!-- Breadcrumbs -->
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="user-dashboard.php">Dashboard</a></li>
            <li class="breadcrumb-item">Profile</li>
            <li class="breadcrumb-item active">View Profile</li>
          </ol>

          <?php if ($profile): ?>
            <!-- Profile Card -->
            <div class="row">
              <div class="col-lg-8 col-md-10">
                <div class="card shadow-sm">
                  <div class="card-body">
                    <div class="d-flex align-items-start">
                      <div class="mr-3">
                        <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light border"
                              style="width:64px;height:64px;">
                          <i class="fas fa-user text-secondary" style="font-size:28px;"></i>
                        </span>
                      </div>
                      <div class="flex-grow-1">
                        <h5 class="mb-1"><?php echo h($profile['name'] ?: 'Driver'); ?></h5>
                        <div class="text-muted small">Driver</div>
                      </div>
                    </div>

                    <hr>

                    <div class="row">
                      <div class="col-sm-6 mb-3">
                        <div class="text-muted small mb-1">Email</div>
                        <div class="font-weight-600"><?php echo h($profile['email'] ?: '—'); ?></div>
                      </div>
                      <div class="col-sm-6 mb-3">
                        <div class="text-muted small mb-1">Phone</div>
                        <div class="font-weight-600"><?php echo h($profile['phone'] ?: '—'); ?></div>
                      </div>
                      <div class="col-sm-12 mb-3">
                        <div class="text-muted small mb-1">Address</div>
                        <div class="font-weight-600"><?php echo h($profile['address'] ?: '—'); ?></div>
                      </div>
                      <?php if (!empty($profile['license_no'])): ?>
                        <div class="col-sm-6 mb-3">
                          <div class="text-muted small mb-1">License #</div>
                          <div class="font-weight-600"><?php echo h($profile['license_no']); ?></div>
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($profile['current_status'])): ?>
                        <div class="col-sm-6 mb-3">
                          <div class="text-muted small mb-1">Current Status</div>
                          <span class="badge badge-<?php
                            $s = strtolower($profile['current_status']);
                            echo ($s==='available'?'success':($s==='on_trip'?'primary':'secondary'));
                          ?>">
                            <?php echo h(ucfirst($profile['current_status'])); ?>
                          </span>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          <?php else: ?>
            <div class="alert alert-warning">We couldn’t find your profile details.</div>
          <?php endif; ?>

          <?php include __DIR__ . '/vendor/inc/footer.php'; ?>
        </div>
      </div>
    </div>

    <a class="scroll-to-top rounded" href="#page-top">
      <i class="fas fa-angle-up"></i>
    </a>

    <!-- JS -->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
    <script src="vendor/js/sb-admin.min.js"></script>

    <!-- Sidebar toggle helper -->
    <script>
      (function () {
        var btn = document.getElementById('sidebarToggle');
        if (btn) btn.addEventListener('click', function () {
          document.body.classList.toggle('sidebar-toggled');
          var sb = document.querySelector('.sidebar');
          if (sb) sb.classList.toggle('toggled');
        });
      })();
    </script>
  </body>
</html>
