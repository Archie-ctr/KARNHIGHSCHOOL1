<?php
$pageTitle   = 'Teacher Assignments';
$activeAdmin = 'assignments';
require_once dirname(__DIR__).'/includes/admin_header.php';

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action = $_POST['action']??'';
    if ($action==='assign') {
        $tid=(int)($_POST['teacher_id']??0); $sid=(int)($_POST['subject_id']??0); $cid=(int)($_POST['class_id']??0);
        if ($tid&&$sid&&$cid) {
            try {
                $pdo->prepare("INSERT IGNORE INTO teacher_assignments (teacher_id,subject_id,class_id,academic_year_id) VALUES (?,?,?,?)")->execute([$tid,$sid,$cid,$ayId]);
                flash('success','Assignment created.');
            } catch(PDOException $e) { flash('error','That assignment already exists.'); }
        }
    } elseif ($action==='delete') {
        $id=(int)($_POST['ta_id']??0);
        if ($id) { $pdo->prepare("DELETE FROM teacher_assignments WHERE id=?")->execute([$id]); flash('success','Assignment removed.'); }
    }
    redirect(BASE_URL.'/admin/teacher_assignments.php');
}

$assignments = $pdo->prepare("SELECT ta.*,CONCAT(t.first_name,' ',t.last_name) teacher_name,s.name subject_name,c.name class_name FROM teacher_assignments ta JOIN teachers t ON t.id=ta.teacher_id JOIN subjects s ON s.id=ta.subject_id JOIN classes c ON c.id=ta.class_id WHERE ta.academic_year_id=? ORDER BY c.name,s.name");
$assignments->execute([$ayId]); $assignments=$assignments->fetchAll();

$teachers = $pdo->query("SELECT id,CONCAT(first_name,' ',last_name) name FROM teachers WHERE status='Active' ORDER BY first_name")->fetchAll();
$subjects = $pdo->query("SELECT id,name FROM subjects WHERE is_active=1 ORDER BY name")->fetchAll();
$classes  = $pdo->prepare("SELECT id,name FROM classes WHERE academic_year_id=? ORDER BY name")->execute([$ayId]) ? $pdo->query("SELECT id,name FROM classes WHERE academic_year_id=$ayId ORDER BY name")->fetchAll() : [];
?>

<div class="page-heading">
  <div><div class="eyebrow">Academics — <?= e($ay) ?> <span></span></div><h1>Teacher Assignments</h1><p>Assign teachers to subjects and classes.</p></div>
  <button class="button button-primary" onclick="document.getElementById('assignModal').style.display='flex'">+ New Assignment</button>
</div>

<div class="table-wrap">
  <table>
    <thead><tr><th>Teacher</th><th>Subject</th><th>Class</th><th>Actions</th></tr></thead>
    <tbody>
      <?php if (empty($assignments)): ?>
      <tr><td colspan="4" style="text-align:center;padding:32px;color:var(--ink-faint)">No assignments yet for <?= e($ay) ?>.</td></tr>
      <?php else: ?>
      <?php foreach ($assignments as $a): ?>
      <tr>
        <td><strong><?= e($a['teacher_name']) ?></strong></td>
        <td><?= e($a['subject_name']) ?></td>
        <td><?= e($a['class_name']) ?></td>
        <td>
          <form method="post" onsubmit="return confirm('Remove assignment?')" style="display:inline">
            <?= csrfField() ?><input type="hidden" name="action" value="delete"/><input type="hidden" name="ta_id" value="<?= $a['id'] ?>"/>
            <button type="submit" class="filter-button button-sm" style="color:var(--error)">Remove</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div id="assignModal" style="display:none;position:fixed;inset:0;background:rgba(26,26,31,.5);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:var(--surface);border-radius:var(--radius-lg);width:100%;max-width:440px;box-shadow:var(--shadow-lg);padding:28px">
    <h3 style="margin-bottom:18px">New Assignment — <?= e($ay) ?></h3>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="assign"/>
      <div class="form-row full"><div class="form-group"><label>Teacher *<select name="teacher_id" required><option value="">Select…</option><?php foreach($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></label></div></div>
      <div class="form-row full"><div class="form-group"><label>Subject *<select name="subject_id" required><option value="">Select…</option><?php foreach($subjects as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></label></div></div>
      <div class="form-row full"><div class="form-group"><label>Class *<select name="class_id" required><option value="">Select…</option><?php foreach($classes as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select></label></div></div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px">
        <button type="button" class="button button-secondary" onclick="document.getElementById('assignModal').style.display='none'">Cancel</button>
        <button type="submit" class="button button-primary">Assign →</button>
      </div>
    </form>
  </div>
</div>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
