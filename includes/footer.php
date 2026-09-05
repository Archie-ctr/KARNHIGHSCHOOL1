<?php
$sName  = setting('school_name',  'KARN HIGH SCHOOL');
$sPhone = setting('school_phone', '+231 886 417 711');
$sPhone2= setting('school_phone2','+231 777 417 711');
$sEmail = setting('school_email', 'info@karnhighschool.edu.lr');
$sTag   = setting('school_tagline','Building knowledge, character and a better future.');
?>
</main>

<footer class="site-footer" aria-label="Site footer">
  <div class="wrap">
    <div class="footer-grid">

      <!-- Brand column -->
      <div>
        <a href="<?= BASE_URL ?>/" aria-label="<?= e($sName) ?> Home">
          <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="<?= e($sName) ?> logo" width="40" height="40" style="border-radius:9px;object-fit:cover"/>
        </a>
        <div class="footer-brand-name" style="margin-top:12px"><?= e($sName) ?></div>
        <div class="footer-brand-loc">Karnplay, Nimba County, Liberia</div>
        <p><?= e($sTag) ?></p>
        <!-- Social / additional -->
        <div style="margin-top:16px;display:flex;gap:8px">
          <a href="<?= BASE_URL ?>/apply.php" style="display:inline-block;background:var(--c-red);color:#fff;padding:8px 16px;border-radius:8px;font-size:.8rem;font-weight:700;transition:background .2s" onmouseover="this.style.background='#8a162d'" onmouseout="this.style.background='var(--c-red)'">Apply Now →</a>
          <a href="<?= BASE_URL ?>/login.php" style="display:inline-block;background:rgba(255,255,255,.1);color:rgba(255,255,255,.75);padding:8px 16px;border-radius:8px;font-size:.8rem;font-weight:700;border:1px solid rgba(255,255,255,.2)">Portal Login</a>
        </div>
      </div>

      <!-- Quick links -->
      <div>
        <h5 class="footer-col" style="font-size:.72rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#fff;margin-bottom:16px">Explore</h5>
        <nav aria-label="Footer navigation">
          <a href="<?= BASE_URL ?>/about.php">About KHS</a>
          <a href="<?= BASE_URL ?>/academics.php">Academics</a>
          <a href="<?= BASE_URL ?>/programs.php">Programs</a>
          <a href="<?= BASE_URL ?>/admissions.php">Admissions</a>
          <a href="<?= BASE_URL ?>/facilities.php">Facilities</a>
          <a href="<?= BASE_URL ?>/teachers.php">Our Teachers</a>
        </nav>
      </div>

      <!-- Resources -->
      <div>
        <h5 style="font-size:.72rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#fff;margin-bottom:16px">Resources</h5>
        <nav aria-label="Resources navigation">
          <a href="<?= BASE_URL ?>/news.php">News &amp; Events</a>
          <a href="<?= BASE_URL ?>/gallery.php">Gallery</a>
          <a href="<?= BASE_URL ?>/faq.php">FAQs</a>
          <a href="<?= BASE_URL ?>/contact.php">Contact Us</a>
          <a href="<?= BASE_URL ?>/apply.php">Apply Online</a>
          <a href="<?= BASE_URL ?>/application-status.php">Track Application</a>
        </nav>
      </div>

      <!-- Contact -->
      <div>
        <h5 style="font-size:.72rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#fff;margin-bottom:16px">Contact</h5>
        <div class="footer-contact-item">
          <span>📍</span>
          <div><p>Karnplay, Nimba County<br>Liberia, West Africa</p></div>
        </div>
        <div class="footer-contact-item">
          <span>📞</span>
          <div><p><?= e($sPhone) ?></p><?php if($sPhone2): ?><p><?= e($sPhone2) ?></p><?php endif; ?></div>
        </div>
        <div class="footer-contact-item">
          <span>✉️</span>
          <div><p><?= e($sEmail) ?></p></div>
        </div>
        <div class="footer-contact-item">
          <span>🕐</span>
          <div><p><?= e(setting('office_hours','Mon–Fri, 8:00am–4:00pm')) ?></p></div>
        </div>
      </div>
    </div>

    <!-- Footer bottom -->
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> <?= e($sName) ?>. All rights reserved.</span>
      <div class="footer-links">
        <a href="<?= BASE_URL ?>/privacy.php">Privacy Policy</a>
        <a href="<?= BASE_URL ?>/terms.php">Terms &amp; Conditions</a>
      </div>
    </div>
  </div>
</footer>

<script src="<?= BASE_URL ?>/assets/js/public.js"></script>
</body>
</html>
