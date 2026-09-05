<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'Our Teachers';
$activeNav = 'about';
$teachers  = db()->query(
    "SELECT first_name,last_name,specialization,qualification,photo FROM teachers WHERE status='Active' ORDER BY first_name"
)->fetchAll();
include __DIR__.'/includes/header.php';
?>
<main class="inner-page">
  <div class="container inner-hero">
    <div class="eyebrow">Our Educators <span></span></div>
    <h1>Meet the teachers<br><em>behind our success.</em></h1>
    <p>Our dedicated educators bring expertise, passion and care to every classroom at Karn High School.</p>
  </div>

  <div class="container" style="padding-bottom:80px">
    <?php if (empty($teachers)): ?>
    <div style="text-align:center;padding:60px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
      <div style="font-size:40px;margin-bottom:12px">👩‍🏫</div>
      <p style="color:var(--ink-soft)">Staff profiles coming soon.</p>
    </div>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:20px">
      <?php foreach ($teachers as $t):
        $initials = strtoupper(substr($t['first_name'],0,1).substr($t['last_name'],0,1));
      ?>
      <article style="background:var(--surface);border:1px solid var(--line);border-radius:var(--radius-lg);overflow:hidden;transition:all .25s">
        <div style="height:140px;background:linear-gradient(135deg,var(--primary-soft),var(--bg-soft));display:flex;align-items:center;justify-content:center">
          <?php if ($t['photo']): ?>
            <img src="<?= BASE_URL ?>/uploads/<?= e($t['photo']) ?>" alt="" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #fff;box-shadow:var(--shadow-md)"/>
          <?php else: ?>
            <div style="width:72px;height:72px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:800"><?= e($initials) ?></div>
          <?php endif; ?>
        </div>
        <div style="padding:18px 20px">
          <strong style="display:block;font-size:15px;font-weight:700"><?= e($t['first_name'].' '.$t['last_name']) ?></strong>
          <span style="font-size:13px;color:var(--primary);font-weight:600"><?= e($t['specialization'] ?? 'Teacher') ?></span>
          <?php if ($t['qualification']): ?>
          <p style="font-size:12.5px;color:var(--ink-faint);margin-top:4px"><?= e($t['qualification']) ?></p>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__.'/includes/footer.php'; ?>
