<?php
require_once __DIR__.'/config/db.php';
$pageTitle = '';
$activeNav = 'home';

// Live stats
try {
    $statStudents = number_format((int)db()->query("SELECT COUNT(*) FROM students WHERE status='Active'")->fetchColumn()).'+';
    $statTeachers = (string)(int)db()->query("SELECT COUNT(*) FROM teachers WHERE status='Active'")->fetchColumn();
    $statGrades   = (string)(int)db()->query("SELECT COUNT(*) FROM grades WHERE is_active=1")->fetchColumn();
} catch (Throwable $e) {
    $statStudents = setting('stats_students','1,240+');
    $statTeachers = setting('stats_teachers','48');
    $statGrades   = setting('stats_grades','14');
}
$statYears = setting('stats_years','39');

// Live content
try {
    $announcements = db()->query("SELECT title,message,published_at FROM announcements WHERE is_public=1 AND (expires_at IS NULL OR expires_at>NOW()) ORDER BY published_at DESC LIMIT 3")->fetchAll();
} catch (Throwable $e) { $announcements=[]; }
try {
    $events = db()->query("SELECT title,event_date,category FROM events WHERE is_public=1 AND event_date>=CURDATE() ORDER BY event_date ASC LIMIT 4")->fetchAll();
} catch (Throwable $e) { $events=[]; }

$headline = setting('hero_headline','Building Knowledge, Character and a Better Future.');
$subtext  = setting('hero_subtext', 'Where curiosity is nurtured, potential is discovered, and every student is prepared to make a meaningful difference.');
$welcome  = setting('welcome_message','At Karn High School, education goes beyond the classroom. We are a vibrant community where every learner is known, supported and inspired to reach higher.');
$founded  = setting('school_founded','1985');
$ay       = currentAcademicYearName();

include __DIR__.'/includes/header.php';
?>

<!-- ══════════════════════════════════════════════════════
     HERO
     ══════════════════════════════════════════════════════ -->
<section class="hero" aria-label="Welcome to Karn High School">
  <div class="wrap hero-grid">

    <!-- Left: Copy -->
    <div>
      <div class="hero-tag">🇱🇷 Karnplay, Nimba · Est. <?= e($founded) ?></div>
      <h1 class="hero-title"><?= nl2br(e($headline)) ?></h1>
      <p class="hero-desc"><?= e($subtext) ?></p>
      <div class="hero-actions">
        <a href="<?= BASE_URL ?>/apply.php"        class="btn btn-primary btn-lg">Apply for Admission</a>
        <a href="<?= BASE_URL ?>/admissions.php"   class="btn btn-secondary">Learn More</a>
        <a href="<?= BASE_URL ?>/login.php?tab=student" class="btn btn-secondary" style="border-color:rgba(255,255,255,.25)">Student Portal →</a>
      </div>
      <div class="hero-trust">
        <span class="hero-trust-icon">✓</span>
        <span>A trusted learning community serving Karnplay, Nimba County since <?= e($founded) ?></span>
      </div>
    </div>

    <!-- Right: Visual card -->
    <div class="hero-visual">
      <div class="hero-orbit orbit-1" aria-hidden="true"></div>
      <div class="hero-orbit orbit-2" aria-hidden="true"></div>
      <div class="hero-card">
        <div class="hero-card-brand">
          <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="KHS logo" width="40" height="40"/>
          <span>KARN HIGH SCHOOL</span>
        </div>
        <h2>Learning today.<br><span>Leading tomorrow.</span></h2>
        <div class="hero-card-lines" aria-hidden="true">
          <span></span><span></span><span></span>
        </div>
        <p>Academic excellence · Character formation · Community</p>
      </div>
      <div class="hero-badge" aria-label="<?= e($statYears) ?> years of excellence">
        <div class="hero-badge-icon">🎓</div>
        <div>
          <strong><?= e($statYears) ?> Years</strong>
          <small>of Excellence</small>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════
     STATS BAR
     ══════════════════════════════════════════════════════ -->
<div class="stats-bar" aria-label="School statistics">
  <div class="wrap">
    <div class="stats-bar-inner">
      <div class="stat-item">
        <strong data-count="<?= preg_replace('/\D/','',$statStudents) ?>"><?= e($statStudents) ?></strong>
        <span>Students Enrolled</span>
      </div>
      <div class="stat-item">
        <strong data-count="<?= e($statTeachers) ?>"><?= e($statTeachers) ?></strong>
        <span>Experienced Educators</span>
      </div>
      <div class="stat-item">
        <strong data-count="<?= e($statGrades) ?>"><?= e($statGrades) ?></strong>
        <span>Academic Levels</span>
      </div>
      <div class="stat-item">
        <strong data-count="<?= e($statYears) ?>"><?= e($statYears) ?></strong>
        <span>Years of Excellence</span>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     WELCOME / ABOUT
     ══════════════════════════════════════════════════════ -->
<section class="section bg-white" aria-labelledby="welcome-heading">
  <div class="wrap">
    <div class="welcome-grid">

      <!-- Image placeholder (replace with real photo) -->
      <div class="welcome-image-wrap">
        <div class="welcome-placeholder">
          <span>🏫</span>
          <p>Karn High School — Karnplay, Nimba</p>
        </div>
        <div class="welcome-badge-wrap">
          <strong><?= e($founded) ?></strong>
          <span>Est.</span>
        </div>
      </div>

      <!-- Content -->
      <div class="welcome-content">
        <div class="section-tag">Welcome to KHS</div>
        <h2 class="section-title" id="welcome-heading">
          A place to <em>belong</em>,<br>learn and grow.
        </h2>
        <p class="body-lg"><?= e($welcome) ?></p>
        <ul class="welcome-points" aria-label="Key values">
          <li>Quality education from ABC/KG through Grade 12</li>
          <li>Dedicated and experienced teaching staff</li>
          <li>Safe, inclusive and supportive learning environment</li>
          <li>Strong focus on character, discipline and community</li>
        </ul>
        <a href="<?= BASE_URL ?>/about.php" class="btn btn-outline">Discover Our Story →</a>
      </div>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════
     WHY KHS
     ══════════════════════════════════════════════════════ -->
<section class="section bg-soft" aria-labelledby="why-khs">
  <div class="wrap">
    <div class="text-center" style="max-width:640px;margin:0 auto var(--space-12)">
      <div class="section-tag">The KHS Difference</div>
      <h2 class="section-title" id="why-khs">More than a school.<br><em>A foundation for life.</em></h2>
      <p class="section-desc">We combine rigorous academics with strong values to prepare every student for success in Liberia and beyond.</p>
    </div>

    <div class="cards-grid" role="list">
      <?php
      $values = [
        ['✨', 'Purposeful Learning',     'A relevant, engaging curriculum aligned to national standards that prepares students for WASSCE, higher education and the world of work.'],
        ['👥', 'Known & Supported',       'Our caring school culture ensures every student is seen, known and supported — academically and personally.'],
        ['📈', 'Results-Focused',         'Consistent academic performance driven by experienced teachers, structured assessments and an active approval workflow.'],
        ['🎯', 'Character Formation',     'We develop responsible, ethical citizens through discipline, community service and strong moral education.'],
        ['🔬', 'Science & Technology',    'Well-equipped laboratories and computer facilities prepare students for the demands of a modern, knowledge-based economy.'],
        ['🤝', 'Community Partnership',   'Strong ties with parents, guardians and the Karnplay community make KHS a school where everyone belongs.'],
      ];
      foreach ($values as [$icon, $title, $text]): ?>
      <article class="card" role="listitem">
        <div class="card-icon" aria-hidden="true"><?= $icon ?></div>
        <h3><?= $title ?></h3>
        <p><?= $text ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════
     ADMISSIONS PROCESS
     ══════════════════════════════════════════════════════ -->
<section class="section bg-white" aria-labelledby="admissions-process">
  <div class="wrap">
    <div style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:24px;margin-bottom:var(--space-10)">
      <div>
        <div class="section-tag">Admissions</div>
        <h2 class="section-title" id="admissions-process">How to join<br><em>Karn High School.</em></h2>
      </div>
      <a href="<?= BASE_URL ?>/apply.php" class="btn btn-primary">Start Application →</a>
    </div>

    <div class="process-grid" role="list">
      <?php
      $steps = [
        ['Apply Online',       'Complete the multi-step online application form. Takes under 10 minutes. You\'ll receive your application number immediately.'],
        ['Document Review',    'Our registrar verifies your submitted documents — report card, birth certificate, and supporting materials.'],
        ['Entrance Examination','Approved applicants receive an Entrance Eligibility Letter and sit the KHS entrance examination.'],
        ['Final Admission',    'Successful candidates receive their official admission letter, student ID and class assignment.'],
      ];
      foreach ($steps as $i => [$title, $desc]): ?>
      <article class="process-item" role="listitem">
        <div class="process-num" aria-label="Step <?= $i+1 ?>"><?= $i+1 ?></div>
        <h3><?= $title ?></h3>
        <p><?= $desc ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════════════════
     ANNOUNCEMENTS & EVENTS (live from DB)
     ══════════════════════════════════════════════════════ -->
<?php if (!empty($announcements) || !empty($events)): ?>
<section class="section bg-soft" aria-label="Latest news and events">
  <div class="wrap">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-12);align-items:start">

      <?php if (!empty($announcements)): ?>
      <!-- Announcements -->
      <div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-6);flex-wrap:wrap;gap:12px">
          <div>
            <div class="section-tag">Latest News</div>
            <h2 class="section-title" style="font-size:clamp(1.4rem,2.5vw,2rem);margin-bottom:0">Announcements</h2>
          </div>
          <a href="<?= BASE_URL ?>/news.php" class="btn-link">View all →</a>
        </div>
        <div class="news-grid" style="grid-template-columns:1fr">
          <?php foreach ($announcements as $ann): ?>
          <article class="news-card">
            <div class="news-thumb">
              <span class="news-tag">📢 School Notice</span>
              <span style="font-size:2rem;opacity:.2">📋</span>
            </div>
            <div class="news-body">
              <div class="news-date"><?= date('F d, Y', strtotime($ann['published_at'])) ?></div>
              <h3><?= e($ann['title']) ?></h3>
              <p><?= e(mb_substr($ann['message'], 0, 110)).(mb_strlen($ann['message'])>110?'…':'') ?></p>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($events)): ?>
      <!-- Upcoming events -->
      <div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:var(--space-6);flex-wrap:wrap;gap:12px">
          <div>
            <div class="section-tag">School Calendar</div>
            <h2 class="section-title" style="font-size:clamp(1.4rem,2.5vw,2rem);margin-bottom:0">Upcoming Events</h2>
          </div>
          <a href="<?= BASE_URL ?>/events.php" class="btn-link">All events →</a>
        </div>
        <div style="display:flex;flex-direction:column;gap:var(--space-3)">
          <?php foreach ($events as $ev): ?>
          <article class="event-item">
            <div class="event-date-box" aria-hidden="true">
              <strong><?= date('d', strtotime($ev['event_date'])) ?></strong>
              <span><?= date('M', strtotime($ev['event_date'])) ?></span>
            </div>
            <div class="event-info">
              <h4><?= e($ev['title']) ?></h4>
              <p><?= ucfirst(e($ev['category'])) ?> &nbsp;·&nbsp; <?= date('Y', strtotime($ev['event_date'])) ?></p>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</section>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════
     CTA BAND
     ══════════════════════════════════════════════════════ -->
<section class="cta-band" aria-label="Apply for admission">
  <div class="wrap">
    <div class="cta-inner">
      <div class="cta-text">
        <span class="overline">Join our community</span>
        <h2>Ready to begin your<br><em>KHS journey?</em></h2>
        <p>Applications for the <?= e($ay) ?> academic year are open. Take the first step toward a brighter future for your child.</p>
        <div class="cta-actions">
          <a href="<?= BASE_URL ?>/apply.php"               class="btn btn-white btn-lg">Apply Online Now →</a>
          <a href="<?= BASE_URL ?>/application-status.php"  class="btn btn-secondary">Track Application</a>
        </div>
      </div>
      <!-- Decorative card -->
      <div style="background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.15);border-radius:18px;padding:28px 32px;min-width:260px;max-width:300px;flex-shrink:0">
        <div style="font-size:2.2rem;margin-bottom:12px">📋</div>
        <p style="color:rgba(255,255,255,.85);font-size:.92rem;line-height:1.7;font-weight:500">
          <strong style="color:#fff;display:block;margin-bottom:6px">Quick Application Facts</strong>
          ✓ Takes under 10 minutes<br>
          ✓ No application fee<br>
          ✓ Instant application number<br>
          ✓ Track status online<br>
          ✓ Documents uploadable later
        </p>
      </div>
    </div>
  </div>
</section>

<!-- Application modal (triggered by Apply buttons) -->
<div id="applicationModal" class="modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="modalTitle" style="position:fixed;inset:0;background:rgba(0,0,0,.55);backdrop-filter:blur(4px);z-index:200;display:flex;align-items:center;justify-content:center;padding:20px;overflow-y:auto">
  <div style="background:#fff;border-radius:18px;width:100%;max-width:600px;max-height:90vh;overflow-y:auto;box-shadow:0 24px 64px rgba(0,0,0,.2)">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:24px 28px 0">
      <div>
        <div class="section-tag">Admissions Portal</div>
        <h2 id="modalTitle" style="font-size:1.4rem;font-weight:800;margin-top:6px">Start Your Application</h2>
      </div>
      <button data-close-modal aria-label="Close" style="font-size:1.4rem;padding:6px;border:none;background:none;cursor:pointer;color:#71717a">&times;</button>
    </div>
    <div class="stepper" style="display:flex;padding:20px 28px;border-bottom:1px solid #e8e2da">
      <div class="stepper-step active"><span class="step-circle">1</span><span>Applicant</span></div>
      <div class="stepper-step"><span class="step-circle">2</span><span>Guardian</span></div>
      <div class="stepper-step"><span class="step-circle">3</span><span>Education</span></div>
      <div class="stepper-step"><span class="step-circle">4</span><span>Grade</span></div>
      <div class="stepper-step"><span class="step-circle">5</span><span>Docs</span></div>
      <div class="stepper-step"><span class="step-circle">6</span><span>Review</span></div>
      <div class="stepper-step"><span class="step-circle">7</span><span>Submit</span></div>
    </div>
    <div class="modal-body" id="modalBody" style="padding:24px 28px"></div>
    <div class="modal-actions" id="modalActions" style="display:flex;justify-content:flex-end;gap:10px;padding:0 28px 28px"></div>
  </div>
</div>

<style>
.modal-backdrop.hidden{display:none!important}
.stepper{overflow-x:auto}
.stepper-step{flex:1;display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#a1a1aa;white-space:nowrap;min-width:60px}
.stepper-step::after{content:'';flex:1;height:2px;background:#e8e2da;margin-left:6px}
.stepper-step:last-child::after{display:none}
.step-circle{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:#f4f4f5;color:#a1a1aa;font-size:11px;font-weight:700;flex-shrink:0}
.stepper-step.active{color:var(--c-red,#b01e3c)}
.stepper-step.active .step-circle{background:var(--c-red,#b01e3c);color:#fff}
.stepper-step.done .step-circle{background:#1a5c3a;color:#fff}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-grid label{display:flex;flex-direction:column;gap:5px;font-size:12.5px;font-weight:600;color:#3f3f46}
.form-grid label.wide{grid-column:1/-1}
.form-grid input,.form-grid select,.form-grid textarea{padding:10px 13px;border:1.5px solid #e8e2da;border-radius:8px;font-size:14px;background:#f9f8f6;transition:border-color .15s}
.form-grid input:focus,.form-grid select:focus{outline:none;border-color:var(--c-red,#b01e3c);background:#fff}
@media(max-width:500px){.form-grid{grid-template-columns:1fr}.form-grid label.wide{grid-column:1}}
</style>

<?php include __DIR__.'/includes/footer.php'; ?>
