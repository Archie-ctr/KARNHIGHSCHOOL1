<?php
// ============================================================
// Teacher Portal — Shared Sidebar Navigation
// Requires resolve_teacher.php to have been included first
// (sets $teacher, $isClassSponsor, $sponsoredClassId).
// ============================================================
if (!isset($activePage))      $activePage      = '';
if (!isset($teacher))         $teacher         = null;
if (!isset($isClassSponsor))  $isClassSponsor  = hasRole('class_teacher');
if (!isset($sponsoredClassId)) $sponsoredClassId = 0;

$ini = 'T';
if ($teacher) {
    $ini = strtoupper(
        substr($teacher['first_name']??'T',0,1).
        substr($teacher['last_name'] ??'', 0,1)
    );
}

// Unread notification count for bell badge
$_unreadNotif = 0;
try {
    $_unreadNotif = (int)db()->query(
        "SELECT COUNT(*) FROM notifications WHERE user_id=".currentUserId()." AND is_read=0"
    )->fetchColumn();
} catch (Throwable $_e) {}

// ── Build nav ────────────────────────────────────────────────
$nav = [
    'dashboard'    => ['🏠', 'Dashboard',         BASE_URL.'/portal/teacher/'],
    'classes'      => ['🏫', 'My Classes',         BASE_URL.'/portal/teacher/my_classes.php'],
    'students'     => ['🎓', 'My Students',        BASE_URL.'/portal/teacher/students.php'],
    'timetable'    => ['📅', 'Timetable',          BASE_URL.'/portal/teacher/timetable.php'],
    'attendance'   => ['📆', 'Attendance',         BASE_URL.'/portal/teacher/take_attendance.php'],
    'marks'        => ['✏️',  'Enter Marks',        BASE_URL.'/portal/teacher/enter_marks.php'],
    'results'      => ['📊', 'Results',            BASE_URL.'/portal/teacher/results.php'],
    'assessments'  => ['📝', 'Assessments',        BASE_URL.'/portal/teacher/assessments.php'],
    'quizzes'      => ['🧠', 'Online Quizzes',     BASE_URL.'/portal/teacher/quizzes.php'],
    'materials'    => ['📚', 'Learning Materials', BASE_URL.'/portal/teacher/materials.php'],
    'announcements'=> ['📢', 'Announcements',      BASE_URL.'/portal/teacher/announcements.php'],
    'notifications'=> ['🔔', 'Notifications',      BASE_URL.'/portal/notifications.php',
                       $_unreadNotif > 0 ? $_unreadNotif : null],
];

// Inject Class Sponsor section right after dashboard for any sponsor
if ($isClassSponsor) {
    $ctNav = [
        'class_dashboard' => ['🏫', 'My Class',      BASE_URL.'/portal/teacher/class_dashboard.php'.($sponsoredClassId?"?class_id=$sponsoredClassId":'')],
        'class_reports'   => ['📑', 'Class Reports',  BASE_URL.'/portal/teacher/class_reports.php'.  ($sponsoredClassId?"?class_id=$sponsoredClassId":'')],
    ];
    $keys = array_keys($nav);
    $vals = array_values($nav);
    $pos  = array_search('dashboard', $keys);
    array_splice($keys, $pos + 1, 0, array_keys($ctNav));
    array_splice($vals, $pos + 1, 0, array_values($ctNav));
    $nav = array_combine($keys, $vals);
}
?>
<aside class="portal-sidebar teacher-sidebar" id="teacherSidebar">
  <!-- Brand -->
  <div class="ts-brand">
    <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="KHS"
         style="width:32px;height:32px;border-radius:8px;object-fit:cover;flex-shrink:0"/>
    <div>
      <strong>KHS</strong>
      <small>Teacher Portal</small>
    </div>
    <button class="ts-close" id="sidebarClose" aria-label="Close menu">✕</button>
  </div>

  <!-- Teacher strip -->
  <?php if ($teacher): ?>
  <div class="ts-profile">
    <div class="ts-avatar"><?= e($ini) ?></div>
    <div class="ts-profile-text">
      <strong><?= e(($teacher['first_name']??'').' '.($teacher['last_name']??'')) ?></strong>
      <small>
        <?php
        $roleLabel = $isClassSponsor ? 'Class Sponsor / Teacher' : 'Subject Teacher';
        echo e($teacher['specialization'] ?: $roleLabel);
        ?>
      </small>
    </div>
  </div>
  <?php endif; ?>

  <!-- Nav -->
  <nav class="ts-nav" aria-label="Teacher portal navigation">
    <?php foreach ($nav as $key => $navItem):
      $icon   = $navItem[0];
      $label  = $navItem[1];
      $url    = $navItem[2];
      $badge  = $navItem[3] ?? null;   // optional notification count

      // Section labels
      if ($isClassSponsor && $key === 'class_dashboard'): ?>
        <div class="ts-divider"></div>
        <div class="ts-section-label">Class Sponsor</div>
      <?php elseif ($key === 'classes'): ?>
        <?php if ($isClassSponsor): ?>
        <div class="ts-divider"></div>
        <div class="ts-section-label">Subject Teaching</div>
        <?php endif; ?>
      <?php elseif ($key === 'notifications'): ?>
        <div class="ts-divider"></div>
      <?php endif; ?>

      <a href="<?= e($url) ?>"
         class="ts-link<?= $activePage === $key ? ' active' : '' ?>"
         <?= $activePage === $key ? 'aria-current="page"' : '' ?>>
        <span class="ts-icon"><?= $icon ?></span>
        <span><?= $label ?></span>
        <?php if ($badge): ?>
        <span class="ts-badge"><?= (int)$badge > 99 ? '99+' : (int)$badge ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <!-- Bottom -->
  <div class="ts-bottom">
    <a href="<?= BASE_URL ?>/" target="_blank" class="ts-link">
      <span class="ts-icon">🌐</span><span>School Website</span>
    </a>
    <a href="<?= BASE_URL ?>/admin/logout.php" class="ts-link ts-logout">
      <span class="ts-icon">↩</span><span>Sign Out</span>
    </a>
  </div>
</aside>
<div class="ts-overlay" id="sidebarOverlay"></div>
