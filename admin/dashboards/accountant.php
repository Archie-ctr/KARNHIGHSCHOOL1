<?php
$pageTitle='Finance Dashboard'; $activeAdmin='dashboard';
require_once dirname(dirname(__DIR__)).'/includes/admin_header.php';
$pdo=db(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
$fn=explode(' ',currentUser()['name']??'Accountant')[0];
$hour=(int)date('G'); $greet=$hour<12?'Good morning':($hour<17?'Good afternoon':'Good evening');

$totalLRD=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='LRD' AND academic_year_id=$ayId")->fetchColumn();
$totalUSD=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='USD' AND academic_year_id=$ayId")->fetchColumn();
$todayLRD=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='LRD' AND DATE(payment_date)=CURDATE()")->fetchColumn();
$payCount=(int)$pdo->query("SELECT COUNT(*) FROM payments WHERE academic_year_id=$ayId")->fetchColumn();
$paidStudents=(int)$pdo->query("SELECT COUNT(DISTINCT student_id) FROM payments WHERE academic_year_id=$ayId")->fetchColumn();
$totalStudents=(int)$pdo->query("SELECT COUNT(*) FROM students WHERE status='Active'")->fetchColumn();
$unpaid=max(0,$totalStudents-$paidStudents);
$recentPay=$pdo->query("SELECT p.*,CONCAT(s.first_name,' ',s.last_name) sname,s.student_id sid FROM payments p JOIN students s ON s.id=p.student_id ORDER BY p.created_at DESC LIMIT 8")->fetchAll();
$targetLRD=(float)(setting('annual_fee_target','2920000'));
$pct=$targetLRD>0?min(100,round($totalLRD/$targetLRD*100)):0;
?>
<div class="page-heading">
  <div>
    <div class="eyebrow"><?=date('l, F d, Y')?> <span></span></div>
    <h1><?=$greet?>, <?=e($fn)?>.</h1>
    <p>Finance Dashboard — <?=e($ay)?></p>
  </div>
  <a href="<?=BASE_URL?>/admin/finance.php" class="button button-primary">+ Record Payment</a>
</div>

<div class="metric-grid finance-metrics" style="margin-bottom:20px">
  <div class="metric-card"><div class="metric-top"><span>Collected (LRD)</span><div class="metric-icon">💵</div></div><strong>LRD <?=number_format($totalLRD)?></strong><small><i></i><?=e($ay)?></small></div>
  <div class="metric-card"><div class="metric-top"><span>Collected (USD)</span><div class="metric-icon">💲</div></div><strong>USD <?=number_format($totalUSD,2)?></strong><small><i></i><?=e($ay)?></small></div>
  <div class="metric-card"><div class="metric-top"><span>Today's Collection</span><div class="metric-icon">📅</div></div><strong>LRD <?=number_format($todayLRD)?></strong><small><i></i><?=$payCount?> total receipts</small></div>
  <div class="metric-card"><div class="metric-top"><span>Yet to Pay</span><div class="metric-icon">⏳</div></div><strong><?=number_format($unpaid)?></strong><small><i></i>of <?=$totalStudents?> students</small></div>
</div>

<div class="panel" style="padding:20px 22px;margin-bottom:20px">
  <div style="display:flex;justify-content:space-between;margin-bottom:10px"><strong>Annual Collection Target: LRD <?=number_format($targetLRD)?></strong><span class="status approved"><?=$pct?>% collected</span></div>
  <div class="progress-bar"><div class="progress-fill green" style="width:<?=$pct?>%"></div></div>
</div>

<div class="quick-grid" style="margin-bottom:20px">
  <a href="<?=BASE_URL?>/admin/finance.php"           class="quick-item"><span class="qi-icon">💳</span><div><strong>Payments</strong><small>Record payment</small></div></a>
  <a href="<?=BASE_URL?>/admin/fee_structures.php"    class="quick-item"><span class="qi-icon">📋</span><div><strong>Fee Structures</strong><small>Manage fees</small></div></a>
  <a href="<?=BASE_URL?>/admin/student_statements.php"class="quick-item"><span class="qi-icon">📊</span><div><strong>Statements</strong><small>Student balances</small></div></a>
  <a href="<?=BASE_URL?>/admin/reports.php"           class="quick-item"><span class="qi-icon">📈</span><div><strong>Reports</strong><small>Export finance</small></div></a>
</div>

<div class="panel">
  <div class="panel-heading"><div><h3>Recent Payments</h3></div><a href="<?=BASE_URL?>/admin/finance.php" class="filter-button">All →</a></div>
  <div class="table-wrap" style="border:none;border-radius:0">
    <table><thead><tr><th>Receipt</th><th>Student</th><th>Amount</th><th>Method</th><th>Date</th></tr></thead>
    <tbody><?php if(empty($recentPay)):?><tr><td colspan="5" style="text-align:center;padding:24px;color:var(--ink-faint)">No payments yet.</td></tr>
    <?php else: foreach($recentPay as $p):?>
    <tr><td class="muted"><?=e($p['receipt_number'])?></td><td><strong><?=e($p['sname'])?></strong><div style="font-size:11px;color:var(--ink-faint)"><?=e($p['sid'])?></div></td><td><strong><?=e($p['currency'])?> <?=number_format($p['amount'],2)?></strong></td><td><?=e($p['payment_method'])?></td><td class="muted"><?=date('M d, Y',strtotime($p['payment_date']))?></td></tr>
    <?php endforeach; endif;?>
    </tbody></table>
  </div>
</div>
<?php require_once dirname(dirname(__DIR__)).'/includes/admin_footer.php'; ?>
