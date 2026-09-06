<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'Admissions';
$activeNav = 'admissions';
$metaDesc  = 'Apply to Karn High School — learn about the application process, requirements, and entrance examination.';
$ay        = currentAcademicYearName();
include __DIR__.'/includes/header.php';
?>

<section class="page-hero">
  <div class="wrap ph-body">
    <nav class="bc" aria-label="Breadcrumb"><a href="<?=BASE_URL?>/">Home</a><span class="bc-sep">/</span><span>Admissions</span></nav>
    <div class="eyebrow inv">Admissions <?= e($ay) ?></div>
    <h1 class="ph-h">Your next chapter<br><em>starts here.</em></h1>
    <p class="ph-lead">We welcome students who are ready to learn, grow and contribute. Applications for <?= e($ay) ?> are now open.</p>
    <div class="ph-btns">
      <a href="<?=BASE_URL?>/apply.php"               class="btn btn-white btn-lg">Apply Online →</a>
      <a href="<?=BASE_URL?>/application-status.php"  class="btn btn-ghost">Track Application</a>
    </div>
  </div>
</section>

<!-- Process -->
<section class="sec bg-white" id="process">
  <div class="wrap">
    <div class="sec-hd tc">
      <div class="eyebrow">How It Works</div>
      <h2 class="h2">Four steps to<br><em>enrol at KHS.</em></h2>
    </div>
    <div class="steps">
      <?php foreach([
        ['Apply Online',          'Complete the multi-step online application. Provide applicant details, guardian information, previous school record, and grade applying for. Takes under 10 minutes.'],
        ['Document Verification', 'Our admissions team reviews your application and verifies submitted documents. You can upload documents online or bring them to the school office.'],
        ['Entrance Examination',  'Approved applicants receive an official Entrance Eligibility Letter and are invited to sit the KHS entrance examination. Results are shared promptly.'],
        ['Final Admission',       'Successful candidates receive their official Admission Letter, Student ID number, class assignment, and fee information. Welcome to KHS!'],
      ] as $i=>[$t,$d]): ?>
      <article class="step"><div class="step-n"><?=$i+1?></div><h3><?=$t?></h3><p><?=$d?></p></article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Requirements & Entrance -->
<section class="sec bg-warm" id="requirements">
  <div class="wrap">
    <div class="two-col">
      <div>
        <div class="eyebrow">What You Need</div>
        <h2 class="h2">Admission<br><em>requirements.</em></h2>
        <p class="body" style="margin-top:16px;margin-bottom:24px">Gather the following before starting your application. All documents can be submitted online or at the school office.</p>
        <div class="pillar-list">
          <?php foreach([
            ['📋','Previous School Report Card',  'var(--red-p)','var(--red)',   'Your most recent academic report card from your previous school.'],
            ['🪪','Birth Certificate',             'var(--grn-p)','var(--grn)',   'Original or certified copy of the applicant\'s birth certificate or age-verification document.'],
            ['📷','Passport Photo',               'var(--gold-p)','var(--gold)', 'One recent passport-sized photograph (JPEG or PNG, max 2MB).'],
            ['📄','Other Documents',              'var(--bg2)',   'var(--ink3)',  'Any additional records, certificates or letters of recommendation (optional at application stage).'],
          ] as [$ico,$t,$bg,$col,$d]): ?>
          <div class="pillar">
            <div class="pillar-ico" style="background:<?=$bg?>;color:<?=$col?>" aria-hidden="true"><?=$ico?></div>
            <div><h3><?=$t?></h3><p><?=$d?></p></div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="alert alert-info" style="margin-top:20px">💡 <strong>Note:</strong> Documents can be uploaded after submitting your application — you will not lose your application number.</div>
      </div>

      <div id="entrance">
        <div class="eyebrow">Entrance Exam</div>
        <h2 class="h2">What to expect<br><em>on exam day.</em></h2>
        <p class="body" style="margin-top:16px;margin-bottom:20px">After your application is reviewed and documents verified, you will receive an official <strong>Entrance Eligibility Letter</strong> with your exam date.</p>
        <div class="pillar-list">
          <?php foreach([
            ['📚','Subjects',   'English, Mathematics, General Science, Reading &amp; Comprehension'],
            ['⏱','Duration',   'Approximately 60–90 minutes'],
            ['📊','Format',     'Multiple choice, True/False, Short answer'],
            ['🏆','Pass Mark',  'Minimum score determined per grade level'],
            ['💻','Online Option','Eligible applicants may sit the exam online'],
          ] as [$ico,$k,$v]): ?>
          <div class="pillar" style="padding:14px 16px">
            <div class="pillar-ico" style="background:var(--red-p);color:var(--red);width:36px;height:36px" aria-hidden="true"><?=$ico?></div>
            <div style="font-size:.88rem"><strong style="color:var(--ink)"><?=$k?>:</strong> <span style="color:var(--ink3)"><?=$v?></span></div>
          </div>
          <?php endforeach; ?>
        </div>
        <a href="<?=BASE_URL?>/apply.php" class="btn btn-primary btn-full" style="margin-top:20px">Begin Application →</a>
      </div>
    </div>
  </div>
</section>

<!-- FAQ -->
<section class="sec bg-white" id="faq">
  <div class="wrap" style="max-width:780px">
    <div class="sec-hd tc">
      <div class="eyebrow">FAQs</div>
      <h2 class="h2">Common admissions<br><em>questions.</em></h2>
    </div>
    <?php foreach([
      ['When can I apply?',                  'Applications for '.$ay.' are open now. We recommend applying early as places are limited.'],
      ['Is there an application fee?',       'No. There is no fee to submit an application to Karn High School.'],
      ['Can I apply without an email?',      'Yes. A phone number is sufficient. Email is optional.'],
      ['How long does the process take?',    'The full process — from application to final admission — typically takes 2–4 weeks.'],
      ['How do I track my application?',     'Visit our Application Status page and enter your application number and phone number to see real-time updates.'],
      ['What grades can I apply for?',       'We accept applications for ABC/KG (Kindergarten) through Grade 12. All levels are currently open for '.e($ay).'.'],
    ] as $i=>[$q,$a]): ?>
    <div class="faq-item">
      <button class="faq-q" onclick="toggleFaq(<?=$i?>)" aria-expanded="false" aria-controls="fa-<?=$i?>">
        <span><?=e($q)?></span>
        <span class="faq-ico" id="fi-<?=$i?>" aria-hidden="true">+</span>
      </button>
      <div class="faq-a" id="fa-<?=$i?>" role="region"><?=e($a)?></div>
    </div>
    <?php endforeach; ?>
    <div style="text-align:center;margin-top:32px">
      <p class="caption" style="margin-bottom:16px">Still have questions? We're happy to help.</p>
      <a href="<?=BASE_URL?>/contact.php" class="btn btn-outline">Contact Admissions →</a>
    </div>
  </div>
</section>

<section class="cta-band">
  <div class="wrap"><div class="cta-row">
    <div class="cta-copy">
      <div class="eyebrow">Applications Open — <?= e($ay) ?></div>
      <h2 class="cta-h">Give your child a<br><em>strong start.</em></h2>
      <p>The online application takes under 10 minutes. No fee. Instant application number. Track your status anytime.</p>
      <div class="cta-acts">
        <a href="<?=BASE_URL?>/apply.php"               class="btn btn-white btn-lg">Begin Application →</a>
        <a href="<?=BASE_URL?>/application-status.php"  class="btn btn-ghost">Track Existing Application</a>
      </div>
    </div>
  </div></div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>
