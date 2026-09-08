<?php
// ============================================================
// Student Portal — Shared Sidebar Navigation
// Include this in every student portal page.
// Expects $activePage to be set before inclusion, e.g.:
//   $activePage = 'results';
// ============================================================
if (!isset($activePage)) $activePage = '';

$nav = [
    'dashboard'    => ['🏠', 'Dashboard',      BASE_URL.'/portal/student/'],
    'profile'      => ['👤', 'My Profile',      BASE_URL.'/portal/student/profile.php'],
    'subjects'     => ['📚', 'My Subjects',     BASE_URL.'/portal/student/subjects.php'],
    'timetable'    => ['📅', 'Timetable',       BASE_URL.'/portal/student/timetable.php'],
    'attendance'   => ['📆', 'Attendance',      BASE_URL.'/portal/student/attendance.php'],
    'results'      => ['📊', 'My Results',      BASE_URL.'/portal/student/my_results.php'],
    'report_card'  => ['📑', 'Report Card',     BASE_URL.'/portal/student/report_card.php'],
    'fees'         => ['💰', 'Fees & Payments', BASE_URL.'/portal/student/fees.php'],
    'library'      => ['📖', 'Library',         BASE_URL.'/portal/student/library.php'],
    'announcements'=> ['📢', 'Announcements',   BASE_URL.'/portal/student/announcements.php'],
];
?>
<aside class="portal-sidebar">
  <div class="portal-brand">
    <div class="brand">
      <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="KHS"/>
      <span><strong>KHS</strong><small>Student Portal</small></span>
    </div>
  </div>

  <nav class="portal-nav" aria-label="Student portal navigation">
    <?php foreach ($nav as $key => [$icon, $label, $url]): ?>
    <a href="<?= e($url) ?>" <?= $activePage === $key ? 'class="active" aria-current="page"' : '' ?>>
      <?= $icon ?> <?= $label ?>
    </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-bottom" style="border-top:1px solid var(--line);padding:12px">
    <a href="<?= BASE_URL ?>/" target="_blank" style="display:block;font-size:12px;color:var(--ink-faint);margin-bottom:8px">🌐 School Website</a>
    <a href="<?= BASE_URL ?>/admin/logout.php" style="color:var(--error);font-size:13px;font-weight:600">⬡ Sign Out</a>
  </div>
</aside>
