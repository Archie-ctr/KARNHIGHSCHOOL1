<?php
require_once dirname(__DIR__).'/config/db.php';
requireAuth();
$pdo=db(); $stdId=(int)($_GET['student_id']??0); $ayId=(int)($_GET['ay_id']??currentAcademicYearId());
if(!$stdId) die('Invalid.');
// Students can only see their own
if(isStudent()){
    $myStd=(int)$pdo->query("SELECT id FROM students WHERE user_id=".currentUser()['id'])->fetchColumn();
    if($myStd!==$stdId) die('Access denied.');
}
$student=$pdo->query("SELECT s.*,g.name grade_name,c.name class_name FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id LEFT JOIN classes c ON c.id=s.current_class_id WHERE s.id=$stdId")->fetch();
if(!$student) die('Student not found.');
$rc=$pdo->query("SELECT * FROM report_cards WHERE student_id=$stdId AND academic_year_id=$ayId LIMIT 1")->fetch();
if(!$rc||$rc['status']!=='published') die('Report card not yet published.');
$ay=$pdo->query("SELECT name FROM academic_years WHERE id=$ayId")->fetchColumn();
$subjects=$pdo->prepare("SELECT DISTINCT s.id,s.name FROM assessment_scores asc2 JOIN subjects s ON s.id=asc2.subject_id WHERE asc2.student_id=? AND asc2.academic_year_id=? ORDER BY s.name"); $subjects->execute([$stdId,$ayId]); $subjects=$subjects->fetchAll();
$scoreData=[];
foreach($subjects as $sub){$sc=$pdo->prepare("SELECT ac.name,ac.sequence,asc2.marks_obtained,asc2.max_marks FROM assessment_scores asc2 JOIN assessment_configs ac ON ac.id=asc2.assessment_config_id WHERE asc2.student_id=? AND asc2.subject_id=? AND asc2.academic_year_id=? ORDER BY ac.sequence");$sc->execute([$stdId,$sub['id'],$ayId]);$scoreData[$sub['id']]=$sc->fetchAll();}
$school=setting('school_name','KARN HIGH SCHOOL');
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Report Card — <?=e($student['first_name'].' '.$student['last_name'])?></title>
<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:Arial,sans-serif;background:#f5f5f5;padding:16px;font-size:11pt}.page{background:#fff;max-width:800px;margin:0 auto;padding:18mm 16mm;box-shadow:0 2px 12px rgba(0,0,0,.1)}.rh{display:flex;align-items:center;gap:14px;border-bottom:3px solid #ac2443;padding-bottom:12px;margin-bottom:10px}.rh img{width:56px;height:56px;border-radius:8px;object-fit:cover}.sn{font-size:17px;font-weight:700;color:#ac2443}.ss{font-size:11px;color:#666}.title{text-align:center;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin:10px 0;background:#ac2443;color:#fff;padding:6px}.info{display:grid;grid-template-columns:1fr 1fr;gap:4px 20px;font-size:11px;margin-bottom:10px;padding:8px;background:#f7e8ec;border-radius:4px}.info span{padding:2px 0}table{width:100%;border-collapse:collapse;font-size:10pt;margin-bottom:10px}th{background:#ac2443;color:#fff;padding:5px 6px;text-align:center;font-size:9pt}th:first-child{text-align:left}td{padding:4px 6px;border:1px solid #ddd;text-align:center}td:first-child{text-align:left;font-weight:600}.tr-avg{background:#f7e8ec;font-weight:700}.att{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:10px}.att-box{text-align:center;padding:8px;background:#f5f5f5;border-radius:4px}.att-box strong{display:block;font-size:16px;color:#ac2443}.comments{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px;font-size:10.5pt}.comment-box{border:1px solid #ddd;border-radius:4px;padding:8px}.comment-box strong{display:block;margin-bottom:4px;color:#ac2443}.sigs{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-top:20px}.sig{text-align:center}.sig-line{border-top:1px solid #333;padding-top:5px;font-size:10px;color:#555}.footer{text-align:center;font-size:9px;color:#999;margin-top:10px;border-top:1px solid #eee;padding-top:8px}@media print{body{background:#fff;padding:0}.page{box-shadow:none;max-width:none;padding:10mm 12mm}.no-print{display:none}}</style>
</head><body>
<div class="no-print" style="max-width:800px;margin:0 auto 10px;display:flex;gap:8px">
  <button onclick="window.print()" style="padding:8px 16px;background:#ac2443;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px">🖨️ Print / Save PDF</button>
  <a href="javascript:history.back()" style="padding:8px 16px;border:1px solid #ddd;border-radius:6px;font-size:13px;color:#555;text-decoration:none">← Back</a>
</div>
<div class="page">
  <div class="rh"><img src="<?=BASE_URL?>/assets/images/logo.jpg" alt="KHS"/><div><div class="sn"><?=e($school)?></div><div class="ss">Karnplay, Nimba County, Liberia &nbsp;|&nbsp; <?=e(setting('school_phone','+231 886 417 711'))?></div></div></div>
  <div class="title">Student Report Card — Academic Year <?=e($ay)?></div>
  <div class="info">
    <span><strong>Name:</strong> <?=e($student['first_name'].($student['middle_name']?' '.$student['middle_name']:'').' '.$student['last_name'])?></span>
    <span><strong>Student ID:</strong> <?=e($student['student_id'])?></span>
    <span><strong>Grade/Class:</strong> <?=e($student['grade_name']??'').($student['class_name']?' / '.e($student['class_name']):'')?></span>
    <span><strong>Academic Year:</strong> <?=e($ay)?></span>
  </div>
  <table>
    <thead><tr><th>Subject</th><th>1st P.</th><th>2nd P.</th><th>3rd P.</th><th>Sem1 Exam</th><th>4th P.</th><th>5th P.</th><th>6th P.</th><th>Sem2 Exam</th><th>Avg</th><th>Grd</th></tr></thead>
    <tbody>
      <?php foreach($subjects as $sub):
        $cols=['1st Period'=>'—','2nd Period'=>'—','3rd Period'=>'—','Semester 1 Examination'=>'—','4th Period'=>'—','5th Period'=>'—','6th Period'=>'—','Semester 2 Examination'=>'—'];
        $vals=[];
        foreach($scoreData[$sub['id']] as $sc){foreach(array_keys($cols) as $k){if(stripos($sc['name'],$k)!==false){$p=$sc['max_marks']>0?round($sc['marks_obtained']/$sc['max_marks']*100,1):null;$cols[$k]=$p??'—';if($p!==null)$vals[]=$p;}}}
        $avg=count($vals)?round(array_sum($vals)/count($vals),1):null; $gl=$avg!==null?gradeLetter($avg,$ayId):'—';
      ?>
      <tr><td><?=e($sub['name'])?></td><?php foreach($cols as $v):?><td><?=$v?></td><?php endforeach;?><td><strong><?=$avg??'—'?></strong></td><td style="color:<?=in_array($gl,['A','B','C','D'])?'green':'red'?>;font-weight:700"><?=$gl?></td></tr>
      <?php endforeach;?>
      <tr class="tr-avg"><td>YEARLY AVERAGE</td><td colspan="8"></td><td><?=$rc['yearly_average']?round($rc['yearly_average'],1).'%':'—'?></td><td><?=$rc['yearly_average']?gradeLetter((float)$rc['yearly_average'],$ayId):'—'?></td></tr>
    </tbody>
  </table>
  <div class="att">
    <div class="att-box"><strong><?=$rc['days_present']??0?></strong>Days Present</div>
    <div class="att-box"><strong><?=$rc['days_absent']??0?></strong>Days Absent</div>
    <div class="att-box"><strong><?=$rc['days_tardy']??0?></strong>Times Tardy</div>
    <div class="att-box"><strong><?=$rc['attendance_pct']??'—'?>%</strong>Attendance</div>
  </div>
  <div class="comments">
    <div class="comment-box"><strong>Conduct:</strong> <?=e($rc['conduct']??'—')?><br><strong>Teacher's Comment:</strong><br><?=nl2br(e($rc['teacher_comment']??'No comment.'))?></div>
    <div class="comment-box"><strong>Principal's Comment:</strong><br><?=nl2br(e($rc['principal_comment']??'No comment.'))?><br><br><strong>Promotion:</strong> <span style="color:#ac2443;font-weight:700"><?=e($rc['promotion_status']??'Pending')?></span></div>
  </div>
  <div class="sigs">
    <div class="sig"><div style="height:36px"></div><div class="sig-line">Class Teacher</div></div>
    <div class="sig"><div style="height:36px"></div><div class="sig-line">Academic Dean</div></div>
    <div class="sig"><div style="height:36px"></div><div class="sig-line">Principal</div></div>
  </div>
  <div class="footer"><?=e($school)?> &nbsp;|&nbsp; Karnplay, Nimba County, Liberia &nbsp;|&nbsp; Generated: <?=date('F d, Y')?></div>
</div>
</body></html>
