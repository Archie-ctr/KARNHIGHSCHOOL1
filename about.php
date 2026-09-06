<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'About Us';
$activeNav = 'about';
$metaDesc  = 'Learn about Karn High School — our history, mission, vision, and the values that guide our community in Karnplay, Nimba, Liberia.';
$founded   = setting('school_founded','1985');
$ay        = currentAcademicYearName();
include __DIR__.'/includes/header.php';
?>

<!-- Page Hero -->
<section class="page-hero">
  <div class="wrap ph-body">
    <nav class="bc" aria-label="Breadcrumb"><a href="<?=BASE_URL?>/">Home</a><span class="bc-sep" aria-hidden="true">/</span><span>About</span></nav>
    <div class="eyebrow inv">Our Story</div>
    <h1 class="ph-h">A school rooted in purpose,<br><em>ready for the future.</em></h1>
    <p class="ph-lead">For nearly four decades, Karn High School has been the cornerstone of quality education in Karnplay, Nimba County — building knowledge, shaping character, and transforming lives.</p>
  </div>
</section>

<!-- Mission & Vision -->
<section class="sec bg-white">
  <div class="wrap">
    <div class="split">
      <div>
        <div class="eyebrow">Who We Are</div>
        <h2 class="h2">Education that goes<br><em>beyond the classroom.</em></h2>
        <div class="prose" style="margin-top:20px">
          <p>Karn High School has served families in Karnplay and across Nimba with a clear belief: every learner deserves a strong foundation, a safe environment, and the confidence to shape their future.</p>
          <p>Our community blends high expectations with genuine care. From ABC/KG through Grade 12, we pair thoughtful teaching with character formation, practical learning and a commitment to each student's potential.</p>
          <p>Founded in <?= e($founded) ?>, KHS has grown into one of Nimba County's most respected educational institutions, with <?= e(setting('stats_students','1,240+')) ?> students and <?= e(setting('stats_teachers','48')) ?> dedicated educators.</p>
        </div>
        <blockquote class="blockquote">
          "Building knowledge, character and a better future — together."
          <cite>Our School Promise</cite>
        </blockquote>
      </div>
      <div class="pillar-list" style="padding-top:8px">
        <?php foreach([
          ['🎯','Our Mission','var(--red-p)','var(--red)',   'To provide quality, inclusive education that equips every learner with the knowledge, skills and values to lead a productive and fulfilling life.'],
          ['👁','Our Vision', 'var(--grn-p)','var(--grn)',   'To be Nimba County\'s leading educational institution — a school where every student thrives and every family is proud to belong.'],
          ['⭐','Our Values', 'var(--gold-p)','var(--gold)', 'Integrity · Excellence · Community · Respect — four values that guide everything we do, in the classroom and beyond.'],
        ] as [$ico,$t,$bg,$col,$d]): ?>
        <div class="pillar">
          <div class="pillar-ico" style="background:<?=$bg?>;color:<?=$col?>" aria-hidden="true"><?=$ico?></div>
          <div><h3><?=$t?></h3><p><?=$d?></p></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- Facts -->
<section class="sec bg-warm">
  <div class="wrap">
    <div class="sec-hd tc">
      <div class="eyebrow">By the Numbers</div>
      <h2 class="h2">Karn High School<br><em>at a glance.</em></h2>
    </div>
    <div class="facts">
      <?php foreach([
        ['📅', setting('school_founded','1985'),    'Year Founded'],
        ['🎓', setting('stats_students','1,240+'),  'Students Enrolled'],
        ['👩‍🏫', setting('stats_teachers','48'),    'Teaching Staff'],
        ['📚', setting('stats_grades','14'),        'Grade Levels'],
        ['🏅', setting('stats_years','39'),         'Years of Service'],
        ['🌍', 'Nimba',                             'County, Liberia'],
      ] as [$ico,$v,$l]): ?>
      <div class="fact">
        <div class="fact-ico" aria-hidden="true"><?=$ico?></div>
        <span class="fact-n"><?=e($v)?></span>
        <span class="fact-l"><?=$l?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- What makes us different -->
<section class="sec bg-white">
  <div class="wrap">
    <div class="sec-hd tc">
      <div class="eyebrow">Our Approach</div>
      <h2 class="h2">What makes KHS<br><em>different.</em></h2>
    </div>
    <div class="cards c3">
      <?php foreach([
        ['🏫','Experienced Faculty',   'Our teachers bring passion and years of classroom experience. Many hold specialist qualifications in their subjects.'],
        ['📖','National Curriculum',   'We follow the Liberian national curriculum with enrichment programmes that challenge and inspire at every level.'],
        ['🔬','Practical Learning',    'Science labs, computer facilities and hands-on projects complement classroom instruction across all levels.'],
        ['🤝','Pastoral Care',        'A dedicated pastoral team ensures every student\'s wellbeing is supported, with guidance available to all.'],
        ['📊','Structured Assessment', 'Period tests, semester exams and published report cards keep students, parents and teachers aligned.'],
        ['🌱','Community Roots',       'We are proud to serve the people of Karnplay and Nimba. Community involvement is central to who we are.'],
      ] as [$i,$t,$d]): ?>
      <article class="card"><div class="card-ico" aria-hidden="true"><?=$i?></div><h3><?=$t?></h3><p><?=$d?></p></article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-band">
  <div class="wrap"><div class="cta-row">
    <div class="cta-copy">
      <div class="eyebrow">Join Our Community</div>
      <h2 class="cta-h">Become part of the<br><em>KHS family.</em></h2>
      <p>Applications for <?= e($ay) ?> are open. We welcome students who are ready to learn, grow and contribute.</p>
      <div class="cta-acts">
        <a href="<?=BASE_URL?>/apply.php"   class="btn btn-white btn-lg">Apply Now</a>
        <a href="<?=BASE_URL?>/contact.php" class="btn btn-ghost">Contact Us</a>
      </div>
    </div>
  </div></div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>
