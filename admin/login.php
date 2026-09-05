<?php
// ============================================================
// KARN HIGH SCHOOL — Admin Login
// ============================================================
require_once dirname(__DIR__) . '/config/db.php';

// Already logged in → go to dashboard
if (isAdminLoggedIn()) {
    redirect('/KARNHIGHSCHOOL/admin/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please enter your email address and password.';
    } else {
        $stmt = db()->prepare(
            'SELECT id, name, email, password_hash, role, is_active FROM admin_users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $user['is_active'] && password_verify($password, $user['password_hash'])) {
            // Regenerate session ID on login (session fixation protection)
            session_regenerate_id(true);
            $_SESSION['admin_id']    = $user['id'];
            $_SESSION['admin_name']  = $user['name'];
            $_SESSION['admin_email'] = $user['email'];
            $_SESSION['admin_role']  = $user['role'];

            // Update last login timestamp
            db()->prepare('UPDATE admin_users SET last_login = NOW() WHERE id = ?')
               ->execute([$user['id']]);

            redirect('/KARNHIGHSCHOOL/admin/index.php');
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Staff Login — Karn High School</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="/KARNHIGHSCHOOL/assets/css/style.css"/>
</head>
<body>

<div class="login-page">
  <div class="login-box">
    <img src="/KARNHIGHSCHOOL/assets/images/logo.jpg" alt="Karn High School logo"/>
    <div class="eyebrow" style="justify-content:center;margin-bottom:8px">KHS management system <span></span></div>
    <h2>Welcome back.</h2>
    <p>Sign in to access the school administration portal.</p>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/KARNHIGHSCHOOL/admin/login.php" novalidate>
      <?= csrfField() ?>
      <label>Email address
        <input type="email" name="email" required autocomplete="email"
               placeholder="you@karnhighschool.edu.lr"
               value="<?= e($_POST['email'] ?? '') ?>"/>
      </label>
      <label>Password
        <input type="password" name="password" required autocomplete="current-password"
               placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"/>
      </label>
      <button class="button button-primary full" type="submit">Sign in &rarr;</button>
    </form>

    <small>
      Default credentials (change after first login):<br>
      <strong>admin@karnhighschool.edu.lr</strong> &nbsp;/&nbsp; <strong>1234</strong>
    </small>

    <div style="margin-top:20px">
      <a href="/KARNHIGHSCHOOL/" style="font-size:13px;color:var(--ink-faint);">&larr; Back to school website</a>
    </div>
  </div>
</div>

<script src="/KARNHIGHSCHOOL/assets/js/main.js"></script>
</body>
</html>
