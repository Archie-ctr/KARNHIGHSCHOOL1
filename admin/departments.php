<?php
$pageTitle   = 'Departments & Houses';
$activeAdmin = 'departments';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['sys_admin','super_admin','school_admin','principal']);

$pdo = db();
$tab = $_GET['tab'] ?? 'departments';

// ── POST actions ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ─ Departments ─
    if ($action === 'add_dept') {
        $name  = trim($_POST['name'] ?? '');
        $code  = trim($_POST['code'] ?? '');
        $head  = (int)($_POST['head_teacher_id'] ?? 0) ?: null;
        $desc  = trim($_POST['description'] ?? '');
        if ($name) {
            try {
                $pdo->prepare("INSERT INTO departments (name,code,head_teacher_id,description) VALUES (?,?,?,?)")
                   ->execute([$name, $code ?: null, $head, $desc ?: null]);
                auditLog('create','departments','department',(int)$pdo->lastInsertId(),'','Created: '.$name);
                flash('success','Department created: '.$name);
            } catch (PDOException $e) {
                flash('error','Department name or code already exists.');
            }
        }
        redirect(BASE_URL.'/admin/departments.php?tab=departments');

    } elseif ($action === 'edit_dept') {
        $id   = (int)($_POST['dept_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $head = (int)($_POST['head_teacher_id'] ?? 0) ?: null;
        $desc = trim($_POST['description'] ?? '');
        if ($id && $name) {
            $pdo->prepare("UPDATE departments SET name=?,code=?,head_teacher_id=?,description=? WHERE id=?")
               ->execute([$name, $code ?: null, $head, $desc ?: null, $id]);
            auditLog('update','departments','department',$id,'','Updated: '.$name);
            flash('success','Department updated.');
        }
        redirect(BASE_URL.'/admin/departments.php?tab=departments');

    } elseif ($action === 'delete_dept') {
        $id = (int)($_POST['dept_id'] ?? 0);
        if ($id) {
            $pdo->prepare("DELETE FROM departments WHERE id=?")->execute([$id]);
            auditLog('delete','departments','department',$id);
            flash('success','Department deleted.');
        }
        redirect(BASE_URL.'/admin/departments.php?tab=departments');

    // ─ Houses ─
    } elseif ($action === 'add_house') {
        $name  = trim($_POST['name'] ?? '');
        $color = trim($_POST['color'] ?? '#888888');
        $motto = trim($_POST['motto'] ?? '');
        if ($name) {
            try {
                $pdo->prepare("INSERT INTO school_houses (name,color,motto) VALUES (?,?,?)")
                   ->execute([$name, $color, $motto ?: null]);
                flash('success','House created: '.$name);
            } catch (PDOException $e) {
                flash('error','House name already exists.');
            }
        }
        redirect(BASE_URL.'/admin/departments.php?tab=houses');

    } elseif ($action === 'edit_house') {
        $id    = (int)($_POST['house_id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $color = trim($_POST['color'] ?? '#888888');
        $motto = trim($_POST['motto'] ?? '');
        if ($id && $name) {
            $pdo->prepare("UPDATE school_houses SET name=?,color=?,motto=? WHERE id=?")->execute([$name,$color,$motto?:null,$id]);
            flash('success','House updated.');
        }
        redirect(BASE_URL.'/admin/departments.php?tab=houses');

    } elseif ($action === 'delete_house') {
        $id = (int)($_POST['house_id'] ?? 0);
        if ($id) {
            $pdo->prepare("DELETE FROM school_houses WHERE id=?")->execute([$id]);
            flash('success','House deleted.');
        }
        redirect(BASE_URL.'/admin/departments.php?tab=houses');
    }
}

// ── Ensure tables exist ───────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS departments (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name            VARCHAR(100) NOT NULL UNIQUE,
        code            VARCHAR(20)  NULL UNIQUE,
        head_teacher_id INT UNSIGNED NULL,
        description     TEXT         NULL,
        is_active       TINYINT(1)   NOT NULL DEFAULT 1,
        created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS school_houses (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(60)  NOT NULL UNIQUE,
        color      VARCHAR(20)  NOT NULL DEFAULT '#888888',
        motto      VARCHAR(200) NULL,
        created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add house_id to students if missing
    $cols = $pdo->query("SHOW COLUMNS FROM students LIKE 'house_id'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE students ADD COLUMN house_id INT UNSIGNED NULL AFTER current_class_id");
    }
    // Add department_id to teachers if missing
    $cols2 = $pdo->query("SHOW COLUMNS FROM teachers LIKE 'department_id'")->fetchAll();
    if (empty($cols2)) {
        $pdo->exec("ALTER TABLE teachers ADD COLUMN department_id INT UNSIGNED NULL");
    }
} catch (Throwable $e) { /* tables may already exist */ }

// ── Load data ─────────────────────────────────────────────────
$departments = $pdo->query(
    "SELECT d.*, CONCAT(u.first_name,' ',u.last_name) head_name,
            (SELECT COUNT(*) FROM teachers t WHERE t.department_id=d.id) teacher_count
     FROM departments d
     LEFT JOIN teachers t2  ON t2.id = d.head_teacher_id
     LEFT JOIN users    u   ON u.id  = t2.user_id
     ORDER BY d.name"
)->fetchAll();

$houses = $pdo->query(
    "SELECT h.*,
            (SELECT COUNT(*) FROM students s WHERE s.house_id=h.id AND s.status='Active') student_count
     FROM school_houses h ORDER BY h.name"
)->fetchAll();

$teachers = $pdo->query(
    "SELECT t.id, CONCAT(u.first_name,' ',u.last_name) name
     FROM teachers t JOIN users u ON u.id=t.user_id
     WHERE t.status='Active' ORDER BY u.first_name"
)->fetchAll();
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">School Structure <span></span></div>
    <h1>Departments &amp; Houses</h1>
    <p>Manage school departments, heads of department and student houses.</p>
  </div>
</div>

<div class="tab-bar" style="margin-bottom:16px">
  <a href="?tab=departments" class="tab-btn <?= $tab==='departments'?'active':'' ?>">🏢 Departments (<?= count($departments) ?>)</a>
  <a href="?tab=houses"      class="tab-btn <?= $tab==='houses'     ?'active':'' ?>">🏠 Student Houses (<?= count($houses) ?>)</a>
</div>

<?php if ($tab === 'departments'): ?>
<!-- ── DEPARTMENTS ──────────────────────────────────────────── -->
<div style="display:flex;justify-content:flex-end;margin-bottom:14px">
  <button class="button button-primary" onclick="document.getElementById('addDeptModal').style.display='flex'">+ Add Department</button>
</div>

<?php if (empty($departments)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">🏢</div>
  <p style="color:var(--ink-soft)">No departments yet. Add your first department above.</p>
</div>
<?php else: ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Department</th><th>Code</th><th>Head of Department</th><th>Teachers</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($departments as $d): ?>
      <tr>
        <td>
          <strong><?= e($d['name']) ?></strong>
          <?php if ($d['description']): ?>
          <div style="font-size:11.5px;color:var(--ink-soft);margin-top:2px"><?= e(mb_substr($d['description'],0,60)) ?></div>
          <?php endif; ?>
        </td>
        <td class="muted"><?= e($d['code'] ?? '—') ?></td>
        <td><?= e($d['head_name'] ?? '—') ?></td>
        <td><span style="font-weight:700"><?= $d['teacher_count'] ?></span></td>
        <td><span class="status <?= $d['is_active']?'approved':'warning' ?>"><?= $d['is_active']?'Active':'Inactive' ?></span></td>
        <td>
          <div style="display:flex;gap:6px">
            <button class="filter-button button-sm"
              onclick="editDept(<?= htmlspecialchars(json_encode($d)) ?>)">✏️ Edit</button>
            <form method="post" onsubmit="return confirm('Delete this department?')" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="action"  value="delete_dept"/>
              <input type="hidden" name="dept_id" value="<?= $d['id'] ?>"/>
              <button type="submit" class="filter-button button-sm" style="color:var(--error)">🗑️ Delete</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php elseif ($tab === 'houses'): ?>
<!-- ── STUDENT HOUSES ───────────────────────────────────────── -->
<div style="display:flex;justify-content:flex-end;margin-bottom:14px">
  <button class="button button-primary" onclick="document.getElementById('addHouseModal').style.display='flex'">+ Add House</button>
</div>

<?php if (empty($houses)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">🏠</div>
  <p style="color:var(--ink-soft)">No student houses yet. Add your first house above.</p>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px">
  <?php foreach ($houses as $h): ?>
  <div class="panel" style="padding:22px;border-top:4px solid <?= e($h['color']) ?>">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
      <div style="width:40px;height:40px;border-radius:50%;background:<?= e($h['color']) ?>;flex-shrink:0"></div>
      <div>
        <h3 style="font-size:15px;font-weight:700"><?= e($h['name']) ?></h3>
        <span style="font-size:12px;color:var(--ink-soft)"><?= $h['student_count'] ?> student<?= $h['student_count']!==1?'s':'' ?></span>
      </div>
    </div>
    <?php if ($h['motto']): ?>
    <p style="font-size:12px;color:var(--ink-soft);font-style:italic;margin-bottom:12px">"<?= e($h['motto']) ?>"</p>
    <?php endif; ?>
    <div style="display:flex;gap:6px">
      <button class="filter-button button-sm"
        onclick="editHouse(<?= htmlspecialchars(json_encode($h)) ?>)">✏️ Edit</button>
      <form method="post" onsubmit="return confirm('Delete this house?')" style="display:inline">
        <?= csrfField() ?>
        <input type="hidden" name="action"   value="delete_house"/>
        <input type="hidden" name="house_id" value="<?= $h['id'] ?>"/>
        <button type="submit" class="filter-button button-sm" style="color:var(--error)">🗑️ Delete</button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- ── Add Department Modal ───────────────────────────────────── -->
<div id="addDeptModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:var(--radius-lg);width:100%;max-width:480px;padding:28px;box-shadow:var(--shadow-lg)">
    <h3 style="margin-bottom:18px">Add Department</h3>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="add_dept"/>
      <div class="form-row"><div class="form-group"><label>Department Name *<input name="name" required placeholder="e.g. Mathematics Department"/></label></div></div>
      <div class="form-row"><div class="form-group"><label>Department Code<input name="code" placeholder="e.g. MATH"/></label></div></div>
      <div class="form-group"><label>Head of Department<select name="head_teacher_id"><option value="">— Select teacher —</option><?php foreach($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></label></div>
      <div class="form-group"><label>Description<textarea name="description" rows="2" placeholder="Optional"></textarea></label></div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px">
        <button type="button" onclick="this.closest('[id]').style.display='none'" class="button button-secondary">Cancel</button>
        <button type="submit" class="button button-primary">Create Department</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Edit Department Modal ─────────────────────────────────── -->
<div id="editDeptModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:var(--radius-lg);width:100%;max-width:480px;padding:28px;box-shadow:var(--shadow-lg)">
    <h3 style="margin-bottom:18px">Edit Department</h3>
    <form method="post" id="editDeptForm">
      <?= csrfField() ?><input type="hidden" name="action" value="edit_dept"/>
      <input type="hidden" name="dept_id" id="editDeptId"/>
      <div class="form-group"><label>Department Name *<input name="name" id="editDeptName" required/></label></div>
      <div class="form-group"><label>Code<input name="code" id="editDeptCode"/></label></div>
      <div class="form-group"><label>Head of Department<select name="head_teacher_id" id="editDeptHead"><option value="">— Select teacher —</option><?php foreach($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></label></div>
      <div class="form-group"><label>Description<textarea name="description" id="editDeptDesc" rows="2"></textarea></label></div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px">
        <button type="button" onclick="document.getElementById('editDeptModal').style.display='none'" class="button button-secondary">Cancel</button>
        <button type="submit" class="button button-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Add House Modal ────────────────────────────────────────── -->
<div id="addHouseModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:var(--radius-lg);width:100%;max-width:420px;padding:28px;box-shadow:var(--shadow-lg)">
    <h3 style="margin-bottom:18px">Add Student House</h3>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="add_house"/>
      <div class="form-group"><label>House Name *<input name="name" required placeholder="e.g. Nelson Mandela House"/></label></div>
      <div class="form-row">
        <div class="form-group"><label>House Color<input type="color" name="color" value="#ac1f3b"/></label></div>
        <div class="form-group"><label>Motto<input name="motto" placeholder="Optional"/></label></div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px">
        <button type="button" onclick="this.closest('[id]').style.display='none'" class="button button-secondary">Cancel</button>
        <button type="submit" class="button button-primary">Create House</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Edit House Modal ───────────────────────────────────────── -->
<div id="editHouseModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:var(--radius-lg);width:100%;max-width:420px;padding:28px;box-shadow:var(--shadow-lg)">
    <h3 style="margin-bottom:18px">Edit House</h3>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="edit_house"/>
      <input type="hidden" name="house_id" id="editHouseId"/>
      <div class="form-group"><label>House Name *<input name="name" id="editHouseName" required/></label></div>
      <div class="form-row">
        <div class="form-group"><label>Color<input type="color" name="color" id="editHouseColor"/></label></div>
        <div class="form-group"><label>Motto<input name="motto" id="editHouseMotto"/></label></div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px">
        <button type="button" onclick="document.getElementById('editHouseModal').style.display='none'" class="button button-secondary">Cancel</button>
        <button type="submit" class="button button-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function editDept(d) {
  document.getElementById('editDeptId').value   = d.id;
  document.getElementById('editDeptName').value  = d.name;
  document.getElementById('editDeptCode').value  = d.code || '';
  document.getElementById('editDeptHead').value  = d.head_teacher_id || '';
  document.getElementById('editDeptDesc').value  = d.description || '';
  document.getElementById('editDeptModal').style.display = 'flex';
}
function editHouse(h) {
  document.getElementById('editHouseId').value    = h.id;
  document.getElementById('editHouseName').value  = h.name;
  document.getElementById('editHouseColor').value = h.color;
  document.getElementById('editHouseMotto').value = h.motto || '';
  document.getElementById('editHouseModal').style.display = 'flex';
}
// Close modals on backdrop click
document.querySelectorAll('[id$="Modal"]').forEach(m => {
  m.addEventListener('click', e => { if(e.target===m) m.style.display='none'; });
});
</script>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
