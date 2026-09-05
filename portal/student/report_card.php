<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('student');
$pdo=db(); $user=currentUser(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
$student=$pdo->prepare("SELECT s.*,g.name grade_name,c.name class_name FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id LEFT JOIN classes c ON c.id=s.current_class_id WHERE s.user_id=? LIMIT 1"); $student->execute([$user['id']]); $student=$student->fetch();
if(!$student){redirect(BASE_URL.'/portal/student/');}

$rc=$pdo->query("SELECT * FROM report_cards WHERE student_id={$student['id']} AND academic_year_id=$ayId LIMIT 1")->fetch();
$subjects=[]; $scoreData=[];
if($rc){
    $subs=$pdo->prepare("SELECT DISTINCT s.id,s.name FROM assessment_scores asc2 JOIN subjects s ON s.id=asc2.subject_id WHERE asc2.student_id=? AND asc2.academic_year_id=? ORDER BY s.name"); $subs->execute([$student['id'],$ayId]); $subjects=$subs->fetchAll();
    foreach($subjects as $sub){
        $sc=$pdo->prepare("SELECT ac.name,ac.sequence,asc2.marks_obtained,asc2.max_marks FROM assessment_scores asc2 JOIN assessment_configs ac ON ac.id=asc2.assessment_config_id WHERE asc2.student_id=? AND asc2.subject_id=? AND asc2.academic_year_id=? ORDER BY ac.sequence");
        $sc->execute([$student['id'],$sub['id'],$ayId]); $scoreData[$sub['id']]=$sc->fetchAll();
    }
}
$schoolName=setting('school_name','KARN HIGH SCHOOL');
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Report Card — Student Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/></head><body>
<div class="portal-grid">
<aside class="portal-sidebar">
  <div class="portal-brand"><div class="brand"><img src="<?=BASE_URL?>/assets/images/logo.jpg" alt="KHS"/><span><strong>KHS</strong><small>Student Portal</small></span></div></div>
  <nav class="portal-nav">
    <a href="<?=BASE_URL?>/portal/student/">🏠 Dashboard</a>
    <a href="<?=BASE_URL?>/portal/student/my_results.php">📊 My Results</a>
    <a href="<?=BASE_URL?>/portal/student/attendance.php">📆 Attendance</a>
    <a href="<?=BASE_URL?>/portal/student/report_card.php" class="active">📑 Report Card</a>
    <a href="<?=BASE_URL?>/portal/student/fees.php">💰 Fees</a>
  </nav>
  <div style="border-top:1px solid var(--line);padding:12px"><a href="<?=BASE_URL?>/admin/logout.php" style="color:var(--error);font-size:13px;font-weight:600">Sign Out</a></div>
</aside>
<div class="portal-content">
  <div class="page-heading">
    <div><h1>Report Card</h1><p><?=e($ay)?></p></div>
    <?php if($rc&&$rc['status']==='published'):?>
    <div style="display:flex;gap:8px">
      <a href="<?=BASE_URL?>/letters/report_card_pdf.php?student_id=<?=$student['id']?>&ay_id=<?=$ayId?>" class="button button-secondary button-sm" target="_blank">📄 Download PDF</a>
      <button onclick="printSection('rcView')" class="button button-secondary button-sm">🖨️ Print</button>
    </div>
    <?php endif;?>
  </div>

  <?php if(!$rc||$rc['status']==='draft'):?>
  <div style="background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);padding:40px;text-align:center">
    <div style="font-size:36px;margin-bottom:12px">📑</div>
    <h3 style="margin-bottom:8px">Report card not yet available</h3>
    <p style="color:var(--ink-soft)">Your report card for <?=e($ay)?> has not been published yet. Please check back later or contact the school office.</p>
  </div>
  <?php else:?>
  <div id="rcView" class="report-card-paper">
    <div class="rc-header">
      <img src="<?=BASE_URL?>/assets/images/logo.jpg" alt="KHS" class="rc-logo"/>
      <div style="flex:1"><div class="rc-school-name"><?=e($schoolName)?></div><div class="rc-subtitle">Karnplay, Nimba County, Liberia</div><div style="font-size:14px;font-weight:700;margin-top:6px">STUDENT REPORT CARD — <?=e($ay)?></div></div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 20px;font-size:12px;margin-bottom:14px;padding:10px;background:var(--bg);border-radius:6px">
      <div><strong>Student:</strong> <?=e($student['first_name'].($student['middle_name']?' '.$student['middle_name']:'').' '.$student['last_name'])?></div>
      <div><strong>Student ID:</strong> <?=e($student['student_id'])?></div>
      <div><strong>Grade/Class:</strong> <?=e($student['grade_name']??'').' / '.e($student['class_name']??'')?></div>
      <div><strong>Academic Year:</strong> <?=e($ay)?></div>
    </div>
    <?php if(!empty($subjects)):?>
    <table class="rc-table">
      <thead>
        <tr><th rowspan="2" style="text-align:left">Subject</th><th colspan="4">Semester 1</th><th colspan="4">Semester 2</th><th rowspan="2">Avg</th><th rowspan="2">Grd</th></tr>
        <tr><th>1st P</th><th>2nd P</th><th>3rd P</th><th>Sem Exam</th><th>4th P</th><th>5th P</th><th>6th P</th><th>Sem Exam</th></tr>
      </thead>
      <tbody>
        <?php foreach($subjects as $sub):
          $cols=['1st Period'=>'—','2nd Period'=>'—','3rd Period'=>'—','Semester 1 Examination'=>'—','4th Period'=>'—','5th Period'=>'—','6th Period'=>'—','Semester 2 Examination'=>'—'];
          $vals=[];
          foreach($scoreData[$sub['id']] as $sc){ foreach(array_keys($cols) as $k){ if(stripos($sc['name'],$k)!==false||$sc['name']===$k){ $pct=$sc['max_marks']>0?round($sc['marks_obtained']/$sc['max_marks']*100,1):null; $cols[$k]=$pct??'—'; if($pct!==null)$vals[]=$pct; } } }
          $avg=count($vals)?round(array_sum($vals)/count($vals),1):null; $gl=$avg!==null?gradeLetter($avg,$ayId):'—';
        ?>
        <tr><td style="text-align:left;font-weight:600"><?=e($sub['name'])?></td><?php foreach($cols as $v):?><td><?=$v?></td><?php endforeach;?><td><strong><?=$avg??'—'?></strong></td><td style="color:<?=in_array($gl,['A','B','C','D'])?'var(--green)':'var(--error)'?>;font-weight:700"><?=$gl?></td></tr>
        <?php endforeach;?>
      </tbody>
    </table>
    <?php endif;?>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:14px 0;font-size:12px">
      <div style="text-align:center;padding:8px;background:var(--bg);border-radius:6px"><strong style="display:block;font-size:16px"><?=$rc['days_present']??0?></strong>Days Present</div>
      <div style="text-align:center;padding:8px;background:var(--bg);border-radius:6px"><strong style="display:block;font-size:16px"><?=$rc['days_absent']??0?></strong>Days Absent</div>
      <div style="text-align:center;padding:8px;background:var(--bg);border-radius:6px"><strong style="display:block;font-size:16px"><?=$rc['days_tardy']??0?></strong>Times Tardy</div>
      <div style="text-align:center;padding:8px;background:var(--primary-soft);border-radius:6px"><strong style="display:block;font-size:16px;color:var(--primary)"><?=$rc['yearly_average']?round($rc['yearly_average'],1).'%':'—'?></strong>Overall Avg</div>
    </div>
    <?php if($rc['teacher_comment']||$rc['principal_comment']):?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:12px;margin-bottom:12px">
      <?php if($rc['teacher_comment']):?><div style="padding:8px;border:1px solid var(--line);border-radius:5px"><strong>Teacher:</strong> <?=nl2br(e($rc['teacher_comment']))?></div><?php endif;?>
      <?php if($rc['principal_comment']):?><div style="padding:8px;border:1px solid var(--line);border-radius:5px"><strong>Principal:</strong> <?=nl2br(e($rc['principal_comment']))?></div><?php endif;?>
    </div>
    <?php endif;?>
    <div style="font-size:13px;font-weight:700;color:var(--primary);margin-bottom:14px">Promotion: <?=e($rc['promotion_status']??'Pending')?></div>
    <div class="rc-signature"><div class="rc-sig-line">Class Teacher</div><div class="rc-sig-line">Academic Dean</div><div class="rc-sig-line">Principal</div></div>
    <div style="text-align:center;margin-top:12px;font-size:10px;color:var(--ink-faint)"><?=e($schoolName)?> &middot; Karnplay, Nimba, Liberia</div>
  </div>
  <?php endif;?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script></body></html>
