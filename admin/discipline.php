<?php
require_once dirname(__DIR__).'/config/db.php';
requireAuth();
requireRole(['sys_admin','school_admin','principal','vice_principal','registrar','class_teacher','discipline_officer']);

$pdo = db();

$canAdd    = can('manage_discipline');  // discipline_officer, vice_principal, principal, school_admin, sys_admin
$canResolve= can('manage_discipline');
$canView   = can('view_discipline') || can('manage_discipline'); // registrar, class_teacher also

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' && $canAdd) {
        $pdo->prepare("INSERT INTO discipline_records (student_id,incident_date,category,description,action_taken,details,recorded_by)
            VALUES (?,?,?,?,?,?,?)")
           ->execute([(int)$_POST['student_id'],$_POST['incident_date'],$_POST['category'],
                      $_POST['description'],$_POST['action_taken'],
                      trim($_POST['details']??'')?:null,currentUser()['id']]);
        auditLog('create','discipline','discipline_record',(int)$pdo->lastInsertId());
        flash('success','Discipline record added.');
    } elseif ($action === 'resolve' && $canResolve) {
        $id = (int)($_POST['rec_id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE discipline_records SET resolved=1 WHERE id=?")->execute([$id]);
            auditLog('resolve','discipline','discipline_record',$id);
            flash('success','Marked as resolved.');
        }
    } elseif ($action === 'delete' && can('manage_discipline') && isPrincipal()) {
        $id = (int)($_POST['rec_id'] ?? 0);
        if ($id) {
            $pdo->prepare("DELETE FROM discipline_records WHERE id=?")->execute([$id]);
            flash('success','Record deleted.');
        }
    } else {
        flash('error','You do not have permission to perform that action.');
    }
    redirect(BASE_URL.'/admin/discipline.php?'.http_build_query(array_filter(['q'=>$_GET['q']??''])));
}

// Use parameterized query — no addslashes
$q    = trim($_GET['q'] ?? '');
$page = max(1,(int)($_GET['page'] ?? 1));
$per  = 25;
$where=[]; $params=[];
if ($q) {
    $where[]="(CONCAT(s.first_name,' ',s.last_name) LIKE ? OR d.category LIKE ? OR d.action_taken LIKE ?)";
    $like="%$q%"; array_push($params,$like,$like,$like);
}
$wsql=$where?'WHERE '.implode(' AND ',$where):'';
$cnt=$pdo->prepare("SELECT COUNT(*) FROM discipline_records d JOIN students s ON s.id=d.student_id $wsql"); $cnt->execute($params); $total=(int)$cnt->fetchColumn();
$pg=paginate($total,$per,$page);
$recs=$pdo->prepare("SELECT d.*,CONCAT(s.first_name,' ',s.last_name) sname,s.student_id sid,g.name grade_name FROM discipline_records d JOIN students s ON s.id=d.student_id LEFT JOIN grades g ON g.id=s.current_grade_id $wsql ORDER BY d.incident_date DESC LIMIT $per OFFSET {$pg['offset']}");
$recs->execute($params); $records=$recs->fetchAll();

$students=$pdo->query("SELECT id,student_id,CONCAT(first_name,' ',last_name) name FROM students WHERE status='Active' ORDER BY first_name")->fetchAll();

$pageTitle   = 'Discipline';
$activeAdmin = 'discipline';
require_once dirname(__DIR__).'/includes/admin_header.php';
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">Discipline <span></span></div>
    <h1>Discipline Records</h1>
    <p><?= $canAdd ? 'Manage student discipline incidents.' : 'View discipline records.' ?></p>
  </div>
  <?php if ($canAdd): ?>
  <button class="button button-primary" onclick="document.getElementById('addDiscModal').style.display='flex'">+ Add Record</button>
  <?php endif; ?>
</div>

<form method="get" class="filter-row" style="margin-bottom:14px">
  <div class="table-search">🔍<input type="search" name="q" placeholder="Student name, category, action…" value="<?= e($q) ?>"/></div>
  <button type="submit" class="button button-primary button-sm">Search</button>
  <?php if ($q): ?><a href="<?= BASE_URL ?>/admin/discipline.php" class="filter-button">Clear</a><?php endif; ?>
</form>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Student</th><th>Grade</th><th>Date</th><th>Category</th>
        <th>Description</th><th>Action Taken</th><th>Status</th>
        <?php if ($canResolve || isPrincipal()): ?><th>Actions</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($records)): ?>
      <tr><td colspan="8" style="text-align:center;padding:32px;color:var(--ink-faint)">No records found.</td></tr>
      <?php else: ?>
      <?php foreach ($records as $r): ?>
      <tr>
        <td><strong><?= e($r['sname']) ?></strong><div style="font-size:11px;color:var(--ink-faint)"><?= e($r['sid']) ?></div></td>
        <td class="muted"><?= e($r['grade_name'] ?? '—') ?></td>
        <td class="muted"><?= date('M d, Y', strtotime($r['incident_date'])) ?></td>
        <td><span class="status pending"><?= e($r['category']) ?></span></td>
        <td style="max-width:220px;white-space:normal;font-size:13px"><?= e(mb_substr($r['description'],0,80)).(mb_strlen($r['description'])>80?'…':'') ?></td>
        <td><span class="status <?= $r['action_taken']==='Suspension'?'warning':'new-s' ?>"><?= e($r['action_taken']) ?></span></td>
        <td><?= $r['resolved'] ? '<span class="status approved">Resolved</span>' : '<span class="status warning">Open</span>' ?></td>
        <?php if ($canResolve || isPrincipal()): ?>
        <td>
          <div style="display:flex;gap:5px;flex-wrap:wrap">
            <?php if (!$r['resolved'] && $canResolve): ?>
            <form method="post" style="display:inline">
              <?= csrfField() ?><input type="hidden" name="action" value="resolve"/><input type="hidden" name="rec_id" value="<?= $r['id'] ?>"/>
              <button type="submit" class="filter-button button-sm" style="color:var(--green)">✓ Resolve</button>
            </form>
            <?php endif; ?>
            <?php if (isPrincipal()): ?>
            <form method="post" onsubmit="return confirm('Delete this record?')" style="display:inline">
              <?= csrfField() ?><input type="hidden" name="action" value="delete"/><input type="hidden" name="rec_id" value="<?= $r['id'] ?>"/>
              <button type="submit" class="filter-button button-sm" style="color:var(--error)">✕</button>
            </form>
            <?php endif; ?>
          </div>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($pg['pages']>1): ?>
<div class="pagination">
  <?php if ($pg['page']>1): ?><a href="?page=<?=$pg['page']-1?>&q=<?=urlencode($q)?>">&laquo;</a><?php endif; ?>
  <?php for($p=max(1,$pg['page']-2);$p<=min($pg['pages'],$pg['page']+2);$p++): ?>
    <?php if($p===$pg['page']): ?><span class="current"><?=$p?></span><?php else: ?><a href="?page=<?=$p?>&q=<?=urlencode($q)?>"><?=$p?></a><?php endif; ?>
  <?php endfor; ?>
  <?php if ($pg['page']<$pg['pages']): ?><a href="?page=<?=$pg['page']+1?>&q=<?=urlencode($q)?>">&raquo;</a><?php endif; ?>
</div>
<?php endif; ?>

<?php if ($canAdd): ?>
<div id="addDiscModal" style="display:none;position:fixed;inset:0;background:rgba(26,26,31,.5);z-index:200;align-items:center;justify-content:center;padding:20px;overflow-y:auto">
  <div style="background:var(--surface);border-radius:var(--radius-lg);width:100%;max-width:500px;box-shadow:var(--shadow-lg);padding:28px">
    <h3 style="margin-bottom:18px">Add Discipline Record</h3>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="add"/>
      <div class="form-row full"><div class="form-group"><label>Student *<select name="student_id" required><option value="">Select…</option><?php foreach($students as $s): ?><option value="<?=$s['id']?>"><?=e($s['name'].' ('.$s['student_id'].')')?></option><?php endforeach; ?></select></label></div></div>
      <div class="form-row">
        <div class="form-group"><label>Incident Date *<input type="date" name="incident_date" required value="<?= date('Y-m-d') ?>"/></label></div>
        <div class="form-group"><label>Category *<select name="category" required><option>Misconduct</option><option>Absence</option><option>Cheating</option><option>Bullying</option><option>Vandalism</option><option>Other</option></select></label></div>
      </div>
      <div class="form-row full"><div class="form-group"><label>Description *<textarea name="description" rows="3" required></textarea></label></div></div>
      <div class="form-row full"><div class="form-group"><label>Action Taken *<select name="action_taken" required><option>Warning</option><option>Suspension</option><option>Counseling</option><option>Parent Meeting</option><option>Community Service</option><option>Other</option></select></label></div></div>
      <div class="form-row full"><div class="form-group"><label>Additional Details<textarea name="details" rows="2"></textarea></label></div></div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:14px">
        <button type="button" class="button button-secondary" onclick="document.getElementById('addDiscModal').style.display='none'">Cancel</button>
        <button type="submit" class="button button-primary">Save Record →</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
