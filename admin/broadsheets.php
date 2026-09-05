<?php
$pageTitle   = 'Broadsheets';
$activeAdmin = 'broadsheets';
require_once dirname(__DIR__).'/includes/admin_header.php';

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();

$classes  = $pdo->prepare("SELECT id,name FROM classes WHERE academic_year_id=? ORDER BY name")->execute([$ayId]) ? $pdo->query("SELECT id,name FROM classes WHERE academic_year_id=$ayId ORDER BY name")->fetchAll() : [];
$selClass = (int)($_GET['class_id']??0);

$students = []; $subjects = []; $matrix = []; $configs = [];
if ($selClass) {
    $sts=$pdo->prepare("SELECT id,student_id,first_name,last_name FROM students WHERE current_class_id=? AND status='Active' ORDER BY last_name,first_name");
    $sts->execute([$selClass]); $students=$sts->fetchAll();

    // Subjects for this class
    $subs=$pdo->prepare("SELECT DISTINCT s.id,s.name,s.short_name FROM assessment_scores asc2 JOIN subjects s ON s.id=asc2.subject_id WHERE asc2.class_id=? AND asc2.academic_year_id=? ORDER BY s.name");
    $subs->execute([$selClass,$ayId]); $subjects=$subs->fetchAll();

    // All scores
    $scores=$pdo->prepare("SELECT asc2.*,ac.name cfg_name,ac.sequence cfg_seq FROM assessment_scores asc2 JOIN assessment_configs ac ON ac.id=asc2.assessment_config_id WHERE asc2.class_id=? AND asc2.academic_year_id=?");
    $scores->execute([$selClass,$ayId]);
    foreach ($scores->fetchAll() as $sc) {
        $matrix[$sc['student_id']][$sc['subject_id']][$sc['cfg_name']] = $sc['marks_obtained'];
    }

    // Config names for headers
    $cfgs=$pdo->prepare("SELECT DISTINCT ac.name,ac.sequence FROM assessment_scores asc2 JOIN assessment_configs ac ON ac.id=asc2.assessment_config_id WHERE asc2.class_id=? AND asc2.academic_year_id=? ORDER BY ac.sequence");
    $cfgs->execute([$selClass,$ayId]); $configs=$cfgs->fetchAll();
}
?>

<div class="page-heading">
  <div><div class="eyebrow">Assessment <span></span></div><h1>Class Broadsheet</h1></div>
  <?php if ($selClass): ?>
  <button onclick="window.print()" class="button button-secondary">🖨️ Print</button>
  <?php endif; ?>
</div>

<form method="get" class="filter-row" style="margin-bottom:20px">
  <select name="class_id" class="filter-button" onchange="this.form.submit()" style="min-width:180px">
    <option value="">Select Class…</option>
    <?php foreach($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $selClass==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
  </select>
</form>

<?php if ($selClass && !empty($students) && !empty($subjects)): ?>
<div id="broadsheetPrint" style="overflow-x:auto">
<div style="background:#fff;padding:16px;border-radius:var(--radius);border:1px solid var(--line)">
  <div style="text-align:center;margin-bottom:12px">
    <strong style="font-size:16px"><?= e(setting('school_name','KARN HIGH SCHOOL')) ?></strong><br>
    <span style="font-size:13px;color:var(--ink-soft)">Class Broadsheet — <?= e($pdo->query("SELECT name FROM classes WHERE id=$selClass")->fetchColumn()) ?> — <?= e($ay) ?></span>
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:11px">
    <thead>
      <tr style="background:var(--primary);color:#fff">
        <th style="padding:7px 10px;text-align:left;white-space:nowrap">Student</th>
        <th style="padding:7px 6px;white-space:nowrap">Std ID</th>
        <?php foreach($subjects as $sub): ?>
        <th colspan="<?= count($configs)+1 ?>" style="padding:7px 6px;text-align:center;border-left:2px solid rgba(255,255,255,.2)"><?= e($sub['short_name']??$sub['name']) ?></th>
        <?php endforeach; ?>
        <th style="padding:7px 6px;border-left:2px solid rgba(255,255,255,.2)">Overall Avg</th>
        <th style="padding:7px 6px">Grade</th>
        <th style="padding:7px 6px">Position</th>
      </tr>
      <tr style="background:var(--primary-soft)">
        <th></th><th></th>
        <?php foreach($subjects as $sub): ?>
          <?php foreach($configs as $cfg): ?><th style="padding:5px 4px;font-size:10px;color:var(--ink-soft);border-left:1px solid var(--line)"><?= e($cfg['name']) ?></th><?php endforeach; ?>
          <th style="padding:5px 4px;font-size:10px;font-weight:800;color:var(--primary)">Avg</th>
        <?php endforeach; ?>
        <th></th><th></th><th></th>
      </tr>
    </thead>
    <tbody>
      <?php
      $studentAvgs = [];
      foreach ($students as $st):
        $subjAvgs = [];
        foreach ($subjects as $sub):
          $subMarks = array_filter($matrix[$st['id']][$sub['id']] ?? [], fn($v) => $v !== null);
          $subjAvgs[$sub['id']] = count($subMarks) ? round(array_sum($subMarks)/count($subMarks),1) : null;
        endforeach;
        $allAvgs  = array_filter($subjAvgs, fn($v) => $v !== null);
        $overallAvg = count($allAvgs) ? round(array_sum($allAvgs)/count($allAvgs),1) : null;
        $studentAvgs[$st['id']] = $overallAvg;
      endforeach;
      // Rank students
      arsort($studentAvgs);
      $positions = []; $pos=1;
      foreach($studentAvgs as $sid=>$avg) { $positions[$sid]=$pos++; }

      foreach ($students as $st):
        $overallAvg = $studentAvgs[$st['id']];
        $gl = $overallAvg !== null ? gradeLetter($overallAvg,$ayId) : '—';
        $subjAvgs2 = [];
        foreach($subjects as $sub) {
          $subMarks = array_filter($matrix[$st['id']][$sub['id']] ?? [], fn($v) => $v !== null);
          $subjAvgs2[$sub['id']] = count($subMarks) ? round(array_sum($subMarks)/count($subMarks),1) : null;
        }
      ?>
      <tr style="border-bottom:1px solid var(--line-soft)">
        <td style="padding:6px 10px;font-weight:600"><?= e($st['first_name'].' '.$st['last_name']) ?></td>
        <td style="padding:6px 4px;color:var(--ink-faint)"><?= e($st['student_id']) ?></td>
        <?php foreach($subjects as $sub): ?>
          <?php foreach($configs as $cfg): ?>
          <td style="padding:6px 4px;text-align:center;border-left:1px solid var(--line-soft)"><?= $matrix[$st['id']][$sub['id']][$cfg['name']] ?? '—' ?></td>
          <?php endforeach; ?>
          <td style="padding:6px 4px;text-align:center;font-weight:700;background:var(--bg)"><?= $subjAvgs2[$sub['id']] ?? '—' ?></td>
        <?php endforeach; ?>
        <td style="padding:6px 4px;text-align:center;font-weight:800"><?= $overallAvg ?? '—' ?></td>
        <td style="padding:6px 4px;text-align:center"><span style="font-weight:700;color:<?= in_array($gl,['A','B','C','D'])?'var(--green)':'var(--error)' ?>"><?= e($gl) ?></span></td>
        <td style="padding:6px 4px;text-align:center;font-weight:700;color:var(--primary)"><?= $positions[$st['id']] ?? '—' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
</div>
<?php elseif ($selClass): ?>
<div style="text-align:center;padding:40px;color:var(--ink-faint)">No marks data available for this class yet.</div>
<?php else: ?>
<div style="background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);padding:40px;text-align:center">
  <div style="font-size:36px;margin-bottom:12px">📃</div>
  <p style="color:var(--ink-soft)">Select a class to view its broadsheet.</p>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
