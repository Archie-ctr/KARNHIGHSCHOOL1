<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'Academic Programs';
$activeNav = 'programs';
$subjects  = db()->query("SELECT * FROM subjects WHERE is_active=1 ORDER BY category,name")->fetchAll();
$byCategory = [];
foreach ($subjects as $s) $byCategory[$s['category']][] = $s;
$categoryLabels = ['core'=>'Core Subjects','elective'=>'Elective Subjects','extracurricular'=>'Extracurricular'];
include __DIR__.'/includes/header.php';
?>
<main class="inner-page">
  <div class="container inner-hero">
    <div class="eyebrow">Academic Programs <span></span></div>
    <h1>Our subjects &amp;<br><em>academic pathways.</em></h1>
    <p>Karn High School offers a comprehensive, nationally aligned curriculum from ABC/KG through Grade 12.</p>
  </div>

  <div class="container content-narrow" style="padding-bottom:80px">
    <!-- Level pathways -->
    <div class="feature-grid" style="margin-bottom:56px">
      <article class="feature-card">
        <span class="feature-num">01</span>
        <h3>Early Childhood &amp; Primary</h3>
        <p>ABC/KG — Grade 6. Strong foundations in literacy, numeracy, science and discovery through joyful, age-appropriate learning.</p>
      </article>
      <article class="feature-card">
        <span class="feature-num">02</span>
        <h3>Junior High School</h3>
        <p>Grades 7–9. Developing critical thinkers and confident learners. Emphasis on English, Mathematics, Science and Social Studies.</p>
      </article>
      <article class="feature-card">
        <span class="feature-num">03</span>
        <h3>Senior High School</h3>
        <p>Grades 10–12. Rigorous academic preparation for WASSCE, university admission and career readiness.</p>
      </article>
    </div>

    <!-- Subjects by category -->
    <?php foreach ($byCategory as $cat => $subs): ?>
    <div style="margin-bottom:36px">
      <div class="eyebrow" style="margin-bottom:16px"><?= e($categoryLabels[$cat] ?? ucfirst($cat)) ?> <span></span></div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px">
        <?php foreach ($subs as $sub): ?>
        <div style="background:var(--surface);border:1px solid var(--line);border-radius:var(--radius-sm);padding:14px 16px;display:flex;align-items:center;gap:10px">
          <span style="width:32px;height:32px;border-radius:8px;background:var(--primary-soft);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0"><?= e($sub['short_name'] ?? substr($sub['name'],0,3)) ?></span>
          <span style="font-size:13.5px;font-weight:600"><?= e($sub['name']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <div class="apply-cta">
      <h2>Ready to join our academic community?</h2>
      <a href="<?= BASE_URL ?>/apply.php" class="button button-primary">Apply for Admission →</a>
    </div>
  </div>
</main>
<?php include __DIR__.'/includes/footer.php'; ?>
