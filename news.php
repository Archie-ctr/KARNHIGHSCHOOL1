<?php
require_once __DIR__.'/config/db.php';
$pageTitle='News'; $activeNav='news';
$metaDesc='Latest news and announcements from Karn High School, Karnplay, Nimba, Liberia.';
try{$anns=db()->query("SELECT title,message,published_at,target FROM announcements WHERE is_public=1 AND (expires_at IS NULL OR expires_at>NOW()) ORDER BY published_at DESC LIMIT 12")->fetchAll();}catch(Throwable $e){$anns=[];}
include __DIR__.'/includes/header.php';
?>
<section class="page-hero">
  <div class="wrap ph-body">
    <nav class="bc" aria-label="Breadcrumb"><a href="<?=BASE_URL?>/">Home</a><span class="bc-sep">/</span><span>News</span></nav>
    <div class="eyebrow inv">News &amp; Announcements</div>
    <h1 class="ph-h">What's happening<br><em>at KHS.</em></h1>
  </div>
</section>
<section class="sec bg-white">
  <div class="wrap">
    <?php if(empty($anns)): ?>
    <div style="text-align:center;padding:56px 0">
      <div style="font-size:3rem;margin-bottom:16px">📰</div>
      <p class="body">No announcements at this time. Check back soon.</p>
    </div>
    <?php else: ?>
    <div class="news-grid">
      <?php foreach($anns as $a): ?>
      <article class="news-card">
        <div class="news-thumb"><span class="news-chip">📢 <?= e(ucfirst($a['target']??'Notice')) ?></span></div>
        <div class="news-body">
          <div class="news-date"><?= date('F d, Y',strtotime($a['published_at'])) ?></div>
          <h3><?= e($a['title']) ?></h3>
          <p><?= e(mb_substr($a['message'],0,130)).(mb_strlen($a['message'])>130?'…':'') ?></p>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
<section class="cta-band">
  <div class="wrap"><div class="cta-row">
    <div class="cta-copy">
      <div class="eyebrow">Stay Connected</div>
      <h2 class="cta-h">Never miss a<br><em>school update.</em></h2>
      <p>Contact us to be added to our parent communication list.</p>
      <div class="cta-acts">
        <a href="<?=BASE_URL?>/contact.php" class="btn btn-white btn-lg">Contact Us →</a>
        <a href="<?=BASE_URL?>/events.php"  class="btn btn-ghost">Upcoming Events</a>
      </div>
    </div>
  </div></div>
</section>
<?php include __DIR__.'/includes/footer.php'; ?>
