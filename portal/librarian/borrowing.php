<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['librarian','principal','sys_admin','school_admin']);
$activePage='borrowing'; $pdo=db(); $user=currentUser(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
$librarian=null; try{$s=$pdo->prepare("SELECT * FROM staff WHERE user_id=? LIMIT 1");$s->execute([$user['id']]);$librarian=$s->fetch()?:null;}catch(Throwable $e){}
if(!$librarian) $librarian=['first_name'=>$user['username']??'Librarian','last_name'=>'','id'=>0];

try{$fs=$pdo->query("SELECT * FROM library_fine_settings WHERE academic_year_id=$ayId OR academic_year_id IS NULL ORDER BY academic_year_id DESC LIMIT 1")->fetch();}catch(Throwable $e){$fs=null;}
$currency=$fs['currency_symbol']??'GHS';

if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf(); $pa=$_POST['action']??'';
    if($pa==='issue'){
        $bookId=(int)$_POST['book_id']; $memberId=(int)$_POST['member_id']; $dueDate=$_POST['due_date']; $memberType=$_POST['member_type']??'student';
        $studentId=$memberType==='student'?$memberId:null;
        try{
            $avail=(int)$pdo->query("SELECT available FROM library_books WHERE id=$bookId AND is_active=1")->fetchColumn();
            if($avail<1) throw new RuntimeException('No copies available for this book.');
            $pdo->prepare("INSERT INTO library_transactions (book_id,student_id,member_type,member_id,academic_year_id,borrow_date,issued_at,due_date,status,issued_by) VALUES (?,?,?,?,?,CURDATE(),NOW(),?,'Issued',?)")
                ->execute([$bookId,$studentId,$memberType,$memberId,$ayId,$dueDate,$user['id']]);
            $pdo->prepare("UPDATE library_books SET available=available-1 WHERE id=?")->execute([$bookId]);
            flash('success','Book issued successfully. Due: '.date('d M Y',strtotime($dueDate)).'.');
        }catch(Throwable $e){flash('error','Failed: '.$e->getMessage());}
        redirect(BASE_URL.'/portal/librarian/borrowing.php');
    }
    if($pa==='renew'){
        $txId=(int)$_POST['tx_id']; $newDue=$_POST['new_due_date'];
        try{
            $pdo->prepare("UPDATE library_transactions SET due_date=?,renewal_due_date=?,renewed_count=renewed_count+1,updated_at=NOW() WHERE id=? AND status='Issued'")->execute([$newDue,$newDue,$txId]);
            flash('success','Loan renewed until '.date('d M Y',strtotime($newDue)).'.');
        }catch(Throwable $e){flash('error','Renewal failed.');}
        redirect(BASE_URL.'/portal/librarian/borrowing.php');
    }
    if($pa==='reserve'){
        $bookId=(int)$_POST['book_id']; $memberId=(int)$_POST['member_id']; $memberType=$_POST['member_type']??'student';
        $studentId=$memberType==='student'?$memberId:null;
        try{
            $pdo->prepare("INSERT INTO library_reservations (book_id,student_id,member_type,member_id,academic_year_id,expires_at,status,reserved_by) VALUES (?,?,?,?,?,DATE_ADD(CURDATE(),INTERVAL 7 DAY),'pending',?)")
                ->execute([$bookId,$studentId,$memberType,$memberId,$ayId,$user['id']]);
            flash('success','Book reserved. Reservation valid for 7 days.');
        }catch(Throwable $e){flash('error','Failed: '.$e->getMessage());}
        redirect(BASE_URL.'/portal/librarian/borrowing.php');
    }
}

$action=$_GET['action']??''; $preBookId=(int)($_GET['book_id']??0);
$fStatus=$_GET['status']??'Issued'; $fSearch=trim($_GET['q']??'');

$where=["1=1"]; $params=[];
if($fStatus) {$where[]="lt.status=?";$params[]=$fStatus;}
if($fSearch) {$where[]="(s.first_name LIKE ? OR s.last_name LIKE ? OR lb.title LIKE ? OR s.student_id LIKE ?)";$p="%$fSearch%";$params=array_merge($params,[$p,$p,$p,$p]);}
try{
    $loans=$pdo->prepare("SELECT lt.*,lb.title btitle,lb.isbn,CONCAT(s.first_name,' ',s.last_name) sname,s.student_id sid,c.name cname,DATEDIFF(CURDATE(),lt.due_date) days_overdue FROM library_transactions lt JOIN library_books lb ON lb.id=lt.book_id LEFT JOIN students s ON s.id=lt.student_id LEFT JOIN classes c ON c.id=s.current_class_id WHERE ".implode(' AND ',$where)." ORDER BY lt.id DESC LIMIT 100");
    $loans->execute($params);$loans=$loans->fetchAll();
}catch(Throwable $e){$loans=[];}

// Reservations
try{$reservations=$pdo->query("SELECT r.*,lb.title btitle,CONCAT(s.first_name,' ',s.last_name) sname,s.student_id sid FROM library_reservations r JOIN library_books lb ON lb.id=r.book_id LEFT JOIN students s ON s.id=r.student_id WHERE r.status='pending' AND r.academic_year_id=$ayId ORDER BY r.reserved_at ASC LIMIT 30")->fetchAll();}catch(Throwable $e){$reservations=[];}

try{$allStudents=$pdo->query("SELECT id,'student' mtype,student_id mid_str,CONCAT(first_name,' ',last_name) name FROM students WHERE status='Active' ORDER BY last_name,first_name")->fetchAll();}catch(Throwable $e){$allStudents=[];}
try{$allTeachers=$pdo->query("SELECT id,'teacher' mtype,teacher_id mid_str,CONCAT(first_name,' ',last_name) name FROM teachers WHERE status='Active' ORDER BY first_name")->fetchAll();}catch(Throwable $e){$allTeachers=[];}
try{$allStaff=$pdo->query("SELECT id,'staff' mtype,employee_id mid_str,CONCAT(first_name,' ',last_name) name FROM staff WHERE status='Active' ORDER BY first_name")->fetchAll();}catch(Throwable $e){$allStaff=[];}
try{$availBooks=$pdo->query("SELECT id,title,author,available FROM library_books WHERE available>0 AND is_active=1 ORDER BY title")->fetchAll();}catch(Throwable $e){$availBooks=[];}
try{$allBooks=$pdo->query("SELECT id,title,author,available FROM library_books WHERE is_active=1 ORDER BY title")->fetchAll();}catch(Throwable $e){$allBooks=[];}
$defaultDue=date('Y-m-d',strtotime('+14 days'));
$tab=$_GET['tab']??'loans';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Borrowing — Library Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">
<div class="page-heading">
  <div><h1>Issue / Borrowing</h1><p>Manage book loans — <?=e($ay)?></p></div>
  <a href="?action=new" class="button button-primary">+ Issue Book</a>
</div>
<?php foreach(getFlash() as $f):?><div class="alert alert-<?=$f['type']?>"><?=e($f['message'])?></div><?php endforeach;?>

<?php if($action==='new'||$action==='reserve'): ?>
<!-- ISSUE / RESERVE FORM -->
<div class="panel" style="padding:22px;margin-bottom:16px">
  <div class="tab-bar" style="margin-bottom:16px">
    <a href="?action=new"     class="tab-btn <?=$action!=='reserve'?'active':''?>">📤 Issue Book</a>
    <a href="?action=reserve" class="tab-btn <?=$action==='reserve'?'active':''?>">🔖 Reserve Book</a>
  </div>
  <form method="post">
    <?=csrfField()?><input type="hidden" name="action" value="<?=$action==='reserve'?'reserve':'issue'?>"/>
    <div class="form-grid">
      <div class="form-group">
        <label>Member Type</label>
        <select name="member_type" id="memberType" onchange="loadMembers()">
          <option value="student">Student</option>
          <option value="teacher">Teacher</option>
          <option value="staff">Staff</option>
        </select>
      </div>
      <div class="form-group">
        <label>Member <span style="color:var(--error)">*</span></label>
        <select name="member_id" id="memberSelect" required>
          <option value="">— Select member —</option>
          <optgroup id="grpStudents" label="Students">
            <?php foreach($allStudents as $m):?><option value="<?=$m['id']?>" data-type="student"><?=e($m['name'].' ('.$m['mid_str'].')')?></option><?php endforeach;?>
          </optgroup>
          <optgroup id="grpTeachers" label="Teachers" style="display:none">
            <?php foreach($allTeachers as $m):?><option value="<?=$m['id']?>" data-type="teacher"><?=e($m['name'].' ('.$m['mid_str'].')')?></option><?php endforeach;?>
          </optgroup>
          <optgroup id="grpStaff" label="Staff" style="display:none">
            <?php foreach($allStaff as $m):?><option value="<?=$m['id']?>" data-type="staff"><?=e($m['name'].' ('.$m['mid_str']??''.')')?></option><?php endforeach;?>
          </optgroup>
        </select>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Book <span style="color:var(--error)">*</span></label>
        <select name="book_id" required>
          <option value="">— Select book —</option>
          <?php foreach(($action==='reserve'?$allBooks:$availBooks) as $b):?>
          <option value="<?=$b['id']?>" <?=$preBookId===$b['id']?'selected':''?>><?=e($b['title'].' — '.($b['author']??'').' ('.$b['available'].' avail.)')?></option>
          <?php endforeach;?>
        </select>
      </div>
      <?php if($action!=='reserve'):?>
      <div class="form-group"><label>Due Date <span style="color:var(--error)">*</span></label><input type="date" name="due_date" required value="<?=$defaultDue?>" min="<?=date('Y-m-d')?>"/></div>
      <?php endif;?>
    </div>
    <div style="display:flex;gap:10px;margin-top:8px">
      <button type="submit" class="button button-primary"><?=$action==='reserve'?'🔖 Reserve Book':'📤 Issue Book'?></button>
      <a href="borrowing.php" class="button button-secondary">Cancel</a>
    </div>
  </form>
</div>
<script>
function loadMembers(){
  var t=document.getElementById('memberType').value;
  document.getElementById('grpStudents').style.display=t==='student'?'':'none';
  document.getElementById('grpTeachers').style.display=t==='teacher'?'':'none';
  document.getElementById('grpStaff').style.display=t==='staff'?'':'none';
  document.getElementById('memberSelect').value='';
}
</script>
<?php endif;?>

<!-- Tabs -->
<div class="tab-bar" style="margin-bottom:16px">
  <a href="?tab=loans"        class="tab-btn <?=$tab==='loans'?'active':''?>">📋 Active Loans</a>
  <a href="?tab=reservations" class="tab-btn <?=$tab==='reservations'?'active':''?>">🔖 Reservations (<?=count($reservations)?>)</a>
  <a href="?tab=all"          class="tab-btn <?=$tab==='all'?'active':''?>">📂 All Transactions</a>
</div>

<?php if($tab==='reservations'): ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Reserved</th><th>Member</th><th>Book</th><th>Expires</th><th>Action</th></tr></thead>
    <tbody>
      <?php if(empty($reservations)):?><tr><td colspan="5" style="text-align:center;color:var(--ink-faint);padding:24px">No pending reservations.</td></tr>
      <?php else:foreach($reservations as $r):?>
      <tr>
        <td class="muted"><?=date('d M Y',strtotime($r['reserved_at']))?></td>
        <td><strong><?=e($r['sname']??'Unknown')?></strong><div style="font-size:11px;color:var(--ink-faint)"><?=e($r['sid']??'')?></div></td>
        <td><?=e($r['btitle'])?></td>
        <td class="muted"><?=$r['expires_at']?date('d M Y',strtotime($r['expires_at'])):'—'?></td>
        <td><a href="?action=new&book_id=<?=$r['book_id']?>" class="button button-primary button-sm">📤 Issue Now</a></td>
      </tr>
      <?php endforeach;endif;?>
    </tbody>
  </table>
</div>
<?php else: ?>
<!-- Active loans / all -->
<form method="get" class="filter-bar" style="margin-bottom:12px">
  <input type="hidden" name="tab" value="<?=$tab?>"/>
  <input type="text" name="q" placeholder="Search student or book…" value="<?=e($fSearch)?>"/>
  <select name="status">
    <option value="Issued" <?=$fStatus==='Issued'?'selected':''?>>Active Loans</option>
    <option value="Returned" <?=$fStatus==='Returned'?'selected':''?>>Returned</option>
    <option value="" <?=$fStatus===''?'selected':''?>>All</option>
  </select>
  <button type="submit" class="button button-secondary button-sm">Filter</button>
</form>
<div class="table-wrap">
  <table>
    <thead><tr><th>Issued</th><th>Member</th><th>Class</th><th>Book</th><th>Due</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
      <?php if(empty($loans)):?><tr><td colspan="7" style="text-align:center;color:var(--ink-faint);padding:24px">No <?=$fStatus==='Issued'?'active loans':'transactions'?> found.</td></tr>
      <?php else:foreach($loans as $l): $ov=$l['status']==='Issued'&&($l['days_overdue']??0)>0;?>
      <tr <?=$ov?'style="background:rgba(239,68,68,.04)"':''?>>
        <td class="muted"><?=date('d M Y',strtotime($l['borrow_date']??$l['issued_at']))?></td>
        <td><strong><?=e($l['sname']??'Unknown')?></strong><div style="font-size:11px;color:var(--ink-faint)"><?=e($l['sid']??'')?></div></td>
        <td class="muted"><?=e($l['cname']??'—')?></td>
        <td style="max-width:160px"><div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=e($l['btitle'])?></div></td>
        <td class="<?=$ov?'':'muted'?>" style="<?=$ov?'color:var(--error);font-weight:700':''?>"><?=date('d M Y',strtotime($l['due_date']))?><?php if($ov):?> <small style="font-size:10px">(+<?=$l['days_overdue']?>d)</small><?php endif;?></td>
        <td>
          <?php if($l['status']==='Returned'):?><span class="status approved" style="font-size:10px">Returned</span>
          <?php elseif($ov):?><span class="status warning" style="font-size:10px">Overdue</span>
          <?php else:?><span style="font-size:10px;background:var(--primary);color:#fff;padding:2px 7px;border-radius:10px;font-weight:700">Active</span><?php endif;?>
        </td>
        <td>
          <?php if($l['status']==='Issued'):?>
          <div style="display:flex;gap:4px">
            <a href="returns.php?tx_id=<?=$l['id']?>" class="button button-secondary button-sm">📥 Return</a>
            <button onclick="document.getElementById('renew<?=$l['id']?>').style.display='table-row'" class="button button-secondary button-sm">🔄 Renew</button>
          </div>
          <?php endif;?>
        </td>
      </tr>
      <!-- Renew row -->
      <?php if($l['status']==='Issued'):?>
      <tr id="renew<?=$l['id']?>" style="display:none;background:var(--bg2)">
        <td colspan="7" style="padding:12px 18px">
          <form method="post" style="display:flex;gap:10px;align-items:flex-end">
            <?=csrfField()?><input type="hidden" name="action" value="renew"/><input type="hidden" name="tx_id" value="<?=$l['id']?>"/>
            <div class="form-group"><label>New Due Date</label><input type="date" name="new_due_date" required value="<?=date('Y-m-d',strtotime($l['due_date'].' +14 days'))?>" min="<?=date('Y-m-d')?>"/></div>
            <button type="submit" class="button button-primary button-sm" style="margin-bottom:4px">🔄 Confirm Renewal</button>
            <button type="button" onclick="document.getElementById('renew<?=$l['id']?>').style.display='none'" class="button button-secondary button-sm" style="margin-bottom:4px">Cancel</button>
          </form>
        </td>
      </tr>
      <?php endif;?>
      <?php endforeach;endif;?>
    </tbody>
  </table>
</div>
<?php endif;?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
