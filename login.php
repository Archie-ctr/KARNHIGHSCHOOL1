<?php
// ============================================================
// KHSMIS — Unified Login (Staff / Student / Parent / Applicant)
// ============================================================
require_once __DIR__.'/config/db.php';

if (isLoggedIn()) portalRedirect();

$error = '';
$tab   = $_GET['tab'] ?? 'staff'; // staff | student | parent

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $loginTab = $_POST['login_tab'] ?? 'staff';
    $email    = trim($_POST['email']    ?? '');
    $phone    = trim($_POST['phone']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $identifier = $email ?: $phone;

    if (!$identifier || !$password) {
        $error = 'Please enter your credentials and password.';
    } else {
        // Query unified users table
        $stmt = db()->prepare(
            'SELECT u.id, u.name, u.email, u.phone, u.password_hash, u.is_active, u.photo,
                    r.name AS role, r.label AS role_label
             FROM users u JOIN roles r ON r.id=u.role_id
             WHERE (u.email=? OR u.phone=?) LIMIT 1'
        );
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        // Also check legacy admin_users table for backward compat
        if (!$user) {
            $stmt2 = db()->prepare(
                'SELECT id, name, email, NULL AS phone, password_hash, is_active, NULL AS photo,
                        role AS role, role AS role_label
                 FROM admin_users WHERE email=? LIMIT 1'
            );
            $stmt2->execute([$identifier]);
            $user = $stmt2->fetch();
        }

        if ($user && $user['is_active'] && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'         => (int)$user['id'],
                'name'       => $user['name'],
                'email'      => $user['email'],
                'phone'      => $user['phone'],
                'role'       => $user['role'],
                'role_label' => $user['role_label'],
                'photo'      => $user['photo'],
            ];

            // Update last_login
            try {
                db()->prepare('UPDATE users SET last_login=NOW() WHERE id=?')->execute([$user['id']]);
            } catch (Throwable $e) {
                try { db()->prepare('UPDATE admin_users SET last_login=NOW() WHERE id=?')->execute([$user['id']]); } catch (Throwable $e2) {}
            }

            auditLog('login', 'auth', 'user', (int)$user['id'], '', '');
            portalRedirect();
        } else {
            $error = 'Invalid credentials. Please check your email/phone and password.';
            auditLog('failed_login', 'auth', 'user', 0, $identifier, '');
        }
    }
    $tab = $_POST['login_tab'] ?? $tab;
}

$schoolName = setting('school_name','KARN HIGH SCHOOL');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Login — <?= e($schoolName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body class="login-body">

<div class="login-page">
  <div class="login-split">
    <!-- Branding panel -->
    <div class="login-brand-panel">
      <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="<?= e($schoolName) ?> logo" class="login-brand-logo"/>
      <h1><?= e($schoolName) ?></h1>
      <p><?= e(setting('school_tagline','Building Knowledge, Character and a Better Future')) ?></p>
      <div class="login-brand-footer">Karnplay, Nimba, Liberia</div>
    </div>

    <!-- Login panel -->
    <div class="login-form-panel">
      <div class="login-box">
        <div class="eyebrow" style="justify-content:center;margin-bottom:12px">KHSMIS Portal <span></span></div>
        <h2>Sign in</h2>
        <p>Access your school portal</p>

        <!-- Role tabs -->
        <div class="login-tabs">
          <a href="?tab=staff"   class="login-tab <?= $tab==='staff'   ?'active':'' ?>">Staff</a>
          <a href="?tab=student" class="login-tab <?= $tab==='student' ?'active':'' ?>">Student</a>
          <a href="?tab=parent"  class="login-tab <?= $tab==='parent'  ?'active':'' ?>">Parent</a>
        </div>

        <?php if ($error): ?>
          <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
          <div class="alert alert-warning">You do not have permission to access that page.</div>
        <?php endif; ?>

        <form method="post" novalidate>
          <?= csrfField() ?>
          <input type="hidden" name="login_tab" value="<?= e($tab) ?>"/>

          <?php if ($tab === 'student'): ?>
            <label>Student ID or Email
              <input type="text" name="email" required autocomplete="username"
                     placeholder="KHS-2024-0001 or email"
                     value="<?= e($_POST['email'] ?? '') ?>"/>
            </label>
          <?php elseif ($tab === 'parent'): ?>
            <label>Phone number or Email
              <input type="text" name="email" required autocomplete="username"
                     placeholder="+231 ... or email"
                     value="<?= e($_POST['email'] ?? '') ?>"/>
            </label>
          <?php else: ?>
            <label>Email address
              <input type="email" name="email" required autocomplete="email"
                     placeholder="you@karnhighschool.edu.lr"
                     value="<?= e($_POST['email'] ?? '') ?>"/>
            </label>
          <?php endif; ?>

          <label>Password
            <input type="password" name="password" required autocomplete="current-password"
                   placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"/>
          </label>

          <button class="button button-primary full" type="submit">Sign in &rarr;</button>
        </form>

        <div class="login-links">
          <a href="<?= BASE_URL ?>/apply.php">New student? Apply for admission</a>
          <a href="<?= BASE_URL ?>/application-status.php">Track application status</a>
        </div>

        <div class="demo-credentials">
          <strong>Demo credentials — all use password: <code>1234</code></strong>
          <div class="demo-grid">
            <span>Sys Admin:</span>      <code>sysadmin@karnhighschool.edu.lr</code>
            <span>School Admin:</span>   <code>schooladmin@karnhighschool.edu.lr</code>
            <span>Principal:</span>      <code>principal@karnhighschool.edu.lr</code>
            <span>Vice Principal:</span> <code>vp@karnhighschool.edu.lr</code>
            <span>Registrar:</span>      <code>registrar@karnhighschool.edu.lr</code>
            <span>Accountant:</span>     <code>accountant@karnhighschool.edu.lr</code>
            <span>Fin. Officer:</span>   <code>finance@karnhighschool.edu.lr</code>
            <span>Teacher:</span>        <code>teacher@karnhighschool.edu.lr</code>
            <span>Class Teacher:</span>  <code>classteacher@karnhighschool.edu.lr</code>
            <span>Discipline:</span>     <code>discipline@karnhighschool.edu.lr</code>
            <span>Librarian:</span>      <code>librarian@karnhighschool.edu.lr</code>
            <span>ICT Officer:</span>    <code>ict@karnhighschool.edu.lr</code>
            <span>Student:</span>        <code>student@karnhighschool.edu.lr</code>
            <span>Parent:</span>         <code>parent@karnhighschool.edu.lr</code>
          </div>
        </div>

        <a href="<?= BASE_URL ?>/" class="login-back">&larr; Back to website</a>
      </div>
    </div>
  </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
