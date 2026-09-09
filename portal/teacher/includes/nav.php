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

$isClassTeacher = hasRole('class_teacher');

// Base nav for all teachers
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

// Class Teacher exclusive items — injected after 'dashboard'
if ($isClassTeacher) {
    $classTeacherNav = [
        'class_dashboard' => ['🏫', 'My Class',         BASE_URL.'/portal/teacher/class_dashboard.php'],
        'class_reports'   => ['📑', 'Class Reports',    BASE_URL.'/portal/teacher/class_reports.php'],
    ];
    // Splice after 'dashboard'
    $navKeys  = array_keys($nav);
    $navVals  = array_values($nav);
    $dashPos  = array_search('dashboard',$navKeys);
    array_splice($navKeys, $dashPos+1, 0, array_keys($classTeacherNav));
    array_splice($navVals, $dashPos+1, 0, array_values($classTeacherNav));
    $nav = array_combine($navKeys,$navVals);
}
?>
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
    <?php foreach ($nav as $key => [$icon, $label, $url]):
      // Visual separator before class teacher section
      if ($isClassTeacher && $key === 'class_dashboard'): ?>
    <div style="margin:6px 14px;height:1px;background:rgba(255,255,255,.08)"></div>
    <div style="padding:6px 14px 2px;font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.35)">Class Teacher</div>
    <?php elseif ($isClassTeacher && $key === 'classes'): ?>
    <div style="margin:6px 14px;height:1px;background:rgba(255,255,255,.08)"></div>
    <div style="padding:6px 14px 2px;font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.35)">Teaching</div>
    <?php endif; ?>
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
