<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'Academic Programs';
$activeNav = 'academics';
$metaDesc  = 'Explore the academic programmes offered at Karn High School — from early childhood through Senior High School.';

try {
    $subjects = db()->query(
        "SELECT * FROM subjects WHERE is_active=1 ORDER BY category, name"
    )->fetchAll();
} catch (Throwable $e) { $subjects = []; }

$byCategory = [];
foreach ($subjects as $s) {
    $byCategory[$s['category'] ?? 'general'][] = $s;
}
$catMeta = [
    'core'          => ['label'=>'Core Subjects',          'ico'=>'📖', 'desc'=>'Foundational subjects required for all students across every grade level.'],
    'elective'      => ['label'=>'Elective Subjects',       'ico'=>'🎨', 'desc'=>'Specialised courses students choose based on their interests and career goals.'],
    'extracurricular'=> ['label'=>'Extracurricular',        'ico'=>'⚽', 'desc'=>'Co-curricular activities that build leadership, teamwork and creative skills.'],
    'general'       => ['label'=>'General Subjects',        'ico'=>'📚', 'desc'=>'Broad-based courses complementing the core curriculum.'],
];

include __DIR__.'/includes/header.php';
?>

<!-- Page Hero -->
<section class="page-hero">
  <div class="wrap ph-body">
    <nav class="bc" aria-label="Breadcrumb">
      <a href="<?= BASE_URL ?>/">Home</a>
      <span class="bc-sep">/</span>
      <span>Programs</span>
    </nav>
    <div class="eyebrow inv">Academic Programs</div>
    <h1 class="ph-h">Curriculum &amp;<br><em>academic pathways.</em></h1>
    <p class="ph-lead">Karn High School delivers a rigorous, nationally aligned curriculum from ABC/KG through Grade 12 — preparing students for higher education, professional life and responsible citizenship.</p>
    <div class="ph-btns">
      <a href="<?= BASE_URL ?>/apply.php" class="btn btn-white btn-lg">Apply Now</a>
      <a href="<?= BASE_URL ?>/admissions.php" class="btn btn-ghost btn-lg">Admissions Info</a>
    </div>
  </div>
</section>

<!-- Level Pathways -->
<section class="sec bg-white">
  <div class="wrap">
    <div class="sec-hd tc">
      <div class="eyebrow">Learning Levels</div>
      <h2 class="h2" style="margin-top:var(--sp2)">Three pathways,<br><em>one strong foundation.</em></h2>
      <p class="sec-desc">Our structured programme takes students from their earliest years through to senior high school graduation with confidence and capability.</p>
    </div>
    <div class="cards c3 fade-up">

      <article class="card" style="border-top:4px solid var(--red)">
        <div class="card-ico" style="font-size:1.8rem;width:56px;height:56px">🏫</div>
        <h3>Early Childhood &amp; Primary</h3>
        <p style="margin-bottom:var(--sp4)">ABC/KG &ndash; Grade 6. Strong foundations in literacy, numeracy, science and discovery through joyful, age-appropriate learning environments that build curiosity and confidence.</p>
        <ul style="display:flex;flex-direction:column;gap:6px;margin-top:auto">
          <?php foreach(['English Language','Mathematics','Science','Social Studies','Liberian Studies'] as $sub): ?>
          <li style="font-size:.82rem;color:var(--ink3);display:flex;align-items:center;gap:6px">
            <span style="width:6px;height:6px;border-radius:50%;background:var(--red);flex-shrink:0"></span>
            <?= $sub ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </article>

      <article class="card" style="border-top:4px solid var(--grn)">
        <div class="card-ico" style="background:var(--grn-p);color:var(--grn);font-size:1.8rem;width:56px;height:56px">📐</div>
        <h3>Junior High School</h3>
        <p style="margin-bottom:var(--sp4)">Grades 7&ndash;9. Developing critical thinkers and confident learners. Students encounter a broader range of subjects that prepare them for senior high specialisation.</p>
        <ul style="display:flex;flex-direction:column;gap:6px;margin-top:auto">
          <?php foreach(['English & Literature','Mathematics','Integrated Science','Social Studies','Computer Studies','Physical Education'] as $sub): ?>
          <li style="font-size:.82rem;color:var(--ink3);display:flex;align-items:center;gap:6px">
            <span style="width:6px;height:6px;border-radius:50%;background:var(--grn);flex-shrink:0"></span>
            <?= $sub ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </article>

      <article class="card" style="border-top:4px solid var(--gold)">
        <div class="card-ico" style="background:var(--gold-p);color:var(--gold);font-size:1.8rem;width:56px;height:56px">🎓</div>
        <h3>Senior High School</h3>
        <p style="margin-bottom:var(--sp4)">Grades 10&ndash;12. Rigorous preparation for the WAEC / WASSCE examinations and university entrance. Students choose elective streams aligned to their future ambitions.</p>
        <ul style="display:flex;flex-direction:column;gap:6px;margin-top:auto">
          <?php foreach(['English Language','Mathematics','Biology / Chemistry / Physics','Economics & Commerce','Geography / History','Elective options'] as $sub): ?>
          <li style="font-size:.82rem;color:var(--ink3);display:flex;align-items:center;gap:6px">
            <span style="width:6px;height:6px;border-radius:50%;background:var(--gold);flex-shrink:0"></span>
            <?= $sub ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </article>

    </div>
  </div>
</section>

<!-- Subject Listing from DB -->
<?php if (!empty($byCategory)): ?>
<section class="sec bg-warm">
  <div class="wrap">
    <div class="sec-hd tc">
      <div class="eyebrow">Subject Catalogue</div>
      <h2 class="h2" style="margin-top:var(--sp2)">Subjects we <em>offer.</em></h2>
      <p class="sec-desc">All subjects offered at Karn High School, grouped by category.</p>
    </div>

    <?php foreach ($byCategory as $cat => $subs):
      $meta = $catMeta[$cat] ?? ['label'=>ucfirst($cat),'ico'=>'📚','desc'=>''];
    ?>
    <div style="margin-bottom:var(--sp12)">
      <div style="display:flex;align-items:center;gap:var(--sp3);margin-bottom:var(--sp5)">
        <span style="font-size:1.5rem"><?= $meta['ico'] ?></span>
        <div>
          <h3 class="h4"><?= $meta['label'] ?></h3>
          <?php if($meta['desc']): ?><p class="caption"><?= $meta['desc'] ?></p><?php endif; ?>
        </div>
      </div>
      <div class="cards fade-up" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr))">
        <?php foreach($subs as $sub): ?>
        <div class="card" style="padding:var(--sp5)">
          <h3 style="font-size:.95rem;margin-bottom:4px"><?= e($sub['name']) ?></h3>
          <?php if(!empty($sub['code'])): ?>
          <span class="badge badge-grey" style="margin-bottom:var(--sp2)"><?= e($sub['code']) ?></span>
          <?php endif; ?>
          <?php if(!empty($sub['description'])): ?>
          <p style="font-size:.83rem;margin-top:4px"><?= e(mb_substr($sub['description'],0,90)).(mb_strlen($sub['description'])>90?'…':'') ?></p>
          <?php endif; ?>
          <?php if(!empty($sub['grade_level'])): ?>
          <p style="font-size:.76rem;color:var(--ink4);margin-top:6px">Grade <?= e($sub['grade_level']) ?></p>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<!-- Teaching Approach -->
<section class="sec bg-white">
  <div class="wrap">
    <div class="sec-hd tc">
      <div class="eyebrow">Our Approach</div>
      <h2 class="h2" style="margin-top:var(--sp2)">How we <em>teach.</em></h2>
    </div>
    <div class="cards c3 fade-up">
      <article class="card">
        <div class="card-ico">🔬</div>
        <h3>Inquiry-Based Learning</h3>
        <p>Students are encouraged to ask questions, investigate problems and construct their own understanding through hands-on, practical learning experiences.</p>
      </article>
      <article class="card">
        <div class="card-ico">💡</div>
        <h3>Critical Thinking</h3>
        <p>Across all subjects we embed analysis, evaluation and creative problem-solving skills that prepare students for the challenges of higher education and work.</p>
      </article>
      <article class="card">
        <div class="card-ico">🤝</div>
        <h3>Collaborative Study</h3>
        <p>Group projects, peer learning and community-focused assignments build communication skills, empathy and the ability to work effectively with others.</p>
      </article>
      <article class="card">
        <div class="card-ico">📊</div>
        <h3>Continuous Assessment</h3>
        <p>Regular formative assessments, mid-term exams and end-of-term tests ensure every student's progress is tracked, supported and celebrated.</p>
      </article>
      <article class="card">
        <div class="card-ico">🌍</div>
        <h3>Liberian Context</h3>
        <p>Our curriculum is grounded in the Liberian national framework, honouring local history, culture and values while preparing students for a global world.</p>
      </article>
      <article class="card">
        <div class="card-ico">🏆</div>
        <h3>Exam Readiness</h3>
        <p>Targeted WAEC/WASSCE preparation, past papers and revision programmes ensure senior students approach national examinations with confidence.</p>
      </article>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-band">
  <div class="wrap">
    <div class="cta-row">
      <div class="cta-copy">
        <div class="eyebrow" style="color:rgba(255,255,255,.55)">Start Your Journey</div>
        <h2 class="cta-h">Ready to enrol at<br><em>Karn High School?</em></h2>
        <p>Submit your application online today. Our admissions team will guide you through every step of the process.</p>
        <div class="cta-acts">
          <a href="<?= BASE_URL ?>/apply.php"      class="btn btn-white btn-lg">Apply Now</a>
          <a href="<?= BASE_URL ?>/admissions.php" class="btn btn-ghost btn-lg">Admission Process</a>
        </div>
      </div>
      <div class="cta-aside">
        <div class="ico">📞</div>
        <strong>Speak to an Admissions Officer</strong>
        <p>Call or visit us at Karnplay, Nimba County. We're happy to answer any academic questions you may have.</p>
        <a href="<?= BASE_URL ?>/contact.php" class="lnk" style="color:#a8dfba;margin-top:var(--sp3)">Get directions</a>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>
