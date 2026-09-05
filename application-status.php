<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'Application Status';
$activeNav = 'admissions';

$app    = null;
$error  = '';
$searched = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $num   = trim($_POST['app_number'] ?? '');
    $phone = trim($_POST['phone']      ?? '');
    if ($num === '' || $phone === '') {
        $error = 'Please enter your application number and phone number.';
    } else {
        $stmt = db()->prepare(
            'SELECT * FROM applications WHERE application_number=? AND (phone=? OR guardian_phone=?) LIMIT 1'
        );
        $stmt->execute([$num, $phone, $phone]);
        $app = $stmt->fetch();
        $searched = true;
        if (!$app) $error = 'No application found with those details. Please check and try again.';
    }
}

// Status flow definition
$statusFlow = [
    'Application Submitted'   => ['done'=>true,  'label'=>'Application Submitted',   'desc'=>'Your application was successfully submitted.'],
    'Under Review'            => ['done'=>false, 'label'=>'Application Under Review', 'desc'=>'Our admissions team is reviewing your application.'],
    'Documents needed'        => ['done'=>false, 'label'=>'Documents Requested',      'desc'=>'Additional documents have been requested.'],
    'Approved for entrance'   => ['done'=>false, 'label'=>'Approved for Entrance',    'desc'=>'Your application has been approved. An entrance eligibility letter is available.'],
    'Entrance scheduled'      => ['done'=>false, 'label'=>'Entrance Exam Scheduled',  'desc'=>'Your entrance examination has been scheduled.'],
    'Entrance completed'      => ['done'=>false, 'label'=>'Entrance Completed',       'desc'=>'You have completed the entrance examination.'],
    'Entrance passed'         => ['done'=>false, 'label'=>'Entrance Passed',          'desc'=>'Congratulations! You have passed the entrance examination.'],
    'Admitted'                => ['done'=>false, 'label'=>'Admission Approved',       'desc'=>'You have been officially admitted to Karn High School!'],
    'Rejected'                => ['done'=>false, 'label'=>'Application Unsuccessful', 'desc'=>'We regret to inform you that your application was not successful.'],
    'Waitlisted'              => ['done'=>false, 'label'=>'Waitlisted',               'desc'=>'Your application has been placed on the waiting list.'],
];

function getStatusIndex(string $status, array $flow): int {
    $keys = array_keys($flow);
    $idx  = array_search($status, $keys);
    return $idx === false ? 0 : (int)$idx;
}

include __DIR__.'/includes/header.php';
?>
<main class="inner-page">
  <div class="container inner-hero">
    <div class="eyebrow">Application Tracking <span></span></div>
    <h1>Check your<br><em>application status.</em></h1>
    <p>Enter your application number and phone number to track your admission progress.</p>
  </div>

  <div class="container" style="padding-bottom:80px;max-width:680px">

    <!-- Search form -->
    <div class="form-section" style="margin-bottom:32px">
      <div class="form-section-title">Find Your Application</div>
      <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
      <?php endif; ?>
      <form method="post">
        <?= csrfField() ?>
        <div class="form-row">
          <div class="form-group">
            <label>Application Number *
              <input name="app_number" required placeholder="e.g. KHS-2026-000123"
                     value="<?= e($_POST['app_number'] ?? '') ?>"/>
            </label>
          </div>
          <div class="form-group">
            <label>Phone Number *
              <input name="phone" required placeholder="+231 …"
                     value="<?= e($_POST['phone'] ?? '') ?>"/>
            </label>
          </div>
        </div>
        <button type="submit" class="button button-primary">Track Application →</button>
      </form>
    </div>

    <?php if ($app): ?>
    <!-- Application found -->
    <div class="form-section">
      <div class="form-section-title" style="display:flex;justify-content:space-between;align-items:center">
        <span>Application <?= e($app['application_number']) ?></span>
        <?= statusBadge($app['status']) ?>
      </div>

      <!-- Applicant summary -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px 20px;margin-bottom:24px;font-size:13.5px">
        <div><span style="color:var(--ink-faint);font-weight:600">Applicant</span><br><strong><?= e($app['first_name'].' '.($app['middle_name']?$app['middle_name'].' ':'').$app['last_name']) ?></strong></div>
        <div><span style="color:var(--ink-faint);font-weight:600">Grade Applying For</span><br><strong><?= e($app['grade_applying_for']) ?></strong></div>
        <div><span style="color:var(--ink-faint);font-weight:600">Academic Year</span><br><strong><?= e($app['academic_year']) ?></strong></div>
        <div><span style="color:var(--ink-faint);font-weight:600">Submitted</span><br><strong><?= date('M d, Y', strtotime($app['created_at'])) ?></strong></div>
        <?php if ($app['entrance_exam_date']): ?>
        <div><span style="color:var(--ink-faint);font-weight:600">Entrance Exam Date</span><br><strong><?= date('M d, Y', strtotime($app['entrance_exam_date'])) ?><?= $app['entrance_exam_time'] ? ' at '.$app['entrance_exam_time'] : '' ?></strong></div>
        <?php endif; ?>
      </div>

      <!-- Status timeline -->
      <div style="font-size:13px;font-weight:700;color:var(--ink-soft);margin-bottom:14px;text-transform:uppercase;letter-spacing:.08em">Application Progress</div>
      <div class="status-timeline">
        <?php
        $currentIdx = getStatusIndex($app['status'], $statusFlow);
        $rejectLike = in_array($app['status'], ['Rejected','Waitlisted']);
        $i = 0;
        foreach ($statusFlow as $key => $step):
            if (in_array($key, ['Rejected','Waitlisted']) && !$rejectLike) { $i++; continue; }
            $isDone   = $i <  $currentIdx;
            $isActive = $i === $currentIdx;
            $dotClass = $isDone ? 'done' : ($isActive ? 'active' : 'pending');
            $icon     = $isDone ? '✓' : ($isActive ? '●' : '○');
        ?>
        <div class="timeline-step">
          <div class="timeline-dot <?= $dotClass ?>"><?= $icon ?></div>
          <div class="timeline-body">
            <strong style="<?= !$isDone && !$isActive ? 'color:var(--ink-faint)' : '' ?>"><?= e($step['label']) ?></strong>
            <?php if ($isActive || $isDone): ?>
            <span><?= e($step['desc']) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <?php $i++; endforeach; ?>
      </div>

      <!-- Action buttons based on status -->
      <?php if ($app['status'] === 'Approved for entrance' && $app['entrance_letter_ref']): ?>
      <div style="margin-top:24px;padding:16px;background:var(--green-soft);border-radius:var(--radius-sm);border:1px solid #b2dfc0">
        <strong style="color:var(--green)">📄 Entrance Eligibility Letter is Ready</strong>
        <p style="font-size:13.5px;color:var(--ink-soft);margin-top:4px;margin-bottom:12px">Your entrance eligibility letter has been generated. Please download and bring it on the exam day.</p>
        <a href="<?= BASE_URL ?>/letters/entrance_letter.php?app=<?= urlencode($app['application_number']) ?>&phone=<?= urlencode($app['phone']) ?>" class="button button-success button-sm" target="_blank">Download Letter →</a>
      </div>
      <?php elseif ($app['status'] === 'Entrance scheduled'): ?>
      <div style="margin-top:24px;padding:16px;background:var(--blue-soft);border-radius:var(--radius-sm);border:1px solid #a8d0f0">
        <strong style="color:var(--blue)">📝 Entrance Exam Scheduled</strong>
        <?php if ($app['entrance_exam_date']): ?>
        <p style="font-size:13.5px;color:var(--ink-soft);margin-top:4px">Date: <strong><?= date('l, F d, Y', strtotime($app['entrance_exam_date'])) ?></strong><?= $app['entrance_exam_time'] ? ' at '.$app['entrance_exam_time'] : '' ?></p>
        <?php endif; ?>
      </div>
      <?php elseif ($app['status'] === 'Documents needed'): ?>
      <div style="margin-top:24px;padding:16px;background:var(--warning-soft);border-radius:var(--radius-sm);border:1px solid #e8d48a">
        <strong style="color:var(--warning)">📎 Documents Required</strong>
        <p style="font-size:13.5px;color:var(--ink-soft);margin-top:4px;margin-bottom:12px">Please contact the admissions office to submit the required documents.</p>
        <a href="<?= BASE_URL ?>/contact.php" class="button button-sm button-secondary">Contact Admissions →</a>
      </div>
      <?php elseif ($app['status'] === 'Admitted'): ?>
      <div style="margin-top:24px;padding:20px;background:var(--green-soft);border-radius:var(--radius-sm);border:1px solid #b2dfc0;text-align:center">
        <div style="font-size:36px;margin-bottom:8px">🎉</div>
        <strong style="font-size:18px;color:var(--green)">Congratulations! You have been admitted.</strong>
        <p style="font-size:13.5px;color:var(--ink-soft);margin-top:8px">Please visit the school office to complete your enrolment.</p>
      </div>
      <?php endif; ?>
    </div>

    <?php elseif ($searched && !$app): ?>
    <!-- Not found state -->
    <div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
      <div style="font-size:40px;margin-bottom:12px">🔍</div>
      <h3 style="margin-bottom:8px">Application not found</h3>
      <p style="color:var(--ink-soft);margin-bottom:20px">We could not find an application matching those details. Please double-check your application number and phone number.</p>
      <a href="<?= BASE_URL ?>/contact.php" class="button button-secondary">Contact Admissions →</a>
    </div>
    <?php endif; ?>

    <!-- Info boxes -->
    <div class="value-grid" style="margin-top:32px">
      <article class="value-card">
        <div class="value-icon">📋</div>
        <h3>New application?</h3>
        <p>Haven't applied yet? Start your online application today for the <?= e($ay) ?> academic year.</p>
        <a href="<?= BASE_URL ?>/apply.php" class="text-link" style="margin-top:8px">Apply now →</a>
      </article>
      <article class="value-card">
        <div class="value-icon">📞</div>
        <h3>Need help?</h3>
        <p>Can't find your application or have questions? Contact our admissions office.</p>
        <a href="<?= BASE_URL ?>/contact.php" class="text-link" style="margin-top:8px">Contact us →</a>
      </article>
      <article class="value-card">
        <div class="value-icon">📄</div>
        <h3>Documents</h3>
        <p>Ensure you have your report card, birth certificate and passport photo ready for submission.</p>
        <a href="<?= BASE_URL ?>/admissions.php" class="text-link" style="margin-top:8px">Learn more →</a>
      </article>
    </div>
  </div>
</main>
<?php include __DIR__.'/includes/footer.php'; ?>
