<?php
$pageTitle   = 'Fee Structures';
$activeAdmin = 'finance';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['accountant','principal','super_admin']);

$pdo=$db=db(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action=$_POST['action']??'';
    if ($action==='add') {
        $pdo->prepare("INSERT INTO fee_structures (academic_year_id,grade_id,fee_type,amount,currency,due_date,description,is_mandatory,is_active) VALUES (?,?,?,?,?,?,?,?,1)")
           ->execute([$ayId,$_POST['grade_id']?:null,$_POST['fee_type'],$_POST['amount'],$_POST['currency']??'LRD',$_POST['due_date']?:null,trim($_POST['description']??'')?:null,$_POST['is_mandatory']??1]);
        flash('success','Fee structure added.');
    } elseif ($action==='delete') { $pdo->prepare("DELETE FROM fee_structures WHERE id=?")->execute([(int)($_POST['fee_id']??0)]); flash('success','Deleted.'); }
    redirect(BASE_URL.'/admin/fee_structures.php');
}

$fees   = $pdo->prepare("SELECT f.*,g.name grade_name FROM fee_structures f LEFT JOIN grades g ON g.id=f.grade_id WHERE f.academic_year_id=? ORDER BY f.fee_type,g.sequence")->execute([$ayId]) ? $pdo->query("SELECT f.*,g.name grade_name FROM fee_structures f LEFT JOIN grades g ON g.id=f.grade_id WHERE f.academic_year_id=$ayId ORDER BY f.fee_type,g.sequence")->fetchAll() : [];
$grades = $pdo->query("SELECT id,name FROM grades WHERE is_active=1 ORDER BY sequence")->fetchAll();
?>
<div class="page-heading"><div><a href="<?= BASE_URL ?>/admin/finance.php" class="text-link" style="margin-bottom:8px;display:flex">← Finance</a><div class="eyebrow">Finance <span></span></div><h1>Fee Structures</h1><p><?= e($ay) ?></p></div><button class="button button-primary" onclick="document.getElementById('addFeeModal').style.display='flex'">+ Add Fee</button></div>
<div class="table-wrap">
  <table>
    <thead><tr><th>Fee Type</th><th>Grade</th><th>Amount</th><th>Due Date</th><th>Mandatory</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($fees as $f): ?>
      <tr>
        <td><strong><?= e($f['fee_type']) ?></strong><?= $f['description']?'<div style="font-size:11px;color:var(--ink-faint)">'.e($f['description']).'</div>':'' ?></td>
        <td><?= $f['grade_name'] ? e($f['grade_name']) : '<span class="muted">All grades</span>' ?></td>
        <td><strong><?= e($f['currency']) ?> <?= number_format($f['amount'],2) ?></strong></td>
        <td class="muted"><?= $f['due_date'] ? date('M d, Y',strtotime($f['due_date'])) : '—' ?></td>
        <td><?= $f['is_mandatory'] ? '<span class="status approved">Yes</span>' : '<span class="status pending">No</span>' ?></td>
        <td><form method="post" onsubmit="return confirm('Delete?')" style="display:inline"><?= csrfField() ?><input type="hidden" name="action" value="delete"/><input type="hidden" name="fee_id" value="<?= $f['id'] ?>"/><button type="submit" class="filter-button button-sm" style="color:var(--error)">Delete</button></form></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<div id="addFeeModal" style="display:none;position:fixed;inset:0;background:rgba(26,26,31,.5);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:var(--surface);border-radius:var(--radius-lg);width:100%;max-width:480px;box-shadow:var(--shadow-lg);padding:28px">
    <h3 style="margin-bottom:18px">Add Fee Structure</h3>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="add"/>
      <div class="form-row"><div class="form-group"><label>Fee Type *<input name="fee_type" required placeholder="e.g. Tuition"/></label></div><div class="form-group"><label>Grade (blank = all)<select name="grade_id"><option value="">All grades</option><?php foreach($grades as $g): ?><option value="<?= $g['id'] ?>"><?= e($g['name']) ?></option><?php endforeach; ?></select></label></div></div>
      <div class="form-row"><div class="form-group"><label>Amount *<input type="number" name="amount" required min="0" step="0.01"/></label></div><div class="form-group"><label>Currency<select name="currency"><option value="LRD">LRD</option><option value="USD">USD</option></select></label></div></div>
      <div class="form-row"><div class="form-group"><label>Due Date<input type="date" name="due_date"/></label></div><div class="form-group"><label>Mandatory<select name="is_mandatory"><option value="1">Yes</option><option value="0">No</option></select></label></div></div>
      <div class="form-row full"><div class="form-group"><label>Description<input name="description" placeholder="Optional"/></label></div></div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px"><button type="button" class="button button-secondary" onclick="document.getElementById('addFeeModal').style.display='none'">Cancel</button><button type="submit" class="button button-primary">Add →</button></div>
    </form>
  </div>
</div>
<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
