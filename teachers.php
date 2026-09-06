<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'Our Teachers';
$activeNav = 'about';
$metaDesc  = 'Meet the dedicated teaching staff at Karn High School — experienced educators committed to excellence.';

try {
    $teachers = db()->query(
        "SELECT u.first_name, u.last_name, t.specialization, t.qualification, t.photo, t.bio,
                t.department, t.years_experience
         FROM teachers t
         JOIN users u ON u.id = t.user_id
         WHERE t.status = 'Active'
         ORDER BY t.department, u.last_name"
    )->fetchAll();
} catch (Throwable $e) {
    // Fallback: try simpler query
    try {
        $teachers = db()->query(
            "SELECT first_name, last_name, specialization, qualification, photo
             FROM teachers WHERE status='Active' ORDER BY first_name"
        )->fetchAll();
    } catch (Throwable $e2) { $teachers = []; }
}

// Group by department if available
$byDept = [];
foreach ($teachers as $t) {
    $d = $t['department'] ?? 'Teaching Staff';
    $byDept[$d][] = $t;
}

include __DIR__.'/includes/header.php';
?>

<!-- Page Hero -->
<section class="page-hero">
  <div class="wrap ph-body">
    <nav class="bc" aria-label="Breadcrumb">
      <a href="<?= BASE_URL ?>/">Home</a>
      <span class="bc-sep">/</span>
      <a href="<?= BASE_URL ?>/about.php">About</a>
      <span class="bc-sep">/</span>
      <span>Our Teachers</span>
    </nav>
    <div class="eyebrow inv">Our Educators</div>
    <h1 class="ph-h">Meet the team<br><em>behind our success.</em></h1>
    <p class="ph-lead">Our dedicated, qualified educators bring expertise, passion and genuine care to every classroom. At KHS, teachers are mentors, guides and champions for every student.</p>
  </div>
</section>

<!-- Why Our Teachers -->
<section class="sec-sm bg-white" style="border-bottom:1px solid var(--bdr)">
  <div class="wrap">
    <div class="cards c3 fade-up">
      <article class="card" style="padding:var(--sp5);text-align:center">
        <div class="card-ico" style="margin:0 auto var(--sp3)">🏆</div>
        <h3>Qualified &amp; Experienced</h3>
        <p>All KHS teachers hold recognised academic qualifications and undergo regular professional development to stay current with best practices in education.</p>
      </article>
      <article class="card" style="padding:var(--sp5);text-align:center">
        <div class="card-ico" style="margin:0 auto var(--sp3)">❤️</div>
        <h3>Student-Centred</h3>
        <p>Our educators know every student by name. They invest time in understanding each learner's strengths, challenges and aspirations beyond the classroom.</p>
      </article>
      <article class="card" style="padding:var(--sp5);text-align:center">
        <div class="card-ico" style="margin:0 auto var(--sp3)">🌍</div>
        <h3>Community-Connected</h3>
        <p>KHS teachers are members of the Karnplay community. They bring local knowledge and a deep commitment to the families and students they serve every day.</p>
      </article>
    </div>
  </div>
</section>

<!-- Teacher Profiles -->
<section class="sec bg-warm">
  <div class="wrap">
    <?php if (empty($teachers)): ?>
    <div class="tc" style="padding:var(--sp16) 0">
      <div style="width:80px;height:80px;background:var(--red-p);border-radius:var(--r4);display:flex;align-items:center;justify-content:center;font-size:2.4rem;margin:0 auto var(--sp6)">👩‍🏫</div>
      <h2 class="h3">Staff profiles coming soon</h2>
      <p class="body" style="margin-top:var(--sp3)">We're building our staff directory. Check back soon to meet our teaching team.</p>
      <a href="<?= BASE_URL ?>/contact.php" class="btn btn-outline" style="margin-top:var(--sp5)">Contact Us</a>
    </div>

    <?php else: ?>

    <?php foreach ($byDept as $deptName => $staff): ?>
    <?php if (count($byDept) > 1): ?>
    <div class="sec-hd" style="margin-bottom:var(--sp8)">
      <div class="eyebrow"><?= e($deptName) ?></div>
    </div>
    <?php else: ?>
    <div class="sec-hd tc" style="margin-bottom:var(--sp8)">
      <div class="eyebrow">Our Staff</div>
      <h2 class="h2" style="margin-top:var(--sp2)">The people who<br><em>make it happen.</em></h2>
    </div>
    <?php endif; ?>

    <div class="teacher-grid fade-up" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:var(--sp5);margin-bottom:var(--sp12)">
      <?php foreach ($staff as $t):
        $fn  = isset($t['first_name']) ? $t['first_name'] : '';
        $ln  = isset($t['last_name'])  ? $t['last_name']  : '';
        $initials = strtoupper(substr($fn,0,1).substr($ln,0,1));
        $name = trim($fn.' '.$ln);
        $colorPairs = [
          ['#ac1f3b','#fff0f3'],['#1b5e3b','#eaf6ef'],
          ['#b8850e','#fdf5e3'],['#1a4fbf','#eff6ff'],
        ];
        $pair = $colorPairs[abs(crc32($name)) % count($colorPairs)];
      ?>
      <article class="teacher-card" style="background:#fff;border:1px solid var(--bdr);border-radius:var(--r3);overflow:hidden;transition:all var(--t2) var(--ease);display:flex;flex-direction:column">
        <!-- Avatar -->
        <div style="height:160px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,<?= $pair[1] ?> 0%,var(--bg2) 100%);position:relative">
          <?php if (!empty($t['photo']) && file_exists(__DIR__.'/uploads/'.$t['photo'])): ?>
          <img src="<?= BASE_URL ?>/uploads/<?= e($t['photo']) ?>" alt="<?= e($name) ?>"
               style="width:90px;height:90px;border-radius:50%;object-fit:cover;border:3px solid #fff;box-shadow:var(--s2)" />
          <?php else: ?>
          <div style="width:80px;height:80px;border-radius:50%;background:<?= $pair[0] ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.65rem;font-weight:800;font-family:var(--serif);border:3px solid #fff;box-shadow:var(--s2)">
            <?= $initials ?>
          </div>
          <?php endif; ?>
        </div>
        <!-- Info -->
        <div style="padding:var(--sp5);flex:1;display:flex;flex-direction:column;gap:4px;text-align:center">
          <h3 style="font-size:1rem;font-weight:700;color:var(--ink)"><?= e($name) ?></h3>
          <?php if (!empty($t['specialization'])): ?>
          <p style="font-size:.82rem;color:var(--red);font-weight:600"><?= e($t['specialization']) ?></p>
          <?php endif; ?>
          <?php if (!empty($t['qualification'])): ?>
          <p style="font-size:.78rem;color:var(--ink3)"><?= e($t['qualification']) ?></p>
          <?php endif; ?>
          <?php if (!empty($t['years_experience'])): ?>
          <p style="font-size:.76rem;color:var(--ink4);margin-top:4px"><?= (int)$t['years_experience'] ?> years experience</p>
          <?php endif; ?>
          <?php if (!empty($t['bio'])): ?>
          <p style="font-size:.82rem;color:var(--ink3);line-height:1.68;margin-top:var(--sp3);text-align:left;border-top:1px solid var(--bdr);padding-top:var(--sp3)"><?= e(mb_substr($t['bio'],0,120)).'…' ?></p>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <?php endif; ?>
  </div>
</section>

<!-- Join the Team -->
<section class="sec bg-white">
  <div class="wrap">
    <div class="split">
      <div class="split-copy">
        <div class="eyebrow">Join Our Team</div>
        <h2 class="h2" style="margin:var(--sp2) 0 var(--sp5)">Passionate about<br><em>education?</em></h2>
        <p class="body">We are always looking for dedicated, qualified educators who share our commitment to student success and community development. If you are passionate about teaching and want to make a real difference in Nimba County, we want to hear from you.</p>
        <ul class="checklist" style="margin:var(--sp5) 0">
          <li>Competitive salary and professional development opportunities</li>
          <li>Supportive, collaborative school culture</li>
          <li>Modern facilities and teaching resources</li>
          <li>A community that values education</li>
        </ul>
        <a href="<?= BASE_URL ?>/contact.php" class="btn btn-primary">Send Your CV</a>
      </div>
      <div class="split-img">
        <div class="split-ph">
          <span class="ico">👩‍🏫</span>
          <p>Join Our Faculty</p>
        </div>
        <div class="split-badge">
          <strong><?= count($teachers) ?: '30+' ?></strong>
          <span>Educators</span>
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
        <div class="eyebrow" style="color:rgba(255,255,255,.55)">World-Class Teaching</div>
        <h2 class="cta-h">Give your child the best<br><em>possible start.</em></h2>
        <p>Enrol your child at Karn High School and place them in the hands of our dedicated, experienced teaching team.</p>
        <div class="cta-acts">
          <a href="<?= BASE_URL ?>/apply.php"   class="btn btn-white btn-lg">Apply Now</a>
          <a href="<?= BASE_URL ?>/about.php"   class="btn btn-ghost btn-lg">About KHS</a>
        </div>
      </div>
      <div class="cta-aside">
        <div class="ico">📩</div>
        <strong>Teaching Vacancies</strong>
        <p>Interested in joining our team? Send your CV and cover letter to our administration office.</p>
        <a href="<?= BASE_URL ?>/contact.php" class="lnk" style="color:#a8dfba;margin-top:var(--sp3)">Contact us</a>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>
