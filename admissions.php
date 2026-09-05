<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'Admissions';
$activeNav = 'admissions';
$metaDesc  = 'Apply to Karn High School. Find out how to apply, what documents are needed, and how the entrance examination works.';
$ay        = currentAcademicYearName();
include __DIR__.'/includes/header.php';
?>

<!-- Page Hero -->
<section class="page-hero">
  <div class="wrap page-hero-inner">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="<?= BASE_URL ?>/">Home</a><span>/</span><span>Admissions</span>
    </nav>
    <div class="section-tag">Admissions <?= e($ay) ?></div>
    <h1>Your next chapter<br><em>starts here.</em></h1>
    <p>We welcome students who are ready to learn, grow and contribute to our vibrant school community. Applications for <?= e($ay) ?> are open.</p>
    <div style="display:flex;gap:var(--space-3);flex-wrap:wrap;margin-top:var(--space-6)">
      <a href="<?= BASE_URL ?>/apply.php"              class="btn btn-white btn-lg">Apply Online →</a>
      <a href="<?= BASE_URL ?>/application-status.php" class="btn btn-secondary">Track Application</a>
    </div>
  </div>
</section>

<!-- Process -->
<section class="section bg-white" id="process">
  <div class="wrap">
    <div class="text-center" style="max-width:600px;margin:0 auto var(--space-12)">
      <div class="section-tag">Admission Steps</div>
      <h2 class="section-title">Four simple steps<br><em>to enrol.</em></h2>
    </div>
    <div class="process-grid">
      <?php
      $steps = [
        ['Apply Online',           'Complete the multi-step online application. Provide applicant details, guardian information, previous school record, and grade applying for. Takes under 10 minutes.'],
        ['Document Verification',  'Our admissions team reviews your application and verifies submitted documents. You may upload documents online or bring them to the school office.'],
        ['Entrance Examination',   'Approved applicants receive an Entrance Eligibility Letter and are invited to sit the KHS entrance examination. Results are shared promptly after review.'],
        ['Final Admission',        'Successful candidates receive their official Admission Letter, Student ID number, class assignment, and fee information. Welcome to KHS!'],
      ];
      foreach ($steps as $i => [$title,$desc]):
      ?>
      <article class="process-item">
        <div class="process-num"><?= $i+1 ?></div>
        <h3><?= $title ?></h3>
        <p><?= $desc ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Requirements -->
<section class="section bg-soft" id="requirements">
  <div class="wrap">
    <div class="content-grid-2" style="align-items:start">
      <div>
        <div class="section-tag">What You Need</div>
        <h2 class="section-title">Admission<br><em>requirements.</em></h2>
        <p class="body-lg" style="margin-bottom:var(--space-6)">Gather the following documents before starting your application. Documents can be submitted online or at the school office.</p>
        <?php
        $docs = [
          ['📋','Previous School Report Card',   'Your most recent academic report card from your previous school.'],
          ['🪪','Birth Certificate',             'Original or certified copy of the applicant\'s birth certificate or age-verification document.'],
          ['📷','Passport Photo',                'One recent passport-sized photograph (JPEG or PNG format, max 2MB).'],
          ['📄','Other Supporting Documents',   'Any additional academic records, certificates or letters of recommendation (optional).'],
        ];
        foreach ($docs as [$icon,$title,$desc]):
        ?>
        <div style="display:flex;gap:var(--space-4);margin-bottom:var(--space-5);align-items:flex-start">
          <div style="width:40px;height:40px;background:var(--c-red-light);color:var(--c-red);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0"><?= $icon ?></div>
          <div>
            <h4 style="font-size:.95rem;font-weight:700;color:var(--c-ink);margin-bottom:3px"><?= $title ?></h4>
            <p style="font-size:.86rem;color:var(--c-ink-3);line-height:1.65;margin:0"><?= $desc ?></p>
          </div>
        </div>
        <?php endforeach; ?>
        <div class="alert alert-info" style="margin-top:var(--space-4)">
          💡 <strong>Note:</strong> Documents can be uploaded after submitting your application. You will not lose your application number.
        </div>
      </div>

      <!-- Entrance exam info -->
      <div id="entrance" style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:var(--r-xl);padding:clamp(24px,4vw,40px);box-shadow:var(--shadow-sm)">
        <div style="font-size:2.4rem;margin-bottom:var(--space-4)">📝</div>
        <h3 style="font-size:1.3rem;font-weight:800;color:var(--c-ink);margin-bottom:var(--space-3)">Entrance Examination</h3>
        <p style="font-size:.9rem;color:var(--c-ink-3);line-height:1.75;margin-bottom:var(--space-5)">After your application is reviewed and documents verified, you will receive an official <strong>Entrance Eligibility Letter</strong> inviting you to the KHS entrance examination.</p>
        <div style="display:flex;flex-direction:column;gap:var(--space-3);margin-bottom:var(--space-6)">
          <?php
          $examInfo=[
            ['📚','Subjects','English, Mathematics, General Science, Reading & Comprehension'],
            ['⏱','Duration','Approximately 60–90 minutes'],
            ['📊','Format','Multiple choice, True/False, Short answer'],
            ['🏆','Passing','Minimum score determined by grade applied for'],
            ['💻','Online Available','Eligible applicants can sit the exam online'],
          ];
          foreach ($examInfo as [$ico,$k,$v]):
          ?>
          <div style="display:flex;gap:var(--space-3);align-items:flex-start;padding:var(--space-3);background:var(--c-bg);border-radius:var(--r-md)">
            <span style="font-size:1rem;flex-shrink:0"><?= $ico ?></span>
            <div style="font-size:.86rem"><strong style="color:var(--c-ink)"><?= $k ?>:</strong> <span style="color:var(--c-ink-3)"><?= $v ?></span></div>
          </div>
          <?php endforeach; ?>
        </div>
        <a href="<?= BASE_URL ?>/apply.php" class="btn btn-primary" style="width:100%;justify-content:center">Begin Application →</a>
      </div>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="section bg-white" id="faq">
  <div class="wrap" style="max-width:760px">
    <div class="text-center" style="margin-bottom:var(--space-10)">
      <div class="section-tag">FAQs</div>
      <h2 class="section-title">Common questions<br><em>about admissions.</em></h2>
    </div>
    <?php
    $faqs = [
      ['When can I apply?', 'Applications for '.$ay.' are open now. We recommend applying early as places are limited.'],
      ['Is there an application fee?', 'No. There is no fee to submit an application to Karn High School.'],
      ['Can I apply without an email address?', 'Yes. A phone number is sufficient. Email is optional.'],
      ['How long does the process take?', 'The full process — from application to final admission — typically takes 2–4 weeks depending on document submission and exam scheduling.'],
      ['Can I track my application online?', 'Yes. Visit our Application Status page and enter your application number and phone number to see real-time status updates.'],
      ['What grades can I apply for?', 'We accept applications for ABC/KG (Kindergarten) through Grade 12. All levels are currently open for '.e($ay).'.'],
    ];
    foreach ($faqs as $i => [$q, $a]):
    ?>
    <div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:var(--r-md);margin-bottom:var(--space-2);overflow:hidden">
      <button onclick="toggleFaq(<?= $i ?>)" style="width:100%;display:flex;justify-content:space-between;align-items:center;padding:16px 20px;font-size:.96rem;font-weight:700;color:var(--c-ink);text-align:left;background:none;border:none;cursor:pointer;gap:12px" aria-expanded="false">
        <span><?= e($q) ?></span>
        <span id="faq-icon-<?= $i ?>" style="font-size:1.2rem;color:var(--c-red);flex-shrink:0">+</span>
      </button>
      <div id="faq-body-<?= $i ?>" style="display:none;padding:0 20px 16px;font-size:.9rem;color:var(--c-ink-3);line-height:1.75"><?= e($a) ?></div>
    </div>
    <?php endforeach; ?>
    <div style="text-align:center;margin-top:var(--space-8)">
      <p style="font-size:.9rem;color:var(--c-ink-3);margin-bottom:var(--space-4)">Still have questions? We're happy to help.</p>
      <a href="<?= BASE_URL ?>/contact.php" class="btn btn-outline">Contact Admissions →</a>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-band">
  <div class="wrap">
    <div class="cta-inner">
      <div class="cta-text">
        <span class="overline">Applications Open — <?= e($ay) ?></span>
        <h2>Give your child a<br><em>strong start.</em></h2>
        <p>The online application takes under 10 minutes. No application fee. Instant application number. Track your status anytime.</p>
        <div class="cta-actions">
          <a href="<?= BASE_URL ?>/apply.php"               class="btn btn-white btn-lg">Begin Online Application →</a>
          <a href="<?= BASE_URL ?>/application-status.php"  class="btn btn-secondary">Track Existing Application</a>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>
