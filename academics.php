<?php
require_once __DIR__.'/config/db.php';
$pageTitle='Academics'; $activeNav='academics';
$metaDesc='Learn about the academic programmes at Karn High School — from ABC/KG through Grade 12.';
$ay=currentAcademicYearName();
include __DIR__.'/includes/header.php';
?>

<section class="page-hero">
  <div class="wrap ph-body">
    <nav class="bc" aria-label="Breadcrumb"><a href="<?=BASE_URL?>/">Home</a><span class="bc-sep">/</span><span>Academics</span></nav>
    <div class="eyebrow inv">Learning at KHS</div>
    <h1 class="ph-h">A complete academic journey<br><em>from ABC/KG to Grade 12.</em></h1>
    <p class="ph-lead">Our curriculum is structured, rigorous and aligned to national standards — preparing every student for WASSCE, higher education and a productive life.</p>
  </div>
</section>

<section class="sec bg-white">
  <div class="wrap">
    <div class="sec-hd tc"><div class="eyebrow">Academic Pathways</div><h2 class="h2">Three levels of<br><em>learning excellence.</em></h2></div>
    <div class="cards c3">
      <?php foreach([
        ['🌱','Early Childhood &amp; Primary','ABC/KG — Grade 6','Strong foundations in literacy, numeracy, discovery and joyful learning. Every child deserves a confident start.','var(--grn-p)','var(--grn)'],
        ['📗','Junior High School',           'Grades 7 — 9',    'Developing critical thinkers and confident learners. Core subjects deepen, study skills build, and personal discipline grows.','var(--red-p)','var(--red)'],
        ['🎓','Senior High School',           'Grades 10 — 12',  'Rigorous preparation for WASSCE, university admission and career readiness. Science, arts and technical streams available.','var(--gold-p)','var(--gold)'],
      ] as [$ico,$t,$range,$d,$bg,$col]): ?>
      <article class="card">
        <div class="card-ico" style="background:<?=$bg?>;color:<?=$col?>" aria-hidden="true"><?=$ico?></div>
        <span class="badge" style="background:<?=$bg?>;color:<?=$col?>;margin-bottom:12px"><?=$range?></span>
        <h3><?=$t?></h3>
        <p><?=$d?></p>
        <a href="<?=BASE_URL?>/programs.php" class="lnk" style="margin-top:16px;color:<?=$col?>">View subjects</a>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec bg-warm">
  <div class="wrap">
    <div class="sec-hd tc"><div class="eyebrow">Our Approach</div><h2 class="h2">How we teach<br><em>at KHS.</em></h2></div>
    <div class="cards">
      <?php foreach([
        ['📚','National Curriculum','We follow the Liberian national curriculum, ensuring students meet all requirements for national examinations and progression.'],
        ['👥','Small Class Sizes','Smaller classes allow teachers to know every student personally and provide targeted support where it matters most.'],
        ['📊','Structured Assessment','Period tests, semester exams and published report cards keep students, parents and teachers aligned throughout the year.'],
        ['🔬','Practical Learning','Science labs, computer facilities and hands-on projects complement classroom instruction across all levels.'],
        ['🏅','Extra-Curricular','Sports, debate, arts and clubs help students discover talents and develop leadership skills beyond the textbook.'],
        ['📱','Digital Literacy','Students are introduced to computers and digital tools from early grades, building essential 21st-century skills.'],
      ] as [$i,$t,$d]): ?>
      <article class="card"><div class="card-ico" aria-hidden="true"><?=$i?></div><h3><?=$t?></h3><p><?=$d?></p></article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="cta-band">
  <div class="wrap"><div class="cta-row">
    <div class="cta-copy">
      <div class="eyebrow">Join Us</div>
      <h2 class="cta-h">Ready to start your<br><em>academic journey?</em></h2>
      <p>Apply today and take the first step toward an excellent education at Karn High School.</p>
      <div class="cta-acts">
        <a href="<?=BASE_URL?>/apply.php"   class="btn btn-white btn-lg">Apply Now →</a>
        <a href="<?=BASE_URL?>/programs.php" class="btn btn-ghost">View All Subjects</a>
      </div>
    </div>
  </div></div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>
