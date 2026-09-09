<?php
// ============================================================
// Student Portal — Shared Sidebar Navigation
// Set $activePage before including this file.
// ============================================================
if (!isset($activePage)) $activePage = '';

$nav = [
    'dashboard'    => ['🏠', 'Dashboard',        BASE_URL.'/portal/student/'],
    'profile'      => ['👤', 'My Profile',        BASE_URL.'/portal/student/profile.php'],
    'subjects'     => ['📚', 'Subjects & Classes', BASE_URL.'/portal/student/subjects.php'],
    'timetable'    => ['📅', 'Timetable',         BASE_URL.'/portal/student/timetable.php'],
    'attendance'   => ['📆', 'Attendance',        BASE_URL.'/portal/student/attendance.php'],
    'assessments'  => ['📝', 'Assessments',       BASE_URL.'/portal/student/assessments.php'],
    'results'      => ['📊', 'My Results',        BASE_URL.'/portal/student/my_results.php'],
    'report_card'  => ['📑', 'Report Card',       BASE_URL.'/portal/student/report_card.php'],
    'fees'         => ['💰', 'Fees & Payments',   BASE_URL.'/portal/student/fees.php'],
    'library'      => ['📖', 'Library',           BASE_URL.'/portal/student/library.php'],
    'announcements'=> ['📢', 'Announcements',     BASE_URL.'/portal/student/announcements.php'],
    'graduation'   => ['🎓', 'Graduation',        BASE_URL.'/portal/student/graduation.php'],
];
?>
<aside class="portal-sidebar" style="background:#1e1b4b;border-right:none">
  <!-- Brand -->
  <div style="padding:18px 16px 14px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:10px">
    <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="KHS"
         style="width:32px;height:32px;border-radius:8px;object-fit:cover;flex-shrink:0"/>
    <div>
      <strong style="display:block;font-size:13px;font-weight:800;color:#fff">KHS</strong>
      <small style="font-size:10px;color:rgba(255,255,255,.4)">Student Portal</small>
    </div>
  </div>

  <!-- Nav -->
  <nav style="flex:1;padding:10px 8px;overflow-y:auto" aria-label="Student portal navigation">
    <?php foreach ($nav as $key => [$icon, $label, $url]): ?>
    <a href="<?= e($url) ?>"
       <?= $activePage === $key ? 'class="active" aria-current="page"' : '' ?>
       style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;
              font-size:13px;font-weight:600;
              color:<?= $activePage === $key ? '#fff' : 'rgba(255,255,255,.62)' ?>;
              background:<?= $activePage === $key ? 'rgba(255,255,255,.13)' : 'none' ?>;
              text-decoration:none;margin-bottom:1px;transition:all .15s">
      <span style="font-size:15px;width:20px;text-align:center;flex-shrink:0"><?= $icon ?></span>
      <?= $label ?>
    </a>
    <?php endforeach; ?>
  </nav>

  <!-- Bottom -->
  <div style="padding:10px 8px;border-top:1px solid rgba(255,255,255,.06)">
    <a href="<?= BASE_URL ?>/" target="_blank"
       style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;
              font-size:12px;color:rgba(255,255,255,.4);text-decoration:none">
      🌐 School Website
    </a>
    <a href="<?= BASE_URL ?>/admin/logout.php"
       style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;
              font-size:13px;font-weight:600;color:rgba(255,100,100,.75);text-decoration:none">
      ↩ Sign Out
    </a>
  </div>
</aside>
