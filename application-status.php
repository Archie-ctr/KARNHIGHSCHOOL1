<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'Application Status';
$activeNav = 'admissions';
$metaDesc  = 'Track the status of your Karn High School admission application. Enter your application number and phone to check progress.';

$app      = null;
$error    = '';
$searched = false;

try { $ay = currentAcademicYearName(); } catch (Throwable $e) { $ay = '2026/2027'; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $num   = trim($_POST['app_number'] ?? '');
    $phone = trim($_POST['phone']      ?? '');
    if ($num === '' || $phone === '') {
        $error = 'Please enter both your application number and phone number.';
    } else {
        try {
            $stmt = db()->prepare(
                'SELECT * FROM applications
                 WHERE application_number = ?
                   AND (phone = ? OR guardian_phone = ?)
                 LIMIT 1'
            );
            $stmt->execute([$num, $phone, $phone]);
            $app = $stmt->fetch();
            $searched = true;
            if (!$app) $error = 'No application found with those details. Please check and try again.';
        } catch (Throwable $e) {
            $error = 'Unable to query the database. Please try again later.';
        }
    }
}

// Full status flow in order
$statusFlow = [
    'Application Submitted' => [
        'icon'  => '✅',
        'label' => 'Application Submitted',
        'desc'  => 'Your application was received and recorded successfully.',
        'color' => 'grn',
    ],
    'Under Review' => [
        'icon'  => '🔍',
        'label' => 'Under Review',
        'desc'  => 'Our admissions team is currently reviewing your application.',
        'color' => 'red',
    ],
    'Documents needed' => [
        'icon'  => '📎',
        'label' => 'Documents Requested',
        'desc'  => 'Additional supporting documents have been requested.',
        'color' => 'gold',
    ],
    'Approved for entrance' => [
        'icon'  => '📨',
        'label' => 'Approved for Entrance',
        'desc'  => 'Your application has been reviewed. An entrance eligibility letter is available.',
        'color' => 'grn',
    ],
    'Entrance scheduled' => [
        'icon'  => '📅',
        'label' => 'Entrance Exam Scheduled',
        'desc'  => 'Your entrance examination date and time have been confirmed.',
        'color' => 'red',
    ],
    'Entrance completed' => [
        'icon'  => '📝',
        'label' => 'Entrance Completed',
        'desc'  => 'You have sat the entrance examination. Results are being processed.',
        'color' => 'red',
    ],
    'Entrance passed' => [
        'icon'  => '🎉',
        'label' => 'Entrance Passed',
        'desc'  => 'Congratulations — you have passed the entrance examination.',
        'color' => 'grn',
    ],
    'Admitted' => [
        'icon'  => '🏫',
        'label' => 'Admission Confirmed',
        'desc'  => 'You have been officially admitted to Karn High School!',
        'color' => 'grn',
    ],
];
$negativeStatuses = [
    'Rejected'   => ['icon'=>'❌','label'=>'Application Unsuccessful','desc'=>'We regret that your application was not successful at this time.','color'=>'red'],
    'Waitlisted' => ['icon'=>'⏳','label'=>'Waitlisted',              'desc'=>'Your application has been placed on the waiting list.','color'=>'gold'],
];

function getStatusIdx(string $status, array $flow): int {
    $keys = array_keys($flow);
    $idx  = array_search($status, $keys);
    return $idx === false ? 0 : (int)$idx;
}

include __DIR__.'/includes/header.php';
?>

<!-- Page Hero -->
<section class="page-hero">
  <div class="wrap ph-body">
    <nav class="bc" aria-label="Breadcrumb">
      <a href="<?= BASE_URL ?>/">Home</a>
      <span class="bc-sep">/</span>
      <a href="<?= BASE_URL ?>/admissions.php">Admissions</a>
      <span class="bc-sep">/</span>
      <span>Application Status</span>
    </nav>
    <div class="eyebrow inv">Application Tracking</div>
    <h1 class="ph-h">Check your<br><em>application status.</em></h1>
    <p class="ph-lead">Enter your application number and the phone number used during application to track your admission progress in real time.</p>
  </div>
</section>

<!-- Lookup Form + Results -->
<section class="sec bg-white">
  <div class="wrap" style="max-width:720px">

    <!-- Search Card -->
    <div class="contact-form-wrap fade-up" style="margin-bottom:var(--sp8)">
      <div style="display:flex;align-items:center;gap:var(--sp3);margin-bottom:var(--sp6)">
        <div style="width:44px;height:44px;border-radius:var(--r2);background:var(--red-p);color:var(--red);display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0">🔍</div>
        <div>
          <h2 style="font-size:1.05rem;font-weight:700;color:var(--ink)">Find Your Application</h2>
          <p style="font-size:.83rem;color:var(--ink3)">Enter the details from your application confirmation.</p>
        </div>
      </div>

      <?php if ($error): ?>
      <div class="alert alert-err" role="alert"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <?= csrfField() ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--sp5)" class="status-form-grid">
          <div class="form-grp">
            <label class="form-lbl" for="app_number">Application Number <span style="color:var(--red)">*</span></label>
            <input class="form-ctrl" type="text" id="app_number" name="app_number" required
                   placeholder="e.g. KHS-2026-000123"
                   value="<?= e($_POST['app_number'] ?? '') ?>"
                   autocomplete="off" />
          </div>
          <div class="form-grp">
            <label class="form-lbl" for="phone">Phone Number <span style="color:var(--red)">*</span></label>
            <input class="form-ctrl" type="tel" id="phone" name="phone" required
                   placeholder="+231 …"
                   value="<?= e($_POST['phone'] ?? '') ?>"
                   autocomplete="off" />
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-lg" style="margin-top:var(--sp5);width:100%;justify-content:center">
          Track Application →
        </button>
      </form>
    </div>

    <?php if ($app): ?>
    <!-- ── APPLICATION FOUND ─────────────────────────────── -->
    <?php
      $isNegative  = in_array($app['status'], array_keys($negativeStatuses));
      $currentStep = $isNegative ? count($statusFlow) : getStatusIdx($app['status'], $statusFlow);
      $statusColor = [
          'Admitted'              => 'badge-grn',
          'Approved for entrance' => 'badge-grn',
          'Entrance passed'       => 'badge-grn',
          'Under Review'          => 'badge-red',
          'Application Submitted' => 'badge-grey',
          'Documents needed'      => 'badge-gold',
          'Entrance scheduled'    => 'badge-gold',
          'Entrance completed'    => 'badge-gold',
          'Rejected'              => 'badge-red',
          'Waitlisted'            => 'badge-gold',
      ];
      $badge = $statusColor[$app['status']] ?? 'badge-grey';
    ?>

    <!-- Header card -->
    <div class="contact-form-wrap fade-up" style="margin-bottom:var(--sp6)">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:var(--sp4);flex-wrap:wrap;margin-bottom:var(--sp5)">
        <div>
          <p class="caption" style="margin-bottom:4px">Application Number</p>
          <h2 style="font-size:1.15rem;font-weight:800;color:var(--ink)"><?= e($app['application_number']) ?></h2>
        </div>
        <span class="badge <?= $badge ?>" style="font-size:.8rem;padding:6px 14px"><?= e($app['status']) ?></span>
      </div>

      <!-- Applicant details grid -->
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:var(--sp4) var(--sp6);padding:var(--sp5);background:var(--bg2);border-radius:var(--r2);margin-bottom:var(--sp6)">
        <div>
          <p class="caption">Applicant Name</p>
          <strong style="font-size:.93rem;color:var(--ink)">
            <?= e(trim($app['first_name'].' '.($app['middle_name']??'').' '.$app['last_name'])) ?>
          </strong>
        </div>
        <div>
          <p class="caption">Grade Applying For</p>
          <strong style="font-size:.93rem;color:var(--ink)"><?= e($app['grade_applying_for'] ?? $app['grade'] ?? '—') ?></strong>
        </div>
        <div>
          <p class="caption">Academic Year</p>
          <strong style="font-size:.93rem;color:var(--ink)"><?= e($app['academic_year'] ?? $ay) ?></strong>
        </div>
        <div>
          <p class="caption">Date Submitted</p>
          <strong style="font-size:.93rem;color:var(--ink)"><?= date('M d, Y', strtotime($app['created_at'])) ?></strong>
        </div>
        <?php if (!empty($app['entrance_exam_date'])): ?>
        <div>
          <p class="caption">Entrance Exam Date</p>
          <strong style="font-size:.93rem;color:var(--ink)">
            <?= date('M d, Y', strtotime($app['entrance_exam_date'])) ?>
            <?= !empty($app['entrance_exam_time']) ? ' · '.$app['entrance_exam_time'] : '' ?>
          </strong>
        </div>
        <?php endif; ?>
      </div>

      <!-- Progress Timeline -->
      <h3 style="font-size:.83rem;font-weight:700;color:var(--ink3);text-transform:uppercase;letter-spacing:.08em;margin-bottom:var(--sp5)">Application Progress</h3>

      <?php if ($isNegative):
        $neg = $negativeStatuses[$app['status']];
      ?>
      <!-- Negative outcome -->
      <div class="timeline">
        <div class="tl-step">
          <div class="tl-dot done" title="Submitted">✓</div>
          <div class="tl-body"><strong>Application Submitted</strong><span>Your application was received.</span></div>
        </div>
        <div class="tl-step">
          <div class="tl-dot now"><?= $neg['icon'] ?></div>
          <div class="tl-body">
            <strong><?= $neg['label'] ?></strong>
            <span><?= $neg['desc'] ?></span>
          </div>
        </div>
      </div>

      <?php else: ?>
      <!-- Normal flow -->
      <div class="timeline">
        <?php foreach ($statusFlow as $key => $step):
          $idx     = getStatusIdx($key, $statusFlow);
          $isDone  = $idx < $currentStep;
          $isNow   = $key === $app['status'];
          $dotCls  = $isDone ? 'done' : ($isNow ? 'now' : '');
          $dotTxt  = $isDone ? '✓' : ($isNow ? $step['icon'] : '');
        ?>
        <div class="tl-step">
          <div class="tl-dot <?= $dotCls ?>" title="<?= e($step['label']) ?>"><?= $dotTxt ?></div>
          <div class="tl-body">
            <strong style="<?= (!$isDone && !$isNow) ? 'color:var(--ink4)' : '' ?>"><?= e($step['label']) ?></strong>
            <?php if ($isDone || $isNow): ?>
            <span><?= e($step['desc']) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Action cards based on status -->
    <?php if ($app['status'] === 'Admitted'): ?>
    <div class="alert alert-ok" style="padding:var(--sp6);border-radius:var(--r3)">
      <div style="font-size:2.5rem;margin-bottom:var(--sp3)">🎉</div>
      <h3 style="font-size:1.1rem;font-weight:800;color:var(--grn);margin-bottom:6px">Congratulations! You have been admitted.</h3>
      <p style="font-size:.9rem;color:#175c2e;line-height:1.76">Please visit the school office to complete your enrolment, pay your fees and collect your welcome pack.</p>
      <a href="<?= BASE_URL ?>/contact.php" class="btn btn-green" style="margin-top:var(--sp4)">Get Directions →</a>
    </div>

    <?php elseif ($app['status'] === 'Approved for entrance' && !empty($app['entrance_letter_ref'])): ?>
    <div class="alert alert-ok" style="padding:var(--sp5);border-radius:var(--r2)">
      <strong style="color:var(--grn)">📄 Entrance Eligibility Letter Ready</strong>
      <p style="font-size:.88rem;color:#175c2e;margin:var(--sp2) 0 var(--sp4);line-height:1.7">Your entrance eligibility letter has been generated. Download it and bring it on examination day.</p>
      <a href="<?= BASE_URL ?>/letters/entrance_letter.php?app=<?= urlencode($app['application_number']) ?>&phone=<?= urlencode($app['phone'] ?? '') ?>"
         class="btn btn-green btn-sm" target="_blank" rel="noopener">Download Letter →</a>
    </div>

    <?php elseif ($app['status'] === 'Entrance scheduled'): ?>
    <div class="alert alert-info" style="padding:var(--sp5);border-radius:var(--r2)">
      <strong>📅 Entrance Examination Scheduled</strong>
      <?php if (!empty($app['entrance_exam_date'])): ?>
      <p style="font-size:.88rem;margin-top:var(--sp2);line-height:1.7">
        <strong>Date:</strong> <?= date('l, F d, Y', strtotime($app['entrance_exam_date'])) ?>
        <?= !empty($app['entrance_exam_time']) ? ' &middot; <strong>Time:</strong> '.$app['entrance_exam_time'] : '' ?>
        <br>Please arrive at least 15 minutes early with valid identification.
      </p>
      <?php endif; ?>
    </div>

    <?php elseif ($app['status'] === 'Documents needed'): ?>
    <div class="alert alert-warn" style="padding:var(--sp5);border-radius:var(--r2)">
      <strong>📎 Documents Required</strong>
      <p style="font-size:.88rem;margin:var(--sp2) 0 var(--sp4);line-height:1.7">Please bring or submit the required documents to the admissions office as soon as possible to avoid delays.</p>
      <a href="<?= BASE_URL ?>/contact.php" class="btn btn-outline btn-sm">Contact Admissions</a>
    </div>
    <?php endif; ?>

    <?php elseif ($searched && !$app): ?>
    <!-- Not found -->
    <div class="contact-form-wrap fade-up" style="text-align:center;padding:var(--sp10)">
      <div style="font-size:3rem;margin-bottom:var(--sp4)">🔍</div>
      <h3 class="h4">Application not found</h3>
      <p class="body" style="margin:var(--sp3) auto var(--sp6);max-width:400px">We could not locate an application matching those details. Please double-check your application number and phone number.</p>
      <a href="<?= BASE_URL ?>/contact.php" class="btn btn-outline">Contact Admissions</a>
    </div>
    <?php endif; ?>

    <!-- Help cards -->
    <div class="cards c3 fade-up" style="margin-top:var(--sp10)">
      <article class="card" style="padding:var(--sp5)">
        <div class="card-ico">📋</div>
        <h3>Haven't applied yet?</h3>
        <p>Start your online application today for the <?= e($ay) ?> academic year.</p>
        <a href="<?= BASE_URL ?>/apply.php" class="lnk" style="margin-top:var(--sp4)">Apply now</a>
      </article>
      <article class="card" style="padding:var(--sp5)">
        <div class="card-ico">📞</div>
        <h3>Need help?</h3>
        <p>Can't find your application or have questions? Our team is ready to assist you.</p>
        <a href="<?= BASE_URL ?>/contact.php" class="lnk" style="margin-top:var(--sp4)">Contact us</a>
      </article>
      <article class="card" style="padding:var(--sp5)">
        <div class="card-ico">📄</div>
        <h3>Required documents</h3>
        <p>Ensure you have your report card, birth certificate and passport photo ready.</p>
        <a href="<?= BASE_URL ?>/admissions.php" class="lnk" style="margin-top:var(--sp4)">Admissions info</a>
      </article>
    </div>
  </div>
</section>

<style>
@media(max-width:560px){
  .status-form-grid{grid-template-columns:1fr !important}
}
</style>

<!-- CTA -->
<section class="cta-band">
  <div class="wrap">
    <div class="cta-row">
      <div class="cta-copy">
        <div class="eyebrow" style="color:rgba(255,255,255,.55)">Admissions</div>
        <h2 class="cta-h">Questions about<br><em>the process?</em></h2>
        <p>Our admissions team is available Monday–Friday to answer any questions about your application.</p>
        <div class="cta-acts">
          <a href="<?= BASE_URL ?>/admissions.php" class="btn btn-white btn-lg">Admissions Info</a>
          <a href="<?= BASE_URL ?>/contact.php"    class="btn btn-ghost btn-lg">Contact Us</a>
        </div>
      </div>
      <div class="cta-aside">
        <div class="ico">📍</div>
        <strong>Visit Our Office</strong>
        <p>Karnplay, Nimba County, Liberia.<br>Mon–Fri · 8:00 AM – 4:30 PM.</p>
        <a href="<?= BASE_URL ?>/contact.php" class="lnk" style="color:#a8dfba;margin-top:var(--sp3)">Get directions</a>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>
