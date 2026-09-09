<?php
// ============================================================
// Parent Portal — Child Gradesheet & Diploma
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('parent');

$activePage = 'report_card';
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();

include __DIR__.'/includes/resolve_child.php';

$isGr12 = $child && ($child['current_grade_id']??0) == 13;

$rc = null; $subjects = []; $scoreData = [];

if ($child) {
    try {
        $rc = db()->query(
            "SELECT * FROM report_cards WHERE student_id={$child['id']} AND academic_year_id=$ayId LIMIT 1"
        )->fetch() ?: null;
    } catch (Throwable $e) {}

    if ($rc) {
        try {
            $subs = db()->prepare(
                "SELECT DISTINCT s.id, s.name FROM assessment_scores asc2
                 JOIN subjects s ON s.id = asc2.subject_id
                 WHERE asc2.student_id=? AND asc2.academic_year_id=? ORDER BY s.name"
            );
            $subs->execute([$child['id'], $ayId]);
            $subjects = $subs->fetchAll();

            foreach ($subjects as $sub) {
                $sc = db()->prepare(
                    "SELECT ac.name, ac.sequence, asc2.marks_obtained, asc2.max_marks
                     FROM assessment_scores asc2
                     JOIN assessment_configs ac ON ac.id = asc2.assessment_config_id
                     WHERE asc2.student_id=? AND asc2.subject_id=? AND asc2.academic_year_id=?
                     ORDER BY ac.sequence"
                );
                $sc->execute([$child['id'], $sub['id'], $ayId]);
                $scoreData[$sub['id']] = $sc->fetchAll();
            }
        } catch (Throwable $e) {}
    }
}
$schoolName = setting('school_name', 'KARN HIGH SCHOOL');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Gradesheet &amp; Diploma — Parent Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <div class="page-heading">
    <div>
      <h1><?= $child ? e($child['first_name'])."'s" : "Child's" ?> Gradesheet<?= $isGr12 ? ' &amp; Diploma' : '' ?></h1>
      <p><?= e($ay) ?></p>
    </div>
    <?php if ($rc && $rc['status'] === 'published'): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="<?= BASE_URL ?>/letters/gradesheet_pdf.php?student_id=<?= $child['id'] ?>&ay_id=<?= $ayId ?>"
         class="button button-secondary button-sm" target="_blank">📋 View Gradesheet PDF</a>
      <?php if ($isGr12): ?>
      <a href="<?= BASE_URL ?>/letters/diploma_pdf.php?student_id=<?= $child['id'] ?>&ay_id=<?= $ayId ?>"
         class="button button-primary button-sm" target="_blank"
         style="background:#1a6b2a;border-color:#1a6b2a">🎓 View Diploma</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php if (empty($children)): ?>
  <div class="alert alert-warning">No children linked. Please contact the school registrar.</div>

  <?php elseif (!$child): ?>
  <div class="alert alert-warning">Child not found. <a href="<?= BASE_URL ?>/portal/parent/">Go back</a>.</div>

  <?php elseif (!$rc || $rc['status'] === 'draft'): ?>
  <div style="background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);padding:48px;text-align:center">
    <div style="font-size:40px;margin-bottom:12px">📋</div>
    <h3 style="margin-bottom:6px">Gradesheet not yet available</h3>
    <p style="color:var(--ink-soft)">
      <?= $child ? e($child['first_name'])."'s" : "The" ?> gradesheet for <?= e($ay) ?> has not been published yet.
      Please check back at the end of the academic year or contact the school office.
    </p>
  </div>

  <?php else: ?>
  <div id="rcView" class="report-card-paper">
    <div class="rc-header">
      <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="KHS" class="rc-logo"/>
      <div style="flex:1">
        <div class="rc-school-name"><?= e($schoolName) ?></div>
        <div class="rc-subtitle">Karnplay, Nimba County, Liberia</div>
        <div style="font-size:14px;font-weight:700;margin-top:6px">STUDENT GRADESHEET &mdash; <?= e($ay) ?></div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 20px;font-size:12px;margin-bottom:14px;padding:10px;background:var(--bg);border-radius:6px">
      <div><strong>Student:</strong> <?= e(trim($child['first_name'].($child['middle_name']?' '.$child['middle_name']:'').' '.$child['last_name'])) ?></div>
      <div><strong>Student ID:</strong> <?= e($child['student_id']) ?></div>
      <div><strong>Grade / Class:</strong> <?= e($child['grade_name'] ?? '') ?> / <?= e($child['class_name'] ?? '') ?></div>
      <div><strong>Academic Year:</strong> <?= e($ay) ?></div>
    </div>

    <?php if (!empty($subjects)): ?>
    <table class="rc-table">
      <thead>
        <tr>
          <th rowspan="2" style="text-align:left">Subject</th>
          <th colspan="4">Semester 1</th>
          <th colspan="4">Semester 2</th>
          <th rowspan="2">Avg</th>
          <th rowspan="2">Grade</th>
        </tr>
        <tr>
          <th>1st P</th><th>2nd P</th><th>3rd P</th><th>Sem Exam</th>
          <th>4th P</th><th>5th P</th><th>6th P</th><th>Sem Exam</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($subjects as $sub):
          $cols = ['1st Period'=>'—','2nd Period'=>'—','3rd Period'=>'—','Semester 1 Examination'=>'—','4th Period'=>'—','5th Period'=>'—','6th Period'=>'—','Semester 2 Examination'=>'—'];
          $vals = [];
          foreach ($scoreData[$sub['id']] as $sc) {
              foreach (array_keys($cols) as $k) {
                  if (stripos($sc['name'],$k) !== false || $sc['name'] === $k) {
                      $pct = ($sc['max_marks'] > 0) ? round($sc['marks_obtained'] / $sc['max_marks'] * 100, 1) : null;
                      $cols[$k] = $pct ?? '—';
                      if ($pct !== null) $vals[] = $pct;
                  }
              }
          }
          $avg = count($vals) ? round(array_sum($vals)/count($vals), 1) : null;
          $gl  = $avg !== null ? gradeLetter($avg, $ayId) : '—';
        ?>
        <tr>
          <td style="text-align:left;font-weight:600"><?= e($sub['name']) ?></td>
          <?php foreach ($cols as $v): ?><td><?= $v ?></td><?php endforeach; ?>
          <td><strong><?= $avg ?? '—' ?></strong></td>
          <td style="color:<?= in_array($gl,['A','B','C','D'])?'var(--green)':'var(--error)' ?>;font-weight:700"><?= $gl ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:14px 0;font-size:12px">
      <div style="text-align:center;padding:8px;background:var(--bg);border-radius:6px"><strong style="display:block;font-size:16px"><?= $rc['days_present']??0 ?></strong>Days Present</div>
      <div style="text-align:center;padding:8px;background:var(--bg);border-radius:6px"><strong style="display:block;font-size:16px"><?= $rc['days_absent']??0 ?></strong>Days Absent</div>
      <div style="text-align:center;padding:8px;background:var(--bg);border-radius:6px"><strong style="display:block;font-size:16px"><?= $rc['days_tardy']??0 ?></strong>Times Tardy</div>
      <div style="text-align:center;padding:8px;background:var(--primary-soft);border-radius:6px"><strong style="display:block;font-size:16px;color:var(--primary)"><?= $rc['yearly_average']?round($rc['yearly_average'],1).'%':'—' ?></strong>Overall Avg</div>
    </div>

    <?php if ($rc['teacher_comment'] || $rc['principal_comment']): ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:12px;margin-bottom:12px">
      <?php if ($rc['teacher_comment']): ?><div style="padding:8px;border:1px solid var(--line);border-radius:5px"><strong>Teacher:</strong> <?= nl2br(e($rc['teacher_comment'])) ?></div><?php endif; ?>
      <?php if ($rc['principal_comment']): ?><div style="padding:8px;border:1px solid var(--line);border-radius:5px"><strong>Principal:</strong> <?= nl2br(e($rc['principal_comment'])) ?></div><?php endif; ?>
    </div>
    <?php endif; ?>

    <div style="font-size:13px;font-weight:700;color:var(--primary);margin-bottom:14px">Promotion: <?= e($rc['promotion_status']??'Pending') ?></div>
    <div class="rc-signature">
      <div class="rc-sig-line">Class Teacher</div>
      <div class="rc-sig-line">Academic Dean</div>
      <div class="rc-sig-line">Principal</div>
    </div>
    <div style="text-align:center;margin-top:12px;font-size:10px;color:var(--ink-faint)"><?= e($schoolName) ?> &middot; Karnplay, Nimba, Liberia</div>
  </div>
  <?php endif; ?>

</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
