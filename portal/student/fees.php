<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('student');
$pdo=db(); $user=currentUser(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
$student=$pdo->prepare("SELECT id,first_name,last_name,student_id FROM students WHERE user_id=? LIMIT 1"); $student->execute([$user['id']]); $student=$student->fetch();
if(!$student){redirect(BASE_URL.'/portal/student/');}

$payments=$pdo->prepare("SELECT p.*,f.fee_type FROM payments p LEFT JOIN fee_structures f ON f.id=p.fee_structure_id WHERE p.student_id=? ORDER BY p.payment_date DESC"); $payments->execute([$student['id']]); $payments=$payments->fetchAll();
$totalPaid=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE student_id=? AND currency='LRD' AND academic_year_id=?"); $totalPaid->execute([$student['id'],$ayId]); $totalPaid=(float)$totalPaid->fetchColumn();
$feeStructure=$pdo->prepare("SELECT SUM(amount) FROM fee_structures WHERE academic_year_id=? AND (grade_id IS NULL OR grade_id=(SELECT current_grade_id FROM students WHERE id=?)) AND is_active=1 AND currency='LRD'"); $feeStructure->execute([$ayId,$student['id']]); $totalDue=(float)$feeStructure->fetchColumn();
$balance=$totalDue-$totalPaid;
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Fees — Student Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/></head><body>
<div class="portal-grid">
<aside class="portal-sidebar">
  <div class="portal-brand"><div class="brand"><img src="<?=BASE_URL?>/assets/images/logo.jpg" alt="KHS"/><span><strong>KHS</strong><small>Student Portal</small></span></div></div>
  <nav class="portal-nav">
    <a href="<?=BASE_URL?>/portal/student/">🏠 Dashboard</a>
    <a href="<?=BASE_URL?>/portal/student/my_results.php">📊 My Results</a>
    <a href="<?=BASE_URL?>/portal/student/attendance.php">📆 Attendance</a>
    <a href="<?=BASE_URL?>/portal/student/report_card.php">📑 Report Card</a>
    <a href="<?=BASE_URL?>/portal/student/fees.php" class="active">💰 Fees</a>
    <a href="<?=BASE_URL?>/portal/student/timetable.php">📅 Timetable</a>
  </nav>
  <div style="border-top:1px solid var(--line);padding:12px"><a href="<?=BASE_URL?>/admin/logout.php" style="color:var(--error);font-size:13px;font-weight:600">Sign Out</a></div>
</aside>
<div class="portal-content">
  <div class="page-heading"><div><h1>Fees &amp; Payments</h1><p><?=e($ay)?></p></div></div>

  <div class="metric-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
    <div class="metric-card"><div class="metric-top"><span>Total Due (LRD)</span><div class="metric-icon">📋</div></div><strong>LRD <?=number_format($totalDue)?></strong></div>
    <div class="metric-card"><div class="metric-top"><span>Paid (LRD)</span><div class="metric-icon">✅</div></div><strong style="color:var(--green)">LRD <?=number_format($totalPaid)?></strong></div>
    <div class="metric-card finance-metrics"><div class="metric-top"><span>Balance (LRD)</span><div class="metric-icon">⏳</div></div><strong style="color:<?=$balance>0?'var(--error)':'var(--green)'?>">LRD <?=number_format(abs($balance))?></strong><small><i></i><?=$balance>0?'Outstanding':'Fully paid'?></small></div>
  </div>

  <?php if($totalDue>0):?>
  <div class="panel" style="padding:20px 22px;margin-bottom:20px">
    <div style="display:flex;justify-content:space-between;margin-bottom:10px"><strong>Payment Progress</strong><span class="status <?=$balance<=0?'approved':'warning'?>"><?=$totalDue>0?round(min(100,$totalPaid/$totalDue*100)).'%':'0%'?> paid</span></div>
    <div class="progress-bar"><div class="progress-fill green" style="width:<?=$totalDue>0?min(100,round($totalPaid/$totalDue*100)):0?>%"></div></div>
  </div>
  <?php endif;?>

  <div class="table-wrap">
    <table>
      <thead><tr><th>Receipt</th><th>Fee Type</th><th>Amount</th><th>Method</th><th>Date</th><th></th></tr></thead>
      <tbody>
        <?php if(empty($payments)):?>
        <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--ink-faint)">No payment records found.</td></tr>
        <?php else:?>
        <?php foreach($payments as $p):?>
        <tr>
          <td class="muted"><?=e($p['receipt_number'])?></td>
          <td><?=e($p['fee_type']??'Payment')?></td>
          <td><strong><?=e($p['currency'])?> <?=number_format($p['amount'],2)?></strong></td>
          <td><?=e($p['payment_method'])?></td>
          <td class="muted"><?=date('M d, Y',strtotime($p['payment_date']))?></td>
          <td><a href="<?=BASE_URL?>/letters/receipt_pdf.php?payment_id=<?=$p['id']?>" class="filter-button button-sm" target="_blank">📄 Receipt</a></td>
        </tr>
        <?php endforeach;?>
        <?php endif;?>
      </tbody>
    </table>
  </div>
  <?php if($balance>0):?>
  <div class="alert alert-warning" style="margin-top:16px">⚠️ You have an outstanding balance of <strong>LRD <?=number_format($balance)?></strong>. Please visit the school's bursar office to make payment.</div>
  <?php endif;?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script></body></html>
