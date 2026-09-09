<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['teacher','class_teacher']);

$activePage = 'classes';
$pdo = db(); $user = currentUser(); $ayId = currentAcademicYearId(); $ay = currentAcademicYearName();
$teacherRow = $pdo->prepare("SELECT * FROM teachers WHERE user_id=? LIMIT 1");
$teacherRow->execute([$user['id']]); $teacher = $teacherRow->fetch();
$teacherId = $teacher ? (int)$teacher['id'] : 0;

$selClass = (int)($_GET['class_id'] ?? 0);

$myClasses = $pdo->prepare("SELECT DISTINCT c.id,c.name,g.name grade_name,(SELECT COUNT(*) FROM students s WHERE s.current_class_id=c.id AND s.status='Active') enrol FROM teacher_assignments ta JOIN classes c ON c.id=ta.class_id JOIN grades g ON g.id=c.grade_id WHERE ta.teacher_id=? AND ta.academic_year_id=? ORDER BY g.sequence,c.name");
$myClasses->execute([$teacherId,$ayId]); $myClasses=$myClasses->fetchAll();
if (!$selClass && count($myClasses)===1) $selClass=$myClasses[0]['id'];

$students = []; $classSubjects = [];
if ($selClass) {
    $sts = $pdo->prepare("SELECT s.*,g.name grade_name FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id WHERE s.current_class_id=? AND s.status='Active' ORDER BY s.last_name,s.first_name");
    $sts->execute([$selClass]); $students=$sts->fetchAll();
    $csubs = $pdo->prepare("SELECT DISTINCT sub.name FROM teacher_assignments ta JOIN subjects sub ON sub.id=ta.subject_id WHERE ta.teacher_id=? AND ta.class_id=? AND ta.academic_year_id=? ORDER BY sub.name");
    $csubs->execute([$teacherId,$selClass,$ayId]); $classSubjects=$csubs->fetchAll(PDO::FETCH_COLUMN);
}
$className = $selClass ? ($pdo->query("SELECT name FROM classes WHERE id=$selClass")->fetchColumn()??'') : '';
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>My Classes — Teacher Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/></head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">
  <div class="page-heading">
    <div><h1>My Classes</h1><p><?= $selClass ? e($className).' — '.count($students).' students' : e($ay) ?></p></div>
  </div>
  <!-- Class tabs -->
  <div class="filter-row" style="margin-bottom:20px">
    <?php foreach($myClasses as $c): $active=$selClass==$c['id']; ?>
    <a href="?class_id=<?=$c['id']?>" class="filter-button" style="<?=$active?'background:var(--primary);color:#fff;border-color:var(--primary)':''?>">
      <?=e($c['name'])?> <span style="font-size:11px;opacity:.75">(<?=$c['enrol']?>)</span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php if ($selClass && !empty($classSubjects)): ?>
  <div style="margin-bottom:14px;font-size:13px;color:var(--ink-soft)">
    <strong>Teaching:</strong>
    <?php foreach($classSubjects as $cs): ?>
    <span style="background:var(--primary-soft);color:var(--primary);padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;margin:2px;display:inline-block"><?=e($cs)?></span>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php if ($selClass && !empty($students)): ?>
  <div class="table-wrap"><table>
    <thead><tr><th>Student</th><th>Student ID</th><th>Gender</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($students as $st): $ini=strtoupper(substr($st['first_name'],0,1).substr($st['last_name'],0,1));?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div class="avatar" style="width:32px;height:32px;font-size:11px;flex-shrink:0"><?=e($ini)?></div>
            <strong><?=e($st['first_name'].' '.$st['last_name'])?></strong>
          </div>
        </td>
        <td class="muted"><?=e($st['student_id'])?></td>
        <td class="muted"><?=e($st['gender']??'—')?></td>
        <td class="muted"><?=e($st['phone']??'—')?></td>
        <td><?=statusBadge($st['status'])?></td>
        <td>
          <a href="<?=BASE_URL?>/portal/teacher/students.php?class_id=<?=$selClass?>&q=<?=urlencode($st['student_id'])?>" class="filter-button button-sm">View</a>
        </td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table></div>
  <?php elseif (!empty($myClasses) && !$selClass): ?>
  <div style="text-align:center;padding:40px;color:var(--ink-soft)">Select a class above to view students.</div>
  <?php elseif (empty($myClasses)): ?>
  <div style="text-align:center;padding:40px;color:var(--ink-faint)">No classes assigned yet. Contact admin.</div>
  <?php endif;?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script></body></html>
