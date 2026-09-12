<?php
// ============================================================
// Letter of Recommendation — KARN HIGH SCHOOL
// URL: /letters/recommendation_letter.php?student_id=N
//      &purpose=college|employment|scholarship|transfer|general
// ============================================================
require_once dirname(__DIR__).'/config/db.php';
require_once dirname(__DIR__).'/includes/doc_verify_helper.php';
requireAuth();
requireStaff(); // only staff can issue recommendations

$pdo    = db();
$stdId  = (int)($_GET['student_id'] ?? 0);
if (!$stdId) { http_response_code(400); die('Invalid request.'); }

$purpose = $_GET['purpose'] ?? 'general';
$validPurposes = ['college','employment','scholarship','transfer','general'];
if (!in_array($purpose, $validPurposes)) $purpose = 'general';

// ── Student record ────────────────────────────────────────────
$student = $pdo->query(
    "SELECT s.*, g.name grade_name, g.sequence grade_seq, c.name class_name
     FROM students s
     LEFT JOIN grades  g ON g.id = s.current_grade_id
     LEFT JOIN classes c ON c.id = s.current_class_id
     WHERE s.id = $stdId LIMIT 1"
)->fetch();
if (!$student) { http_response_code(404); die('Student not found.'); }

$fullName = trim(($student['first_name']??'').' '.($student['middle_name']??'').' '.($student['last_name']??''));
$firstName = $student['first_name'] ?? 'the student';
$pronoun   = strtolower($student['gender'] ?? 'Male') === 'female' ? 'she' : 'he';
$pronoun2  = strtolower($student['gender'] ?? 'Male') === 'female' ? 'her' : 'his';
$pronoun3  = strtolower($student['gender'] ?? 'Male') === 'female' ? 'her' : 'him';

// ── Academic summary ─────────────────────────────────────────
$gpaRow = $pdo->query(
    "SELECT ROUND(AVG(ar.yearly_average),2) gpa, COUNT(DISTINCT ar.academic_year_id) years_enrolled
     FROM annual_results ar WHERE ar.student_id = $stdId"
)->fetch();
$gpa          = $gpaRow['gpa'] ? (float)$gpaRow['gpa'] : null;
$yearsEnrolled= (int)($gpaRow['years_enrolled'] ?? 0);

// Best year GPA
$bestYear = $pdo->query(
    "SELECT ay.name ay_name, ROUND(AVG(ar.yearly_average),2) yr_gpa
     FROM annual_results ar JOIN academic_years ay ON ay.id=ar.academic_year_id
     WHERE ar.student_id=$stdId GROUP BY ar.academic_year_id
     ORDER BY yr_gpa DESC LIMIT 1"
)->fetch();

// ── Attendance ────────────────────────────────────────────────
$att = $pdo->query(
    "SELECT COUNT(*) total, SUM(status='Present') present, SUM(status='Absent') absent
     FROM attendance WHERE student_id=$stdId
     AND academic_year_id=(SELECT id FROM academic_years WHERE is_current=1 LIMIT 1)"
)->fetch();
$attPct = ($att && $att['total'] > 0)
    ? round(($att['present'] / $att['total']) * 100, 1) : null;

// ── Discipline record (check for any serious incidents) ───────
$disciplineCount = 0;
try {
    $disciplineCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM discipline_records WHERE student_id=$stdId AND severity IN ('major','critical')"
    )->fetchColumn();
} catch (Throwable $e) {}

// ── Number of years at school ─────────────────────────────────
$admissionYear = $student['admission_date'] ? (int)date('Y', strtotime($student['admission_date'])) : null;
$currentYear   = (int)date('Y');
$yearsAtSchool = $admissionYear ? max(1, $currentYear - $admissionYear) : $yearsEnrolled;

// ── Signing staff (current user if principal/VP/registrar) ────
$signerUser  = currentUser();
$signerName  = $signerUser['name'] ?? 'Principal';
$signerRole  = ucwords(str_replace('_', ' ', currentRole() ?? 'Principal'));
$principalName = setting('principal_name', $signerName);

// ── School settings ───────────────────────────────────────────
$school  = setting('school_name',    'KARN HIGH SCHOOL');
$address = setting('school_address', 'Karnplay, Nimba County, Liberia');
$phone   = setting('school_phone',   '+231 886 417 711');
$email   = setting('school_email',   'info@karnhighschool.edu.lr');
$ay      = currentAcademicYearName();

// ── GPA descriptor ────────────────────────────────────────────
function gpaDescriptor(?float $gpa): string {
    if ($gpa === null) return 'commendable';
    if ($gpa >= 85)  return 'outstanding';
    if ($gpa >= 75)  return 'excellent';
    if ($gpa >= 65)  return 'commendable';
    if ($gpa >= 55)  return 'satisfactory';
    return 'developing';
}
function gpaAdjective(?float $gpa): string {
    if ($gpa === null) return 'diligent';
    if ($gpa >= 85)  return 'exceptionally gifted';
    if ($gpa >= 75)  return 'highly capable';
    if ($gpa >= 65)  return 'dedicated and capable';
    if ($gpa >= 55)  return 'hardworking';
    return 'persevering';
}

// ── Purpose label ─────────────────────────────────────────────
$purposeLabel = match($purpose) {
    'college'     => 'College / University Admission',
    'employment'  => 'Employment',
    'scholarship' => 'Scholarship Application',
    'transfer'    => 'School Transfer',
    default       => 'General Purpose',
};

$refNumber = 'LOR-'.date('Y').'-'.str_pad($stdId, 4, '0', STR_PAD_LEFT).'-'.strtoupper(substr($purpose, 0, 3));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Letter of Recommendation — <?= e($fullName) ?></title>
  <style>
    * { box-sizing:border-box; margin:0; padding:0 }
    body {
      font-family: 'Times New Roman', Times, serif;
      background: #e8e8e8;
      padding: 20px;
      font-size: 12pt;
      color: #111;
      line-height: 1.7;
    }
    .letter {
      background: #fff;
      max-width: 760px;
      margin: 0 auto 30px;
      padding: 38px 56px 44px;
      box-shadow: 0 3px 16px rgba(0,0,0,.18);
      border: 1px solid #ccc;
      position: relative;
      overflow: hidden;
    }
    /* Watermark */
    .wm-logo {
      position: absolute;
      top: 50%; left: 50%;
      transform: translate(-50%,-50%);
      width: 55%;
      opacity: 0.055;
      pointer-events: none;
      user-select: none;
      z-index: 0;
    }
    /* Decorative top border */
    .letter::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 6px;
      background: linear-gradient(90deg, #ac2443 0%, #6b1029 50%, #ac2443 100%);
    }
    /* All content above watermark */
    .letter > *:not(.wm-logo) { position: relative; z-index: 1 }

    /* Header */
    .lh {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding-bottom: 14px;
      border-bottom: 2.5px solid #ac2443;
      margin-bottom: 18px;
    }
    .lh-left { display:flex; align-items:center; gap:18px }
    .lh img { width: 72px; height: 72px; object-fit:cover; border-radius:8px }
    .sn { font-size: 18pt; font-weight: 700; color: #ac2443; font-family: Arial, sans-serif }
    .ss { font-size: 10pt; color: #555; margin-top: 3px }
    .lh-right { text-align:right; font-size:10pt; color:#555 }

    /* Title banner */
    .doc-title {
      background: #ac2443;
      color: #fff;
      text-align: center;
      padding: 10px 0;
      font-size: 13pt;
      font-weight: 700;
      letter-spacing: .1em;
      text-transform: uppercase;
      margin-bottom: 22px;
      border-radius: 2px;
    }
    .doc-subtitle {
      text-align: center;
      font-size: 10pt;
      color: #666;
      margin-top: -18px;
      margin-bottom: 20px;
      font-style: italic;
    }

    /* Meta row */
    .meta-row {
      display: flex;
      justify-content: space-between;
      font-size: 10.5pt;
      color: #444;
      margin-bottom: 20px;
    }

    /* Body */
    .body { font-size: 12pt; line-height: 1.85; color: #111 }
    .body p { margin-bottom: 14px }

    /* Highlight box */
    .hbox {
      background: #fdf1f4;
      border-left: 4px solid #ac2443;
      border-radius: 0 4px 4px 0;
      padding: 14px 20px;
      margin: 20px 0;
      font-size: 11pt;
    }
    .hbox table { width: 100%; border-collapse: collapse }
    .hbox td { padding: 4px 6px }
    .hbox td:first-child { font-weight: 700; color: #ac2443; width: 210px; white-space: nowrap }

    /* Signatures */
    .sigs {
      margin-top: 40px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 40px;
    }
    .sig { text-align: center }
    .sig-space { height: 52px }
    .sig-line {
      border-top: 1.5px solid #333;
      padding-top: 6px;
      font-size: 11pt;
      font-weight: 700;
      color: #222;
    }
    .sig-sub { font-size: 10pt; color: #555 }

    /* Stamp circle */
    .stamp-circle {
      width: 90px; height: 90px;
      border: 2px dashed #ac2443;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: #cc8899;
      font-size: 8pt;
      text-align: center;
      margin-top: 10px;
    }

    /* Footer */
    .footer {
      margin-top: 24px;
      padding-top: 12px;
      border-top: 1px solid #ddd;
      font-size: 9pt;
      color: #888;
      text-align: center;
      line-height: 1.6;
    }

    @media print {
      body { background:#fff; padding:0 }
      .letter { box-shadow:none; max-width:none; padding:18mm 22mm 20mm; border:none; margin:0 }
      .no-print { display:none !important }
    }
  </style>
</head>
<body>

<!-- Toolbar -->
<div class="no-print" style="max-width:760px;margin:0 auto 12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
  <div style="display:flex;gap:8px">
    <?php foreach (['general'=>'General','college'=>'College','scholarship'=>'Scholarship','employment'=>'Employment','transfer'=>'Transfer'] as $p=>$lbl): ?>
    <a href="?student_id=<?= $stdId ?>&purpose=<?= $p ?>"
       style="padding:6px 14px;border-radius:5px;font-size:12px;font-weight:700;text-decoration:none;
              background:<?= $purpose===$p?'#ac2443':'#f0f0f0' ?>;
              color:<?= $purpose===$p?'#fff':'#444' ?>">
      <?= $lbl ?>
    </a>
    <?php endforeach; ?>
  </div>
  <div style="display:flex;gap:8px">
    <button onclick="window.print()"
      style="padding:8px 20px;background:#ac2443;color:#fff;border:none;border-radius:5px;font-size:13px;font-weight:700;cursor:pointer">
      🖨 Print / Save PDF
    </button>
    <a href="javascript:history.back()"
      style="padding:8px 16px;background:#6c757d;color:#fff;border-radius:5px;font-size:13px;font-weight:700;text-decoration:none">
      ← Back
    </a>
  </div>
</div>

<div class="letter">
  <img class="wm-logo" src="<?= BASE_URL ?>/assets/images/logo.png" alt=""
       onerror="this.src='<?= BASE_URL ?>/assets/images/logo.jpg'"/>

  <!-- ── Header ── -->
  <div class="lh">
    <div class="lh-left">
      <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="KHS Logo"
           onerror="this.src='<?= BASE_URL ?>/assets/images/logo.jpg'"/>
      <div>
        <div class="sn"><?= e($school) ?></div>
        <div class="ss"><?= e($address) ?></div>
        <div class="ss">Tel: <?= e($phone) ?> &nbsp;|&nbsp; <?= e($email) ?></div>
      </div>
    </div>
    <div class="lh-right">
      <div>Ref: <strong><?= e($refNumber) ?></strong></div>
      <div>Date: <?= date('F d, Y') ?></div>
      <div style="margin-top:4px">Academic Year: <?= e($ay) ?></div>
    </div>
  </div>

  <!-- ── Title ── -->
  <div class="doc-title">Letter of Recommendation</div>
  <div class="doc-subtitle">Purpose: <?= e($purposeLabel) ?></div>

  <!-- ── Body ── -->
  <div class="body">

    <p>To Whom It May Concern,</p>

    <?php
    // ── Opening paragraph — varies by purpose ────────────────
    $opening = match($purpose) {
        'college' =>
            "It is with great pleasure and without reservation that I write this letter of recommendation on behalf of <strong>".e($fullName)."</strong> for consideration for admission to your institution. As ".e($pronoun2)." Principal / authorised representative at ".e($school).", I have had the opportunity to observe ".e($pronoun2)." academic journey and character development over the course of ".e($pronoun2)." enrolment with us.",
        'scholarship' =>
            "I am honoured to provide this letter of recommendation in strong support of <strong>".e($fullName)."</strong>'s application for your scholarship programme. It is my sincere belief that ".e($pronoun)." represents the very qualities — academic excellence, determination, and integrity — that such awards are designed to recognise and nurture.",
        'employment' =>
            "I write this letter with confidence and enthusiasm to recommend <strong>".e($fullName)."</strong> for employment consideration. During ".e($pronoun2)." time at ".e($school).", ".e($pronoun)." has consistently demonstrated the qualities of a diligent, responsible, and trustworthy young person ready to contribute meaningfully in a professional environment.",
        'transfer' =>
            "This letter serves to formally introduce and recommend <strong>".e($fullName)."</strong> to your institution upon transfer from ".e($school).". We have known ".e($pronoun3)." as a committed student, and we have full confidence that ".e($pronoun)." will be a valued member of your school community.",
        default =>
            "I am writing to recommend <strong>".e($fullName)."</strong>, a student currently enrolled at ".e($school).". Having observed ".e($pronoun2)." academic and personal development firsthand, I am pleased to provide this letter of recommendation and commend ".e($pronoun3)." to any institution, organisation, or individual who may find it relevant.",
    };
    echo "<p>$opening</p>";
    ?>

    <!-- Student info box -->
    <div class="hbox">
      <table>
        <tr><td>Full Name:</td><td><strong><?= e($fullName) ?></strong></td></tr>
        <tr><td>Student ID:</td><td><?= e($student['student_id'] ?? '—') ?></td></tr>
        <tr><td>Current Grade:</td><td><?= e($student['grade_name'] ?? '—') ?> — <?= e($student['class_name'] ?? '—') ?></td></tr>
        <tr><td>Admission Date:</td><td><?= $student['admission_date'] ? date('F d, Y', strtotime($student['admission_date'])) : '—' ?></td></tr>
        <?php if ($gpa !== null): ?>
        <tr><td>Cumulative GPA:</td><td><strong><?= number_format($gpa, 2) ?>%</strong> — <?= ucfirst(gpaDescriptor($gpa)) ?> standing</td></tr>
        <?php endif; ?>
        <?php if ($attPct !== null): ?>
        <tr><td>Attendance Rate:</td><td><?= $attPct ?>% (<?= $att['present'] ?> / <?= $att['total'] ?> days present)</td></tr>
        <?php endif; ?>
        <tr><td>Years at School:</td><td><?= $yearsAtSchool > 1 ? $yearsAtSchool.' years' : 'Less than 1 year' ?></td></tr>
      </table>
    </div>

    <!-- Academic paragraph -->
    <?php if ($gpa !== null): ?>
    <p>
      Academically, <strong><?= e($firstName) ?></strong> has maintained a <?= gpaDescriptor($gpa) ?> performance record with a cumulative grade point average of <strong><?= number_format($gpa, 2) ?>%</strong> across <?= $yearsEnrolled > 1 ? $yearsEnrolled.' academic years' : 'the current academic year' ?>.
      <?php if ($bestYear): ?>
      <?= ucfirst($pronoun2) ?> strongest year was <strong><?= e($bestYear['ay_name']) ?></strong>, where <?= $pronoun ?> achieved an average of <strong><?= number_format((float)$bestYear['yr_gpa'], 2) ?>%</strong>.
      <?php endif; ?>
      <?= ucfirst($pronoun) ?> is <?= gpaAdjective($gpa) ?> and demonstrates a genuine commitment to learning.
    </p>
    <?php endif; ?>

    <!-- Attendance paragraph -->
    <?php if ($attPct !== null && $attPct >= 70): ?>
    <p>
      <?= ucfirst($pronoun2) ?> attendance record reflects a strong sense of responsibility and dedication to <?= $pronoun2 ?> education, with an attendance rate of <strong><?= $attPct ?>%</strong> in the current academic year.
      <?php if ($attPct >= 90): ?>
      This exemplary attendance is a testament to <?= $pronoun2 ?> discipline and commitment.
      <?php endif; ?>
    </p>
    <?php endif; ?>

    <!-- Conduct paragraph -->
    <?php if ($disciplineCount === 0): ?>
    <p>
      Throughout <?= $pronoun2 ?> enrolment at <?= e($school) ?>, <strong><?= e($firstName) ?></strong> has maintained a clean disciplinary record. <?= ucfirst($pronoun) ?> is known by faculty and staff as a respectful, well-mannered student who upholds the values of our institution. <?= ucfirst($pronoun) ?> relates positively with peers and demonstrates maturity beyond <?= $pronoun2 ?> years.
    </p>
    <?php else: ?>
    <p>
      <strong><?= e($firstName) ?></strong> is a growing young person who, like all students, has faced challenges during <?= $pronoun2 ?> time at <?= e($school) ?>. We believe <?= $pronoun ?> has shown a willingness to learn from these experiences and continues to grow in character and conduct.
    </p>
    <?php endif; ?>

    <!-- Purpose-specific closing paragraph -->
    <?php
    $closing = match($purpose) {
        'college' =>
            "I am fully confident that <strong>".e($firstName)."</strong> will rise to the academic and social demands of tertiary education. ".ucfirst($pronoun)." possesses the intellectual curiosity, work ethic, and personal character to thrive in a university environment. I therefore unreservedly recommend ".e($pronoun3)." for admission to your institution and encourage you to give ".e($pronoun2)." application your most favourable consideration.",
        'scholarship' =>
            "A scholarship awarded to <strong>".e($firstName)."</strong> would not only reward demonstrated merit but also enable a determined young person to reach ".e($pronoun2)." full potential. I have no doubt that ".e($pronoun)." will honour such an investment through ".e($pronoun2)." continued dedication and service to ".e($pronoun2)." community. I recommend ".e($pronoun3)." in the highest possible terms.",
        'employment' =>
            "I am confident that <strong>".e($firstName)."</strong> will prove to be a reliable, conscientious, and productive member of any team or organisation. ".ucfirst($pronoun2)." positive attitude, punctuality, and sense of responsibility make ".e($pronoun3)." an ideal candidate. I recommend ".e($pronoun3)." without reservation for the opportunity you have available.",
        'transfer' =>
            "We are sad to see <strong>".e($firstName)."</strong> leave our institution, but we understand the circumstances necessitating this transfer. We trust that ".e($pronoun)." will continue to be an excellent student at your school. We recommend ".e($pronoun3)." to you with full confidence and ask that you extend to ".e($pronoun3)." every opportunity to succeed.",
        default =>
            "In summary, <strong>".e($firstName)."</strong> is a student of good character, academic capability, and personal integrity. I recommend ".e($pronoun3)." with confidence to any individual or institution that requires such a reference. Please do not hesitate to contact me directly should you require further information.",
    };
    echo "<p>$closing</p>";
    ?>

    <p>Yours sincerely,</p>
  </div>

  <!-- ── Signatures ── -->
  <div class="sigs">
    <div class="sig">
      <div class="sig-space"></div>
      <div class="sig-line"><?= e($signerName) ?></div>
      <div class="sig-sub"><?= e($signerRole) ?></div>
      <div class="sig-sub"><?= e($school) ?></div>
      <div class="stamp-circle">OFFICIAL<br>STAMP</div>
    </div>
    <div class="sig">
      <div class="sig-space"></div>
      <div class="sig-line"><?= e($principalName) ?></div>
      <div class="sig-sub">Principal</div>
      <div class="sig-sub"><?= e($school) ?></div>
    </div>
  </div>

  <!-- ── Footer ── -->
  <?= docVerifyStrip('recommendation',$stdId,$fullName,$student['grade_name']??'',$ay,$refNumber,currentUserId(),$purpose,'+1 year') ?>
  <div class="footer">
    <?= e($school) ?> &nbsp;&bull;&nbsp; <?= e($address) ?> &nbsp;&bull;&nbsp; <?= e($phone) ?><br>
    Ref: <?= e($refNumber) ?> &nbsp;&bull;&nbsp; Issued: <?= date('d F Y') ?> &nbsp;&bull;&nbsp;
    This letter is issued by the Office of the Principal and is valid for one year from date of issue.
    Any alteration renders this document invalid.
  </div>

</div><!-- .letter -->
</body>
</html>
