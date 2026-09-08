<?php
// ============================================================
// Applicant Portal — Shared Sidebar Navigation
// Expects $activePage to be set before inclusion.
// ============================================================
if (!isset($activePage)) $activePage = '';

$nav = [
    'dashboard'    => ['🏠', 'Dashboard',          BASE_URL.'/portal/applicant/'],
    'application'  => ['📋', 'My Application',     BASE_URL.'/portal/applicant/application.php'],
    'documents'    => ['📎', 'Upload Documents',   BASE_URL.'/portal/applicant/documents.php'],
    'exam'         => ['📝', 'Entrance Exam',      BASE_URL.'/portal/applicant/exam.php'],
    'decision'     => ['📨', 'Admission Decision', BASE_URL.'/portal/applicant/decision.php'],
];
?>
<aside class="portal-sidebar">
  <div class="portal-brand">
    <div class="brand">
      <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="KHS"/>
      <span><strong>KHS</strong><small>Applicant Portal</small></span>
    </div>
  </div>

  <nav class="portal-nav" aria-label="Applicant portal navigation">
    <?php foreach ($nav as $key => [$icon, $label, $url]): ?>
    <a href="<?= e($url) ?>" <?= $activePage === $key ? 'class="active" aria-current="page"' : '' ?>>
      <?= $icon ?> <?= $label ?>
    </a>
    <?php endforeach; ?>
  </nav>

  <div style="padding:14px 16px;border-top:1px solid rgba(255,255,255,.08)">
    <p style="font-size:11px;color:rgba(255,255,255,.35);line-height:1.6;margin-bottom:10px">
      Questions about your application? Contact our admissions office.
    </p>
    <a href="<?= BASE_URL ?>/contact.php" target="_blank"
       style="display:block;font-size:12px;color:rgba(255,255,255,.5);margin-bottom:8px">✉️ Contact Admissions</a>
    <a href="<?= BASE_URL ?>/admin/logout.php"
       style="color:var(--error);font-size:13px;font-weight:600">⬡ Sign Out</a>
  </div>
</aside>
