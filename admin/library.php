<?php
require_once dirname(__DIR__).'/config/db.php';
requireAuth();
requireRole(['sys_admin','school_admin','principal','librarian','registrar','teacher','class_teacher']);

$pdo = db();

$canManage = can('manage_library');  // librarian, school_admin, principal, sys_admin
$canIssue  = can('issue_books');     // librarian, school_admin, principal, sys_admin
$canView   = can('view_library');    // teacher, class_teacher, registrar + above

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_book' && $canManage) {
        $pdo->prepare("INSERT INTO library_books (isbn,title,author,category,publisher,year,total_copies,available)
            VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$_POST['isbn']?:null,$_POST['title'],$_POST['author']??null,
                      $_POST['category']??null,$_POST['publisher']??null,
                      $_POST['year']?:null,(int)($_POST['copies']??1),(int)($_POST['copies']??1)]);
        flash('success','Book added to library.');

    } elseif ($action === 'issue' && $canIssue) {
        $bookId = (int)($_POST['book_id'] ?? 0);
        $stdId  = (int)($_POST['student_id'] ?? 0);
        $due    = $_POST['due_date'] ?? '';
        if ($bookId && $stdId && $due) {
            $avail = (int)$pdo->query("SELECT available FROM library_books WHERE id=$bookId")->fetchColumn();
            if ($avail > 0) {
                $pdo->prepare("INSERT INTO library_transactions (book_id,student_id,issued_by,due_date) VALUES (?,?,?,?)")
                   ->execute([$bookId,$stdId,currentUser()['id'],$due]);
                $pdo->prepare("UPDATE library_books SET available=available-1 WHERE id=?")->execute([$bookId]);
                flash('success','Book issued successfully.');
            } else {
                flash('error','No copies available for this book.');
            }
        }

    } elseif ($action === 'return' && $canIssue) {
        $txId = (int)($_POST['tx_id'] ?? 0);
        if ($txId) {
            $tx = $pdo->query("SELECT book_id FROM library_transactions WHERE id=$txId")->fetch();
            $pdo->prepare("UPDATE library_transactions SET status='Returned',returned_at=NOW() WHERE id=?")->execute([$txId]);
            if ($tx) $pdo->prepare("UPDATE library_books SET available=available+1 WHERE id=?")->execute([$tx['book_id']]);
            flash('success','Book returned.');
        }

    } elseif ($action === 'delete_book' && $canManage) {
        $id = (int)($_POST['book_id'] ?? 0);
        if ($id) { $pdo->prepare("DELETE FROM library_books WHERE id=?")->execute([$id]); flash('success','Book removed.'); }

    } else {
        flash('error','You do not have permission to perform that action.');
    }
    redirect(BASE_URL.'/admin/library.php?tab='.($_POST['tab']??'books'));
}

$q   = trim($_GET['q']   ?? '');
$tab = $_GET['tab'] ?? 'books';
// Parameterized search — no addslashes
$where=[]; $params=[];
if ($q) { $where[]="(lb.title LIKE ? OR lb.author LIKE ? OR lb.category LIKE ?)"; $like="%$q%"; array_push($params,$like,$like,$like); }
$wsql=$where?'WHERE '.implode(' AND ',$where):'';
$books = $pdo->prepare("SELECT lb.* FROM library_books lb $wsql ORDER BY lb.title")->execute($params) ? [] : [];
$stmt=$pdo->prepare("SELECT lb.* FROM library_books lb $wsql ORDER BY lb.title"); $stmt->execute($params); $books=$stmt->fetchAll();

$transactions = $pdo->query("SELECT lt.*,lb.title book_title,CONCAT(s.first_name,' ',s.last_name) sname,s.student_id sid FROM library_transactions lt JOIN library_books lb ON lb.id=lt.book_id JOIN students s ON s.id=lt.student_id ORDER BY lt.issued_at DESC LIMIT 60")->fetchAll();
$students     = $pdo->query("SELECT id,student_id,CONCAT(first_name,' ',last_name) name FROM students WHERE status='Active' ORDER BY first_name")->fetchAll();

$pageTitle   = 'Library';
$activeAdmin = 'library';
require_once dirname(__DIR__).'/includes/admin_header.php';
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">Library <span></span></div>
    <h1>Library Management</h1>
    <p><?= $canManage ? 'Add books, issue and manage returns.' : ($canIssue ? 'Issue and return books.' : 'View library catalogue.') ?></p>
  </div>
  <?php if ($canManage): ?>
  <button class="button button-primary" onclick="document.getElementById('addBookModal').style.display='flex'">+ Add Book</button>
  <?php endif; ?>
</div>

<!-- Tabs — only show Issue tab if user can issue -->
<div class="tab-bar" style="margin-bottom:16px">
  <a href="?tab=books" class="tab-btn <?= $tab==='books'?'active':'' ?>">📚 Books (<?= count($books) ?>)</a>
  <a href="?tab=transactions" class="tab-btn <?= $tab==='transactions'?'active':'' ?>">📋 Transactions</a>
  <?php if ($canIssue): ?>
  <a href="?tab=issue" class="tab-btn <?= $tab==='issue'?'active':'' ?>">➕ Issue Book</a>
  <?php endif; ?>
</div>

<?php if ($tab === 'books'): ?>
<form method="get" class="filter-row" style="margin-bottom:12px">
  <input type="hidden" name="tab" value="books"/>
  <div class="table-search">🔍<input type="search" name="q" placeholder="Title, author, category…" value="<?= e($q) ?>"/></div>
  <button class="button button-primary button-sm">Search</button>
  <?php if ($q): ?><a href="?tab=books" class="filter-button">Clear</a><?php endif; ?>
</form>
<div class="table-wrap">
  <table>
    <thead><tr><th>Title</th><th>Author</th><th>Category</th><th>Total</th><th>Available</th><th>Location</th><?php if($canManage):?><th>Actions</th><?php endif;?></tr></thead>
    <tbody>
      <?php if (empty($books)): ?>
      <tr><td colspan="7" style="text-align:center;padding:28px;color:var(--ink-faint)">No books found.</td></tr>
      <?php else: ?>
      <?php foreach ($books as $b): ?>
      <tr>
        <td><strong><?= e($b['title']) ?></strong><?= $b['isbn']?'<div style="font-size:11px;color:var(--ink-faint)">ISBN: '.e($b['isbn']).'</div>':'' ?></td>
        <td><?= e($b['author'] ?? '—') ?></td>
        <td><?= e($b['category'] ?? '—') ?></td>
        <td><?= $b['total_copies'] ?></td>
        <td><strong style="color:<?= $b['available']>0?'var(--green)':'var(--error)' ?>"><?= $b['available'] ?></strong></td>
        <td class="muted"><?= e($b['location'] ?? '—') ?></td>
        <?php if ($canManage): ?>
        <td>
          <form method="post" onsubmit="return confirm('Remove this book?')" style="display:inline">
            <?= csrfField() ?><input type="hidden" name="action" value="delete_book"/><input type="hidden" name="book_id" value="<?= $b['id'] ?>"/>
            <button type="submit" class="filter-button button-sm" style="color:var(--error)">Remove</button>
          </form>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php elseif ($tab === 'transactions'): ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Book</th><th>Student</th><th>Issued</th><th>Due</th><th>Status</th><?php if($canIssue):?><th>Action</th><?php endif;?></tr></thead>
    <tbody>
      <?php foreach ($transactions as $tx): ?>
      <tr>
        <td><strong><?= e($tx['book_title']) ?></strong></td>
        <td><?= e($tx['sname']) ?><div style="font-size:11px;color:var(--ink-faint)"><?= e($tx['sid']) ?></div></td>
        <td class="muted"><?= date('M d, Y', strtotime($tx['issued_at'])) ?></td>
        <td class="muted" style="color:<?= (!$tx['returned_at']&&strtotime($tx['due_date'])<time())?'var(--error)':'inherit' ?>">
          <?= date('M d, Y', strtotime($tx['due_date'])) ?>
          <?= (!$tx['returned_at']&&strtotime($tx['due_date'])<time())?'<span class="status warning" style="margin-left:4px;font-size:10px">Overdue</span>':'' ?>
        </td>
        <td><?= statusBadge($tx['status']) ?></td>
        <?php if ($canIssue): ?>
        <td>
          <?php if ($tx['status']==='Issued'): ?>
          <form method="post" style="display:inline">
            <?= csrfField() ?><input type="hidden" name="action" value="return"/><input type="hidden" name="tx_id" value="<?= $tx['id'] ?>"/>
            <button type="submit" class="filter-button button-sm">↩ Return</button>
          </form>
          <?php endif; ?>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php elseif ($tab === 'issue' && $canIssue): ?>
<div class="form-section">
  <div class="form-section-title">Issue a Book to a Student</div>
  <form method="post">
    <input type="hidden" name="tab" value="issue"/>
    <?= csrfField() ?><input type="hidden" name="action" value="issue"/>
    <div class="form-row full"><div class="form-group"><label>Book *
      <select name="book_id" required>
        <option value="">Select book…</option>
        <?php foreach ($books as $b): ?>
        <option value="<?= $b['id'] ?>" <?= $b['available']<=0?'disabled':'' ?>><?= e($b['title']) ?> <?= $b['author']?'— '.e($b['author']):'' ?> (<?= $b['available'] ?> available)</option>
        <?php endforeach; ?>
      </select>
    </label></div></div>
    <div class="form-row">
      <div class="form-group"><label>Student *
        <select name="student_id" required>
          <option value="">Select student…</option>
          <?php foreach ($students as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name'].' ('.$s['student_id'].')') ?></option><?php endforeach; ?>
        </select>
      </label></div>
      <div class="form-group"><label>Due Date *<input type="date" name="due_date" required value="<?= date('Y-m-d',strtotime('+14 days')) ?>"/></label></div>
    </div>
    <button type="submit" class="button button-primary">Issue Book →</button>
  </form>
</div>
<?php endif; ?>

<?php if ($canManage): ?>
<div id="addBookModal" style="display:none;position:fixed;inset:0;background:rgba(26,26,31,.5);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:var(--surface);border-radius:var(--radius-lg);width:100%;max-width:480px;box-shadow:var(--shadow-lg);padding:28px">
    <h3 style="margin-bottom:18px">Add Book</h3>
    <form method="post"><input type="hidden" name="tab" value="books"/><?= csrfField() ?><input type="hidden" name="action" value="add_book"/>
      <div class="form-row full"><div class="form-group"><label>Title *<input name="title" required/></label></div></div>
      <div class="form-row"><div class="form-group"><label>Author<input name="author"/></label></div><div class="form-group"><label>ISBN<input name="isbn"/></label></div></div>
      <div class="form-row"><div class="form-group"><label>Category<input name="category" placeholder="Reference, Fiction…"/></label></div><div class="form-group"><label>Copies<input type="number" name="copies" value="1" min="1"/></label></div></div>
      <div class="form-row"><div class="form-group"><label>Publisher<input name="publisher"/></label></div><div class="form-group"><label>Year<input type="number" name="year" placeholder="2024"/></label></div></div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:14px">
        <button type="button" class="button button-secondary" onclick="document.getElementById('addBookModal').style.display='none'">Cancel</button>
        <button type="submit" class="button button-primary">Add Book →</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
