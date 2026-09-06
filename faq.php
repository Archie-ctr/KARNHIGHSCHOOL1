<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'FAQ';
$activeNav = '';
$metaDesc  = 'Frequently asked questions about Karn High School — admissions, fees, academics, uniforms and more.';

try {
    $faqs = db()->query(
        "SELECT * FROM faq WHERE is_active=1 ORDER BY sort_order ASC, id ASC"
    )->fetchAll();
} catch (Throwable $e) { $faqs = []; }

// Static fallback FAQ if DB is empty
if (empty($faqs)) {
    $faqs = [
        ['question'=>'How do I apply to Karn High School?',
         'answer'=>'You can apply online through our website by clicking "Apply Now". Complete the application form, upload the required documents and submit. You will receive your application number immediately.'],
        ['question'=>'What documents are required for admission?',
         'answer'=>'Applicants need to provide a birth certificate or national ID, last academic report card or transcript, two recent passport-sized photographs, and a parent/guardian contact number. Additional documents may be requested during the review.'],
        ['question'=>'Is there an entrance examination?',
         'answer'=>'Yes. After initial review, qualified applicants are invited to sit an entrance examination covering English and Mathematics. Successful candidates receive an admission offer letter.'],
        ['question'=>'What grades/levels does KHS offer?',
         'answer'=>'Karn High School offers education from ABC/KG through Grade 12, covering Early Childhood & Primary (ABC–Grade 6), Junior High School (Grades 7–9) and Senior High School (Grades 10–12).'],
        ['question'=>'What are the school fees?',
         'answer'=>'Fees vary by grade level and are reviewed each academic year. Please contact the school directly or visit our finance office for the current fee schedule for your child\'s grade.'],
        ['question'=>'What is the school uniform?',
         'answer'=>'All students are required to wear the official KHS uniform. Details of the uniform requirements, including colour and style specifications, are provided in the offer letter upon admission.'],
        ['question'=>'What extracurricular activities are available?',
         'answer'=>'KHS offers a range of extracurricular activities including football, athletics, debate club, science club, drama and community service programmes. We believe in developing the whole child beyond academics.'],
        ['question'=>'How can I check my child\'s academic progress?',
         'answer'=>'Parents and guardians can access their child\'s academic information through our parent portal. Report cards are issued at the end of each term, and parent-teacher meetings are held regularly.'],
        ['question'=>'How do I contact the school?',
         'answer'=>'You can reach us by phone at +231 886 417 711, by email at info@karnhighschool.edu.lr, or by visiting us at Karnplay, Nimba County, Liberia. Our office is open Monday–Friday, 8:00 AM–4:30 PM.'],
        ['question'=>'Does KHS accept transfer students?',
         'answer'=>'Yes. Transfer students from accredited schools may apply. An assessment of prior academic records will be conducted to determine appropriate placement. Contact the registrar for transfer admission details.'],
    ];
}

// Group by category if present
$grouped = [];
foreach ($faqs as $f) {
    $g = $f['category'] ?? 'General';
    $grouped[$g][] = $f;
}

include __DIR__.'/includes/header.php';
?>

<!-- Page Hero -->
<section class="page-hero">
  <div class="wrap ph-body">
    <nav class="bc" aria-label="Breadcrumb">
      <a href="<?= BASE_URL ?>/">Home</a>
      <span class="bc-sep">/</span>
      <span>FAQ</span>
    </nav>
    <div class="eyebrow inv">Help Centre</div>
    <h1 class="ph-h">Frequently asked<br><em>questions.</em></h1>
    <p class="ph-lead">Everything you need to know about Karn High School — admissions, academics, fees, uniforms and more. Can't find your answer? Contact us directly.</p>
  </div>
</section>

<!-- FAQ Section -->
<section class="sec bg-white">
  <div class="wrap">
    <div style="display:grid;grid-template-columns:1fr 3fr;gap:clamp(32px,5vw,64px);align-items:start" class="faq-layout">

      <!-- Sidebar quick links -->
      <aside class="faq-sidebar" style="position:sticky;top:calc(var(--nav-h) + 24px)">
        <div style="background:var(--bg2);border-radius:var(--r3);padding:var(--sp6)">
          <h3 class="h4" style="margin-bottom:var(--sp4)">Topics</h3>
          <nav style="display:flex;flex-direction:column;gap:4px" aria-label="FAQ categories">
            <?php if (count($grouped) === 1 && isset($grouped['General'])): ?>
              <?php foreach ($faqs as $i => $faq): ?>
              <a href="#faq-<?= $i ?>" style="font-size:.85rem;color:var(--ink2);padding:7px var(--sp3);border-radius:var(--r1);transition:all var(--t1);display:block" onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''"><?= e(mb_substr($faq['question'],0,45)).'…' ?></a>
              <?php endforeach; ?>
            <?php else: ?>
              <?php foreach (array_keys($grouped) as $grp): ?>
              <a href="#faq-cat-<?= htmlspecialchars(strtolower(str_replace(' ','-',$grp))) ?>" style="font-size:.87rem;color:var(--ink2);padding:8px var(--sp3);border-radius:var(--r1);transition:all var(--t1);display:block" onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''"><?= e($grp) ?></a>
              <?php endforeach; ?>
            <?php endif; ?>
          </nav>
          <div class="divider" style="margin:var(--sp5) 0"></div>
          <h4 style="font-size:.83rem;font-weight:700;color:var(--ink);margin-bottom:var(--sp3)">Still have questions?</h4>
          <p style="font-size:.82rem;color:var(--ink3);margin-bottom:var(--sp4);line-height:1.65">Our admissions team is happy to help with any query not covered here.</p>
          <a href="<?= BASE_URL ?>/contact.php" class="btn btn-primary btn-sm btn-full">Contact Us</a>
        </div>
      </aside>

      <!-- FAQ Accordion -->
      <div>
        <?php $globalIdx = 0; foreach ($grouped as $grpName => $items): ?>

        <?php if (count($grouped) > 1): ?>
        <h2 class="h4" id="faq-cat-<?= htmlspecialchars(strtolower(str_replace(' ','-',$grpName))) ?>" style="margin-bottom:var(--sp5);padding-bottom:var(--sp3);border-bottom:2px solid var(--bdr)"><?= e($grpName) ?></h2>
        <?php endif; ?>

        <div style="margin-bottom:var(--sp10)">
          <?php foreach ($items as $faq):
            $id = 'faq-'.$globalIdx++;
          ?>
          <div class="faq-item fade-up" id="<?= $id ?>">
            <button class="faq-q" onclick="toggleFaq('<?= $id ?>')" aria-expanded="false" aria-controls="<?= $id ?>-body">
              <?= e($faq['question']) ?>
              <span class="faq-ico" id="<?= $id ?>-ico" aria-hidden="true">+</span>
            </button>
            <div class="faq-a" id="<?= $id ?>-body" role="region">
              <?= nl2br(e($faq['answer'])) ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <?php endforeach; ?>
      </div>

    </div>
  </div>
</section>

<style>
@media(max-width:768px){
  .faq-layout{grid-template-columns:1fr !important}
  .faq-sidebar{position:static !important}
}
</style>

<script>
function toggleFaq(id) {
  const btn  = document.querySelector('#' + id + ' .faq-q');
  const body = document.getElementById(id + '-body');
  const ico  = document.getElementById(id + '-ico');
  const open = body.style.display === 'block';
  body.style.display = open ? 'none' : 'block';
  ico.textContent = open ? '+' : '×';
  ico.classList.toggle('open', !open);
  btn.setAttribute('aria-expanded', String(!open));
}
</script>

<!-- CTA -->
<section class="cta-band">
  <div class="wrap">
    <div class="cta-row">
      <div class="cta-copy">
        <div class="eyebrow" style="color:rgba(255,255,255,.55)">Ready to Join?</div>
        <h2 class="cta-h">Start your application<br><em>today.</em></h2>
        <p>Have all the information you need? Take the next step and apply to Karn High School online.</p>
        <div class="cta-acts">
          <a href="<?= BASE_URL ?>/apply.php"      class="btn btn-white btn-lg">Apply Now</a>
          <a href="<?= BASE_URL ?>/contact.php"    class="btn btn-ghost btn-lg">Ask a Question</a>
        </div>
      </div>
      <div class="cta-aside">
        <div class="ico">📞</div>
        <strong>Call Our Office</strong>
        <p>+231 886 417 711<br>Monday – Friday · 8:00 AM – 4:30 PM</p>
        <a href="<?= BASE_URL ?>/contact.php" class="lnk" style="color:#a8dfba;margin-top:var(--sp3)">Send a message</a>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__.'/includes/footer.php'; ?>
