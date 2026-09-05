<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'Facilities';
$activeNav = '';
include __DIR__.'/includes/header.php';
$facilities = [
  ['🏫', 'Classrooms',        'Well-maintained, ventilated classrooms designed for focused learning from ABC/KG through Grade 12.'],
  ['💻', 'Computer Lab',      'A modern computer laboratory equipped with computers and internet access to support digital literacy.'],
  ['📚', 'School Library',    'A well-stocked library with academic texts, reference materials and reading resources for all grade levels.'],
  ['⚽', 'Sports Grounds',    'Open sports fields and courts supporting football, athletics and physical education programmes.'],
  ['🔬', 'Science Laboratory','A functional science lab supporting biology, chemistry and physics practical sessions for senior students.'],
  ['🍽️', 'School Canteen',   'An on-campus canteen providing nutritious meals and snacks for students and staff.'],
  ['🚻', 'Sanitation Facilities','Clean and safe sanitation facilities maintained to high hygiene standards for all students and staff.'],
  ['🏛️', 'Administrative Block','A dedicated administration building housing the principal, registrar, academic dean and bursar offices.'],
];
?>
<main class="inner-page">
  <div class="container inner-hero">
    <div class="eyebrow">School Facilities <span></span></div>
    <h1>Our learning<br><em>environment.</em></h1>
    <p>Karn High School provides a safe, well-resourced environment where students can thrive academically and personally.</p>
  </div>
  <div class="container" style="padding-bottom:80px">
    <div class="value-grid" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr))">
      <?php foreach ($facilities as [$icon,$name,$desc]): ?>
      <article class="value-card">
        <div class="value-icon"><?= $icon ?></div>
        <h3><?= $name ?></h3>
        <p><?= $desc ?></p>
      </article>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:48px" class="apply-cta">
      <h2>Experience KHS for yourself.</h2>
      <p style="color:var(--ink-soft);margin-bottom:20px">Apply for admission and join our growing community of learners.</p>
      <a href="<?= BASE_URL ?>/apply.php" class="button button-primary">Apply for Admission →</a>
    </div>
  </div>
</main>
<?php include __DIR__.'/includes/footer.php'; ?>
