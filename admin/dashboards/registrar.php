<?php
$pageTitle='Registrar Dashboard'; $activeAdmin='dashboard';
require_once dirname(dirname(__DIR__)).'/includes/admin_header.php';
$pdo=db(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
$fn=explode(' ',currentUser()['name']??'Registrar')[0];
$hour=(int)date('G'); $greet=$hour<12?'Good morning':($hour<17?'Good afternoon':'Good evening');

$newApps     =(int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status='Application Submitted'")->fetchColumn();
$underReview =(int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status='Under Review'")->fetchColumn();
$pendingDocs =(int)$pdo->query("SELECT COUNT(*) FROM applications WHERE document_status='Pending'")->fetchColumn();
$totalStudents=(int)$pdo->query("SELECT COUNT(*) FROM students WHERE status='Active'")->fetchColumn();
$newStudents =(int)$pdo->query("SELECT COUNT(*) FROM students WHERE status='Active' AND YEAR(admission_date)=YEAR(CURDATE())")->fetchColumn();
$recentApps  =$pdo->query("SELECT application_number,first_name,last_name,grade_applying_for,status,created_at FROM applications ORDER BY created_at DESC LIMIT 8")->fetchAll();
?>
<div class="page-heading">
  <div>
    <div class="eyebrow"><?=date('l, F d, Y')?> <span></span></div>
    <h1><?=$greet?>, <?=e($fn)?>.</h1>
    <p>Registrar Dashboard — <?=e($ay)?></p>
  </div>
  <a href="<?=BASE_URL?>/admin/applications.php" class="button button-primary">📋 View Applications</a>
</div>

<?php if($newApps>0): ?>
<div class="alert alert-info">📋 <strong><?=$newApps?> new application<?=$newApps!=1?'s':''?></strong> awaiting review.</div>
<?php endif; ?>

<div class="metric-grid" style="margin-bottom:24px">
  <div class="metric-card"><div class="metric-top"><span>New Applications</span><div class="metric-icon">📋</div></div><strong><?=$newApps?></strong><small><i></i>Awaiting review</small></div>
  <div class="metric-card"><div class="metric-top"><span>Under Review</span><div class="metric-icon">🔍</div></div><strong><?=$underReview?></strong><small><i></i>In progress</small></div>
  <div class="metric-card finance-metrics"><div class="metric-top"><span>Pending Documents</span><div class="metric-icon">📄</div></div><strong><?=$pendingDocs?></strong><small><i></i>Documents needed</small></div>
  <div class="metric-card"><div class="metric-top"><span>Active Students</span><div class="metric-icon">🎓</div></div><strong><?=number_format($totalStudents)?></strong><small><i></i><?=$newStudents?> enrolled this year</small></div>
</div>

<div class="quick-grid" style="margin-bottom:20px">
  <a href="<?=BASE_URL?>/admin/applications.php"    class="quick-item"><span class="qi-icon">📋</span><div><strong>Applications</strong><small>Review submissions</small></div></a>
  <a href="<?=BASE_URL?>/admin/students.php"        class="quick-item"><span class="qi-icon">🎓</span><div><strong>Students</strong><small>Manage records</small></div></a>
  <a href="<?=BASE_URL?>/admin/documents.php"       class="quick-item"><span class="qi-icon">📄</span><div><strong>Documents</strong><small>Verify uploads</small></div></a>
  <a href="<?=BASE_URL?>/admin/admission_decisions.php" class="quick-item"><span class="qi-icon">✔</span><div><strong>Decisions</strong><small>Process admissions</small></div></a>
</div>

<div class="panel">
  <div class="panel-heading"><div><h3>Recent Applications</h3></div><a href="<?=BASE_URL?>/admin/applications.php" class="filter-button">All →</a></div>
  <div class="table-wrap" style="border:none;border-radius:0">
    <table><thead><tr><th>Applicant</th><th>App #</th><th>Grade</th><th>Status</th><th>Date</th></tr></thead>
    <tbody>
      <?php if(empty($recentApps)): ?><tr><td colspan="5" style="text-align:center;padding:24px;color:var(--ink-faint)">No applications yet.</td></tr>
      <?php else: foreach($recentApps as $a): $ini=strtoupper(substr($a['first_name'],0,1).substr($a['last_name'],0,1)); ?>
      <tr><td><div class="person"><div class="avatar-sm"><?=e($ini)?></div><strong><?=e($a['first_name'].' '.$a['last_name'])?></strong></div></td><td class="muted" style="font-size:12px"><?=e($a['application_number'])?></td><td><?=e($a['grade_applying_for'])?></td><td><?=statusBadge($a['status'])?></td><td class="muted"><?=date('M d, Y',strtotime($a['created_at']))?></td></tr>
      <?php endforeach; endif; ?>
    </tbody></table>
  </div>
</div>
<?php require_once dirname(dirname(__DIR__)).'/includes/admin_footer.php'; ?>
