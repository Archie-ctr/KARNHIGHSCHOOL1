<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['librarian','principal','sys_admin','school_admin']);
$activePage='books'; $pdo=db(); $user=currentUser(); $ayId=currentAcademicYearId();
$librarian=null; try{$s=$pdo->prepare("SELECT * FROM staff WHERE user_id=? LIMIT 1");$s->execute([$user['id']]);$librarian=$s->fetch()?:null;}catch(Throwable $e){}
if(!$librarian) $librarian=['first_name'=>$user['username']??'Librarian','last_name'=>'','id'=>0];

$action=$_GET['action']??''; $bookId=(int)($_GET['id']??0);

if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf(); $pa=$_POST['action']??'';
    if($pa==='create'){
        try{
            $pdo->prepare("INSERT INTO library_books (title,author,isbn,publisher,publisher_full,publication_year,category_id,category,total_copies,available,location,description,edition,language,pages,condition_status,is_active,added_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,NOW())")
                ->execute([trim($_POST['title']),trim($_POST['author']??''),trim($_POST['isbn']??''),trim($_POST['publisher']??''),trim($_POST['publisher']??''),
                    (int)$_POST['publication_year']?:null, (int)$_POST['category_id']?:null,
                    // also store category name for backward compat
                    null, // will fill below
                    (int)$_POST['total_copies']??1,(int)$_POST['total_copies']??1,
                    trim($_POST['location']??''),trim($_POST['description']??''),
                    trim($_POST['edition']??''),trim($_POST['language']??'English'),
                    (int)$_POST['pages']?:null, $_POST['condition_status']??'Good', $user['id']]);
            $newId=(int)$pdo->lastInsertId();
            // Back-fill category name
            if(!empty($_POST['category_id'])){ try{$cn=$pdo->query("SELECT name FROM book_categories WHERE id=".(int)$_POST['category_id'])->fetchColumn(); if($cn) $pdo->prepare("UPDATE library_books SET category=? WHERE id=?")->execute([$cn,$newId]);}catch(Throwable $e){} }
            // Log inventory
            $pdo->prepare("INSERT INTO library_inventory_log (book_id,action,copies,notes,logged_by) VALUES (?,'added',?,?,?)")->execute([$newId,(int)$_POST['total_copies']??1,'New book added',$user['id']]);
            flash('success','Book added to catalogue.');
        }catch(Throwable $e){flash('error','Failed: '.$e->getMessage());}
        redirect(BASE_URL.'/portal/librarian/books.php');
    }
    if($pa==='update'&&$bookId){
        try{
            $pdo->prepare("UPDATE library_books SET title=?,author=?,isbn=?,publisher=?,publisher_full=?,publication_year=?,category_id=?,location=?,description=?,edition=?,language=?,pages=?,condition_status=?,updated_at=NOW() WHERE id=?")
                ->execute([trim($_POST['title']),trim($_POST['author']??''),trim($_POST['isbn']??''),trim($_POST['publisher']??''),trim($_POST['publisher']??''),
                    (int)$_POST['publication_year']?:null,(int)$_POST['category_id']?:null,
                    trim($_POST['location']??''),trim($_POST['description']??''),trim($_POST['edition']??''),trim($_POST['language']??'English'),(int)$_POST['pages']?:null,$_POST['condition_status']??'Good',$bookId]);
            if(!empty($_POST['category_id'])){ try{$cn=$pdo->query("SELECT name FROM book_categories WHERE id=".(int)$_POST['category_id'])->fetchColumn(); if($cn) $pdo->prepare("UPDATE library_books SET category=? WHERE id=?")->execute([$cn,$bookId]);}catch(Throwable $e){} }
            // Adjust copies if changed
            $old=$pdo->query("SELECT total_copies,available FROM library_books WHERE id=$bookId")->fetch();
            $newCopies=(int)$_POST['total_copies']??$old['total_copies'];
            if($newCopies!==$old['total_copies']){
                $diff=$newCopies-$old['total_copies'];
                $pdo->prepare("UPDATE library_books SET total_copies=?,available=available+? WHERE id=?")->execute([$newCopies,$diff,$bookId]);
                $pdo->prepare("INSERT INTO library_inventory_log (book_id,action,copies,notes,logged_by) VALUES (?,'adjusted',?,?,?)")->execute([$bookId,abs($diff),($diff>0?'Copies added':'Copies removed'),$user['id']]);
            }
            flash('success','Book updated.');
        }catch(Throwable $e){flash('error','Failed: '.$e->getMessage());}
        redirect(BASE_URL.'/portal/librarian/books.php?id='.$bookId);
    }
    if($pa==='archive'&&$bookId){
        try{$pdo->prepare("UPDATE library_books SET is_active=0 WHERE id=?")->execute([$bookId]);flash('success','Book archived.');}
        catch(Throwable $e){flash('error','Cannot archive.');}
        redirect(BASE_URL.'/portal/librarian/books.php');
    }
    if($pa==='unarchive'&&$bookId){
        try{$pdo->prepare("UPDATE library_books SET is_active=1 WHERE id=?")->execute([$bookId]);flash('success','Book restored.');}
        catch(Throwable $e){flash('error','Failed.');}
        redirect(BASE_URL.'/portal/librarian/books.php?id='.$bookId);
    }
    if($pa==='delete'&&$bookId){
        try{$pdo->prepare("DELETE FROM library_books WHERE id=?")->execute([$bookId]);flash('success','Book deleted.');}
        catch(Throwable $e){flash('error','Cannot delete — active loans exist.');}
        redirect(BASE_URL.'/portal/librarian/books.php');
    }
}

$book=null;
if($bookId){
    try{$book=$pdo->query("SELECT lb.*,bc.name cat_name FROM library_books lb LEFT JOIN book_categories bc ON bc.id=lb.category_id WHERE lb.id=$bookId")->fetch();}catch(Throwable $e){}
    // Loan history
    try{$loanHistory=$pdo->query("SELECT lt.*,CONCAT(s.first_name,' ',s.last_name) sname,s.student_id sid FROM library_transactions lt JOIN students s ON s.id=lt.student_id WHERE lt.book_id=$bookId ORDER BY lt.id DESC LIMIT 20")->fetchAll();}catch(Throwable $e){$loanHistory=[];}
}

$fSearch=trim($_GET['q']??''); $fCat=(int)($_GET['cat']??0); $fStatus=$_GET['status']??'active'; $fAuthor=trim($_GET['author']??'');
$where=['1=1']; $params=[];
if($fStatus==='active') $where[]="lb.is_active=1";
elseif($fStatus==='archived') $where[]="lb.is_active=0";
if($fSearch){$where[]="(lb.title LIKE ? OR lb.author LIKE ? OR lb.isbn LIKE ?)";$p="%$fSearch%";$params=[$p,$p,$p];}
if($fCat){$where[]="lb.category_id=?";$params[]=$fCat;}
if($fAuthor){$where[]="lb.author LIKE ?";$params[]="%$fAuthor%";}
try{
    $books=$pdo->prepare("SELECT lb.*,bc.name cat_name,(SELECT COUNT(*) FROM library_transactions lt WHERE lt.book_id=lb.id AND lt.status='Issued') on_loan FROM library_books lb LEFT JOIN book_categories bc ON bc.id=lb.category_id WHERE ".implode(' AND ',$where)." ORDER BY lb.title LIMIT 200");
    $books->execute($params);$books=$books->fetchAll();
}catch(Throwable $e){$books=[];}
try{$cats=$pdo->query("SELECT * FROM book_categories ORDER BY name")->fetchAll();}catch(Throwable $e){$cats=[];}
$conditions=['Good','Fair','Damaged','Poor'];
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Books — Library Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">
<div class="page-heading">
  <div><h1><?=$bookId&&$book?e(mb_substr($book['title'],0,50)):($action==='new'?'Add New Book':'Book Catalogue')?></h1>
  <p><?=!$bookId&&$action!=='new'?count($books).' book'.((count($books))!=1?'s':'').' found':'Library Management'?></p></div>
  <div style="display:flex;gap:8px">
    <?php if($bookId):?><a href="books.php" class="button button-secondary">← Catalogue</a><?php endif;?>
    <?php if(!$bookId&&$action!=='new'):?><a href="?action=new" class="button button-primary">+ Add Book</a><?php endif;?>
  </div>
</div>
<?php foreach(getFlash() as $f):?><div class="alert alert-<?=$f['type']?>"><?=e($f['message'])?></div><?php endforeach;?>

<?php if($action==='new'||($bookId&&$action==='edit'&&$book)): ?>
<!-- FORM -->
<div class="panel" style="padding:22px;margin-bottom:16px">
  <h3 style="font-weight:700;margin-bottom:16px"><?=$bookId?'Edit Book':'Add New Book'?></h3>
  <form method="post" action="?<?=$bookId?"id=$bookId":''?>">
    <?=csrfField()?><input type="hidden" name="action" value="<?=$bookId?'update':'create'?>"/>
    <div class="form-grid">
      <div class="form-group" style="grid-column:1/-1"><label>Title <span style="color:var(--error)">*</span></label><input type="text" name="title" required value="<?=e($book['title']??'')?>"/></div>
      <div class="form-group"><label>Author(s)</label><input type="text" name="author" value="<?=e($book['author']??'')?>" placeholder="e.g. John Smith"/></div>
      <div class="form-group"><label>ISBN</label><input type="text" name="isbn" value="<?=e($book['isbn']??'')?>" placeholder="978-x-xxx-xxxxx-x"/></div>
      <div class="form-group"><label>Publisher</label><input type="text" name="publisher" value="<?=e($book['publisher_full']??$book['publisher']??'')?>"/></div>
      <div class="form-group"><label>Publication Year</label><input type="number" name="publication_year" min="1800" max="<?=date('Y')?>" value="<?=e($book['publication_year']??$book['year']??'')?>"/></div>
      <div class="form-group"><label>Category</label>
        <select name="category_id"><option value="">— Uncategorised —</option>
          <?php foreach($cats as $c):?><option value="<?=$c['id']?>" <?=($book['category_id']??0)==$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?>
        </select>
      </div>
      <div class="form-group"><label>Total Copies</label><input type="number" name="total_copies" min="1" value="<?=(int)($book['total_copies']??1)?>"/></div>
      <div class="form-group"><label>Edition</label><input type="text" name="edition" value="<?=e($book['edition']??'')?>" placeholder="e.g. 3rd Edition"/></div>
      <div class="form-group"><label>Language</label><input type="text" name="language" value="<?=e($book['language']??'English')?>"/></div>
      <div class="form-group"><label>Pages</label><input type="number" name="pages" min="1" value="<?=e($book['pages']??'')?>"/></div>
      <div class="form-group"><label>Shelf Location</label><input type="text" name="location" placeholder="e.g. Section A, Shelf 3" value="<?=e($book['location']??'')?>"/></div>
      <div class="form-group"><label>Condition</label>
        <select name="condition_status"><?php foreach($conditions as $c):?><option value="<?=$c?>" <?=($book['condition_status']??'Good')===$c?'selected':''?>><?=$c?></option><?php endforeach;?></select>
      </div>
      <div class="form-group" style="grid-column:1/-1"><label>Description / Synopsis</label><textarea name="description" rows="3" placeholder="Brief description…"><?=e($book['description']??'')?></textarea></div>
    </div>
    <div style="display:flex;gap:10px;margin-top:8px">
      <button type="submit" class="button button-primary">💾 <?=$bookId?'Update Book':'Add Book'?></button>
      <a href="books.php<?=$bookId?"?id=$bookId":''?>" class="button button-secondary">Cancel</a>
    </div>
  </form>
</div>

<?php elseif($bookId&&$book): ?>
<!-- DETAIL VIEW -->
<div style="display:grid;grid-template-columns:1fr 300px;gap:16px">
  <div>
    <div class="panel" style="padding:20px;margin-bottom:14px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;margin-bottom:14px">
        <div>
          <h3 style="font-weight:800;font-size:16px;margin-bottom:3px"><?=e($book['title'])?></h3>
          <p style="font-size:13.5px;color:var(--ink-soft)">by <?=e($book['author']??'Unknown')?></p>
          <?php if($book['isbn']):?><p style="font-size:12px;color:var(--ink-faint)">ISBN: <?=e($book['isbn'])?></p><?php endif;?>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <?php if(!$book['is_active']):?><span class="status warning">Archived</span><?php endif;?>
          <a href="?id=<?=$bookId?>&action=edit" class="button button-secondary button-sm">✏️ Edit</a>
        </div>
      </div>
      <div class="form-grid">
        <div><label>Category</label><p><?=e($book['cat_name']??$book['category']??'—')?></p></div>
        <div><label>Publisher</label><p><?=e($book['publisher_full']??$book['publisher']??'—')?></p></div>
        <div><label>Year</label><p><?=e($book['publication_year']??$book['year']??'—')?></p></div>
        <div><label>Edition</label><p><?=e($book['edition']??'—')?></p></div>
        <div><label>Language</label><p><?=e($book['language']??'English')?></p></div>
        <div><label>Pages</label><p><?=$book['pages']?:'—'?></p></div>
        <div><label>Location</label><p><?=e($book['location']??'—')?></p></div>
        <div><label>Condition</label><p><span style="color:<?=$book['condition_status']==='Good'?'var(--green)':($book['condition_status']==='Damaged'?'var(--error)':'var(--warning)')?>; font-weight:700"><?=e($book['condition_status']??'Good')?></span></p></div>
        <div><label>Total Copies</label><p><strong><?=(int)$book['total_copies']?></strong></p></div>
        <div><label>Available</label><p><strong style="color:<?=(int)($book['available']??0)>0?'var(--green)':'var(--error)'?>"><?=(int)($book['available']??0)?></strong></p></div>
        <?php if($book['damaged_copies']||$book['lost_copies']):?>
        <div><label>Damaged</label><p style="color:var(--warning)"><?=$book['damaged_copies']?:0?></p></div>
        <div><label>Lost</label><p style="color:var(--error)"><?=$book['lost_copies']?:0?></p></div>
        <?php endif;?>
        <?php if($book['description']):?><div style="grid-column:1/-1"><label>Description</label><p><?=e($book['description'])?></p></div><?php endif;?>
      </div>
    </div>
    <!-- Loan history -->
    <?php if(!empty($loanHistory)):?>
    <div class="panel" style="padding:18px">
      <h4 style="font-weight:700;font-size:13px;margin-bottom:12px">📋 Loan History</h4>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Student</th><th>Issued</th><th>Due</th><th>Returned</th><th>Fine</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach($loanHistory as $l):?>
            <tr>
              <td><strong><?=e($l['sname'])?></strong><div style="font-size:11px;color:var(--ink-faint)"><?=e($l['sid'])?></div></td>
              <td class="muted"><?=date('d M Y',strtotime($l['borrow_date']??$l['issued_at']))?></td>
              <td class="muted"><?=date('d M Y',strtotime($l['due_date']))?></td>
              <td class="muted"><?=$l['returned_date']??$l['returned_at']?date('d M Y',strtotime($l['returned_date']??$l['returned_at'])):'—'?></td>
              <td><?=$l['fine_amount']>0?'GHS '.number_format($l['fine_amount'],2):'—'?></td>
              <td><span class="status <?=$l['status']==='Returned'?'approved':($l['due_date']<date('Y-m-d')&&$l['status']==='Issued'?'warning':'new-s')?>" style="font-size:10px"><?=e($l['status'])?></span></td>
            </tr>
            <?php endforeach;?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif;?>
  </div>
  <div style="display:flex;flex-direction:column;gap:12px">
    <div class="panel" style="padding:18px">
      <h4 style="font-weight:700;font-size:13px;margin-bottom:12px">⚡ Actions</h4>
      <div style="display:flex;flex-direction:column;gap:8px">
        <a href="borrowing.php?action=new&book_id=<?=$bookId?>" class="button button-primary" style="text-align:center">📤 Issue this Book</a>
        <a href="?id=<?=$bookId?>&action=edit" class="button button-secondary" style="text-align:center">✏️ Edit Details</a>
        <?php if($book['is_active']):?>
        <form method="post" action="?id=<?=$bookId?>" onsubmit="return confirm('Archive this book?')"><<?=csrfField()?><input type="hidden" name="action" value="archive"/><button class="button button-secondary" style="width:100%">📦 Archive Book</button></form>
        <?php else:?>
        <form method="post" action="?id=<?=$bookId?>"><<?=csrfField()?><input type="hidden" name="action" value="unarchive"/><button class="button button-secondary" style="width:100%">♻️ Restore Book</button></form>
        <?php endif;?>
        <form method="post" action="?id=<?=$bookId?>" onsubmit="return confirm('Permanently delete? Cannot undo.')"><<?=csrfField()?><input type="hidden" name="action" value="delete"/><button class="button button-danger" style="width:100%">🗑 Delete</button></form>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<!-- LIST -->
<form method="get" class="filter-bar" style="margin-bottom:16px">
  <input type="text" name="q" placeholder="Title, author, ISBN…" value="<?=e($fSearch)?>"/>
  <select name="cat"><option value="">All Categories</option><?php foreach($cats as $c):?><option value="<?=$c['id']?>" <?=$fCat==$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select>
  <select name="status"><option value="active">Active</option><option value="archived" <?=$fStatus==='archived'?'selected':''?>>Archived</option><option value="" <?=$fStatus===''?'selected':''?>>All</option></select>
  <button type="submit" class="button button-secondary button-sm">Filter</button>
  <a href="books.php" class="button button-secondary button-sm">Reset</a>
</form>
<div class="table-wrap">
  <table>
    <thead><tr><th>Title</th><th>Author</th><th>ISBN</th><th>Category</th><th>Copies</th><th>Available</th><th>On Loan</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php if(empty($books)):?>
      <tr><td colspan="9" style="text-align:center;color:var(--ink-faint);padding:32px">No books found. <a href="?action=new">Add one →</a></td></tr>
      <?php else:foreach($books as $b):?>
      <tr>
        <td><strong><?=e(mb_substr($b['title'],0,40))?><?=mb_strlen($b['title'])>40?'…':''?></strong></td>
        <td class="muted"><?=e($b['author']??'—')?></td>
        <td class="muted" style="font-size:11px"><?=e($b['isbn']??'—')?></td>
        <td class="muted"><?=e($b['cat_name']??$b['category']??'—')?></td>
        <td><?=(int)$b['total_copies']?></td>
        <td><strong style="color:<?=(int)($b['available']??0)>0?'var(--green)':'var(--error)'?>"><?=(int)($b['available']??0)?></strong></td>
        <td class="muted"><?=(int)$b['on_loan']?></td>
        <td><?=$b['is_active']?'<span class="status approved" style="font-size:10px">Active</span>':'<span class="status warning" style="font-size:10px">Archived</span>'?></td>
        <td style="display:flex;gap:4px"><a href="?id=<?=$b['id']?>" class="button button-secondary button-sm">View</a><a href="borrowing.php?action=new&book_id=<?=$b['id']?>" class="button button-secondary button-sm">Issue</a></td>
      </tr>
      <?php endforeach;endif;?>
    </tbody>
  </table>
</div>
<?php endif;?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
