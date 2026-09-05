<?php
$pageTitle   = 'Subjects';
$activeAdmin = 'subjects';
require_once dirname(__DIR__).'/includes/admin_header.php';

$pdo = db();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action = $_POST['action']??'';
    if ($action==='add') {
        $name = trim($_POST['name']??'');
        $code = trim($_POST['code']??'');
        if ($name) { $pdo->prepare("INSERT INTO subjects (code,name,short_name,category,is_active) VALUES (?,?,?,?,1)")->execute([$code?:null,$name,trim($_POST['short_name']??'')?:null,$_POST['category']??'core']); flash('success','Subject added.'); }
    } elseif ($action==='toggle') {
        $id=(int)($_POST['sub_id']??0);
        if ($id) { $pdo->prepare("UPDATE subjects SET is_active=NOT is_active WHERE id=?")->execute([$id]); }
    } elseif ($action==='delete') {
        $id=(int)($_POST['sub_id']??0);
        if ($id) { $pdo->prepare("DELETE FROM subjects WHERE id=?")->execute([$id]); flash('success','Subject deleted.'); }
    }
    redirect(BASE_URL.'/admin/subjects.php');
}

$subjects = $pdo->query("SELECT * FROM subjects ORDER BY category,name")->fetchAll();
?>

<div class="page-heading">
  <div><div class="eyebrow">Academics <span></span></div><h1>Subjects</h1></div>
  <button class="button button-primary" onclick="document.getElementById('addSubModal').style.display='flex'">+ Add Subject</button>
</div>

<div class="table-wrap">
  <table>
    <thead><tr><th>Code</th><th>Subject Name</th><th>Short Name</th><th>Category</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($subjects as $s): ?>
      <tr style="<?= $s['is_active']?'':'opacity:.5' ?>">
        <td class="muted"><?= e($s['code']??'—') ?></td>
        <td><strong><?= e($s['name']) ?></strong></td>
        <td class="muted"><?= e($s['short_name']??'—') ?></td>
        <td><span class="status <?= $s['category']==='core'?'approved':($s['category']==='elective'?'new-s':'pending') ?>"><?= e(ucfirst($s['category'])) ?></span></td>
        <td><?= statusBadge($s['is_active']?'Active':'Inactive') ?></td>
        <td>
          <div style="display:flex;gap:5px">
            <form method="post" style="display:inline"><?= csrfField() ?><input type="hidden" name="action" value="toggle"/><input type="hidden" name="sub_id" value="<?= $s['id'] ?>"/><button type="submit" class="filter-button button-sm"><?= $s['is_active']?'Deactivate':'Activate' ?></button></form>
            <form method="post" onsubmit="return confirm('Delete subject?')" style="display:inline"><?= csrfField() ?><input type="hidden" name="action" value="delete"/><input type="hidden" name="sub_id" value="<?= $s['id'] ?>"/><button type="submit" class="filter-button button-sm" style="color:var(--error)">✕</button></form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div id="addSubModal" style="display:none;position:fixed;inset:0;background:rgba(26,26,31,.5);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:var(--surface);border-radius:var(--radius-lg);width:100%;max-width:440px;box-shadow:var(--shadow-lg);padding:28px">
    <h3 style="margin-bottom:18px">Add Subject</h3>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="add"/>
      <div class="form-row full"><div class="form-group"><label>Subject Name *<input name="name" required placeholder="e.g. Mathematics"/></label></div></div>
      <div class="form-row">
        <div class="form-group"><label>Code<input name="code" placeholder="MAT"/></label></div>
        <div class="form-group"><label>Short Name<input name="short_name" placeholder="Math"/></label></div>
      </div>
      <div class="form-row full"><div class="form-group"><label>Category<select name="category"><option value="core">Core</option><option value="elective">Elective</option><option value="extracurricular">Extracurricular</option></select></label></div></div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px">
        <button type="button" class="button button-secondary" onclick="document.getElementById('addSubModal').style.display='none'">Cancel</button>
        <button type="submit" class="button button-primary">Add →</button>
      </div>
    </form>
  </div>
</div>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
