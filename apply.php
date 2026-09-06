<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'Apply for Admission';
$activeNav = 'admissions';
$metaDesc  = 'Apply online to Karn High School. Complete the admission form in minutes and receive your application number instantly.';

try {
    $ay     = currentAcademicYearName();
    $grades = db()->query("SELECT id, name FROM grades WHERE is_active=1 ORDER BY sequence")->fetchAll();
} catch (Throwable $e) {
    $ay     = '2026/2027';
    $grades = [];
}

$steps = [
    ['n'=>'01', 'label'=>'Applicant',  'desc'=>'Personal details'],
    ['n'=>'02', 'label'=>'Guardian',   'desc'=>'Parent / guardian'],
    ['n'=>'03', 'label'=>'Education',  'desc'=>'Previous schooling'],
    ['n'=>'04', 'label'=>'Grade',      'desc'=>'Grade & year'],
    ['n'=>'05', 'label'=>'Documents',  'desc'=>'Upload files'],
    ['n'=>'06', 'label'=>'Declare',    'desc'=>'Review & sign'],
];

include __DIR__.'/includes/header.php';
?>

<!-- Page Hero -->
<section class="page-hero">
  <div class="wrap ph-body">
    <nav class="bc" aria-label="Breadcrumb">
      <a href="<?= BASE_URL ?>/">Home</a>
      <span class="bc-sep">/</span>
      <a href="<?= BASE_URL ?>/admissions.php">Admissions</a>
      <span class="bc-sep">/</span>
      <span>Apply</span>
    </nav>
    <div class="eyebrow inv">Online Application · <?= e($ay) ?></div>
    <h1 class="ph-h">Apply to<br><em>Karn High School.</em></h1>
    <p class="ph-lead">Complete the form below in about 10 minutes. You will receive your unique application number immediately upon submission.</p>
  </div>
</section>

<!-- Form Section -->
<section class="sec bg-white">
  <div class="wrap" style="max-width:820px">

    <!-- Step Tracker -->
    <div class="apply-steps" aria-label="Application steps" style="margin-bottom:var(--sp10)">
      <?php foreach ($steps as $i => $s): ?>
      <div class="apply-step <?= $i===0 ? 'active' : '' ?>" id="tracker-<?= $i+1 ?>" aria-current="<?= $i===0?'step':'false' ?>">
        <div class="apply-step-n"><?= $s['n'] ?></div>
        <div class="apply-step-body">
          <span class="apply-step-lbl"><?= $s['label'] ?></span>
          <span class="apply-step-desc"><?= $s['desc'] ?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Flash Messages -->
    <?php
    $flash = $_SESSION['flash'] ?? null;
    if ($flash) { unset($_SESSION['flash']); }
    if ($flash && $flash['type'] === 'error'): ?>
    <div class="alert alert-err" role="alert"><?= e($flash['msg']) ?></div>
    <?php endif; ?>

    <!-- Application Form -->
    <form id="applyForm" method="post" action="<?= BASE_URL ?>/submit_application.php"
          enctype="multipart/form-data" novalidate>
      <?= csrfField() ?>
      <input type="hidden" name="source" value="apply_page" />

      <!-- ── STEP 1: Applicant ─────────────────────────────── -->
      <div class="apply-panel" id="panel-1">
        <div class="apply-panel-hd">
          <span class="apply-panel-n">01</span>
          <div>
            <h2 class="apply-panel-h">Applicant Information</h2>
            <p class="apply-panel-sub">Tell us about the student applying for admission.</p>
          </div>
        </div>
        <div class="apply-panel-body">

          <div class="form-row2">
            <div class="form-grp">
              <label class="form-lbl" for="firstName">First Name <span class="req">*</span></label>
              <input class="form-ctrl" type="text" id="firstName" name="firstName" required
                     placeholder="First name" autocomplete="given-name" />
            </div>
            <div class="form-grp">
              <label class="form-lbl" for="middleName">Middle Name</label>
              <input class="form-ctrl" type="text" id="middleName" name="middleName"
                     placeholder="Optional" />
            </div>
          </div>

          <div class="form-row2">
            <div class="form-grp">
              <label class="form-lbl" for="lastName">Last Name <span class="req">*</span></label>
              <input class="form-ctrl" type="text" id="lastName" name="lastName" required
                     placeholder="Last name" autocomplete="family-name" />
            </div>
            <div class="form-grp">
              <label class="form-lbl" for="dateOfBirth">Date of Birth <span class="req">*</span></label>
              <input class="form-ctrl" type="date" id="dateOfBirth" name="dateOfBirth" required />
            </div>
          </div>

          <div class="form-row2">
            <div class="form-grp">
              <label class="form-lbl" for="gender">Gender <span class="req">*</span></label>
              <select class="form-ctrl" id="gender" name="gender" required>
                <option value="">Select gender…</option>
                <option value="Female">Female</option>
                <option value="Male">Male</option>
              </select>
            </div>
            <div class="form-grp">
              <label class="form-lbl" for="nationality">Nationality <span class="req">*</span></label>
              <input class="form-ctrl" type="text" id="nationality" name="nationality"
                     value="Liberian" required />
            </div>
          </div>

          <div class="form-row2">
            <div class="form-grp">
              <label class="form-lbl" for="phone">Phone Number <span class="req">*</span></label>
              <input class="form-ctrl" type="tel" id="phone" name="phone" required
                     placeholder="+231 886 …" autocomplete="tel" />
            </div>
            <div class="form-grp">
              <label class="form-lbl" for="email">Email Address</label>
              <input class="form-ctrl" type="email" id="email" name="email"
                     placeholder="Optional" autocomplete="email" />
            </div>
          </div>

          <div class="form-grp">
            <label class="form-lbl" for="address">Current Address <span class="req">*</span></label>
            <input class="form-ctrl" type="text" id="address" name="address" required
                   placeholder="Community, District, County" autocomplete="street-address" />
          </div>

          <div class="form-row3">
            <div class="form-grp">
              <label class="form-lbl" for="community">Community</label>
              <input class="form-ctrl" type="text" id="community" name="community"
                     placeholder="e.g. Karnplay" />
            </div>
            <div class="form-grp">
              <label class="form-lbl" for="county">County <span class="req">*</span></label>
              <input class="form-ctrl" type="text" id="county" name="county"
                     value="Nimba" required />
            </div>
            <div class="form-grp">
              <label class="form-lbl" for="district">District</label>
              <input class="form-ctrl" type="text" id="district" name="district"
                     placeholder="Optional" />
            </div>
          </div>

          <div class="apply-nav">
            <span></span>
            <button type="button" class="btn btn-primary" onclick="goStep(2)">
              Next: Guardian Info <span aria-hidden="true">→</span>
            </button>
          </div>
        </div>
      </div>

      <!-- ── STEP 2: Guardian ──────────────────────────────── -->
      <div class="apply-panel" id="panel-2" hidden>
        <div class="apply-panel-hd">
          <span class="apply-panel-n">02</span>
          <div>
            <h2 class="apply-panel-h">Parent / Guardian Information</h2>
            <p class="apply-panel-sub">Contact details for the student's parent or primary guardian.</p>
          </div>
        </div>
        <div class="apply-panel-body">

          <div class="form-grp">
            <label class="form-lbl" for="guardianName">Guardian Full Name <span class="req">*</span></label>
            <input class="form-ctrl" type="text" id="guardianName" name="guardianName" required
                   placeholder="Full name" />
          </div>

          <div class="form-row2">
            <div class="form-grp">
              <label class="form-lbl" for="guardianRelationship">Relationship <span class="req">*</span></label>
              <select class="form-ctrl" id="guardianRelationship" name="guardianRelationship" required>
                <option value="">Select…</option>
                <option>Mother</option>
                <option>Father</option>
                <option>Aunt/Uncle</option>
                <option>Guardian</option>
                <option>Other</option>
              </select>
            </div>
            <div class="form-grp">
              <label class="form-lbl" for="guardianPhone">Phone Number <span class="req">*</span></label>
              <input class="form-ctrl" type="tel" id="guardianPhone" name="guardianPhone" required
                     placeholder="+231 …" />
            </div>
          </div>

          <div class="form-row2">
            <div class="form-grp">
              <label class="form-lbl" for="guardianEmail">Guardian Email</label>
              <input class="form-ctrl" type="email" id="guardianEmail" name="guardianEmail"
                     placeholder="Optional" />
            </div>
            <div class="form-grp">
              <label class="form-lbl" for="emergencyContact">Emergency Contact Phone</label>
              <input class="form-ctrl" type="tel" id="emergencyContact" name="emergencyContact"
                     placeholder="Optional" />
            </div>
          </div>

          <div class="apply-nav">
            <button type="button" class="btn btn-outline" onclick="goStep(1)">← Back</button>
            <button type="button" class="btn btn-primary" onclick="goStep(3)">
              Next: Education <span aria-hidden="true">→</span>
            </button>
          </div>
        </div>
      </div>

      <!-- ── STEP 3: Education ─────────────────────────────── -->
      <div class="apply-panel" id="panel-3" hidden>
        <div class="apply-panel-hd">
          <span class="apply-panel-n">03</span>
          <div>
            <h2 class="apply-panel-h">Previous Education</h2>
            <p class="apply-panel-sub">Tell us about the student's previous schooling. Leave blank if not applicable.</p>
          </div>
        </div>
        <div class="apply-panel-body">

          <div class="form-grp">
            <label class="form-lbl" for="previousSchool">Previous School</label>
            <input class="form-ctrl" type="text" id="previousSchool" name="previousSchool"
                   placeholder="School name (leave blank if none)" />
          </div>

          <div class="form-row2">
            <div class="form-grp">
              <label class="form-lbl" for="lastGrade">Last Grade Completed</label>
              <input class="form-ctrl" type="text" id="lastGrade" name="lastGrade"
                     placeholder="e.g. Grade 7" />
            </div>
            <div class="form-grp">
              <label class="form-lbl" for="lastGradeYear">Year Completed</label>
              <input class="form-ctrl" type="text" id="lastGradeYear" name="lastGradeYear"
                     placeholder="e.g. 2025" inputmode="numeric" />
            </div>
          </div>

          <div class="apply-nav">
            <button type="button" class="btn btn-outline" onclick="goStep(2)">← Back</button>
            <button type="button" class="btn btn-primary" onclick="goStep(4)">
              Next: Grade Selection <span aria-hidden="true">→</span>
            </button>
          </div>
        </div>
      </div>

      <!-- ── STEP 4: Grade ─────────────────────────────────── -->
      <div class="apply-panel" id="panel-4" hidden>
        <div class="apply-panel-hd">
          <span class="apply-panel-n">04</span>
          <div>
            <h2 class="apply-panel-h">Grade &amp; Academic Year</h2>
            <p class="apply-panel-sub">Select the grade you are applying for this academic year.</p>
          </div>
        </div>
        <div class="apply-panel-body">

          <div class="form-row2">
            <div class="form-grp">
              <label class="form-lbl" for="grade">Grade Applying For <span class="req">*</span></label>
              <select class="form-ctrl" id="grade" name="grade" required>
                <option value="">Select grade…</option>
                <?php if (!empty($grades)): ?>
                  <?php foreach ($grades as $g): ?>
                  <option value="<?= e($g['name']) ?>"><?= e($g['name']) ?></option>
                  <?php endforeach; ?>
                <?php else: ?>
                  <?php foreach (['ABC/KG','Grade 1','Grade 2','Grade 3','Grade 4','Grade 5','Grade 6','Grade 7','Grade 8','Grade 9','Grade 10','Grade 11','Grade 12'] as $g): ?>
                  <option value="<?= $g ?>"><?= $g ?></option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
            </div>
            <div class="form-grp">
              <label class="form-lbl" for="academicYear">Academic Year <span class="req">*</span></label>
              <select class="form-ctrl" id="academicYear" name="academicYear" required>
                <option value="<?= e($ay) ?>" selected><?= e($ay) ?></option>
              </select>
            </div>
          </div>

          <div class="apply-nav">
            <button type="button" class="btn btn-outline" onclick="goStep(3)">← Back</button>
            <button type="button" class="btn btn-primary" onclick="goStep(5)">
              Next: Documents <span aria-hidden="true">→</span>
            </button>
          </div>
        </div>
      </div>

      <!-- ── STEP 5: Documents ─────────────────────────────── -->
      <div class="apply-panel" id="panel-5" hidden>
        <div class="apply-panel-hd">
          <span class="apply-panel-n">05</span>
          <div>
            <h2 class="apply-panel-h">Supporting Documents</h2>
            <p class="apply-panel-sub">Upload your documents now or bring them when you visit the school. PDF, JPG, PNG accepted · Max 5 MB per file.</p>
          </div>
        </div>
        <div class="apply-panel-body">

          <div class="doc-grid">
            <div class="doc-upload">
              <label class="doc-upload-lbl" for="doc_report_card">
                <span class="doc-ico">📋</span>
                <strong>Previous Report Card</strong>
                <span>PDF, JPG or PNG</span>
                <input type="file" id="doc_report_card" name="doc_report_card"
                       accept=".pdf,.jpg,.jpeg,.png" class="doc-file-input"
                       onchange="docPreview(this,'prev-report')" />
              </label>
              <p class="doc-name" id="prev-report">No file chosen</p>
            </div>
            <div class="doc-upload">
              <label class="doc-upload-lbl" for="doc_birth_cert">
                <span class="doc-ico">📄</span>
                <strong>Birth Certificate</strong>
                <span>PDF, JPG or PNG</span>
                <input type="file" id="doc_birth_cert" name="doc_birth_cert"
                       accept=".pdf,.jpg,.jpeg,.png" class="doc-file-input"
                       onchange="docPreview(this,'prev-birth')" />
              </label>
              <p class="doc-name" id="prev-birth">No file chosen</p>
            </div>
            <div class="doc-upload">
              <label class="doc-upload-lbl" for="doc_photo">
                <span class="doc-ico">🖼️</span>
                <strong>Passport Photo</strong>
                <span>JPG or PNG</span>
                <input type="file" id="doc_photo" name="doc_photo"
                       accept=".jpg,.jpeg,.png" class="doc-file-input"
                       onchange="docPreview(this,'prev-photo')" />
              </label>
              <p class="doc-name" id="prev-photo">No file chosen</p>
            </div>
            <div class="doc-upload">
              <label class="doc-upload-lbl" for="doc_other">
                <span class="doc-ico">📎</span>
                <strong>Other Document</strong>
                <span>PDF, JPG or PNG</span>
                <input type="file" id="doc_other" name="doc_other"
                       accept=".pdf,.jpg,.jpeg,.png" class="doc-file-input"
                       onchange="docPreview(this,'prev-other')" />
              </label>
              <p class="doc-name" id="prev-other">No file chosen</p>
            </div>
          </div>

          <div class="apply-nav">
            <button type="button" class="btn btn-outline" onclick="goStep(4)">← Back</button>
            <button type="button" class="btn btn-primary" onclick="goStep(6)">
              Next: Declaration <span aria-hidden="true">→</span>
            </button>
          </div>
        </div>
      </div>

      <!-- ── STEP 6: Declaration ───────────────────────────── -->
      <div class="apply-panel" id="panel-6" hidden>
        <div class="apply-panel-hd">
          <span class="apply-panel-n">06</span>
          <div>
            <h2 class="apply-panel-h">Review &amp; Declaration</h2>
            <p class="apply-panel-sub">Please read the declaration below and confirm before submitting.</p>
          </div>
        </div>
        <div class="apply-panel-body">

          <!-- Summary -->
          <div id="apply-summary" style="background:var(--bg2);border-radius:var(--r2);padding:var(--sp5);margin-bottom:var(--sp6)">
            <h3 style="font-size:.88rem;font-weight:700;color:var(--ink);margin-bottom:var(--sp4)">Application Summary</h3>
            <div id="summary-rows" style="display:grid;grid-template-columns:1fr 1fr;gap:6px 20px;font-size:.84rem"></div>
            <button type="button" onclick="goStep(1)" class="lnk" style="margin-top:var(--sp4);font-size:.82rem">Edit information</button>
          </div>

          <!-- Declaration box -->
          <div style="background:var(--red-p);border:1.5px solid var(--red-m);border-radius:var(--r2);padding:var(--sp5);margin-bottom:var(--sp5)">
            <h3 style="font-size:.9rem;font-weight:700;color:var(--red);margin-bottom:var(--sp3)">📋 Declaration</h3>
            <p style="font-size:.87rem;line-height:1.82;color:var(--ink2)">I declare that the information provided in this application is true, accurate and complete to the best of my knowledge. I understand that submitting false information may result in the rejection of this application or the withdrawal of any admission offer made on the basis of it.</p>
          </div>

          <div class="form-grp" style="margin-bottom:var(--sp6)">
            <label style="display:flex;align-items:flex-start;gap:var(--sp3);font-size:.93rem;font-weight:600;color:var(--ink);cursor:pointer">
              <input type="checkbox" name="declaration" required
                     style="width:18px;height:18px;flex-shrink:0;margin-top:2px;accent-color:var(--red)" />
              I have read and agree to the declaration above. <span class="req">*</span>
            </label>
          </div>

          <div class="form-grp" style="margin-bottom:var(--sp6)">
            <label style="display:flex;align-items:flex-start;gap:var(--sp3);font-size:.93rem;font-weight:600;color:var(--ink);cursor:pointer">
              <input type="checkbox" name="privacy_agree" required
                     style="width:18px;height:18px;flex-shrink:0;margin-top:2px;accent-color:var(--red)" />
              I agree to the <a href="<?= BASE_URL ?>/privacy.php" target="_blank" style="color:var(--red)">Privacy Policy</a> and <a href="<?= BASE_URL ?>/terms.php" target="_blank" style="color:var(--red)">Terms &amp; Conditions</a>. <span class="req">*</span>
            </label>
          </div>

          <div class="apply-nav">
            <button type="button" class="btn btn-outline" onclick="goStep(5)">← Back</button>
            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
              Submit Application →
            </button>
          </div>
        </div>
      </div>

    </form>

    <!-- Help box -->
    <div style="margin-top:var(--sp10);background:var(--bg2);border-radius:var(--r3);padding:var(--sp6);display:flex;gap:var(--sp5);align-items:flex-start">
      <div style="font-size:1.4rem;flex-shrink:0">💬</div>
      <div>
        <h3 style="font-size:.95rem;font-weight:700;color:var(--ink);margin-bottom:4px">Need help with your application?</h3>
        <p style="font-size:.87rem;color:var(--ink3);line-height:1.72">Contact our admissions team at <strong>+231 886 417 711</strong> or visit the school office Monday–Friday, 8:00 AM – 4:30 PM.</p>
        <a href="<?= BASE_URL ?>/contact.php" class="lnk" style="font-size:.85rem;margin-top:var(--sp3)">Contact admissions</a>
      </div>
    </div>

  </div>
</section>

<style>
/* ── Apply Step Tracker ─────────────────────────── */
.apply-steps{display:flex;gap:0;overflow-x:auto;border:1.5px solid var(--bdr);border-radius:var(--r3);overflow:hidden}
.apply-step{flex:1;min-width:0;display:flex;align-items:center;gap:var(--sp3);padding:14px var(--sp4);border-right:1px solid var(--bdr);background:var(--bg2);transition:background var(--t2);cursor:default}
.apply-step:last-child{border-right:none}
.apply-step.active{background:#fff;box-shadow:inset 0 -3px 0 var(--red)}
.apply-step.done{background:var(--grn-p)}
.apply-step-n{width:28px;height:28px;border-radius:50%;background:var(--bdr);color:var(--ink3);display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:800;flex-shrink:0;transition:all var(--t2)}
.apply-step.active .apply-step-n{background:var(--red);color:#fff}
.apply-step.done   .apply-step-n{background:var(--grn);color:#fff}
.apply-step-body{min-width:0}
.apply-step-lbl{display:block;font-size:.83rem;font-weight:700;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.apply-step-desc{display:block;font-size:.7rem;color:var(--ink4);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
@media(max-width:600px){
  .apply-step-desc{display:none}
  .apply-step{padding:12px var(--sp3);gap:var(--sp2)}
  .apply-step-lbl{font-size:.74rem}
}
@media(max-width:420px){.apply-step-lbl{display:none}}

/* ── Panel ──────────────────────────────────────── */
.apply-panel{background:#fff;border:1.5px solid var(--bdr);border-radius:var(--r3);overflow:hidden;margin-bottom:var(--sp4)}
.apply-panel[hidden]{display:none}
.apply-panel-hd{display:flex;align-items:center;gap:var(--sp4);padding:var(--sp5) var(--sp6);background:var(--bg2);border-bottom:1px solid var(--bdr)}
.apply-panel-n{width:40px;height:40px;border-radius:50%;background:var(--red);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:800;flex-shrink:0}
.apply-panel-h{font-size:1.05rem;font-weight:700;color:var(--ink);line-height:1.2}
.apply-panel-sub{font-size:.82rem;color:var(--ink3);margin-top:2px}
.apply-panel-body{padding:var(--sp6)}

/* ── Form layout ────────────────────────────────── */
.form-lbl{display:block;font-size:.81rem;font-weight:700;color:var(--ink2);margin-bottom:var(--sp2);letter-spacing:.01em}
.req{color:var(--red)}
.form-row2{display:grid;grid-template-columns:1fr 1fr;gap:var(--sp5)}
.form-row3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:var(--sp5)}
@media(max-width:600px){
  .form-row2,.form-row3{grid-template-columns:1fr}
}

/* ── Document upload ────────────────────────────── */
.doc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:var(--sp4);margin-bottom:var(--sp6)}
.doc-upload-lbl{display:flex;flex-direction:column;align-items:center;gap:var(--sp2);padding:var(--sp5);border:2px dashed var(--bdr);border-radius:var(--r2);cursor:pointer;text-align:center;transition:all var(--t2);background:var(--bg)}
.doc-upload-lbl:hover{border-color:var(--red);background:var(--red-p)}
.doc-ico{font-size:1.8rem}
.doc-upload-lbl strong{font-size:.87rem;font-weight:700;color:var(--ink)}
.doc-upload-lbl span{font-size:.74rem;color:var(--ink4)}
.doc-file-input{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
.doc-name{font-size:.74rem;color:var(--ink3);text-align:center;margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

/* ── Nav row ────────────────────────────────────── */
.apply-nav{display:flex;align-items:center;justify-content:space-between;gap:var(--sp3);padding-top:var(--sp5);border-top:1px solid var(--bdr);margin-top:var(--sp4)}
@media(max-width:480px){.apply-nav{flex-direction:column-reverse}.apply-nav .btn{width:100%;justify-content:center}}
</style>

<script>
// Step navigation
const TOTAL = 6;
let cur = 1;

function goStep(n) {
  if (n < 1 || n > TOTAL) return;
  // Validate step being left
  if (n > cur) {
    const panel = document.getElementById('panel-' + cur);
    const required = panel ? panel.querySelectorAll('[required]') : [];
    let ok = true;
    required.forEach(el => {
      if (!el.checkValidity()) {
        el.reportValidity();
        ok = false;
      }
    });
    if (!ok) return;
  }

  // Hide current, show new
  document.getElementById('panel-' + cur).setAttribute('hidden', '');
  document.getElementById('panel-' + n).removeAttribute('hidden');

  // Update tracker
  const trackers = document.querySelectorAll('.apply-step');
  trackers.forEach((t, i) => {
    t.classList.remove('active', 'done');
    if (i + 1 === n)    t.classList.add('active');
    if (i + 1 < n)      t.classList.add('done');
    t.setAttribute('aria-current', i + 1 === n ? 'step' : 'false');
  });

  cur = n;
  // Build summary on step 6
  if (n === 6) buildSummary();
  window.scrollTo({top: document.getElementById('panel-' + n).offsetTop - 90, behavior: 'smooth'});
}

function docPreview(input, previewId) {
  const el = document.getElementById(previewId);
  el.textContent = input.files[0] ? input.files[0].name : 'No file chosen';
}

function buildSummary() {
  const rows = document.getElementById('summary-rows');
  if (!rows) return;
  const get = id => {
    const el = document.getElementById(id);
    return el ? (el.type === 'select-one' ? el.options[el.selectedIndex]?.text || '' : el.value) : '';
  };
  const fields = [
    ['Applicant',    [get('firstName'), get('middleName'), get('lastName')].filter(Boolean).join(' ')],
    ['Date of Birth', get('dateOfBirth')],
    ['Gender',        get('gender')],
    ['Phone',         get('phone')],
    ['Guardian',      get('guardianName')],
    ['G. Phone',      get('guardianPhone')],
    ['Previous School', get('previousSchool') || '—'],
    ['Grade Applying', get('grade')],
  ];
  rows.innerHTML = fields.map(([k,v]) =>
    `<div><span style="color:var(--ink3);font-size:.78rem;font-weight:600">${k}</span><br><strong style="font-size:.87rem">${v || '—'}</strong></div>`
  ).join('');
}

// Prevent accidental submission on enter key in inputs (go next instead)
document.getElementById('applyForm').addEventListener('keydown', function(e) {
  if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && e.target.type !== 'submit') {
    e.preventDefault();
    if (cur < TOTAL) goStep(cur + 1);
  }
});
</script>

<?php include __DIR__.'/includes/footer.php'; ?>
