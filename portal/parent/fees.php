<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('parent');
$pdo=db(); $user=currentUser(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
$guardian=$pdo->prepare("SELECT id FROM guardians WHERE user_id=? LIMIT 1"); $guardian->execute([$user['id']]); $guardian=$guardian->fetch();
$children=[];
if($guardian){$ch=$pdo->prepare("SELECT s.*,g.name grade_name FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id JOIN student_guardians sg ON sg.student_id=s.id WHERE sg.guardian_id=? ORDER BY s.first_name");$ch->execute([$guardian['id']]);$children=$ch->fetchAll();}
if(empty($children)){$ch2=$pdo->prepare("SELECT s.*,g.name grade_name FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id WHERE s.status='Active' AND (s.phone=? OR s.email=?) ORDER BY s.first_name");$ch2->execute([$user['phone']??'',$user['email']??'']);$children=$ch2->fetchAll();}
$selChild=(int)($_GET['child_id']??($children[0]['id']??0));
$child=null; foreach($children as $ch) if($ch['id']==$selChild){$child=$ch;break;}
$payments=[]; $totalPaid=0; $totalDue=0;
if($child){
    $p=$pdo->query("SELECT p.*,f.fee_type FROM payments p LEFT JOIN fee_structures f ON f.id=p.fee_structure_id WHERE p.student_id={$child['id']} ORDER BY p.payment_date DESC"); $payments=$p->fetchAll();
    $totalPaid=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE student_id={$child['id']} AND currency='LRD' AND academic_year_id=$ayId")->fetchColumn();
    $totalDue=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM fee_structures WHERE academic_year_id=$ayId AND is_active=1 AND currency='LRD'")->fetchColumn();
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/><title>Fees — Parent Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/><link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/></head><body>
<div class="portal-grid">
<aside class="portal-sidebar"><div class="portal-brand"><div class="brand"><img src="<?=BASE_URL?>/assets/images/logo.jpg" alt="KHS"/><span><strong>KHS</strong><small>Parent Portal</small></span></div></div>
<nav class="portal-nav"><a href="<?=BASE_URL?>/portal/parent/">🏠 Dashboard</a><a href="<?=BASE_URL?>/portal/parent/child_results.php<?=$selChild?"?child_id=$selChild":''?>">📊 Results</a><a href="<?=BASE_URL?>/portal/parent/fees.php<?=$selChild?"?child_id=$selChild":''?>" class="active">💰 Fees</a><a href="<?=BASE_URL?>/portal/parent/report_card.php<?=$selChild?"?child_id=$selChild":''?>">📑 Report Card</a><a href="<?=BASE_URL?>/portal/parent/announcements.php">📢 Announcements</a></nav>
<div style="border-top:1px solid var(--line);padding:12px"><a href="<?=BASE_URL?>/admin/logout.php" style="color:var(--error);font-size:13px;font-weight:600">Sign Out</a></div></aside>
<div class="portal-content">
  <div class="page-heading"><div><h1>Fees &amp; Payments</h1><p><?=$child?e($child['first_name'].' '.$child['last_name']).' — ':''?><?=e($ay)?></p></div></div>
  <?php if(count($children)>1):?><div class="filter-row" style="margin-bottom:20px"><?php foreach($children as $ch):?><a href="?child_id=<?=$ch['id']?>" class="filter-button" style="<?=$selChild==$ch['id']?'background:var(--primary);color:#fff;border-color:var(--primary)':''?>"><?=e($ch['first_name'])?></a><?php endforeach;?></div><?php endif;?>
  <?php if($child):?>
  <div class="metric-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px">
    <div class="metric-card"><div class="metric-top"><span>Total Due</span></div><strong>LRD <?=number_format($totalDue)?></strong></div>
    <div class="metric-card"><div class="metric-top"><span>Paid</span></div><strong style="color:var(--green)">LRD <?=number_format($totalPaid)?></strong></div>
    <div class="metric-card finance-metrics"><div class="metric-top"><span>Balance</span></div><strong style="color:<?=$totalDue-$totalPaid>0?'var(--error)':'var(--green)'?>">LRD <?=number_format(abs($totalDue-$totalPaid))?></strong><small><i></i><?=$totalDue-$totalPaid>0?'Outstanding':'Paid in full'?></small></div>
  </div>
  <div class="table-wrap"><table>
    <thead><tr><th>Receipt</th><th>Fee Type</th><th>Amount</th><th>Method</th><th>Date</th><th></th></tr></thead>
    <tbody><?php if(empty($payments)):?><tr><td colspan="6" style="text-align:center;padding:28px;color:var(--ink-faint)">No payments recorded.</td></tr>
    <?php else: foreach($payments as $p):?><tr><td class="muted"><?=e($p['receipt_number'])?></td><td><?=e($p['fee_type']??'Payment')?></td><td><strong><?=e($p['currency'])?> <?=number_format($p['amount'],2)?></strong></td><td><?=e($p['payment_method'])?></td><td class="muted"><?=date('M d, Y',strtotime($p['payment_date']))?></td><td><a href="<?=BASE_URL?>/letters/receipt_pdf.php?payment_id=<?=$p['id']?>" class="filter-button button-sm" target="_blank">📄 Receipt</a></td></tr><?php endforeach; endif;?>
    </tbody>
  </table></div>
  <?php if($totalDue-$totalPaid>0):?><div class="alert alert-warning" style="margin-top:14px">⚠️ Outstanding balance: <strong>LRD <?=number_format($totalDue-$totalPaid)?></strong>. Please visit the school bursar office to make payment.</div><?php endif;?>
  <?php endif;?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script></body></html>
