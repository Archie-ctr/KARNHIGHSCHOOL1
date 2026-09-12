<?php
// ============================================================
// WASSCE / School Clearance — KARN HIGH SCHOOL
// Issued to students in Grade 3, 6, 9 and 12 only.
// URL: /letters/wassce_clearance.php?student_id=N
// ============================================================
require_once dirname(__DIR__).'/config/db.php';
require_once dirname(__DIR__).'/includes/doc_verify_helper.php';
requireAuth();
requireStaff();

$pdo   = db();
$stdId = (int)($_GET['student_id'] ?? 0);
if (!$stdId) { http_response_code(400); die('Invalid request.'); }

// ── Student ───────────────────────────────────────────────────
$student = $pdo->query(
    "SELECT s.*, g.name grade_name, g.id grade_id, g.sequence grade_seq,
            c.name class_name, ay.name ay_name, ay.id ay_id,
            ay.start_date ay_start, ay.end_date ay_end
     FROM students s
     LEFT JOIN grades  g  ON g.id  = s.current_grade_id
     LEFT JOIN classes c  ON c.id  = s.current_class_id
     LEFT JOIN academic_years ay ON ay.is_current = 1
     WHERE s.id = $stdId LIMIT 1"
)->fetch();

if (!$student) { http_response_code(404); die('Student not found.'); }

// ── Restrict to Grade 3, 6, 9, 12 ────────────────────────────
$allowedGradeIds = [4, 7, 10, 13]; // Grade 3=4, 6=7, 9=10, 12=13
if (!in_array((int)$student['grade_id'], $allowedGradeIds)) {
    http_response_code(403);
    die('<div style="font-family:sans-serif;padding:48px;text-align:center">
        <h2>Not eligible</h2>
        <p>WASSCE Clearance is only issued to students in Grade 3, 6, 9, and 12.</p>
        <a href="javascript:history.back()">← Back</a>
    </div>');
}

// ── WASSCE results for current year ──────────────────────────
$results = $pdo->query(
    "SELECT sub.name subject_name, sub.code subject_code,
            ar.yearly_average, ar.grade_letter, ar.passed,
            ar.sem1_average, ar.sem2_average
     FROM annual_results ar
     JOIN subjects sub ON sub.id = ar.subject_id
     JOIN classes   c  ON c.id  = ar.class_id
     WHERE ar.student_id = $stdId
       AND ar.academic_year_id = {$student['ay_id']}
     ORDER BY sub.name"
)->fetchAll();

// Overall average and pass status
$totalAvg = 0; $cntSubj = 0; $allPassed = true;
foreach ($results as $r) {
    if ($r['yearly_average'] !== null) { $totalAvg += $r['yearly_average']; $cntSubj++; }
    if (!$r['passed']) $allPassed = false;
}
$genAvg    = $cntSubj ? round($totalAvg / $cntSubj, 1) : null;
$overallStatus = $allPassed ? 'Passed' : 'Not all passed';

// ── Candidate ID (student_id or generated) ───────────────────
$candidateId = $student['student_id'] ?? ('KHS-'.date('Y').'-'.str_pad($stdId,7,'0',STR_PAD_LEFT));

// ── Academic year range ───────────────────────────────────────
$yearStart = $student['ay_start'] ? date('Y', strtotime($student['ay_start'])) : date('Y');
$yearEnd   = $student['ay_end']   ? date('Y', strtotime($student['ay_end']))   : (int)$yearStart+1;

// ── School settings ───────────────────────────────────────────
$school    = setting('school_name',    'KARN HIGH SCHOOL');
$address   = 'KARNPLAY CITY, GBEHLAY-GEH DISTRICT';
$county    = 'NIMBA COUNTY, REPUBLIC OF LIBERIA';
$phone     = setting('school_phone',   '+231 0777-812-687/0880-574-466');
$principal = setting('principal_name', 'Mr. Mike Karnar');
$vPrincipal= setting('vice_principal_name', 'Mr. Jonathan Mulbah');
$registrar = setting('registrar_name',  'Mr. Bertin Bolenie');

$fullName  = strtoupper(trim(($student['first_name']??'').' '.($student['middle_name']??'').' '.($student['last_name']??'')));
$pronoun   = strtolower($student['gender']??'male') === 'female' ? 'she' : 'he';
$pronoun2  = strtolower($student['gender']??'male') === 'female' ? 'her' : 'his';

// ── Grade level label ─────────────────────────────────────────
$gradeLabel = match($student['grade_id']) {
    4  => 'Grade Three (3)',
    7  => 'Grade Six (6)',
    10 => 'Grade Nine (9)',
    13 => 'Grade Twelve (12)',
    default => $student['grade_name'],
};
$examLabel = match($student['grade_id']) {
    4  => 'National Primary School Leaving Examination',
    7  => 'National Junior High School Examination',
    10 => 'National West African Examination (WAEC)',
    13 => 'West African Senior School Certificate Examination (WASSCE)',
    default => 'National Examination',
};

$refNumber = 'SC-'.date('Y').'-'.str_pad($stdId,4,'0',STR_PAD_LEFT).'-G'.$student['grade_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>School Clearance — <?= e($fullName) ?></title>
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
      max-width: 720px;
      margin: 0 auto 30px;
      padding: 28px 36px 32px;
      box-shadow: 0 3px 16px rgba(0,0,0,.18);
      position: relative;
      overflow: hidden;
    }
    /* Border lines matching the KHS template */
    .page::before {
      content: '';
      position: absolute;
      inset: 8px;
      border: 2px solid #333;
      pointer-events: none;
    }
    /* Logo watermark */
    .wm-logo {
      position: absolute;
      top: 50%; left: 50%;
      transform: translate(-50%,-50%);
      width: 55%;
      opacity: .06;
      pointer-events: none;
      z-index: 0;
    }
    /* All content above watermark */
    .page > *:not(.wm-logo) { position: relative; z-index: 1 }

    /* Header */
    .hdr { display:flex; align-items:center; justify-content:space-between; margin-bottom:6px }
    .hdr-logo { width:64px; height:64px; object-fit:contain }
    .hdr-center { text-align:center; flex:1; padding:0 12px }
    .school-name { font-size:22pt; font-weight:900; letter-spacing:.04em; line-height:1.1; color:#111 }
    .school-sub  { font-size:9pt; color:#333; margin-top:3px; line-height:1.6 }
    .hdr-divider { border:none; border-top:3px solid #111; margin:8px 0 4px }

    /* Date line */
    .date-line { font-size:10.5pt; margin-bottom:10px }
    .date-line .label { font-weight:700 }
    .underline { border-bottom:1.5px solid #333; display:inline-block; min-width:160px; padding-bottom:1px }

    /* Section title */
    .section-title {
      text-align:center; font-size:12pt; font-weight:900;
      text-decoration:underline; text-transform:uppercase;
      margin:10px 0 8px; letter-spacing:.04em;
    }

    /* Body text */
    .body-text { font-size:10.5pt; line-height:1.85; margin-bottom:10px }
    .body-text .ul { border-bottom:1.5px solid #333; display:inline-block; padding-bottom:1px; min-width:100px }

    /* WASSCE results table */
    .results-title {
      text-align:center; font-size:11pt; font-weight:900;
      text-decoration:underline; text-transform:uppercase;
      margin:14px 0 8px; letter-spacing:.04em;
    }
    .candidate-row { font-size:10pt; margin-bottom:8px }
    .candidate-row strong { font-weight:700 }

    .results-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 3px 20px;
      margin-bottom:10px;
    }
    .result-item {
      display: flex;
      align-items: baseline;
      gap: 4px;
      font-size: 10.5pt;
      border-bottom: 1px solid #bbb;
      padding-bottom: 2px;
    }
    .result-item .sub  { flex:1; font-size:10pt }
    .result-item .grade{ font-weight:700; min-width:30px; text-align:right }

    .status-row {
      font-size:11pt; font-weight:700; margin:8px 0 4px;
      display:flex; align-items:center; gap:8px;
    }

    /* Motto */
    .motto { font-style:italic; font-size:9.5pt; color:#444; text-align:center; margin:10px 0 }

    /* Signatures */
    .sig-section {
      display:grid; grid-template-columns:1fr 1fr; gap:20px;
      margin-top:24px;
    }
    .sig-box { text-align:center }
    .sig-line-space { height:44px }
    .sig-line { border-top:1.5px solid #333; padding-top:5px; font-size:10pt; font-weight:700 }
    .sig-sub  { font-size:9pt; color:#555 }
    .stamp-circle {
      width:80px; height:80px;
      border:2px dashed #555; border-radius:50%;
      display:inline-flex; align-items:center; justify-content:center;
      color:#999; font-size:7pt; text-align:center; margin-top:4px;
    }

    @media print {
      body { background:#fff; padding:0 }
      .page { box-shadow:none; max-width:none; padding:14mm 18mm; margin:0 }
      .no-print { display:none !important }
    }
  </style>
</head>
<body>

<!-- Toolbar -->
<div class="no-print" style="max-width:720px;margin:0 auto 12px;display:flex;justify-content:flex-end;gap:8px">
  <button onclick="window.print()" style="padding:8px 20px;background:#111;color:#fff;border:none;border-radius:5px;font-size:13px;font-weight:700;cursor:pointer">🖨 Print / Save PDF</button>
  <a href="javascript:history.back()" style="padding:8px 16px;background:#6c757d;color:#fff;border-radius:5px;font-size:13px;font-weight:700;text-decoration:none">← Back</a>
</div>

<div class="page">
  <img class="wm-logo"
       src="<?= BASE_URL ?>/assets/images/logo.png" alt=""
       onerror="this.src='<?= BASE_URL ?>/assets/images/logo.jpg'"/>

  <!-- ── Header ── -->
  <div class="hdr">
    <img class="hdr-logo"
         src="<?= BASE_URL ?>/assets/images/logo.png" alt="KHS"
         onerror="this.src='<?= BASE_URL ?>/assets/images/logo.jpg'"/>
    <div class="hdr-center">
      <div class="school-name"><?= e($school) ?></div>
      <div class="school-sub">
        <?= e($address) ?><br>
        <?= e($county) ?><br>
        Cell: <?= e($phone) ?>
      </div>
    </div>
    <img class="hdr-logo"
         src="<?= BASE_URL ?>/assets/images/logo.png" alt="KHS"
         onerror="this.src='<?= BASE_URL ?>/assets/images/logo.jpg'"/>
  </div>
  <hr class="hdr-divider"/>

  <!-- Date -->
  <div class="date-line">
    <span class="label">Date:</span>
    <span class="underline">&nbsp;&nbsp;<?= date('F d, Y') ?>&nbsp;&nbsp;</span>
  </div>

  <!-- Section Title -->
  <div class="section-title">School Clearance</div>

  <!-- Body -->
  <div class="body-text">
    This certifies that student
    <span class="ul">&nbsp;<strong><?= e($fullName) ?></strong>&nbsp;</span>
    has satisfactorily completed the course of studies by this institution in collaboration
    with the Ministry of Education and <?= $pronoun ?>/she has successfully passed the
    <strong><?= e($examLabel) ?></strong>
    with the following grades listed below.
    Therefore, <?= $pronoun ?>/she is qualified for
    <?php if ($student['grade_id'] == 13): ?>
      <strong>graduation</strong>.
    <?php else: ?>
      <strong>promotion to the next grade level</strong>.
    <?php endif; ?>
  </div>

  <!-- WASSCE Results Section -->
  <div class="results-title">
    <?= $student['grade_id'] == 13 ? 'WASSCE Results' : 'Examination Results' ?>
  </div>

  <div class="candidate-row">
    <strong>CANDIDATE ID#:</strong>
    <span class="underline">&nbsp;<?= e($candidateId) ?>&nbsp;</span>
    &nbsp;&nbsp;&nbsp;
    <strong>Grade:</strong>
    <span class="underline">&nbsp;<?= e($gradeLabel) ?>&nbsp;</span>
  </div>

  <?php if (!empty($results)): ?>
  <div class="results-grid">
    <?php foreach ($results as $idx => $r):
      $grade = $r['grade_letter'] ?: ($r['yearly_average'] !== null ? (round($r['yearly_average']) >= 90 ? 'A' : (round($r['yearly_average']) >= 80 ? 'B' : (round($r['yearly_average']) >= 70 ? 'C' : (round($r['yearly_average']) >= 60 ? 'D' : 'F')))) : '—');
      // Format like template: "English 302: F8"
      $code = $r['subject_code'] ? $r['subject_name'].' '.$r['subject_code'] : $r['subject_name'];
    ?>
    <div class="result-item">
      <span class="sub"><?= e($code) ?>:</span>
      <span class="grade"><?= e($grade) ?><?= $r['yearly_average'] !== null ? ' ('.number_format((float)$r['yearly_average'],0).')' : '' ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="status-row">
    <span>Status:</span>
    <span class="underline">&nbsp;
      <?php if ($allPassed): ?>
        <span style="color:#155724">✓ Passed<?= $genAvg !== null ? ' — Gen. Avg: '.$genAvg.'%' : '' ?></span>
      <?php else: ?>
        <span style="color:#721c24">Not Cleared</span>
      <?php endif; ?>
    &nbsp;</span>
  </div>
  <?php else: ?>
  <div style="padding:20px;text-align:center;color:#888;border:1px dashed #ccc;border-radius:4px;margin:10px 0">
    No examination results recorded for the current academic year.
  </div>
  <?php endif; ?>

  <!-- Motto -->
  <div class="motto">May God richly bless us as we strive for education.</div>

  <!-- QR Verification -->
  <?= docVerifyStrip('school_clearance', $stdId,
      $fullName, $student['grade_name']??'', $student['ay_name']??'',
      $refNumber, currentUserId(), null, '+5 years') ?>

  <!-- Signatures -->
  <div class="sig-section">
    <div class="sig-box">
      <div class="sig-line-space"></div>
      <div class="sig-line"><?= e($vPrincipal) ?></div>
      <div class="sig-sub">Vice Principal/Inst. (VEI)</div>
      <div class="sig-sub"><?= e($school) ?></div>
      <div class="stamp-circle">OFFICIAL<br>STAMP</div>
    </div>
    <div class="sig-box">
      <div class="sig-line-space"></div>
      <div class="sig-line"><?= e($principal) ?></div>
      <div class="sig-sub">Principal</div>
      <div class="sig-sub"><?= e($school) ?></div>
      <div class="stamp-circle">OFFICIAL<br>STAMP</div>
    </div>
  </div>

  <!-- Footer -->
  <div style="margin-top:14px;padding-top:8px;border-top:1px solid #ccc;font-size:8pt;color:#888;text-align:center">
    <?= e($school) ?> &nbsp;&bull;&nbsp; <?= e($address) ?>, <?= e($county) ?><br>
    Ref: <?= e($refNumber) ?> &nbsp;&bull;&nbsp; Issued: <?= date('d F Y') ?>
  </div>

</div><!-- .page -->
</body>
</html>
