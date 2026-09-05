<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'Apply for Admission';
$activeNav = 'admissions';
$ay = currentAcademicYearName();
include __DIR__.'/includes/header.php';
?>
<main class="inner-page">
  <div class="container inner-hero">
    <div class="eyebrow">Online Application <span></span></div>
    <h1>Apply to <em>Karn High School</em></h1>
    <p>Complete the online application form below. You will receive an application number immediately upon submission. The process takes about 10 minutes.</p>
  </div>

  <div class="container" style="padding-bottom:80px;max-width:760px">
    <!-- Steps overview -->
    <div style="display:flex;gap:0;border:1px solid var(--line);border-radius:var(--radius);overflow:hidden;margin-bottom:32px">
      <?php
      $steps = ['Applicant','Guardian','Education','Grade','Documents','Review','Submit'];
      foreach ($steps as $i => $s):
      ?>
      <div style="flex:1;padding:12px 8px;text-align:center;border-right:1px solid var(--line);background:<?=$i===0?'var(--primary-soft)':'var(--surface)'?>;<?=$i===count($steps)-1?'border-right:none':''?>">
        <div style="width:24px;height:24px;border-radius:50%;background:<?=$i===0?'var(--primary)':'var(--line)'?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;margin:0 auto 4px"><?=$i+1?></div>
        <div style="font-size:11px;font-weight:600;color:<?=$i===0?'var(--primary)':'var(--ink-faint)'?>"><?= $s ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Application form -->
    <form id="applyForm" method="post" action="<?= BASE_URL ?>/submit_application.php" enctype="multipart/form-data" novalidate>
      <?= csrfField() ?>
      <input type="hidden" name="source" value="apply_page"/>

      <!-- STEP 1: Applicant -->
      <div class="form-section" id="applyStep1">
        <div class="form-section-title">Step 1 — Applicant Information</div>
        <div class="form-row">
          <div class="form-group"><label>First Name *<input name="firstName" required placeholder="First name"/></label></div>
          <div class="form-group"><label>Middle Name<input name="middleName" placeholder="Optional"/></label></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Last Name *<input name="lastName" required placeholder="Last name"/></label></div>
          <div class="form-group"><label>Date of Birth *<input type="date" name="dateOfBirth" required/></label></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Gender *<select name="gender" required><option value="">Select…</option><option>Female</option><option>Male</option></select></label></div>
          <div class="form-group"><label>Nationality *<input name="nationality" value="Liberian" required/></label></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Phone Number *<input name="phone" required placeholder="+231 886 …"/></label></div>
          <div class="form-group"><label>Email Address<input type="email" name="email" placeholder="Optional"/></label></div>
        </div>
        <div class="form-row full"><div class="form-group"><label>Current Address *<input name="address" required placeholder="Community, District, County"/></label></div></div>
        <div class="form-row three">
          <div class="form-group"><label>Community<input name="community" placeholder="e.g. Karnplay"/></label></div>
          <div class="form-group"><label>County<input name="county" value="Nimba" required/></label></div>
          <div class="form-group"><label>District<input name="district" placeholder="Optional"/></label></div>
        </div>
      </div>

      <!-- STEP 2: Guardian -->
      <div class="form-section">
        <div class="form-section-title">Step 2 — Parent / Guardian Information</div>
        <div class="form-row full"><div class="form-group"><label>Full Name *<input name="guardianName" required placeholder="Parent or guardian full name"/></label></div></div>
        <div class="form-row">
          <div class="form-group"><label>Relationship *<select name="guardianRelationship" required><option value="">Select…</option><option>Mother</option><option>Father</option><option>Aunt/Uncle</option><option>Guardian</option><option>Other</option></select></label></div>
          <div class="form-group"><label>Phone Number *<input name="guardianPhone" required placeholder="+231 …"/></label></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Guardian Email<input type="email" name="guardianEmail" placeholder="Optional"/></label></div>
          <div class="form-group"><label>Emergency Contact Phone<input name="emergencyContact" placeholder="Optional"/></label></div>
        </div>
      </div>

      <!-- STEP 3: Education -->
      <div class="form-section">
        <div class="form-section-title">Step 3 — Previous Education</div>
        <div class="form-row full"><div class="form-group"><label>Previous School<input name="previousSchool" placeholder="School name (leave blank if none)"/></label></div></div>
        <div class="form-row">
          <div class="form-group"><label>Last Grade Completed<input name="lastGrade" placeholder="e.g. Grade 7"/></label></div>
          <div class="form-group"><label>Year Completed<input name="lastGradeYear" placeholder="e.g. 2025"/></label></div>
        </div>
      </div>

      <!-- STEP 4: Grade -->
      <div class="form-section">
        <div class="form-section-title">Step 4 — Grade &amp; Academic Year</div>
        <div class="form-row">
          <div class="form-group"><label>Grade Applying For *
            <select name="grade" required>
              <option value="">Select grade…</option>
              <?php
              $grades = db()->query("SELECT id,name FROM grades WHERE is_active=1 ORDER BY sequence")->fetchAll();
              foreach ($grades as $g): ?>
                <option value="<?= e($g['name']) ?>"><?= e($g['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label></div>
          <div class="form-group"><label>Academic Year *
            <select name="academicYear" required>
              <option value="<?= e($ay) ?>" selected><?= e($ay) ?></option>
            </select>
          </label></div>
        </div>
      </div>

      <!-- STEP 5: Documents -->
      <div class="form-section">
        <div class="form-section-title">Step 5 — Supporting Documents (Optional at this stage)</div>
        <p style="font-size:14px;color:var(--ink-soft);margin-bottom:16px">You may upload documents now or submit them later. Accepted formats: PDF, JPG, PNG. Max 5 MB per file.</p>
        <div class="form-row">
          <div class="form-group"><label>Previous Report Card<input type="file" name="doc_report_card" accept=".pdf,.jpg,.jpeg,.png"/></label></div>
          <div class="form-group"><label>Birth Certificate<input type="file" name="doc_birth_cert" accept=".pdf,.jpg,.jpeg,.png"/></label></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Passport Photo<input type="file" name="doc_photo" accept=".jpg,.jpeg,.png"/></label></div>
          <div class="form-group"><label>Other Document<input type="file" name="doc_other" accept=".pdf,.jpg,.jpeg,.png"/></label></div>
        </div>
      </div>

      <!-- STEP 6: Declaration -->
      <div class="form-section">
        <div class="form-section-title">Step 6 — Declaration</div>
        <p style="font-size:14px;color:var(--ink-soft);margin-bottom:16px">Please read carefully before submitting.</p>
        <div style="background:var(--bg);border-radius:var(--radius-sm);padding:16px;font-size:13.5px;line-height:1.8;color:var(--ink-soft);margin-bottom:16px">
          I declare that the information provided in this application is true and correct to the best of my knowledge. I understand that providing false information may result in rejection of the application or withdrawal of admission.
        </div>
        <label style="display:flex;align-items:center;gap:10px;font-size:14px;font-weight:600;cursor:pointer">
          <input type="checkbox" name="declaration" required style="width:16px;height:16px;accent-color:var(--primary)"/>
          I agree to the declaration above *
        </label>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:8px">
        <a href="<?= BASE_URL ?>/admissions.php" class="button button-secondary">← Back to Admissions</a>
        <button type="submit" class="button button-primary">Submit Application →</button>
      </div>
    </form>
  </div>
</main>
<?php include __DIR__.'/includes/footer.php'; ?>
