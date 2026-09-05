<?php
$pageTitle='Teacher Dashboard'; $activeAdmin='dashboard';
require_once dirname(dirname(__DIR__)).'/includes/admin_header.php';
$pdo=db(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
$teacher=currentTeacher();
$fn=explode(' ',currentUser()['name']??'Teacher')[0];
$hour=(int)date('G'); $greet=$hour<12?'Good morning':($hour<17?'Good afternoon':'Good evening');

// Teacher metrics
$myClasses=myClassIds();
$studentCount=empty($myClasses)?0:(int)$pdo->query("SELECT COUNT(*) FROM students WHERE current_class_id IN (".implode(',',array_map('intval',$myClasses)).") AND status='Active'")->fetchColumn();

// Marks status breakdown
$draftMarks=(int)$pdo->prepare("SELECT COUNT(*) FROM assessment_scores WHERE entered_by=? AND status='draft' AND academic_year_id=?")->execute([currentUserId(),$ayId])?$pdo->query("SELECT COUNT(*) FROM assessment_scores WHERE entered_by=".currentUserId()." AND status='draft' AND academic_year_id=$ayId")->fetchColumn():0;
$submittedMarks=(int)$pdo->query("SELECT COUNT(*) FROM assessment_scores WHERE entered_by=".currentUserId()." AND status IN ('submitted','resubmitted') AND academic_year_id=$ayId")->fetchColumn();
$returnedMarks=(int)$pdo->query("SELECT COUNT(*) FROM assessment_scores WHERE entered_by=".currentUserId()." AND status='returned' AND academic_year_id=$ayId")->fetchColumn();

// Today's attendance
$attendanceToday=$pdo->query("SELECT COUNT(*) FROM attendance WHERE recorded_by=".currentUserId()." AND date=CURDATE()")->fetchColumn();
?>

<div class="page-heading">
  <div>
    <div class="eyebrow"><?=date('l, F d, Y')?> <span></span></div>
    <h1><?=$greet?>, <?=e($fn)?>.</h1>
    <p><?=e(currentUser()['role_label']??'Teacher')?> — <?=e($ay)?></p>
  </div>
  <?php if($returnedMarks>0): ?>
  <a href="<?=BASE_URL?>/admin/marks_entry.php" class="button button-primary" style="animation:pulse 2s infinite">
    ↩ <?=$returnedMarks?> Returned Mark<?=$returnedMarks!=1?'s':''?>
  </a>
  <?php endif; ?>
</div>

<!-- Returned marks alert -->
<?php if($returnedMarks>0): ?>
<div class="alert alert-warning alert-sticky">
  ↩ <strong><?=$returnedMarks?> mark record<?=$returnedMarks!=1?'s':''?> were returned for correction.</strong>
  Please <a href="<?=BASE_URL?>/admin/marks_entry.php" style="color:var(--warning);font-weight:700">correct and resubmit them</a>.
</div>
<?php endif; ?>

<div class="metric-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px">
  <div class="metric-card"><div class="metric-top"><span>My Classes</span><div class="metric-icon">🏫</div></div><strong><?=count($myClasses)?></strong><small><i></i><?=e($ay)?></small></div>
  <div class="metric-card"><div class="metric-top"><span>My Students</span><div class="metric-icon">🎓</div></div><strong><?=number_format($studentCount)?></strong><small><i></i>Across all classes</small></div>
  <div class="metric-card <?=$returnedMarks>0?'finance-metrics':''?>"><div class="metric-top"><span>Draft Marks</span><div class="metric-icon">✏️</div></div><strong><?=$draftMarks?></strong><small><i></i><?=$returnedMarks>0?$returnedMarks.' returned':'Ready to submit'?></small></div>
  <div class="metric-card"><div class="metric-top"><span>Attendance Today</span><div class="metric-icon">📆</div></div><strong><?=$attendanceToday?><?=$attendanceToday?'<small><i></i>Taken</small>':'<small style="color:var(--warning)"><i></i>Not taken</small>'?></div>
</div>

<!-- Mark status workflow -->
<?php if($draftMarks>0||$submittedMarks>0): ?>
<div class="panel" style="padding:20px 22px;margin-bottom:20px">
  <h3 style="font-size:15px;font-weight:700;margin-bottom:14px">📊 My Marks Status</h3>
  <div style="display:flex;gap:16px;flex-wrap:wrap">
    <?php if($draftMarks>0): ?><div style="padding:12px 18px;background:var(--warning-soft);border-radius:var(--radius-sm);border:1px solid var(--warning)"><strong style="color:var(--warning)"><?=$draftMarks?></strong> <span style="font-size:13px">in DRAFT — not yet submitted</span></div><?php endif; ?>
    <?php if($submittedMarks>0): ?><div style="padding:12px 18px;background:var(--blue-soft);border-radius:var(--radius-sm);border:1px solid var(--blue)"><strong style="color:var(--blue)"><?=$submittedMarks?></strong> <span style="font-size:13px">SUBMITTED — awaiting approval</span></div><?php endif; ?>
    <?php if($returnedMarks>0): ?><div style="padding:12px 18px;background:var(--error-soft);border-radius:var(--radius-sm);border:1px solid var(--error)"><strong style="color:var(--error)"><?=$returnedMarks?></strong> <span style="font-size:13px">RETURNED — needs correction</span></div><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Quick actions -->
<div class="quick-grid">
  <a href="<?=BASE_URL?>/admin/marks_entry.php"  class="quick-item"><span class="qi-icon">✏️</span><div><strong>Enter Marks</strong><small><?=$draftMarks?> draft, <?=$returnedMarks?> returned</small></div></a>
  <a href="<?=BASE_URL?>/admin/attendance.php"   class="quick-item"><span class="qi-icon">📆</span><div><strong>Take Attendance</strong><small><?=$attendanceToday?'Done today':'Not done today'?></small></div></a>
  <a href="<?=BASE_URL?>/admin/students.php"     class="quick-item"><span class="qi-icon">🎓</span><div><strong>My Students</strong><small><?=$studentCount?> students</small></div></a>
  <a href="<?=BASE_URL?>/admin/timetable.php"    class="quick-item"><span class="qi-icon">📅</span><div><strong>Timetable</strong><small>View schedule</small></div></a>
</div>

<?php require_once dirname(dirname(__DIR__)).'/includes/admin_footer.php'; ?>
