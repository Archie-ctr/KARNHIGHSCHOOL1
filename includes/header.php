<?php
if (!isset($pageTitle))  $pageTitle = '';
if (!isset($activeNav))  $activeNav = '';
if (!isset($metaDesc))   $metaDesc  = 'Karn High School — '.setting('school_tagline','Building Knowledge, Character and a Better Future').' | Karnplay, Nimba, Liberia';

$siteName = setting('school_name',  'KARN HIGH SCHOOL');
$phone    = setting('school_phone', '+231 886 417 711');
$ayName   = currentAcademicYearName();
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
  <meta name="theme-color"        content="#8a162d"/>
  <title><?= $fullTitle ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,700;0,800;1,600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/public.css"/>
</head>
<body>

<!-- Skip to content (accessibility) -->
<a href="#main-content" style="position:absolute;top:-100px;left:0;padding:8px 16px;background:var(--c-red);color:#fff;font-weight:700;z-index:9999;border-radius:0 0 8px 0;transition:top .2s" onfocus="this.style.top='0'" onblur="this.style.top='-100px'">Skip to content</a>

<!-- Topbar -->
<div class="topbar" role="banner">
  <div class="topbar-inner">
    <span>📞 <?= e($phone) ?></span>
    <span class="topbar-div" aria-hidden="true"></span>
    <span>Admissions open — <?= e($ayName) ?></span>
    <div class="topbar-right">
      <span><?= e(setting('office_hours','Mon–Fri, 8:00am–4:00pm')) ?></span>
      <a href="<?= BASE_URL ?>/login.php">Portal Login →</a>
    </div>
  </div>
</div>

<!-- Main navigation -->
<header class="site-header" role="navigation">
  <div class="nav-container">
    <!-- Brand -->
    <a href="<?= BASE_URL ?>/" class="nav-brand" aria-label="<?= e($siteName) ?> — Home">
      <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="" class="nav-brand-logo" width="44" height="44"/>
      <div class="nav-brand-text">
        <strong><?= e($siteName) ?></strong>
        <small>Karnplay, Nimba, Liberia</small>
      </div>
    </a>

    <!-- Desktop + Mobile nav links -->
    <nav id="mainNav" class="nav-links" aria-label="Main navigation">
      <?php foreach ($navItems as $key => [$label, $href]): ?>
        <a href="<?= $href ?>" <?= $activeNav === $key ? 'class="nav-active" aria-current="page"' : '' ?>>
          <?= $label ?>
        </a>
      <?php endforeach; ?>
      <a href="<?= BASE_URL ?>/apply.php" class="nav-apply">Apply Now</a>
    </nav>

    <!-- Hamburger (mobile) -->
    <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation" aria-expanded="false" aria-controls="mainNav">
      <span aria-hidden="true"></span>
      <span aria-hidden="true"></span>
      <span aria-hidden="true"></span>
    </button>
  </div>
</header>

<main id="main-content">
