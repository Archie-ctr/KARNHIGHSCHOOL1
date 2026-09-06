<?php
require_once __DIR__.'/config/db.php';
$pageTitle  = 'Events';
$activeNav  = 'events';
$metaDesc   = 'Upcoming and past events at Karn High School, Karnplay, Nimba County, Liberia.';

try {
    $upcoming = db()->query(
        "SELECT * FROM events WHERE is_public=1 AND event_date >= CURDATE() ORDER BY event_date ASC LIMIT 24"
    )->fetchAll();
    $past = db()->query(
        "SELECT * FROM events WHERE is_public=1 AND event_date < CURDATE() ORDER BY event_date DESC LIMIT 8"
    )->fetchAll();
} catch (Throwable $e) { $upcoming = []; $past = []; }

include __DIR__.'/includes/header.php';
?>

<!-- Page Hero -->
<section class="page-hero">
  <div class="wrap ph-body">
    <nav class="bc" aria-label="Breadcrumb">
      <a href="<?= BASE_URL ?>/">Home</a>
      <span class="bc-sep">/</span>
      <span>Events</span>
    </nav>
    <div class="eyebrow inv">School Calendar</div>
    <h1 class="ph-h">Events &amp; activities<br><em>at KHS.</em></h1>
    <p class="ph-lead">Stay connected with what's happening on campus — from academic programmes and sports to cultural celebrations and community engagements.</p>
  </div>
</section>

<!-- Upcoming Events -->
<section class="sec bg-white">
  <div class="wrap">
    <?php if (empty($upcoming) && empty($past)): ?>
    <div style="text-align:center;padding:64px 0">
      <div style="font-size:3.5rem;margin-bottom:var(--sp4)">📅</div>
      <p class="body">No events listed at this time. Check back soon.</p>
      <a href="<?= BASE_URL ?>/contact.php" class="btn btn-outline" style="margin-top:var(--sp5)">Contact Us</a>
    </div>

    <?php else: ?>

    <?php if (!empty($upcoming)): ?>
    <div class="sec-hd">
      <div class="eyebrow">Upcoming Events</div>
      <h2 class="h2" style="margin-top:var(--sp2)">What's coming <em>next.</em></h2>
    </div>

    <div class="ev-list" style="margin-bottom:var(--sp16)">
      <?php foreach ($upcoming as $ev): ?>
      <?php
        $d     = date('d', strtotime($ev['event_date']));
        $m     = date('M', strtotime($ev['event_date']));
        $y     = date('Y', strtotime($ev['event_date']));
        $dayN  = date('l', strtotime($ev['event_date']));
        $catColors = ['academic'=>'badge-red','sports'=>'badge-grn','cultural'=>'badge-gold','general'=>'badge-grey'];
        $cat   = $ev['category'] ?? 'general';
        $badge = $catColors[$cat] ?? 'badge-grey';
      ?>
      <article class="ev-row fade-up">
        <div class="ev-dt" aria-label="<?= $dayN ?>, <?= $d ?> <?= $m ?> <?= $y ?>">
          <span class="ev-dd"><?= $d ?></span>
          <span class="ev-dm"><?= $m ?></span>
        </div>
        <div class="ev-info" style="flex:1">
          <div style="display:flex;align-items:center;gap:var(--sp2);flex-wrap:wrap;margin-bottom:4px">
            <h4><?= e($ev['title']) ?></h4>
            <span class="badge <?= $badge ?>"><?= ucfirst($cat) ?></span>
          </div>
          <?php if (!empty($ev['description'])): ?>
          <p><?= e(mb_substr($ev['description'],0,160)).(mb_strlen($ev['description']??'')>160?'…':'') ?></p>
          <?php endif; ?>
          <div style="display:flex;align-items:center;gap:var(--sp4);margin-top:6px;flex-wrap:wrap">
            <span style="font-size:.78rem;color:var(--ink3)">📅 <?= $dayN ?>, <?= date('F d, Y',strtotime($ev['event_date'])) ?></span>
            <?php if (!empty($ev['location'])): ?>
            <span style="font-size:.78rem;color:var(--ink3)">📍 <?= e($ev['location']) ?></span>
            <?php endif; ?>
            <?php if (!empty($ev['start_time'])): ?>
            <span style="font-size:.78rem;color:var(--ink3)">🕐 <?= date('g:i A',strtotime($ev['start_time'])) ?></span>
            <?php endif; ?>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Past Events -->
    <?php if (!empty($past)): ?>
    <div class="sec-hd">
      <div class="eyebrow">Past Events</div>
      <h2 class="h2" style="margin-top:var(--sp2)">Recent <em>highlights.</em></h2>
    </div>
    <div class="cards fade-up">
      <?php foreach ($past as $ev): ?>
      <?php
        $catColors = ['academic'=>'badge-red','sports'=>'badge-grn','cultural'=>'badge-gold','general'=>'badge-grey'];
        $cat   = $ev['category'] ?? 'general';
        $badge = $catColors[$cat] ?? 'badge-grey';
      ?>
      <article class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--sp3)">
          <span class="badge <?= $badge ?>"><?= ucfirst($cat) ?></span>
          <span class="caption"><?= date('M d, Y',strtotime($ev['event_date'])) ?></span>
        </div>
        <h3><?= e($ev['title']) ?></h3>
        <?php if (!empty($ev['description'])): ?>
        <p><?= e(mb_substr($ev['description'],0,110)).(mb_strlen($ev['description']??'')>110?'…':'') ?></p>
        <?php endif; ?>
        <?php if (!empty($ev['location'])): ?>
        <p style="margin-top:var(--sp3);font-size:.8rem;color:var(--ink4)">📍 <?= e($ev['location']) ?></p>
        <?php endif; ?>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
  </div>
</section>

<!-- CTA -->
<section class="cta-band">
  <div class="wrap">
    <div class="cta-row">
      <div class="cta-copy">
        <div class="eyebrow" style="color:rgba(255,255,255,.55)">Get Involved</div>
        <h2 class="cta-h">Become part of the<br><em>KHS family.</em></h2>
        <p>Apply for admission today and join a school where every student is known, challenged and celebrated.</p>
        <div class="cta-acts">
          <a href="<?= BASE_URL ?>/apply.php" class="btn btn-white btn-lg">Apply Now</a>
          <a href="<?= BASE_URL ?>/contact.php" class="btn btn-ghost btn-lg">Contact Us</a>
        </div>
      </div>
      <div class="cta-aside">
        <div class="ico">📣</div>
        <strong>Stay Updated</strong>
        <p>Visit the school or contact our office to get the latest schedule of events and activities.</p>
        <a href="<?= BASE_URL ?>/contact.php" class="lnk" style="color:#a8dfba;margin-top:var(--sp3)">Get in touch</a>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>
