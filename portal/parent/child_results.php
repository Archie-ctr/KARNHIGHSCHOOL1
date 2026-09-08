<?php
// ============================================================
// Parent Portal — Child Grades & Results
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('parent');

$activePage = 'results';
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();

include __DIR__.'/includes/resolve_child.php';

$subjects  = []; $bySubject = []; $configs = [];

if ($child) {
    try {
        $subs = db()->prepare(
            "SELECT DISTINCT s.id, s.name
             FROM assessment_scores asc2
             JOIN subjects s ON s.id = asc2.subject_id
             WHERE asc2.student_id=? AND asc2.academic_year_id=? ORDER BY s.name"
        );
        $subs->execute([$child['id'], $ayId]);
        $subjects = $subs->fetchAll();

        foreach ($subjects as $sub) {
            $sc = db()->prepare(
                "SELECT ac.name cfg_name, asc2.marks_obtained, asc2.max_marks
                 FROM assessment_scores asc2
                 JOIN assessment_configs ac ON ac.id = asc2.assessment_config_id
                 WHERE asc2.student_id=? AND asc2.subject_id=? AND asc2.academic_year_id=?
                   AND asc2.status IN ('submitted','approved')
                 ORDER BY ac.sequence"
            );
            $sc->execute([$child['id'], $sub['id'], $ayId]);
            $bySubject[$sub['id']] = $sc->fetchAll();
        }

        foreach ($subjects as $sub) {
            foreach ($bySubject[$sub['id']] as $sc) {
                $configs[$sc['cfg_name']] = $sc['cfg_name'];
            }
        }
    } catch (Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Results — Parent Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <div class="page-heading">
    <div>
      <h1><?= $child ? e($child['first_name'])."'s" : "Child's" ?> Grades &amp; Results</h1>
      <p><?= e($ay) ?></p>
    </div>
    <?php if ($child): ?>
    <a href="report_card.php?child_id=<?= $child['id'] ?>" class="button button-secondary button-sm">📑 Report Card</a>
    <?php endif; ?>
  </div>

  <?php if (empty($children)): ?>
  <div class="alert alert-warning">No children linked. Please contact the school registrar.</div>

  <?php elseif (!$child): ?>
  <div class="alert alert-warning">Child not found. <a href="<?= BASE_URL ?>/portal/parent/">Go back</a>.</div>

  <?php elseif (empty($subjects)): ?>
  <div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
    <div style="font-size:40px;margin-bottom:12px">📊</div>
    <h3 style="margin-bottom:6px">No results yet</h3>
    <p style="color:var(--ink-soft)">Results for <?= e($ay) ?> will appear here once marks are submitted by teachers.</p>
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
          <th style="padding:12px 10px;color:#fff;text-align:center">Average</th>
          <th style="padding:12px 10px;color:#fff;text-align:center">Grade</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($subjects as $sub):
          $cfgVals = [];
          foreach ($bySubject[$sub['id']] as $sc) $cfgVals[$sc['cfg_name']] = $sc;
          $vals = array_filter(array_map(
              fn($sc) => ($sc['max_marks'] > 0 && $sc['marks_obtained'] !== null)
                          ? round($sc['marks_obtained'] / $sc['max_marks'] * 100, 1) : null,
              $bySubject[$sub['id']]
          ), fn($v) => $v !== null);
          $avg = count($vals) ? round(array_sum($vals) / count($vals), 1) : null;
          $gl  = $avg !== null ? gradeLetter($avg, $ayId) : '—';
          $glColor = in_array($gl, ['A','B','C','D']) ? 'var(--green)' : 'var(--error)';
        ?>
        <tr style="border-bottom:1px solid var(--line-soft)">
          <td style="padding:10px 16px;font-weight:600"><?= e($sub['name']) ?></td>
          <?php foreach ($configs as $cfg):
            $d   = $cfgVals[$cfg] ?? null;
            $pct = ($d && $d['max_marks'] > 0 && $d['marks_obtained'] !== null)
                   ? round($d['marks_obtained'] / $d['max_marks'] * 100, 1) : null;
          ?>
          <td style="padding:10px;text-align:center;<?= $pct !== null && $pct < 50 ? 'color:var(--error)' : '' ?>">
            <?= $pct !== null ? $pct.'%' : '<span style="color:var(--line)">—</span>' ?>
          </td>
          <?php endforeach; ?>
          <td style="padding:10px;text-align:center;font-weight:700"><?= $avg !== null ? $avg.'%' : '—' ?></td>
          <td style="padding:10px;text-align:center">
            <span style="font-weight:800;font-size:15px;color:<?= $glColor ?>"><?= $gl ?></span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p style="margin-top:10px;font-size:12px;color:var(--ink-faint)">
    ℹ️ Only submitted or approved marks are shown. Grades follow the school's grading scale.
  </p>
  <?php endif; ?>

</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
