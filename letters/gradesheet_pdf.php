<?php
require_once dirname(__DIR__).'/config/db.php';
require_once dirname(__DIR__).'/includes/doc_verify_helper.php';
requireAuth();
$pdo=db(); $stdId=(int)($_GET['student_id']??0); $ayId=(int)($_GET['ay_id']??currentAcademicYearId());
if(!$stdId) die('Invalid request.');
// Students can only see their own
if(isStudent()){
    $myStd=(int)$pdo->query("SELECT id FROM students WHERE user_id=".currentUser()['id']." LIMIT 1")->fetchColumn();
    if($myStd!==$stdId) die('Access denied.');
}
// Parents can only see their children
if(hasRole('parent')){
    $g=$pdo->prepare("SELECT id FROM guardians WHERE user_id=? LIMIT 1");
    $g->execute([currentUser()['id']]); $gid=(int)($g->fetchColumn()?:0);
    $linked=$gid?(int)$pdo->query("SELECT COUNT(*) FROM student_guardians WHERE guardian_id=$gid AND student_id=$stdId")->fetchColumn():0;
    if(!$linked) die('Access denied.');
}
$student=$pdo->query(
    "SELECT s.*,g.name grade_name,c.name class_name,g.id grade_id
     FROM students s
     LEFT JOIN grades  g ON g.id=s.current_grade_id
     LEFT JOIN classes c ON c.id=s.current_class_id
     WHERE s.id=$stdId"
)->fetch();
if(!$student) die('Student not found.');
$rc=$pdo->query("SELECT * FROM report_cards WHERE student_id=$stdId AND academic_year_id=$ayId LIMIT 1")->fetch();
if(!$rc||$rc['status']!=='published') die('Gradesheet not yet published for this academic year.');
$ay=$pdo->query("SELECT name FROM academic_years WHERE id=$ayId")->fetchColumn();
$subjects=$pdo->prepare(
    "SELECT DISTINCT s.id,s.name FROM assessment_scores asc2
     JOIN subjects s ON s.id=asc2.subject_id
     WHERE asc2.student_id=? AND asc2.academic_year_id=?
     ORDER BY s.name"
);
$subjects->execute([$stdId,$ayId]); $subjects=$subjects->fetchAll();
$scoreData=[];
foreach($subjects as $sub){
    $sc=$pdo->prepare(
        "SELECT ac.name,ac.sequence,asc2.marks_obtained,asc2.max_marks
         FROM assessment_scores asc2
         JOIN assessment_configs ac ON ac.id=asc2.assessment_config_id
         WHERE asc2.student_id=? AND asc2.subject_id=? AND asc2.academic_year_id=?
         ORDER BY ac.sequence"
    );
    $sc->execute([$stdId,$sub['id'],$ayId]);
    $scoreData[$sub['id']]=$sc->fetchAll();
}
$school   = setting('school_name','KARN HIGH SCHOOL');
$address  = setting('school_address','Karnplay, Nimba County, Liberia');
$phone    = setting('school_phone','+231 886 417 711');
$email    = setting('school_email','');
$motto    = setting('school_motto','Excellence in Education');
$isGr12   = ($student['grade_id']==13); // Grade 12 = id 13
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Gradesheet — <?=e($student['first_name'].' '.$student['last_name'])?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,sans-serif;background:#f5f5f5;padding:16px;font-size:11pt;color:#1a1a1a}
.page{background:#fff;max-width:820px;margin:0 auto;padding:16mm 18mm 14mm;box-shadow:0 2px 16px rgba(0,0,0,.13);position:relative}
/* Watermark border */
.page::before{content:'';position:absolute;inset:6mm;border:1.5px double #ac2443;opacity:.18;pointer-events:none}
/* Logo watermark */
.wm-logo{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:58%;opacity:.055;pointer-events:none;user-select:none;z-index:0}
/* Header */
.rh{display:flex;align-items:center;gap:14px;padding-bottom:10px;margin-bottom:8px;border-bottom:3px solid #ac2443}
.rh img{width:62px;height:62px;border-radius:50%;object-fit:cover;border:2px solid #ac2443}
.rh-text{flex:1}
.school-name{font-size:19px;font-weight:700;color:#ac2443;letter-spacing:.02em}
.school-sub{font-size:10.5px;color:#666;margin-top:2px}
.school-motto{font-size:10px;font-style:italic;color:#888;margin-top:1px}
/* Title bar */
.doc-title{text-align:center;background:#ac2443;color:#fff;padding:6px 10px;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;margin:8px 0}
/* Student info */
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:4px 24px;font-size:10.5pt;background:#fdf0f3;border:1px solid #f0d0d8;border-radius:4px;padding:9px 12px;margin-bottom:10px}
.info-grid span{padding:2px 0;border-bottom:1px dotted #e8c8d0}
.info-grid span:last-child,.info-grid span:nth-last-child(2){border-bottom:none}
/* Marks table */
table{width:100%;border-collapse:collapse;font-size:9.5pt;margin-bottom:10px}
thead th{background:#ac2443;color:#fff;padding:5px 5px;text-align:center;font-size:8.5pt;border:1px solid #8a1a30}
thead th:first-child{text-align:left;padding-left:7px}
tbody td{padding:4px 5px;border:1px solid #ddd;text-align:center}
tbody td:first-child{text-align:left;font-weight:600;padding-left:7px}
tbody tr:nth-child(even){background:#fdf8f9}
.tr-avg{background:#fdf0f3!important;font-weight:700;border-top:2px solid #ac2443}
.grade-a,.grade-b,.grade-c,.grade-d{color:#1a6b2a;font-weight:700}
.grade-f{color:#a82828;font-weight:700}
/* Grading key */
.grade-key{font-size:8.5pt;margin-bottom:10px;padding:6px 10px;background:#f9f9f9;border:1px solid #eee;border-radius:4px;display:flex;flex-wrap:wrap;gap:6px 18px}
.grade-key span{white-space:nowrap}
/* Attendance */
.att-strip{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:10px}
.att-box{text-align:center;padding:8px 4px;background:#f5f5f5;border-radius:5px;border:1px solid #e8e8e8}
.att-box strong{display:block;font-size:17px;color:#ac2443;font-weight:700}
.att-box span{font-size:9pt;color:#555}
/* Comments */
.comment-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px}
.comment-box{border:1px solid #ddd;border-radius:4px;padding:9px 10px;font-size:9.5pt}
.comment-box strong{display:block;color:#ac2443;margin-bottom:3px;font-size:9.5pt}
/* Signatures */
.sigs{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-top:18px;margin-bottom:10px}
.sig{text-align:center}
.sig-line{border-top:1.5px solid #333;padding-top:4px;font-size:9pt;color:#444;margin-top:34px}
/* Diploma link */
.diploma-notice{background:#fdf0f3;border:1.5px solid #ac2443;border-radius:5px;padding:8px 12px;font-size:10pt;margin-bottom:10px;text-align:center;color:#ac2443;font-weight:600}
/* Footer */
.footer{text-align:center;font-size:8.5pt;color:#999;padding-top:7px;border-top:1px solid #eee;margin-top:6px}
/* Print */
@media print{
    body{background:#fff;padding:0}
    .page{box-shadow:none;max-width:none;padding:8mm 12mm}
    .no-print{display:none}
    .page::before{opacity:.12}
}
</style>
</head><body>

<div class="no-print" style="max-width:820px;margin:0 auto 12px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
  <button onclick="window.print()" style="padding:8px 18px;background:#ac2443;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:700">🖨️ Print / Save PDF</button>
  <?php if($isGr12):?>
  <a href="diploma_pdf.php?student_id=<?=$stdId?>&ay_id=<?=$ayId?>" target="_blank" style="padding:8px 18px;background:#1a6b2a;color:#fff;border-radius:6px;font-size:13px;font-weight:700;text-decoration:none">🎓 View Diploma</a>
  <?php endif;?>
  <a href="javascript:history.back()" style="padding:8px 16px;border:1px solid #ddd;border-radius:6px;font-size:13px;color:#555;text-decoration:none">← Back</a>
</div>

<div class="page">
  <img class="wm-logo" src="<?=BASE_URL?>/assets/images/logo.png" alt=""
       onerror="this.src='<?=BASE_URL?>/assets/images/logo.jpg'"/>  <!-- Header -->
  <div class="rh">
    <img src="<?=BASE_URL?>/assets/images/logo.jpg" alt="<?=e($school)?>"/>
    <div class="rh-text">
      <div class="school-name"><?=e($school)?></div>
      <div class="school-sub"><?=e($address)?> <?=$phone?' &nbsp;|&nbsp; '.e($phone):''?> <?=$email?' &nbsp;|&nbsp; '.e($email):''?></div>
      <?php if($motto):?><div class="school-motto">"<?=e($motto)?>"</div><?php endif;?>
    </div>
    <div style="text-align:right;font-size:9pt;color:#888">
      <div><strong>School Year</strong></div>
      <div style="font-size:11pt;font-weight:700;color:#ac2443"><?=e($ay)?></div>
    </div>
  </div>

  <div class="doc-title">Student Gradesheet &mdash; Academic Year <?=e($ay)?></div>

  <!-- Student info -->
  <div class="info-grid">
    <span><strong>Full Name:</strong> <?=e(trim($student['first_name'].($student['middle_name']?' '.$student['middle_name']:'').' '.$student['last_name']))?></span>
    <span><strong>Student ID:</strong> <?=e($student['student_id'])?></span>
    <span><strong>Grade / Class:</strong> <?=e($student['grade_name']??'—').($student['class_name']?' / '.e($student['class_name']):'')?></span>
    <span><strong>Academic Year:</strong> <?=e($ay)?></span>
    <span><strong>Gender:</strong> <?=e($student['gender']??'—')?></span>
    <span><strong>Date of Birth:</strong> <?=$student['date_of_birth']?date('F d, Y',strtotime($student['date_of_birth'])):'—'?></span>
    <span><strong>Admission No.:</strong> <?=e($student['admission_number']??$student['student_id'])?></span>
    <span><strong>Status:</strong> <strong style="color:#1a6b2a"><?=e($rc['promotion_status']??'Pending')?></strong></span>
  </div>

  <?php if($isGr12):?>
  <div class="diploma-notice">🎓 This student is eligible to receive a Diploma. <a href="diploma_pdf.php?student_id=<?=$stdId?>&ay_id=<?=$ayId?>" target="_blank" style="color:#ac2443">View & Print Diploma →</a></div>
  <?php endif;?>

  <!-- Marks table -->
  <table>
    <thead>
      <tr>
        <th style="width:22%">Subject</th>
        <th>1st Pd</th><th>2nd Pd</th><th>3rd Pd</th><th>Sem 1 Exam</th>
        <th>4th Pd</th><th>5th Pd</th><th>6th Pd</th><th>Sem 2 Exam</th>
        <th>Avg %</th><th>Grade</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $colKeys=['1st Period','2nd Period','3rd Period','Semester 1 Examination','4th Period','5th Period','6th Period','Semester 2 Examination'];
      foreach($subjects as $sub):
          $cols=array_fill_keys($colKeys,'—'); $vals=[];
          foreach($scoreData[$sub['id']] as $sc){
              foreach($colKeys as $k){
                  if(stripos($sc['name'],$k)!==false){
                      $p=$sc['max_marks']>0?round($sc['marks_obtained']/$sc['max_marks']*100,1):null;
                      $cols[$k]=$p??'—';
                      if($p!==null) $vals[]=$p;
                  }
              }
          }
          $avg=count($vals)?round(array_sum($vals)/count($vals),1):null;
          $gl=$avg!==null?gradeLetter($avg,$ayId):'—';
          $glClass=in_array($gl,['A','B'])?'grade-a':(in_array($gl,['C','D'])?'grade-c':'grade-f');
      ?>
      <tr>
        <td><?=e($sub['name'])?></td>
        <?php foreach($cols as $v):
          $cellColor=is_numeric($v)?($v<50?'color:#a82828':($v>=70?'color:#1a6b2a':'')):'';
        ?>
        <td style="<?=$cellColor?>"><?=$v?></td>
        <?php endforeach;?>
        <td><strong><?=$avg??'—'?></strong></td>
        <td class="<?=$glClass?>"><?=$gl?></td>
      </tr>
      <?php endforeach;?>
      <tr class="tr-avg">
        <td>YEARLY AVERAGE</td>
        <td colspan="8" style="text-align:center;font-size:8.5pt;color:#666">Calculated from all assessment scores above</td>
        <td><?=$rc['yearly_average']?round($rc['yearly_average'],1).'%':'—'?></td>
        <td class="<?=($rc['yearly_average']&&gradeLetter((float)$rc['yearly_average'],$ayId)!=='F')?'grade-a':'grade-f'?>"><?=$rc['yearly_average']?gradeLetter((float)$rc['yearly_average'],$ayId):'—'?></td>
      </tr>
    </tbody>
  </table>

  <!-- Grading key -->
  <div class="grade-key">
    <strong>Grading Scale:</strong>
    <span>A = 90–100%</span><span>B = 80–89%</span><span>C = 70–79%</span>
    <span>D = 60–69%</span><span>E = 50–59%</span><span>F = Below 50%</span>
  </div>

  <!-- Attendance -->
  <div class="att-strip">
    <div class="att-box"><strong><?=$rc['days_present']??0?></strong><span>Days Present</span></div>
    <div class="att-box"><strong><?=$rc['days_absent']??0?></strong><span>Days Absent</span></div>
    <div class="att-box"><strong><?=$rc['days_tardy']??0?></strong><span>Times Tardy</span></div>
    <div class="att-box"><strong><?=$rc['attendance_pct']??'—'?>%</strong><span>Attendance Rate</span></div>
  </div>

  <!-- Comments -->
  <div class="comment-row">
    <div class="comment-box">
      <strong>Conduct: <span style="color:#1a1a1a"><?=e($rc['conduct']??'—')?></span></strong>
      <strong style="margin-top:8px">Class Teacher's Comment:</strong>
      <p style="margin-top:4px;font-size:9.5pt;line-height:1.5"><?=nl2br(e($rc['teacher_comment']??'No comment recorded.'))?></p>
    </div>
    <div class="comment-box">
      <strong>Principal's Comment:</strong>
      <p style="margin-top:4px;font-size:9.5pt;line-height:1.5"><?=nl2br(e($rc['principal_comment']??'No comment recorded.'))?></p>
      <div style="margin-top:10px;padding-top:8px;border-top:1px solid #eee">
        <strong>Promotion Status:</strong>
        <span style="color:<?=$rc['promotion_status']==='Promoted'?'#1a6b2a':'#a82828'?>;font-weight:700;font-size:11pt;margin-left:6px"><?=e($rc['promotion_status']??'Pending')?></span>
      </div>
    </div>
  </div>

  <!-- Signatures -->
  <div class="sigs">
    <div class="sig"><div class="sig-line">Class Teacher</div></div>
    <div class="sig"><div class="sig-line">Academic Dean / Vice Principal</div></div>
    <div class="sig"><div class="sig-line">Principal</div></div>
  </div>

  <!-- Footer -->
  <?php
  $_gsRef = 'GS-'.date('Y').'-'.str_pad($stdId,4,'0',STR_PAD_LEFT).'-'.($ayId??0);
  echo docVerifyStrip('gradesheet',$stdId,
      trim(($student['first_name']??'').' '.($student['last_name']??'')),
      $student['grade_name']??'', $ay,
      $_gsRef, isLoggedIn()?currentUserId():null, null, '+5 years');
  ?>
  <div class="footer">
    <?=e($school)?> &nbsp;&bull;&nbsp; <?=e($address)?>
    <?=$phone?' &nbsp;&bull;&nbsp; '.e($phone):''?>
    &nbsp;&bull;&nbsp; This document is an official school record. Any alteration renders it invalid.
    &nbsp;&bull;&nbsp; Generated: <?=date('F d, Y')?>
  </div>
</div>
</body></html>
