<?php
// ============================================================
// Diploma — KARN HIGH SCHOOL (Grade 12 only)
// Matches the actual KHS diploma format from the school's records.
// URL: /letters/diploma_pdf.php?student_id=N
// ============================================================
require_once dirname(__DIR__).'/config/db.php';
require_once dirname(__DIR__).'/includes/doc_verify_helper.php';
requireAuth();

$pdo   = db();
$stdId = (int)($_GET['student_id'] ?? 0);
$ayId  = (int)($_GET['ay_id'] ?? currentAcademicYearId());
if (!$stdId) { http_response_code(400); die('Invalid request.'); }

// ── Access control ────────────────────────────────────────────
if (isStudent()) {
    $myId = (int)$pdo->query("SELECT id FROM students WHERE user_id=".currentUser()['id']." LIMIT 1")->fetchColumn();
    if ($myId !== $stdId) { http_response_code(403); die('Access denied.'); }
}

// ── Student record ────────────────────────────────────────────
$student = $pdo->query(
    "SELECT s.*, g.name grade_name, g.id grade_id
     FROM students s
     LEFT JOIN grades g ON g.id = s.current_grade_id
     WHERE s.id = $stdId LIMIT 1"
)->fetch();
if (!$student) { http_response_code(404); die('Student not found.'); }
if ((int)$student['grade_id'] !== 13) {
    die('<div style="font-family:sans-serif;padding:48px;text-align:center">
        <h2>Diploma is only issued to Grade 12 graduates.</h2>
        <a href="javascript:history.back()">← Back</a></div>');
}

// ── Graduation record ─────────────────────────────────────────
$grad = [];
try {
    $grad = $pdo->query(
        "SELECT * FROM graduations WHERE student_id=$stdId AND academic_year_id=$ayId LIMIT 1"
    )->fetch() ?: [];
} catch (Throwable $e) {}

$certNum   = $grad['certificate_number'] ?? ('KHS-G12-'.$ayId.'-'.str_pad($stdId,4,'0',STR_PAD_LEFT));
$gradDate  = $grad['graduation_date'] ?? null;
$issueDate = $gradDate ? date('j', strtotime($gradDate)) : date('j');
$issueMonth= $gradDate ? date('F', strtotime($gradDate)) : date('F');
$issueYear = $gradDate ? date('Y', strtotime($gradDate)) : date('Y');
// Ordinal suffix
$suffix    = match((int)$issueDate % 10) {
    1 => ((int)$issueDate === 11 ? 'th' : 'st'),
    2 => ((int)$issueDate === 12 ? 'th' : 'nd'),
    3 => ((int)$issueDate === 13 ? 'th' : 'rd'),
    default => 'th',
};

// ── School settings ───────────────────────────────────────────
$school     = setting('school_name',      'KARN HIGH SCHOOL');
$address    = setting('school_address',   'Karnplay City, Nimba County');
$country    = 'Republic of Liberia, West Africa';
$registrar  = setting('registrar_name',   'Registrar');
$principal  = setting('principal_name',   'Mrs. Mike Karnar');

$studentFullName = strtoupper(trim(
    ($student['first_name']??'').' '.
    ($student['middle_name'] ? $student['middle_name'].' ' : '').
    ($student['last_name']??'')
));

$refNumber = 'DIP-'.$ayId.'-'.str_pad($stdId,4,'0',STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Diploma — <?= e($studentFullName) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700;900&family=IM+Fell+English:ital@0;1&display=swap" rel="stylesheet"/>
  <style>
    * { box-sizing:border-box; margin:0; padding:0 }
    body {
      background: #c8b89a;
      padding: 20px;
      display: flex;
      flex-direction: column;
      align-items: center;
      font-family: 'IM Fell English', Georgia, serif;
    }

    /* ── Diploma frame ── */
    .diploma-wrap {
      position: relative;
      width: 820px;
      background: #fffdf7;
      box-shadow: 0 8px 40px rgba(0,0,0,.5);
      border: 6px solid #8B6914;
    }
    /* Pink outer decorative border like the KHS original */
    .diploma-wrap::before {
      content: '';
      position: absolute;
      inset: 10px;
      border: 2.5px solid #c9823a;
      pointer-events: none;
      z-index: 2;
    }
    .diploma-wrap::after {
      content: '';
      position: absolute;
      inset: 16px;
      border: 1px solid #e8b86d;
      pointer-events: none;
      z-index: 2;
    }

    /* ── Inner content ── */
    .diploma-inner {
      position: relative;
      padding: 44px 64px 38px;
      z-index: 3;
      min-height: 560px;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    /* Logo watermark */
    .dip-wm-logo {
      position: absolute;
      top: 50%; left: 50%;
      transform: translate(-50%,-50%);
      width: 52%;
      opacity: .055;
      pointer-events: none;
      z-index: 0;
    }
    .diploma-inner > *:not(.dip-wm-logo) { position: relative; z-index: 1 }

    /* Corner ornaments */
    .corner { position:absolute; font-size:24px; color:#8B6914; opacity:.7; z-index:3 }
    .c-tl { top:20px;  left:24px  }
    .c-tr { top:20px;  right:24px }
    .c-bl { bottom:20px; left:24px  }
    .c-br { bottom:20px; right:24px }

    /* ── Header ── */
    .dip-header {
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 22px;
      margin-bottom: 12px;
      padding-bottom: 10px;
      border-bottom: 2px solid #C9A227;
    }
    .dip-logo {
      width: 70px; height: 70px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #8B6914;
      box-shadow: 0 2px 8px rgba(139,105,20,.3);
    }
    .dip-school-name {
      font-family: 'Cinzel', Georgia, serif;
      font-size: 26pt;
      font-weight: 900;
      color: #3a1f00;
      letter-spacing: .06em;
      text-shadow: 1px 1px 0 rgba(201,162,39,.4);
      line-height: 1.1;
    }
    .dip-school-sub {
      font-family: Arial, sans-serif;
      font-size: 9.5pt;
      color: #5a3400;
      text-align: center;
      line-height: 1.6;
      margin-top: 2px;
    }

    /* ── Certifies that ── */
    .dip-certifies {
      font-family: 'IM Fell English', Georgia, serif;
      font-style: italic;
      font-size: 13pt;
      color: #3a1f00;
      margin: 14px 0 6px;
      text-align: center;
    }

    /* ── Student name ── */
    .dip-name {
      font-family: 'Cinzel', Georgia, serif;
      font-size: 20pt;
      font-weight: 700;
      color: #8B1a00;
      letter-spacing: .08em;
      text-align: center;
      padding: 8px 40px;
      border-bottom: 2px solid #C9A227;
      border-top: 2px solid #C9A227;
      margin: 4px 0 14px;
      width: 100%;
      text-shadow: 1px 1px 0 rgba(201,162,39,.2);
    }

    /* ── Body text ── */
    .dip-body {
      font-family: 'IM Fell English', Georgia, serif;
      font-size: 11.5pt;
      color: #2a1400;
      text-align: center;
      line-height: 1.75;
      margin-bottom: 6px;
      max-width: 560px;
    }

    /* ── DIPLOMA title ── */
    .dip-title {
      font-family: 'Cinzel', Georgia, serif;
      font-size: 28pt;
      font-weight: 900;
      color: #8B6914;
      letter-spacing: .15em;
      text-align: center;
      margin: 6px 0 14px;
      text-shadow: 2px 2px 0 rgba(201,162,39,.3);
    }

    /* ── Witness text ── */
    .dip-witness {
      font-family: 'IM Fell English', Georgia, serif;
      font-style: italic;
      font-size: 10.5pt;
      color: #3a1f00;
      text-align: center;
      line-height: 1.7;
      margin-bottom: 14px;
      max-width: 480px;
    }

    /* ── Date line ── */
    .dip-date {
      font-family: 'IM Fell English', Georgia, serif;
      font-size: 12pt;
      color: #3a1f00;
      text-align: center;
      margin-bottom: 18px;
    }
    .dip-date sup { font-size: 8pt }

    /* ── Cert number ── */
    .cert-num {
      font-family: Arial, sans-serif;
      font-size: 8pt;
      color: #888;
      text-align: center;
      margin-bottom: 14px;
      letter-spacing: .04em;
    }

    /* ── Seal / medal ── */
    .dip-seal {
      width: 72px; height: 72px;
      border-radius: 50%;
      border: 3px double #C9A227;
      display: flex;
      align-items: center;
      justify-content: center;
      background: radial-gradient(circle, #fdf5dc, #fde8a0);
      box-shadow: 0 2px 10px rgba(139,105,20,.4);
      margin: 0 auto 16px;
    }

    /* ── Signatures ── */
    .dip-sigs {
      width: 100%;
      display: grid;
      grid-template-columns: 1fr auto 1fr;
      align-items: end;
      gap: 20px;
      margin-top: 4px;
    }
    .sig-col { text-align: center }
    .sig-space { height: 48px }
    .sig-line-rule { border-top: 1.5px solid #5a3400; padding-top: 5px }
    .sig-label { font-family:Arial,sans-serif; font-size:10pt; font-weight:700; color:#3a1f00 }
    .sig-sub   { font-family:Arial,sans-serif; font-size:8.5pt; color:#666 }
    .stamp-circle {
      width:80px; height:80px; border-radius:50%;
      border:2px dashed #8B6914;
      display:inline-flex; align-items:center;
      justify-content:center; color:#aaa; font-size:7pt;
      text-align:center;
    }

    /* ── QR + disclaimer ── */
    .dip-disclaimer {
      font-family:Arial,sans-serif;
      font-size:7.5pt; color:#9a8a6a;
      text-align:center; font-style:italic;
      margin-top:10px;
    }

    @media print {
      body { background:#fff; padding:0 }
      .diploma-wrap { box-shadow:none; width:100% }
      .no-print { display:none !important }
    }
  </style>
</head>
<body>

<!-- Toolbar -->
<div class="no-print" style="width:820px;margin:0 auto 12px;display:flex;justify-content:flex-end;gap:8px">
  <button onclick="window.print()" style="padding:8px 20px;background:#8B6914;color:#fff;border:none;border-radius:5px;font-size:13px;font-weight:700;cursor:pointer">🖨 Print / Save PDF</button>
  <a href="javascript:history.back()" style="padding:8px 16px;background:#6c757d;color:#fff;border-radius:5px;font-size:13px;font-weight:700;text-decoration:none">← Back</a>
</div>

<div class="diploma-wrap">
  <!-- Corner ornaments -->
  <div class="corner c-tl">❧</div>
  <div class="corner c-tr" style="transform:scaleX(-1)">❧</div>
  <div class="corner c-bl" style="transform:scaleY(-1)">❧</div>
  <div class="corner c-br" style="transform:scale(-1,-1)">❧</div>

  <div class="diploma-inner">
    <img class="dip-wm-logo"
         src="<?= BASE_URL ?>/assets/images/logo.png" alt=""
         onerror="this.src='<?= BASE_URL ?>/assets/images/logo.jpg'"/>

    <!-- Header -->
    <div class="dip-header">
      <img class="dip-logo"
           src="<?= BASE_URL ?>/assets/images/logo.png" alt="KHS"
           onerror="this.src='<?= BASE_URL ?>/assets/images/logo.jpg'"/>
      <div style="text-align:center">
        <div class="dip-school-name"><?= e($school) ?></div>
        <div class="dip-school-sub">
          <?= e($address) ?><br>
          <?= e($country) ?>
        </div>
      </div>
      <img class="dip-logo"
           src="<?= BASE_URL ?>/assets/images/logo.png" alt="KHS"
           onerror="this.src='<?= BASE_URL ?>/assets/images/logo.jpg'"/>
    </div>

    <!-- Certifies that -->
    <div class="dip-certifies">This certifies that:</div>

    <!-- Student name -->
    <div class="dip-name"><?= e($studentFullName) ?></div>

    <!-- Body -->
    <div class="dip-body">
      Has satisfactorily completed the work of grade twelve as prescribed by the
      Ministry of Education and faculty of <?= e($school) ?> and is awarded this
    </div>

    <!-- DIPLOMA title -->
    <div class="dip-title">DIPLOMA</div>

    <!-- Witness text -->
    <div class="dip-witness">
      In witness whereof, our signatures are hereby affixed at
      <?= e($address) ?>,<br>
      <?= e($country) ?>.
    </div>

    <!-- Date -->
    <div class="dip-date">
      This <?= $issueDate ?><sup><?= $suffix ?></sup> Day of <?= $issueMonth ?>
      &nbsp;&nbsp; AD <?= $issueYear ?>
    </div>

    <!-- Cert number -->
    <div class="cert-num">
      Certificate No: <?= e($certNum) ?> &nbsp;&bull;&nbsp;
      <?= e($school) ?> &nbsp;&bull;&nbsp; <?= e($address) ?>
    </div>

    <!-- Seal -->
    <div class="dip-seal">
      <img src="<?= BASE_URL ?>/assets/images/logo.png"
           style="width:58px;height:58px;border-radius:50%;object-fit:cover"
           onerror="this.outerHTML='<span style=font-size:26px>🏅</span>'"/>
    </div>

    <!-- Signatures -->
    <div class="dip-sigs">
      <div class="sig-col">
        <div class="sig-space"></div>
        <div class="sig-line-rule">
          <div class="sig-label">Signed:</div>
          <div class="sig-sub"><?= e($registrar) ?></div>
          <div class="sig-sub">Registrar</div>
        </div>
        <div class="stamp-circle">OFFICIAL<br>STAMP</div>
      </div>
      <div style="text-align:center;padding-bottom:8px">
        <!-- centre decorative element -->
        <div style="font-size:28px;color:#C9A227">✦</div>
      </div>
      <div class="sig-col">
        <div class="sig-space"></div>
        <div class="sig-line-rule">
          <div class="sig-label">Approved:</div>
          <div class="sig-sub"><?= e($principal) ?></div>
          <div class="sig-sub">Principal</div>
        </div>
        <div class="stamp-circle">OFFICE OF<br>THE PRINCIPAL</div>
      </div>
    </div>

    <!-- Disclaimer + QR -->
    <div class="dip-disclaimer">
      This diploma is an official document of <?= e($school) ?>.
      Any unauthorized alteration, reproduction or misuse is prohibited under Liberian law.
    </div>

    <?= docVerifyStrip('diploma', $stdId, $studentFullName, 'Grade 12',
        currentAcademicYearName(), $certNum,
        isLoggedIn() ? currentUserId() : null, null, '+50 years') ?>

  </div><!-- .diploma-inner -->
</div><!-- .diploma-wrap -->

</body>
</html>
