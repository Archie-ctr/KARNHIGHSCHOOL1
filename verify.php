<?php
// ============================================================
// Document Verification — PUBLIC (no login required)
// URL: /verify.php?t=TOKEN
// Scanned from QR code on any KHS official document
// ============================================================
require_once __DIR__.'/config/db.php';

$token  = trim($_GET['t'] ?? '');
$record = null;
$error  = null;
$school = setting('school_name',    'KARN HIGH SCHOOL');
$address= setting('school_address', 'Karnplay, Nimba County, Liberia');
$phone  = setting('school_phone',   '+231 886 417 711');
$email  = setting('school_email',   'info@karnhighschool.edu.lr');

$docTypeLabels = [
    'transcript'      => 'Academic Transcript',
    'recommendation'  => 'Letter of Recommendation',
    'gradesheet'      => 'Gradesheet / Report Card',
    'diploma'         => 'Diploma',
    'report_card'     => 'Official Report Card',
    'admission'       => 'Admission Letter',
];

if ($token !== '') {
    // Sanitise — token must be hex-like, max 64 chars
    if (!preg_match('/^[a-f0-9]{32,64}$/i', $token)) {
        $error = 'Invalid verification token format.';
    } else {
        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT dv.*, s.student_id sid_code
             FROM document_verifications dv
             LEFT JOIN students s ON s.id = dv.student_id
             WHERE dv.token = ? LIMIT 1"
        );
        $stmt->execute([$token]);
        $record = $stmt->fetch();

        if (!$record) {
            $error = 'No document found for this verification code. The document may not be genuine or the code may be incorrect.';
        } elseif ($record['is_revoked']) {
            $error = 'This document has been REVOKED by the issuing institution and is no longer valid.';
            $record = null;
        } elseif ($record['expires_at'] && strtotime($record['expires_at']) < time()) {
            $error = 'This document has EXPIRED. Please contact '.e($school).' for a re-issue.';
            $record = null;
        } else {
            // Increment verified count + last verified timestamp
            $pdo->prepare(
                "UPDATE document_verifications
                 SET verified_count = verified_count + 1, last_verified_at = NOW()
                 WHERE token = ?"
            )->execute([$token]);
            $record['verified_count']++;
        }
    }
}

$docLabel = $record ? ($docTypeLabels[$record['doc_type']] ?? ucfirst($record['doc_type'])) : '';
$isValid  = ($record !== null && !$error);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Document Verification — <?= e($school) ?></title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',Arial,sans-serif;background:#f0f2f5;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:30px 16px;color:#1a1a1a}
    .card{background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.12);width:100%;max-width:560px;overflow:hidden}
    /* Header */
    .card-header{background:#1a2744;padding:24px 28px;display:flex;align-items:center;gap:16px}
    .card-header img{width:52px;height:52px;border-radius:8px;object-fit:cover;flex-shrink:0}
    .card-header h1{color:#fff;font-size:15pt;font-weight:800;line-height:1.2}
    .card-header p{color:rgba(255,255,255,.6);font-size:10pt;margin-top:3px}
    /* Body */
    .card-body{padding:28px}
    /* Result banner */
    .result-banner{display:flex;align-items:center;gap:14px;padding:16px 20px;border-radius:8px;margin-bottom:22px;font-weight:700;font-size:13pt}
    .result-banner.valid{background:#d4edda;border:1.5px solid #28a745;color:#155724}
    .result-banner.invalid{background:#f8d7da;border:1.5px solid #dc3545;color:#721c24}
    .result-banner.neutral{background:#fff3cd;border:1.5px solid #ffc107;color:#856404}
    .result-icon{font-size:26px;flex-shrink:0}
    /* Info grid */
    .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px}
    .info-item{background:#f8f9fc;border-radius:8px;padding:12px 14px;border:1px solid #e8eaf0}
    .info-item .label{font-size:9pt;font-weight:700;color:#6c757d;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px}
    .info-item .value{font-size:11.5pt;font-weight:700;color:#1a2744}
    .info-item.full{grid-column:1/-1}
    /* Badge */
    .doc-type-badge{display:inline-block;background:#1a2744;color:#fff;padding:4px 12px;border-radius:20px;font-size:10pt;font-weight:700;margin-bottom:16px}
    /* Verification count */
    .verif-note{font-size:10pt;color:#6c757d;text-align:center;margin-top:16px;padding-top:16px;border-top:1px solid #eee}
    /* Search form */
    .search-form{margin-top:22px}
    .search-form label{display:block;font-size:11pt;font-weight:700;margin-bottom:8px;color:#1a2744}
    .search-row{display:flex;gap:8px}
    .search-row input{flex:1;padding:10px 14px;border:1.5px solid #ced4da;border-radius:7px;font-size:12pt;outline:none;transition:border .15s}
    .search-row input:focus{border-color:#1a2744}
    .search-row button{padding:10px 20px;background:#1a2744;color:#fff;border:none;border-radius:7px;font-size:12pt;font-weight:700;cursor:pointer;white-space:nowrap;transition:background .15s}
    .search-row button:hover{background:#2d3f6b}
    /* Footer */
    .card-footer{background:#f8f9fc;border-top:1px solid #eee;padding:14px 28px;font-size:9pt;color:#6c757d;text-align:center;line-height:1.7}
    @media(max-width:480px){.info-grid{grid-template-columns:1fr}.info-item.full{grid-column:1}}
  </style>
</head>
<body>

<div class="card">
  <!-- Header -->
  <div class="card-header">
    <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="KHS"
         onerror="this.src='<?= BASE_URL ?>/assets/images/logo.jpg'"/>
    <div>
      <h1><?= e($school) ?></h1>
      <p>Official Document Verification Portal</p>
    </div>
  </div>

  <div class="card-body">

    <?php if ($token === ''): ?>
    <!-- ── No token — show search form only ── -->
    <div class="result-banner neutral">
      <span class="result-icon">🔍</span>
      <div>Enter a verification code or scan a QR code from an official document.</div>
    </div>

    <?php elseif ($error): ?>
    <!-- ── Error / invalid ── -->
    <div class="result-banner invalid">
      <span class="result-icon">❌</span>
      <div>
        <div>Document Not Verified</div>
        <div style="font-size:10pt;font-weight:400;margin-top:4px"><?= e($error) ?></div>
      </div>
    </div>

    <?php else: ?>
    <!-- ── Valid document ── -->
    <div class="result-banner valid">
      <span class="result-icon">✅</span>
      <div>
        <div>Document Verified — Authentic</div>
        <div style="font-size:10pt;font-weight:400;margin-top:4px">
          This document is genuine and was issued by <?= e($school) ?>.
        </div>
      </div>
    </div>

    <div class="doc-type-badge">📄 <?= e($docLabel) ?></div>

    <div class="info-grid">
      <div class="info-item full">
        <div class="label">Student Name</div>
        <div class="value"><?= e($record['student_name']) ?></div>
      </div>
      <div class="info-item">
        <div class="label">Student ID</div>
        <div class="value"><?= e($record['sid_code'] ?? '—') ?></div>
      </div>
      <div class="info-item">
        <div class="label">Grade</div>
        <div class="value"><?= e($record['grade_name'] ?? '—') ?></div>
      </div>
      <div class="info-item">
        <div class="label">Academic Year</div>
        <div class="value"><?= e($record['academic_year'] ?? '—') ?></div>
      </div>
      <?php if ($record['purpose']): ?>
      <div class="info-item">
        <div class="label">Purpose</div>
        <div class="value"><?= e(ucfirst($record['purpose'])) ?></div>
      </div>
      <?php endif; ?>
      <div class="info-item">
        <div class="label">Date Issued</div>
        <div class="value"><?= date('d M Y', strtotime($record['issued_at'])) ?></div>
      </div>
      <?php if ($record['expires_at']): ?>
      <div class="info-item">
        <div class="label">Valid Until</div>
        <div class="value"><?= date('d M Y', strtotime($record['expires_at'])) ?></div>
      </div>
      <?php endif; ?>
      <div class="info-item full">
        <div class="label">Reference Number</div>
        <div class="value" style="font-size:10pt;word-break:break-all"><?= e($record['ref_number'] ?? '—') ?></div>
      </div>
    </div>

    <div class="verif-note">
      🔒 Verification token: <strong><?= e(substr($token,0,8)).'...' ?></strong>
      &nbsp;|&nbsp; Verified <?= $record['verified_count'] ?> time<?= $record['verified_count']!=1?'s':'' ?>
      &nbsp;|&nbsp; Last checked: <?= $record['last_verified_at'] ? date('d M Y H:i', strtotime($record['last_verified_at'])) : 'Just now' ?>
    </div>
    <?php endif; ?>

    <!-- ── Search form (always shown) ── -->
    <div class="search-form">
      <label for="vtoken">Verify another document code:</label>
      <form method="get" action="<?= BASE_URL ?>/verify.php">
        <div class="search-row">
          <input type="text" id="vtoken" name="t"
                 placeholder="Paste verification token here…"
                 value="<?= e($token) ?>" autocomplete="off" spellcheck="false"/>
          <button type="submit">Verify</button>
        </div>
      </form>
    </div>

  </div><!-- .card-body -->

  <div class="card-footer">
    <strong><?= e($school) ?></strong> &nbsp;|&nbsp; <?= e($address) ?><br>
    <?= e($phone) ?> &nbsp;|&nbsp; <?= e($email) ?><br>
    For questions about document authenticity, contact the Office of the Registrar.
  </div>
</div>

</body>
</html>
