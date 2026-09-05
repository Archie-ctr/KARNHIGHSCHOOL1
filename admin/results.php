<?php
$pageTitle   = 'Results';
$activeAdmin = 'results';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['sys_admin','school_admin','principal','vice_principal','registrar','teacher','class_teacher']);

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();
$canApprove = can('approve_marks');

// ── Approve marks ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='approve') {
    verifyCsrf();
    if (!$canApprove) { flash('error','You do not have permission to approve marks.'); redirect(BASE_URL.'/admin/results.php'); }
    $clsId=(int)($_POST['class_id']??0); $subId=(int)($_POST['subject_id']??0); $cfgId=(int)($_POST['config_id']??0);
    $pdo->prepare("UPDATE assessment_scores SET status='approved',approved_by=?,approved_at=NOW() WHERE class_id=? AND subject_id=? AND assessment_config_id=? AND academic_year_id=? AND status='submitted'")
       ->execute([currentUser()['id'],$clsId,$subId,$cfgId,$ayId]);
    auditLog('approve_marks','assessment','class',$clsId,'submitted','approved');
    flash('success','Marks approved and published.');
    redirect(BASE_URL.'/admin/results.php?class_id='.$clsId.'&subject_id='.$subId);
}

$classes = $pdo->prepare("SELECT id,name FROM classes WHERE academic_year_id=? ORDER BY name")->execute([$ayId]) ? $pdo->query("SELECT id,name FROM classes WHERE academic_year_id=$ayId ORDER BY name")->fetchAll() : [];
$selClass= (int)($_GET['class_id']??0);
$selSub  = (int)($_GET['subject_id']??0);
$subjects = $pdo->query("SELECT id,name FROM subjects WHERE is_active=1 ORDER BY name")->fetchAll();

$results = [];
if ($selClass && $selSub) {
    $res=$pdo->prepare("SELECT s.id,s.student_id,s.first_name,s.last_name,ac.name config_name,ac.max_marks,asc.marks_obtained,asc.status,ac.sequence
        FROM students s
        JOIN assessment_scores asc ON asc.student_id=s.id AND asc.class_id=? AND asc.subject_id=? AND asc.academic_year_id=?
        JOIN assessment_configs ac ON ac.id=asc.assessment_config_id
        WHERE s.status='Active'
        ORDER BY s.last_name,s.first_name,ac.sequence");
    $res->execute([$selClass,$selSub,$ayId]); $results=$res->fetchAll();
}
?>

<div class="page-heading">
  <div><div class="eyebrow">Assessment <span></span></div><h1>Results & Approval</h1></div>
</div>

<form method="get" class="filter-row" style="margin-bottom:24px">
  <select name="class_id" class="filter-button" onchange="this.form.submit()" style="min-width:160px">
    <option value="">Select Class…</option>
    <?php foreach($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $selClass==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
  </select>
  <select name="subject_id" class="filter-button" onchange="this.form.submit()" style="min-width:180px">
    <option value="">Select Subject…</option>
    <?php foreach($subjects as $s): ?><option value="<?= $s['id'] ?>" <?= $selSub==$s['id']?'selected':'' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
  </select>
</form>

<?php if (!empty($results)): ?>
<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Student</th>
        <th>Student ID</th>
        <?php
        // Collect unique configs
        $cfgHeaders = []; foreach($results as $r) $cfgHeaders[$r['config_name']] = $r['config_name'];
        foreach($cfgHeaders as $h): ?><th><?= e($h) ?></th><?php endforeach; ?>
        <th>Average</th>
        <th>Grade</th>
      </tr>
    </thead>
    <tbody>
      <?php
      // Group by student
      $byStudent = [];
      foreach ($results as $r) $byStudent[$r['id']][$r['config_name']] = $r;
      foreach ($byStudent as $stdId => $cfgData):
        $first = reset($cfgData);
        $marks = array_filter(array_column($cfgData,'marks_obtained'), fn($v) => $v !== null);
        $avg   = count($marks) ? round(array_sum($marks)/count($marks),1) : null;
        $gl    = $avg !== null ? gradeLetter($avg,$ayId) : '—';
      ?>
      <tr>
        <td><strong><?= e($first['first_name'].' '.$first['last_name']) ?></strong></td>
        <td class="muted"><?= e($first['student_id']) ?></td>
        <?php foreach($cfgHeaders as $h): ?>
          <td><?= isset($cfgData[$h]) ? fmtMark($cfgData[$h]['marks_obtained']) : '—' ?><?= isset($cfgData[$h])&&$cfgData[$h]['status']==='approved'?'<span style="color:var(--green);font-size:10px"> ✓</span>':'' ?></td>
        <?php endforeach; ?>
        <td><strong><?= $avg !== null ? $avg : '—' ?></strong></td>
        <td><span class="status <?= in_array($gl,['A','B','C','D'])?'approved':'warning' ?>"><?= e($gl) ?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php elseif ($selClass&&$selSub): ?>
<div style="text-align:center;padding:40px;color:var(--ink-faint)">No marks entered for this class and subject yet.</div>
<?php else: ?>
<div style="background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);padding:40px;text-align:center">
  <div style="font-size:36px;margin-bottom:12px">📊</div>
  <p style="color:var(--ink-soft)">Select a class and subject to view results.</p>
</div>
<?php endif; ?>

<?php if ($canApprove && $selClass && $selSub && !empty($results)): ?>
<?php
// Check if any submitted marks exist to approve
$hasSubmitted = false;
foreach ($byStudent as $cfgData2) { foreach ($cfgData2 as $d) { if (!empty($d['status']) && $d['status']==='submitted') { $hasSubmitted=true; break 2; } } }
?>
<?php if ($hasSubmitted): ?>
<div class="form-section" style="margin-top:20px">
  <div class="form-section-title" style="display:flex;justify-content:space-between;align-items:center">
    <span>📤 Submitted marks are awaiting approval</span>
    <?php
    // Get config IDs for submitted marks in this class/subject
    $submittedCfgs=$pdo->prepare("SELECT DISTINCT assessment_config_id FROM assessment_scores WHERE class_id=? AND subject_id=? AND academic_year_id=? AND status='submitted'");
    $submittedCfgs->execute([$selClass,$selSub,$ayId]); $cfgIds=$submittedCfgs->fetchAll(PDO::FETCH_COLUMN);
    ?>
  </div>
  <p style="font-size:13.5px;color:var(--ink-soft);margin-bottom:16px">As <?= e(currentUser()['role_label']??'') ?>, you can approve these submitted marks to make them official.</p>
  <?php foreach ($cfgIds as $cfgId): ?>
  <?php $cfgName=$pdo->query("SELECT name FROM assessment_configs WHERE id=$cfgId")->fetchColumn(); ?>
  <form method="post" style="display:inline;margin-right:8px">
    <?= csrfField() ?>
    <input type="hidden" name="action"     value="approve"/>
    <input type="hidden" name="class_id"   value="<?= $selClass ?>"/>
    <input type="hidden" name="subject_id" value="<?= $selSub ?>"/>
    <input type="hidden" name="config_id"  value="<?= $cfgId ?>"/>
    <button type="submit" class="button button-success button-sm" onclick="return confirm('Approve marks for <?= e(addslashes($cfgName??'')) ?>? This cannot be undone.')">
      ✓ Approve "<?= e($cfgName ?? 'Assessment') ?>"
    </button>
  </form>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>