<?php
$pageTitle   = 'Classes';
$activeAdmin = 'classes';
require_once dirname(__DIR__).'/includes/admin_header.php';

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action = $_POST['action']??'';
    if ($action==='add') {
        $gid  = (int)($_POST['grade_id']??0);
        $sect = trim($_POST['section']??'');
        if ($gid && $sect) {
            $gname = $pdo->prepare("SELECT name FROM grades WHERE id=?")->execute([$gid]) ? $pdo->query("SELECT name FROM grades WHERE id=$gid")->fetchColumn() : '';
            $cname = $gname.' '.$sect;
            $pdo->prepare("INSERT INTO classes (grade_id,academic_year_id,name,section,teacher_id,room) VALUES (?,?,?,?,?,?)")->execute([$gid,$ayId,$cname,$sect,($_POST['teacher_id']??null)?:null,trim($_POST['room']??'')?:null]);
            flash('success','Class '.$cname.' created.');
        }
    } elseif ($action==='delete') {
        $id=(int)($_POST['class_id']??0);
        if ($id) { $pdo->prepare("DELETE FROM classes WHERE id=?")->execute([$id]); flash('success','Class deleted.'); }
    }
    redirect(BASE_URL.'/admin/classes.php');
}

$classes  = $pdo->prepare("SELECT c.*,g.name grade_name,t.first_name tc_fn,t.last_name tc_ln,(SELECT COUNT(*) FROM students s WHERE s.current_class_id=c.id) enrol FROM classes c JOIN grades g ON g.id=c.grade_id LEFT JOIN teachers t ON t.id=c.teacher_id WHERE c.academic_year_id=? ORDER BY g.sequence,c.section")->execute([$ayId]) ? [] : [];
$stmt=$pdo->prepare("SELECT c.*,g.name grade_name,t.first_name tc_fn,t.last_name tc_ln,(SELECT COUNT(*) FROM students s WHERE s.current_class_id=c.id) enrol FROM classes c JOIN grades g ON g.id=c.grade_id LEFT JOIN teachers t ON t.id=c.teacher_id WHERE c.academic_year_id=? ORDER BY g.sequence,c.section"); $stmt->execute([$ayId]); $classes=$stmt->fetchAll();
$grades   = $pdo->query("SELECT id,name FROM grades WHERE is_active=1 ORDER BY sequence")->fetchAll();
$teachers = $pdo->query("SELECT id,CONCAT(first_name,' ',last_name) name FROM teachers WHERE status='Active' ORDER BY first_name")->fetchAll();
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">Classes — <?= e($ay) ?> <span></span></div>
    <h1>Classes</h1>
    <p>Manage classes and class teacher assignments.</p>
  </div>
  <button class="button button-primary" onclick="document.getElementById('addClassModal').style.display='flex'">+ New Class</button>
</div>

<div class="table-wrap">
  <table>
    <thead><tr><th>Class</th><th>Grade</th><th>Section</th><th>Class Teacher</th><th>Enrolment</th><th>Room</th><th>Actions</th></tr></thead>
    <tbody>
      <?php if (empty($classes)): ?>
      <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--ink-faint)">No classes created yet for <?= e($ay) ?>.</td></tr>
      <?php else: ?>
      <?php foreach ($classes as $c): ?>
      <tr>
        <td><strong><?= e($c['name']) ?></strong></td>
        <td><?= e($c['grade_name']) ?></td>
        <td><?= e($c['section']??'—') ?></td>
        <td><?= $c['tc_fn'] ? e($c['tc_fn'].' '.$c['tc_ln']) : '<span class="muted">Not assigned</span>' ?></td>
        <td><?= (int)$c['enrol'] ?></td>
        <td class="muted"><?= e($c['room']??'—') ?></td>
        <td>
          <form method="post" onsubmit="return confirm('Delete this class?')" style="display:inline">
            <?= csrfField() ?><input type="hidden" name="action" value="delete"/><input type="hidden" name="class_id" value="<?= $c['id'] ?>"/>
            <button type="submit" class="filter-button button-sm" style="color:var(--error)">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div id="addClassModal" style="display:none;position:fixed;inset:0;background:rgba(26,26,31,.5);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:var(--surface);border-radius:var(--radius-lg);width:100%;max-width:440px;box-shadow:var(--shadow-lg);padding:28px">
    <h3 style="margin-bottom:18px">New Class — <?= e($ay) ?></h3>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="add"/>
      <div class="form-row">
        <div class="form-group"><label>Grade *<select name="grade_id" required><option value="">Select…</option><?php foreach($grades as $g): ?><option value="<?= $g['id'] ?>"><?= e($g['name']) ?></option><?php endforeach; ?></select></label></div>
        <div class="form-group"><label>Section *<input name="section" required placeholder="A, B, C…" maxlength="5"/></label></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Class Teacher<select name="teacher_id"><option value="">None</option><?php foreach($teachers as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></label></div>
        <div class="form-group"><label>Room<input name="room" placeholder="Room 101…"/></label></div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px">
        <button type="button" class="button button-secondary" onclick="document.getElementById('addClassModal').style.display='none'">Cancel</button>
        <button type="submit" class="button button-primary">Create Class →</button>
      </div>
    </form>
  </div>
</div>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
