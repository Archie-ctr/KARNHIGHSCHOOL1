<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('teacher');
$pdo=db(); $user=currentUser(); $ayId=currentAcademicYearId();
$teacherId=(int)($pdo->query("SELECT id FROM teachers WHERE user_id={$user['id']} LIMIT 1")->fetchColumn()??0);
$selClass=(int)($_GET['class_id']??0);

$myClasses=$pdo->prepare("SELECT DISTINCT c.id,c.name,g.name grade_name,(SELECT COUNT(*) FROM students s WHERE s.current_class_id=c.id AND s.status='Active') enrol FROM teacher_assignments ta JOIN classes c ON c.id=ta.class_id JOIN grades g ON g.id=c.grade_id WHERE ta.teacher_id=? AND ta.academic_year_id=? ORDER BY c.name");
$myClasses->execute([$teacherId,$ayId]); $myClasses=$myClasses->fetchAll();

$students=[];
if($selClass){
    $sts=$pdo->prepare("SELECT s.*,g.name grade_name FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id WHERE s.current_class_id=? AND s.status='Active' ORDER BY s.last_name,s.first_name");
    $sts->execute([$selClass]); $students=$sts->fetchAll();
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>My Classes — Teacher Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/></head><body>
<div class="portal-grid">
<aside class="portal-sidebar">
  <div class="portal-brand"><div class="brand"><img src="<?=BASE_URL?>/assets/images/logo.jpg" alt="KHS"/><span><strong>KHS</strong><small>Teacher Portal</small></span></div></div>
  <nav class="portal-nav">
    <a href="<?=BASE_URL?>/portal/teacher/">🏠 Dashboard</a>
    <a href="<?=BASE_URL?>/portal/teacher/my_classes.php" class="active">🏫 My Classes</a>
    <a href="<?=BASE_URL?>/portal/teacher/enter_marks.php">✏️ Enter Marks</a>
    <a href="<?=BASE_URL?>/portal/teacher/take_attendance.php">📆 Attendance</a>
    <a href="<?=BASE_URL?>/portal/teacher/students.php">🎓 Students</a>
  </nav>
  <div style="border-top:1px solid var(--line);padding:12px"><a href="<?=BASE_URL?>/admin/logout.php" style="color:var(--error);font-size:13px;font-weight:600">Sign Out</a></div>
</aside>
<div class="portal-content">
  <div class="page-heading"><div><h1>My Classes</h1></div></div>
  <div class="filter-row" style="margin-bottom:20px">
    <?php foreach($myClasses as $c):?>
    <a href="?class_id=<?=$c['id']?>" class="filter-button <?=$selClass==$c['id']?'active':''?>" style="<?=$selClass==$c['id']?'background:var(--primary);color:#fff;border-color:var(--primary)':''?>"><?=e($c['name'])?> (<?=$c['enrol']?>)</a>
    <?php endforeach;?>
  </div>
  <?php if($selClass&&!empty($students)):?>
  <div class="table-wrap"><table>
    <thead><tr><th>Student</th><th>Student ID</th><th>Gender</th><th>Phone</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach($students as $st): $ini=strtoupper(substr($st['first_name'],0,1).substr($st['last_name'],0,1));?>
      <tr>
        <td><div class="person"><div class="avatar-sm" style="background:var(--accent-soft);color:var(--accent)"><?=e($ini)?></div><strong><?=e($st['first_name'].' '.$st['last_name'])?></strong></div></td>
        <td class="muted"><?=e($st['student_id'])?></td>
        <td><?=e($st['gender']??'—')?></td>
        <td class="muted"><?=e($st['phone']??'—')?></td>
        <td><?=statusBadge($st['status'])?></td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table></div>
  <?php elseif(empty($myClasses)):?>
  <div style="text-align:center;padding:40px;color:var(--ink-faint)">No classes assigned yet. Contact your academic dean.</div>
  <?php endif;?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script></body></html>
