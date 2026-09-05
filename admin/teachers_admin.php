<?php
require_once dirname(__DIR__).'/config/db.php';
requireAuth();
requireRole(['sys_admin','school_admin','principal','vice_principal','registrar']);

$pdo = db();

$canAdd    = can('manage_teachers');  // school_admin, principal, sys_admin
$canStatus = can('manage_teachers');
$canView   = can('view_teachers');    // vice_principal, registrar also

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' && $canAdd) {
        $tid = generateTeacherId();
        $pdo->prepare("INSERT INTO teachers (teacher_id,first_name,last_name,gender,phone,email,qualification,specialization,employment_date,status)
            VALUES (?,?,?,?,?,?,?,?,?,?)")
           ->execute([$tid,trim($_POST['first_name']),trim($_POST['last_name']),
                      $_POST['gender']??'Male',trim($_POST['phone']??'')?:null,
                      trim($_POST['email']??'')?:null,trim($_POST['qualification']??'')?:null,
                      trim($_POST['specialization']??'')?:null,$_POST['employment_date']?:null,'Active']);
        // Create user account for teacher
        if (trim($_POST['email']??'')) {
            $hash = password_hash('1234', PASSWORD_BCRYPT);
            $roleId = (int)$pdo->query("SELECT id FROM roles WHERE name='teacher' LIMIT 1")->fetchColumn();
            try {
                $pdo->prepare("INSERT INTO users (name,email,password_hash,role_id) VALUES (?,?,?,?)")
                   ->execute([trim($_POST['first_name']).' '.trim($_POST['last_name']),trim($_POST['email']),$hash,$roleId]);
                $uid=(int)$pdo->lastInsertId();
                $pdo->prepare("UPDATE teachers SET user_id=? WHERE teacher_id=?")->execute([$uid,$tid]);
            } catch (PDOException $e) { /* email already in use — skip */ }
        }
        auditLog('create','teachers','teacher',(int)$pdo->lastInsertId(),'','Teacher ID: '.$tid);
        flash('success','Teacher added. ID: '.$tid.'. Default password: 1234');

    } elseif ($action === 'status' && $canStatus) {
        $id = (int)($_POST['teacher_id'] ?? 0);
        $st = $_POST['status'] ?? '';
        if ($id && in_array($st,['Active','Inactive','On Leave'],true)) {
            $pdo->prepare("UPDATE teachers SET status=? WHERE id=?")->execute([$st,$id]);
        }
    } elseif ($action === 'delete' && isPrincipal()) {
        $id = (int)($_POST['teacher_id'] ?? 0);
        if ($id) { $pdo->prepare("DELETE FROM teachers WHERE id=?")->execute([$id]); flash('success','Teacher removed.'); }
    } else {
        flash('error','You do not have permission to perform that action.');
    }
    redirect(BASE_URL.'/admin/teachers_admin.php?'.http_build_query(array_filter(['q'=>$_GET['q']??''])));
}

$q    = trim($_GET['q'] ?? '');
$where=[]; $params=[];
if ($q) { $where[]='(first_name LIKE ? OR last_name LIKE ? OR teacher_id LIKE ? OR specialization LIKE ?)'; $like="%$q%"; array_push($params,$like,$like,$like,$like); }
$wsql=$where?'WHERE '.implode(' AND ',$where):'';
$stmt=$pdo->prepare("SELECT * FROM teachers $wsql ORDER BY first_name"); $stmt->execute($params); $teachers=$stmt->fetchAll();

$pageTitle   = 'Teachers';
$activeAdmin = 'teachers';
require_once dirname(__DIR__).'/includes/admin_header.php';
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">Staff <span></span></div>
    <h1>Teachers</h1>
    <p><?= $canAdd ? 'Manage teaching staff records.' : 'View teaching staff.' ?></p>
  </div>
  <?php if ($canAdd): ?>
  <button class="button button-primary" onclick="document.getElementById('addTchrModal').style.display='flex'">+ Add Teacher</button>
  <?php endif; ?>
</div>

<form method="get" class="filter-row" style="margin-bottom:14px">
  <div class="table-search">🔍<input type="search" name="q" placeholder="Name, ID, specialization…" value="<?= e($q) ?>"/></div>
  <button type="submit" class="button button-primary button-sm">Search</button>
  <?php if ($q): ?><a href="<?= BASE_URL ?>/admin/teachers_admin.php" class="filter-button">Clear</a><?php endif; ?>
</form>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Teacher</th><th>ID</th><th>Specialization</th><th>Phone</th>
        <th>Qualification</th><th>Status</th>
        <?php if ($canStatus || isPrincipal()): ?><th>Actions</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($teachers)): ?>
      <tr><td colspan="7" style="text-align:center;padding:28px;color:var(--ink-faint)">No teachers found.</td></tr>
      <?php else: ?>
      <?php foreach ($teachers as $t):
        $ini = strtoupper(substr($t['first_name'],0,1).substr($t['last_name'],0,1));
      ?>
      <tr>
        <td><div class="person"><div class="avatar-sm" style="background:var(--accent-soft);color:var(--accent)"><?= e($ini) ?></div><strong><?= e($t['first_name'].' '.$t['last_name']) ?></strong></div></td>
        <td class="muted"><?= e($t['teacher_id']) ?></td>
        <td><?= e($t['specialization'] ?? '—') ?></td>
        <td class="muted"><?= e($t['phone'] ?? '—') ?></td>
        <td class="muted"><?= e($t['qualification'] ?? '—') ?></td>
        <td><?= statusBadge($t['status']) ?></td>
        <?php if ($canStatus || isPrincipal()): ?>
        <td>
          <div style="display:flex;gap:5px;align-items:center">
            <?php if ($canStatus): ?>
            <form method="post" style="display:inline">
              <?= csrfField() ?><input type="hidden" name="action" value="status"/><input type="hidden" name="teacher_id" value="<?= $t['id'] ?>"/>
              <select name="status" class="filter-button" style="padding:5px 8px;font-size:12px" onchange="this.form.submit()">
                <option <?= $t['status']==='Active'?'selected':'' ?>>Active</option>
                <option <?= $t['status']==='Inactive'?'selected':'' ?>>Inactive</option>
                <option <?= $t['status']==='On Leave'?'selected':'' ?>>On Leave</option>
              </select>
            </form>
            <?php endif; ?>
            <?php if (isPrincipal()): ?>
            <form method="post" onsubmit="return confirm('Remove this teacher?')" style="display:inline">
              <?= csrfField() ?><input type="hidden" name="action" value="delete"/><input type="hidden" name="teacher_id" value="<?= $t['id'] ?>"/>
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

<?php if ($canAdd): ?>
<div id="addTchrModal" style="display:none;position:fixed;inset:0;background:rgba(26,26,31,.5);z-index:200;align-items:center;justify-content:center;padding:20px;overflow-y:auto">
  <div style="background:var(--surface);border-radius:var(--radius-lg);width:100%;max-width:520px;box-shadow:var(--shadow-lg);padding:28px">
    <h3 style="margin-bottom:18px">Add Teacher</h3>
    <p style="font-size:13px;color:var(--ink-soft);margin-bottom:16px">A portal account will be created automatically if an email is provided (default password: <strong>1234</strong>).</p>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="add"/>
      <div class="form-row">
        <div class="form-group"><label>First Name *<input name="first_name" required/></label></div>
        <div class="form-group"><label>Last Name *<input name="last_name" required/></label></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Gender<select name="gender"><option value="Male">Male</option><option value="Female">Female</option></select></label></div>
        <div class="form-group"><label>Phone<input name="phone" placeholder="+231 …"/></label></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Email (for portal login)<input type="email" name="email"/></label></div>
        <div class="form-group"><label>Employment Date<input type="date" name="employment_date"/></label></div>
      </div>
      <div class="form-row full"><div class="form-group"><label>Specialization<input name="specialization" placeholder="e.g. Mathematics"/></label></div></div>
      <div class="form-row full"><div class="form-group"><label>Qualification<input name="qualification" placeholder="e.g. B.Ed. Mathematics"/></label></div></div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:14px">
        <button type="button" class="button button-secondary" onclick="document.getElementById('addTchrModal').style.display='none'">Cancel</button>
        <button type="submit" class="button button-primary">Add Teacher →</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
