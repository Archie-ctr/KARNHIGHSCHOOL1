<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('teacher');
$pdo=$db=db(); $user=currentUser(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();

$teacher=$pdo->prepare("SELECT * FROM teachers WHERE user_id=? LIMIT 1"); $teacher->execute([$user['id']]); $teacher=$teacher->fetch();
if(!$teacher){echo'<p style="font-family:sans-serif;padding:40px">Teacher record not linked to this account. Contact admin.</p>';exit;}

$myClasses=$pdo->prepare("SELECT DISTINCT c.id,c.name,g.name grade_name,(SELECT COUNT(*) FROM students s WHERE s.current_class_id=c.id AND s.status='Active') enrol FROM teacher_assignments ta JOIN classes c ON c.id=ta.class_id JOIN grades g ON g.id=c.grade_id WHERE ta.teacher_id=? AND ta.academic_year_id=? ORDER BY c.name");
$myClasses->execute([$teacher['id'],$ayId]); $myClasses=$myClasses->fetchAll();

$mySubjects=$pdo->prepare("SELECT DISTINCT s.name,s.id FROM teacher_assignments ta JOIN subjects s ON s.id=ta.subject_id WHERE ta.teacher_id=? AND ta.academic_year_id=?");
$mySubjects->execute([$teacher['id'],$ayId]); $mySubjects=$mySubjects->fetchAll();

$pendingMarks=$pdo->prepare("SELECT COUNT(*) FROM assessment_scores WHERE entered_by=? AND status='draft' AND academic_year_id=?");
$pendingMarks->execute([$user['id'],$ayId]); $pending=(int)$pendingMarks->fetchColumn();

$ini=strtoupper(substr($teacher['first_name'],0,1).substr($teacher['last_name'],0,1));
$announcements=$pdo->query("SELECT title,message,published_at FROM announcements WHERE target IN ('all','teachers') AND (expires_at IS NULL OR expires_at>NOW()) ORDER BY published_at DESC LIMIT 4")->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Teacher Portal — KHS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/></head><body>
<div class="portal-grid">
<aside class="portal-sidebar">
  <div class="portal-brand"><div class="brand"><img src="<?=BASE_URL?>/assets/images/logo.jpg" alt="KHS"/><span><strong>KHS</strong><small>Teacher Portal</small></span></div></div>
  <nav class="portal-nav">
    <a href="<?=BASE_URL?>/portal/teacher/" class="active">🏠 Dashboard</a>
    <a href="<?=BASE_URL?>/portal/teacher/my_classes.php">🏫 My Classes</a>
    <a href="<?=BASE_URL?>/portal/teacher/enter_marks.php">✏️ Enter Marks</a>
    <a href="<?=BASE_URL?>/portal/teacher/take_attendance.php">📆 Attendance</a>
    <a href="<?=BASE_URL?>/portal/teacher/students.php">🎓 Students</a>
    <a href="<?=BASE_URL?>/portal/teacher/timetable.php">📅 Timetable</a>
    <a href="<?=BASE_URL?>/portal/teacher/announcements.php">📢 Announcements</a>
  </nav>
  <div style="border-top:1px solid var(--line);padding:12px"><a href="<?=BASE_URL?>/admin/logout.php" style="color:var(--error);font-size:13px;font-weight:600">Sign Out</a></div>
</aside>
<div class="portal-content">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
    <div><h1 style="font-size:26px;font-weight:800;margin-bottom:4px">Hello, <?=e($teacher['first_name'])?>! 👩‍🏫</h1><p style="color:var(--ink-soft)"><?=e($teacher['specialization']??'Teacher')?> &mdash; <?=e($ay)?></p></div>
    <div style="display:flex;align-items:center;gap:12px"><div class="avatar" style="width:46px;height:46px;font-size:16px"><?=e($ini)?></div><div><strong><?=e($teacher['first_name'].' '.$teacher['last_name'])?></strong><div style="font-size:12px;color:var(--ink-faint)"><?=e($teacher['teacher_id'])?></div></div></div>
  </div>

  <div class="metric-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
    <div class="metric-card"><div class="metric-top"><span>My Classes</span><div class="metric-icon">🏫</div></div><strong><?=count($myClasses)?></strong><small><i></i><?=e($ay)?></small></div>
    <div class="metric-card"><div class="metric-top"><span>My Subjects</span><div class="metric-icon">📚</div></div><strong><?=count($mySubjects)?></strong><small><i></i>Assigned</small></div>
    <div class="metric-card"><div class="metric-top"><span>Pending Draft Marks</span><div class="metric-icon">✏️</div></div><strong><?=$pending?></strong><small><i></i>Awaiting submission</small></div>
  </div>

  <div class="quick-grid" style="margin-bottom:24px">
    <a href="<?=BASE_URL?>/portal/teacher/enter_marks.php"    class="quick-item"><span class="qi-icon">✏️</span><div><strong>Enter Marks</strong><small>Record assessment scores</small></div></a>
    <a href="<?=BASE_URL?>/portal/teacher/take_attendance.php" class="quick-item"><span class="qi-icon">📆</span><div><strong>Take Attendance</strong><small>Daily class register</small></div></a>
    <a href="<?=BASE_URL?>/portal/teacher/my_classes.php"     class="quick-item"><span class="qi-icon">🏫</span><div><strong>My Classes</strong><small>View students</small></div></a>
    <a href="<?=BASE_URL?>/portal/teacher/students.php"       class="quick-item"><span class="qi-icon">🎓</span><div><strong>Students</strong><small>Class performance</small></div></a>
  </div>

  <?php if(!empty($myClasses)):?>
  <div class="panel" style="margin-bottom:20px">
    <div class="panel-heading"><div><h3>My Classes</h3><p><?=e($ay)?></p></div><a href="<?=BASE_URL?>/portal/teacher/my_classes.php" class="filter-button">View all →</a></div>
    <div class="table-wrap" style="border:none;border-radius:0"><table>
      <thead><tr><th>Class</th><th>Grade</th><th>Students</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach($myClasses as $cls):?>
        <tr>
          <td><strong><?=e($cls['name'])?></strong></td><td><?=e($cls['grade_name'])?></td><td><?=(int)$cls['enrol']?></td>
          <td><a href="<?=BASE_URL?>/portal/teacher/take_attendance.php?class_id=<?=$cls['id']?>" class="filter-button button-sm">Take Attendance</a></td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table></div>
  </div>
  <?php endif;?>

  <?php if(!empty($announcements)):?>
  <div class="panel">
    <div class="panel-heading"><div><h3>Announcements</h3></div></div>
    <?php foreach($announcements as $ann):?>
    <div class="activity"><span class="activity-dot pink"></span><div><strong><?=e($ann['title'])?></strong><p><?=e(mb_substr($ann['message'],0,100)).(mb_strlen($ann['message'])>100?'…':'')?></p><small><?=date('M d, Y',strtotime($ann['published_at']))?></small></div></div>
    <?php endforeach;?>
  </div>
  <?php endif;?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script></body></html>
