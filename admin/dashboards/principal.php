<?php
$pageTitle='Principal Dashboard'; $activeAdmin='dashboard';
require_once dirname(dirname(__DIR__)).'/includes/admin_header.php';
$pdo=db(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
$approvals=countPendingApprovals(); $total=$approvals['_total']??0;
$today=date('l, F d, Y'); $fn=explode(' ',currentUser()['name']??'Principal')[0];
$hour=(int)date('G'); $greet=$hour<12?'Good morning':($hour<17?'Good afternoon':'Good evening');

// Key metrics
$totalStudents=(int)$pdo->query("SELECT COUNT(*) FROM students WHERE status='Active'")->fetchColumn();
$totalTeachers=(int)$pdo->query("SELECT COUNT(*) FROM teachers WHERE status='Active'")->fetchColumn();
$pendingApps  =(int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status IN ('Application Submitted','Under Review')")->fetchColumn();
$pendingMarks =(int)$pdo->query("SELECT COUNT(DISTINCT class_id,subject_id,assessment_config_id) FROM assessment_scores WHERE status IN ('submitted','resubmitted') AND academic_year_id=$ayId")->fetchColumn();
$feesLRD      =(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='LRD' AND academic_year_id=$ayId")->fetchColumn();
$openCases    =(int)$pdo->query("SELECT COUNT(*) FROM discipline_records WHERE resolved=0")->fetchColumn();
$publishedRC  =(int)$pdo->query("SELECT COUNT(*) FROM report_cards WHERE status='published' AND academic_year_id=$ayId")->fetchColumn();
$attendanceToday=$pdo->query("SELECT SUM(status='Present') p, COUNT(*) t FROM attendance WHERE date=CURDATE()")->fetch();
$attRate=$attendanceToday&&$attendanceToday['t']>0?round($attendanceToday['p']/$attendanceToday['t']*100,1):null;
?>

<div class="page-heading">
  <div>
    <div class="eyebrow"><?=e($today)?> <span></span></div>
    <h1><?=$greet?>, <?=e($fn)?>.</h1>
    <p>Principal Dashboard — <?=e($ay)?></p>
  </div>
  <?php if($total>0): ?>
  <a href="<?=BASE_URL?>/admin/approval_center.php" class="button button-primary" style="position:relative">
    ✅ Approval Center
    <span style="position:absolute;top:-8px;right:-8px;background:var(--error);color:#fff;font-size:11px;font-weight:800;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center"><?=$total?></span>
  </a>
  <?php endif; ?>
</div>

<!-- APPROVAL CENTER SUMMARY (most important for principal) -->
<?php if($total>0): ?>
<div style="background:linear-gradient(135deg,var(--primary-deep),#5a1228);color:#fff;border-radius:var(--radius-lg);padding:22px 26px;margin-bottom:24px">
  <div style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;opacity:.75;margin-bottom:12px">🔴 Requires Your Attention</div>
  <div style="display:flex;gap:20px;flex-wrap:wrap">
    <?php
    $modules=['marks'=>'✏️ Marks','admissions'=>'📋 Admissions','attendance'=>'📆 Attendance','discipline'=>'⚖️ Discipline','finance'=>'💰 Finance','promotion'=>'⬆️ Promotion'];
    foreach($modules as $mod=>$label):
      $cnt=$approvals[$mod]??0;
      if(!$cnt) continue;
    ?>
    <a href="<?=BASE_URL?>/admin/approval_center.php?module=<?=$mod?>" style="background:rgba(255,255,255,.12);border-radius:var(--radius-sm);padding:12px 18px;text-decoration:none;color:#fff;display:flex;align-items:center;gap:10px;border:1px solid rgba(255,255,255,.2);transition:background .2s" onmouseover="this.style.background='rgba(255,255,255,.2)'" onmouseout="this.style.background='rgba(255,255,255,.12)'">
      <span style="font-size:20px"><?=explode(' ',$label)[0]?></span>
      <div><div style="font-weight:700;font-size:13px"><?=explode(' ',$label)[1]?></div><div style="font-size:20px;font-weight:800"><?=$cnt?></div></div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- KEY METRICS -->
<div class="metric-grid" style="margin-bottom:24px">
  <div class="metric-card"><div class="metric-top"><span>Active Students</span><div class="metric-icon">🎓</div></div><strong><?=number_format($totalStudents)?></strong><small><i></i><?=e($ay)?></small></div>
  <div class="metric-card"><div class="metric-top"><span>Active Teachers</span><div class="metric-icon">👩‍🏫</div></div><strong><?=number_format($totalTeachers)?></strong><small><i></i>Teaching staff</small></div>
  <div class="metric-card <?=$attRate!==null&&$attRate<80?'finance-metrics':''?>"><div class="metric-top"><span>Today's Attendance</span><div class="metric-icon">📆</div></div><strong><?=$attRate!==null?$attRate.'%':'—'?></strong><small><i></i><?=$attendanceToday?$attendanceToday['p'].' present of '.$attendanceToday['t']:'No data yet'?></small></div>
  <div class="metric-card finance-metrics"><div class="metric-top"><span>Fees Collected (LRD)</span><div class="metric-icon">💰</div></div><strong>LRD <?=number_format($feesLRD/1000,1)?>K</strong><small><i></i><?=e($ay)?></small></div>
</div>

<div class="dash-columns">
  <!-- Quick action cards -->
  <div>
    <div class="panel">
      <div class="panel-heading"><div><h3>Quick Actions</h3><p>Common tasks</p></div></div>
      <div class="quick-grid">
        <a href="<?=BASE_URL?>/admin/applications.php"   class="quick-item"><span class="qi-icon">📋</span><div><strong>Applications</strong><small><?=$pendingApps?> pending</small></div></a>
        <a href="<?=BASE_URL?>/admin/marks_approval.php" class="quick-item"><span class="qi-icon">✏️</span><div><strong>Marks Approval</strong><small><?=$pendingMarks?> batch<?=$pendingMarks!=1?'es':''?></small></div></a>
        <a href="<?=BASE_URL?>/admin/report_cards.php"   class="quick-item"><span class="qi-icon">📑</span><div><strong>Report Cards</strong><small><?=$publishedRC?> published</small></div></a>
        <a href="<?=BASE_URL?>/admin/discipline.php"     class="quick-item"><span class="qi-icon">⚖️</span><div><strong>Discipline</strong><small><?=$openCases?> open case<?=$openCases!=1?'s':''?></small></div></a>
        <a href="<?=BASE_URL?>/admin/students.php"       class="quick-item"><span class="qi-icon">🎓</span><div><strong>Students</strong><small>Manage records</small></div></a>
        <a href="<?=BASE_URL?>/admin/finance.php"        class="quick-item"><span class="qi-icon">💰</span><div><strong>Finance</strong><small>Payments</small></div></a>
        <a href="<?=BASE_URL?>/admin/teachers_admin.php" class="quick-item"><span class="qi-icon">👩‍🏫</span><div><strong>Teachers</strong><small>Staff management</small></div></a>
        <a href="<?=BASE_URL?>/admin/settings.php"       class="quick-item"><span class="qi-icon">⚙️</span><div><strong>Settings</strong><small>School config</small></div></a>
      </div>
    </div>
  </div>

  <!-- Recent activity -->
  <section class="panel activity-panel">
    <div class="panel-heading"><div><h3>Recent Applications</h3><p>Latest submissions</p></div><a href="<?=BASE_URL?>/admin/applications.php" class="filter-button">All →</a></div>
    <?php $recentApps=$pdo->query("SELECT application_number,first_name,last_name,grade_applying_for,status,created_at FROM applications ORDER BY created_at DESC LIMIT 5")->fetchAll(); ?>
    <?php if(empty($recentApps)): ?><div style="padding:20px;color:var(--ink-faint);text-align:center">No applications yet.</div>
    <?php else: foreach($recentApps as $a): ?>
    <div class="activity">
      <span class="activity-dot pink"></span>
      <div>
        <strong><?=e($a['first_name'].' '.$a['last_name'])?></strong>
        <p><?=e($a['application_number'])?> — <?=e($a['grade_applying_for'])?></p>
        <small><?=date('M d, Y',strtotime($a['created_at']))?> · <?=statusBadge($a['status'])?></small>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </section>
</div>

<?php require_once dirname(dirname(__DIR__)).'/includes/admin_footer.php'; ?>
