<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'Terms & Conditions';
$activeNav = '';
$metaDesc  = 'Terms and conditions for using the Karn High School website and student management portal.';
include __DIR__.'/includes/header.php';
?>

<!-- Page Hero -->
<section class="page-hero">
  <div class="wrap ph-body">
    <nav class="bc" aria-label="Breadcrumb">
      <a href="<?= BASE_URL ?>/">Home</a>
      <span class="bc-sep">/</span>
      <span>Terms &amp; Conditions</span>
    </nav>
    <div class="eyebrow inv">Legal</div>
    <h1 class="ph-h">Terms &amp;<br><em>Conditions.</em></h1>
    <p class="ph-lead">Last updated: September 2026</p>
  </div>
</section>

<!-- Content -->
<section class="sec bg-white">
  <div class="wrap">
    <div style="display:grid;grid-template-columns:220px 1fr;gap:clamp(32px,5vw,64px);align-items:start" class="terms-layout">

      <!-- Sidebar TOC -->
      <aside style="position:sticky;top:calc(var(--nav-h) + 24px)" class="terms-toc">
        <div style="background:var(--bg2);border-radius:var(--r3);padding:var(--sp5)">
          <h3 style="font-size:.74rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--ink3);margin-bottom:var(--sp4)">Contents</h3>
          <nav style="display:flex;flex-direction:column;gap:2px">
            <?php
            $sections = [
              ['id'=>'acceptance',    'label'=>'Acceptance'],
              ['id'=>'acceptable-use','label'=>'Acceptable Use'],
              ['id'=>'applications',  'label'=>'Applications'],
              ['id'=>'entrance-exam', 'label'=>'Entrance Examination'],
              ['id'=>'payments',      'label'=>'Fees &amp; Payments'],
              ['id'=>'portal',        'label'=>'Portal Access'],
              ['id'=>'ip',            'label'=>'Intellectual Property'],
              ['id'=>'liability',     'label'=>'Liability'],
              ['id'=>'changes',       'label'=>'Changes to Terms'],
              ['id'=>'contact',       'label'=>'Contact'],
            ];
            foreach ($sections as $s): ?>
            <a href="#<?= $s['id'] ?>" style="font-size:.82rem;color:var(--ink2);padding:6px var(--sp3);border-radius:var(--r1);transition:all var(--t1);display:block"
               onmouseover="this.style.background='var(--bg)';this.style.color='var(--red)'"
               onmouseout="this.style.background='';this.style.color='var(--ink2)'"><?= $s['label'] ?></a>
            <?php endforeach; ?>
          </nav>
          <div class="divider" style="margin:var(--sp4) 0"></div>
          <a href="<?= BASE_URL ?>/privacy.php" style="font-size:.82rem;color:var(--ink3);display:flex;align-items:center;gap:4px">
            Privacy Policy →
          </a>
        </div>
      </aside>

      <!-- Prose content -->
      <article class="prose">

        <section id="acceptance">
          <h2 class="h3" style="margin-bottom:var(--sp4)">Acceptance of Terms</h2>
          <p>By accessing or using the Karn High School website, online application portal or any digital service provided by the Karn High School Management Information System (KHSMIS), you agree to be bound by these Terms &amp; Conditions. If you do not agree with any part of these terms, please discontinue use of our services.</p>
          <p>These terms apply to all users including prospective and current students, parents, guardians and school staff.</p>
        </section>

        <div class="divider"></div>

        <section id="acceptable-use">
          <h2 class="h3" style="margin-bottom:var(--sp4)">Acceptable Use</h2>
          <p>Users of the Karn High School website and portal agree to:</p>
          <ul style="display:flex;flex-direction:column;gap:var(--sp2);margin:var(--sp4) 0">
            <?php foreach ([
              'Use the system only for lawful purposes related to school administration, admissions, academic management and communication',
              'Not attempt to gain unauthorised access to any part of the system or another user\'s account',
              'Not share login credentials with any other person',
              'Not upload, transmit or distribute malicious code, viruses or harmful content',
              'Not use the system to harass, threaten or impersonate any individual',
              'Comply with all applicable laws of the Republic of Liberia',
            ] as $item): ?>
            <li style="display:flex;align-items:flex-start;gap:var(--sp3);font-size:.95rem;line-height:1.72;color:var(--ink2)">
              <span style="width:6px;height:6px;background:var(--red);border-radius:50%;flex-shrink:0;margin-top:9px"></span>
              <?= $item ?>
            </li>
            <?php endforeach; ?>
          </ul>
          <p>Karn High School reserves the right to suspend or terminate access for any user found to be in violation of these terms.</p>
        </section>

        <div class="divider"></div>

        <section id="applications">
          <h2 class="h3" style="margin-bottom:var(--sp4)">Admission Applications</h2>
          <p>All information submitted through the online admission application must be truthful, accurate and complete. By submitting an application, the applicant and parent/guardian confirm that:</p>
          <ul style="display:flex;flex-direction:column;gap:var(--sp2);margin:var(--sp4) 0">
            <?php foreach ([
              'All information provided is accurate and complete to the best of their knowledge',
              'Supporting documents submitted are genuine and unaltered',
              'Any misrepresentation or omission of material facts may result in rejection of the application or withdrawal of any offer of admission at any stage',
            ] as $item): ?>
            <li style="display:flex;align-items:flex-start;gap:var(--sp3);font-size:.95rem;line-height:1.72;color:var(--ink2)">
              <span style="width:6px;height:6px;background:var(--red);border-radius:50%;flex-shrink:0;margin-top:9px"></span>
              <?= $item ?>
            </li>
            <?php endforeach; ?>
          </ul>
          <p>Submission of an application does not guarantee admission. All applications are subject to review and the school's admission criteria for the relevant grade level and academic year.</p>
        </section>

        <div class="divider"></div>

        <section id="entrance-exam">
          <h2 class="h3" style="margin-bottom:var(--sp4)">Entrance Examination</h2>
          <p>Applicants invited to sit the entrance examination agree to the following conditions:</p>
          <ul style="display:flex;flex-direction:column;gap:var(--sp2);margin:var(--sp4) 0">
            <?php foreach ([
              'The examination must be completed independently without assistance from any other person',
              'Any form of cheating, plagiarism or impersonation is grounds for immediate disqualification',
              'The school reserves the right to verify results through additional assessment at any time',
              'Entrance examination results are the property of Karn High School and will not be shared with third parties',
            ] as $item): ?>
            <li style="display:flex;align-items:flex-start;gap:var(--sp3);font-size:.95rem;line-height:1.72;color:var(--ink2)">
              <span style="width:6px;height:6px;background:var(--red);border-radius:50%;flex-shrink:0;margin-top:9px"></span>
              <?= $item ?>
            </li>
            <?php endforeach; ?>
          </ul>
        </section>

        <div class="divider"></div>

        <section id="payments">
          <h2 class="h3" style="margin-bottom:var(--sp4)">Fees &amp; Payments</h2>
          <p>School fees are due in accordance with the payment schedule provided at the time of enrolment. The following terms apply to all fee payments:</p>
          <ul style="display:flex;flex-direction:column;gap:var(--sp2);margin:var(--sp4) 0">
            <?php foreach ([
              'Fees, once paid, are subject to the school\'s refund policy as communicated at the time of payment',
              'Official receipts generated by the school\'s system serve as proof of payment',
              'The school is not responsible for payments made through unofficial channels',
              'Payment disputes should be directed to the Bursar\'s office within 30 days of the transaction',
              'Non-payment of fees may result in restricted access to examinations, reports and school services',
            ] as $item): ?>
            <li style="display:flex;align-items:flex-start;gap:var(--sp3);font-size:.95rem;line-height:1.72;color:var(--ink2)">
              <span style="width:6px;height:6px;background:var(--red);border-radius:50%;flex-shrink:0;margin-top:9px"></span>
              <?= $item ?>
            </li>
            <?php endforeach; ?>
          </ul>
        </section>

        <div class="divider"></div>

        <section id="portal">
          <h2 class="h3" style="margin-bottom:var(--sp4)">Portal Access</h2>
          <p>Access to the student, parent and staff portals is provided for the sole purpose of managing academic information relevant to Karn High School. Users are responsible for maintaining the confidentiality of their login credentials. The school accepts no liability for any loss or damage arising from unauthorised access resulting from the user's failure to maintain credential security.</p>
          <p>Portal access may be suspended at any time for reasons including non-payment of fees, disciplinary action, graduation, withdrawal or system maintenance.</p>
        </section>

        <div class="divider"></div>

        <section id="ip">
          <h2 class="h3" style="margin-bottom:var(--sp4)">Intellectual Property</h2>
          <p>All content on the Karn High School website and management system — including text, images, logos, report card formats, documents and data structures — is the intellectual property of Karn High School. Content may not be reproduced, republished, distributed or used for commercial purposes without the prior written permission of the school.</p>
        </section>

        <div class="divider"></div>

        <section id="liability">
          <h2 class="h3" style="margin-bottom:var(--sp4)">Limitation of Liability</h2>
          <p>While Karn High School takes reasonable steps to ensure the accuracy and availability of its online systems, we do not guarantee uninterrupted or error-free service. The school is not liable for any direct, indirect or consequential loss arising from your use of the website or portal, including temporary outages, data errors or delays in processing.</p>
        </section>

        <div class="divider"></div>

        <section id="changes">
          <h2 class="h3" style="margin-bottom:var(--sp4)">Changes to These Terms</h2>
          <p>Karn High School reserves the right to update or modify these Terms &amp; Conditions at any time. Changes will be published on this page with an updated &ldquo;Last updated&rdquo; date. Continued use of the website or portal following the publication of changes constitutes acceptance of the revised terms.</p>
          <p>We encourage users to review this page periodically to stay informed of any updates.</p>
        </section>

        <div class="divider"></div>

        <section id="contact">
          <h2 class="h3" style="margin-bottom:var(--sp4)">Contact</h2>
          <p>For any questions or concerns regarding these Terms &amp; Conditions, please contact:</p>
          <div style="background:var(--bg2);border-radius:var(--r2);padding:var(--sp5);margin-top:var(--sp4)">
            <strong style="font-size:1rem;display:block;margin-bottom:var(--sp3)">Karn High School</strong>
            <div style="display:flex;flex-direction:column;gap:var(--sp2);font-size:.9rem;color:var(--ink2)">
              <span>📍 Karnplay, Nimba County, Liberia</span>
              <span>📞 <?= e(setting('school_phone', '+231 886 417 711')) ?></span>
              <span>✉️ <?= e(setting('school_email', 'info@karnhighschool.edu.lr')) ?></span>
              <span>🕐 Monday – Friday · 8:00 AM – 4:30 PM</span>
            </div>
          </div>
        </section>

      </article>
    </div>
  </div>
</section>

<style>
@media(max-width:768px){
  .terms-layout{grid-template-columns:1fr !important}
  .terms-toc{position:static !important}
}
</style>

<!-- CTA -->
<section class="cta-band">
  <div class="wrap">
    <div class="cta-row">
      <div class="cta-copy">
        <div class="eyebrow" style="color:rgba(255,255,255,.55)">Any Questions?</div>
        <h2 class="cta-h">Get in touch with<br><em>our team.</em></h2>
        <p>If you have any questions about our terms or how our school operates, we're always here to help.</p>
        <div class="cta-acts">
          <a href="<?= BASE_URL ?>/contact.php"  class="btn btn-white btn-lg">Contact Us</a>
          <a href="<?= BASE_URL ?>/privacy.php"  class="btn btn-ghost btn-lg">Privacy Policy</a>
        </div>
      </div>
      <div class="cta-aside">
        <div class="ico">📋</div>
        <strong>Ready to apply?</strong>
        <p>Now that you understand our terms, take the next step and submit your admission application.</p>
        <a href="<?= BASE_URL ?>/apply.php" class="lnk" style="color:#a8dfba;margin-top:var(--sp3)">Apply now</a>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>
