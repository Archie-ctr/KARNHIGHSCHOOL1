<?php
require_once dirname(__DIR__).'/config/db.php';
requireAuth();
$pdo=db(); $stdId=(int)($_GET['student_id']??0);
if(!$stdId) die('Invalid.'); requireStaff();
$student=$pdo->query("SELECT s.*,g.name grade_name,c.name class_name FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id LEFT JOIN classes c ON c.id=s.current_class_id WHERE s.id=$stdId")->fetch();
if(!$student) die('Student not found.');
$school=setting('school_name','KARN HIGH SCHOOL'); $ay=currentAcademicYearName();
$admNum=$student['admission_number']??('ADM-'.date('Y').'-'.str_pad($student['id'],4,'0',STR_PAD_LEFT));
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Admission Letter — <?=e($student['first_name'].' '.$student['last_name'])?></title>
<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:'Times New Roman',serif;background:#f5f5f5;padding:20px}.letter{background:#fff;max-width:750px;margin:0 auto;padding:40px 56px;box-shadow:0 2px 12px rgba(0,0,0,.1)}.lh{display:flex;align-items:center;gap:20px;border-bottom:3px solid #ac2443;padding-bottom:16px;margin-bottom:20px}.lh img{width:70px;height:70px;border-radius:10px}.sn{font-size:20px;font-weight:700;color:#ac2443}.ss{font-size:13px;color:#555}.title{text-align:center;font-size:16px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin:20px 0;color:#fff;background:#ac2443;padding:10px;border-radius:4px}.body{font-size:13.5px;line-height:1.9;color:#222}.body p{margin-bottom:14px}.hbox{background:#f7e8ec;border:1px solid #ac2443;border-radius:6px;padding:16px 20px;margin:20px 0;font-size:13px}.hbox table{width:100%}.hbox td{padding:4px 0}.hbox td:first-child{font-weight:700;width:190px;color:#ac2443}.sigs{margin-top:40px;display:grid;grid-template-columns:1fr 1fr;gap:40px}.sig{text-align:center}.sig-line{border-top:1px solid #333;padding-top:6px;font-size:12px;color:#555}.footer{margin-top:24px;padding-top:14px;border-top:1px solid #ddd;font-size:11px;color:#888;text-align:center}@media print{body{background:#fff;padding:0}.letter{box-shadow:none;max-width:none;padding:20mm 22mm}.no-print{display:none}}</style>
</head><body>
<div class="no-print" style="max-width:750px;margin:0 auto 12px;display:flex;gap:10px">
  <button onclick="window.print()" style="padding:8px 18px;background:#ac2443;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px">🖨️ Print / Save PDF</button>
  <a href="javascript:history.back()" style="padding:8px 16px;border:1px solid #ddd;border-radius:6px;font-size:13px;color:#555;text-decoration:none">← Back</a>
</div>
<div class="letter">
  <div class="lh"><img src="<?=BASE_URL?>/assets/images/logo.jpg" alt="KHS"/><div><div class="sn"><?=e($school)?></div><div class="ss">Karnplay, Nimba County, Liberia &nbsp;|&nbsp; <?=e(setting('school_phone','+231 886 417 711'))?></div></div></div>
  <div class="title">Official Admission Letter</div>
  <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:20px">
    <div><strong>Ref:</strong> <?=e($admNum)?></div>
    <div><strong>Date:</strong> <?=date('F d, Y')?></div>
  </div>
  <div class="body">
    <p>Dear <strong><?=e($student['first_name'].' '.$student['last_name'])?></strong> and Parent/Guardian,</p>
    <p>On behalf of the Administration, Faculty and Staff of <strong><?=e($school)?></strong>, we are delighted to offer admission to the following student for the <strong><?=e($ay)?></strong> academic year:</p>
    <div class="hbox"><table>
      <tr><td>Student Name:</td><td><?=e($student['first_name'].($student['middle_name']?' '.$student['middle_name']:'').' '.$student['last_name'])?></td></tr>
      <tr><td>Admission Number:</td><td><strong><?=e($admNum)?></strong></td></tr>
      <tr><td>Student ID:</td><td><?=e($student['student_id']??'—')?></td></tr>
      <tr><td>Grade Admitted To:</td><td><?=e($student['grade_name']??'—')?></td></tr>
      <tr><td>Class:</td><td><?=e($student['class_name']??'To be assigned')?></td></tr>
      <tr><td>Academic Year:</td><td><?=e($ay)?></td></tr>
      <tr><td>Admission Date:</td><td><?=$student['admission_date']?date('F d, Y',strtotime($student['admission_date'])):date('F d, Y')?></td></tr>
    </table></div>
    <p>You are kindly requested to report to the school Registrar's Office with this letter and the following documents within <strong>5 working days</strong> to complete the enrolment process:</p>
    <ul style="margin-left:20px;margin-bottom:14px;line-height:2">
      <li>Two passport-sized photographs</li>
      <li>Original birth certificate</li>
      <li>Previous school report card or transcript</li>
      <li>Completed health information form</li>
      <li>Proof of payment of registration fees</li>
    </ul>
    <p>We are confident that <strong><?=e($student['first_name'])?></strong> will find our school to be an enriching and supportive environment. We look forward to partnering with your family in pursuit of academic excellence and good character.</p>
    <p>Congratulations and welcome to the <strong><?=e($school)?></strong> family!</p>
  </div>
  <div class="sigs">
    <div class="sig"><div style="height:48px"></div><div class="sig-line">Registrar<br><?=e($school)?></div></div>
    <div class="sig"><div style="height:48px"></div><div class="sig-line">Principal<br><?=e($school)?></div></div>
  </div>
  <div class="footer"><?=e($school)?> &nbsp;|&nbsp; Karnplay, Nimba County, Liberia &nbsp;|&nbsp; <?=e(setting('school_phone','+231 886 417 711'))?> &nbsp;|&nbsp; <?=date('Y')?></div>
</div>
</body></html>
