<?php
// ============================================================
// Applicant Portal — Admission Decision & Letter
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('applicant');

$activePage = 'decision';
$user       = currentUser();

// Load application
try {
    $app = db()->prepare("SELECT * FROM applications WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
    $app->execute([$user['id']]);
    $app = $app->fetch();
} catch (Throwable $e) { $app = null; }

$decisionStatuses = ['Admitted','Rejected','Waitlisted','Entrance passed'];
$hasDecision = $app && in_array($app['status'], $decisionStatuses);
$isAdmitted  = $app && $app['status'] === 'Admitted';
$isRejected  = $app && $app['status'] === 'Rejected';
$isWaitlisted= $app && $app['status'] === 'Waitlisted';
$isPassed    = $app && $app['status'] === 'Entrance passed';
$schoolName  = setting('school_name', 'KARN HIGH SCHOOL');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Admission Decision — Applicant Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <div class="page-heading">
    <div><h1>Admission Decision</h1><p>Your official admission outcome</p></div>
  </div>

  <?php if (!$app): ?>
  <div class="alert alert-warning">No application found.</div>

  <?php elseif (!$hasDecision): ?>
  <div style="text-align:center;padding:56px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
    <div style="font-size:40px;margin-bottom:14px">📨</div>
    <h3 style="margin-bottom:8px">Decision pending</h3>
    <p style="color:var(--ink-soft)">Your admission decision will appear here once the admissions process is complete.</p>
    <p style="color:var(--ink-soft);margin-top:8px">Current status: <strong><?= e($app['status']) ?></strong></p>
  </div>

  <?php elseif ($isAdmitted): ?>
  <!-- ADMITTED -->
  <div style="background:linear-gradient(135deg,#1b5e3b 0%,#0e3522 100%);border-radius:var(--radius);padding:32px;text-align:center;margin-bottom:24px;color:#fff">
    <div style="font-size:56px;margin-bottom:12px">🎉</div>
    <h2 style="font-size:24px;font-weight:800;color:#fff;margin-bottom:8px">Congratulations!</h2>
    <p style="color:rgba(255,255,255,.8);font-size:15px;max-width:480px;margin:0 auto 20px">
      You have been officially admitted to <strong><?= e($schoolName) ?></strong>
      for the <?= e($app['academic_year'] ?? '') ?> academic year.
    </p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      <a href="<?= BASE_URL ?>/letters/admission_letter.php?app=<?= urlencode($app['application_number']) ?>&phone=<?= urlencode($app['phone'] ?? '') ?>"
         class="button" style="background:#fff;color:var(--green);font-weight:700" target="_blank">
        📄 Download Admission Letter
      </a>
      <button onclick="window.print()" class="button" style="background:rgba(255,255,255,.15);color:#fff;border:1.5px solid rgba(255,255,255,.35)">
        🖨️ Print
      </button>
    </div>
  </div>

  <!-- Admission details -->
  <div class="panel" style="padding:24px;margin-bottom:20px" id="admissionCard">
    <div style="text-align:center;margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid var(--line)">
      <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="KHS" style="width:52px;height:52px;border-radius:10px;margin:0 auto 8px"/>
      <div style="font-size:16px;font-weight:800;color:var(--ink)"><?= e($schoolName) ?></div>
      <div style="font-size:12px;color:var(--ink-soft)">Karnplay, Nimba County, Liberia</div>
      <div style="margin-top:8px;font-size:13px;font-weight:700;color:var(--green)">
        ✅ ADMISSION CONFIRMED — <?= e($app['academic_year'] ?? '') ?>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px 20px;font-size:13px" class="decision-grid">
      <?php foreach ([
        'Applicant Name'   => trim($app['first_name'].($app['middle_name']?' '.$app['middle_name']:'').' '.$app['last_name']),
        'Application No.'  => $app['application_number'],
        'Grade Admitted'   => $app['grade_applying_for'] ?? $app['grade'] ?? '—',
        'Academic Year'    => $app['academic_year'] ?? '—',
        'Decision Date'    => date('M d, Y'),
      ] as $label => $val): ?>
      <div>
        <span style="display:block;font-size:11px;color:var(--ink-faint);font-weight:600;margin-bottom:2px"><?= $label ?></span>
        <strong><?= e($val) ?></strong>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:18px;padding:14px;background:var(--bg);border-radius:var(--radius-sm);font-size:13px;color:var(--ink-soft);line-height:1.72">
      <strong style="color:var(--ink)">Next Steps:</strong><br>
      Please visit the school admissions office with this letter and the following:
      <ul style="margin:8px 0 0 16px;display:flex;flex-direction:column;gap:4px">
        <li>Original birth certificate</li>
        <li>Last school report card (original)</li>
        <li>2 passport-sized photographs</li>
        <li>Guardian's identification</li>
        <li>First term school fees payment</li>
      </ul>
    </div>
  </div>

  <?php elseif ($isRejected): ?>
  <!-- REJECTED -->
  <div style="background:#fff1f2;border:1.5px solid #fecdd3;border-radius:var(--radius);padding:28px;text-align:center;margin-bottom:20px">
    <div style="font-size:40px;margin-bottom:12px">📋</div>
    <h3 style="color:var(--error);margin-bottom:8px">Application Unsuccessful</h3>
    <p style="color:var(--ink-soft);max-width:480px;margin:0 auto 16px">
      We regret to inform you that your application to <?= e($schoolName) ?> was not successful at this time.
    </p>
    <p style="color:var(--ink-soft);font-size:13px">
      You may contact our admissions office for feedback or to enquire about future intake.
    </p>
    <a href="<?= BASE_URL ?>/contact.php" class="button button-secondary" style="margin-top:16px" target="_blank">Contact Admissions</a>
  </div>

  <?php elseif ($isWaitlisted): ?>
  <!-- WAITLISTED -->
  <div style="background:#fefce8;border:1.5px solid #fde68a;border-radius:var(--radius);padding:28px;text-align:center;margin-bottom:20px">
    <div style="font-size:40px;margin-bottom:12px">⏳</div>
    <h3 style="color:var(--warning);margin-bottom:8px">Placed on Waiting List</h3>
    <p style="color:var(--ink-soft);max-width:480px;margin:0 auto 12px">
      Your application has been placed on the waiting list for <?= e($schoolName) ?>.
      We will contact you if a place becomes available.
    </p>
    <p style="font-size:13px;color:var(--ink-soft)">Please keep your contact details up to date. <a href="<?= BASE_URL ?>/contact.php" target="_blank">Contact us</a> if you have not heard within 30 days.</p>
  </div>

  <?php elseif ($isPassed): ?>
  <!-- ENTRANCE PASSED — awaiting final admission -->
  <div class="alert" style="background:var(--green-soft);border-color:var(--green-light);margin-bottom:20px">
    <strong>🎉 Entrance Examination Passed!</strong>
    Your entrance examination results have been received. A formal admission decision will be communicated shortly.
  </div>
  <?php endif; ?>

  <!-- Contact box -->
  <div class="panel" style="padding:20px 22px;display:flex;gap:16px;align-items:flex-start">
    <span style="font-size:1.4rem;flex-shrink:0">✉️</span>
    <div>
      <h3 style="font-size:14px;font-weight:700;margin-bottom:4px">Questions about your decision?</h3>
      <p style="font-size:13px;color:var(--ink-soft)">Contact our admissions office at <strong>+231 886 417 711</strong> or visit us at Karnplay, Nimba County.</p>
      <a href="<?= BASE_URL ?>/contact.php" target="_blank" class="lnk" style="font-size:13px;margin-top:8px">Contact us →</a>
    </div>
  </div>

</div>
</div>
<style>
@media(max-width:580px){.decision-grid{grid-template-columns:1fr !important}}
@media print{.portal-sidebar{display:none!important}.portal-content{padding:0!important}}
</style>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
