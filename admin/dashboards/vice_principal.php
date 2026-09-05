<?php
$pageTitle='Vice Principal Dashboard'; $activeAdmin='dashboard';
require_once dirname(dirname(__DIR__)).'/includes/admin_header.php';
$pdo=db(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
$fn=explode(' ',currentUser()['name']??'VP')[0]; $hour=(int)date('G');
$greet=$hour<12?'Good morning':($hour<17?'Good afternoon':'Good evening');
$approvals=countPendingApprovals(); $total=$approvals['_total']??0;
$pendingMarks=(int)$pdo->query("SELECT COUNT(*) FROM assessment_scores WHERE status IN ('submitted','resubmitted') AND academic_year_id=$ayId")->fetchColumn();
$totalStudents=(int)$pdo->query("SELECT COUNT(*) FROM students WHERE status='Active'")->fetchColumn();
$attendanceRate=$pdo->query("SELECT ROUND(SUM(status='Present')/COUNT(*)*100,1) FROM attendance WHERE academic_year_id=$ayId")->fetchColumn();
$openCases=(int)$pdo->query("SELECT COUNT(*) FROM discipline_records WHERE resolved=0")->fetchColumn();
?>
<div class="page-heading">
  <div><div class="eyebrow"><?=date('l, F d, Y')?> <span></span></div><h1><?=$greet?>, <?=e($fn)?>.</h1><p>Vice Principal — <?=e($ay)?></p></div>
  <?php if($total>0): ?>
  <a href="<?=BASE_URL?>/admin/approval_center.php" class="button button-primary">✅ <?=$total?> Pending Approval<?=$total!=1?'s':''?></a>
  <?php endif; ?>
</div>

<?php if($pendingMarks>0): ?>
<div class="alert alert-info">✏️ <strong><?=$pendingMarks?> mark record<?=$pendingMarks!=1?'s':''?></strong> submitted by teachers and awaiting your review. <a href="<?=BASE_URL?>/admin/marks_approval.php" style="font-weight:700">Review now →</a></div>
<?php endif; ?>

<div class="metric-grid" style="margin-bottom:24px">
  <div class="metric-card"><div class="metric-top"><span>Active Students</span><div class="metric-icon">🎓</div></div><strong><?=number_format($totalStudents)?></strong></div>
  <div class="metric-card <?=$pendingMarks>0?'finance-metrics':''?>"><div class="metric-top"><span>Marks Pending Review</span><div class="metric-icon">✏️</div></div><strong><?=$pendingMarks?></strong><small><i></i>Submitted by teachers</small></div>
  <div class="metric-card"><div class="metric-top"><span>Attendance Rate</span><div class="metric-icon">📆</div></div><strong><?=$attendanceRate??'—'?>%</strong><small><i></i>This academic year</small></div>
  <div class="metric-card <?=$openCases>0?'finance-metrics':''?>"><div class="metric-top"><span>Open Discipline Cases</span><div class="metric-icon">⚖️</div></div><strong><?=$openCases?></strong></div>
</div>

<div class="quick-grid">
  <a href="<?=BASE_URL?>/admin/marks_approval.php"  class="quick-item"><span class="qi-icon">🔍</span><div><strong>Review Marks</strong><small><?=$pendingMarks?> pending</small></div></a>
  <a href="<?=BASE_URL?>/admin/approval_center.php" class="quick-item"><span class="qi-icon">✅</span><div><strong>Approval Center</strong><small><?=$total?> items</small></div></a>
  <a href="<?=BASE_URL?>/admin/attendance.php"      class="quick-item"><span class="qi-icon">📆</span><div><strong>Attendance</strong><small>View reports</small></div></a>
  <a href="<?=BASE_URL?>/admin/discipline.php"      class="quick-item"><span class="qi-icon">⚖️</span><div><strong>Discipline</strong><small><?=$openCases?> open</small></div></a>
  <a href="<?=BASE_URL?>/admin/students.php"        class="quick-item"><span class="qi-icon">🎓</span><div><strong>Students</strong><small>Directory</small></div></a>
  <a href="<?=BASE_URL?>/admin/timetable.php"       class="quick-item"><span class="qi-icon">⏰</span><div><strong>Timetable</strong><small>Manage schedule</small></div></a>
  <a href="<?=BASE_URL?>/admin/report_cards.php"    class="quick-item"><span class="qi-icon">📑</span><div><strong>Report Cards</strong><small>Generate & publish</small></div></a>
  <a href="<?=BASE_URL?>/admin/promotion.php"       class="quick-item"><span class="qi-icon">⬆️</span><div><strong>Promotion</strong><small>Year-end review</small></div></a>
</div>
<?php require_once dirname(dirname(__DIR__)).'/includes/admin_footer.php'; ?>
