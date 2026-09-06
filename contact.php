<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'Contact Us';
$activeNav = 'contact';
$metaDesc  = 'Get in touch with Karn High School — call, email or visit us in Karnplay, Nimba County, Liberia.';
$success   = false;
$errors    = [];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $name    = trim($_POST['name']    ?? '');
    $phone   = trim($_POST['phone']   ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    if ($name==='')    $errors[]='Your name is required.';
    if ($message==='') $errors[]='A message is required.';
    if (empty($errors)) {
        db()->prepare('INSERT INTO contact_messages (name,phone,message) VALUES (?,?,?)')->execute([$name, $phone?:null, ($subject?"Subject: $subject\n\n":'').$message]);
        $success = true;
    }
}

include __DIR__.'/includes/header.php';
?>

<section class="page-hero">
  <div class="wrap ph-body">
    <nav class="bc" aria-label="Breadcrumb"><a href="<?=BASE_URL?>/">Home</a><span class="bc-sep">/</span><span>Contact</span></nav>
    <div class="eyebrow inv">Get In Touch</div>
    <h1 class="ph-h">We'd love to<br><em>hear from you.</em></h1>
    <p class="ph-lead">Whether you have a question about admissions, academics, fees or anything else — our team is here to help.</p>
  </div>
</section>

<section class="sec bg-white">
  <div class="wrap">
    <div class="two-col">

      <!-- Info -->
      <div>
        <div class="eyebrow">Contact Information</div>
        <h2 class="h2" style="margin:8px 0 28px">Reach us<br><em>anytime.</em></h2>
        <div class="contact-info-list">
          <div class="ci-item"><div class="ci-ico" aria-hidden="true">📞</div><div><h4>Call Us</h4><p><?= e(setting('school_phone','+231 886 417 711')) ?><?= setting('school_phone2') ? '<br>'.e(setting('school_phone2')) : '' ?></p></div></div>
          <div class="ci-item"><div class="ci-ico" aria-hidden="true">✉️</div><div><h4>Email Us</h4><p><a href="mailto:<?= e(setting('school_email','info@karnhighschool.edu.lr')) ?>" style="color:var(--red)"><?= e(setting('school_email','info@karnhighschool.edu.lr')) ?></a></p></div></div>
          <div class="ci-item"><div class="ci-ico" aria-hidden="true">📍</div><div><h4>Visit Us</h4><p>Karn High School<br>Karnplay, Nimba County<br>Liberia, West Africa</p></div></div>
          <div class="ci-item"><div class="ci-ico" aria-hidden="true">🕐</div><div><h4>Office Hours</h4><p><?= e(setting('office_hours','Monday–Friday, 8:00am–4:00pm')) ?></p></div></div>
        </div>
        <div style="margin-top:28px;background:var(--bg2);border-radius:var(--r3);padding:24px">
          <h3 style="font-size:1rem;font-weight:700;margin-bottom:16px;color:var(--ink)">Quick Links</h3>
          <div style="display:flex;flex-direction:column;gap:10px">
            <a href="<?=BASE_URL?>/apply.php"              class="btn btn-primary btn-sm" style="align-self:flex-start">📋 Apply for Admission →</a>
            <a href="<?=BASE_URL?>/application-status.php" class="btn btn-outline  btn-sm" style="align-self:flex-start">🔍 Track Application Status</a>
            <a href="<?=BASE_URL?>/faq.php"                class="btn btn-outline  btn-sm" style="align-self:flex-start">❓ View FAQs</a>
          </div>
        </div>
      </div>

      <!-- Form -->
      <div class="contact-form-wrap">
        <h2 style="font-size:1.3rem;font-weight:800;margin-bottom:6px;color:var(--ink)">Send a Message</h2>
        <p class="caption" style="margin-bottom:24px">We'll respond as soon as possible during office hours.</p>

        <?php if($success): ?>
        <div class="alert alert-ok" role="alert">✓ Your message has been sent. We'll be in touch soon!</div>
        <?php endif; ?>
        <?php foreach($errors as $err): ?>
        <div class="alert alert-err" role="alert"><?= e($err) ?></div>
        <?php endforeach; ?>

        <?php if(!$success): ?>
        <form method="post" novalidate>
          <?= csrfField() ?>
          <div class="form-grp">
            <label for="cn">Your Name <span style="color:var(--red)">*</span></label>
            <input type="text" id="cn" name="name" class="form-ctrl" required placeholder="Full name" value="<?= e($_POST['name']??'') ?>"/>
          </div>
          <div class="form-grp">
            <label for="cp">Phone Number</label>
            <input type="tel" id="cp" name="phone" class="form-ctrl" placeholder="+231 886 …" value="<?= e($_POST['phone']??'') ?>"/>
          </div>
          <div class="form-grp">
            <label for="cs">Subject</label>
            <input type="text" id="cs" name="subject" class="form-ctrl" placeholder="e.g. Admissions enquiry" value="<?= e($_POST['subject']??'') ?>"/>
          </div>
          <div class="form-grp">
            <label for="cm">Message <span style="color:var(--red)">*</span></label>
            <textarea id="cm" name="message" class="form-ctrl" required placeholder="Write your message here…" rows="5"><?= e($_POST['message']??'') ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary btn-full">Send Message →</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>
