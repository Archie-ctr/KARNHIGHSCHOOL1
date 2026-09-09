<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['teacher','class_teacher']);

$activePage = 'timetable';
$pdo = db(); $user = currentUser(); $ayId = currentAcademicYearId(); $ay = currentAcademicYearName();
$teacherRow = $pdo->prepare("SELECT * FROM teachers WHERE user_id=? LIMIT 1");
$teacherRow->execute([$user['id']]); $teacher = $teacherRow->fetch();
$teacherId = $teacher ? (int)$teacher['id'] : 0;

$myClassIds = $pdo->prepare("SELECT DISTINCT class_id FROM teacher_assignments WHERE teacher_id=? AND academic_year_id=?");
$myClassIds->execute([$teacherId,$ayId]); $myClassIds=array_column($myClassIds->fetchAll(),'class_id');

$timetable = []; $days = [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday'];
if (!empty($myClassIds)) {
    $placeholders = implode(',', array_fill(0,count($myClassIds),'?'));
    $tt = $pdo->prepare("SELECT tt.*,s.name sname,c.name cname FROM timetable tt JOIN subjects s ON s.id=tt.subject_id JOIN classes c ON c.id=tt.class_id WHERE tt.teacher_id=? AND tt.class_id IN ($placeholders) AND tt.academic_year_id=? ORDER BY tt.day_of_week,tt.period_slot");
    $tt->execute(array_merge([$teacherId],$myClassIds,[$ayId]));
    foreach ($tt->fetchAll() as $row) $timetable[$row['day_of_week']][$row['period_slot']] = $row;
}
$myClasses = $pdo->prepare("SELECT DISTINCT c.id,c.name FROM teacher_assignments ta JOIN classes c ON c.id=ta.class_id WHERE ta.teacher_id=? AND ta.academic_year_id=? ORDER BY c.name");
$myClasses->execute([$teacherId,$ayId]); $myClasses=$myClasses->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Timetable — Teacher Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/></head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">
  <div class="page-heading">
    <div><h1>My Timetable</h1><p><?=e($ay)?></p></div>
    <button onclick="window.print()" class="button button-secondary button-sm">🖨️ Print</button>
  </div>
  <?php if (empty($timetable)): ?>
  <div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
    <div style="font-size:36px;margin-bottom:12px">📅</div>
    <h3>No timetable set up yet</h3>
    <p style="color:var(--ink-soft)">Your timetable has not been configured. Please contact your administrator.</p>
  </div>
  <?php else: ?>
  <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:600px">
      <thead>
        <tr>
          <th style="padding:10px 12px;background:var(--primary);color:#fff;border-right:1px solid rgba(255,255,255,.2);text-align:left">Period</th>
          <?php foreach($days as $d): ?>
          <th style="padding:10px 12px;background:var(--primary);color:#fff;border-right:1px solid rgba(255,255,255,.2);text-align:center"><?=$d?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php for($p=1;$p<=8;$p++): ?>
        <tr style="border-bottom:1px solid var(--line-soft)">
          <td style="padding:10px 12px;font-weight:700;background:var(--bg);border-right:1px solid var(--line);font-size:12px;color:var(--ink-soft)">Period <?=$p?></td>
          <?php foreach([1,2,3,4,5] as $d): $cell=$timetable[$d][$p]??null;?>
          <td style="padding:10px 12px;border-right:1px solid var(--line-soft);text-align:center;vertical-align:top">
            <?php if ($cell): ?>
            <div style="font-weight:700;font-size:13px"><?=e($cell['sname'])?></div>
            <div style="font-size:11px;color:var(--primary);font-weight:600;margin-top:2px"><?=e($cell['cname'])?></div>
            <?php if($cell['room']):?><div style="font-size:10px;color:var(--ink-faint)">📍 <?=e($cell['room'])?></div><?php endif;?>
            <?php if($cell['start_time']&&$cell['end_time']):?><div style="font-size:10px;color:var(--ink-faint)"><?=date('g:ia',strtotime($cell['start_time']))?>–<?=date('g:ia',strtotime($cell['end_time']))?></div><?php endif;?>
            <?php else: ?><span style="color:var(--line)">—</span><?php endif;?>
          </td>
          <?php endforeach;?>
        </tr>
        <?php endfor;?>
      </tbody>
    </table>
  </div>
  <div style="margin-top:14px;padding:14px;background:var(--bg2);border-radius:var(--radius-sm);font-size:13px">
    <strong>Your classes:</strong>
    <?php foreach($myClasses as $c):?>
    <span style="background:var(--primary-soft);color:var(--primary);padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;margin:2px;display:inline-block"><?=e($c['name'])?></span>
    <?php endforeach;?>
  </div>
  <?php endif;?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script></body></html>
