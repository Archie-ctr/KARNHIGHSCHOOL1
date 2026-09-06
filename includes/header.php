<?php
if (!isset($pageTitle)) $pageTitle = '';
if (!isset($activeNav)) $activeNav = '';
if (!isset($metaDesc))  $metaDesc  = 'Karn High School — '.setting('school_tagline','Building Knowledge, Character and a Better Future').' | Karnplay, Nimba, Liberia';

$siteName  = setting('school_name',  'KARN HIGH SCHOOL');
$phone     = setting('school_phone', '+231 886 417 711');
$ayName    = currentAcademicYearName();
$fullTitle = $pageTitle ? e($pageTitle).' — '.$siteName : $siteName;

$navItems = [
  'about'      => ['About',      BASE_URL.'/about.php'],
  'academics'  => ['Academics',  BASE_URL.'/academics.php'],
  'programs'   => ['Programs',   BASE_URL.'/programs.php'],
  'admissions' => ['Admissions', BASE_URL.'/admissions.php'],
  'news'       => ['News',       BASE_URL.'/news.php'],
  'events'     => ['Events',     BASE_URL.'/events.php'],
  'contact'    => ['Contact',    BASE_URL.'/contact.php'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0,viewport-fit=cover"/>
  <meta name="description" content="<?= e($metaDesc) ?>"/>
  <meta property="og:title"       content="<?= $fullTitle ?>"/>
  <meta property="og:description" content="<?= e($metaDesc) ?>"/>
  <meta property="og:type"        content="website"/>
  <meta name="theme-color"        content="#861530"/>
  <title><?= $fullTitle ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,500&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/public.css"/>
</head>
<body>

<a href="#main" class="skip-link">Skip to content</a>

<!-- ── Topbar ─────────────────────────────────────────────── -->
<div class="topbar" role="banner">
  <div class="topbar-row">
    <span>📞 <?= e($phone) ?></span>
    <span class="t-sep" aria-hidden="true"></span>
    <span>Admissions open — <?= e($ayName) ?></span>
    <div class="topbar-right">
      <span><?= e(setting('office_hours','Mon–Fri 8am–4pm')) ?></span>
      <a href="<?= BASE_URL ?>/login.php">Staff Login →</a>
    </div>
  </div>
</div>

<!-- ── Nav ───────────────────────────────────────────────── -->
<header class="site-header" id="site-header">
  <nav class="nav-row" aria-label="Main navigation">
    <a href="<?= BASE_URL ?>/" class="nav-brand" aria-label="<?= e($siteName) ?> — Home">
      <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="" class="nav-logo" width="42" height="42" loading="eager"/>
      <div>
        <span class="nav-name"><?= e($siteName) ?></span>
        <span class="nav-place">Karnplay, Nimba, Liberia</span>
      </div>
    </a>

    <ul class="nav-links" id="navMenu" role="list">
      <?php foreach ($navItems as $key => [$label, $href]): ?>
      <li><a href="<?= $href ?>" <?= $activeNav === $key ? 'class="on" aria-current="page"' : '' ?>><?= $label ?></a></li>
      <?php endforeach; ?>
      <li><a href="<?= BASE_URL ?>/apply.php" class="nav-cta">Apply Now</a></li>
    </ul>

    <button class="nav-btn" id="navBtn" aria-label="Open menu" aria-expanded="false" aria-controls="navMenu">
      <span aria-hidden="true"></span>
      <span aria-hidden="true"></span>
      <span aria-hidden="true"></span>
    </button>
  </nav>
</header>

<main id="main">
