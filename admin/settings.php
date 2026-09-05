<?php
// ── Process POST first — BEFORE any output ────────────────────
require_once dirname(__DIR__).'/config/db.php';
requireAuth(); requireRole(['principal','super_admin']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // Save text settings
    $keys = [
        'school_name','school_tagline','school_address','school_phone','school_phone2',
        'school_email','school_founded','school_motto','office_hours',
        'current_academic_year','admission_open','admission_year',
        'currency_primary','currency_secondary','passing_grade',
        'hero_headline','hero_subtext','welcome_message',
        'stats_students','stats_teachers','stats_grades','stats_years',
    ];
    foreach ($keys as $k) {
        $v = trim($_POST[$k] ?? '');
        $pdo->prepare("INSERT INTO school_settings (setting_key,setting_value) VALUES (?,?)
                       ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")
           ->execute([$k, $v]);
    }

    // Handle logo upload
    if (!empty($_FILES['school_logo']['name']) && $_FILES['school_logo']['error'] === UPLOAD_ERR_OK) {
        $path = uploadFile($_FILES['school_logo'], 'logos', ['jpg','jpeg','png'], 2);
        if ($path) {
            // Ensure the assets/images directory exists
            $imgDir = BASE_PATH . '/assets/images';
            if (!is_dir($imgDir)) {
                mkdir($imgDir, 0755, true);
            }
            $dest = $imgDir . '/logo.jpg';
            if (!copy(UPLOAD_DIR . '/' . $path, $dest)) {
                flash('warning', 'Settings saved but logo copy failed. Check folder permissions.');
            }
        }
    }

    flash('success', 'Settings saved successfully.');
    redirect(BASE_URL . '/admin/settings.php');
}

// ── Now include header (outputs HTML) ────────────────────────
$pageTitle   = 'Settings';
$activeAdmin = 'settings';
require_once dirname(__DIR__).'/includes/admin_header.php';

// Load all settings for display
$allSettings = $pdo->query("SELECT setting_key,setting_value FROM school_settings")
                   ->fetchAll(PDO::FETCH_KEY_PAIR);
$s = fn($k, $d = '') => $allSettings[$k] ?? $d;
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">System <span></span></div>
    <h1>School Settings</h1>
    <p>Configure KARN HIGH SCHOOL system preferences.</p>
  </div>
</div>

<form method="post" enctype="multipart/form-data">
  <?= csrfField() ?>

  <!-- School Information -->
  <div class="form-section">
    <div class="form-section-title">🏫 School Information</div>
    <div class="form-row">
      <div class="form-group"><label>School Name<input name="school_name" value="<?= e($s('school_name','KARN HIGH SCHOOL')) ?>"/></label></div>
      <div class="form-group"><label>School Founded<input name="school_founded" value="<?= e($s('school_founded','1985')) ?>"/></label></div>
    </div>
    <div class="form-row full"><div class="form-group"><label>Tagline<input name="school_tagline" value="<?= e($s('school_tagline','Building Knowledge, Character and a Better Future')) ?>"/></label></div></div>
    <div class="form-row full"><div class="form-group"><label>Address<input name="school_address" value="<?= e($s('school_address','Karnplay, Nimba County, Liberia')) ?>"/></label></div></div>
    <div class="form-row">
      <div class="form-group"><label>Phone 1<input name="school_phone" value="<?= e($s('school_phone','+231 886 417 711')) ?>"/></label></div>
      <div class="form-group"><label>Phone 2<input name="school_phone2" value="<?= e($s('school_phone2','+231 777 417 711')) ?>"/></label></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Email<input type="email" name="school_email" value="<?= e($s('school_email','info@karnhighschool.edu.lr')) ?>"/></label></div>
      <div class="form-group"><label>Office Hours<input name="office_hours" value="<?= e($s('office_hours','Monday–Friday, 8:00am–4:00pm')) ?>"/></label></div>
    </div>
    <div class="form-row full"><div class="form-group"><label>School Motto<input name="school_motto" value="<?= e($s('school_motto','Excellence in Education')) ?>"/></label></div></div>
  </div>

  <!-- Logo -->
  <div class="form-section">
    <div class="form-section-title">🖼️ School Logo</div>
    <div style="display:flex;align-items:center;gap:20px;margin-bottom:14px">
      <?php $logoPath = BASE_PATH.'/assets/images/logo.jpg'; ?>
      <?php if (file_exists($logoPath)): ?>
        <img src="<?= BASE_URL ?>/assets/images/logo.jpg?v=<?= filemtime($logoPath) ?>"
             alt="Current logo"
             style="width:64px;height:64px;border-radius:12px;object-fit:cover;border:1px solid var(--line)"/>
      <?php else: ?>
        <div style="width:64px;height:64px;border-radius:12px;background:var(--primary-soft);display:flex;align-items:center;justify-content:center;font-size:24px;border:1px solid var(--line)">🏫</div>
      <?php endif; ?>
      <div>
        <strong>School Logo</strong>
        <p style="font-size:13px;color:var(--ink-faint)">Upload to replace (JPG/PNG, max 2 MB)</p>
        <?php if (!file_exists($logoPath)): ?>
          <p style="font-size:12px;color:var(--error)">⚠ No logo found at assets/images/logo.jpg</p>
        <?php endif; ?>
      </div>
    </div>
    <input type="file" name="school_logo" accept=".jpg,.jpeg,.png" style="font-size:14px"/>
  </div>

  <!-- Academic Settings -->
  <div class="form-section">
    <div class="form-section-title">📚 Academic Settings</div>
    <div class="form-row">
      <div class="form-group"><label>Current Academic Year<input name="current_academic_year" value="<?= e($s('current_academic_year','2026/2027')) ?>"/></label></div>
      <div class="form-group"><label>Passing Grade (%)<input type="number" name="passing_grade" value="<?= e($s('passing_grade','70')) ?>" min="0" max="100"/></label></div>
    </div>
  </div>

  <!-- Admissions -->
  <div class="form-section">
    <div class="form-section-title">📋 Admissions</div>
    <div class="form-row">
      <div class="form-group">
        <label>Admissions Open
          <select name="admission_open">
            <option value="1" <?= $s('admission_open','1')==='1'?'selected':'' ?>>Yes</option>
            <option value="0" <?= $s('admission_open','1')==='0'?'selected':'' ?>>No</option>
          </select>
        </label>
      </div>
      <div class="form-group"><label>Admission Year<input name="admission_year" value="<?= e($s('admission_year','2026/2027')) ?>"/></label></div>
    </div>
  </div>

  <!-- Finance -->
  <div class="form-section">
    <div class="form-section-title">💰 Finance</div>
    <div class="form-row">
      <div class="form-group">
        <label>Primary Currency
          <select name="currency_primary">
            <option value="LRD" <?= $s('currency_primary','LRD')==='LRD'?'selected':'' ?>>LRD (Liberian Dollar)</option>
            <option value="USD" <?= $s('currency_primary','LRD')==='USD'?'selected':'' ?>>USD (US Dollar)</option>
          </select>
        </label>
      </div>
      <div class="form-group">
        <label>Secondary Currency
          <select name="currency_secondary">
            <option value="USD" <?= $s('currency_secondary','USD')==='USD'?'selected':'' ?>>USD</option>
            <option value="LRD" <?= $s('currency_secondary','USD')==='LRD'?'selected':'' ?>>LRD</option>
          </select>
        </label>
      </div>
    </div>
  </div>

  <!-- Website CMS -->
  <div class="form-section">
    <div class="form-section-title">🌐 Website Content</div>
    <div class="form-row full"><div class="form-group"><label>Homepage Hero Headline<input name="hero_headline" value="<?= e($s('hero_headline','Building Knowledge, Character and a Better Future.')) ?>"/></label></div></div>
    <div class="form-row full"><div class="form-group"><label>Hero Sub-text<textarea name="hero_subtext" rows="2"><?= e($s('hero_subtext')) ?></textarea></label></div></div>
    <div class="form-row full"><div class="form-group"><label>Welcome Message<textarea name="welcome_message" rows="3"><?= e($s('welcome_message')) ?></textarea></label></div></div>
    <div class="form-row">
      <div class="form-group"><label>Stats — Students<input name="stats_students" value="<?= e($s('stats_students','1,240+')) ?>"/></label></div>
      <div class="form-group"><label>Stats — Teachers<input name="stats_teachers" value="<?= e($s('stats_teachers','48')) ?>"/></label></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Stats — Grade Levels<input name="stats_grades" value="<?= e($s('stats_grades','14')) ?>"/></label></div>
      <div class="form-group"><label>Stats — Years of Excellence<input name="stats_years" value="<?= e($s('stats_years','39')) ?>"/></label></div>
    </div>
  </div>

  <div style="display:flex;justify-content:flex-end;gap:10px">
    <button type="submit" class="button button-primary">💾 Save All Settings</button>
  </div>
</form>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
