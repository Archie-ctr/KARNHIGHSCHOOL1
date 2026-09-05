<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'Privacy Policy';
$activeNav = '';
include __DIR__.'/includes/header.php';
?>
<main class="inner-page">
  <div class="container inner-hero">
    <div class="eyebrow">Legal <span></span></div>
    <h1>Privacy Policy</h1>
    <p>Last updated: September 2026</p>
  </div>
  <div class="container content-narrow" style="padding-bottom:80px">
    <div class="prose">
      <p>Karn High School ("we", "our", "us") is committed to protecting the privacy of students, parents, guardians and visitors who interact with our school management system and public website.</p>
      <h3 style="font-size:18px;font-weight:700;margin:24px 0 10px">Information We Collect</h3>
      <p>We collect personal information including names, contact details, date of birth, academic records and payment information, necessary for the purposes of school administration, admissions and academic management.</p>
      <h3 style="font-size:18px;font-weight:700;margin:24px 0 10px">How We Use Your Information</h3>
      <p>Personal information is used to process admissions applications, manage student academic records, communicate with students and parents regarding school matters, process fee payments, and comply with applicable educational regulations in Liberia.</p>
      <h3 style="font-size:18px;font-weight:700;margin:24px 0 10px">Data Security</h3>
      <p>We implement appropriate technical and organisational measures to protect personal data. Access to student records is restricted to authorised school personnel based on their role and responsibilities.</p>
      <h3 style="font-size:18px;font-weight:700;margin:24px 0 10px">Document Storage</h3>
      <p>Uploaded documents such as birth certificates and academic records are stored securely and are only accessible to authorised admissions staff. Documents are not shared with third parties without consent.</p>
      <h3 style="font-size:18px;font-weight:700;margin:24px 0 10px">Your Rights</h3>
      <p>Parents and students have the right to access, correct or request deletion of their personal information held by the school, subject to applicable legal requirements. To exercise these rights, please contact us at the address below.</p>
      <h3 style="font-size:18px;font-weight:700;margin:24px 0 10px">Contact</h3>
      <p>For privacy-related enquiries, please contact:<br>
      <strong>Karn High School</strong><br>
      Karnplay, Nimba County, Liberia<br>
      <?= e(setting('school_phone','+231 886 417 711')) ?><br>
      <?= e(setting('school_email','info@karnhighschool.edu.lr')) ?></p>
    </div>
  </div>
</main>
<?php include __DIR__.'/includes/footer.php'; ?>
