<?php
session_start();
include('admin/vendor/inc/config.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include("vendor/inc/head.php");?>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/login.css">

  <style>
    :root{ --kaya-ink:#000047; --kaya-nav:#0A0F2C; --kaya-border:#e5e7eb; }
    html,body{height:100%; font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif; color:#111827;}

    .split-container{display:flex; min-height:100vh; margin:0; padding:0; background:#f8fafc;}
    .left-side{ flex:1; position:relative; background:#0b102f;
      background-image:url('assets/images/login-bg.jpg'); background-size:cover; background-position:center; }
    .left-side::before{ content:""; position:absolute; inset:0;
      background:linear-gradient(120deg, rgba(10,15,44,.75), rgba(10,15,44,.35)); }
    .left-content{ position:relative; z-index:1; color:#fff; height:100%;
      display:flex; flex-direction:column; justify-content:center; padding:3rem; }
    .brand{ font-weight:800; letter-spacing:.06em; text-transform:uppercase; color:#fff; font-size:1.1rem; opacity:.9; }
    .hero-title{font-weight:800; font-size:2.1rem; line-height:1.15; margin:.75rem 0 .5rem;}
    .hero-sub{opacity:.9; max-width:34ch}

    .right-side{flex:1; display:flex; align-items:center; justify-content:center; padding:2rem;}
    .login-form-box{ width:420px; max-width:94vw; background:#fff; border:1px solid var(--kaya-border);
      border-radius:1rem; box-shadow:0 14px 40px rgba(2,6,23,.08); padding:1.75rem 1.5rem 2rem; }
    .login-title{ font-weight:800; color:var(--kaya-ink); font-size:1.75rem; line-height:1.2; text-align:center; margin:0 0 1rem; }

    .kaya-field{ margin-bottom:1rem; }
    .kaya-field label{ display:block; font-weight:600; color:#374151; font-size:.92rem; margin-bottom:.35rem; }
    .kaya-ctrl{ position:relative; background:#eef2ff; border-radius:.6rem; }
    .kaya-ctrl .left-icon{ position:absolute; left:.65rem; top:50%; transform:translateY(-50%);
      width:1.25rem; text-align:center; color:#6b7280; }
    .kaya-ctrl .right-icon{ position:absolute; right:.6rem; top:50%; transform:translateY(-50%);
      width:1.25rem; text-align:center; color:#6b7280; cursor:pointer; }
    .kaya-input{ width:100%; border:none; outline:0; background:transparent;
      padding:.8rem .9rem .7rem 2.2rem; border-radius:.6rem; font-size:1rem; color:#111827; }
    .kaya-input:focus{ box-shadow:inset 0 0 0 2px rgba(0,0,71,.08); }

    .btn-kaya-primary{ display:block; width:100%; background:var(--kaya-nav); border:1px solid var(--kaya-nav);
      color:#fff; padding:.9rem 1rem; border-radius:9999px; font-weight:700; letter-spacing:.02em; }
    .btn-kaya-primary:hover{ background:#0c1438; border-color:#0c1438; color:#fff; }

    .login-meta{ text-align:center; margin-top:1rem; font-size:.92rem; }
    .login-meta a{ color:var(--kaya-ink); font-weight:600; }

    @media (max-width: 992px){ .left-content{ padding:2rem; } }
    @media (max-width: 768px){ .split-container{flex-direction:column;} .left-side{ display:none; } }
  </style>
</head>

<body class="split-container">

  <aside class="left-side">
    <div class="left-content">
      <div class="brand">KAYA</div>
      <h1 class="hero-title">Driver Portal</h1>
      <p class="hero-sub">See assigned trips, start and complete rides, and view your schedule.</p>
    </div>
  </aside>

  <main class="right-side">
    <div class="login-form-box">
      <h2 class="login-title">Driver Login</h2>

      <?php if(isset($_SESSION['error'])): ?>
        <div class="alert alert-danger mb-3"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
      <?php endif; ?>

      <form action="login-process.php" method="post" autocomplete="off">
        <input type="hidden" name="expect_role" value="driver">

        <div class="kaya-field">
          <label for="email">Email</label>
          <div class="kaya-ctrl">
            <i class="fas fa-envelope left-icon"></i>
            <input type="email" class="kaya-input" id="email" name="email" required placeholder="driver@example.com">
          </div>
        </div>

        <div class="kaya-field">
          <label for="password">Password</label>
          <div class="kaya-ctrl">
            <i class="fas fa-lock left-icon"></i>
            <input type="password" class="kaya-input" id="password" name="password" required placeholder="••••••••">
            <i class="fas fa-eye right-icon" id="togglePwd" title="Show/Hide password"></i>
          </div>
        </div>

        <button type="submit" class="btn btn-kaya-primary">Login</button>
      </form>
    </div>
  </main>

  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script>
    (function(){
      var eye = document.getElementById('togglePwd');
      var pwd = document.getElementById('password');
      if (!eye || !pwd) return;
      eye.addEventListener('click', function(){
        pwd.type = (pwd.type === 'password') ? 'text' : 'password';
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
      });
    })();
  </script>
</body>
</html>
