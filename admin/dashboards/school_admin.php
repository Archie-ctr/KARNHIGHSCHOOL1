<?php
$pageTitle='School Administrator Dashboard'; $activeAdmin='dashboard';
require_once dirname(dirname(__DIR__)).'/includes/admin_header.php';
$pdo=db(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
$fn=explode(' ',currentUser()['name']??'Admin')[0]; $hour=(int)date('G');
$greet=$hour<12?'Good morning':($hour<17?'Good afternoon':'Good evening');
$approvals=countPendingApprovals();
$totalStudents=(int)$pdo->query("SELECT COUNT(*) FROM students WHERE status='Active'")->fetchColumn();
$totalTeachers=(int)$pdo->query("SELECT COUNT(*) FROM teachers WHERE status='Active'")->fetchColumn();
$totalClasses=(int)$pdo->query("SELECT COUNT(*) FROM classes WHERE academic_year_id=$ayId")->fetchColumn();
$pendingApps=(int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status IN ('Application Submitted','Under Review')")->fetchColumn();
?>
<div class="page-heading">
  <div><div class="eyebrow"><?=date('l, F d, Y')?> <span></span></div><h1><?=$greet?>, <?=e($fn)?>.</h1><p>School Administrator — <?=e($ay)?></p></div>
  <a href="<?=BASE_URL?>/admin/approval_center.php" class="button button-primary">✅ Approval Center <?=$approvals['_total']?'('.$approvals['_total'].')':''?></a>
</div>
<div class="metric-grid" style="margin-bottom:24px">
  <div class="metric-card"><div class="metric-top"><span>Students</span><div class="metric-icon">🎓</div></div><strong><?=number_format($totalStudents)?></strong></div>
  <div class="metric-card"><div class="metric-top"><span>Teachers</span><div class="metric-icon">👩‍🏫</div></div><strong><?=$totalTeachers?></strong></div>
  <div class="metric-card"><div class="metric-top"><span>Classes</span><div class="metric-icon">🏫</div></div><strong><?=$totalClasses?></strong><small><i></i><?=e($ay)?></small></div>
  <div class="metric-card finance-metrics"><div class="metric-top"><span>Pending Admissions</span><div class="metric-icon">📋</div></div><strong><?=$pendingApps?></strong></div>
</div>
<div class="quick-grid">
  <a href="<?=BASE_URL?>/admin/students.php"       class="quick-item"><span class="qi-icon">🎓</span><div><strong>Students</strong><small>All records</small></div></a>
  <a href="<?=BASE_URL?>/admin/teachers_admin.php" class="quick-item"><span class="qi-icon">👩‍🏫</span><div><strong>Teachers</strong><small>Staff management</small></div></a>
  <a href="<?=BASE_URL?>/admin/classes.php"        class="quick-item"><span class="qi-icon">🏫</span><div><strong>Classes</strong><small>Academic structure</small></div></a>
  <a href="<?=BASE_URL?>/admin/applications.php"   class="quick-item"><span class="qi-icon">📋</span><div><strong>Admissions</strong><small>Applications</small></div></a>
  <a href="<?=BASE_URL?>/admin/finance.php"        class="quick-item"><span class="qi-icon">💰</span><div><strong>Finance</strong><small>Fees & payments</small></div></a>
  <a href="<?=BASE_URL?>/admin/announcements.php"  class="quick-item"><span class="qi-icon">📢</span><div><strong>Announcements</strong><small>Communicate</small></div></a>
  <a href="<?=BASE_URL?>/admin/reports.php"        class="quick-item"><span class="qi-icon">📈</span><div><strong>Reports</strong><small>Export data</small></div></a>
  <a href="<?=BASE_URL?>/admin/settings.php"       class="quick-item"><span class="qi-icon">⚙️</span><div><strong>Settings</strong><small>School config</small></div></a>
</div>
<?php require_once dirname(dirname(__DIR__)).'/includes/admin_footer.php'; ?>
