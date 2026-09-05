<?php
$pageTitle='Dashboard'; $activeAdmin='dashboard';
require_once dirname(dirname(__DIR__)).'/includes/admin_header.php';
$fn=explode(' ',currentUser()['name']??'Staff')[0];
$role=ucwords(str_replace('_',' ',currentRole()));
?>
<div class="page-heading">
  <div>
    <div class="eyebrow"><?=date('l, F d, Y')?> <span></span></div>
    <h1>Welcome, <?=e($fn)?>.</h1>
    <p><?=e($role)?> — <?=e(currentAcademicYearName())?></p>
  </div>
</div>
<div class="quick-grid">
  <?php if(can('students.view')):?><a href="<?=BASE_URL?>/admin/students.php" class="quick-item"><span class="qi-icon">🎓</span><div><strong>Students</strong></div></a><?php endif;?>
  <?php if(can('attendance.take')||can('attendance.view')):?><a href="<?=BASE_URL?>/admin/attendance.php" class="quick-item"><span class="qi-icon">📆</span><div><strong>Attendance</strong></div></a><?php endif;?>
  <?php if(can('marks.create')):?><a href="<?=BASE_URL?>/admin/marks_entry.php" class="quick-item"><span class="qi-icon">✏️</span><div><strong>Enter Marks</strong></div></a><?php endif;?>
  <?php if(can('marks.view')):?><a href="<?=BASE_URL?>/admin/results.php" class="quick-item"><span class="qi-icon">📊</span><div><strong>Results</strong></div></a><?php endif;?>
  <?php if(can('library.view')):?><a href="<?=BASE_URL?>/admin/library.php" class="quick-item"><span class="qi-icon">📚</span><div><strong>Library</strong></div></a><?php endif;?>
  <?php if(can('discipline.view')):?><a href="<?=BASE_URL?>/admin/discipline.php" class="quick-item"><span class="qi-icon">⚖️</span><div><strong>Discipline</strong></div></a><?php endif;?>
  <?php if(can('comms.view_announcements')):?><a href="<?=BASE_URL?>/admin/announcements.php" class="quick-item"><span class="qi-icon">📢</span><div><strong>Announcements</strong></div></a><?php endif;?>
  <?php if(can('finance.view')):?><a href="<?=BASE_URL?>/admin/finance.php" class="quick-item"><span class="qi-icon">💰</span><div><strong>Finance</strong></div></a><?php endif;?>
</div>
<?php require_once dirname(dirname(__DIR__)).'/includes/admin_footer.php'; ?>
