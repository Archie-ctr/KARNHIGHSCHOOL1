<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'Academics';
$activeNav = 'academics';
$metaDesc  = 'Learn about the academic programmes at Karn High School — from ABC/KG through Grade 12 in Karnplay, Nimba, Liberia.';
include __DIR__.'/includes/header.php';
?>

<section class="page-hero">
  <div class="wrap page-hero-inner">
    <nav class="breadcrumb" aria-label="Breadcrumb"><a href="<?=BASE_URL?>/">Home</a><span>/</span><span>Academics</span></nav>
    <div class="section-tag">Learning at KHS</div>
    <h1>A complete academic journey<br><em>from ABC/KG to Grade 12.</em></h1>
    <p>Our curriculum is structured, rigorous and aligned to national standards — preparing every student for WASSCE, higher education and a productive life.</p>
  </div>
</section>

<section class="section bg-white">
  <div class="wrap">
    <div class="text-center" style="max-width:580px;margin:0 auto var(--space-12)">
      <div class="section-tag">Academic Pathways</div>
      <h2 class="section-title">Three levels of<br><em>learning excellence.</em></h2>
    </div>
    <div class="cards-grid">
      <?php
      $levels=[
        ['🌱','Early Childhood & Primary','ABC/KG — Grade 6','Strong foundations in literacy, numeracy, discovery and joyful learning. Every child deserves a confident start.','var(--c-green-light)','var(--c-green)'],
        ['📗','Junior High School',        'Grades 7 — 9',    'Developing critical thinkers and confident learners. Core subjects deepen, study skills build, and personal discipline grows.','var(--c-red-light)','var(--c-red)'],
        ['🎓','Senior High School',        'Grades 10 — 12',  'Rigorous academic preparation for WASSCE, university admission and career readiness. Science, arts and technical streams available.','#fef9ec','var(--c-gold)'],
      ];
      foreach ($levels as [$icon,$title,$range,$desc,$bg,$color]):
      ?>
      <article class="card" style="--card-accent:<?=$color?>">
        <div class="card-icon" style="background:<?=$bg?>;color:<?=$color?>"><?=$icon?></div>
        <div class="badge" style="background:<?=$bg?>;color:<?=$color?>;margin-bottom:var(--space-3)"><?=$range?></div>
        <h3><?=$title?></h3>
        <p><?=$desc?></p>
        <a href="<?=BASE_URL?>/admissions.php" class="btn-link" style="margin-top:var(--space-4);color:<?=$color?>">Explore →</a>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section bg-soft">
  <div class="wrap">
    <div class="text-center" style="max-width:580px;margin:0 auto var(--space-10)">
      <div class="section-tag">Our Approach</div>
      <h2 class="section-title">How we teach<br><em>at KHS.</em></h2>
    </div>
    <div class="cards-grid">
      <?php $approach=[
        ['📚','National Curriculum','We follow the Liberian national curriculum, ensuring students meet all requirements for national examinations and progression.'],
        ['👥','Small Class Sizes','Smaller classes allow teachers to know every student personally and provide targeted support where it matters most.'],
        ['📊','Structured Assessment','Period tests, semester exams and published report cards keep students, parents and teachers aligned throughout the year.'],
        ['🔬','Practical Learning','Science labs, computer facilities and hands-on projects complement classroom instruction across all levels.'],
        ['🏅','Extra-Curricular','Sports, debate, arts and clubs help students discover talents and develop leadership skills beyond the textbook.'],
        ['📱','Digital Literacy','Students are introduced to computers and digital tools from early grades, building essential 21st-century skills.'],
      ];
      foreach ($approach as [$i,$t,$d]):
      ?><article class="card"><div class="card-icon"><?=$i?></div><h3><?=$t?></h3><p><?=$d?></p></article><?php endforeach; ?>
    </div>
  </div>
</section>

<section class="cta-band">
  <div class="wrap"><div class="cta-inner">
    <div class="cta-text">
      <span class="overline">Join Us</span>
      <h2>Ready to start your<br><em>academic journey?</em></h2>
      <p>Apply today and take the first step toward an excellent education at Karn High School.</p>
      <div class="cta-actions">
        <a href="<?=BASE_URL?>/apply.php" class="btn btn-white btn-lg">Apply Now →</a>
        <a href="<?=BASE_URL?>/programs.php" class="btn btn-secondary">View All Subjects</a>
      </div>
    </div>
  </div></div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>
