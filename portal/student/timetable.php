<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('student');
$pdo=db(); $user=currentUser(); $ayId=currentAcademicYearId();
$student=$pdo->prepare("SELECT id,current_class_id FROM students WHERE user_id=? LIMIT 1"); $student->execute([$user['id']]); $student=$student->fetch();
$timetable=[]; $days=['Monday','Tuesday','Wednesday','Thursday','Friday'];
if($student&&$student['current_class_id']){
    $tt=$pdo->prepare("SELECT tt.*,s.name sname,CONCAT(t.first_name,' ',t.last_name) tname FROM timetable tt JOIN subjects s ON s.id=tt.subject_id LEFT JOIN teachers t ON t.id=tt.teacher_id WHERE tt.class_id=? AND tt.academic_year_id=? ORDER BY tt.day_of_week,tt.period_slot");
    $tt->execute([$student['current_class_id'],$ayId]); $tt=$tt->fetchAll();
    foreach($tt as $row) $timetable[$row['day_of_week']][$row['period_slot']]=$row;
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/><title>Timetable — Student Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/><link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/></head><body>
<div class="portal-grid">
<aside class="portal-sidebar"><div class="portal-brand"><div class="brand"><img src="<?=BASE_URL?>/assets/images/logo.jpg" alt="KHS"/><span><strong>KHS</strong><small>Student Portal</small></span></div></div>
<nav class="portal-nav"><a href="<?=BASE_URL?>/portal/student/">🏠 Dashboard</a><a href="<?=BASE_URL?>/portal/student/my_results.php">📊 My Results</a><a href="<?=BASE_URL?>/portal/student/attendance.php">📆 Attendance</a><a href="<?=BASE_URL?>/portal/student/report_card.php">📑 Report Card</a><a href="<?=BASE_URL?>/portal/student/fees.php">💰 Fees</a><a href="<?=BASE_URL?>/portal/student/timetable.php" class="active">📅 Timetable</a></nav>
<div style="border-top:1px solid var(--line);padding:12px"><a href="<?=BASE_URL?>/admin/logout.php" style="color:var(--error);font-size:13px;font-weight:600">Sign Out</a></div></aside>
<div class="portal-content">
  <div class="page-heading"><div><h1>My Timetable</h1></div></div>
  <?php if(empty($timetable)):?>
  <div style="text-align:center;padding:40px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)"><div style="font-size:36px;margin-bottom:12px">📅</div><p style="color:var(--ink-soft)">Timetable not yet set up. Please check back later.</p></div>
  <?php else:?>
  <div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:13px">
    <thead><tr><th style="padding:10px;background:var(--primary);color:#fff;border-right:1px solid rgba(255,255,255,.2)">Period</th><?php foreach($days as $d):?><th style="padding:10px;background:var(--primary);color:#fff;border-right:1px solid rgba(255,255,255,.2)"><?=$d?></th><?php endforeach;?></tr></thead>
    <tbody><?php for($p=1;$p<=8;$p++):?><tr style="border-bottom:1px solid var(--line-soft)">
      <td style="padding:10px 14px;font-weight:700;background:var(--bg);border-right:1px solid var(--line)">P<?=$p?></td>
      <?php foreach([1,2,3,4,5] as $d): $cell=$timetable[$d][$p]??null;?>
      <td style="padding:10px 14px;border-right:1px solid var(--line-soft)">
        <?php if($cell):?><div style="font-weight:600;font-size:13px"><?=e($cell['sname'])?></div><?php if($cell['tname']):?><div style="font-size:11px;color:var(--ink-faint)"><?=e($cell['tname'])?></div><?php endif;?><?php if($cell['room']):?><div style="font-size:11px;color:var(--primary)"><?=e($cell['room'])?></div><?php endif;?>
        <?php else:?><span style="color:var(--line)">—</span><?php endif;?>
      </td>
      <?php endforeach;?></tr><?php endfor;?></tbody>
  </table></div>
  <?php endif;?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script></body></html>
