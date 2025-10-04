<?php
// vendor/inc/checklogin.php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/config.php';

function check_login() {
  global $mysqli;

  // Preferred: new session key
  if (!empty($_SESSION['account_id'])) {
    $id = (int)$_SESSION['account_id'];
  } else {
    // Bridge: map legacy u_id -> accounts by email
    $id = 0;
    if (!empty($_SESSION['u_id'])) {
      if ($s=$mysqli->prepare("SELECT u_email FROM tms_user WHERE u_id=? LIMIT 1")) {
        $s->bind_param('i', $_SESSION['u_id']);
        $s->execute(); $s->bind_result($email); $s->fetch(); $s->close();
        if ($email) {
          if ($s=$mysqli->prepare("SELECT id FROM accounts WHERE is_active=1 AND LOWER(email)=LOWER(?) LIMIT 1")) {
            $s->bind_param('s',$email);
            $s->execute(); $s->bind_result($id); $s->fetch(); $s->close();
            if ($id) $_SESSION['account_id'] = (int)$id;
          }
        }
      }
    }
  }

  if (empty($id)) { header("Location: /driver-login.php?err=auth"); exit; }

  // preload name/email for navbar
  if (empty($_SESSION['name']) || empty($_SESSION['email'])) {
    if ($s=$mysqli->prepare("SELECT name,email FROM accounts WHERE id=? AND is_active=1")) {
      $s->bind_param('i',$id); $s->execute(); $s->bind_result($nm,$em);
      if ($s->fetch()) { $_SESSION['name']=$nm; $_SESSION['email']=$em; }
      $s->close();
    }
  }
}
