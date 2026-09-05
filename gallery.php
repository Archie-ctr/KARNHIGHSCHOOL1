<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'Gallery';
$activeNav = '';
$images = db()->query("SELECT * FROM gallery WHERE is_active=1 ORDER BY sort_order,id DESC")->fetchAll();
include __DIR__.'/includes/header.php';
?>
<main class="inner-page">
  <div class="container inner-hero">
    <div class="eyebrow">School Life <span></span></div>
    <h1>Life at<br><em>Karn High School.</em></h1>
    <p>Glimpses of our vibrant school community — in the classroom, on the field, and in the community.</p>
  </div>
  <div class="container" style="padding-bottom:80px">
    <?php if (empty($images)): ?>
    <div style="text-align:center;padding:60px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
      <div style="font-size:40px;margin-bottom:12px">🖼️</div>
      <p style="color:var(--ink-soft)">Gallery coming soon. Check back for photos of life at KHS.</p>
    </div>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px">
      <?php foreach ($images as $img): ?>
      <div style="background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);overflow:hidden;transition:all .25s" onmouseover="this.style.boxShadow='var(--shadow-md)'" onmouseout="this.style.boxShadow=''">
        <img src="<?= BASE_URL ?>/uploads/<?= e($img['image_path']) ?>" alt="<?= e($img['title']) ?>" style="width:100%;height:200px;object-fit:cover"/>
        <?php if ($img['title'] || $img['caption']): ?>
        <div style="padding:12px 14px">
          <?php if ($img['title']): ?><strong style="font-size:13.5px"><?= e($img['title']) ?></strong><?php endif; ?>
          <?php if ($img['caption']): ?><p style="font-size:12.5px;color:var(--ink-soft);margin-top:4px"><?= e($img['caption']) ?></p><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__.'/includes/footer.php'; ?>
