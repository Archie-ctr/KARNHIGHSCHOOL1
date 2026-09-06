<?php
require_once __DIR__.'/config/db.php';
$pageTitle = '';
$activeNav = 'home';

try{
  $cnt_s=(int)db()->query("SELECT COUNT(*) FROM students WHERE status='Active'")->fetchColumn();
  $cnt_t=(int)db()->query("SELECT COUNT(*) FROM teachers WHERE status='Active'")->fetchColumn();
  $cnt_g=(int)db()->query("SELECT COUNT(*) FROM grades WHERE is_active=1")->fetchColumn();
}catch(Throwable $e){$cnt_s=$cnt_t=$cnt_g=0;}
$stat_s = $cnt_s>0 ? number_format($cnt_s).'+' : setting('stats_students','1,240+');
$stat_t = $cnt_t>0 ? (string)$cnt_t            : setting('stats_teachers','48');
$stat_g = $cnt_g>0 ? (string)$cnt_g            : setting('stats_grades','14');
$stat_y = setting('stats_years','39');

try{ $anns=db()->query("SELECT title,message,published_at FROM announcements WHERE is_public=1 AND (expires_at IS NULL OR expires_at>NOW()) ORDER BY published_at DESC LIMIT 3")->fetchAll(); }catch(Throwable $e){$anns=[];}
try{ $evts=db()->query("SELECT title,event_date,category FROM events WHERE is_public=1 AND event_date>=CURDATE() ORDER BY event_date ASC LIMIT 4")->fetchAll(); }catch(Throwable $e){$evts=[];}

$headline = setting('hero_headline','Building Knowledge, Character and a Better Future.');
$subtext  = setting('hero_subtext', 'Where curiosity is nurtured, potential is discovered, and every student is prepared to make a meaningful difference.');
$welcome  = setting('welcome_message','At Karn High School, education goes beyond the classroom. We are a vibrant community where every learner is known, supported and inspired to reach higher.');
$founded  = setting('school_founded','1985');
$ay       = currentAcademicYearName();

include __DIR__.'/includes/header.php';
?>

<!-- ════ HERO ═════════════════════════════════════════════ -->
<section class="hero" aria-label="Welcome">
  <div class="wrap hero-grid">

    <div class="hero-copy">
      <div class="hero-pill"><span class="hero-pill-dot"></span>🇱🇷 Karnplay, Nimba · Est. <?= e($founded) ?></div>
      <h1 class="hero-h1"><?= nl2br(e($headline)) ?></h1>
      <p class="hero-sub"><?= e($subtext) ?></p>
      <div class="hero-btns">
        <a href="<?= BASE_URL ?>/apply.php"             class="btn btn-primary btn-lg">Apply for Admission</a>
        <a href="<?= BASE_URL ?>/admissions.php"        class="btn btn-ghost">Learn More</a>
        <a href="<?= BASE_URL ?>/login.php?tab=student" class="btn btn-ghost">Student Portal →</a>
      </div>
      <p class="hero-trust"><span class="hero-ok" aria-hidden="true">✓</span> A trusted learning community serving Karnplay, Nimba County since <?= e($founded) ?></p>
    </div>

    <div class="hero-vis">
      <div class="hero-orb orb-1" aria-hidden="true"></div>
      <div class="hero-orb orb-2" aria-hidden="true"></div>
      <div class="hero-card">
        <div class="hero-card-top">
          <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="KHS logo" width="38" height="38" loading="eager"/>
          <span>KARN HIGH SCHOOL</span>
        </div>
        <h2 class="hero-card-h">Learning today.<br><span>Leading tomorrow.</span></h2>
        <div class="hero-bars" aria-hidden="true"><i></i><i></i><i></i></div>
        <p>Academic excellence · Character · Community</p>
      </div>
      <div class="hero-badge" aria-label="<?= e($stat_y) ?> years of excellence">
        <div class="hero-badge-ico" aria-hidden="true">🎓</div>
        <div><strong><?= e($stat_y) ?> Years</strong><small>of Excellence</small></div>
      </div>
    </div>
  </div>
</section>

<!-- ════ STATS ════════════════════════════════════════════ -->
<div class="stats-bar" aria-label="School statistics">
  <div class="wrap">
    <div class="stats-row">
      <div class="stat"><span class="stat-n"><?= e($stat_s) ?></span><span class="stat-l">Students Enrolled</span></div>
      <div class="stat"><span class="stat-n"><?= e($stat_t) ?></span><span class="stat-l">Experienced Educators</span></div>
      <div class="stat"><span class="stat-n"><?= e($stat_g) ?></span><span class="stat-l">Academic Levels</span></div>
      <div class="stat"><span class="stat-n"><?= e($stat_y) ?></span><span class="stat-l">Years of Excellence</span></div>
    </div>
  </div>
</div>

<!-- ════ WELCOME ══════════════════════════════════════════ -->
<section class="sec bg-white" aria-labelledby="welcome-h">
  <div class="wrap">
    <div class="split">
      <div class="split-img">
        <div class="split-ph"><span class="ico">🏫</span><p>Karn High School — Karnplay, Nimba</p></div>
        <div class="split-badge"><strong><?= e($founded) ?></strong><span>Est.</span></div>
      </div>
      <div class="split-copy">
        <div class="eyebrow">Welcome to KHS</div>
        <h2 class="h2" id="welcome-h">A place to <em>belong</em>,<br>learn and grow.</h2>
        <p class="body"><?= e($welcome) ?></p>
        <ul class="checklist" aria-label="Key values">
          <li>Quality education from ABC/KG through Grade 12</li>
          <li>Dedicated, experienced and caring teaching staff</li>
          <li>Safe, inclusive and structured learning environment</li>
          <li>Strong emphasis on character, discipline and community</li>
        </ul>
        <a href="<?= BASE_URL ?>/about.php" class="btn btn-outline">Discover Our Story</a>
      </div>
    </div>
  </div>
</section>

<!-- ════ WHY KHS ══════════════════════════════════════════ -->
<section class="sec bg-warm" aria-labelledby="why-h">
  <div class="wrap">
    <div class="sec-hd tc">
      <div class="eyebrow">The KHS Difference</div>
      <h2 class="h2" id="why-h">More than a school.<br><em>A foundation for life.</em></h2>
      <p class="sec-desc">We combine rigorous academics with strong values to prepare students for success in Liberia and beyond.</p>
    </div>
    <div class="cards" role="list">
      <?php foreach([
        ['✨','Purposeful Learning',   'A relevant, engaging curriculum aligned to national standards, preparing students for WASSCE, higher education and work.'],
        ['👥','Known & Supported',     'A caring school culture where every student is seen, known and supported — academically and personally.'],
        ['📊','Results-Focused',       'Structured assessments, an approval workflow and published report cards keep everyone aligned throughout the year.'],
        ['🎯','Character Formation',   'We develop responsible, ethical citizens through discipline, community service and strong moral education.'],
        ['🔬','Science & Technology',  'Equipped laboratories and computer facilities prepare students for the demands of a modern economy.'],
        ['🤝','Community Partnership', 'Strong ties with parents, guardians and Karnplay make KHS a school where everyone truly belongs.'],
      ] as [$ico,$t,$d]): ?>
      <article class="card" role="listitem">
        <div class="card-ico" aria-hidden="true"><?= $ico ?></div>
        <h3><?= $t ?></h3>
        <p><?= $d ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ════ ADMISSION PROCESS ════════════════════════════════ -->
<section class="sec bg-white" aria-labelledby="process-h">
  <div class="wrap">
    <div class="sec-hd" style="display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:16px">
      <div>
        <div class="eyebrow">Admissions</div>
        <h2 class="h2" id="process-h">Four steps to<br><em>join KHS.</em></h2>
      </div>
      <a href="<?= BASE_URL ?>/apply.php" class="btn btn-primary">Start Application →</a>
    </div>
    <div class="steps">
      <?php foreach([
        ['Apply Online',          'Complete the multi-step online form. Takes under 10 minutes. Get your application number instantly.'],
        ['Document Verification', 'Our registrar reviews your application and verifies submitted documents.'],
        ['Entrance Examination',  'Approved applicants receive an Entrance Eligibility Letter and sit the KHS entrance exam.'],
        ['Final Admission',       'Successful candidates receive their official admission letter, student ID and class assignment.'],
      ] as $i=>[$t,$d]): ?>
      <article class="step">
        <div class="step-n" aria-label="Step <?= $i+1 ?>"><?= $i+1 ?></div>
        <h3><?= $t ?></h3>
        <p><?= $d ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ════ NEWS & EVENTS (live) ════════════════════════════ -->
<?php if(!empty($anns)||!empty($evts)): ?>
<section class="sec bg-warm" aria-label="News and events">
  <div class="wrap">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:start">

      <?php if(!empty($anns)): ?>
      <div>
        <div class="sec-hd" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
          <div><div class="eyebrow">Announcements</div><h2 class="h2" style="font-size:clamp(1.5rem,3vw,2rem);margin-top:4px">Latest News</h2></div>
          <a href="<?= BASE_URL ?>/news.php" class="lnk">View all</a>
        </div>
        <div class="news-grid" style="grid-template-columns:1fr">
          <?php foreach($anns as $a): ?>
          <article class="news-card">
            <div class="news-thumb"><span class="news-chip">📢 Notice</span></div>
            <div class="news-body">
              <div class="news-date"><?= date('F d, Y',strtotime($a['published_at'])) ?></div>
              <h3><?= e($a['title']) ?></h3>
              <p><?= e(mb_substr($a['message'],0,110)).(mb_strlen($a['message'])>110?'…':'') ?></p>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if(!empty($evts)): ?>
      <div>
        <div class="sec-hd" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
          <div><div class="eyebrow">Calendar</div><h2 class="h2" style="font-size:clamp(1.5rem,3vw,2rem);margin-top:4px">Upcoming Events</h2></div>
          <a href="<?= BASE_URL ?>/events.php" class="lnk">All events</a>
        </div>
        <div class="ev-list">
          <?php foreach($evts as $ev): ?>
          <article class="ev-row">
            <div class="ev-dt" aria-label="<?= date('F d',strtotime($ev['event_date'])) ?>">
              <span class="ev-dd"><?= date('d',strtotime($ev['event_date'])) ?></span>
              <span class="ev-dm"><?= date('M',strtotime($ev['event_date'])) ?></span>
            </div>
            <div class="ev-info">
              <h4><?= e($ev['title']) ?></h4>
              <p><?= e(ucfirst($ev['category'])) ?> · <?= date('Y',strtotime($ev['event_date'])) ?></p>
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

<!-- ════ CTA BAND ═════════════════════════════════════════ -->
<section class="cta-band" aria-label="Apply for admission">
  <div class="wrap">
    <div class="cta-row">
      <div class="cta-copy">
        <div class="eyebrow">Join Our Community</div>
        <h2 class="cta-h">Ready to begin your<br><em>KHS journey?</em></h2>
        <p>Applications for <?= e($ay) ?> are open. Take the first step toward a brighter future for your child today.</p>
        <div class="cta-acts">
          <a href="<?= BASE_URL ?>/apply.php"               class="btn btn-white btn-lg">Apply Online Now</a>
          <a href="<?= BASE_URL ?>/application-status.php"  class="btn btn-ghost">Track Application</a>
        </div>
      </div>
      <div class="cta-aside">
        <div class="ico" aria-hidden="true">📋</div>
        <strong>Quick Application Facts</strong>
        <p>✓ Under 10 minutes to complete<br>✓ No application fee<br>✓ Instant application number<br>✓ Track status online<br>✓ Documents uploadable later</p>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>
