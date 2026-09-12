<?php
// ============================================================
// Official Academic Transcript — KARN HIGH SCHOOL
// URL: /letters/transcript_pdf.php?student_id=N
// ============================================================
require_once dirname(__DIR__).'/config/db.php';
requireAuth();

$pdo    = db();
$stdId  = (int)($_GET['student_id'] ?? 0);
if (!$stdId) { http_response_code(400); die('Invalid request.'); }

// ── Access control ────────────────────────────────────────────
if (isStudent()) {
    $myId = (int)$pdo->query("SELECT id FROM students WHERE user_id=".currentUser()['id']." LIMIT 1")->fetchColumn();
    if ($myId !== $stdId) { http_response_code(403); die('Access denied.'); }
}
if (hasRole('parent')) {
    $gid = (int)($pdo->prepare("SELECT id FROM guardians WHERE user_id=?")->execute([currentUser()['id']]) ?
        $pdo->query("SELECT id FROM guardians WHERE user_id=".currentUser()['id']." LIMIT 1")->fetchColumn() : 0);
    $ok  = $gid ? (int)$pdo->query("SELECT COUNT(*) FROM student_guardians WHERE guardian_id=$gid AND student_id=$stdId")->fetchColumn() : 0;
    if (!$ok) { http_response_code(403); die('Access denied.'); }
}

// ── Student record ────────────────────────────────────────────
$student = $pdo->query(
    "SELECT s.*, g.name grade_name, c.name class_name, g.id grade_id
     FROM students s
     LEFT JOIN grades  g ON g.id = s.current_grade_id
     LEFT JOIN classes c ON c.id = s.current_class_id
     WHERE s.id = $stdId LIMIT 1"
)->fetch();
if (!$student) { http_response_code(404); die('Student not found.'); }

// ── All academic years this student has results for ───────────
$years = $pdo->query(
    "SELECT DISTINCT ay.id, ay.name, ay.start_date
     FROM annual_results ar
     JOIN academic_years ay ON ay.id = ar.academic_year_id
     WHERE ar.student_id = $stdId
     ORDER BY ay.start_date"
)->fetchAll();

// ── Build results grouped by year ────────────────────────────
$transcript = [];
foreach ($years as $yr) {
    $rows = $pdo->query(
        "SELECT sub.name subject_name, sub.code subject_code,
                c.name class_name, g.name grade_name,
                ar.sem1_average, ar.sem2_average, ar.yearly_average,
                ar.grade_letter, ar.class_position, ar.subject_position, ar.passed
         FROM annual_results ar
         JOIN subjects sub ON sub.id = ar.subject_id
         JOIN classes   c  ON c.id  = ar.class_id
         JOIN grades    g  ON g.id  = c.grade_id
         WHERE ar.student_id = $stdId AND ar.academic_year_id = {$yr['id']}
         ORDER BY sub.name"
    )->fetchAll();

    if (empty($rows)) continue;

    // Compute GPA for this year
    $total = 0; $count = 0; $passed = 0;
    foreach ($rows as $r) {
        if ($r['yearly_average'] !== null) { $total += $r['yearly_average']; $count++; }
        if ($r['passed']) $passed++;
    }
    $gpa = $count ? round($total / $count, 2) : null;

    $transcript[] = [
        'year'    => $yr,
        'rows'    => $rows,
        'gpa'     => $gpa,
        'passed'  => $passed,
        'total'   => count($rows),
        'class'   => $rows[0]['class_name'] ?? '',
        'grade'   => $rows[0]['grade_name'] ?? '',
    ];
}

// ── School settings ───────────────────────────────────────────
$school  = setting('school_name',    'KARN HIGH SCHOOL');
$address = setting('school_address', 'Karnplay, Nimba County, Liberia');
$phone   = setting('school_phone',   '+231 886 417 711');
$email   = setting('school_email',   'info@karnhighschool.edu.lr');
$motto   = setting('school_motto',   'Excellence in Education');

// ── Grade letter helper ────────────────────────────────────────
function gradeColor(string $g): string {
    return match(strtoupper($g)) {
        'A','A+'=> '#155724',
        'B'    => '#004085',
        'C'    => '#856404',
        'D'    => '#721c24',
        default=> '#495057',
    };
}
function passedLabel(bool $p): string {
    return $p ? '<span style="color:#155724;font-weight:700">PASS</span>'
              : '<span style="color:#721c24;font-weight:700">FAIL</span>';
}

// ── GPA letter ────────────────────────────────────────────────
function gpaLetter(float $avg): string {
    if ($avg >= 90) return 'A+';
    if ($avg >= 80) return 'A';
    if ($avg >= 70) return 'B';
    if ($avg >= 60) return 'C';
    if ($avg >= 50) return 'D';
    return 'F';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Official Transcript — <?= e($student['first_name'].' '.$student['last_name']) ?></title>
  <style>
    * { box-sizing:border-box; margin:0; padding:0 }
    body { font-family:Arial,sans-serif; background:#e8e8e8; padding:20px; font-size:10.5pt; color:#1a1a1a }
    .page {
      background:#fff;
      max-width:760px;
      margin:0 auto 40px;
      padding:32px 36px;
      box-shadow:0 2px 12px rgba(0,0,0,.18);
      border:1px solid #bbb;
      page-break-after: always;
    }
    /* Header */
    .header { text-align:center; margin-bottom:18px; padding-bottom:14px; border-bottom:3px double #2c3e50 }
    .header img { height:72px; margin-bottom:8px }
    .header h1 { font-size:18pt; font-weight:900; letter-spacing:.04em; text-transform:uppercase; color:#1a2744 }
    .header h2 { font-size:11pt; font-weight:400; color:#555; margin:3px 0 2px }
    .header .motto { font-style:italic; font-size:9.5pt; color:#666 }
    .doc-title {
      text-align:center; margin:16px 0 14px;
      font-size:14pt; font-weight:900; letter-spacing:.12em;
      text-transform:uppercase; color:#1a2744;
      border:2px solid #1a2744; padding:7px 0;
    }
    /* Student info */
    .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:5px 24px; margin-bottom:16px; font-size:10pt }
    .info-grid span { display:block }
    .info-grid strong { color:#333 }
    /* Year section */
    .year-header {
      background:#1a2744; color:#fff;
      padding:6px 12px; font-size:10.5pt; font-weight:700;
      display:flex; justify-content:space-between; align-items:center;
      margin-top:16px;
    }
    .year-meta { font-size:9pt; opacity:.85 }
    /* Table */
    table { width:100%; border-collapse:collapse; margin-top:0; font-size:9.5pt }
    thead tr { background:#2c3e50; color:#fff }
    thead th { padding:5px 8px; text-align:left; font-weight:700; font-size:9pt }
    thead th.num { text-align:right }
    tbody tr:nth-child(even) { background:#f7f8fa }
    tbody tr:hover { background:#eef2ff }
    td { padding:5px 8px; border-bottom:1px solid #e0e0e0; vertical-align:middle }
    td.num { text-align:right; font-variant-numeric:tabular-nums }
    td.grade { text-align:center; font-weight:700; font-size:10pt }
    /* GPA row */
    .gpa-row td { background:#f0f4ff; font-weight:700; border-top:2px solid #2c3e50; font-size:9.5pt }
    /* Summary box */
    .summary {
      margin-top:20px; padding:12px 16px;
      border:1.5px solid #2c3e50; border-radius:4px;
      background:#f8f9fc; font-size:10pt;
    }
    .summary-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; text-align:center }
    .summary-item { padding:8px; background:#fff; border:1px solid #dde; border-radius:4px }
    .summary-item .val { font-size:15pt; font-weight:900; color:#1a2744 }
    .summary-item .lbl { font-size:8.5pt; color:#666; margin-top:2px }
    /* Signature */
    .sig-section { margin-top:30px; display:grid; grid-template-columns:1fr 1fr 1fr; gap:20px; font-size:9.5pt }
    .sig-box { text-align:center }
    .sig-line { border-bottom:1.5px solid #333; margin-bottom:5px; height:32px }
    .sig-label { font-size:8.5pt; color:#555 }
    /* Footer */
    .footer {
      margin-top:20px; padding-top:10px; border-top:1px solid #bbb;
      font-size:8pt; color:#888; text-align:center; line-height:1.6
    }
    .watermark {
      position:fixed; top:50%; left:50%; transform:translate(-50%,-50%) rotate(-45deg);
      font-size:70pt; font-weight:900; color:rgba(26,39,68,.06);
      pointer-events:none; white-space:nowrap; letter-spacing:.1em;
      z-index:0
    }
    @media print {
      body { background:#fff; padding:0 }
      .page { box-shadow:none; border:none; margin:0; padding:20px 24px }
      .no-print { display:none }
      .watermark { position:fixed }
    }
  </style>
</head>
<body>

<!-- Print button -->
<div class="no-print" style="max-width:760px;margin:0 auto 14px;display:flex;gap:10px;justify-content:flex-end">
  <button onclick="window.print()" style="padding:9px 22px;background:#1a2744;color:#fff;border:none;border-radius:5px;font-size:13px;font-weight:700;cursor:pointer">🖨 Print / Save PDF</button>
  <a href="javascript:history.back()" style="padding:9px 18px;background:#6c757d;color:#fff;border-radius:5px;font-size:13px;font-weight:700;text-decoration:none">← Back</a>
</div>

<div class="watermark">OFFICIAL</div>

<div class="page">

  <!-- ── School Header ── -->
  <div class="header">
    <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="KHS Logo" onerror="this.style.display='none'"/>
    <h1><?= e($school) ?></h1>
    <h2><?= e($address) ?> &nbsp;|&nbsp; <?= e($phone) ?></h2>
    <?php if ($email): ?><h2><?= e($email) ?></h2><?php endif; ?>
    <div class="motto">"<?= e($motto) ?>"</div>
  </div>

  <div class="doc-title">Official Academic Transcript</div>

  <!-- ── Student Info ── -->
  <div class="info-grid">
    <span><strong>Full Name:</strong> <?= e(trim($student['first_name'].' '.($student['middle_name']??'').' '.$student['last_name'])) ?></span>
    <span><strong>Student ID:</strong> <?= e($student['student_id']) ?></span>
    <span><strong>Date of Birth:</strong> <?= $student['date_of_birth'] ? date('d F Y', strtotime($student['date_of_birth'])) : '—' ?></span>
    <span><strong>Gender:</strong> <?= e($student['gender'] ?? '—') ?></span>
    <span><strong>Admission Date:</strong> <?= $student['admission_date'] ? date('d F Y', strtotime($student['admission_date'])) : '—' ?></span>
    <span><strong>Current Grade:</strong> <?= e($student['grade_name'] ?? '—') ?></span>
    <span><strong>Admission #:</strong> <?= e($student['admission_number'] ?? '—') ?></span>
    <span><strong>Status:</strong> <?= e($student['status'] ?? 'Active') ?></span>
  </div>

  <?php if (empty($transcript)): ?>
  <div style="text-align:center;padding:48px;color:#888;border:1px dashed #ccc;border-radius:6px;margin-top:16px">
    <div style="font-size:36px;margin-bottom:12px">📋</div>
    <strong>No academic records found for this student.</strong><br>
    <small>Results will appear here once grades have been entered and finalised.</small>
  </div>
  <?php else: ?>

  <?php
  // Overall summary across all years
  $overallTotal = 0; $overallCount = 0; $overallYears = count($transcript);
  foreach ($transcript as $t) {
      if ($t['gpa'] !== null) { $overallTotal += $t['gpa']; $overallCount++; }
  }
  $overallGPA = $overallCount ? round($overallTotal / $overallCount, 2) : null;
  ?>

  <!-- ── Per-year results ── -->
  <?php foreach ($transcript as $t): ?>
  <div class="year-header">
    <span>Academic Year: <?= e($t['year']['name']) ?></span>
    <span class="year-meta">Class: <?= e($t['class']) ?> &nbsp;|&nbsp; Grade: <?= e($t['grade']) ?></span>
  </div>
  <table>
    <thead>
      <tr>
        <th style="width:32%">Subject</th>
        <th class="num">Sem 1</th>
        <th class="num">Sem 2</th>
        <th class="num">Average</th>
        <th style="text-align:center">Grade</th>
        <th style="text-align:center">Result</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($t['rows'] as $r): ?>
      <tr>
        <td><?= e($r['subject_name']) ?></td>
        <td class="num"><?= $r['sem1_average'] !== null ? number_format((float)$r['sem1_average'],1) : '—' ?></td>
        <td class="num"><?= $r['sem2_average'] !== null ? number_format((float)$r['sem2_average'],1) : '—' ?></td>
        <td class="num"><strong><?= $r['yearly_average'] !== null ? number_format((float)$r['yearly_average'],1) : '—' ?></strong></td>
        <td class="grade" style="color:<?= gradeColor($r['grade_letter']??'') ?>">
          <?= e($r['grade_letter'] ?? '—') ?>
        </td>
        <td style="text-align:center"><?= passedLabel((bool)$r['passed']) ?></td>
      </tr>
      <?php endforeach; ?>
      <!-- GPA row -->
      <tr class="gpa-row">
        <td>Year Average (GPA)</td>
        <td class="num">—</td>
        <td class="num">—</td>
        <td class="num"><?= $t['gpa'] !== null ? number_format($t['gpa'],2) : '—' ?></td>
        <td class="grade" style="color:<?= gradeColor($t['gpa'] !== null ? gpaLetter($t['gpa']) : '') ?>">
          <?= $t['gpa'] !== null ? gpaLetter($t['gpa']) : '—' ?>
        </td>
        <td style="text-align:center">
          <?= $t['passed'] === $t['total']
            ? '<span style="color:#155724;font-weight:700">ALL PASS</span>'
            : '<span style="color:#856404;font-weight:700">'.$t['passed'].'/'.$t['total'].' PASS</span>'
          ?>
        </td>
      </tr>
    </tbody>
  </table>
  <?php endforeach; ?>

  <!-- ── Overall Summary ── -->
  <div class="summary" style="margin-top:22px">
    <div style="font-weight:700;font-size:10.5pt;margin-bottom:10px;text-transform:uppercase;letter-spacing:.06em;color:#1a2744">
      Academic Summary
    </div>
    <div class="summary-grid">
      <div class="summary-item">
        <div class="val"><?= $overallYears ?></div>
        <div class="lbl">Years on Record</div>
      </div>
      <div class="summary-item">
        <div class="val"><?= $overallGPA !== null ? number_format($overallGPA,2) : '—' ?></div>
        <div class="lbl">Cumulative GPA</div>
      </div>
      <div class="summary-item">
        <div class="val" style="color:<?= $overallGPA !== null ? gradeColor(gpaLetter($overallGPA)) : '#555' ?>">
          <?= $overallGPA !== null ? gpaLetter($overallGPA) : '—' ?>
        </div>
        <div class="lbl">Overall Grade</div>
      </div>
      <div class="summary-item">
        <div class="val"><?= e($student['grade_name'] ?? '—') ?></div>
        <div class="lbl">Current Grade</div>
      </div>
    </div>
  </div>

  <!-- ── Grading Scale ── -->
  <table style="margin-top:14px;width:100%;font-size:8.5pt;border:1px solid #dde">
    <thead><tr style="background:#f0f4ff">
      <th colspan="6" style="padding:5px 8px;text-align:left;font-size:9pt;color:#1a2744">Grading Scale</th>
    </tr>
    <tr style="background:#e8ecf5">
      <th style="padding:4px 8px;text-align:center">Grade</th>
      <th style="padding:4px 8px;text-align:center">A+</th>
      <th style="padding:4px 8px;text-align:center">A</th>
      <th style="padding:4px 8px;text-align:center">B</th>
      <th style="padding:4px 8px;text-align:center">C</th>
      <th style="padding:4px 8px;text-align:center">D / F</th>
    </tr></thead>
    <tbody><tr style="text-align:center">
      <td style="padding:4px 8px;font-weight:700">Range</td>
      <td style="padding:4px 8px">90 – 100</td>
      <td style="padding:4px 8px">80 – 89</td>
      <td style="padding:4px 8px">70 – 79</td>
      <td style="padding:4px 8px">60 – 69</td>
      <td style="padding:4px 8px">Below 60</td>
    </tr></tbody>
  </table>

  <!-- ── Signatures ── -->
  <div class="sig-section" style="margin-top:28px">
    <div class="sig-box">
      <div class="sig-line"></div>
      <div style="font-weight:700">Registrar</div>
      <div class="sig-label">Date: _________________</div>
    </div>
    <div class="sig-box">
      <div class="sig-line"></div>
      <div style="font-weight:700">Principal</div>
      <div class="sig-label">Date: _________________</div>
    </div>
    <div class="sig-box">
      <div class="sig-line"></div>
      <div style="font-weight:700">Official School Stamp</div>
      <div class="sig-label">&nbsp;</div>
    </div>
  </div>

  <?php endif; ?>

  <!-- ── Footer ── -->
  <div class="footer">
    This is an official academic transcript issued by <strong><?= e($school) ?></strong>.<br>
    Any alteration renders this document invalid. Issued: <?= date('d F Y') ?> &nbsp;|&nbsp;
    Student ID: <?= e($student['student_id']) ?> &nbsp;|&nbsp;
    Verified by the Office of the Registrar
  </div>

</div><!-- .page -->

</body>
</html>
