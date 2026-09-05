<?php
// Entrance Eligibility Letter — accessible by applicant (app + phone) or admin
require_once dirname(__DIR__).'/config/db.php';
$pdo=db();
$id=(int)($_GET['id']??0); $appNum=trim($_GET['app']??''); $phone=trim($_GET['phone']??'');
$app=null;
if($id&&isAdminLoggedIn()){
    $app=$pdo->query("SELECT * FROM applications WHERE id=$id")->fetch();
} elseif($appNum&&$phone){
    $stmt=$pdo->prepare("SELECT * FROM applications WHERE application_number=? AND (phone=? OR guardian_phone=?) LIMIT 1"); $stmt->execute([$appNum,$phone,$phone]); $app=$stmt->fetch();
}
if(!$app){die('<p style="font-family:sans-serif;padding:40px;text-align:center">Letter not found or access denied.</p>');}
if(!$app['entrance_letter_ref']){die('<p style="font-family:sans-serif;padding:40px;text-align:center">Entrance eligibility letter not yet issued for this application.</p>');}
$school=setting('school_name','KARN HIGH SCHOOL');
$phone1=setting('school_phone','+231 886 417 711');
$examDate=$app['entrance_exam_date']?date('l, F d, Y',strtotime($app['entrance_exam_date'])):'To be confirmed';
$examTime=$app['entrance_exam_time']?:'9:00 AM';
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Entrance Eligibility Letter — <?=e($app['application_number'])?></title>
<style>
  *{box-sizing:border-box;margin:0;padding:0} body{font-family:'Times New Roman',serif;background:#f5f5f5;padding:20px} .letter{background:#fff;max-width:750px;margin:0 auto;padding:40px 56px;box-shadow:0 2px 12px rgba(0,0,0,.1)} .letterhead{display:flex;align-items:center;gap:20px;border-bottom:3px solid #ac2443;padding-bottom:16px;margin-bottom:20px} .letterhead img{width:70px;height:70px;border-radius:10px;object-fit:cover} .school-name{font-size:20px;font-weight:700;color:#ac2443} .school-sub{font-size:13px;color:#555} .letter-title{text-align:center;font-size:15px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin:20px 0;color:#ac2443;border-top:1px solid #ddd;border-bottom:1px solid #ddd;padding:8px 0} .ref-date{display:flex;justify-content:space-between;font-size:13px;margin-bottom:20px} .body-text{font-size:13.5px;line-height:1.85;color:#222} .body-text p{margin-bottom:14px} .highlight-box{background:#f7e8ec;border:1px solid #ac2443;border-radius:6px;padding:16px 20px;margin:20px 0;font-size:13.5px} .highlight-box table{width:100%} .highlight-box td{padding:4px 0} .highlight-box td:first-child{font-weight:700;width:180px;color:#ac2443} .signature-section{margin-top:40px;display:grid;grid-template-columns:1fr 1fr;gap:40px} .sig-block{text-align:center} .sig-line{border-top:1px solid #333;padding-top:6px;font-size:12px;color:#555} .footer-note{margin-top:24px;padding-top:14px;border-top:1px solid #ddd;font-size:11px;color:#888;text-align:center} @media print{body{background:#fff;padding:0} .no-print{display:none} .letter{box-shadow:none;max-width:none;padding:20mm 22mm}}
</style></head><body>
<div class="no-print" style="max-width:750px;margin:0 auto 12px;display:flex;gap:10px">
  <button onclick="window.print()" style="padding:8px 18px;background:#ac2443;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:14px">🖨️ Print / Save PDF</button>
  <a href="javascript:history.back()" style="padding:8px 18px;border:1px solid #ddd;border-radius:6px;font-size:14px;color:#555;text-decoration:none">← Back</a>
</div>
<div class="letter">
  <div class="letterhead">
    <img src="<?=BASE_URL?>/assets/images/logo.jpg" alt="KHS Logo"/>
    <div><div class="school-name"><?=e($school)?></div><div class="school-sub">Karnplay, Nimba County, Liberia &nbsp;|&nbsp; <?=e($phone1)?></div><div class="school-sub"><?=e(setting('school_email','info@karnhighschool.edu.lr'))?></div></div>
  </div>
  <div class="letter-title">Entrance Eligibility / Provisional Admission Letter</div>
  <div class="ref-date">
    <div><strong>Ref:</strong> <?=e($app['entrance_letter_ref'])?></div>
    <div><strong>Date:</strong> <?=date('F d, Y')?></div>
  </div>
  <div class="body-text">
    <p>Dear <strong><?=e($app['guardian_name'])?></strong> (<?=e($app['guardian_relationship'])?>),</p>
    <p>We are pleased to inform you that the application submitted for <strong><?=e($app['first_name'].' '.($app['middle_name']?$app['middle_name'].' ':'').$app['last_name'])?></strong> has been reviewed and assessed by the Admissions Office of <strong><?=e($school)?></strong>.</p>
    <p>After careful review of the submitted application and supporting documents, we are pleased to confirm that the above-named applicant has been found eligible to participate in the <strong><?=e($school)?> Entrance Examination</strong> for the <strong><?=e($app['academic_year'])?></strong> academic year.</p>
    <div class="highlight-box">
      <table>
        <tr><td>Applicant Name:</td><td><?=e($app['first_name'].' '.($app['middle_name']?$app['middle_name'].' ':'').$app['last_name'])?></td></tr>
        <tr><td>Application Number:</td><td><?=e($app['application_number'])?></td></tr>
        <tr><td>Grade Applied For:</td><td><?=e($app['grade_applying_for'])?></td></tr>
        <tr><td>Academic Year:</td><td><?=e($app['academic_year'])?></td></tr>
        <tr><td>Exam Date:</td><td><strong><?=e($examDate)?></strong></td></tr>
        <tr><td>Exam Time:</td><td><strong><?=e($examTime)?></strong></td></tr>
        <tr><td>Venue:</td><td>KARN HIGH SCHOOL, Karnplay, Nimba</td></tr>
      </table>
    </div>
    <p>Please ensure that the applicant arrives at least <strong>30 minutes before</strong> the scheduled examination time. The applicant must bring this letter and a valid identification document.</p>
    <p>This letter confirms eligibility for the entrance examination only and does not constitute a final admission offer. Final admission will be determined based on the examination results and available spaces.</p>
    <p>Should you require any further information, please contact the Admissions Office at <strong><?=e($phone1)?></strong> during school hours (Monday–Friday, 8:00am–4:00pm).</p>
    <p>We look forward to welcoming <strong><?=e($app['first_name'])?></strong> to our school community.</p>
    <p>Yours sincerely,</p>
  </div>
  <div class="signature-section">
    <div class="sig-block"><div style="height:48px"></div><div class="sig-line">Registrar<br><?=e($school)?></div></div>
    <div class="sig-block"><div style="height:48px"></div><div class="sig-line">Principal<br><?=e($school)?></div></div>
  </div>
  <div class="footer-note">This is an official document of <?=e($school)?>. &nbsp;|&nbsp; Ref: <?=e($app['entrance_letter_ref'])?> &nbsp;|&nbsp; Generated: <?=date('F d, Y')?></div>
</div>
</body></html>
