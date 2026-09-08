<?php
// ============================================================
// Applicant Portal — Dashboard
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('applicant');

$activePage = 'dashboard';
$user = currentUser();

// Load all applications for this user, most recent first
try {
    $apps = db()->prepare(
        "SELECT * FROM applications WHERE user_id=? ORDER BY created_at DESC"
    );
    $apps->execute([$user['id']]);
    $apps = $apps->fetchAll();
} catch (Throwable $e) { $apps = []; }

$app = $apps[0] ?? null; // Primary/latest application

// Status badge colours
$statusMeta = [
    'Application Submitted' => ['badge-grey',   '📋', 'Your application has been received and is awaiting review.'],
    'Under Review'          => ['badge-red',    '🔍', 'Our admissions team is currently reviewing your application.'],
    'Documents needed'      => ['badge-gold',   '📎', 'Additional documents are required. Please upload them now.'],
    'Approved for entrance' => ['badge-grn',    '📨', 'Your application is approved. An entrance examination invite is ready.'],
    'Entrance scheduled'    => ['badge-gold',   '📅', 'Your entrance examination has been scheduled.'],
    'Entrance completed'    => ['badge-grey',   '✅', 'You have completed the entrance examination. Results are being processed.'],
    'Entrance passed'       => ['badge-grn',    '🎉', 'Congratulations — you have passed the entrance examination!'],
    'Admitted'              => ['badge-grn',    '🏫', 'You have been officially admitted to Karn High School!'],
    'Rejected'              => ['badge-red',    '❌', 'Your application was unsuccessful at this time.'],
    'Waitlisted'            => ['badge-gold',   '⏳', 'Your application has been placed on the waiting list.'],
];

$statusFlow = [
    'Application Submitted', 'Under Review', 'Documents needed',
    'Approved for entrance', 'Entrance scheduled', 'Entrance completed',
    'Entrance passed', 'Admitted',
];

function currentStepIndex(string $status): int {
    global $statusFlow;
    $idx = array_search($status, $statusFlow);
    return $idx === false ? 0 : (int)$idx;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Applicant Portal — KHS</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <!-- Header -->
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;flex-wrap:wrap;gap:12px">
    <div>
      <h1 style="font-size:24px;font-weight:800;margin-bottom:4px">
        Welcome, <?= e(explode(' ', $user['name'])[0]) ?>! 👋
      </h1>
      <p style="color:var(--ink-soft);font-size:13px">Applicant Portal — Karn High School</p>
    </div>
    <a href="<?= BASE_URL ?>/portal/applicant/application.php"
       class="button button-secondary button-sm">📋 View Application</a>
  </div>

  <?php if (empty($apps)): ?>
  <!-- No application found -->
  <div style="text-align:center;padding:56px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
    <div style="font-size:40px;margin-bottom:14px">📋</div>
    <h3 style="margin-bottom:6px">No application found</h3>
    <p style="color:var(--ink-soft);margin-bottom:20px">It seems your account is not yet linked to an application.</p>
    <a href="<?= BASE_URL ?>/apply.php" class="button button-primary">Start New Application</a>
  </div>

  <?php else: ?>

  <!-- Current status banner -->
  <?php
    $status = $app['status'] ?? 'Application Submitted';
    [$badgeCls, $statusIco, $statusMsg] = $statusMeta[$status] ?? ['badge-grey','📋','Your application is being processed.'];
    $stepIdx = currentStepIndex($status);
    $isNegative = in_array($status, ['Rejected', 'Waitlisted']);
    $isAdmitted = $status === 'Admitted';
  ?>

  <?php if ($isAdmitted): ?>
  <div style="background:linear-gradient(135deg,var(--green),#0e3522);border-radius:var(--radius);padding:28px;margin-bottom:24px;color:#fff;text-align:center">
    <div style="font-size:48px;margin-bottom:10px">🎉</div>
    <h2 style="font-size:22px;font-weight:800;color:#fff;margin-bottom:6px">Congratulations! You've been admitted!</h2>
    <p style="color:rgba(255,255,255,.78);margin-bottom:20px">You have been officially admitted to Karn High School.</p>
    <a href="<?= BASE_URL ?>/portal/applicant/decision.php" class="button" style="background:#fff;color:var(--green);font-weight:700">📄 Download Admission Letter</a>
  </div>

  <?php elseif ($isNegative): ?>
  <div style="background:var(--error-soft);border:1.5px solid var(--error-light);border-radius:var(--radius);padding:22px;margin-bottom:24px">
    <div style="display:flex;align-items:center;gap:14px">
      <span style="font-size:1.8rem"><?= $statusIco ?></span>
      <div>
        <h3 style="color:var(--error);font-weight:700;font-size:16px;margin-bottom:4px"><?= e($status) ?></h3>
        <p style="color:var(--ink-soft);font-size:14px"><?= $statusMsg ?></p>
      </div>
    </div>
  </div>

  <?php else: ?>
  <div style="background:var(--primary-soft);border:1.5px solid rgba(var(--primary-rgb),.18);border-radius:var(--radius);padding:22px;margin-bottom:24px">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:18px">
      <span style="font-size:1.8rem"><?= $statusIco ?></span>
      <div>
        <h3 style="color:var(--ink);font-weight:700;font-size:16px;margin-bottom:4px">
          Status: <span class="status <?= $badgeCls ?>"><?= e($status) ?></span>
        </h3>
        <p style="color:var(--ink-soft);font-size:14px"><?= $statusMsg ?></p>
      </div>
    </div>
    <!-- Progress bar -->
    <div style="display:flex;gap:0;border-radius:20px;overflow:hidden;height:8px;background:var(--line)">
      <?php
      $total = count($statusFlow);
      $pct   = $total > 1 ? round(($stepIdx / ($total-1)) * 100) : 0;
      ?>
      <div style="width:<?= $pct ?>%;background:var(--primary);transition:width .5s ease"></div>
    </div>
    <p style="font-size:11px;color:var(--ink-faint);margin-top:6px">Step <?= $stepIdx+1 ?> of <?= $total ?></p>
  </div>
  <?php endif; ?>

  <!-- Application summary card -->
  <div class="panel" style="padding:20px 22px;margin-bottom:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px">
      <h3 style="font-size:14px;font-weight:700">Application Summary</h3>
      <span class="status <?= $badgeCls ?>"><?= e($status) ?></span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px 20px;font-size:13px">
      <?php foreach ([
        'Application No.'  => $app['application_number'],
        'Applicant'        => trim($app['first_name'].($app['middle_name']?' '.$app['middle_name']:'').' '.$app['last_name']),
        'Grade Applying'   => $app['grade_applying_for'] ?? $app['grade'] ?? '—',
        'Academic Year'    => $app['academic_year'] ?? '—',
        'Submitted'        => date('M d, Y', strtotime($app['created_at'])),
      ] as $label => $val): ?>
      <div>
        <span style="display:block;font-size:11px;color:var(--ink-faint);font-weight:600;margin-bottom:2px"><?= $label ?></span>
        <strong style="font-size:13px"><?= e($val) ?></strong>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Action cards based on status -->
  <?php if ($status === 'Documents needed'): ?>
  <div class="alert alert-warning" style="margin-bottom:20px">
    <strong>📎 Action required:</strong> The admissions office has requested additional documents.
    <a href="documents.php" class="button button-sm button-secondary" style="margin-left:12px">Upload Documents →</a>
  </div>
  <?php endif; ?>

  <?php if (in_array($status, ['Approved for entrance','Entrance scheduled'])): ?>
  <div class="alert" style="background:var(--green-soft);border-color:var(--green-light);margin-bottom:20px">
    <strong>📝 Entrance Examination:</strong> You have been invited to sit the entrance examination.
    <a href="exam.php" class="button button-sm" style="margin-left:12px;background:var(--green);color:#fff">View Exam Details →</a>
  </div>
  <?php endif; ?>

  <?php if ($status === 'Admitted' || $status === 'Entrance passed'): ?>
  <div class="alert" style="background:var(--green-soft);border-color:var(--green-light);margin-bottom:20px">
    <strong>🎉 Admission Confirmed:</strong> Please download your admission letter and visit the school office.
    <a href="decision.php" class="button button-sm" style="margin-left:12px;background:var(--green);color:#fff">Download Letter →</a>
  </div>
  <?php endif; ?>

  <!-- Quick links -->
  <div class="quick-grid">
    <a href="application.php" class="quick-item">
      <span class="qi-icon">📋</span>
      <div><strong>My Application</strong><small>View your submitted form</small></div>
    </a>
    <a href="documents.php" class="quick-item">
      <span class="qi-icon">📎</span>
      <div><strong>Documents</strong><small>Upload supporting files</small></div>
    </a>
    <a href="exam.php" class="quick-item">
      <span class="qi-icon">📝</span>
      <div><strong>Entrance Exam</strong><small>Invitation & schedule</small></div>
    </a>
    <a href="decision.php" class="quick-item">
      <span class="qi-icon">📨</span>
      <div><strong>Admission Decision</strong><small>View decision & letter</small></div>
    </a>
    <a href="<?= BASE_URL ?>/contact.php" class="quick-item" target="_blank">
      <span class="qi-icon">✉️</span>
      <div><strong>Contact Admissions</strong><small>Ask a question</small></div>
    </a>
    <a href="<?= BASE_URL ?>/faq.php" class="quick-item" target="_blank">
      <span class="qi-icon">❓</span>
      <div><strong>FAQ</strong><small>Common questions</small></div>
    </a>
  </div>

  <?php endif; ?>

</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
