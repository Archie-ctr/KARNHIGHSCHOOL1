<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'Facilities';
$activeNav = '';
$metaDesc  = 'Explore the world-class facilities at Karn High School — classrooms, science labs, library, computer lab, sports grounds and more.';

$facilities = [
  [
    'ico'  => '🏫',
    'name' => 'Modern Classrooms',
    'desc' => 'Well-maintained, spacious and ventilated classrooms designed for focused learning. Each room is equipped with appropriate furniture, ventilation and lighting to create an optimal learning environment for students from ABC/KG through Grade 12.',
    'tag'  => 'Learning',
  ],
  [
    'ico'  => '💻',
    'name' => 'Computer Laboratory',
    'desc' => 'A modern computer lab equipped with desktop computers, a reliable internet connection and software tools to support digital literacy and ICT education across all grade levels. Students gain hands-on experience with technology in a supervised environment.',
    'tag'  => 'Technology',
  ],
  [
    'ico'  => '📚',
    'name' => 'School Library',
    'desc' => 'A well-stocked library housing academic textbooks, reference materials, novels and reading resources for every grade level. Supervised study sessions and reading programmes encourage a lifelong love of learning beyond the classroom.',
    'tag'  => 'Learning',
  ],
  [
    'ico'  => '🔬',
    'name' => 'Science Laboratory',
    'desc' => 'A fully functional laboratory supporting biology, chemistry and physics practical sessions for senior students. Proper safety equipment, microscopes and experiment materials are maintained to ensure engaging, safe and effective science education.',
    'tag'  => 'Science',
  ],
  [
    'ico'  => '⚽',
    'name' => 'Sports Grounds',
    'desc' => 'Open sports fields and courts accommodating football, track and field athletics, volleyball and physical education activities. Regular inter-class and inter-school competitions are held to develop teamwork, leadership and physical fitness.',
    'tag'  => 'Sports',
  ],
  [
    'ico'  => '🍽️',
    'name' => 'School Canteen',
    'desc' => 'An on-campus canteen providing nutritious, affordable meals and snacks for students and staff throughout the school day. The canteen promotes healthy eating habits and ensures students are energised and ready to learn.',
    'tag'  => 'Welfare',
  ],
  [
    'ico'  => '🚻',
    'name' => 'Sanitation Facilities',
    'desc' => 'Clean, safe and gender-separated sanitation facilities maintained to high hygiene standards. Regular inspection and maintenance ensure all students and staff have access to proper sanitation throughout the school day.',
    'tag'  => 'Welfare',
  ],
  [
    'ico'  => '🏛️',
    'name' => 'Administrative Block',
    'desc' => 'A dedicated administration building housing the principal\'s office, vice principal, registrar, academic coordinator, bursar and admissions offices. The block is the operational hub of the school and provides a professional service environment for parents and visitors.',
    'tag'  => 'Administration',
  ],
];

include __DIR__.'/includes/header.php';
?>

<!-- Page Hero -->
<section class="page-hero">
  <div class="wrap ph-body">
    <nav class="bc" aria-label="Breadcrumb">
      <a href="<?= BASE_URL ?>/">Home</a>
      <span class="bc-sep">/</span>
      <span>Facilities</span>
    </nav>
    <div class="eyebrow inv">Our Campus</div>
    <h1 class="ph-h">Facilities built<br><em>for great learning.</em></h1>
    <p class="ph-lead">Karn High School provides a safe, well-resourced and stimulating environment where every student has the space, tools and support to grow academically, physically and socially.</p>
  </div>
</section>

<!-- Stats Banner -->
<section class="sec-sm bg-white" style="border-bottom:1px solid var(--bdr)">
  <div class="wrap">
    <div class="facts">
      <div class="fact">
        <div class="fact-ico">🏫</div>
        <span class="fact-n" data-target="24">24</span>
        <span class="fact-l">Classrooms</span>
      </div>
      <div class="fact">
        <div class="fact-ico">💻</div>
        <span class="fact-n" data-target="40">40</span>
        <span class="fact-l">Computer Stations</span>
      </div>
      <div class="fact">
        <div class="fact-ico">📚</div>
        <span class="fact-n" data-target="3000">3,000+</span>
        <span class="fact-l">Library Books</span>
      </div>
      <div class="fact">
        <div class="fact-ico">⚽</div>
        <span class="fact-n" data-target="4">4</span>
        <span class="fact-l">Sports Facilities</span>
      </div>
      <div class="fact">
        <div class="fact-ico">🔬</div>
        <span class="fact-n" data-target="2">2</span>
        <span class="fact-l">Science Labs</span>
      </div>
      <div class="fact">
        <div class="fact-ico">🌳</div>
        <span class="fact-n">5 acres</span>
        <span class="fact-l">Campus Grounds</span>
      </div>
    </div>
  </div>
</section>

<!-- Facilities Grid -->
<section class="sec bg-warm">
  <div class="wrap">
    <div class="sec-hd tc">
      <div class="eyebrow">What We Offer</div>
      <h2 class="h2" style="margin-top:var(--sp2)">Everything your child<br><em>needs to succeed.</em></h2>
      <p class="sec-desc">Our facilities are regularly maintained and upgraded to ensure students have access to a high-quality learning environment every day.</p>
    </div>

    <div class="cards fade-up" style="grid-template-columns:repeat(auto-fill,minmax(280px,1fr))">
      <?php foreach ($facilities as $f): ?>
      <article class="card" style="display:flex;flex-direction:column">
        <div style="display:flex;align-items:flex-start;gap:var(--sp4);margin-bottom:var(--sp4)">
          <div class="card-ico" style="font-size:1.6rem;width:52px;height:52px;flex-shrink:0;margin-bottom:0"><?= $f['ico'] ?></div>
          <div>
            <span class="badge badge-red" style="margin-bottom:6px"><?= $f['tag'] ?></span>
            <h3 style="font-size:1.03rem;font-weight:700;color:var(--ink)"><?= $f['name'] ?></h3>
          </div>
        </div>
        <p style="font-size:.88rem;line-height:1.76;color:var(--ink3)"><?= $f['desc'] ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Safety & Welfare highlight -->
<section class="sec bg-white">
  <div class="wrap">
    <div class="split">
      <div class="split-copy">
        <div class="eyebrow">Safe &amp; Supportive</div>
        <h2 class="h2" style="margin:var(--sp2) 0 var(--sp5)">A campus where<br><em>every student thrives.</em></h2>
        <p class="body">At KHS we believe a student's environment is just as important as the curriculum. Our campus is designed to be inclusive, safe and stimulating for learners at every stage.</p>
        <ul class="checklist" style="margin:var(--sp5) 0">
          <li>Safe, fenced campus with controlled entry points</li>
          <li>Clean, well-lit and ventilated learning spaces</li>
          <li>Disability-aware infrastructure improvements</li>
          <li>On-site school nurse and welfare support</li>
          <li>Regular maintenance and inspection of all facilities</li>
          <li>Separate sanitation facilities for boys and girls</li>
        </ul>
        <a href="<?= BASE_URL ?>/about.php" class="lnk">Learn more about KHS</a>
      </div>
      <div class="split-img">
        <div class="split-ph">
          <span class="ico">🏫</span>
          <p>KHS Campus</p>
        </div>
        <div class="split-badge">
          <strong>Est.</strong>
          <span>2005</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section class="cta-band">
  <div class="wrap">
    <div class="cta-row">
      <div class="cta-copy">
        <div class="eyebrow" style="color:rgba(255,255,255,.55)">Come See For Yourself</div>
        <h2 class="cta-h">Visit Karn High School<br><em>in person.</em></h2>
        <p>We welcome prospective students and families to visit our campus and see our facilities first-hand. Book a visit or apply online today.</p>
        <div class="cta-acts">
          <a href="<?= BASE_URL ?>/apply.php"   class="btn btn-white btn-lg">Apply Now</a>
          <a href="<?= BASE_URL ?>/contact.php" class="btn btn-ghost btn-lg">Book a Visit</a>
        </div>
      </div>
      <div class="cta-aside">
        <div class="ico">📍</div>
        <strong>Find Us</strong>
        <p>Karnplay, Nimba County, Liberia.<br>Open Monday–Friday, 8 AM – 4:30 PM.</p>
        <a href="<?= BASE_URL ?>/contact.php" class="lnk" style="color:#a8dfba;margin-top:var(--sp3)">Get directions</a>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>
