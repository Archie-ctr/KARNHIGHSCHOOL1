<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['teacher','class_teacher']);
$pdo=db(); $user=currentUser(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();

// Get teacher record
$teacherRow=$pdo->prepare("SELECT id FROM teachers WHERE user_id=? LIMIT 1");
$teacherRow->execute([$user['id']]); $teacherId=(int)($teacherRow->fetchColumn()??0);

// Get classes this teacher is assigned to
$myClasses=$pdo->prepare("SELECT DISTINCT c.id,c.name FROM teacher_assignments ta JOIN classes c ON c.id=ta.class_id WHERE ta.teacher_id=? AND ta.academic_year_id=? ORDER BY c.name");
$myClasses->execute([$teacherId,$ayId]); $myClasses=$myClasses->fetchAll();

$selClass=(int)($_GET['class_id']??0);
// If no class selected and teacher only has one, auto-select it
if (!$selClass && count($myClasses)===1) $selClass=$myClasses[0]['id'];

$q=trim($_GET['q']??'');
$students=[];
if ($selClass) {
    $wsql=$q?"AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ?)":'';
    $stmt=$pdo->prepare("SELECT s.*,g.name grade_name FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id WHERE s.current_class_id=? AND s.status='Active' $wsql ORDER BY s.last_name,s.first_name");
    $params=[$selClass]; if($q){$like="%$q%";$params=array_merge($params,[$like,$like,$like]);}
    $stmt->execute($params); $students=$stmt->fetchAll();
}

$className=$selClass ? ($pdo->query("SELECT name FROM classes WHERE id=$selClass")->fetchColumn()??'') : '';
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>My Students — Teacher Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/></head><body>
<div class="portal-grid">
<aside class="portal-sidebar">
  <div class="portal-brand"><div class="brand"><img src="<?=BASE_URL?>/assets/images/logo.jpg" alt="KHS"/><span><strong>KHS</strong><small>Teacher Portal</small></span></div></div>
  <nav class="portal-nav">
    <a href="<?=BASE_URL?>/portal/teacher/">🏠 Dashboard</a>
    <a href="<?=BASE_URL?>/portal/teacher/my_classes.php">🏫 My Classes</a>
    <a href="<?=BASE_URL?>/portal/teacher/enter_marks.php">✏️ Enter Marks</a>
    <a href="<?=BASE_URL?>/portal/teacher/take_attendance.php">📆 Attendance</a>
    <a href="<?=BASE_URL?>/portal/teacher/students.php" class="active">🎓 Students</a>
    <a href="<?=BASE_URL?>/portal/teacher/timetable.php">📅 Timetable</a>
    <a href="<?=BASE_URL?>/portal/teacher/announcements.php">📢 Announcements</a>
  </nav>
  <div style="border-top:1px solid var(--line);padding:12px"><a href="<?=BASE_URL?>/admin/logout.php" style="color:var(--error);font-size:13px;font-weight:600">Sign Out</a></div>
</aside>
<div class="portal-content">
  <div class="page-heading">
    <div><h1>My Students</h1><p><?=$selClass?e($className).' — ':''?><?=e($ay)?></p></div>
  </div>
  <?=renderFlash()?>

  <!-- Class selector -->
  <?php if (count($myClasses)>1): ?>
  <div class="filter-row" style="margin-bottom:20px">
    <?php foreach($myClasses as $c): ?>
    <a href="?class_id=<?=$c['id']?>" class="filter-button" style="<?=$selClass==$c['id']?'background:var(--primary);color:#fff;border-color:var(--primary)':''?>"><?=e($c['name'])?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($selClass): ?>
  <!-- Search -->
  <form method="get" class="filter-row" style="margin-bottom:16px">
    <input type="hidden" name="class_id" value="<?=$selClass?>"/>
    <div class="table-search">🔍<input type="search" name="q" placeholder="Search by name or ID…" value="<?=e($q)?>"/></div>
    <button type="submit" class="button button-primary button-sm">Search</button>
    <?php if($q): ?><a href="?class_id=<?=$selClass?>" class="filter-button">Clear</a><?php endif; ?>
  </form>

  <div class="stat-mini-row" style="margin-bottom:16px">
    <div class="stat-mini-item"><strong><?=count($students)?></strong><span><?=$q?'Results':'Students in class'?></span></div>
  </div>

  <div class="table-wrap">
    <table>
      <thead><tr><th>Student</th><th>Student ID</th><th>Gender</th><th>Phone</th><th>Guardian</th><th>Status</th></tr></thead>
      <tbody>
        <?php if (empty($students)): ?>
        <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--ink-faint)">
          <?=$q?'No students found matching your search.':'No active students in this class.'?>
        </td></tr>
        <?php else: ?>
        <?php foreach ($students as $s):
          $ini=strtoupper(substr($s['first_name'],0,1).substr($s['last_name'],0,1));
        ?>
        <tr>
          <td>
            <div class="person">
              <div class="avatar-sm" style="background:var(--accent-soft);color:var(--accent)"><?=e($ini)?></div>
              <div>
                <strong><?=e($s['first_name'].' '.$s['last_name'])?></strong>
                <?php if($s['email']): ?><div style="font-size:11px;color:var(--ink-faint)"><?=e($s['email'])?></div><?php endif; ?>
              </div>
            </div>
          </td>
          <td class="muted"><?=e($s['student_id'])?></td>
          <td><?=e($s['gender']??'—')?></td>
          <td class="muted"><?=e($s['phone']??'—')?></td>
          <td class="muted"><?=e($s['guardian_name']??'—')?></td>
          <td><?=statusBadge($s['status'])?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php elseif (empty($myClasses)): ?>
  <div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
    <div style="font-size:36px;margin-bottom:12px">🏫</div>
    <h3>No classes assigned</h3>
    <p style="color:var(--ink-soft)">You have not been assigned to any class yet. Please contact your academic dean.</p>
  </div>
  <?php else: ?>
  <div style="text-align:center;padding:48px;color:var(--ink-faint)">Select a class above to view students.</div>
  <?php endif; ?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script></body></html>
