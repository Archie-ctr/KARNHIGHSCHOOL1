<?php
// ── Process POST first — BEFORE any output ────────────────────
require_once dirname(__DIR__).'/config/db.php';
requireAuth(); requireRole(['sys_admin','super_admin','principal']);
$pdo = db();
$tab = $_GET['tab'] ?? 'school';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $saveTab = $_POST['settings_tab'] ?? 'school';

    // Universal key-value saver
    $saveKeys = [];
    if ($saveTab === 'school') {
        $saveKeys = [
            'school_name','school_tagline','school_address','school_phone','school_phone2',
            'school_email','school_founded','school_motto','office_hours',
            'current_academic_year','admission_open','admission_year',
            'currency_primary','currency_secondary','passing_grade',
            'hero_headline','hero_subtext','welcome_message',
            'stats_students','stats_teachers','stats_grades','stats_years',
            'school_county','school_district',
        ];
    } elseif ($saveTab === 'academic') {
        $saveKeys = [
            'grading_a_min','grading_b_min','grading_c_min','grading_d_min',
            'grading_a_label','grading_b_label','grading_c_label','grading_d_label','grading_f_label',
            'promotion_pass_threshold','promotion_auto',
            'attendance_min_percent','attendance_late_policy',
            'exam_retake_allowed','exam_max_retakes',
            'marks_decimal_places','marks_max_default',
            'terms_per_year','exam_per_term',
        ];
    } elseif ($saveTab === 'finance') {
        $saveKeys = [
            'fee_currency_primary','fee_currency_secondary','fee_due_day',
            'fee_late_fine_enabled','fee_late_fine_amount','fee_late_fine_currency',
            'fee_receipt_prefix','fee_waiver_requires_approval',
            'fee_categories',
        ];
    } elseif ($saveTab === 'notifications') {
        $saveKeys = [
            'notify_email_enabled','notify_sms_enabled',
            'notify_on_admission','notify_on_marks','notify_on_payment',
            'notify_on_discipline','notify_sender_name','notify_sender_email',
            'notify_sms_sender_id',
        ];
    } elseif ($saveTab === 'integrations') {
        $saveKeys = [
            'smtp_host','smtp_port','smtp_user','smtp_encryption',
            'sms_provider','sms_api_key','sms_sender',
            'payment_gateway','payment_api_key','payment_mode',
            'online_exam_enabled','online_exam_duration','online_exam_randomize',
            'storage_type','storage_cloud_key',
        ];
        // Don't overwrite SMTP password if blank
        if (trim($_POST['smtp_password']??'') !== '') {
            $saveKeys[] = 'smtp_password';
        }
        if (trim($_POST['payment_secret']??'') !== '') {
            $saveKeys[] = 'payment_secret';
        }
    }
    foreach ($saveKeys as $k) {
        $v = trim($_POST[$k] ?? '');
        $pdo->prepare("INSERT INTO school_settings (setting_key,setting_value) VALUES (?,?)
                       ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")
           ->execute([$k, $v]);
    }

    // Handle logo upload (school tab only)
    if ($saveTab === 'school' && !empty($_FILES['school_logo']['name']) && $_FILES['school_logo']['error'] === UPLOAD_ERR_OK) {
        $path = uploadFile($_FILES['school_logo'], 'logos', ['jpg','jpeg','png'], 2);
        if ($path) {
            $imgDir = BASE_PATH . '/assets/images';
            if (!is_dir($imgDir)) mkdir($imgDir, 0755, true);
            $dest = $imgDir . '/logo.jpg';
            if (!copy(UPLOAD_DIR . '/' . $path, $dest)) {
                flash('warning', 'Settings saved but logo copy failed. Check folder permissions.');
            }
        }
    }

    auditLog('update','settings','school_settings',0,'','Tab: '.$saveTab);
    flash('success', 'Settings saved successfully.');
    redirect(BASE_URL . '/admin/settings.php?tab='.urlencode($saveTab));
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

<!-- Settings Tabs -->
<div class="tab-bar" style="margin-bottom:20px">
  <a href="?tab=school"        class="tab-btn <?= $tab==='school'        ?'active':'' ?>">🏫 School Profile</a>
  <a href="?tab=academic"      class="tab-btn <?= $tab==='academic'      ?'active':'' ?>">🎓 Academic</a>
  <a href="?tab=finance"       class="tab-btn <?= $tab==='finance'       ?'active':'' ?>">💰 Finance</a>
  <a href="?tab=notifications" class="tab-btn <?= $tab==='notifications' ?'active':'' ?>">📢 Notifications</a>
  <a href="?tab=integrations"  class="tab-btn <?= ($tab==='integrations'||$tab==='security_settings') ?'active':'' ?>">🔌 Integrations</a>
</div>

<?php if ($tab === 'school'): ?>
<!-- ══════════════════ SCHOOL PROFILE TAB ══════════════════ -->
<form method="post" enctype="multipart/form-data">
  <?= csrfField() ?>
  <input type="hidden" name="settings_tab" value="school"/>

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
    <button type="submit" class="button button-primary">💾 Save Settings</button>
  </div>
</form>

<?php elseif ($tab === 'academic'): ?>
<!-- ══════════════════ ACADEMIC SETTINGS TAB ══════════════════ -->
<form method="post">
  <?= csrfField() ?>
  <input type="hidden" name="settings_tab" value="academic"/>

  <!-- Grading Configuration -->
  <div class="form-section">
    <div class="form-section-title">📊 Grading Configuration</div>
    <p style="font-size:13px;color:var(--ink-soft);margin-bottom:16px">Set the minimum percentage for each grade letter.</p>
    <div class="form-row three">
      <div class="form-group"><label>A — Minimum %<input type="number" name="grading_a_min" min="0" max="100" value="<?= e($s('grading_a_min','90')) ?>"/></label></div>
      <div class="form-group"><label>A Label<input name="grading_a_label" value="<?= e($s('grading_a_label','Excellent')) ?>"/></label></div>
    </div>
    <div class="form-row three">
      <div class="form-group"><label>B — Minimum %<input type="number" name="grading_b_min" min="0" max="100" value="<?= e($s('grading_b_min','80')) ?>"/></label></div>
      <div class="form-group"><label>B Label<input name="grading_b_label" value="<?= e($s('grading_b_label','Very Good')) ?>"/></label></div>
    </div>
    <div class="form-row three">
      <div class="form-group"><label>C — Minimum %<input type="number" name="grading_c_min" min="0" max="100" value="<?= e($s('grading_c_min','70')) ?>"/></label></div>
      <div class="form-group"><label>C Label<input name="grading_c_label" value="<?= e($s('grading_c_label','Good')) ?>"/></label></div>
    </div>
    <div class="form-row three">
      <div class="form-group"><label>D — Minimum %<input type="number" name="grading_d_min" min="0" max="100" value="<?= e($s('grading_d_min','60')) ?>"/></label></div>
      <div class="form-group"><label>D Label<input name="grading_d_label" value="<?= e($s('grading_d_label','Satisfactory')) ?>"/></label></div>
    </div>
    <div class="form-row three">
      <div class="form-group"><label>F Label (below D)<input name="grading_f_label" value="<?= e($s('grading_f_label','Failing')) ?>"/></label></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Marks Decimal Places<select name="marks_decimal_places"><option value="0" <?= $s('marks_decimal_places','1')==='0'?'selected':'' ?>>0</option><option value="1" <?= $s('marks_decimal_places','1')==='1'?'selected':'' ?>>1</option><option value="2" <?= $s('marks_decimal_places','1')==='2'?'selected':'' ?>>2</option></select></label></div>
      <div class="form-group"><label>Default Max Marks<input type="number" name="marks_max_default" value="<?= e($s('marks_max_default','100')) ?>"/></label></div>
    </div>
  </div>

  <!-- Promotion Rules -->
  <div class="form-section">
    <div class="form-section-title">⬆️ Promotion Rules</div>
    <div class="form-row">
      <div class="form-group"><label>Minimum Overall % to Promote<input type="number" name="promotion_pass_threshold" min="0" max="100" value="<?= e($s('promotion_pass_threshold','50')) ?>"/></label></div>
      <div class="form-group"><label>Automatic Promotion<select name="promotion_auto"><option value="0" <?= $s('promotion_auto','0')==='0'?'selected':'' ?>>Manual (requires approval)</option><option value="1" <?= $s('promotion_auto','0')==='1'?'selected':'' ?>>Auto (when threshold met)</option></select></label></div>
    </div>
  </div>

  <!-- Attendance Settings -->
  <div class="form-section">
    <div class="form-section-title">📆 Attendance Settings</div>
    <div class="form-row">
      <div class="form-group"><label>Minimum Attendance % Required<input type="number" name="attendance_min_percent" min="0" max="100" value="<?= e($s('attendance_min_percent','75')) ?>"/></label></div>
      <div class="form-group"><label>Late Arrival Policy<select name="attendance_late_policy"><option value="late" <?= $s('attendance_late_policy','late')==='late'?'selected':'' ?>>Mark as Late</option><option value="half_absent" <?= $s('attendance_late_policy','late')==='half_absent'?'selected':'' ?>>Count as Half-Absent</option><option value="present" <?= $s('attendance_late_policy','late')==='present'?'selected':'' ?>>Count as Present</option></select></label></div>
    </div>
  </div>

  <!-- Examination Settings -->
  <div class="form-section">
    <div class="form-section-title">📝 Examination Settings</div>
    <div class="form-row">
      <div class="form-group"><label>Terms Per Year<input type="number" name="terms_per_year" min="1" max="4" value="<?= e($s('terms_per_year','2')) ?>"/></label></div>
      <div class="form-group"><label>Exams Per Term<input type="number" name="exam_per_term" min="1" max="4" value="<?= e($s('exam_per_term','1')) ?>"/></label></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Allow Exam Retakes<select name="exam_retake_allowed"><option value="0" <?= $s('exam_retake_allowed','0')==='0'?'selected':'' ?>>No</option><option value="1" <?= $s('exam_retake_allowed','0')==='1'?'selected':'' ?>>Yes</option></select></label></div>
      <div class="form-group"><label>Max Retakes Per Student<input type="number" name="exam_max_retakes" min="0" max="5" value="<?= e($s('exam_max_retakes','1')) ?>"/></label></div>
    </div>
  </div>

  <div style="display:flex;justify-content:flex-end">
    <button type="submit" class="button button-primary">💾 Save Academic Settings</button>
  </div>
</form>

<?php elseif ($tab === 'finance'): ?>
<!-- ══════════════════ FINANCE SETTINGS TAB ══════════════════ -->
<form method="post">
  <?= csrfField() ?>
  <input type="hidden" name="settings_tab" value="finance"/>

  <div class="form-section">
    <div class="form-section-title">💰 Fee & Currency Settings</div>
    <div class="form-row">
      <div class="form-group"><label>Primary Currency<input name="fee_currency_primary" placeholder="e.g. LRD" value="<?= e($s('fee_currency_primary','LRD')) ?>"/></label></div>
      <div class="form-group"><label>Secondary Currency<input name="fee_currency_secondary" placeholder="e.g. USD" value="<?= e($s('fee_currency_secondary','USD')) ?>"/></label></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Fee Due Day of Month<input type="number" name="fee_due_day" min="1" max="31" value="<?= e($s('fee_due_day','15')) ?>"/></label></div>
      <div class="form-group"><label>Receipt Number Prefix<input name="fee_receipt_prefix" placeholder="e.g. KHS-RCP" value="<?= e($s('fee_receipt_prefix','KHS-RCP')) ?>"/></label></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Late Payment Fine<select name="fee_late_fine_enabled"><option value="0" <?= $s('fee_late_fine_enabled','0')==='0'?'selected':'' ?>>Disabled</option><option value="1" <?= $s('fee_late_fine_enabled','0')==='1'?'selected':'' ?>>Enabled</option></select></label></div>
      <div class="form-group"><label>Fine Amount<input type="number" name="fee_late_fine_amount" value="<?= e($s('fee_late_fine_amount','0')) ?>"/></label></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Fee Waiver Requires Approval<select name="fee_waiver_requires_approval"><option value="1" <?= $s('fee_waiver_requires_approval','1')==='1'?'selected':'' ?>>Yes</option><option value="0" <?= $s('fee_waiver_requires_approval','1')==='0'?'selected':'' ?>>No</option></select></label></div>
    </div>
  </div>

  <div class="form-section">
    <div class="form-section-title">📋 Fee Categories</div>
    <p style="font-size:13px;color:var(--ink-soft);margin-bottom:10px">Comma-separated list of fee categories.</p>
    <div class="form-group"><label>Categories<input name="fee_categories" value="<?= e($s('fee_categories','Tuition,Registration,Examination,Sports,Library,Miscellaneous')) ?>"/></label></div>
  </div>

  <div style="display:flex;justify-content:flex-end">
    <button type="submit" class="button button-primary">💾 Save Finance Settings</button>
  </div>
</form>

<?php elseif ($tab === 'notifications'): ?>
<!-- ══════════════════ NOTIFICATIONS TAB ══════════════════ -->
<form method="post">
  <?= csrfField() ?>
  <input type="hidden" name="settings_tab" value="notifications"/>

  <div class="form-section">
    <div class="form-section-title">📢 Notification Channels</div>
    <div class="form-row">
      <div class="form-group"><label>Email Notifications<select name="notify_email_enabled"><option value="0" <?= $s('notify_email_enabled','0')==='0'?'selected':'' ?>>Disabled</option><option value="1" <?= $s('notify_email_enabled','0')==='1'?'selected':'' ?>>Enabled</option></select></label></div>
      <div class="form-group"><label>SMS Notifications<select name="notify_sms_enabled"><option value="0" <?= $s('notify_sms_enabled','0')==='0'?'selected':'' ?>>Disabled</option><option value="1" <?= $s('notify_sms_enabled','0')==='1'?'selected':'' ?>>Enabled</option></select></label></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Sender Name<input name="notify_sender_name" value="<?= e($s('notify_sender_name','KARN HIGH SCHOOL')) ?>"/></label></div>
      <div class="form-group"><label>Sender Email<input type="email" name="notify_sender_email" value="<?= e($s('notify_sender_email','noreply@karnhighschool.edu.lr')) ?>"/></label></div>
    </div>
    <div class="form-group"><label>SMS Sender ID<input name="notify_sms_sender_id" placeholder="e.g. KARNHS" maxlength="11" value="<?= e($s('notify_sms_sender_id','KARNHS')) ?>"/></label></div>
  </div>

  <div class="form-section">
    <div class="form-section-title">🔔 Notification Triggers</div>
    <?php
    $triggers = ['notify_on_admission'=>'New admission application submitted','notify_on_marks'=>'Marks submitted/approved','notify_on_payment'=>'Payment received','notify_on_discipline'=>'Discipline incident recorded'];
    foreach ($triggers as $k => $label): ?>
    <div class="form-row" style="align-items:center">
      <div class="form-group"><label><?= $label ?><select name="<?= $k ?>"><option value="0" <?= $s($k,'0')==='0'?'selected':'' ?>>Off</option><option value="email" <?= $s($k,'0')==='email'?'selected':'' ?>>Email</option><option value="sms" <?= $s($k,'0')==='sms'?'selected':'' ?>>SMS</option><option value="both" <?= $s($k,'0')==='both'?'selected':'' ?>>Email + SMS</option></select></label></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div style="display:flex;justify-content:flex-end">
    <button type="submit" class="button button-primary">💾 Save Notification Settings</button>
  </div>
</form>

<?php elseif ($tab === 'integrations'): ?>
<!-- ══════════════════ INTEGRATIONS TAB ══════════════════ -->
<form method="post">
  <?= csrfField() ?>
  <input type="hidden" name="settings_tab" value="integrations"/>

  <!-- SMTP Email -->
  <div class="form-section">
    <div class="form-section-title">✉️ Email Configuration (SMTP)</div>
    <div class="form-row">
      <div class="form-group"><label>SMTP Host<input name="smtp_host" placeholder="smtp.gmail.com" value="<?= e($s('smtp_host')) ?>"/></label></div>
      <div class="form-group"><label>SMTP Port<input type="number" name="smtp_port" placeholder="587" value="<?= e($s('smtp_port','587')) ?>"/></label></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>SMTP Username<input name="smtp_user" placeholder="your@email.com" value="<?= e($s('smtp_user')) ?>"/></label></div>
      <div class="form-group"><label>SMTP Password<input type="password" name="smtp_password" placeholder="Leave blank to keep current"/></label></div>
    </div>
    <div class="form-group"><label>Encryption<select name="smtp_encryption"><option value="tls" <?= $s('smtp_encryption','tls')==='tls'?'selected':'' ?>>TLS</option><option value="ssl" <?= $s('smtp_encryption','tls')==='ssl'?'selected':'' ?>>SSL</option><option value="none" <?= $s('smtp_encryption','tls')==='none'?'selected':'' ?>>None</option></select></label></div>
  </div>

  <!-- SMS -->
  <div class="form-section">
    <div class="form-section-title">📱 SMS Configuration</div>
    <div class="form-row">
      <div class="form-group"><label>SMS Provider<input name="sms_provider" placeholder="e.g. Twilio, AfricasTalking" value="<?= e($s('sms_provider')) ?>"/></label></div>
      <div class="form-group"><label>SMS Sender ID<input name="sms_sender" maxlength="11" value="<?= e($s('sms_sender','KARNHS')) ?>"/></label></div>
    </div>
    <div class="form-group"><label>SMS API Key<input name="sms_api_key" placeholder="Leave blank to keep current" value="<?= e($s('sms_api_key')) ?>"/></label></div>
  </div>

  <!-- Payment Gateway -->
  <div class="form-section">
    <div class="form-section-title">💳 Payment Gateway</div>
    <div class="form-row">
      <div class="form-group"><label>Gateway<select name="payment_gateway"><option value="">None</option><option value="flutterwave" <?= $s('payment_gateway')==='flutterwave'?'selected':'' ?>>Flutterwave</option><option value="paystack" <?= $s('payment_gateway')==='paystack'?'selected':'' ?>>Paystack</option><option value="stripe" <?= $s('payment_gateway')==='stripe'?'selected':'' ?>>Stripe</option><option value="custom" <?= $s('payment_gateway')==='custom'?'selected':'' ?>>Custom</option></select></label></div>
      <div class="form-group"><label>Mode<select name="payment_mode"><option value="test" <?= $s('payment_mode','test')==='test'?'selected':'' ?>>Test / Sandbox</option><option value="live" <?= $s('payment_mode','test')==='live'?'selected':'' ?>>Live</option></select></label></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Public/API Key<input name="payment_api_key" value="<?= e($s('payment_api_key')) ?>"/></label></div>
      <div class="form-group"><label>Secret Key<input type="password" name="payment_secret" placeholder="Leave blank to keep current"/></label></div>
    </div>
  </div>

  <!-- Online Examination -->
  <div class="form-section">
    <div class="form-section-title">📝 Online Examination</div>
    <div class="form-row">
      <div class="form-group"><label>Online Exams Enabled<select name="online_exam_enabled"><option value="0" <?= $s('online_exam_enabled','0')==='0'?'selected':'' ?>>Disabled</option><option value="1" <?= $s('online_exam_enabled','0')==='1'?'selected':'' ?>>Enabled</option></select></label></div>
      <div class="form-group"><label>Default Duration (minutes)<input type="number" name="online_exam_duration" value="<?= e($s('online_exam_duration','60')) ?>"/></label></div>
    </div>
    <div class="form-group"><label>Randomize Questions<select name="online_exam_randomize"><option value="0" <?= $s('online_exam_randomize','1')==='0'?'selected':'' ?>>No</option><option value="1" <?= $s('online_exam_randomize','1')==='1'?'selected':'' ?>>Yes</option></select></label></div>
  </div>

  <!-- Cloud Storage -->
  <div class="form-section">
    <div class="form-section-title">☁️ Cloud Storage</div>
    <div class="form-row">
      <div class="form-group"><label>Storage Type<select name="storage_type"><option value="local" <?= $s('storage_type','local')==='local'?'selected':'' ?>>Local Server</option><option value="s3" <?= $s('storage_type','local')==='s3'?'selected':'' ?>>Amazon S3</option><option value="gcs" <?= $s('storage_type','local')==='gcs'?'selected':'' ?>>Google Cloud Storage</option></select></label></div>
      <div class="form-group"><label>Cloud API Key / Bucket<input name="storage_cloud_key" placeholder="Leave blank for local storage" value="<?= e($s('storage_cloud_key')) ?>"/></label></div>
    </div>
  </div>

  <div style="display:flex;justify-content:flex-end">
    <button type="submit" class="button button-primary">💾 Save Integration Settings</button>
  </div>
</form>

<?php endif; ?>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
