<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'Gallery';
$activeNav = '';
$metaDesc  = 'Photos and memories from life at Karn High School — classroom moments, sports, events and more.';

try {
    $images = db()->query(
        "SELECT * FROM gallery WHERE is_active=1 ORDER BY sort_order ASC, id DESC"
    )->fetchAll();
} catch (Throwable $e) { $images = []; }

// Group by category if available
$byGroup = [];
foreach ($images as $img) {
    $g = $img['category'] ?? 'General';
    $byGroup[$g][] = $img;
}

include __DIR__.'/includes/header.php';
?>

<!-- Page Hero -->
<section class="page-hero">
  <div class="wrap ph-body">
    <nav class="bc" aria-label="Breadcrumb">
      <a href="<?= BASE_URL ?>/">Home</a>
      <span class="bc-sep">/</span>
      <span>Gallery</span>
    </nav>
    <div class="eyebrow inv">School Life</div>
    <h1 class="ph-h">Life at<br><em>Karn High School.</em></h1>
    <p class="ph-lead">Glimpses of our vibrant school community — in the classroom, on the field, at events and in the wider community.</p>
  </div>
</section>

<?php if (empty($images)): ?>
<!-- Placeholder when no images -->
<section class="sec bg-white">
  <div class="wrap tc">
    <div style="max-width:480px;margin:0 auto;padding:var(--sp16) 0">
      <div style="width:80px;height:80px;background:var(--red-p);border-radius:var(--r4);display:flex;align-items:center;justify-content:center;font-size:2.4rem;margin:0 auto var(--sp6)">🖼️</div>
      <h2 class="h3">Gallery coming soon</h2>
      <p class="body" style="margin-top:var(--sp3)">We're building our photo collection. Check back soon for images of life at KHS.</p>
      <a href="<?= BASE_URL ?>/contact.php" class="btn btn-outline" style="margin-top:var(--sp6)">Contact Us</a>
    </div>
  </div>
</section>

<?php else: ?>

<!-- Gallery Grid -->
<section class="sec bg-white" id="gallery-top">
  <div class="wrap">

    <?php if (count($byGroup) > 1): ?>
    <!-- Category filter tabs -->
    <div style="display:flex;align-items:center;gap:var(--sp2);flex-wrap:wrap;margin-bottom:var(--sp8)" role="tablist" aria-label="Gallery categories">
      <button class="gallery-tab active" data-filter="all" role="tab" aria-selected="true">All Photos</button>
      <?php foreach (array_keys($byGroup) as $grp): ?>
      <button class="gallery-tab" data-filter="<?= htmlspecialchars(strtolower(preg_replace('/\s+/','-',$grp))) ?>" role="tab" aria-selected="false"><?= e($grp) ?></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Masonry-style grid -->
    <div class="gallery-grid" id="galleryGrid">
      <?php foreach ($images as $idx => $img):
        $cat = strtolower(preg_replace('/\s+/','-',$img['category'] ?? 'general'));
        $src = BASE_URL.'/uploads/'.e($img['image_path']);
        $alt = e($img['title'] ?? 'KHS photo');
      ?>
      <figure class="gal-item fade-up" data-cat="<?= $cat ?>" style="animation-delay:<?= ($idx%9)*40 ?>ms">
        <a href="<?= $src ?>" class="gal-link" data-title="<?= $alt ?>" data-caption="<?= e($img['caption'] ?? '') ?>" aria-label="View full size: <?= $alt ?>">
          <img src="<?= $src ?>" alt="<?= $alt ?>" loading="lazy" />
          <div class="gal-overlay">
            <span class="gal-zoom">🔍</span>
          </div>
        </a>
        <?php if (!empty($img['title']) || !empty($img['caption'])): ?>
        <figcaption>
          <?php if (!empty($img['title'])): ?><strong><?= e($img['title']) ?></strong><?php endif; ?>
          <?php if (!empty($img['caption'])): ?><span><?= e($img['caption']) ?></span><?php endif; ?>
        </figcaption>
        <?php endif; ?>
      </figure>
      <?php endforeach; ?>
    </div>

  </div>
</section>

<!-- Lightbox -->
<div id="lightbox" class="lb" role="dialog" aria-modal="true" aria-label="Image viewer" hidden>
  <button class="lb-close" id="lbClose" aria-label="Close lightbox">✕</button>
  <button class="lb-prev"  id="lbPrev"  aria-label="Previous image">‹</button>
  <button class="lb-next"  id="lbNext"  aria-label="Next image">›</button>
  <div class="lb-stage">
    <img id="lbImg" src="" alt="" />
    <div class="lb-cap" id="lbCap"></div>
  </div>
</div>

<style>
/* Gallery-specific styles */
.gallery-tab{padding:8px 18px;border-radius:var(--pill);font-size:.83rem;font-weight:600;border:1.5px solid var(--bdr);background:#fff;color:var(--ink2);cursor:pointer;transition:all var(--t2)}
.gallery-tab:hover,.gallery-tab.active{background:var(--red);color:#fff;border-color:var(--red)}

.gallery-grid{columns:3 280px;gap:var(--sp4)}
@media(max-width:600px){.gallery-grid{columns:2 160px;gap:var(--sp3)}}
@media(max-width:380px){.gallery-grid{columns:1}}

.gal-item{break-inside:avoid;margin-bottom:var(--sp4);border-radius:var(--r2);overflow:hidden;background:#fff;border:1px solid var(--bdr);box-shadow:var(--s1);transition:box-shadow var(--t2)}
.gal-item:hover{box-shadow:var(--s3)}
.gal-item[hidden]{display:none}

.gal-link{display:block;position:relative;overflow:hidden;cursor:zoom-in}
.gal-link img{width:100%;height:auto;display:block;transition:transform .4s var(--ease)}
.gal-link:hover img{transform:scale(1.04)}

.gal-overlay{position:absolute;inset:0;background:rgba(35,31,27,.45);display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity var(--t2)}
.gal-link:hover .gal-overlay{opacity:1}
.gal-zoom{font-size:1.8rem;color:#fff}

figcaption{padding:10px 14px}
figcaption strong{display:block;font-size:.87rem;font-weight:700;color:var(--ink)}
figcaption span{display:block;font-size:.78rem;color:var(--ink3);margin-top:2px}

/* Lightbox */
.lb{position:fixed;inset:0;background:rgba(0,0,0,.9);z-index:9000;display:flex;align-items:center;justify-content:center;padding:var(--sp5)}
.lb[hidden]{display:none}
.lb-stage{max-width:900px;width:100%;text-align:center}
.lb-stage img{max-height:78vh;width:auto;max-width:100%;border-radius:var(--r2);box-shadow:0 20px 60px rgba(0,0,0,.5)}
.lb-cap{color:rgba(255,255,255,.75);font-size:.88rem;margin-top:var(--sp3);min-height:20px}
.lb-close,.lb-prev,.lb-next{position:fixed;background:rgba(255,255,255,.12);color:#fff;border:1.5px solid rgba(255,255,255,.2);border-radius:50%;width:44px;height:44px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;cursor:pointer;transition:background var(--t1);backdrop-filter:blur(8px)}
.lb-close{top:var(--sp5);right:var(--sp5)}
.lb-prev{left:var(--sp5);top:50%;transform:translateY(-50%)}
.lb-next{right:var(--sp5);top:50%;transform:translateY(-50%)}
.lb-close:hover,.lb-prev:hover,.lb-next:hover{background:rgba(255,255,255,.25)}
@media(max-width:480px){.lb-prev{left:var(--sp2)}.lb-next{right:var(--sp2)}}
</style>

<script>
(function(){
  // Filter tabs
  const tabs = document.querySelectorAll('.gallery-tab');
  const items = document.querySelectorAll('.gal-item');
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => { t.classList.remove('active'); t.setAttribute('aria-selected','false'); });
      tab.classList.add('active');
      tab.setAttribute('aria-selected','true');
      const f = tab.dataset.filter;
      items.forEach(item => {
        if (f === 'all' || item.dataset.cat === f) {
          item.removeAttribute('hidden');
        } else {
          item.setAttribute('hidden','');
        }
      });
    });
  });

  // Lightbox
  const lb     = document.getElementById('lightbox');
  const lbImg  = document.getElementById('lbImg');
  const lbCap  = document.getElementById('lbCap');
  const lbClose= document.getElementById('lbClose');
  const lbPrev = document.getElementById('lbPrev');
  const lbNext = document.getElementById('lbNext');
  const links  = Array.from(document.querySelectorAll('.gal-link'));
  let cur = 0;

  function openLb(idx) {
    cur = idx;
    const l = links[idx];
    lbImg.src = l.href;
    lbImg.alt = l.dataset.title || '';
    lbCap.textContent = l.dataset.caption || l.dataset.title || '';
    lb.removeAttribute('hidden');
    document.body.style.overflow = 'hidden';
    lbClose.focus();
  }
  function closeLb() {
    lb.setAttribute('hidden','');
    document.body.style.overflow = '';
    links[cur].focus();
  }
  function navigate(dir) {
    const visible = links.filter(l => !l.closest('.gal-item').hasAttribute('hidden'));
    const i = visible.indexOf(links[cur]);
    const next = visible[(i + dir + visible.length) % visible.length];
    cur = links.indexOf(next);
    openLb(cur);
  }

  links.forEach((l, i) => {
    l.addEventListener('click', e => { e.preventDefault(); openLb(i); });
  });
  lbClose.addEventListener('click', closeLb);
  lbPrev.addEventListener('click', () => navigate(-1));
  lbNext.addEventListener('click', () => navigate(1));
  lb.addEventListener('click', e => { if (e.target === lb) closeLb(); });
  document.addEventListener('keydown', e => {
    if (lb.hasAttribute('hidden')) return;
    if (e.key === 'Escape') closeLb();
    if (e.key === 'ArrowLeft')  navigate(-1);
    if (e.key === 'ArrowRight') navigate(1);
  });
})();
</script>
<?php endif; ?>

<!-- CTA -->
<section class="cta-band">
  <div class="wrap">
    <div class="cta-row">
      <div class="cta-copy">
        <div class="eyebrow" style="color:rgba(255,255,255,.55)">Be Part of It</div>
        <h2 class="cta-h">Join a community<br><em>that thrives.</em></h2>
        <p>See yourself in our classrooms, on our fields, and at our events. Apply to Karn High School today.</p>
        <div class="cta-acts">
          <a href="<?= BASE_URL ?>/apply.php"   class="btn btn-white btn-lg">Apply Now</a>
          <a href="<?= BASE_URL ?>/contact.php" class="btn btn-ghost btn-lg">Contact Us</a>
        </div>
      </div>
      <div class="cta-aside">
        <div class="ico">📸</div>
        <strong>Share Your Memories</strong>
        <p>Are you a student or alumnus with great photos of KHS life? Contact us to contribute to our gallery.</p>
        <a href="<?= BASE_URL ?>/contact.php" class="lnk" style="color:#a8dfba;margin-top:var(--sp3)">Get in touch</a>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>
