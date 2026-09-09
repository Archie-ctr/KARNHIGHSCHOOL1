<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['teacher','class_teacher']);

$activePage = 'results';
$pdo = db(); $user = currentUser(); $ayId = currentAcademicYearId(); $ay = currentAcademicYearName();

$teacherRow = $pdo->prepare("SELECT * FROM teachers WHERE user_id=? LIMIT 1");
$teacherRow->execute([$user['id']]); $teacher = $teacherRow->fetch();
if (!$teacher) { redirect(BASE_URL.'/portal/teacher/'); }
$teacherId = $teacher['id'];

// ── Assignments for this teacher ──────────────────────────────
$assignments = $pdo->prepare(
    "SELECT DISTINCT ta.class_id,ta.subject_id,c.name cname,s.name sname
     FROM teacher_assignments ta JOIN classes c ON c.id=ta.class_id JOIN subjects s ON s.id=ta.subject_id
     WHERE ta.teacher_id=? AND ta.academic_year_id=? ORDER BY c.name,s.name"
);
$assignments->execute([$teacherId,$ayId]); $assignments=$assignments->fetchAll();

$selClass = (int)($_GET['class_id']   ?? 0);
$selSub   = (int)($_GET['subject_id'] ?? 0);

$scores = []; $configs = []; $students = []; $statusSummary = [];

if ($selClass && $selSub) {
    // Verify assignment
    $ok = $pdo->prepare("SELECT 1 FROM teacher_assignments WHERE teacher_id=? AND class_id=? AND subject_id=? AND academic_year_id=? LIMIT 1");
    $ok->execute([$teacherId,$selClass,$selSub,$ayId]);
    if ($ok->fetchColumn()) {

        $configs = $pdo->prepare(
            "SELECT DISTINCT ac.id,ac.name,ac.sequence,ac.max_marks
             FROM assessment_scores asc2 JOIN assessment_configs ac ON ac.id=asc2.assessment_config_id
             WHERE asc2.class_id=? AND asc2.subject_id=? AND asc2.academic_year_id=?
             ORDER BY ac.sequence"
        );
        $configs->execute([$selClass,$selSub,$ayId]); $configs=$configs->fetchAll();

        $students = $pdo->prepare(
            "SELECT s.id,s.student_id,s.first_name,s.last_name FROM students s
             WHERE s.current_class_id=? AND s.status='Active' ORDER BY s.last_name,s.first_name"
        );
        $students->execute([$selClass]); $students=$students->fetchAll();

        $sc = $pdo->prepare(
            "SELECT asc2.*,ac.name cfg_name,ac.sequence FROM assessment_scores asc2
             JOIN assessment_configs ac ON ac.id=asc2.assessment_config_id
             WHERE asc2.class_id=? AND asc2.subject_id=? AND asc2.academic_year_id=?
             ORDER BY asc2.student_id,ac.sequence"
        );
        $sc->execute([$selClass,$selSub,$ayId]);
        foreach ($sc->fetchAll() as $row) {
            $scores[$row['student_id']][$row['assessment_config_id']] = $row;
            $statusSummary[$row['status']] = ($statusSummary[$row['status']] ?? 0) + 1;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Results — Teacher Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <div class="page-heading">
    <div><h1>Results & Performance</h1><p>View submitted marks, returned marks, and class performance</p></div>
    <a href="<?= BASE_URL ?>/portal/teacher/enter_marks.php" class="button button-secondary">✏️ Enter Marks</a>
  </div>

  <!-- Selector -->
  <form method="get" class="filter-row" style="margin-bottom:16px">
    <select name="class_id" class="filter-button" onchange="this.form.submit()">
      <option value="">Select class…</option>
      <?php
      $seen = [];
      foreach ($assignments as $a): if (isset($seen[$a['class_id']])) continue; $seen[$a['class_id']]=1; ?>
      <option value="<?= $a['class_id'] ?>" <?= $selClass==$a['class_id']?'selected':'' ?>><?= e($a['cname']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="subject_id" class="filter-button" onchange="this.form.submit()">
      <option value="">Select subject…</option>
      <?php foreach ($assignments as $a): if ($selClass && $a['class_id']!=$selClass) continue; ?>
      <option value="<?= $a['subject_id'] ?>" <?= $selSub==$a['subject_id']?'selected':'' ?>><?= e($a['sname']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="button button-secondary button-sm">View</button>
  </form>

  <?php if (!$selClass || !$selSub): ?>
  <div style="text-align:center;padding:56px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
    <div style="font-size:40px;margin-bottom:12px">📊</div>
    <p style="color:var(--ink-soft)">Select a class and subject to view results.</p>
  </div>

  <?php elseif (empty($students)): ?>
  <div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
    <p style="color:var(--ink-soft)">No students found for the selected class.</p>
  </div>

  <?php else: ?>

  <!-- Status summary -->
  <?php if (!empty($statusSummary)): ?>
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
    <?php
    $sBadge=['draft'=>'pending','submitted'=>'new-s','approved'=>'approved','returned'=>'warning','resubmitted'=>'new-s','rejected'=>'warning','published'=>'approved'];
    foreach ($statusSummary as $st => $cnt): ?>
    <span class="status <?= $sBadge[$st]??'new-s' ?>" style="font-size:12px;padding:5px 12px">
      <?= ucfirst($st) ?>: <strong><?= $cnt ?></strong>
    </span>
    <?php endforeach; ?>
  </div>

  <!-- Returned marks alert -->
  <?php $returnedCount = $statusSummary['returned'] ?? 0;
  if ($returnedCount > 0): ?>
  <div class="alert alert-warn" style="margin-bottom:16px">
    ↩️ <strong><?= $returnedCount ?> mark record<?= $returnedCount!==1?'s':'' ?></strong> returned for correction.
    <a href="<?= BASE_URL ?>/portal/teacher/enter_marks.php?class_id=<?= $selClass ?>&subject_id=<?= $selSub ?>" style="font-weight:700;margin-left:8px">Correct & Resubmit →</a>
  </div>
  <?php endif; endif; ?>

  <?php if (!empty($students) && !empty($configs)): ?>
  <!-- Results table -->
  <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:600px">
      <thead>
        <tr style="background:var(--primary)">
          <th style="padding:10px 14px;color:#fff;text-align:left;white-space:nowrap">Student</th>
          <?php foreach ($configs as $cfg): ?>
          <th style="padding:10px 8px;color:#fff;text-align:center;font-size:11px;white-space:nowrap">
            <?= e($cfg['name']) ?><br><span style="opacity:.65;font-weight:400">/<?= $cfg['max_marks'] ?></span>
          </th>
          <?php endforeach; ?>
          <th style="padding:10px 8px;color:#fff;text-align:center;white-space:nowrap">Average</th>
          <th style="padding:10px 8px;color:#fff;text-align:center;white-space:nowrap">Grade</th>
          <th style="padding:10px 8px;color:#fff;text-align:center;white-space:nowrap">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $s):
          $sScores = $scores[$s['id']] ?? [];
          $vals = array_filter(array_map(fn($sc) => ($sc['max_marks']>0 && $sc['marks_obtained']!==null) ? round($sc['marks_obtained']/$sc['max_marks']*100,1) : null, $sScores), fn($v) => $v!==null);
          $avg  = count($vals) ? round(array_sum($vals)/count($vals),1) : null;
          $gl   = $avg !== null ? gradeLetter($avg,$ayId) : '—';
          // Determine overall status for this student
          $stStatuses = array_unique(array_column(array_values($sScores),'status'));
          $rowStatus  = count($stStatuses)===1 ? $stStatuses[0] : (in_array('returned',$stStatuses)?'returned':(in_array('submitted',$stStatuses)?'submitted':'draft'));
        ?>
        <tr style="border-bottom:1px solid var(--line-soft)">
          <td style="padding:9px 14px">
            <strong><?= e($s['last_name'].', '.$s['first_name']) ?></strong>
            <div style="font-size:11px;color:var(--ink-faint)"><?= e($s['student_id']) ?></div>
          </td>
          <?php foreach ($configs as $cfg):
            $sc  = $sScores[$cfg['id']] ?? null;
            $pct = ($sc && $sc['max_marks']>0 && $sc['marks_obtained']!==null) ? round($sc['marks_obtained']/$sc['max_marks']*100,1) : null;
          ?>
          <td style="padding:9px 8px;text-align:center;<?= $pct!==null&&$pct<50?'color:var(--error)':'' ?>">
            <?php if ($sc): ?>
            <strong><?= $sc['marks_obtained']??'—' ?></strong>
            <?php if($pct!==null):?><div style="font-size:10px;color:var(--ink-soft)"><?= $pct ?>%</div><?php endif; ?>
            <?php else: ?><span style="color:var(--ink-faint)">—</span><?php endif; ?>
          </td>
          <?php endforeach; ?>
          <td style="padding:9px 8px;text-align:center;font-weight:700">
            <?= $avg!==null ? '<span style="color:'.($avg>=50?'var(--green)':'var(--error)').'">'.$avg.'%</span>' : '—' ?>
          </td>
          <td style="padding:9px 8px;text-align:center">
            <span style="font-weight:800;color:<?= in_array($gl,['A','B','C','D'])?'var(--green)':'var(--error)' ?>"><?= $gl ?></span>
          </td>
          <td style="padding:9px 8px;text-align:center">
            <span class="status <?= $sBadge[$rowStatus]??'new-s' ?>" style="font-size:10px"><?= ucfirst($rowStatus) ?></span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p style="margin-top:10px;font-size:12px;color:var(--ink-faint)">
    ℹ️ Showing approved and submitted marks only. To edit drafts or resubmit returned marks, use <a href="<?= BASE_URL ?>/portal/teacher/enter_marks.php?class_id=<?= $selClass ?>&subject_id=<?= $selSub ?>" style="color:var(--primary)">Enter Marks</a>.
  </p>
  <?php elseif (!empty($students)): ?>
  <p style="color:var(--ink-soft);padding:24px;text-align:center">No marks entered yet for this class/subject combination.</p>
  <?php endif; ?>

</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body></html>
