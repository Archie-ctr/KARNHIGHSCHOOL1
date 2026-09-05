<?php
$pageTitle='Discipline Dashboard'; $activeAdmin='dashboard';
require_once dirname(dirname(__DIR__)).'/includes/admin_header.php';
$pdo=db(); $fn=explode(' ',currentUser()['name']??'Officer')[0]; $hour=(int)date('G');
$greet=$hour<12?'Good morning':($hour<17?'Good afternoon':'Good evening');
$openCases=(int)$pdo->query("SELECT COUNT(*) FROM discipline_records WHERE resolved=0")->fetchColumn();
$thisMonth=(int)$pdo->query("SELECT COUNT(*) FROM discipline_records WHERE MONTH(incident_date)=MONTH(CURDATE()) AND YEAR(incident_date)=YEAR(CURDATE())")->fetchColumn();
$suspensions=(int)$pdo->query("SELECT COUNT(*) FROM discipline_records WHERE action_taken='Suspension' AND resolved=0")->fetchColumn();
$recent=$pdo->query("SELECT d.*,CONCAT(s.first_name,' ',s.last_name) sname FROM discipline_records d JOIN students s ON s.id=d.student_id WHERE d.resolved=0 ORDER BY d.incident_date DESC LIMIT 10")->fetchAll();
?>
<div class="page-heading">
  <div><div class="eyebrow"><?=date('l, F d, Y')?> <span></span></div><h1><?=$greet?>, <?=e($fn)?>.</h1><p>Discipline Officer</p></div>
  <a href="<?=BASE_URL?>/admin/discipline.php" class="button button-primary">+ New Incident</a>
</div>
<div class="metric-grid" style="margin-bottom:24px">
  <div class="metric-card <?=$openCases>0?'finance-metrics':''?>"><div class="metric-top"><span>Open Cases</span><div class="metric-icon">⚖️</div></div><strong><?=$openCases?></strong><small><i></i>Awaiting resolution</small></div>
  <div class="metric-card"><div class="metric-top"><span>This Month</span><div class="metric-icon">📅</div></div><strong><?=$thisMonth?></strong><small><i></i>New incidents</small></div>
  <div class="metric-card <?=$suspensions>0?'finance-metrics':''?>"><div class="metric-top"><span>Active Suspensions</span><div class="metric-icon">🚫</div></div><strong><?=$suspensions?></strong></div>
  <div class="metric-card"><div class="metric-top"><span>Quick Links</span><div class="metric-icon">🔗</div></div>
    <a href="<?=BASE_URL?>/admin/discipline.php" class="text-link" style="font-size:12px">All cases →</a>
    <a href="<?=BASE_URL?>/admin/students.php"   class="text-link" style="font-size:12px;display:block">Students →</a>
  </div>
</div>
<div class="panel">
  <div class="panel-heading"><div><h3>Open Cases</h3></div><a href="<?=BASE_URL?>/admin/discipline.php" class="filter-button">All →</a></div>
  <div class="table-wrap" style="border:none;border-radius:0">
    <table><thead><tr><th>Student</th><th>Date</th><th>Category</th><th>Action</th></tr></thead>
    <tbody><?php if(empty($recent)):?><tr><td colspan="4" style="text-align:center;padding:24px;color:var(--ink-faint)">No open cases.</td></tr>
    <?php else: foreach($recent as $r):?>
    <tr><td><strong><?=e($r['sname'])?></strong></td><td class="muted"><?=date('M d',strtotime($r['incident_date']))?></td><td><span class="status pending"><?=e($r['category'])?></span></td><td><span class="status <?=$r['action_taken']==='Suspension'?'warning':'new-s'?>"><?=e($r['action_taken'])?></span></td></tr>
    <?php endforeach; endif;?>
    </tbody></table>
  </div>
</div>
<?php require_once dirname(dirname(__DIR__)).'/includes/admin_footer.php'; ?>
