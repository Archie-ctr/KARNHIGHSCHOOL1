<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'Privacy Policy';
$activeNav = '';
$metaDesc  = 'Privacy Policy for Karn High School — how we collect, use and protect your personal data.';
include __DIR__.'/includes/header.php';
?>

<!-- Page Hero -->
<section class="page-hero">
  <div class="wrap ph-body">
    <nav class="bc" aria-label="Breadcrumb">
      <a href="<?= BASE_URL ?>/">Home</a>
      <span class="bc-sep">/</span>
      <span>Privacy Policy</span>
    </nav>
    <div class="eyebrow inv">Legal</div>
    <h1 class="ph-h">Privacy<br><em>Policy.</em></h1>
    <p class="ph-lead">Last updated: September 2026</p>
  </div>
</section>

<!-- Content -->
<section class="sec bg-white">
  <div class="wrap">
    <div style="display:grid;grid-template-columns:220px 1fr;gap:clamp(32px,5vw,64px);align-items:start" class="prose-layout">

      <!-- Sidebar TOC -->
      <aside style="position:sticky;top:calc(var(--nav-h) + 24px)" class="prose-toc">
        <div style="background:var(--bg2);border-radius:var(--r3);padding:var(--sp5)">
          <h3 style="font-size:.74rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--ink3);margin-bottom:var(--sp4)">Contents</h3>
          <nav style="display:flex;flex-direction:column;gap:2px">
            <?php
            $sections = [
              ['id'=>'overview',     'label'=>'Overview'],
              ['id'=>'collection',   'label'=>'Information We Collect'],
              ['id'=>'use',          'label'=>'How We Use It'],
              ['id'=>'security',     'label'=>'Data Security'],
              ['id'=>'documents',    'label'=>'Document Storage'],
              ['id'=>'retention',    'label'=>'Data Retention'],
              ['id'=>'rights',       'label'=>'Your Rights'],
              ['id'=>'cookies',      'label'=>'Cookies'],
              ['id'=>'children',     'label'=>'Children'],
              ['id'=>'contact',      'label'=>'Contact Us'],
            ];
            foreach ($sections as $s): ?>
            <a href="#<?= $s['id'] ?>" style="font-size:.82rem;color:var(--ink2);padding:6px var(--sp3);border-radius:var(--r1);transition:all var(--t1);display:block"
               onmouseover="this.style.background='var(--bg)';this.style.color='var(--red)'"
               onmouseout="this.style.background='';this.style.color='var(--ink2)'"><?= $s['label'] ?></a>
            <?php endforeach; ?>
          </nav>
          <div class="divider" style="margin:var(--sp4) 0"></div>
          <a href="<?= BASE_URL ?>/terms.php" style="font-size:.82rem;color:var(--ink3);display:flex;align-items:center;gap:4px">
            Terms &amp; Conditions →
          </a>
        </div>
      </aside>

      <!-- Prose content -->
      <article class="prose">

        <section id="overview">
          <h2 class="h3" style="margin-bottom:var(--sp4)">Overview</h2>
          <p>Karn High School (&ldquo;we&rdquo;, &ldquo;our&rdquo;, &ldquo;us&rdquo;) is committed to protecting the privacy of students, parents, guardians and all visitors who interact with our school management system and public website. This policy explains what personal information we collect, why we collect it, how it is used and how it is protected.</p>
          <p>By using our website or online application portal, you agree to the collection and use of information in accordance with this policy.</p>
        </section>

        <div class="divider"></div>

        <section id="collection">
          <h2 class="h3" style="margin-bottom:var(--sp4)">Information We Collect</h2>
          <p>We collect personal information in order to operate our school management system and process admissions applications. This may include:</p>
          <ul style="display:flex;flex-direction:column;gap:var(--sp2);margin:var(--sp4) 0">
            <?php foreach ([
              'Full name, date of birth and gender',
              'Contact information: phone number, email address and physical address',
              'Academic records, grades and attendance data',
              'Payment and fee-related financial records',
              'Uploaded documents: birth certificates, report cards and passport photos',
              'Guardian and emergency contact information',
              'Application tracking data and admission status history',
              'System login credentials (passwords are stored as secure hashes)',
            ] as $item): ?>
            <li style="display:flex;align-items:flex-start;gap:var(--sp3);font-size:.95rem;line-height:1.72;color:var(--ink2)">
              <span style="width:6px;height:6px;background:var(--red);border-radius:50%;flex-shrink:0;margin-top:9px"></span>
              <?= $item ?>
            </li>
            <?php endforeach; ?>
          </ul>
          <p>We do not collect sensitive personal data beyond what is necessary for school administration unless required by applicable Liberian educational regulations.</p>
        </section>

        <div class="divider"></div>

        <section id="use">
          <h2 class="h3" style="margin-bottom:var(--sp4)">How We Use Your Information</h2>
          <p>Personal information is used exclusively for school-related purposes, including:</p>
          <ul style="display:flex;flex-direction:column;gap:var(--sp2);margin:var(--sp4) 0">
            <?php foreach ([
              'Processing and tracking admission applications',
              'Managing student academic records, attendance and results',
              'Communicating with students and parents about school matters',
              'Processing fee payments and generating financial statements',
              'Maintaining disciplinary records where necessary',
              'Generating reports for academic and administrative use',
              'Complying with applicable Liberian educational laws and regulations',
            ] as $item): ?>
            <li style="display:flex;align-items:flex-start;gap:var(--sp3);font-size:.95rem;line-height:1.72;color:var(--ink2)">
              <span style="width:6px;height:6px;background:var(--red);border-radius:50%;flex-shrink:0;margin-top:9px"></span>
              <?= $item ?>
            </li>
            <?php endforeach; ?>
          </ul>
          <p>We do not sell, rent or share personal information with third parties for marketing purposes.</p>
        </section>

        <div class="divider"></div>

        <section id="security">
          <h2 class="h3" style="margin-bottom:var(--sp4)">Data Security</h2>
          <p>We implement appropriate technical and organisational measures to protect personal data against unauthorised access, alteration, disclosure or destruction. These measures include:</p>
          <ul style="display:flex;flex-direction:column;gap:var(--sp2);margin:var(--sp4) 0">
            <?php foreach ([
              'Secure password hashing using industry-standard algorithms',
              'Role-based access control limiting data access to authorised personnel only',
              'CSRF protection on all form submissions',
              'Session management with secure, server-side token validation',
              'Restricted file upload handling with type and size validation',
            ] as $item): ?>
            <li style="display:flex;align-items:flex-start;gap:var(--sp3);font-size:.95rem;line-height:1.72;color:var(--ink2)">
              <span style="width:6px;height:6px;background:var(--red);border-radius:50%;flex-shrink:0;margin-top:9px"></span>
              <?= $item ?>
            </li>
            <?php endforeach; ?>
          </ul>
        </section>

        <div class="divider"></div>

        <section id="documents">
          <h2 class="h3" style="margin-bottom:var(--sp4)">Document Storage</h2>
          <p>Uploaded documents such as birth certificates, report cards and passport photographs are stored securely on our server. They are accessible only to authorised admissions and administrative staff. Documents are not shared with external third parties without the explicit consent of the student or parent/guardian, except where required by law.</p>
        </section>

        <div class="divider"></div>

        <section id="retention">
          <h2 class="h3" style="margin-bottom:var(--sp4)">Data Retention</h2>
          <p>We retain personal data for as long as it is necessary for the purposes for which it was collected, or as required by applicable educational record-keeping requirements. Student academic records are retained for a minimum of seven years after graduation or departure. Applications that do not result in admission are retained for two academic years before being securely deleted.</p>
        </section>

        <div class="divider"></div>

        <section id="rights">
          <h2 class="h3" style="margin-bottom:var(--sp4)">Your Rights</h2>
          <p>Students, parents and guardians have the following rights regarding their personal information:</p>
          <ul style="display:flex;flex-direction:column;gap:var(--sp2);margin:var(--sp4) 0">
            <?php foreach ([
              'The right to access personal information held by the school',
              'The right to request correction of inaccurate or incomplete data',
              'The right to request deletion of data where retention is no longer necessary',
              'The right to object to processing in certain circumstances',
            ] as $item): ?>
            <li style="display:flex;align-items:flex-start;gap:var(--sp3);font-size:.95rem;line-height:1.72;color:var(--ink2)">
              <span style="width:6px;height:6px;background:var(--red);border-radius:50%;flex-shrink:0;margin-top:9px"></span>
              <?= $item ?>
            </li>
            <?php endforeach; ?>
          </ul>
          <p>To exercise any of these rights, please contact us using the details below. Requests will be reviewed and responded to within a reasonable timeframe, subject to applicable legal requirements.</p>
        </section>

        <div class="divider"></div>

        <section id="cookies">
          <h2 class="h3" style="margin-bottom:var(--sp4)">Cookies &amp; Sessions</h2>
          <p>Our website and portal use session cookies to maintain login state and provide a functional experience. These cookies are temporary and are removed when you close your browser. We do not use tracking cookies or third-party advertising cookies. You may configure your browser to refuse cookies, but this may affect the functionality of the portal.</p>
        </section>

        <div class="divider"></div>

        <section id="children">
          <h2 class="h3" style="margin-bottom:var(--sp4)">Children</h2>
          <p>Karn High School collects information about minors as part of its core educational function. All student data is collected with the knowledge and consent of a parent or guardian as part of the admission and enrolment process. We do not knowingly collect personal information from minors for any purpose unrelated to education.</p>
        </section>

        <div class="divider"></div>

        <section id="contact">
          <h2 class="h3" style="margin-bottom:var(--sp4)">Contact Us</h2>
          <p>For privacy-related enquiries, requests or concerns, please contact:</p>
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
  .prose-layout{grid-template-columns:1fr !important}
  .prose-toc{position:static !important}
}
</style>

<!-- CTA -->
<section class="cta-band">
  <div class="wrap">
    <div class="cta-row">
      <div class="cta-copy">
        <div class="eyebrow" style="color:rgba(255,255,255,.55)">Questions?</div>
        <h2 class="cta-h">We're here to<br><em>help you.</em></h2>
        <p>Have a privacy concern or question about how we use your data? Contact our administration office directly.</p>
        <div class="cta-acts">
          <a href="<?= BASE_URL ?>/contact.php" class="btn btn-white btn-lg">Contact Us</a>
          <a href="<?= BASE_URL ?>/terms.php"   class="btn btn-ghost btn-lg">Terms &amp; Conditions</a>
        </div>
      </div>
      <div class="cta-aside">
        <div class="ico">🔒</div>
        <strong>Your data is safe</strong>
        <p>We take data protection seriously and apply security best practices across all our systems.</p>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>
