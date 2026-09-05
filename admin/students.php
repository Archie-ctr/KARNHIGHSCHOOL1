<?php
// ── POST processing before any output ────────────────────────
require_once dirname(__DIR__).'/config/db.php';
requireAuth(); requireStaff(); // all staff can reach the page

$pdo  = db();
$ayId = currentAcademicYearId();

// Define what this role can DO
$canAdd    = can('add_student');
$canEdit   = can('edit_student');
$canDelete = can('delete_student');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add' && $canAdd) {
        $fn = trim($_POST['first_name'] ?? '');
        $ln = trim($_POST['last_name']  ?? '');
        $gr = (int)($_POST['grade_id']  ?? 0);
        $cl = (int)($_POST['class_id']  ?? 0);
        if ($fn && $ln && $gr) {
            $sid  = generateStudentId();
            $admn = generateAdmissionNumber();
            $pdo->prepare("INSERT INTO students
                (student_id,admission_number,first_name,middle_name,last_name,gender,date_of_birth,
                 phone,email,address,county,current_grade_id,current_class_id,academic_year_id,
                 status,admission_date)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
               ->execute([$sid,$admn,$fn,trim($_POST['middle_name']??'')?:null,$ln,
                          $_POST['gender']??'Male',$_POST['dob']??null,
                          trim($_POST['phone']??'')?:null,trim($_POST['email']??'')?:null,
                          trim($_POST['address']??'')?:null,trim($_POST['county']??'Nimba'),
                          $gr,$cl?:null,$ayId,'Active',$_POST['enrollment_date']??date('Y-m-d')]);
            auditLog('create','students','student',(int)$pdo->lastInsertId(),'','New student: '.$sid);
            flash('success','Student added. ID: '.$sid);
        }
    } elseif ($action === 'status' && $canEdit) {
        $id = (int)($_POST['student_id'] ?? 0);
        $st = $_POST['status'] ?? '';
        $allowed = ['Active','Inactive','Graduated','Transferred','Withdrawn','Suspended'];
        if ($id && in_array($st, $allowed, true)) {
            $old = $pdo->query("SELECT status FROM students WHERE id=$id")->fetchColumn();
            $pdo->prepare("UPDATE students SET status=?,updated_at=NOW() WHERE id=?")->execute([$st,$id]);
            auditLog('update','students','student',$id,$old,$st);
        }
    } elseif ($action === 'delete' && $canDelete) {
        $id = (int)($_POST['student_id'] ?? 0);
        if ($id) {
            $pdo->prepare("DELETE FROM students WHERE id=?")->execute([$id]);
            auditLog('delete','students','student',$id);
            flash('success','Student record deleted.');
        }
    } else {
        flash('error','You do not have permission to perform that action.');
    }
    redirect(BASE_URL.'/admin/students.php?'.http_build_query(array_filter([
        'q'        => $_GET['q']        ?? '',
        'grade_id' => $_GET['grade_id'] ?? '',
        'status'   => $_GET['status']   ?? '',
    ])));
}

// ── Filters ───────────────────────────────────────────────────
$q       = trim($_GET['q']        ?? '');
$gradeF  = (int)($_GET['grade_id'] ?? 0);
$statusF = trim($_GET['status']    ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$per     = 20;

$where = []; $params = [];
if ($q)      { $where[] = '(s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ? OR s.phone LIKE ? OR s.admission_number LIKE ?)'; $like="%$q%"; array_push($params,$like,$like,$like,$like,$like); }
if ($gradeF) { $where[] = 's.current_grade_id=?'; $params[] = $gradeF; }
if ($statusF){ $where[] = 's.status=?';            $params[] = $statusF; }
$wsql = $where ? 'WHERE '.implode(' AND ',$where) : '';

$cnt = $pdo->prepare("SELECT COUNT(*) FROM students s $wsql"); $cnt->execute($params); $total=(int)$cnt->fetchColumn();
$pg  = paginate($total, $per, $page);
$rows= $pdo->prepare("SELECT s.*,g.name grade_name,c.name class_name FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id LEFT JOIN classes c ON c.id=s.current_class_id $wsql ORDER BY s.first_name,s.last_name LIMIT $per OFFSET {$pg['offset']}");
$rows->execute($params); $students = $rows->fetchAll();

$grades  = $pdo->query("SELECT id,name FROM grades WHERE is_active=1 ORDER BY sequence")->fetchAll();
$classes = $pdo->prepare("SELECT id,name FROM classes WHERE academic_year_id=? ORDER BY name")->execute([$ayId]) ? $pdo->query("SELECT id,name FROM classes WHERE academic_year_id=$ayId ORDER BY name")->fetchAll() : [];
$active  = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status='Active'")->fetchColumn();

$pageTitle   = 'Students';
$activeAdmin = 'students';
require_once dirname(__DIR__).'/includes/admin_header.php';
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">Student Records <span></span></div>
    <h1>Students</h1>
    <p>Manage student information and enrolment records.</p>
  </div>
  <?php if ($canAdd): ?>
  <button class="button button-primary" onclick="document.getElementById('addStudentModal').style.display='flex'">+ Add Student</button>
  <?php endif; ?>
</div>

<div class="stat-mini-row">
  <div class="stat-mini-item"><strong><?= number_format($active) ?></strong><span>Active students</span></div>
  <div class="stat-mini-item"><strong><?= number_format($total) ?></strong><span>Filtered results</span></div>
</div>

<div class="list-content">
  <form method="get" class="filter-row">
    <div class="table-search">🔍<input type="search" name="q" placeholder="Name, ID, phone…" value="<?= e($q) ?>"/></div>
    <select name="grade_id" class="filter-button" onchange="this.form.submit()">
      <option value="">All grades</option>
      <?php foreach ($grades as $g): ?><option value="<?= $g['id'] ?>" <?= $gradeF==$g['id']?'selected':'' ?>><?= e($g['name']) ?></option><?php endforeach; ?>
    </select>
    <select name="status" class="filter-button" onchange="this.form.submit()">
      <option value="">All statuses</option>
      <?php foreach (['Active','Inactive','Graduated','Transferred','Withdrawn','Suspended'] as $s): ?>
      <option value="<?= e($s) ?>" <?= $statusF===$s?'selected':'' ?>><?= e($s) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="button button-primary button-sm">Search</button>
    <?php if ($q||$gradeF||$statusF): ?><a href="<?= BASE_URL ?>/admin/students.php" class="filter-button">Clear</a><?php endif; ?>
    <!-- Export: only for roles that can view -->
    <a href="<?= BASE_URL ?>/admin/reports.php?export=csv&type=students" class="filter-button">📥 Export CSV</a>
  </form>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Student</th><th>Student ID</th><th>Admission #</th><th>Grade/Class</th>
          <th>Phone</th><th>Status</th>
          <?php if ($canEdit || $canDelete): ?><th>Actions</th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($students)): ?>
        <tr><td colspan="7" style="text-align:center;padding:36px;color:var(--ink-faint)">No students found.</td></tr>
        <?php else: ?>
        <?php foreach ($students as $st):
          $ini = strtoupper(substr($st['first_name'],0,1).substr($st['last_name'],0,1));
          $stCls = match($st['status']){'Active'=>'approved','Graduated'=>'new-s',default=>'warning'};
        ?>
        <tr>
          <td>
            <div class="person">
              <div class="avatar-sm" style="background:var(--accent-soft);color:var(--accent)"><?= e($ini) ?></div>
              <div>
                <strong><?= e($st['first_name'].' '.$st['last_name']) ?></strong>
                <?php if($st['email']): ?><div style="font-size:11px;color:var(--ink-faint)"><?= e($st['email']) ?></div><?php endif; ?>
              </div>
            </div>
          </td>
          <td class="muted"><?= e($st['student_id']) ?></td>
          <td class="muted"><?= e($st['admission_number'] ?? '—') ?></td>
          <td><?= e($st['grade_name'] ?? '—') ?><?= $st['class_name'] ? ' / '.e($st['class_name']) : '' ?></td>
          <td class="muted"><?= e($st['phone'] ?? '—') ?></td>
          <td><span class="status <?= $stCls ?>"><?= e($st['status']) ?></span></td>
          <?php if ($canEdit || $canDelete): ?>
          <td>
            <div style="display:flex;gap:5px;flex-wrap:wrap">
              <?php if ($canEdit): ?>
              <form method="post" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action"     value="status"/>
                <input type="hidden" name="student_id" value="<?= $st['id'] ?>"/>
                <select name="status" class="filter-button" style="padding:5px 8px;font-size:12px" onchange="this.form.submit()">
                  <?php foreach (['Active','Inactive','Graduated','Transferred','Withdrawn'] as $opt): ?>
                  <option value="<?= $opt ?>" <?= $st['status']===$opt?'selected':'' ?>><?= $opt ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
              <?php endif; ?>
              <?php if ($canDelete): ?>
              <form method="post" onsubmit="return confirm('Permanently delete this student?')">
                <?= csrfField() ?>
                <input type="hidden" name="action"     value="delete"/>
                <input type="hidden" name="student_id" value="<?= $st['id'] ?>"/>
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
    <?php if ($pg['page']>1): ?><a href="?page=<?=$pg['page']-1?>&q=<?=urlencode($q)?>&grade_id=<?=$gradeF?>&status=<?=urlencode($statusF)?>">&laquo;</a><?php endif; ?>
    <?php for($p=max(1,$pg['page']-2);$p<=min($pg['pages'],$pg['page']+2);$p++): ?>
      <?php if($p===$pg['page']): ?><span class="current"><?=$p?></span><?php else: ?><a href="?page=<?=$p?>&q=<?=urlencode($q)?>&grade_id=<?=$gradeF?>&status=<?=urlencode($statusF)?>"><?=$p?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($pg['page']<$pg['pages']): ?><a href="?page=<?=$pg['page']+1?>&q=<?=urlencode($q)?>&grade_id=<?=$gradeF?>&status=<?=urlencode($statusF)?>">&raquo;</a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?php if ($canAdd): ?>
<!-- Add Student Modal -->
<div id="addStudentModal" style="display:none;position:fixed;inset:0;background:rgba(26,26,31,.5);z-index:200;align-items:center;justify-content:center;padding:20px;overflow-y:auto">
  <div style="background:var(--surface);border-radius:var(--radius-lg);width:100%;max-width:640px;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-lg)">
    <div class="modal-head">
      <div><div class="eyebrow">New Student <span></span></div><h2>Add Student</h2></div>
      <button class="modal-close" onclick="document.getElementById('addStudentModal').style.display='none'">&times;</button>
    </div>
    <form method="post" style="padding:22px 26px 26px">
      <?= csrfField() ?><input type="hidden" name="action" value="add"/>
      <div class="form-row">
        <div class="form-group"><label>First Name *<input name="first_name" required/></label></div>
        <div class="form-group"><label>Middle Name<input name="middle_name"/></label></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Last Name *<input name="last_name" required/></label></div>
        <div class="form-group"><label>Gender *<select name="gender" required><option value="Male">Male</option><option value="Female">Female</option></select></label></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Date of Birth<input type="date" name="dob"/></label></div>
        <div class="form-group"><label>Phone<input name="phone" placeholder="+231 …"/></label></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Grade *<select name="grade_id" required><option value="">Select…</option><?php foreach($grades as $g): ?><option value="<?=$g['id']?>"><?=e($g['name'])?></option><?php endforeach; ?></select></label></div>
        <div class="form-group"><label>Class<select name="class_id"><option value="">Select…</option><?php foreach($classes as $c): ?><option value="<?=$c['id']?>"><?=e($c['name'])?></option><?php endforeach; ?></select></label></div>
      </div>
      <div class="form-row full"><div class="form-group"><label>Address<input name="address"/></label></div></div>
      <div class="form-row">
        <div class="form-group"><label>County<input name="county" value="Nimba"/></label></div>
        <div class="form-group"><label>Enrolment Date<input type="date" name="enrollment_date" value="<?= date('Y-m-d') ?>"/></label></div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px">
        <button type="button" class="button button-secondary" onclick="document.getElementById('addStudentModal').style.display='none'">Cancel</button>
        <button type="submit" class="button button-primary">Add Student →</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
