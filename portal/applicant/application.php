<?php
// ============================================================
// Applicant Portal — My Application (view only after submission)
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('applicant');

$activePage = 'application';
$user = currentUser();

// Load application
try {
    $app = db()->prepare("SELECT * FROM applications WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
    $app->execute([$user['id']]);
    $app = $app->fetch();
} catch (Throwable $e) { $app = null; }

// Status history
$history = [];
if ($app) {
    try {
        $h = db()->prepare(
            "SELECT * FROM application_status_history WHERE application_id=? ORDER BY created_at ASC"
        );
        $h->execute([$app['id']]);
        $history = $h->fetchAll();
    } catch (Throwable $e) {}
}

$statusBadgeMap = [
    'Application Submitted' => 'badge-grey',
    'Under Review'          => 'badge-red',
    'Documents needed'      => 'badge-gold',
    'Approved for entrance' => 'badge-grn',
    'Entrance scheduled'    => 'badge-gold',
    'Entrance completed'    => 'badge-grey',
    'Entrance passed'       => 'badge-grn',
    'Admitted'              => 'badge-grn',
    'Rejected'              => 'badge-red',
    'Waitlisted'            => 'badge-gold',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>My Application — Applicant Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <div class="page-heading">
    <div><h1>My Application</h1><p>Your submitted application details</p></div>
    <?php if ($app): ?>
    <span class="status <?= $statusBadgeMap[$app['status']] ?? 'badge-grey' ?>"><?= e($app['status']) ?></span>
    <?php endif; ?>
  </div>

  <?php if (!$app): ?>
  <div style="text-align:center;padding:56px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
    <div style="font-size:40px;margin-bottom:12px">📋</div>
    <h3 style="margin-bottom:6px">No application found</h3>
    <a href="<?= BASE_URL ?>/apply.php" class="button button-primary" style="margin-top:16px">Start New Application</a>
  </div>

  <?php else: ?>

  <!-- Return-for-correction notice -->
  <?php if (($app['internal_notes'] ?? '') && $app['status'] === 'Documents needed'): ?>
  <div class="alert alert-warning" style="margin-bottom:20px">
    <strong>📎 Documents Requested:</strong> <?= e($app['internal_notes']) ?>
    <a href="documents.php" class="button button-sm button-secondary" style="margin-left:10px">Upload Now →</a>
  </div>
  <?php endif; ?>

  <!-- Applicant info -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px" class="app-grid">

    <div class="panel" style="padding:22px">
      <h3 style="font-size:14px;font-weight:700;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--line)">👤 Applicant Information</h3>
      <?php foreach ([
        'Full Name'       => trim($app['first_name'].($app['middle_name']?' '.$app['middle_name']:'').' '.$app['last_name']),
        'Date of Birth'   => $app['date_of_birth'] ? date('F d, Y', strtotime($app['date_of_birth'])) : '—',
        'Gender'          => $app['gender']       ?? '—',
        'Nationality'     => $app['nationality']  ?? '—',
        'Phone'           => $app['phone']         ?? '—',
        'Email'           => $app['email']         ?? '—',
        'Address'         => $app['current_address'] ?? '—',
        'County'          => $app['county']        ?? '—',
      ] as $label => $val): ?>
      <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--line-soft);font-size:13px">
        <span style="color:var(--ink-soft);font-weight:600;min-width:110px"><?= $label ?></span>
        <span style="text-align:right;color:var(--ink)"><?= e($val) ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <div>
      <div class="panel" style="padding:22px;margin-bottom:16px">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--line)">🎓 Application Details</h3>
        <?php foreach ([
          'Application No.' => $app['application_number'],
          'Grade Applying'  => $app['grade_applying_for'] ?? $app['grade'] ?? '—',
          'Academic Year'   => $app['academic_year'] ?? '—',
          'Submitted'       => date('M d, Y · g:i A', strtotime($app['created_at'])),
          'Previous School' => $app['previous_school'] ?? '—',
          'Last Grade'      => $app['last_grade_completed'] ?? '—',
        ] as $label => $val): ?>
        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--line-soft);font-size:13px">
          <span style="color:var(--ink-soft);font-weight:600;min-width:110px"><?= $label ?></span>
          <span style="text-align:right;color:var(--ink)"><?= e($val) ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="panel" style="padding:22px">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--line)">👨‍👩‍👧 Guardian Information</h3>
        <?php foreach ([
          'Name'         => $app['guardian_name']         ?? '—',
          'Relationship' => $app['guardian_relationship'] ?? '—',
          'Phone'        => $app['guardian_phone']        ?? '—',
        ] as $label => $val): ?>
        <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--line-soft);font-size:13px">
          <span style="color:var(--ink-soft);font-weight:600;min-width:110px"><?= $label ?></span>
          <span style="text-align:right;color:var(--ink)"><?= e($val) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Status timeline -->
  <?php if (!empty($history)): ?>
  <div class="panel" style="padding:22px">
    <h3 style="font-size:14px;font-weight:700;margin-bottom:16px">📅 Application Timeline</h3>
    <div style="display:flex;flex-direction:column;gap:0">
      <?php foreach ($history as $i => $h): ?>
      <div style="display:flex;gap:16px;padding-bottom:<?= $i < count($history)-1 ? '20px' : '0' ?>;position:relative">
        <?php if ($i < count($history)-1): ?>
        <div style="position:absolute;left:15px;top:32px;bottom:0;width:2px;background:var(--line-soft)"></div>
        <?php endif; ?>
        <div style="width:32px;height:32px;border-radius:50%;background:<?= $i===count($history)-1?'var(--primary)':'var(--green)' ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0;z-index:1">
          <?= $i===count($history)-1 ? '●' : '✓' ?>
        </div>
        <div style="padding-top:5px">
          <strong style="display:block;font-size:14px;color:var(--ink)"><?= e($h['new_status']) ?></strong>
          <?php if (!empty($h['notes'])): ?>
          <span style="font-size:12px;color:var(--ink-soft)"><?= e($h['notes']) ?></span><br>
          <?php endif; ?>
          <span style="font-size:11px;color:var(--ink-faint)"><?= date('M d, Y · g:i A', strtotime($h['created_at'])) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <p style="margin-top:14px;font-size:12px;color:var(--ink-faint);text-align:center">
    Application details cannot be changed after final submission. To report an error, <a href="<?= BASE_URL ?>/contact.php" style="color:var(--primary)">contact the admissions office</a>.
  </p>
  <?php endif; ?>

</div>
</div>
<style>@media(max-width:640px){.app-grid{grid-template-columns:1fr !important}}</style>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
