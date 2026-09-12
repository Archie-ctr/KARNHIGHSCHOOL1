<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['librarian','principal','vice_principal','sys_admin','school_admin']);
$activePage='dashboard'; $pdo=db(); $user=currentUser(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
$librarian=null; try{$s=$pdo->prepare("SELECT * FROM staff WHERE user_id=? LIMIT 1");$s->execute([$user['id']]);$librarian=$s->fetch()?:null;}catch(Throwable $e){}
if(!$librarian) $librarian=['first_name'=>$user['username']??'Librarian','last_name'=>'','id'=>0];

// Fine settings
try{$fs=$pdo->query("SELECT * FROM library_fine_settings WHERE academic_year_id=$ayId OR academic_year_id IS NULL ORDER BY academic_year_id DESC LIMIT 1")->fetch();}catch(Throwable $e){$fs=null;}
$finePerDay=(float)($fs['fine_per_day']??0.50); $currency=$fs['currency_symbol']??'GHS';

// KPIs
$kpis=[];
foreach([
  'total_books'    =>"SELECT COUNT(*) FROM library_books WHERE is_active=1",
  'total_copies'   =>"SELECT COALESCE(SUM(total_copies),0) FROM library_books WHERE is_active=1",
  'borrowed'       =>"SELECT COUNT(*) FROM library_transactions WHERE status='Issued'",
  'overdue'        =>"SELECT COUNT(*) FROM library_transactions WHERE status='Issued' AND due_date<CURDATE()",
  'today_issued'   =>"SELECT COUNT(*) FROM library_transactions WHERE DATE(COALESCE(borrow_date,issued_at))=CURDATE()",
  'today_returned' =>"SELECT COUNT(*) FROM library_transactions WHERE status='Returned' AND DATE(COALESCE(returned_date,returned_at))=CURDATE()",
  'unpaid_fines'   =>"SELECT COALESCE(SUM(fine_amount),0) FROM library_transactions WHERE fine_amount>0 AND (fine_paid=0 OR fine_paid IS NULL) AND (fine_waived=0 OR fine_waived IS NULL)",
  'reservations'   =>"SELECT COUNT(*) FROM library_reservations WHERE status='pending'",
] as $k=>$q){ try{$kpis[$k]=$pdo->query($q)->fetchColumn();}catch(Throwable $e){$kpis[$k]=0;} }

// Recent activity
try{$recent=$pdo->query("SELECT lt.*,lb.title btitle,CONCAT(s.first_name,' ',s.last_name) sname,s.student_id sid FROM library_transactions lt JOIN library_books lb ON lb.id=lt.book_id JOIN students s ON s.id=lt.student_id ORDER BY lt.id DESC LIMIT 8")->fetchAll();}catch(Throwable $e){$recent=[];}
// Popular books this year
try{$popular=$pdo->query("SELECT lb.title,lb.author,COUNT(*) borrows FROM library_transactions lt JOIN library_books lb ON lb.id=lt.book_id WHERE lt.academic_year_id=$ayId GROUP BY lb.id ORDER BY borrows DESC LIMIT 6")->fetchAll();}catch(Throwable $e){$popular=[];}
// Overdue alert
try{$overdueList=$pdo->query("SELECT lt.*,lb.title btitle,CONCAT(s.first_name,' ',s.last_name) sname,DATEDIFF(CURDATE(),lt.due_date) days FROM library_transactions lt JOIN library_books lb ON lb.id=lt.book_id JOIN students s ON s.id=lt.student_id WHERE lt.status='Issued' AND lt.due_date<CURDATE() ORDER BY days DESC LIMIT 5")->fetchAll();}catch(Throwable $e){$overdueList=[];}

$hour=(int)date('G'); $greet=$hour<12?'Good morning':($hour<17?'Good afternoon':'Good evening');
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Library Dashboard — KHS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">

<div class="page-heading">
  <div><h1><?=$greet?>, <?=e($librarian['first_name'])?>!</h1><p>School Library &mdash; <?=e($ay)?></p></div>
  <a href="borrowing.php?action=new" class="button button-primary">📤 Issue Book</a>
</div>
<?php foreach(getFlash() as $f):?><div class="alert alert-<?=$f['type']?>"><?=e($f['message'])?></div><?php endforeach;?>

<?php if($kpis['overdue']>0):?>
<div class="alert alert-warning">⏰ <strong><?=$kpis['overdue']?> book<?=$kpis['overdue']!=1?'s':''?></strong> are overdue. <a href="overdue.php" style="font-weight:700">View overdue list →</a></div>
<?php endif;?>

<div class="metric-grid">
  <div class="metric-card"><div class="metric-top"><span>Total Books</span><div class="metric-icon">📚</div></div><strong><?=number_format($kpis['total_books'])?></strong><small><i></i><?=number_format($kpis['total_copies'])?> copies</small></div>
  <div class="metric-card"><div class="metric-top"><span>On Loan</span><div class="metric-icon">📤</div></div><strong><?=$kpis['borrowed']?></strong><small><i></i>Active loans</small></div>
  <div class="metric-card <?=$kpis['overdue']>0?'finance-metrics':''?>"><div class="metric-top"><span>Overdue</span><div class="metric-icon">⏰</div></div><strong style="color:<?=$kpis['overdue']>0?'var(--error)':'var(--green)'?>"><?=$kpis['overdue']?></strong><small><i></i>Past due date</small></div>
  <div class="metric-card"><div class="metric-top"><span>Today Issued</span><div class="metric-icon">📤</div></div><strong><?=$kpis['today_issued']?></strong></div>
  <div class="metric-card"><div class="metric-top"><span>Today Returned</span><div class="metric-icon">📥</div></div><strong style="color:var(--green)"><?=$kpis['today_returned']?></strong></div>
  <div class="metric-card <?=$kpis['unpaid_fines']>0?'finance-metrics':''?>"><div class="metric-top"><span>Unpaid Fines</span><div class="metric-icon">💰</div></div><strong style="color:<?=$kpis['unpaid_fines']>0?'var(--error)':'var(--green)'?>"><?=$currency?> <?=number_format($kpis['unpaid_fines'],2)?></strong></div>
</div>

<!-- Quick actions -->
<div class="panel" style="padding:18px;margin-bottom:16px">
  <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:10px">Quick Actions</div>
  <div class="quick-grid">
    <a href="borrowing.php?action=new" class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px"><span class="qi-icon">📤</span><div><strong>Issue Book</strong><small>New loan</small></div></a>
    <a href="returns.php"              class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px"><span class="qi-icon">📥</span><div><strong>Return Book</strong><small><?=$kpis['borrowed']?> active</small></div></a>
    <a href="overdue.php"              class="quick-item <?=$kpis['overdue']>0?'':'?'?>" style="flex-direction:row;align-items:center;gap:12px;padding:14px<?=$kpis['overdue']>0?';border-color:var(--error)':''?>"><span class="qi-icon">⏰</span><div><strong>Overdue</strong><small><?=$kpis['overdue']?> books</small></div></a>
    <a href="books.php?action=new"     class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px"><span class="qi-icon">📚</span><div><strong>Add Book</strong><small>New catalogue entry</small></div></a>
    <a href="members.php"              class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px"><span class="qi-icon">👥</span><div><strong>Members</strong><small>Student/teacher/staff</small></div></a>
    <a href="reports.php"              class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px"><span class="qi-icon">📊</span><div><strong>Reports</strong><small>Statistics</small></div></a>
  </div>
</div>

<div style="display:grid;grid-template-columns:3fr 2fr;gap:16px;margin-bottom:16px">
  <!-- Recent activity -->
  <div class="panel" style="padding:18px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <h3 style="font-weight:700;font-size:13px">⏱ Recent Transactions</h3>
      <a href="borrowing.php" style="font-size:12px;color:var(--primary)">All →</a>
    </div>
    <?php if(empty($recent)):?><p class="muted" style="font-size:13px">No transactions yet.</p><?php else:?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Student</th><th>Book</th><th>Due</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach($recent as $t): $ov=$t['status']==='Issued'&&$t['due_date']<date('Y-m-d');?>
          <tr>
            <td><strong><?=e($t['sname'])?></strong><div style="font-size:11px;color:var(--ink-faint)"><?=e($t['sid'])?></div></td>
            <td style="max-width:140px"><div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=e($t['btitle'])?></div></td>
            <td class="<?=$ov?'':'muted'?>" style="<?=$ov?'color:var(--error);font-weight:700':''?>"><?=date('d M',strtotime($t['due_date']))?></td>
            <td>
              <?php if($t['status']==='Returned'):?><span class="status approved" style="font-size:10px">Returned</span>
              <?php elseif($ov):?><span class="status warning" style="font-size:10px">Overdue</span>
              <?php else:?><span style="font-size:10px;background:var(--primary);color:#fff;padding:2px 7px;border-radius:10px;font-weight:700">Active</span><?php endif;?>
            </td>
          </tr>
          <?php endforeach;?>
        </tbody>
      </table>
    </div>
    <?php endif;?>
  </div>
  <!-- Popular books -->
  <div class="panel" style="padding:18px">
    <h3 style="font-weight:700;font-size:13px;margin-bottom:12px">⭐ Most Borrowed</h3>
    <?php if(empty($popular)):?><p class="muted" style="font-size:13px">No borrowing data yet.</p>
    <?php else:foreach($popular as $p):?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--line)">
      <div><div style="font-size:12.5px;font-weight:600"><?=e(mb_substr($p['title'],0,32))?><?=mb_strlen($p['title'])>32?'…':''?></div>
      <div style="font-size:11px;color:var(--ink-soft)"><?=e($p['author']??'Unknown')?></div></div>
      <span style="background:var(--primary);color:#fff;font-size:11px;padding:2px 8px;border-radius:10px;font-weight:700;flex-shrink:0"><?=$p['borrows']?></span>
    </div>
    <?php endforeach;endif;?>
  </div>
</div>

<!-- Overdue alert panel -->
<?php if(!empty($overdueList)):?>
<div class="panel" style="padding:18px;border-top:3px solid var(--error)">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
    <h3 style="font-weight:700;font-size:13px;color:var(--error)">⚠️ Overdue Books (top 5)</h3>
    <a href="overdue.php" style="font-size:12px;color:var(--error);font-weight:700">View all →</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Student</th><th>Book</th><th>Due Date</th><th>Days Overdue</th><th>Fine</th></tr></thead>
      <tbody>
        <?php foreach($overdueList as $o): $fine=round($o['days']*$finePerDay,2);?>
        <tr>
          <td><strong><?=e($o['sname'])?></strong></td>
          <td style="max-width:150px"><div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=e($o['btitle'])?></div></td>
          <td style="color:var(--error);font-weight:700"><?=date('d M Y',strtotime($o['due_date']))?></td>
          <td style="color:var(--error);font-weight:800"><?=$o['days']?> day<?=$o['days']!=1?'s':''?></td>
          <td style="font-weight:700;color:var(--error)"><?=$currency?> <?=number_format($fine,2)?></td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
  </div>
</div>
<?php endif;?>

</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
