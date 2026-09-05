<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'Contact Us';
$activeNav = 'contact';
$metaDesc  = 'Get in touch with Karn High School. Visit us in Karnplay, Nimba County, call us, or send a message online.';
$success   = false;
$errors    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name    = trim($_POST['name']    ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if ($name    === '') $errors[] = 'Your name is required.';
    if ($message === '') $errors[] = 'A message is required.';
    if (empty($errors)) {
        $stmt = db()->prepare('INSERT INTO contact_messages (name,phone,message) VALUES (?,?,?)');
        $stmt->execute([$name, $phone ?: null, ($subject ? "Subject: $subject\n\n" : '').$message]);
        $success = true;
    }
}

include __DIR__.'/includes/header.php';
?>

<!-- Page Hero -->
<section class="page-hero">
  <div class="wrap page-hero-inner">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="<?= BASE_URL ?>/">Home</a><span>/</span><span>Contact</span>
    </nav>
    <div class="section-tag">Get In Touch</div>
    <h1>We'd love to<br><em>hear from you.</em></h1>
    <p>Whether you have a question about admissions, academics, fees or anything else — our team is here to help.</p>
  </div>
</section>

<!-- Contact content -->
<section class="section bg-white">
  <div class="wrap">
    <div class="content-grid-2">

      <!-- Info column -->
      <div>
        <h2 class="section-title" style="font-size:clamp(1.4rem,2.5vw,2rem);margin-bottom:var(--space-6)">Contact Information</h2>
        <div class="contact-info-list">
          <div class="contact-info-item">
            <div class="contact-icon" aria-hidden="true">📞</div>
            <div>
              <h4>Call Us</h4>
              <p><?= e(setting('school_phone','+231 886 417 711')) ?></p>
              <?php if (setting('school_phone2')): ?>
              <p><?= e(setting('school_phone2')) ?></p>
              <?php endif; ?>
            </div>
          </div>
          <div class="contact-info-item">
            <div class="contact-icon" aria-hidden="true">✉️</div>
            <div>
              <h4>Email Us</h4>
              <p><?= e(setting('school_email','info@karnhighschool.edu.lr')) ?></p>
            </div>
          </div>
          <div class="contact-info-item">
            <div class="contact-icon" aria-hidden="true">📍</div>
            <div>
              <h4>Visit Us</h4>
              <p>Karn High School<br>Karnplay, Nimba County<br>Liberia, West Africa</p>
            </div>
          </div>
          <div class="contact-info-item">
            <div class="contact-icon" aria-hidden="true">🕐</div>
            <div>
              <h4>Office Hours</h4>
              <p><?= e(setting('office_hours','Monday–Friday, 8:00am–4:00pm')) ?></p>
            </div>
          </div>
        </div>

        <!-- Quick links box -->
        <div style="margin-top:var(--space-8);background:var(--c-bg-warm);border-radius:var(--r-lg);padding:var(--space-6)">
          <h3 style="font-size:1rem;font-weight:700;margin-bottom:var(--space-4);color:var(--c-ink)">Quick Links</h3>
          <div style="display:flex;flex-direction:column;gap:var(--space-3)">
            <a href="<?= BASE_URL ?>/apply.php"               class="btn btn-primary btn-sm" style="align-self:flex-start">📋 Apply for Admission →</a>
            <a href="<?= BASE_URL ?>/application-status.php"  class="btn btn-outline  btn-sm" style="align-self:flex-start">🔍 Track Application Status</a>
            <a href="<?= BASE_URL ?>/faq.php"                 class="btn btn-outline  btn-sm" style="align-self:flex-start">❓ View FAQs</a>
          </div>
        </div>
      </div>

      <!-- Form column -->
      <div class="contact-form-wrap">
        <h2 style="font-size:1.3rem;font-weight:800;margin-bottom:var(--space-2);color:var(--c-ink)">Send a Message</h2>
        <p style="font-size:0.88rem;color:var(--c-ink-3);margin-bottom:var(--space-6)">We'll get back to you as soon as possible during office hours.</p>

        <?php if ($success): ?>
        <div class="alert alert-success" role="alert">
          ✓ Your message has been sent! We'll be in touch soon.
        </div>
        <?php endif; ?>
        <?php foreach ($errors as $err): ?>
        <div class="alert alert-error" role="alert"><?= e($err) ?></div>
        <?php endforeach; ?>

        <?php if (!$success): ?>
        <form method="post" novalidate>
          <?= csrfField() ?>
          <div class="form-group">
            <label for="contact-name">Your Name <span style="color:var(--c-red)">*</span></label>
            <input type="text" id="contact-name" name="name" class="form-control" required
                   placeholder="Full name" value="<?= e($_POST['name'] ?? '') ?>"/>
          </div>
          <div class="form-group">
            <label for="contact-phone">Phone Number</label>
            <input type="tel" id="contact-phone" name="phone" class="form-control"
                   placeholder="+231 886 …" value="<?= e($_POST['phone'] ?? '') ?>"/>
          </div>
          <div class="form-group">
            <label for="contact-subject">Subject</label>
            <input type="text" id="contact-subject" name="subject" class="form-control"
                   placeholder="e.g. Admissions enquiry" value="<?= e($_POST['subject'] ?? '') ?>"/>
          </div>
          <div class="form-group">
            <label for="contact-message">Message <span style="color:var(--c-red)">*</span></label>
            <textarea id="contact-message" name="message" class="form-control" required
                      placeholder="Write your message here…" rows="5"><?= e($_POST['message'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">
            Send Message →
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>
