<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('student');
$pdo=db(); $user=currentUser(); $activePage='library';
$ayId=currentAcademicYearId(); $ay=currentAcademicYearName();

$student=$pdo->prepare("SELECT id,first_name,last_name,student_id FROM students WHERE user_id=? LIMIT 1");
$student->execute([$user['id']]); $student=$student->fetch();
if(!$student) redirect(BASE_URL.'/portal/student/');
$studentId=$student['id'];

// Current loans — try library_transactions first (portal table), fall back to library_borrowings
try{
    $current=$pdo->query(
        "SELECT lt.id,lb.title,lb.author,lb.isbn,lb.category,
                lt.borrow_date,lt.issued_at,lt.due_date,lt.status,
                lt.fine_amount,lt.fine_paid,lt.fine_waived,lt.book_condition,
                DATEDIFF(CURDATE(),lt.due_date) days_overdue
         FROM library_transactions lt
         JOIN library_books lb ON lb.id=lt.book_id
         WHERE lt.student_id=$studentId AND lt.status='Issued'
         ORDER BY lt.due_date ASC"
    )->fetchAll();
}catch(Throwable $e){
    try{
        $current=$pdo->query(
            "SELECT lt.*,b.title,b.author,b.isbn,b.category,
                    DATEDIFF(CURDATE(),lt.due_date) days_overdue
             FROM library_transactions lt
             JOIN books b ON b.id=lt.book_id
             WHERE lt.student_id=$studentId AND lt.status='Issued'
             ORDER BY lt.due_date ASC"
        )->fetchAll();
    }catch(Throwable $e2){$current=[];}
}

// Returned history
try{
    $history=$pdo->query(
        "SELECT lt.*,lb.title,lb.author,lb.isbn,
                lt.returned_date,lt.returned_at,lt.fine_amount,lt.fine_paid,lt.fine_waived
         FROM library_transactions lt
         JOIN library_books lb ON lb.id=lt.book_id
         WHERE lt.student_id=$studentId AND lt.status='Returned'
         ORDER BY COALESCE(lt.returned_date,lt.returned_at) DESC LIMIT 30"
    )->fetchAll();
}catch(Throwable $e){
    try{
        $history=$pdo->query(
            "SELECT lt.*,b.title,b.author,b.isbn
             FROM library_transactions lt
             JOIN books b ON b.id=lt.book_id
             WHERE lt.student_id=$studentId AND lt.status='Returned'
             ORDER BY lt.returned_at DESC LIMIT 30"
        )->fetchAll();
    }catch(Throwable $e2){$history=[];}
}

// Fines
$totalFines=0; $unpaidFines=0;
try{
    $fRow=$pdo->query("SELECT COALESCE(SUM(fine_amount),0) total,COALESCE(SUM(CASE WHEN (fine_paid=0 OR fine_paid IS NULL) AND (fine_waived=0 OR fine_waived IS NULL) THEN fine_amount ELSE 0 END),0) unpaid FROM library_transactions WHERE student_id=$studentId AND fine_amount>0")->fetch();
    $totalFines=(float)$fRow['total']; $unpaidFines=(float)$fRow['unpaid'];
}catch(Throwable $e){}

// Fine settings
try{$fs=$pdo->query("SELECT fine_per_day,currency_symbol FROM library_fine_settings ORDER BY id DESC LIMIT 1")->fetch();}catch(Throwable $e){$fs=null;}
$finePerDay=(float)($fs['fine_per_day']??0.50); $currency=$fs['currency_symbol']??'GHS';

$today=date('Y-m-d');
$overdueCurrent=array_filter($current,fn($b)=>($b['days_overdue']??0)>0);
$tab=$_GET['tab']??'current';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Library — Student Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">

<div class="page-heading">
  <div><h1>Library</h1><p>Your borrowed books &amp; history</p></div>
</div>

<?php if(count($overdueCurrent)>0):?>
<div class="alert alert-warning">⏰ <strong><?=count($overdueCurrent)?> book<?=count($overdueCurrent)!=1?'s':''?> overdue!</strong> Please return them as soon as possible to avoid additional fines.</div>
<?php endif;?>
<?php if($unpaidFines>0):?>
<div class="alert alert-warning">💰 You have <strong><?=$currency?> <?=number_format($unpaidFines,2)?></strong> in unpaid library fines. Please visit the library to settle.</div>
<?php endif;?>

<!-- KPIs -->
<div class="metric-grid" style="margin-bottom:16px">
  <div class="metric-card"><div class="metric-top"><span>On Loan</span><div class="metric-icon">📤</div></div><strong><?=count($current)?></strong><small><i></i>Currently borrowed</small></div>
  <div class="metric-card <?=count($overdueCurrent)>0?'finance-metrics':''?>"><div class="metric-top"><span>Overdue</span><div class="metric-icon">⏰</div></div><strong style="color:<?=count($overdueCurrent)>0?'var(--error)':'var(--green)'?>"><?=count($overdueCurrent)?></strong></div>
  <div class="metric-card"><div class="metric-top"><span>Books Returned</span><div class="metric-icon">📥</div></div><strong><?=count($history)?></strong><small><i></i>All time</small></div>
  <div class="metric-card <?=$unpaidFines>0?'finance-metrics':''?>"><div class="metric-top"><span>Unpaid Fines</span><div class="metric-icon">💰</div></div><strong style="color:<?=$unpaidFines>0?'var(--error)':'var(--green)'?>"><?=$currency?> <?=number_format($unpaidFines,2)?></strong></div>
</div>

<div class="tab-bar" style="margin-bottom:16px">
  <a href="?tab=current" class="tab-btn <?=$tab==='current'?'active':''?>">📤 Currently Borrowed (<?=count($current)?>)</a>
  <a href="?tab=history" class="tab-btn <?=$tab==='history'?'active':''?>">📋 Return History (<?=count($history)?>)</a>
  <a href="?tab=fines"   class="tab-btn <?=$tab==='fines'  ?'active':''?>">💰 Fines</a>
</div>

<?php if($tab==='current'): ?>
<?php if(empty($current)):?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:40px;margin-bottom:12px">📚</div>
  <h3 style="margin-bottom:6px">No books currently borrowed</h3>
  <p style="color:var(--ink-soft)">Visit the school library to borrow books.</p>
</div>
<?php else:?>
<div style="display:flex;flex-direction:column;gap:12px">
  <?php foreach($current as $b):
    $ov=($b['days_overdue']??0)>0;
    $accrued=$ov?min(round($b['days_overdue']*$finePerDay,2),50):0;
    $existFine=(float)($b['fine_amount']??0);
    $daysLeft=$ov?0:max(0,(int)ceil((strtotime($b['due_date'])-time())/86400));
  ?>
  <div class="panel" style="padding:18px;border-left:4px solid <?=$ov?'var(--error)':($daysLeft<=3?'var(--warning)':'var(--green)')?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px">
      <div>
        <h3 style="font-weight:700;font-size:14px;margin-bottom:3px"><?=e($b['title'])?></h3>
        <div style="font-size:12.5px;color:var(--ink-soft)"><?=e($b['author']??'Unknown')?><?=$b['isbn']?' &middot; ISBN: '.e($b['isbn']):''?></div>
        <?php if($b['category']):?><div style="font-size:12px;color:var(--ink-faint);margin-top:2px"><?=e($b['category'])?></div><?php endif;?>
      </div>
      <div style="text-align:right">
        <?php if($ov):?>
        <div style="color:var(--error);font-weight:800;font-size:14px">⏰ <?=$b['days_overdue']?> day<?=$b['days_overdue']!=1?'s':''?> overdue</div>
        <div style="font-size:12px;color:var(--error)">Accrued fine: <?=$currency?> <?=number_format($accrued,2)?></div>
        <?php elseif($daysLeft<=3):?>
        <div style="color:var(--warning);font-weight:700;font-size:13px">Due in <?=$daysLeft?> day<?=$daysLeft!=1?'s':''?></div>
        <?php else:?>
        <div style="color:var(--green);font-weight:600;font-size:13px"><?=$daysLeft?> days left</div>
        <?php endif;?>
        <div style="font-size:12px;color:var(--ink-soft);margin-top:2px">Due: <strong><?=date('d M Y',strtotime($b['due_date']))?></strong></div>
        <div style="font-size:11.5px;color:var(--ink-faint)">Borrowed: <?=date('d M Y',strtotime($b['borrow_date']??$b['issued_at']))?></div>
      </div>
    </div>
  </div>
  <?php endforeach;?>
</div>
<?php endif;?>

<?php elseif($tab==='history'): ?>
<?php if(empty($history)):?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:40px;margin-bottom:12px">📋</div>
  <h3>No return history yet</h3>
</div>
<?php else:?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Book</th><th>Author</th><th>Borrowed</th><th>Returned</th><th>Fine</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach($history as $b):
        $ret=$b['returned_date']??$b['returned_at']??null;
        $fineAmt=(float)($b['fine_amount']??0);
      ?>
      <tr>
        <td><strong><?=e($b['title'])?></strong><?=$b['isbn']?'<div style="font-size:11px;color:var(--ink-faint)">'.e($b['isbn']).'</div>':''?></td>
        <td class="muted"><?=e($b['author']??'—')?></td>
        <td class="muted"><?=date('d M Y',strtotime($b['borrow_date']??$b['issued_at']??'now'))?></td>
        <td class="muted"><?=$ret?date('d M Y',strtotime($ret)):'—'?></td>
        <td><?php if($fineAmt>0):?><span style="color:<?=($b['fine_paid']??0)||($$b['fine_waived']??0)?'var(--green)':'var(--error)'?>;font-weight:700"><?=$currency?> <?=number_format($fineAmt,2)?><?=(($b['fine_paid']??0)?' ✓ Paid':(($b['fine_waived']??0)?' (Waived)':' Unpaid'))?></span><?php else:?><span class="muted">—</span><?php endif;?></td>
        <td><span class="status approved" style="font-size:10px">Returned</span></td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>
<?php endif;?>

<?php else: // fines tab ?>
<?php
try{
    $fineRows=$pdo->query(
        "SELECT lt.*,lb.title,lb.author FROM library_transactions lt
         JOIN library_books lb ON lb.id=lt.book_id
         WHERE lt.student_id=$studentId AND lt.fine_amount>0
         ORDER BY lt.id DESC LIMIT 30"
    )->fetchAll();
}catch(Throwable $e){$fineRows=[];}
?>
<div class="panel" style="padding:20px;margin-bottom:16px">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <div>
      <div style="font-size:13px;color:var(--ink-soft);margin-bottom:4px">Total Fines</div>
      <div style="font-size:24px;font-weight:800"><?=$currency?> <?=number_format($totalFines,2)?></div>
    </div>
    <div>
      <div style="font-size:13px;color:var(--ink-soft);margin-bottom:4px">Unpaid</div>
      <div style="font-size:24px;font-weight:800;color:<?=$unpaidFines>0?'var(--error)':'var(--green)'?>"><?=$currency?> <?=number_format($unpaidFines,2)?></div>
    </div>
    <?php if($unpaidFines>0):?>
    <div class="alert alert-info" style="flex:1;margin:0">💡 To pay outstanding fines, visit the school library during library hours. Bring your student ID.</div>
    <?php endif;?>
  </div>
</div>
<?php if(empty($fineRows)):?>
<p style="color:var(--ink-faint);padding:20px 0;text-align:center">No fines on record — great work!</p>
<?php else:?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Book</th><th>Due Date</th><th>Days Late</th><th>Fine</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach($fineRows as $r):
        $late=$r['due_date']&&$r['borrow_date']?max(0,(int)ceil((strtotime($r['due_date'])-strtotime($r['returned_date']??$r['returned_at']??'now'))/86400*-1)):0;
        $paid=($r['fine_paid']??0);$waived=($r['fine_waived']??0);
      ?>
      <tr>
        <td><strong><?=e($r['title'])?></strong></td>
        <td class="muted"><?=date('d M Y',strtotime($r['due_date']))?></td>
        <td><?=$late>0?"<span style='color:var(--error);font-weight:700'>$late day".($late!=1?'s':'').'</span>':'—'?></td>
        <td style="font-weight:700"><?=$currency?> <?=number_format($r['fine_amount'],2)?></td>
        <td><span style="font-size:11px;font-weight:700;color:<?=$paid?'var(--green)':($waived?'var(--blue)':'var(--error)')?>"><?=$paid?'✓ Paid':($waived?'Waived':'Unpaid')?></span></td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>
<?php endif;?>
<?php endif;?>

</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
