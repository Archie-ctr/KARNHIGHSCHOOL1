<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'FAQ';
$activeNav = '';
$faqs = db()->query("SELECT * FROM faq WHERE is_active=1 ORDER BY sort_order,id")->fetchAll();
include __DIR__.'/includes/header.php';
?>
<main class="inner-page">
  <div class="container inner-hero">
    <div class="eyebrow">Help &amp; Answers <span></span></div>
    <h1>Frequently asked<br><em>questions.</em></h1>
    <p>Everything you need to know about Karn High School — admissions, academics, fees and more.</p>
  </div>
  <div class="container" style="padding-bottom:80px;max-width:800px">
    <?php if (empty($faqs)): ?>
    <p style="color:var(--ink-soft)">No FAQs available yet. Please contact us with your questions.</p>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:2px">
      <?php foreach ($faqs as $i => $faq): ?>
      <div style="background:var(--surface);border:1px solid var(--line);border-radius:var(--radius-sm);overflow:hidden">
        <button onclick="toggleFaq(<?= $i ?>)" style="width:100%;display:flex;justify-content:space-between;align-items:center;padding:18px 20px;font-size:14.5px;font-weight:700;color:var(--ink);text-align:left;background:none;border:none;cursor:pointer">
          <span><?= e($faq['question']) ?></span>
          <span id="faq-icon-<?= $i ?>" style="font-size:18px;color:var(--primary);flex-shrink:0;margin-left:12px">+</span>
        </button>
        <div id="faq-body-<?= $i ?>" style="display:none;padding:0 20px 18px;font-size:14px;line-height:1.7;color:var(--ink-soft)">
          <?= nl2br(e($faq['answer'])) ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div style="margin-top:40px;padding:28px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);text-align:center">
      <strong style="font-size:16px">Still have questions?</strong>
      <p style="color:var(--ink-soft);margin:8px 0 16px">Our admissions team is happy to help.</p>
      <a href="<?= BASE_URL ?>/contact.php" class="button button-primary">Contact Us →</a>
    </div>
  </div>
</main>
<script>
function toggleFaq(i) {
  const body = document.getElementById('faq-body-'+i);
  const icon = document.getElementById('faq-icon-'+i);
  const open = body.style.display !== 'none' && body.style.display !== '';
  body.style.display = open ? 'none' : 'block';
  icon.textContent   = open ? '+' : '−';
}
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
