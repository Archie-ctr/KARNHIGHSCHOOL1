<?php
// ============================================================
// Student Portal — Gradesheet & Diploma
// Report card = Gradesheet (all students)
// Diploma = Grade 12 students only (issued at year end)
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('student');

$pdo        = db();
$user       = currentUser();
$activePage = 'report_card';
$ayId       = currentAcademicYearId();
$ay         = currentAcademicYearName();

$student = $pdo->prepare(
    "SELECT s.*, g.name grade_name, g.id grade_id, c.name class_name
     FROM students s
     LEFT JOIN grades  g ON g.id = s.current_grade_id
     LEFT JOIN classes c ON c.id = s.current_class_id
     WHERE s.user_id=? LIMIT 1"
);
$student->execute([$user['id']]);
$student = $student->fetch();
if (!$student) { redirect(BASE_URL.'/portal/student/'); }

$rc = $pdo->query(
    "SELECT * FROM report_cards
     WHERE student_id={$student['id']} AND academic_year_id=$ayId LIMIT 1"
)->fetch();

$isGr12 = ($student['grade_id'] == 13); // Grade 12

$subjects  = [];
$scoreData = [];
if ($rc) {
    $subs = $pdo->prepare(
        "SELECT DISTINCT s.id, s.name
         FROM assessment_scores asc2
         JOIN subjects s ON s.id = asc2.subject_id
         WHERE asc2.student_id=? AND asc2.academic_year_id=?
         ORDER BY s.name"
    );
    $subs->execute([$student['id'], $ayId]);
    $subjects = $subs->fetchAll();

    foreach ($subjects as $sub) {
        $sc = $pdo->prepare(
            "SELECT ac.name, ac.sequence, asc2.marks_obtained, asc2.max_marks
             FROM assessment_scores asc2
             JOIN assessment_configs ac ON ac.id = asc2.assessment_config_id
             WHERE asc2.student_id=? AND asc2.subject_id=? AND asc2.academic_year_id=?
             ORDER BY ac.sequence"
        );
        $sc->execute([$student['id'], $sub['id'], $ayId]);
        $scoreData[$sub['id']] = $sc->fetchAll();
    }
}

$schoolName = setting('school_name','KARN HIGH SCHOOL');
$ini = strtoupper(substr($student['first_name'],0,1).substr($student['last_name'],0,1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Gradesheet &amp; Diploma — Student Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <div class="page-heading">
    <div>
      <h1>Gradesheet<?= $isGr12 ? ' &amp; Diploma' : '' ?></h1>
      <p><?= e($student['grade_name'] ?? '') ?><?= $student['class_name'] ? ' / '.e($student['class_name']) : '' ?> &mdash; <?= e($ay) ?></p>
    </div>
    <?php if ($rc && $rc['status'] === 'published'): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <a href="<?= BASE_URL ?>/letters/gradesheet_pdf.php?student_id=<?= $student['id'] ?>&ay_id=<?= $ayId ?>"
         class="button button-secondary button-sm" target="_blank">📋 View Gradesheet PDF</a>
      <?php if ($isGr12): ?>
      <a href="<?= BASE_URL ?>/letters/diploma_pdf.php?student_id=<?= $student['id'] ?>&ay_id=<?= $ayId ?>"
         class="button button-primary button-sm" target="_blank"
         style="background:#1a6b2a;border-color:#1a6b2a">🎓 View Diploma PDF</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php foreach(getFlash() as $f):?><div class="alert alert-<?=$f['type']?>"><?=e($f['message'])?></div><?php endforeach;?>

  <!-- Grade 12 diploma notice -->
  <?php if ($isGr12 && $rc && $rc['status'] === 'published'): ?>
  <div style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border:1.5px solid #16a34a;border-radius:var(--radius-sm);padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
    <span style="font-size:32px">🎓</span>
    <div style="flex:1">
      <strong style="font-size:14.5px;color:#14532d;display:block;margin-bottom:3px">Congratulations, <?= e($student['first_name']) ?>! You are a Grade 12 Graduate.</strong>
      <p style="font-size:13px;color:#166534">You will receive both a Gradesheet and a Diploma at the end of this academic year. Both documents are now available below.</p>
    </div>
    <a href="<?= BASE_URL ?>/letters/diploma_pdf.php?student_id=<?= $student['id'] ?>&ay_id=<?= $ayId ?>"
       target="_blank" class="button button-primary" style="background:#1a6b2a;border-color:#1a6b2a;flex-shrink:0">🎓 Open Diploma</a>
  </div>
  <?php elseif ($isGr12): ?>
  <div class="alert alert-info" style="margin-bottom:16px">
    🎓 <strong>Grade 12 Diploma Notice:</strong> As a Grade 12 student, you will receive a Diploma at the end of the academic year in addition to your Gradesheet. It will be available once the Gradesheet is published.
  </div>
  <?php endif; ?>

  <?php if (!$rc || $rc['status'] === 'draft'): ?>
  <div style="background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);padding:48px;text-align:center">
    <div style="font-size:40px;margin-bottom:12px">📋</div>
    <h3 style="margin-bottom:6px">Gradesheet not yet available</h3>
    <p style="color:var(--ink-soft)">Your gradesheet for <?= e($ay) ?> has not been published yet. Please check back at the end of the academic year or contact the school office.</p>
  </div>

  <?php else: ?>

  <!-- Document selection tabs for Gr12 -->
  <?php if ($isGr12): ?>
  <?php $docTab = $_GET['doc'] ?? 'gradesheet'; ?>
  <div class="tab-bar" style="margin-bottom:16px">
    <a href="?doc=gradesheet" class="tab-btn <?= $docTab==='gradesheet'?'active':'' ?>">📋 Gradesheet</a>
    <a href="?doc=diploma"    class="tab-btn <?= $docTab==='diploma'   ?'active':'' ?>">🎓 Diploma</a>
  </div>
  <?php if ($docTab === 'diploma'):?>
  <!-- Diploma preview frame -->
  <div class="panel" style="padding:24px;text-align:center">
    <h3 style="font-weight:700;margin-bottom:8px;color:#1a6b2a">🎓 Grade 12 Diploma</h3>
    <p style="color:var(--ink-soft);font-size:13.5px;margin-bottom:16px">
      Your official diploma is ready. Open it in a new tab to print or save as PDF.
    </p>
    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
      <a href="<?= BASE_URL ?>/letters/diploma_pdf.php?student_id=<?= $student['id'] ?>&ay_id=<?= $ayId ?>"
         target="_blank" class="button button-primary" style="background:#1a6b2a;border-color:#1a6b2a;font-size:15px;padding:12px 28px">🎓 Open & Print Diploma</a>
      <a href="<?= BASE_URL ?>/letters/gradesheet_pdf.php?student_id=<?= $student['id'] ?>&ay_id=<?= $ayId ?>"
         target="_blank" class="button button-secondary">📋 Open Gradesheet</a>
    </div>
    <div style="margin-top:20px;padding:18px;background:var(--bg2);border-radius:var(--radius-sm);text-align:left;max-width:420px;margin-left:auto;margin-right:auto">
      <div style="font-size:12.5px;color:var(--ink-soft);display:flex;flex-direction:column;gap:6px">
        <div><strong>Student:</strong> <?= e(trim($student['first_name'].' '.$student['last_name'])) ?></div>
        <div><strong>Grade:</strong> <?= e($student['grade_name']??'Grade 12') ?></div>
        <div><strong>Academic Year:</strong> <?= e($ay) ?></div>
        <div><strong>Status:</strong> <span style="color:#1a6b2a;font-weight:700"><?= e($rc['promotion_status']??'Graduated') ?></span></div>
        <?php if ($rc['yearly_average']): ?>
        <div><strong>Final Average:</strong> <span style="color:#1a6b2a;font-weight:700"><?= round($rc['yearly_average'],1) ?>%</span></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php $docTab='diploma'; // skip gradesheet view below ?>
  <?php else: ?><?php $docTab='gradesheet'; ?>
  <?php endif;?>
  <?php endif; // isGr12 ?>

  <?php if (!$isGr12 || ($docTab??'gradesheet') === 'gradesheet'): ?>
  <!-- Gradesheet preview -->
  <div class="panel" style="padding:22px;margin-bottom:16px">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px">
      <h3 style="font-weight:700;font-size:15px">📋 Gradesheet — <?= e($ay) ?></h3>
      <div style="display:flex;gap:8px">
        <a href="<?= BASE_URL ?>/letters/gradesheet_pdf.php?student_id=<?= $student['id'] ?>&ay_id=<?= $ayId ?>"
           target="_blank" class="button button-primary button-sm">📋 Print / Save PDF</a>
      </div>
    </div>

    <!-- Student banner -->
    <div style="display:flex;align-items:center;gap:14px;padding:14px;background:var(--bg2);border-radius:var(--radius-sm);margin-bottom:14px;flex-wrap:wrap">
      <div class="avatar" style="width:46px;height:46px;font-size:16px;flex-shrink:0"><?= e($ini) ?></div>
      <div>
        <strong style="font-size:14.5px"><?= e(trim($student['first_name'].($student['middle_name']?' '.$student['middle_name']:'').' '.$student['last_name'])) ?></strong>
        <div style="font-size:12.5px;color:var(--ink-soft)"><?= e($student['student_id']) ?> &middot; <?= e($student['grade_name']??'') ?> <?= $student['class_name']?' / '.e($student['class_name']):'' ?></div>
      </div>
      <div style="margin-left:auto;text-align:right">
        <span class="status <?= $rc['promotion_status']==='Promoted'?'approved':($rc['promotion_status']==='Graduated'?'approved':'pending') ?>"><?= e($rc['promotion_status']??'Pending') ?></span>
        <?php if ($rc['yearly_average']): ?>
        <div style="font-size:12px;margin-top:4px;color:var(--ink-soft)">Average: <strong style="color:var(--primary)"><?= round($rc['yearly_average'],1) ?>%</strong></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Subject marks table -->
    <?php if (!empty($subjects)): ?>
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:12px;min-width:700px">
        <thead>
          <tr style="background:var(--primary)">
            <th style="padding:8px 10px;color:#fff;text-align:left;white-space:nowrap">Subject</th>
            <th style="padding:8px 6px;color:#fff;text-align:center;font-size:10px" colspan="4">Semester 1</th>
            <th style="padding:8px 6px;color:#fff;text-align:center;font-size:10px" colspan="4">Semester 2</th>
            <th style="padding:8px 8px;color:#fff;text-align:center">Avg%</th>
            <th style="padding:8px 8px;color:#fff;text-align:center">Grade</th>
          </tr>
          <tr style="background:#c4223c">
            <th style="padding:5px 10px;color:#fff;text-align:left;font-size:10px"></th>
            <th style="padding:5px 5px;color:#fff;font-size:10px;text-align:center">1st P</th>
            <th style="padding:5px 5px;color:#fff;font-size:10px;text-align:center">2nd P</th>
            <th style="padding:5px 5px;color:#fff;font-size:10px;text-align:center">3rd P</th>
            <th style="padding:5px 5px;color:#fff;font-size:10px;text-align:center">S1 Exam</th>
            <th style="padding:5px 5px;color:#fff;font-size:10px;text-align:center">4th P</th>
            <th style="padding:5px 5px;color:#fff;font-size:10px;text-align:center">5th P</th>
            <th style="padding:5px 5px;color:#fff;font-size:10px;text-align:center">6th P</th>
            <th style="padding:5px 5px;color:#fff;font-size:10px;text-align:center">S2 Exam</th>
            <th style="padding:5px;color:#fff;font-size:10px"></th>
            <th style="padding:5px;color:#fff;font-size:10px"></th>
          </tr>
        </thead>
        <tbody>
          <?php
          $colKeys=['1st Period','2nd Period','3rd Period','Semester 1 Examination','4th Period','5th Period','6th Period','Semester 2 Examination'];
          foreach ($subjects as $sub):
              $cols = array_fill_keys($colKeys,'—'); $vals=[];
              foreach ($scoreData[$sub['id']] as $sc) {
                  foreach ($colKeys as $k) {
                      if (stripos($sc['name'],$k) !== false) {
                          $p = ($sc['max_marks']>0)?round($sc['marks_obtained']/$sc['max_marks']*100,1):null;
                          $cols[$k]=$p??'—';
                          if($p!==null) $vals[]=$p;
                      }
                  }
              }
              $avg=count($vals)?round(array_sum($vals)/count($vals),1):null;
              $gl=$avg!==null?gradeLetter($avg,$ayId):'—';
          ?>
          <tr style="border-bottom:1px solid var(--line)">
            <td style="padding:7px 10px;font-weight:600"><?= e($sub['name']) ?></td>
            <?php foreach ($cols as $v): $nc=is_numeric($v)?($v<50?'color:var(--error)':($v>=70?'color:var(--green)':'')):''; ?>
            <td style="padding:6px 4px;text-align:center;font-size:12px;<?=$nc?>"><?= $v ?></td>
            <?php endforeach; ?>
            <td style="padding:6px;text-align:center;font-weight:700"><?= $avg??'—' ?></td>
            <td style="padding:6px;text-align:center;font-weight:800;color:<?= in_array($gl,['A','B','C','D'])?'var(--green)':'var(--error)' ?>"><?= $gl ?></td>
          </tr>
          <?php endforeach; ?>
          <tr style="background:var(--bg2);border-top:2px solid var(--primary);font-weight:700">
            <td style="padding:7px 10px">YEARLY AVERAGE</td>
            <td colspan="8" style="text-align:center;font-size:10.5px;color:var(--ink-soft)">Based on all assessment scores</td>
            <td style="padding:6px;text-align:center;font-size:14px;color:var(--primary);font-weight:800"><?= $rc['yearly_average']?round($rc['yearly_average'],1).'%':'—' ?></td>
            <td style="padding:6px;text-align:center;font-weight:800;font-size:14px;color:<?= ($rc['yearly_average']&&gradeLetter((float)$rc['yearly_average'],$ayId)!=='F')?'var(--green)':'var(--error)' ?>"><?= $rc['yearly_average']?gradeLetter((float)$rc['yearly_average'],$ayId):'—' ?></td>
          </tr>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <p style="color:var(--ink-faint);padding:16px;text-align:center">No assessment scores on record for <?= e($ay) ?>.</p>
    <?php endif; ?>

    <!-- Grading key -->
    <div style="font-size:11px;color:var(--ink-soft);margin-top:10px;display:flex;flex-wrap:wrap;gap:6px 16px;padding:6px 0;border-top:1px solid var(--line)">
      <strong>Grading Scale:</strong>
      <span>A = 90–100%</span><span>B = 80–89%</span><span>C = 70–79%</span>
      <span>D = 60–69%</span><span>E = 50–59%</span><span>F = Below 50%</span>
    </div>
  </div>

  <!-- Attendance summary -->
  <div class="metric-grid" style="margin-bottom:16px">
    <div class="metric-card"><div class="metric-top"><span>Days Present</span><div class="metric-icon">📆</div></div><strong><?= $rc['days_present']??0 ?></strong></div>
    <div class="metric-card"><div class="metric-top"><span>Days Absent</span><div class="metric-icon">❌</div></div><strong><?= $rc['days_absent']??0 ?></strong></div>
    <div class="metric-card"><div class="metric-top"><span>Times Tardy</span><div class="metric-icon">⏰</div></div><strong><?= $rc['days_tardy']??0 ?></strong></div>
    <div class="metric-card"><div class="metric-top"><span>Attendance Rate</span><div class="metric-icon">📊</div></div><strong><?= $rc['attendance_pct']??'—' ?>%</strong></div>
  </div>

  <!-- Teacher & Principal comments -->
  <?php if ($rc['teacher_comment'] || $rc['principal_comment'] || $rc['conduct']): ?>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
    <div class="panel" style="padding:16px">
      <div style="font-size:12px;font-weight:700;color:var(--primary);margin-bottom:4px">Conduct: <?= e($rc['conduct']??'—') ?></div>
      <strong style="font-size:13px;display:block;margin-bottom:6px">Class Teacher's Comment</strong>
      <p style="font-size:13px;color:var(--ink-soft);line-height:1.6"><?= nl2br(e($rc['teacher_comment']??'No comment.')) ?></p>
    </div>
    <div class="panel" style="padding:16px">
      <strong style="font-size:13px;display:block;margin-bottom:6px">Principal's Comment</strong>
      <p style="font-size:13px;color:var(--ink-soft);line-height:1.6"><?= nl2br(e($rc['principal_comment']??'No comment.')) ?></p>
    </div>
  </div>
  <?php endif; ?>
  <?php endif; // show gradesheet content ?>

  <?php endif; // rc published ?>

</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
