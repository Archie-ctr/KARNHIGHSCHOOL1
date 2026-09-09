<?php
require_once dirname(__DIR__).'/config/db.php';
requireAuth();
$pdo=db(); $stdId=(int)($_GET['student_id']??0); $ayId=(int)($_GET['ay_id']??currentAcademicYearId());
if(!$stdId) die('Invalid request.');

// Access control
if(isStudent()){
    $myStd=(int)$pdo->query("SELECT id FROM students WHERE user_id=".currentUser()['id']." LIMIT 1")->fetchColumn();
    if($myStd!==$stdId) die('Access denied.');
}
if(hasRole('parent')){
    $g=$pdo->prepare("SELECT id FROM guardians WHERE user_id=? LIMIT 1");
    $g->execute([currentUser()['id']]); $gid=(int)($g->fetchColumn()?:0);
    $linked=$gid?(int)$pdo->query("SELECT COUNT(*) FROM student_guardians WHERE guardian_id=$gid AND student_id=$stdId")->fetchColumn():0;
    if(!$linked) die('Access denied.');
}

$student=$pdo->query(
    "SELECT s.*,g.name grade_name,g.id grade_id,c.name class_name
     FROM students s
     LEFT JOIN grades  g ON g.id=s.current_grade_id
     LEFT JOIN classes c ON c.id=s.current_class_id
     WHERE s.id=$stdId"
)->fetch();
if(!$student) die('Student not found.');
if($student['grade_id']!=13) die('Diploma is only available for Grade 12 graduates.');

// Must have a published gradesheet (report card)
$rc=$pdo->query("SELECT * FROM report_cards WHERE student_id=$stdId AND academic_year_id=$ayId LIMIT 1")->fetch();
if(!$rc||$rc['status']!=='published') die('Diploma cannot be generated — gradesheet not yet published.');

// Graduation record (optional — for certificate number and honours)
try{$grad=$pdo->query("SELECT * FROM graduation_records WHERE student_id=$stdId ORDER BY graduation_date DESC LIMIT 1")->fetch();}catch(Throwable $e){$grad=null;}

// Academic average
try{
    $avg=(float)$pdo->query(
        "SELECT COALESCE(ROUND(AVG(marks_obtained/max_marks*100),1),0)
         FROM assessment_scores
         WHERE student_id=$stdId AND academic_year_id=$ayId
           AND status IN ('approved','published') AND max_marks>0"
    )->fetchColumn();
}catch(Throwable $e){$avg=(float)($rc['yearly_average']??0);}

// Honours determination
function diplomaHonours(float $avg):string{
    if($avg>=95) return 'Summa Cum Laude';
    if($avg>=90) return 'Magna Cum Laude';
    if($avg>=85) return 'Cum Laude';
    if($avg>=80) return 'With Distinction';
    if($avg>=70) return 'With Merit';
    return '';
}
$honours=$grad['honours']??diplomaHonours($avg);

$ay         =$pdo->query("SELECT name FROM academic_years WHERE id=$ayId")->fetchColumn();
$school     = setting('school_name','KARN HIGH SCHOOL');
$address    = setting('school_address','Karnplay, Nimba County, Liberia');
$motto      = setting('school_motto','Excellence in Education');
$phone      = setting('school_phone','+231 886 417 711');
$gradDate   = $grad['graduation_date']??null;
$certNum    = $grad['certificate_number']??('KHS-G12-'.$ayId.'-'.str_pad($stdId,4,'0',STR_PAD_LEFT));
$studentFullName = strtoupper(trim($student['first_name'].($student['middle_name']?' '.$student['middle_name']:'').' '.$student['last_name']));
$issueDate  = $gradDate?date('F d, Y',strtotime($gradDate)):date('F d, Y');
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Diploma — <?=e($studentFullName)?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Open+Sans:wght@400;600&display=swap');
body{background:#2a1a0a;padding:20px;display:flex;flex-direction:column;align-items:center;font-family:'Open Sans',Arial,sans-serif}

.diploma-wrap{position:relative;width:870px;background:#fffdf7;padding:0;box-shadow:0 8px 40px rgba(0,0,0,.5)}
/* Outer decorative border */
.diploma-border-outer{position:absolute;inset:10px;border:3px solid #8B6914;pointer-events:none;z-index:1}
.diploma-border-inner{position:absolute;inset:16px;border:1px solid #C9A227;pointer-events:none;z-index:1}

.diploma-inner{position:relative;padding:40px 60px 36px;z-index:2;min-height:580px;display:flex;flex-direction:column;align-items:center}

/* Header */
.dip-header{width:100%;display:flex;align-items:center;justify-content:center;gap:20px;margin-bottom:16px;padding-bottom:14px;border-bottom:2px solid #C9A227}
.dip-logo{width:72px;height:72px;border-radius:50%;object-fit:cover;border:3px solid #8B6914;box-shadow:0 2px 8px rgba(139,105,20,.3)}
.dip-school{text-align:center}
.dip-school-name{font-family:'Cinzel',Georgia,serif;font-size:22px;font-weight:700;color:#5a3400;letter-spacing:.08em;text-shadow:1px 1px 0 rgba(201,162,39,.3)}
.dip-school-sub{font-size:10.5px;color:#7a5a20;margin-top:3px;letter-spacing:.03em}
.dip-school-motto{font-family:'Playfair Display',Georgia,serif;font-style:italic;font-size:10px;color:#9a7a40;margin-top:2px}

/* Main title */
.dip-title{font-family:'Cinzel',Georgia,serif;font-size:36px;font-weight:700;color:#8B6914;letter-spacing:.15em;text-align:center;margin:18px 0 6px;text-shadow:2px 2px 4px rgba(139,105,20,.2)}
.dip-subtitle{font-family:'Cinzel',Georgia,serif;font-size:13px;color:#a08030;letter-spacing:.2em;text-align:center;margin-bottom:20px;text-transform:uppercase}
/* Divider */
.dip-divider{width:80%;height:2px;background:linear-gradient(90deg,transparent,#C9A227,transparent);margin:8px auto 20px}

/* Award text */
.dip-award-text{font-family:'Playfair Display',Georgia,serif;font-size:13.5px;color:#3a2000;text-align:center;line-height:1.8;max-width:620px}
.dip-student-name{font-family:'Cinzel',Georgia,serif;font-size:30px;font-weight:700;color:#5a2000;text-align:center;margin:12px 0;letter-spacing:.06em;text-decoration:underline;text-decoration-color:#C9A227;text-underline-offset:6px}
.dip-degree{font-family:'Playfair Display',Georgia,serif;font-size:18px;font-weight:600;color:#3a2000;text-align:center;margin:6px 0}
.dip-field{font-family:'Cinzel',Georgia,serif;font-size:15px;color:#8B6914;text-align:center;letter-spacing:.08em;margin:4px 0 8px}
<?php if($honours):?>
.dip-honours{display:inline-block;font-family:'Playfair Display',Georgia,serif;font-style:italic;font-size:14px;color:#6a4000;background:linear-gradient(135deg,#fdf5dc,#fde8a0);border:1.5px solid #C9A227;padding:5px 18px;border-radius:20px;margin:6px auto;text-align:center}
<?php endif;?>
.dip-year{font-family:'Cinzel',Georgia,serif;font-size:13px;color:#8B6914;text-align:center;margin:10px 0;letter-spacing:.1em}

/* Stats strip */
.dip-stats{display:flex;gap:0;width:80%;margin:14px auto;border:1.5px solid #C9A227;border-radius:6px;overflow:hidden}
.dip-stat{flex:1;text-align:center;padding:8px;border-right:1px solid #C9A227}
.dip-stat:last-child{border-right:none}
.dip-stat strong{display:block;font-family:'Cinzel',serif;font-size:16px;color:#8B6914;font-weight:700}
.dip-stat span{font-size:8.5pt;color:#7a5a20}

/* Certificate number */
.cert-num{font-family:'Open Sans',sans-serif;font-size:9pt;color:#9a8a6a;text-align:center;letter-spacing:.06em;margin-bottom:10px}

/* Signatures */
.sig-row{display:grid;grid-template-columns:repeat(3,1fr);gap:30px;width:90%;margin-top:20px}
.sig-box{text-align:center}
.sig-line{border-top:1.5px solid #5a3400;padding-top:5px;font-size:9pt;color:#5a3400;font-family:'Open Sans',sans-serif}
.sig-title{font-size:8pt;color:#8B6914;margin-top:1px}

/* Seal placeholder */
.dip-seal{width:80px;height:80px;border-radius:50%;border:3px double #C9A227;display:flex;align-items:center;justify-content:center;margin:10px auto;background:radial-gradient(circle,#fdf5dc,#fde8a0);font-size:28px;color:#8B6914;box-shadow:0 2px 8px rgba(139,105,20,.3)}

/* Corner ornaments */
.corner{position:absolute;width:40px;height:40px;font-size:22px;color:#C9A227;opacity:.7}
.c-tl{top:20px;left:22px}.c-tr{top:20px;right:22px}.c-bl{bottom:20px;left:22px}.c-br{bottom:20px;right:22px}

/* Print */
@media print{
    body{background:white;padding:0}
    .diploma-wrap{box-shadow:none;width:100%}
    .no-print{display:none}
    @page{size:landscape;margin:5mm}
}
</style>
</head><body>

<div class="no-print" style="width:870px;display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">
  <button onclick="window.print()" style="padding:8px 18px;background:#8B6914;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:700">🖨️ Print Diploma</button>
  <a href="gradesheet_pdf.php?student_id=<?=$stdId?>&ay_id=<?=$ayId?>" target="_blank" style="padding:8px 18px;background:#ac2443;color:#fff;border-radius:6px;font-size:13px;font-weight:700;text-decoration:none">📋 View Gradesheet</a>
  <a href="javascript:history.back()" style="padding:8px 16px;border:1px solid #aaa;border-radius:6px;font-size:13px;color:#ddd;text-decoration:none">← Back</a>
</div>

<div class="diploma-wrap">
  <div class="diploma-border-outer"></div>
  <div class="diploma-border-inner"></div>

  <!-- Corner ornaments -->
  <span class="corner c-tl">❧</span>
  <span class="corner c-tr" style="transform:scaleX(-1)">❧</span>
  <span class="corner c-bl" style="transform:scaleY(-1)">❧</span>
  <span class="corner c-br" style="transform:scale(-1,-1)">❧</span>

  <div class="diploma-inner">
    <!-- School header -->
    <div class="dip-header">
      <img class="dip-logo" src="<?=BASE_URL?>/assets/images/logo.jpg" alt="<?=e($school)?>"/>
      <div class="dip-school">
        <div class="dip-school-name"><?=e($school)?></div>
        <div class="dip-school-sub"><?=e($address)?></div>
        <?php if($motto):?><div class="dip-school-motto">"<?=e($motto)?>"</div><?php endif;?>
      </div>
      <img class="dip-logo" src="<?=BASE_URL?>/assets/images/logo.jpg" alt=""/>
    </div>

    <!-- Title -->
    <div class="dip-title">DIPLOMA</div>
    <div class="dip-subtitle">of Secondary Education</div>
    <div class="dip-divider"></div>

    <!-- Award text -->
    <div class="dip-award-text">This is to certify that</div>
    <div class="dip-student-name"><?=e($studentFullName)?></div>
    <div class="dip-award-text">
      having satisfactorily completed the required course of study and fulfilled all academic
      requirements of the Senior Secondary School Programme
    </div>

    <div style="margin:10px 0;font-family:'Playfair Display',serif;font-size:13.5px;color:#3a2000;text-align:center;line-height:1.7">
      is hereby awarded the
    </div>

    <div class="dip-degree">West African Senior Secondary School Certificate</div>
    <div class="dip-field">Grade 12 &nbsp;&mdash;&nbsp; <?=e($school)?></div>

    <?php if($honours):?>
    <div style="text-align:center;margin:6px 0">
      <span class="dip-honours">Graduated <?=e($honours)?></span>
    </div>
    <?php endif;?>

    <div class="dip-year">Academic Year &nbsp;<?=e($ay)?></div>

    <!-- Stats strip -->
    <div class="dip-stats">
      <div class="dip-stat"><strong><?=$avg>0?round($avg,1).'%':'—'?></strong><span>Final Average</span></div>
      <div class="dip-stat"><strong><?=$rc['days_present']??0?></strong><span>Days Present</span></div>
      <div class="dip-stat"><strong><?=$rc['attendance_pct']??'—'?>%</strong><span>Attendance</span></div>
      <div class="dip-stat"><strong><?=e($rc['promotion_status']??'Graduated')?></strong><span>Status</span></div>
    </div>

    <!-- Certificate number -->
    <div class="cert-num">Certificate No: <?=e($certNum)?> &nbsp;&bull;&nbsp; Issued: <?=$issueDate?> &nbsp;&bull;&nbsp; <?=e($address)?></div>

    <!-- Official seal -->
    <div class="dip-seal">🏫</div>

    <!-- Signatures -->
    <div class="sig-row">
      <div class="sig-box">
        <div class="sig-line">Class Teacher</div>
        <div class="sig-title">Form Teacher</div>
      </div>
      <div class="sig-box">
        <div class="sig-line">Principal</div>
        <div class="sig-title">Principal, <?=e($school)?></div>
      </div>
      <div class="sig-box">
        <div class="sig-line">Board Chairman / Superintendent</div>
        <div class="sig-title">School Authority</div>
      </div>
    </div>

    <div style="margin-top:14px;font-size:8.5pt;color:#9a8a6a;text-align:center;font-style:italic">
      This diploma is an official document of <?=e($school)?>. Any unauthorized alteration, reproduction or misuse is prohibited under Liberian law.
    </div>
  </div>
</div>

</body></html>
