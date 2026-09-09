<?php
// ============================================================
// Student Portal — My Results
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('student');

$pdo        = db();
$user       = currentUser();
$activePage = 'results';
$ayId       = currentAcademicYearId();
$ay         = currentAcademicYearName();

$student = $pdo->prepare(
    "SELECT s.*, g.name grade_name, c.name class_name
     FROM students s
     LEFT JOIN grades  g ON g.id = s.current_grade_id
     LEFT JOIN classes c ON c.id = s.current_class_id
     WHERE s.user_id = ? LIMIT 1"
);
$student->execute([$user['id']]);
$student = $student->fetch();
if (!$student) { redirect(BASE_URL.'/portal/student/'); }

// All approved/published scores grouped by subject
$scores = $pdo->prepare(
    "SELECT ac.name cfg_name, ac.sequence, ac.max_marks,
            s.name subj_name, asc2.marks_obtained, asc2.status
     FROM assessment_scores asc2
     JOIN assessment_configs ac ON ac.id = asc2.assessment_config_id
     JOIN subjects            s  ON s.id  = asc2.subject_id
     WHERE asc2.student_id = ? AND asc2.academic_year_id = ?
     ORDER BY s.name, ac.sequence"
);
$scores->execute([$student['id'], $ayId]);
$scores = $scores->fetchAll();

$bySubject = [];
foreach ($scores as $sc) {
    $bySubject[$sc['subj_name']][$sc['cfg_name']] = $sc;
}
$configs = [];
foreach ($scores as $sc) $configs[$sc['cfg_name']] = $sc['cfg_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>My Results — Student Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <div class="page-heading">
    <div>
      <h1>My Results</h1>
      <p><?= e($student['grade_name'] ?? '') ?><?= $student['class_name'] ? ' / '.e($student['class_name']) : '' ?> &mdash; <?= e($ay) ?></p>
    </div>
    <a href="<?= BASE_URL ?>/portal/student/report_card.php" class="button button-secondary button-sm">📑 Report Card</a>
  </div>

  <?php if (empty($bySubject)): ?>
  <div style="background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);padding:48px;text-align:center">
    <div style="font-size:40px;margin-bottom:12px">📊</div>
    <h3 style="margin-bottom:6px">No results yet</h3>
    <p style="color:var(--ink-soft)">Results for <?= e($ay) ?> will appear here once your teachers enter and submit marks.</p>
  </div>

  <?php else: ?>
  <div class="panel" style="overflow-x:auto;padding:0">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead>
        <tr style="background:var(--primary)">
          <th style="padding:12px 16px;color:#fff;text-align:left;white-space:nowrap">Subject</th>
          <?php foreach ($configs as $cfg): ?>
          <th style="padding:12px 10px;color:#fff;text-align:center;white-space:nowrap;font-size:11px"><?= e($cfg) ?></th>
          <?php endforeach; ?>
          <th style="padding:12px 10px;color:#fff;text-align:center;white-space:nowrap">Average</th>
          <th style="padding:12px 10px;color:#fff;text-align:center;white-space:nowrap">Grade</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bySubject as $subName => $cfgScores):
          $vals = [];
          foreach ($configs as $cfg) {
              $sc = $cfgScores[$cfg] ?? null;
              if ($sc && $sc['max_marks'] > 0 && $sc['marks_obtained'] !== null) {
                  $vals[] = round($sc['marks_obtained'] / $sc['max_marks'] * 100, 1);
              }
          }
          $avg = count($vals) ? round(array_sum($vals) / count($vals), 1) : null;
          $gl  = $avg !== null ? gradeLetter($avg, $ayId) : '—';
          $glColor = in_array($gl, ['A','B','C','D']) ? 'var(--green)' : 'var(--error)';
        ?>
        <tr style="border-bottom:1px solid var(--line-soft)">
          <td style="padding:10px 16px;font-weight:600;color:var(--ink)"><?= e($subName) ?></td>
          <?php foreach ($configs as $cfg):
            $sc  = $cfgScores[$cfg] ?? null;
            $pct = ($sc && $sc['max_marks'] > 0 && $sc['marks_obtained'] !== null)
                   ? round($sc['marks_obtained'] / $sc['max_marks'] * 100, 1) : null;
          ?>
          <td style="padding:10px;text-align:center;<?= $pct !== null && $pct < 50 ? 'color:var(--error)' : '' ?>">
            <?= $pct !== null ? $pct.'%' : '<span style="color:var(--line)">—</span>' ?>
          </td>
          <?php endforeach; ?>
          <td style="padding:10px;text-align:center;font-weight:700">
            <?= $avg !== null ? '<span style="font-size:15px">'.$avg.'%</span>' : '—' ?>
          </td>
          <td style="padding:10px;text-align:center">
            <span style="font-weight:800;font-size:15px;color:<?= $glColor ?>"><?= $gl ?></span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <p style="margin-top:12px;font-size:12px;color:var(--ink-faint)">
    ℹ️ Results shown are submitted or approved marks only. Grades are calculated based on the school's grading scale.
  </p>
  <?php endif; ?>

  <!-- ── PREVIOUS YEARS ─────────────────────────────── -->
  <?php
  // All academic years the student has results in
  try {
      $prevYears=$pdo->query(
          "SELECT DISTINCT ay.id,ay.name FROM assessment_scores asc2
           JOIN academic_years ay ON ay.id=asc2.academic_year_id
           WHERE asc2.student_id={$student['id']} AND asc2.academic_year_id!=$ayId
           ORDER BY ay.start_date DESC LIMIT 5"
      )->fetchAll();
  } catch (Throwable $e) { $prevYears=[]; }
  if (!empty($prevYears)):
  ?>
  <div style="margin-top:24px">
    <h3 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:12px">📈 Previous Academic Years</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px">
      <?php foreach ($prevYears as $py):
        try{
            $pyAvg=$pdo->query("SELECT ROUND(AVG(marks_obtained/max_marks*100),1) FROM assessment_scores WHERE student_id={$student['id']} AND academic_year_id={$py['id']} AND status IN ('approved','published') AND max_marks>0")->fetchColumn();
        }catch(Throwable $e){$pyAvg=null;}
        $pyCol=$pyAvg!==null?($pyAvg>=70?'var(--green)':($pyAvg>=50?'var(--warning)':'var(--error)')):'var(--ink-soft)';
      ?>
      <div class="panel" style="padding:18px;text-align:center;border-top:3px solid <?=$pyCol?>">
        <div style="font-size:12px;font-weight:700;color:var(--ink-soft);margin-bottom:6px"><?=e($py['name'])?></div>
        <div style="font-size:1.8rem;font-weight:800;color:<?=$pyCol?>"><?=$pyAvg!==null?$pyAvg.'%':'—'?></div>
        <a href="my_results.php?ay=<?=$py['id']?>" style="font-size:12px;color:var(--primary);margin-top:6px;display:block">View details →</a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── ACADEMIC PROGRESS ─────────────────────────── -->
  <?php if (!empty($prevYears)): ?>
  <div style="margin-top:20px" class="panel" style="padding:20px">
    <div style="padding:20px 20px 0">
      <h3 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:12px">📊 Academic Progress Over Years</h3>
    </div>
    <div style="padding:16px 20px 20px;display:flex;align-items:flex-end;gap:8px;height:80px">
      <?php
      $allYears=array_merge($prevYears,[['id'=>$ayId,'name'=>$ay]]);
      usort($allYears,fn($a,$b)=>$a['id']<=>$b['id']);
      $maxAvg=100;
      foreach($allYears as $yr):
          try{$yAvg=(float)$pdo->query("SELECT COALESCE(ROUND(AVG(marks_obtained/max_marks*100),1),0) FROM assessment_scores WHERE student_id={$student['id']} AND academic_year_id={$yr['id']} AND status IN ('approved','published') AND max_marks>0")->fetchColumn();}catch(Throwable $e){$yAvg=0;}
          $h=max(4,round($yAvg/$maxAvg*68));
          $col=$yAvg>=70?'var(--green)':($yAvg>=50?'var(--warning)':'var(--error)');
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px">
        <span style="font-size:9px;color:var(--ink-soft)"><?=$yAvg>0?$yAvg.'%':'—'?></span>
        <div style="width:100%;height:<?=$h?>px;background:<?=$yAvg>0?$col:'var(--line)'?>;border-radius:3px 3px 0 0;opacity:.85"></div>
        <span style="font-size:9px;color:var(--ink-soft);white-space:nowrap"><?=e($yr['name'])?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
