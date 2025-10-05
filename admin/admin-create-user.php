<?php
session_start();
require_once __DIR__ . '/vendor/inc/config.php';
require_once __DIR__ . '/vendor/inc/checklogin.php';
check_login();

// Require superadmin
if (($_SESSION['role'] ?? '') !== 'superadmin') {
  http_response_code(403);
  exit('Forbidden');
}

// CSRF (very minimal example; replace with your helper if you have one)
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

function audit_log(mysqli $db, int $actorId = null, string $action, ?int $targetId, array $details = []) {
  $json = json_encode($details, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
  $ua   = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
  $stmt = $db->prepare("INSERT INTO admin_audit_logs (actor_id, action, target_id, details, ip_addr, user_agent) VALUES (?,?,?,?,?,?)");
  $stmt->bind_param('isisss', $actorId, $action, $targetId, $json, $ip, $ua);
  $stmt->execute();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
    http_response_code(419);
    exit('CSRF token mismatch');
  }

  $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
  $name  = trim($_POST['name'] ?? '');
  $role  = 'admin'; // lock to admin only (no creating superadmins via UI)
  $pwd   = (string)($_POST['password'] ?? '');

  // Basic validations
  if (!$email || $name === '' || strlen($pwd) < 10) {
    exit('Invalid data. Ensure a valid email, name, and 10+ char password.');
  }

  // Hash password
  $hash = password_hash($pwd, PASSWORD_DEFAULT);

  // Create account
  $stmt = $mysqli->prepare("INSERT INTO accounts (role, name, email, password_hash, is_active) VALUES (?,?,?,?,1)");
  $stmt->bind_param('ssss', $role, $name, $email, $hash);

  if ($stmt->execute()) {
    $newId = (int)$stmt->insert_id;
    audit_log($mysqli, (int)$_SESSION['account_id'], 'create_admin', $newId, [
      'email' => $email,
      'name'  => $name
    ]);
    echo "Admin created successfully.";
  } else {
    // Duplicate email? show safe message
    if ($mysqli->errno === 1062) {
      echo "That email is already in use.";
    } else {
      echo "Error creating admin.";
    }
  }
  exit;
}
?>
<!doctype html>
<html>
  <body>
    <h1>Create Admin (SuperAdmin only)</h1>
    <form method="post">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['csrf']); ?>">
      <label>Name <input name="name" required></label><br>
      <label>Email <input type="email" name="email" required></label><br>
      <label>Password <input type="password" name="password" minlength="10" required></label><br>
      <button type="submit">Create Admin</button>
    </form>
  </body>
</html>
