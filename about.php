<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'About Us';
$activeNav = 'about';
$metaDesc  = 'Learn about Karn High School — our history, mission, vision, and the values that guide our community in Karnplay, Nimba, Liberia.';
include __DIR__.'/includes/header.php';
?>

<!-- Page Hero -->
<section class="page-hero">
  <div class="wrap page-hero-inner">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="<?= BASE_URL ?>/">Home</a><span>/</span><span>About</span>
    </nav>
    <div class="section-tag">Our Story</div>
    <h1>A school rooted in purpose,<br><em>ready for the future.</em></h1>
    <p>For nearly four decades, Karn High School has been the cornerstone of quality education in Karnplay, Nimba County — building knowledge, shaping character, and transforming lives.</p>
  </div>
</section>

<!-- Mission & Vision -->
<section class="section bg-white">
  <div class="wrap">
    <div class="content-grid-2">
      <div>
        <div class="section-tag">Who We Are</div>
        <h2 class="section-title">Education that <em>goes beyond</em> the classroom.</h2>
        <div class="prose">
          <p>Karn High School has served families in Karnplay and across Nimba with a clear belief: every learner deserves a strong foundation, a safe environment, and the confidence to shape their future.</p>
          <p>Our community blends high expectations with genuine care. From ABC/KG through Grade 12, we pair thoughtful teaching with character formation, practical learning, and a commitment to each student's potential.</p>
          <p>Founded in <?= e(setting('school_founded','1985')) ?>, KHS has grown into one of Nimba County's most respected educational institutions, with over <?= e(setting('stats_students','1,240')) ?> students and <?= e(setting('stats_teachers','48')) ?> dedicated educators.</p>
        </div>
        <blockquote class="blockquote">
          "Building knowledge, character and a better future — together."
          <cite>Our School Promise</cite>
        </blockquote>
      </div>
      <div style="display:flex;flex-direction:column;gap:var(--space-4);padding-top:var(--space-5)">
        <?php
        $pillars = [
          ['🎯', 'Our Mission',  'var(--c-red-light)',   'var(--c-red)',   'To provide quality, inclusive education that equips every learner with the knowledge, skills and values to lead a productive and fulfilling life.'],
          ['👁', 'Our Vision',   'var(--c-green-light)', 'var(--c-green)', 'To be Nimba County\'s leading educational institution — a school where every student thrives and every family is proud to belong.'],
          ['⭐', 'Our Values',   '#fef9ec',              'var(--c-gold)',  'Integrity · Excellence · Community · Respect — these four values guide everything we do, in the classroom and beyond.'],
        ];
        foreach ($pillars as [$icon, $title, $bg, $color, $text]):
        ?>
        <div style="display:flex;gap:var(--space-4);padding:var(--space-5);background:<?= $bg ?>;border-radius:var(--r-lg);align-items:flex-start">
          <div style="width:44px;height:44px;border-radius:12px;background:<?= $color ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0"><?= $icon ?></div>
          <div>
            <h3 style="font-size:1rem;font-weight:700;color:var(--c-ink);margin-bottom:4px"><?= $title ?></h3>
            <p style="font-size:0.88rem;color:var(--c-ink-3);line-height:1.65;margin:0"><?= $text ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- Key facts -->
<section class="section bg-soft">
  <div class="wrap">
    <div class="text-center" style="max-width:600px;margin:0 auto var(--space-10)">
      <div class="section-tag">By the Numbers</div>
      <h2 class="section-title">Karn High School<br><em>at a glance.</em></h2>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:var(--space-5)">
      <?php
      $facts=[
        ['📅',setting('school_founded','1985'),    'Year Founded'],
        ['🎓',setting('stats_students','1,240+'),  'Students Enrolled'],
        ['👩‍🏫',setting('stats_teachers','48'),    'Teaching Staff'],
        ['📚',setting('stats_grades','14'),        'Grade Levels'],
        ['🏅',setting('stats_years','39'),         'Years of Service'],
        ['🌍','Nimba',                             'County, Liberia'],
      ];
      foreach ($facts as [$icon,$val,$label]):
      ?>
      <div style="background:var(--c-surface);border:1px solid var(--c-border);border-radius:var(--r-lg);padding:var(--space-6);text-align:center">
        <div style="font-size:2rem;margin-bottom:var(--space-2)"><?= $icon ?></div>
        <strong style="display:block;font-size:1.8rem;font-weight:800;color:var(--c-red);letter-spacing:-.02em;line-height:1"><?= e($val) ?></strong>
        <span style="font-size:0.82rem;color:var(--c-ink-3);font-weight:500"><?= $label ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Feature cards -->
<section class="section bg-white">
  <div class="wrap">
    <div class="text-center" style="max-width:580px;margin:0 auto var(--space-10)">
      <div class="section-tag">Our Approach</div>
      <h2 class="section-title">What makes KHS<br><em>different.</em></h2>
    </div>
    <div class="cards-grid">
      <?php
      $features = [
        ['🏫','Experienced Faculty',     'Our teachers bring a passion for learning and years of classroom experience. Many hold specialist qualifications in their subject areas.'],
        ['📖','Strong Curriculum',       'We follow the Liberian national curriculum, with enrichment programmes that challenge and inspire students at every level.'],
        ['🔬','Science & Lab Work',      'Hands-on laboratory sessions in biology, chemistry and physics build practical skills alongside theoretical knowledge.'],
        ['🤝','Pastoral Care',           'A dedicated pastoral team ensures every student\'s wellbeing is supported, with counseling and guidance available to all.'],
        ['📊','Regular Assessment',      'Structured period tests, semester examinations and published report cards keep students, parents and teachers aligned.'],
        ['🌱','Community Roots',         'We are proud to serve the people of Karnplay and Nimba. Community involvement is central to who we are and how we operate.'],
      ];
      foreach ($features as [$icon,$title,$text]):
      ?>
      <article class="card">
        <div class="card-icon"><?= $icon ?></div>
        <h3><?= $title ?></h3>
        <p><?= $text ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-band">
  <div class="wrap">
    <div class="cta-inner">
      <div class="cta-text">
        <span class="overline">Join Our Community</span>
        <h2>Become part of the<br><em>KHS family.</em></h2>
        <p>Applications for the <?= e(currentAcademicYearName()) ?> academic year are open. We welcome students who are ready to learn, grow and contribute.</p>
        <div class="cta-actions">
          <a href="<?= BASE_URL ?>/apply.php"   class="btn btn-white btn-lg">Apply Now →</a>
          <a href="<?= BASE_URL ?>/contact.php" class="btn btn-secondary">Contact Us</a>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>
