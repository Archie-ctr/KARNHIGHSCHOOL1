<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('parent');
$pdo=db(); $user=currentUser(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();

// Get guardian record and linked children
$guardian=$pdo->prepare("SELECT id FROM guardians WHERE user_id=? LIMIT 1"); $guardian->execute([$user['id']]); $guardian=$guardian->fetch();
$children=[];
if($guardian){
    $ch=$pdo->prepare("SELECT s.*,g.name grade_name,c.name class_name FROM student_guardians sg JOIN students s ON s.id=sg.student_id LEFT JOIN grades g ON g.id=s.current_grade_id LEFT JOIN classes c ON c.id=s.current_class_id WHERE sg.guardian_id=? ORDER BY s.first_name");
    $ch->execute([$guardian['id']]); $children=$ch->fetchAll();
}
// Fallback: find by guardian_phone / guardian_name stored in students
if(empty($children)){
    $ch2=$pdo->prepare("SELECT s.*,g.name grade_name,c.name class_name FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id LEFT JOIN classes c ON c.id=s.current_class_id WHERE (s.phone=? OR s.email=?) AND s.status='Active' ORDER BY s.first_name");
    $ch2->execute([$user['phone']??'',$user['email']??'']); $children=$ch2->fetchAll();
}

$selChild=(int)($_GET['child_id']??($children[0]['id']??0));
$child=null; $attStats=null; $avg=null; $recentPay=[];
foreach($children as $ch) if($ch['id']==$selChild){$child=$ch;break;}
if($child){
    $at=$pdo->prepare("SELECT SUM(status='Present') p,COUNT(*) t FROM attendance WHERE student_id=? AND academic_year_id=?"); $at->execute([$child['id'],$ayId]); $at=$at->fetch(); $attStats=$at;
    $av=$pdo->prepare("SELECT ROUND(AVG(marks_obtained/max_marks*100),1) FROM assessment_scores WHERE student_id=? AND academic_year_id=? AND status IN ('submitted','approved') AND marks_obtained IS NOT NULL"); $av->execute([$child['id'],$ayId]); $avg=$av->fetchColumn();
    $rp=$pdo->prepare("SELECT p.receipt_number,p.amount,p.currency,p.payment_date,p.payment_method FROM payments p WHERE p.student_id=? ORDER BY p.payment_date DESC LIMIT 5"); $rp->execute([$child['id']]); $recentPay=$rp->fetchAll();
}
$announcements=$pdo->query("SELECT title,message,published_at FROM announcements WHERE target IN ('all','parents') AND (expires_at IS NULL OR expires_at>NOW()) ORDER BY published_at DESC LIMIT 4")->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Parent Portal — KHS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/></head><body>
<div class="portal-grid">
<aside class="portal-sidebar">
  <div class="portal-brand"><div class="brand"><img src="<?=BASE_URL?>/assets/images/logo.jpg" alt="KHS"/><span><strong>KHS</strong><small>Parent Portal</small></span></div></div>
  <nav class="portal-nav">
    <a href="<?=BASE_URL?>/portal/parent/" class="active">🏠 Dashboard</a>
    <a href="<?=BASE_URL?>/portal/parent/child_results.php<?=$selChild?"?child_id=$selChild":''?>">📊 Results</a>
    <a href="<?=BASE_URL?>/portal/parent/child_attendance.php<?=$selChild?"?child_id=$selChild":''?>">📆 Attendance</a>
    <a href="<?=BASE_URL?>/portal/parent/fees.php<?=$selChild?"?child_id=$selChild":''?>">💰 Fees</a>
    <a href="<?=BASE_URL?>/portal/parent/report_card.php<?=$selChild?"?child_id=$selChild":''?>">📑 Report Card</a>
    <a href="<?=BASE_URL?>/portal/parent/announcements.php">📢 Announcements</a>
  </nav>
  <div style="border-top:1px solid var(--line);padding:12px"><a href="<?=BASE_URL?>/admin/logout.php" style="color:var(--error);font-size:13px;font-weight:600">Sign Out</a></div>
</aside>
<div class="portal-content">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div><h1 style="font-size:24px;font-weight:800">Welcome, <?=e(explode(' ',$user['name'])[0])?>! 👋</h1><p style="color:var(--ink-soft)">Parent Portal — <?=e($ay)?></p></div>
  </div>

  <?php if(empty($children)):?>
  <div class="alert alert-warning">No children linked to your account. Please contact the school registrar to link your children.</div>
  <?php else:?>

  <!-- Child selector -->
  <?php if(count($children)>1):?>
  <div class="filter-row" style="margin-bottom:20px">
    <?php foreach($children as $ch): $ini=strtoupper(substr($ch['first_name'],0,1).substr($ch['last_name'],0,1));?>
    <a href="?child_id=<?=$ch['id']?>" class="filter-button" style="<?=$selChild==$ch['id']?'background:var(--primary);color:#fff;border-color:var(--primary)':''?>">
      <?=e($ch['first_name'].' '.$ch['last_name'])?> — <?=e($ch['grade_name']??'')?>
    </a>
    <?php endforeach;?>
  </div>
  <?php endif;?>

  <?php if($child):?>
  <!-- Child info card -->
  <div class="form-section" style="margin-bottom:20px">
    <div style="display:flex;align-items:center;gap:16px">
      <div class="avatar" style="width:56px;height:56px;font-size:20px"><?=strtoupper(substr($child['first_name'],0,1).substr($child['last_name'],0,1))?></div>
      <div>
        <h2 style="font-size:20px;font-weight:800"><?=e($child['first_name'].' '.$child['last_name'])?></h2>
        <p style="color:var(--ink-soft);font-size:14px"><?=e($child['grade_name']??'')?><?=$child['class_name']?' / '.e($child['class_name']):'';?> &nbsp;&middot;&nbsp; <?=e($child['student_id'])?></p>
      </div>
      <div style="margin-left:auto"><?=statusBadge($child['status'])?></div>
    </div>
  </div>

  <!-- Metrics -->
  <div class="metric-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px">
    <div class="metric-card"><div class="metric-top"><span>Current Average</span><div class="metric-icon">📊</div></div><strong><?=$avg?$avg.'%':'—'?></strong></div>
    <div class="metric-card"><div class="metric-top"><span>Attendance Rate</span><div class="metric-icon">📆</div></div><strong><?=($attStats&&$attStats['t']>0)?round($attStats['p']/$attStats['t']*100,1).'%':'—'?></strong><small><i></i>Days present: <?=$attStats['p']??0?></small></div>
    <div class="metric-card finance-metrics"><div class="metric-top"><span>Quick Links</span><div class="metric-icon">📋</div></div>
      <div style="display:flex;flex-direction:column;gap:6px;margin-top:6px">
        <a href="<?=BASE_URL?>/portal/parent/child_results.php?child_id=<?=$child['id']?>" class="text-link" style="font-size:13px">View results →</a>
        <a href="<?=BASE_URL?>/portal/parent/fees.php?child_id=<?=$child['id']?>" class="text-link" style="font-size:13px">View fees →</a>
      </div>
    </div>
  </div>

  <!-- Recent payments -->
  <?php if(!empty($recentPay)):?>
  <div class="panel" style="margin-bottom:20px">
    <div class="panel-heading"><div><h3>Recent Payments</h3></div><a href="<?=BASE_URL?>/portal/parent/fees.php?child_id=<?=$child['id']?>" class="filter-button">All payments →</a></div>
    <div class="table-wrap" style="border:none;border-radius:0"><table>
      <thead><tr><th>Receipt</th><th>Amount</th><th>Method</th><th>Date</th></tr></thead>
      <tbody><?php foreach($recentPay as $p):?><tr><td class="muted"><?=e($p['receipt_number'])?></td><td><strong><?=e($p['currency'])?> <?=number_format($p['amount'],2)?></strong></td><td><?=e($p['payment_method'])?></td><td class="muted"><?=date('M d, Y',strtotime($p['payment_date']))?></td></tr><?php endforeach;?></tbody>
    </table></div>
  </div>
  <?php endif;?>
  <?php endif;?>
  <?php endif;?>

  <!-- Announcements -->
  <?php if(!empty($announcements)):?>
  <div class="panel">
    <div class="panel-heading"><div><h3>School Announcements</h3></div></div>
    <?php foreach($announcements as $ann):?><div class="activity"><span class="activity-dot pink"></span><div><strong><?=e($ann['title'])?></strong><p><?=e(mb_substr($ann['message'],0,120)).(mb_strlen($ann['message'])>120?'…':'')?></p><small><?=date('M d, Y',strtotime($ann['published_at']))?></small></div></div><?php endforeach;?>
  </div>
  <?php endif;?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script></body></html>
