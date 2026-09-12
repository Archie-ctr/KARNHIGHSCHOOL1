<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['librarian','principal','sys_admin','school_admin']);

$activePage = 'categories';
$pdo = db(); $user = currentUser(); $ayId = currentAcademicYearId(); $ay = currentAcademicYearName();

$librarian = null;
try { $s=$pdo->prepare("SELECT * FROM staff WHERE user_id=? LIMIT 1"); $s->execute([$user['id']]); $librarian=$s->fetch()?:null; } catch (Throwable $e) {}
if (!$librarian) $librarian=['first_name'=>$user['username']??'Librarian','last_name'=>'','id'=>0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $paction = $_POST['action'] ?? '';
    if ($paction === 'create') {
        try {
            $pdo->prepare("INSERT INTO book_categories (name,description) VALUES (?,?)")
                ->execute([trim($_POST['name']),trim($_POST['description']??'')]);
            flash('success','Category added.');
        } catch (Throwable $e) { flash('error','Failed: '.$e->getMessage()); }
        redirect(BASE_URL.'/portal/librarian/categories.php');
    }
    if ($paction === 'delete') {
        try {
            $pdo->prepare("DELETE FROM book_categories WHERE id=?")->execute([(int)$_POST['cat_id']]);
            flash('success','Category deleted.');
        } catch (Throwable $e) { flash('error','Cannot delete — books may reference this category.'); }
        redirect(BASE_URL.'/portal/librarian/categories.php');
    }
}

try {
    $cats = $pdo->query(
        "SELECT bc.*,(SELECT COUNT(*) FROM books b WHERE b.category_id=bc.id) book_count
         FROM book_categories bc ORDER BY bc.name"
    )->fetchAll();
} catch(Throwable $e){$cats=[];}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Categories — Library Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <div class="page-heading">
    <div><h1>Categories</h1><p>Book categories &amp; classification</p></div>
  </div>

  <?php foreach (getFlash() as $f): ?><div class="alert alert-<?= $f['type'] ?>"><?= e($f['message']) ?></div><?php endforeach; ?>

  <div style="display:grid;grid-template-columns:1fr 2fr;gap:16px">
    <!-- Add form -->
    <div class="panel" style="padding:20px">
      <h3 style="font-weight:700;margin-bottom:16px">Add Category</h3>
      <form method="post">
        <?= csrfField() ?><input type="hidden" name="action" value="create"/>
        <div class="form-group"><label>Name <span style="color:var(--error)">*</span></label><input type="text" name="name" required placeholder="e.g. Science Fiction"/></div>
        <div class="form-group"><label>Description</label><textarea name="description" rows="2" placeholder="Optional description…"></textarea></div>
        <button type="submit" class="button button-primary">+ Add Category</button>
      </form>
    </div>

    <!-- List -->
    <div class="panel" style="padding:20px">
      <h3 style="font-weight:700;margin-bottom:14px">All Categories (<?= count($cats) ?>)</h3>
      <?php if (empty($cats)): ?>
      <p style="color:var(--ink-faint)">No categories yet.</p>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Category</th><th>Description</th><th>Books</th><th>Action</th></tr></thead>
          <tbody>
            <?php foreach ($cats as $c): ?>
            <tr>
              <td><strong><?= e($c['name']) ?></strong></td>
              <td class="muted"><?= e(mb_substr($c['description']??'',0,50)) ?></td>
              <td><a href="books.php?cat=<?=$c['id']?>" style="font-weight:700;color:var(--primary)"><?= $c['book_count'] ?></a></td>
              <td>
                <?php if ($c['book_count'] == 0): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Delete this category?')">
                  <?=csrfField()?><input type="hidden" name="action" value="delete"/><input type="hidden" name="cat_id" value="<?=$c['id']?>"/>
                  <button class="button button-danger button-sm">Delete</button>
                </form>
                <?php else: ?>
                <span class="muted" style="font-size:12px">In use</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
