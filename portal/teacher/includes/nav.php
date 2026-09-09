<?php
// ============================================================
// Teacher Portal — Shared Sidebar Navigation
// Set $activePage before including this file.
// Set $teacher (array with teacher record) if available.
// ============================================================
if (!isset($activePage)) $activePage = '';
if (!isset($teacher))    $teacher    = null;

$ini = '';
if ($teacher) {
    $ini = strtoupper(substr($teacher['first_name']??'',0,1).substr($teacher['last_name']??'',0,1));
}

$nav = [
    'dashboard'   => ['🏠', 'Dashboard',        BASE_URL.'/portal/teacher/'],
    'classes'     => ['🏫', 'My Classes',        BASE_URL.'/portal/teacher/my_classes.php'],
    'students'    => ['🎓', 'My Students',       BASE_URL.'/portal/teacher/students.php'],
    'timetable'   => ['📅', 'Timetable',         BASE_URL.'/portal/teacher/timetable.php'],
    'attendance'  => ['📆', 'Attendance',        BASE_URL.'/portal/teacher/take_attendance.php'],
    'marks'       => ['✏️', 'Enter Marks',       BASE_URL.'/portal/teacher/enter_marks.php'],
    'results'     => ['📊', 'Results',           BASE_URL.'/portal/teacher/results.php'],
    'assessments' => ['📝', 'Assessments',       BASE_URL.'/portal/teacher/assessments.php'],
    'quizzes'     => ['🧠', 'Online Quizzes',    BASE_URL.'/portal/teacher/quizzes.php'],
    'materials'   => ['📚', 'Learning Materials',BASE_URL.'/portal/teacher/materials.php'],
    'announcements'=> ['📢', 'Announcements',    BASE_URL.'/portal/teacher/announcements.php'],
];
?>
<aside class="portal-sidebar">
  <div class="portal-brand">
    <div class="brand">
      <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="KHS"/>
      <span><strong>KHS</strong><small>Teacher Portal</small></span>
    </div>
  </div>

  <?php if ($teacher): ?>
  <div style="padding:10px 14px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:10px">
    <div class="avatar" style="width:34px;height:34px;font-size:12px;flex-shrink:0"><?= e($ini) ?></div>
    <div style="min-width:0">
      <strong style="display:block;font-size:12px;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e(($teacher['first_name']??'').' '.($teacher['last_name']??'')) ?></strong>
      <small style="font-size:10px;color:rgba(255,255,255,.45)"><?= e($teacher['specialization'] ?? 'Teacher') ?></small>
    </div>
  </div>
  <?php endif; ?>

  <nav class="portal-nav" aria-label="Teacher portal navigation">
    <?php foreach ($nav as $key => [$icon, $label, $url]): ?>
    <a href="<?= e($url) ?>" <?= $activePage === $key ? 'class="active" aria-current="page"' : '' ?>>
      <?= $icon ?> <?= $label ?>
    </a>
    <?php endforeach; ?>
  </nav>

  <div style="border-top:1px solid var(--line);padding:12px">
    <a href="<?= BASE_URL ?>/" target="_blank" style="display:block;font-size:12px;color:var(--ink-faint);margin-bottom:8px">🌐 School Website</a>
    <a href="<?= BASE_URL ?>/admin/logout.php" style="color:var(--error);font-size:13px;font-weight:600">⬡ Sign Out</a>
  </div>
</aside>
