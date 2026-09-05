<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('student');
$pdo=db(); $user=currentUser(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
$student=$pdo->prepare("SELECT s.*,g.name grade_name,c.name class_name FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id LEFT JOIN classes c ON c.id=s.current_class_id WHERE s.user_id=? LIMIT 1"); $student->execute([$user['id']]); $student=$student->fetch();
if(!$student){redirect(BASE_URL.'/portal/student/');}

// Get all approved/submitted scores
$scores=$pdo->prepare("SELECT ac.name cfg_name,ac.sequence,ac.max_marks,s.name subj_name,asc2.marks_obtained,asc2.status FROM assessment_scores asc2 JOIN assessment_configs ac ON ac.id=asc2.assessment_config_id JOIN subjects s ON s.id=asc2.subject_id WHERE asc2.student_id=? AND asc2.academic_year_id=? ORDER BY s.name,ac.sequence");
$scores->execute([$student['id'],$ayId]); $scores=$scores->fetchAll();

// Group by subject
$bySubject=[];
foreach($scores as $sc) $bySubject[$sc['subj_name']][$sc['cfg_name']]=$sc;
$configs=[];
foreach($scores as $sc) $configs[$sc['cfg_name']]=$sc['cfg_name'];
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>My Results — Student Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/></head><body>
<div class="portal-grid">
<aside class="portal-sidebar">
  <div class="portal-brand"><div class="brand"><img src="<?=BASE_URL?>/assets/images/logo.jpg" alt="KHS"/><span><strong>KHS</strong><small>Student Portal</small></span></div></div>
  <nav class="portal-nav">
    <a href="<?=BASE_URL?>/portal/student/">🏠 Dashboard</a>
    <a href="<?=BASE_URL?>/portal/student/my_results.php" class="active">📊 My Results</a>
    <a href="<?=BASE_URL?>/portal/student/attendance.php">📆 Attendance</a>
    <a href="<?=BASE_URL?>/portal/student/report_card.php">📑 Report Card</a>
    <a href="<?=BASE_URL?>/portal/student/fees.php">💰 Fees</a>
    <a href="<?=BASE_URL?>/portal/student/timetable.php">📅 Timetable</a>
  </nav>
  <div style="border-top:1px solid var(--line);padding:12px"><a href="<?=BASE_URL?>/admin/logout.php" style="color:var(--error);font-size:13px;font-weight:600">Sign Out</a></div>
</aside>
<div class="portal-content">
  <div class="page-heading">
    <div><h1>My Results</h1><p><?=e($student['grade_name']??'')?> &mdash; <?=e($ay)?></p></div>
  </div>
  <?php if(empty($bySubject)):?>
  <div style="background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);padding:40px;text-align:center"><div style="font-size:36px;margin-bottom:12px">📊</div><p style="color:var(--ink-soft)">No results available yet for <?=e($ay)?>. Results will appear here once your teachers enter and submit marks.</p></div>
  <?php else:?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Subject</th>
          <?php foreach($configs as $cfg):?><th><?=e($cfg)?></th><?php endforeach;?>
          <th>Average</th><th>Grade</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($bySubject as $subj=>$cfgData):
          $vals=array_filter(array_map(fn($d)=>$d['marks_obtained']!==null?($d['marks_obtained']/$d['max_marks']*100):null,$cfgData),fn($v)=>$v!==null);
          $avg=count($vals)?round(array_sum($vals)/count($vals),1):null;
          $gl=$avg!==null?gradeLetter($avg,$ayId):'—';
        ?>
        <tr>
          <td><strong><?=e($subj)?></strong></td>
          <?php foreach($configs as $cfg): $d=$cfgData[$cfg]??null;?>
          <td><?=$d&&$d['marks_obtained']!==null?fmtMark($d['marks_obtained']).'/'.$d['max_marks']:'—'?></td>
          <?php endforeach;?>
          <td><strong><?=$avg!==null?$avg.'%':'—'?></strong></td>
          <td><span class="status <?=in_array($gl,['A','B','C','D'])?'approved':'warning'?>"><?=e($gl)?></span></td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
  </div>
  <?php endif;?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script></body></html>
