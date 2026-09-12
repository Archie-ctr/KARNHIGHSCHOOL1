<?php
// ============================================================
// Official Academic Transcript — KARN HIGH SCHOOL
// Format: Liberian school transcript style
//   - Subjects as rows, grade-year columns (10th, 11th, 12th)
//   - General Average row at bottom
//   - Principal's signature + official stamp
// URL: /letters/transcript_pdf.php?student_id=N
// ============================================================
require_once dirname(__DIR__).'/config/db.php';
requireAuth();

$pdo   = db();
$stdId = (int)($_GET['student_id'] ?? 0);
if (!$stdId) { http_response_code(400); die('Invalid request.'); }

// ── Access control ────────────────────────────────────────────
if (isStudent()) {
    $myId = (int)$pdo->query("SELECT id FROM students WHERE user_id=".currentUser()['id']." LIMIT 1")->fetchColumn();
    if ($myId !== $stdId) { http_response_code(403); die('Access denied.'); }
}
if (hasRole('parent')) {
    $gRow = $pdo->prepare("SELECT id FROM guardians WHERE user_id=? LIMIT 1");
    $gRow->execute([currentUser()['id']]);
    $gid  = (int)($gRow->fetchColumn() ?: 0);
    $ok   = $gid ? (int)$pdo->query("SELECT COUNT(*) FROM student_guardians WHERE guardian_id=$gid AND student_id=$stdId")->fetchColumn() : 0;
    if (!$ok) { http_response_code(403); die('Access denied.'); }
}

// ── Student record ────────────────────────────────────────────
$student = $pdo->query(
    "SELECT s.*, g.name grade_name, g.id grade_id, g.sequence grade_seq
     FROM students s
     LEFT JOIN grades g ON g.id = s.current_grade_id
     WHERE s.id = $stdId LIMIT 1"
)->fetch();
if (!$student) { http_response_code(404); die('Student not found.'); }

// ── Determine which grade columns to show ─────────────────────
// For Grade 12 students: show 10th, 11th, 12th
// For Grade 11: show 9th (if any), 10th, 11th
// For others: show all years they have data for (up to 3 most recent)
$allYears = $pdo->query(
    "SELECT DISTINCT ay.id, ay.name ay_name, ay.start_date,
            g.name grade_name, g.sequence grade_seq, g.id grade_id
     FROM annual_results ar
     JOIN academic_years ay ON ay.id  = ar.academic_year_id
     JOIN classes        c  ON c.id   = ar.class_id
     JOIN grades         g  ON g.id   = c.grade_id
     WHERE ar.student_id = $stdId
     ORDER BY g.sequence ASC"
)->fetchAll();

// Build index: grade_id -> year info
$yearsByGrade = [];
foreach ($allYears as $yr) {
    $yearsByGrade[$yr['grade_id']] = $yr;
}

// Pick the 3 columns to display: last 3 grades with data
// For Grade 12 we want Grade 10 / Grade 11 / Grade 12
$colGrades = array_slice($allYears, -3, 3); // last 3 by grade sequence

// If student is Grade 12 (grade_id=13) force columns 11,12,13
$isGrade12 = ($student['grade_id'] == 13);

// Collect all subject names across all columns (union)
$subjectSet = [];
foreach ($colGrades as $col) {
    $rows = $pdo->query(
        "SELECT DISTINCT sub.name, sub.id
         FROM annual_results ar
         JOIN subjects sub ON sub.id = ar.subject_id
         JOIN classes   c  ON c.id  = ar.class_id
         WHERE ar.student_id = $stdId
           AND ar.academic_year_id = {$col['id']}
         ORDER BY sub.name"
    )->fetchAll();
    foreach ($rows as $r) {
        $subjectSet[$r['name']] = $r['name'];
    }
}
ksort($subjectSet);
$subjects = array_keys($subjectSet);

// Build score matrix: subject -> [grade_id -> score]
$matrix = [];
$colAvgs = []; // grade_id -> general average

foreach ($colGrades as $col) {
    $gid = $col['grade_id'];
    $scores = $pdo->query(
        "SELECT sub.name, ar.yearly_average, ar.grade_letter
         FROM annual_results ar
         JOIN subjects sub ON sub.id = ar.subject_id
         JOIN classes   c  ON c.id  = ar.class_id
         WHERE ar.student_id = $stdId
           AND ar.academic_year_id = {$col['id']}"
    )->fetchAll();

    $total = 0; $cnt = 0;
    foreach ($scores as $sc) {
        $matrix[$sc['name']][$gid] = [
            'avg'   => $sc['yearly_average'],
            'grade' => $sc['grade_letter'],
        ];
        if ($sc['yearly_average'] !== null) { $total += $sc['yearly_average']; $cnt++; }
    }
    $colAvgs[$gid] = $cnt ? round($total / $cnt, 1) : null;
}

// ── School settings ────────────────────────────────────────────
$school  = setting('school_name',    'KARN HIGH SCHOOL');
$address = setting('school_address', 'Karnplay, Nimba County, Liberia');
$phone   = setting('school_phone',   '+231 886 417 711');
$email   = setting('school_email',   'info@karnhighschool.edu.lr');
$motto   = setting('school_motto',   'Excellence in Education');
$principal = setting('principal_name', 'Principal');

$fullName = trim(($student['first_name']??'').' '.($student['middle_name']??'').' '.($student['last_name']??''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Official Transcript — <?= e($fullName) ?></title>
  <style>
    * { box-sizing:border-box; margin:0; padding:0 }
    body {
      font-family: Arial, sans-serif;
      background: #d8d8d8;
      padding: 20px;
      font-size: 11pt;
      color: #111;
    }
    .page {
      background: #fff;
      max-width: 680px;
      margin: 0 auto 30px;
      padding: 28px 32px 32px;
      box-shadow: 0 3px 16px rgba(0,0,0,.22);
      border: 1.5px solid #999;
      position: relative;
      overflow: hidden;
    }

    /* ── Watermark ── */
    .watermark {
      position: absolute;
      top: 50%; left: 50%;
      transform: translate(-50%,-50%) rotate(-40deg);
      font-size: 88pt;
      font-weight: 900;
      color: rgba(26,39,68,.055);
      white-space: nowrap;
      letter-spacing: .08em;
      pointer-events: none;
      user-select: none;
      z-index: 0;
    }

    /* ── Header ── */
    .header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding-bottom: 12px;
      border-bottom: 3px double #1a2744;
      margin-bottom: 10px;
      position: relative; z-index:1;
    }
    .header-logo { width: 68px; height: 68px; object-fit: contain }
    .header-center { text-align: center; flex: 1; padding: 0 10px }
    .header-center h1 {
      font-size: 20pt; font-weight: 900;
      letter-spacing: .06em; text-transform: uppercase;
      color: #1a2744; line-height: 1.1;
    }
    .header-center .sub {
      font-size: 9pt; color: #555; margin-top: 2px; line-height: 1.5
    }
    .header-center .motto { font-style:italic; font-size:9pt; color:#777; margin-top:3px }

    /* ── Doc title block ── */
    .doc-title-block {
      text-align: center;
      margin: 14px 0 10px;
      position: relative; z-index:1;
    }
    .doc-title-block .office {
      font-size: 12pt; font-weight: 700;
      text-transform: uppercase;
      color: #1a2744; letter-spacing:.05em;
    }
    .doc-title-block .title {
      font-size: 14pt; font-weight: 900;
      text-transform: uppercase;
      color: #1a2744; letter-spacing: .1em;
      border-top: 1.5px solid #1a2744;
      border-bottom: 1.5px solid #1a2744;
      padding: 4px 0; margin-top: 4px;
    }

    /* ── Student info line ── */
    .student-info {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      font-size: 11pt;
      margin: 10px 0 14px;
      position: relative; z-index:1;
    }
    .info-field {
      display: flex;
      align-items: baseline;
      gap: 6px;
      flex: 1;
      min-width: 180px;
    }
    .info-label { font-weight: 700; white-space: nowrap; font-size: 10.5pt }
    .info-value {
      border-bottom: 1.5px solid #333;
      flex: 1;
      padding-bottom: 1px;
      font-size: 11pt;
      min-width: 80px;
    }

    /* ── Transcript table ── */
    .transcript-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 8px;
      position: relative; z-index:1;
    }
    .transcript-table th, .transcript-table td {
      border: 1.5px solid #333;
      padding: 5px 8px;
      text-align: center;
      font-size: 11pt;
    }
    .transcript-table th { background: #fff; font-weight: 700; }
    .transcript-table td.subject-col {
      text-align: left;
      font-size: 10.5pt;
      padding-left: 10px;
    }
    .transcript-table tr.avg-row td {
      font-weight: 900;
      border-top: 2.5px solid #333;
      font-size: 11pt;
      background: #f5f7ff;
    }
    .transcript-table tr:nth-child(even) td { background: #fafafa }
    .transcript-table tr.avg-row td { background: #eef2ff }

    /* ── Signature section ── */
    .sig-section {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      margin-top: 32px;
      gap: 20px;
      position: relative; z-index:1;
    }
    .sig-box { text-align: center; flex: 1 }
    .sig-line {
      border-bottom: 1.5px solid #333;
      margin: 0 10px 5px;
      height: 44px;
    }
    .sig-label { font-size: 10pt; font-weight: 700 }
    .sig-sub   { font-size: 8.5pt; color: #666 }

    .stamp-box {
      width: 100px; height: 100px;
      border: 2px dashed #aaa;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #bbb;
      font-size: 8pt;
      text-align: center;
      margin: 0 auto;
    }

    /* ── Footer ── */
    .footer {
      margin-top: 18px;
      border-top: 1px solid #ccc;
      padding-top: 8px;
      font-size: 8pt;
      color: #888;
      text-align: center;
      line-height: 1.6;
      position: relative; z-index:1;
    }

    /* Print */
    @media print {
      body { background: #fff; padding: 0 }
      .page { box-shadow: none; border: none; margin: 0; padding: 18px 22px }
      .no-print { display: none !important }
    }
  </style>
</head>
<body>

<!-- Toolbar -->
<div class="no-print" style="max-width:680px;margin:0 auto 12px;display:flex;justify-content:flex-end;gap:8px">
  <button onclick="window.print()"
    style="padding:8px 20px;background:#1a2744;color:#fff;border:none;border-radius:5px;font-size:13px;font-weight:700;cursor:pointer">
    🖨 Print / Save PDF
  </button>
  <a href="javascript:history.back()"
    style="padding:8px 16px;background:#6c757d;color:#fff;border-radius:5px;font-size:13px;font-weight:700;text-decoration:none">
    ← Back
  </a>
</div>

<div class="page">
  <div class="watermark">OFFICIAL</div>

  <!-- ── Header ── -->
  <div class="header">
    <img class="header-logo"
         src="<?= BASE_URL ?>/assets/images/logo.png" alt="KHS"
         onerror="this.style.display='none'"/>
    <div class="header-center">
      <h1><?= e($school) ?></h1>
      <div class="sub">
        <?= e($address) ?><br>
        <?php if ($phone): ?>Tel: <?= e($phone) ?><?php endif; ?>
        <?php if ($email): ?> &nbsp;|&nbsp; <?= e($email) ?><?php endif; ?>
      </div>
      <div class="motto">"<?= e($motto) ?>"</div>
    </div>
    <img class="header-logo"
         src="<?= BASE_URL ?>/assets/images/logo.png" alt="KHS"
         onerror="this.style.display='none'"/>
  </div>

  <!-- ── Title ── -->
  <div class="doc-title-block">
    <div class="office">Office of the Principal</div>
    <div class="title">Official Transcript</div>
  </div>

  <!-- ── Student Info ── -->
  <div class="student-info">
    <div class="info-field" style="flex:2">
      <span class="info-label">Name of Student:</span>
      <span class="info-value"><?= e(strtoupper($fullName)) ?></span>
    </div>
    <div class="info-field" style="flex:0 0 auto;min-width:120px">
      <span class="info-label">Grade:</span>
      <span class="info-value"><?= e($student['grade_name'] ?? '—') ?></span>
    </div>
  </div>
  <div class="student-info" style="margin-top:0">
    <div class="info-field" style="flex:0 0 auto;min-width:180px">
      <span class="info-label">Student ID:</span>
      <span class="info-value"><?= e($student['student_id'] ?? '—') ?></span>
    </div>
    <div class="info-field" style="flex:0 0 auto;min-width:160px">
      <span class="info-label">Date:</span>
      <span class="info-value"><?= date('d/m/Y') ?></span>
    </div>
    <div class="info-field" style="flex:0 0 auto;min-width:140px">
      <span class="info-label">Gender:</span>
      <span class="info-value"><?= e($student['gender'] ?? '—') ?></span>
    </div>
  </div>

  <!-- ── Transcript Table ── -->
  <?php if (empty($colGrades) || empty($subjects)): ?>
  <div style="text-align:center;padding:40px;color:#888;border:1px dashed #ccc;border-radius:4px;margin-top:12px">
    <strong>No academic records found for this student.</strong><br>
    <small>Records will appear once grades have been finalised.</small>
  </div>
  <?php else: ?>
  <table class="transcript-table">
    <thead>
      <tr>
        <th style="text-align:left;padding-left:10px;width:38%">Subject</th>
        <?php foreach ($colGrades as $col): ?>
        <th><?= e($col['grade_name']) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($subjects as $subName): ?>
      <tr>
        <td class="subject-col"><?= e($subName) ?></td>
        <?php foreach ($colGrades as $col):
          $gid  = $col['grade_id'];
          $cell = $matrix[$subName][$gid] ?? null;
          $val  = $cell ? (float)$cell['avg'] : null;
        ?>
        <td>
          <?php if ($val !== null): ?>
            <?= number_format($val, 0) ?>
          <?php else: ?>
            <span style="color:#bbb">—</span>
          <?php endif; ?>
        </td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>

      <!-- General Average row -->
      <tr class="avg-row">
        <td class="subject-col">Gen. Ave</td>
        <?php foreach ($colGrades as $col): ?>
        <td>
          <?php if ($colAvgs[$col['grade_id']] !== null): ?>
            <strong><?= number_format($colAvgs[$col['grade_id']], 1) ?></strong>
          <?php else: ?>
            —
          <?php endif; ?>
        </td>
        <?php endforeach; ?>
      </tr>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- ── Signature Section ── -->
  <div class="sig-section">
    <div class="sig-box" style="flex:2">
      <div class="sig-line"></div>
      <div class="sig-label">Signed: ___________________________</div>
      <div class="sig-sub">Business Manager / Registrar</div>
    </div>
    <div class="sig-box" style="flex:0 0 100px">
      <div class="stamp-box">OFFICIAL<br>STAMP</div>
    </div>
  </div>

  <div style="margin-top:16px;position:relative;z-index:1">
    <div style="display:flex;align-items:baseline;gap:8px">
      <span style="font-weight:700;font-size:10.5pt">Approved:</span>
      <span style="border-bottom:1.5px solid #333;flex:1;min-width:200px;padding-bottom:1px">&nbsp;</span>
    </div>
    <div style="margin-top:4px;font-size:9pt;color:#555">Principal / Head of Institution</div>
  </div>

  <!-- ── Footer ── -->
  <div class="footer">
    This is an official academic transcript issued by <strong><?= e($school) ?></strong>,
    <?= e($address) ?>.<br>
    Any alteration renders this document invalid.
    Issued: <?= date('d F Y') ?> &nbsp;&middot;&nbsp; Student ID: <?= e($student['student_id']) ?>
  </div>

</div><!-- .page -->
</body>
</html>
