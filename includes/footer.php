<?php
$sName   = setting('school_name',   'KARN HIGH SCHOOL');
$sPhone  = setting('school_phone',  '+231 886 417 711');
$sPhone2 = setting('school_phone2', '+231 777 417 711');
$sEmail  = setting('school_email',  'info@karnhighschool.edu.lr');
$sTag    = setting('school_tagline','Building knowledge, character and a better future.');
$sHours  = setting('office_hours',  'Monday–Friday, 8:00am–4:00pm');
?>
</main>

<footer class="site-footer" aria-label="Site footer">
  <div class="wrap">
    <div class="footer-grid">

      <!-- Brand -->
      <div class="ft-brand">
        <a href="<?= BASE_URL ?>/" aria-label="<?= e($sName) ?> homepage">
          <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="<?= e($sName) ?> logo" class="ft-logo" width="40" height="40" loading="lazy"/>
        </a>
        <span class="ft-brand-name"><?= e($sName) ?></span>
        <span class="ft-brand-place">Karnplay, Nimba County, Liberia</span>
        <p><?= e($sTag) ?></p>
        <div class="ft-brand-btns">
          <a href="<?= BASE_URL ?>/apply.php" class="btn btn-primary btn-sm">Apply Now</a>
          <a href="<?= BASE_URL ?>/login.php" class="btn btn-ghost btn-sm">Portal Login</a>
        </div>
      </div>

      <!-- Explore -->
      <div>
        <span class="ft-col-h">Explore</span>
        <nav aria-label="Footer explore links">
          <a href="<?= BASE_URL ?>/about.php">About KHS</a>
          <a href="<?= BASE_URL ?>/academics.php">Academics</a>
          <a href="<?= BASE_URL ?>/programs.php">Programmes</a>
          <a href="<?= BASE_URL ?>/admissions.php">Admissions</a>
          <a href="<?= BASE_URL ?>/facilities.php">Facilities</a>
          <a href="<?= BASE_URL ?>/teachers.php">Our Teachers</a>
        </nav>
      </div>

      <!-- Resources -->
      <div>
        <span class="ft-col-h">Resources</span>
        <nav aria-label="Footer resources links">
          <a href="<?= BASE_URL ?>/news.php">News</a>
          <a href="<?= BASE_URL ?>/events.php">Events</a>
          <a href="<?= BASE_URL ?>/gallery.php">Gallery</a>
          <a href="<?= BASE_URL ?>/faq.php">FAQs</a>
          <a href="<?= BASE_URL ?>/apply.php">Apply Online</a>
          <a href="<?= BASE_URL ?>/application-status.php">Track Application</a>
        </nav>
      </div>

      <!-- Contact -->
      <div>
        <span class="ft-col-h">Contact</span>
        <div class="ft-contact-row">
          <span aria-hidden="true">📍</span>
          <p>Karnplay, Nimba County<br>Liberia, West Africa</p>
        </div>
        <div class="ft-contact-row">
          <span aria-hidden="true">📞</span>
          <p><?= e($sPhone) ?><?= $sPhone2 ? '<br>'.e($sPhone2) : '' ?></p>
        </div>
        <div class="ft-contact-row">
          <span aria-hidden="true">✉️</span>
          <p><a href="mailto:<?= e($sEmail) ?>" style="color:inherit"><?= e($sEmail) ?></a></p>
        </div>
        <div class="ft-contact-row">
          <span aria-hidden="true">🕐</span>
          <p><?= e($sHours) ?></p>
        </div>
      </div>
    </div>

    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> <?= e($sName) ?>. All rights reserved.</span>
      <div class="ft-btm-links">
        <a href="<?= BASE_URL ?>/privacy.php">Privacy Policy</a>
        <a href="<?= BASE_URL ?>/terms.php">Terms &amp; Conditions</a>
      </div>
    </div>
  </div>
</footer>

<script src="<?= BASE_URL ?>/assets/js/public.js"></script>
</body>
</html>
