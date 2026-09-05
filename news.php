<?php
require_once __DIR__ . '/config/db.php';
$pageTitle = 'News & Events';
$activeNav = 'news';
include __DIR__ . '/includes/header.php';

$articles = [
  [
    'tag'  => 'School life',
    'date' => 'Sep 02, 2026',
    'title'=> 'Welcome back, KHS community',
    'text' => 'A new academic year begins with fresh energy, new goals and the same unwavering commitment to excellence. We are delighted to welcome all returning and new students.',
    'color'=> 'var(--primary-soft)',
  ],
  [
    'tag'  => 'Admissions',
    'date' => 'Aug 18, 2026',
    'title'=> '2026/2027 applications are now open',
    'text' => 'Take the first step toward a brighter future. Our online application portal is now open for the 2026/2027 academic year. Apply early to secure your place.',
    'color'=> 'var(--accent-soft)',
  ],
  [
    'tag'  => 'Community',
    'date' => 'Jul 28, 2026',
    'title'=> 'KHS celebrates another year of impact',
    'text' => 'We are proud of the students, families and educators who make our community exceptional. This year\'s graduation ceremony was a proud moment for all of Karnplay.',
    'color'=> 'var(--gold-soft)',
  ],
  [
    'tag'  => 'Academics',
    'date' => 'Jul 10, 2026',
    'title'=> 'WASSCE results: another year of distinction',
    'text' => 'Our Grade 12 students achieved outstanding results in the West African Senior School Certificate Examinations. We congratulate every student on their hard work.',
    'color'=> 'var(--blue-soft)',
  ],
  [
    'tag'  => 'School life',
    'date' => 'Jun 20, 2026',
    'title'=> 'End-of-year prize giving ceremony',
    'text' => 'Students across all grades were recognised for academic excellence, leadership and community service at our annual prize giving event.',
    'color'=> 'var(--primary-soft)',
  ],
  [
    'tag'  => 'Community',
    'date' => 'May 15, 2026',
    'title'=> 'Inter-school sports day recap',
    'text' => 'KHS athletes competed brilliantly at the Nimba County inter-school sports day, bringing home medals in athletics, football and debate.',
    'color'=> 'var(--green-soft)',
  ],
];
?>

<main class="inner-page">
  <div class="container inner-hero">
    <div class="eyebrow">News &amp; events <span></span></div>
    <h1>What's happening<br><em>at KHS.</em></h1>
  </div>

  <div class="container" style="padding-bottom:80px">
    <div class="news-grid">
      <?php foreach ($articles as $a): ?>
      <article class="news-card">
        <div class="news-image" style="background:linear-gradient(135deg,<?= $a['color'] ?>,var(--bg-soft))">
          <span class="tag"><?= e($a['tag']) ?></span>
          <span style="font-size:40px;opacity:.15">&#128197;</span>
        </div>
        <small><?= e($a['date']) ?></small>
        <h3><?= e($a['title']) ?></h3>
        <p><?= e($a['text']) ?></p>
        <span class="text-link" style="padding:0 24px 24px;display:flex">Read story &rarr;</span>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
