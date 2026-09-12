<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['teacher','class_teacher']);

$activePage = 'classes';
$pdo = db(); $user = currentUser(); $ayId = currentAcademicYearId(); $ay = currentAcademicYearName();
include __DIR__.'/includes/resolve_teacher.php';

$selClass = (int)($_GET['class_id'] ?? 0);

// All classes this teacher has assignments in
$myClasses = [];
if ($teacherId > 0) {
    $mc = $pdo->prepare(
        "SELECT DISTINCT c.id, c.name, g.name grade_name, c.section,
                (SELECT COUNT(*) FROM students s WHERE s.current_class_id=c.id AND s.status='Active') enrol,
                IF(c.teacher_id=?,1,0) is_sponsored
         FROM teacher_assignments ta
         JOIN classes c ON c.id=ta.class_id
         JOIN grades  g ON g.id=c.grade_id
         WHERE ta.teacher_id=? AND ta.academic_year_id=?
         ORDER BY g.sequence, c.name"
    );
    $mc->execute([$teacherId, $teacherId, $ayId]);
    $myClasses = $mc->fetchAll();
}

// If sponsor and sponsored class not in assignments list, add it
if ($isClassSponsor && $sponsoredClassId) {
    $found = array_filter($myClasses, fn($c) => (int)$c['id'] === $sponsoredClassId);
    if (empty($found)) {
        try {
            $sc = $pdo->query(
                "SELECT c.id, c.name, g.name grade_name, c.section,
                        (SELECT COUNT(*) FROM students s WHERE s.current_class_id=c.id AND s.status='Active') enrol,
                        1 is_sponsored
                 FROM classes c JOIN grades g ON g.id=c.grade_id
                 WHERE c.id=$sponsoredClassId"
            )->fetch();
            if ($sc) array_unshift($myClasses, $sc);
        } catch (Throwable $e) {}
    }
}

if (!$selClass && !empty($myClasses)) $selClass = $myClasses[0]['id'];

// For the selected class, load subjects differently based on role:
// CLASS SPONSOR → all subjects taught in the class (by any teacher)
// SUBJECT TEACHER → only their own assigned subjects
$students = []; $classSubjects = []; $allClassSubjects = [];

if ($selClass) {
    // Always load all students in the class
    $sts = $pdo->prepare(
        "SELECT s.*, g.name grade_name FROM students s
         LEFT JOIN grades g ON g.id=s.current_grade_id
         WHERE s.current_class_id=? AND s.status='Active'
         ORDER BY s.last_name, s.first_name"
    );
    $sts->execute([$selClass]); $students = $sts->fetchAll();

    // My subjects in this class
    if ($teacherId > 0) {
        $cs = $pdo->prepare(
            "SELECT DISTINCT sub.id, sub.name, sub.code
             FROM teacher_assignments ta
             JOIN subjects sub ON sub.id=ta.subject_id
             WHERE ta.teacher_id=? AND ta.class_id=? AND ta.academic_year_id=?"
        );
        $cs->execute([$teacherId, $selClass, $ayId]);
        $classSubjects = $cs->fetchAll(PDO::FETCH_COLUMN, 1);
    }

    // If sponsor, load ALL subjects in the class (all teachers)
    $isSponsorForSelected = $isClassSponsor && ($selClass === $sponsoredClassId || $sponsoredClassId === 0);
    if ($isSponsorForSelected || ($isClassSponsor && $selClass === $sponsoredClassId)) {
        try {
            $allClassSubjects = $pdo->query(
                "SELECT DISTINCT sub.id, sub.name, sub.code,
                        t.first_name t_first, t.last_name t_last,
                        (SELECT ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1)
                         FROM assessment_scores asc2
                         WHERE asc2.subject_id=sub.id AND asc2.class_id=$selClass
                           AND asc2.academic_year_id=$ayId
                           AND asc2.status IN ('approved','published')
                           AND asc2.max_marks>0) class_avg
                 FROM teacher_assignments ta
                 JOIN subjects sub ON sub.id=ta.subject_id
                 LEFT JOIN teachers t ON t.id=ta.teacher_id
                 WHERE ta.class_id=$selClass AND ta.academic_year_id=$ayId
                 ORDER BY sub.name"
            )->fetchAll();
        } catch (Throwable $e) { $allClassSubjects = []; }
    }
}

$className = $selClass ? ($pdo->query("SELECT name FROM classes WHERE id=$selClass")->fetchColumn()??'') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>My Classes — Teacher Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

<div class="page-heading">
  <div>
    <h1>My Classes</h1>
    <p><?= $selClass ? e($className).' — '.count($students).' students' : e($ay) ?></p>
  </div>
  <?php if ($isClassSponsor && $sponsoredClassId): ?>
  <span class="status approved" style="font-size:12px;padding:6px 14px">
    🏫 Class Sponsor: <?= e($sponsoredClass['name'] ?? 'Class') ?>
  </span>
  <?php endif; ?>
</div>

<?php foreach(getFlash() as $f):?><div class="alert alert-<?=$f['type']?>"><?=e($f['message'])?></div><?php endforeach;?>

<!-- Role notice -->
<?php if ($isClassSponsor): ?>
<div class="alert alert-info" style="margin-bottom:16px">
  👤 <strong>You are Class Sponsor for <?= e($sponsoredClass['name'] ?? 'your class') ?>.</strong>
  You can view all subjects and their grades. For your own subjects, you can enter and submit marks as usual.
</div>
<?php else: ?>
<div style="background:var(--bg2);border-radius:var(--radius-sm);padding:10px 14px;margin-bottom:16px;font-size:13px;color:var(--ink-soft)">
  📚 <strong>Subject Teacher:</strong> You see students and subjects you are assigned to teach.
</div>
<?php endif; ?>

<!-- Class tabs -->
<div class="filter-row" style="margin-bottom:16px;flex-wrap:wrap;gap:8px">
  <?php foreach($myClasses as $c): $active = $selClass == (int)$c['id']; ?>
  <a href="?class_id=<?= $c['id'] ?>" class="filter-button"
     style="<?= $active?'background:var(--primary);color:#fff;border-color:var(--primary)':'' ?>">
    <?= e($c['name']) ?>
    <?php if ($c['is_sponsored']): ?>
    <span style="font-size:10px;background:rgba(255,255,255,.25);padding:1px 5px;border-radius:8px;margin-left:4px">★ Sponsor</span>
    <?php endif; ?>
    <span style="font-size:11px;opacity:.75">(<?= $c['enrol'] ?>)</span>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($selClass): ?>

  <!-- ── CLASS SPONSOR VIEW: show all subjects ── -->
  <?php if (!empty($allClassSubjects)): ?>
  <div class="panel" style="padding:18px;margin-bottom:16px">
    <h3 style="font-weight:700;font-size:13px;margin-bottom:14px">📚 All Subjects in <?= e($className) ?></h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px">
      <?php foreach($allClassSubjects as $sub):
        $isMySubject = in_array($sub['name'], $classSubjects);
        $avg = $sub['class_avg'];
        $avgCol = $avg===null?'var(--ink-faint)':($avg>=70?'var(--green)':($avg>=50?'var(--warning)':'var(--error)'));
      ?>
      <div style="padding:12px 14px;background:<?=$isMySubject?'var(--primary-soft)':'var(--bg2)'?>;border-radius:var(--radius-sm);border:1.5px solid <?=$isMySubject?'var(--primary)':'var(--line)'?>">
        <div style="display:flex;justify-content:space-between;align-items:flex-start">
          <div>
            <div style="font-weight:700;font-size:13px"><?= e($sub['name']) ?></div>
            <div style="font-size:11.5px;color:var(--ink-soft);margin-top:2px">
              <?= $sub['t_first'] ? e($sub['t_first'].' '.$sub['t_last']) : 'Not assigned' ?>
              <?php if ($isMySubject): ?>
              <span style="color:var(--primary);font-weight:700"> · You</span>
              <?php endif; ?>
            </div>
          </div>
          <?php if ($avg !== null): ?>
          <span style="font-size:13px;font-weight:800;color:<?=$avgCol?>"><?= $avg ?>%</span>
          <?php endif; ?>
        </div>
        <?php if ($isMySubject): ?>
        <a href="enter_marks.php?class_id=<?=$selClass?>"
           class="button button-primary button-sm" style="margin-top:8px;display:block;text-align:center;font-size:11.5px">
           ✏️ Enter Marks
        </a>
        <?php else: ?>
        <a href="results.php?class_id=<?=$selClass?>&subject_id=<?=$sub['id']?>"
           class="button button-secondary button-sm" style="margin-top:8px;display:block;text-align:center;font-size:11.5px">
           📊 View Grades
        </a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── SUBJECT TEACHER VIEW: show own subjects only ── -->
  <?php elseif (!empty($classSubjects)): ?>
  <div style="margin-bottom:14px;font-size:13px;color:var(--ink-soft)">
    <strong>Your subjects in this class:</strong>
    <?php foreach($classSubjects as $cs): ?>
    <span style="background:var(--primary-soft);color:var(--primary);padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;margin:2px;display:inline-block"><?= e($cs) ?></span>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Students table -->
  <?php if (!empty($students)): ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Student</th>
          <th>Student ID</th>
          <th>Gender</th>
          <?php if ($isClassSponsor && !empty($allClassSubjects)): ?>
          <th>Overall Avg</th>
          <?php endif; ?>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($students as $st):
          $ini = strtoupper(substr($st['first_name'],0,1).substr($st['last_name'],0,1));
          // Class sponsor: compute student's overall average
          $stuAvg = null;
          if ($isClassSponsor && !empty($allClassSubjects)) {
              try {
                  $stuAvg = $pdo->query(
                      "SELECT ROUND(AVG(marks_obtained/max_marks*100),1)
                       FROM assessment_scores
                       WHERE student_id={$st['id']} AND class_id=$selClass
                         AND academic_year_id=$ayId
                         AND status IN ('approved','published') AND max_marks>0"
                  )->fetchColumn();
              } catch (Throwable $e) {}
          }
          $stuAvgCol = $stuAvg===null?'var(--ink-faint)':($stuAvg>=70?'var(--green)':($stuAvg>=50?'var(--warning)':'var(--error)'));
        ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:10px">
              <div class="avatar" style="width:32px;height:32px;font-size:11px;flex-shrink:0"><?= e($ini) ?></div>
              <strong><?= e($st['first_name'].' '.$st['last_name']) ?></strong>
            </div>
          </td>
          <td class="muted"><?= e($st['student_id']) ?></td>
          <td class="muted"><?= e($st['gender']??'—') ?></td>
          <?php if ($isClassSponsor && !empty($allClassSubjects)): ?>
          <td style="font-weight:700;color:<?=$stuAvgCol?>"><?= $stuAvg!==null?$stuAvg.'%':'—' ?></td>
          <?php endif; ?>
          <td><span class="status <?=$st['status']==='Active'?'approved':'pending'?>" style="font-size:11px"><?= e($st['status']) ?></span></td>
          <td>
            <a href="students.php?class_id=<?=$selClass?>&q=<?= urlencode($st['student_id']) ?>"
               class="button button-secondary button-sm">View</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php elseif (empty($myClasses)): ?>
  <div style="text-align:center;padding:40px;color:var(--ink-faint)">
    No classes assigned yet. Contact admin to assign you to classes and subjects.
  </div>
  <?php else: ?>
  <div style="text-align:center;padding:40px;color:var(--ink-soft)">
    No active students in this class.
  </div>
  <?php endif; // students ?>

<?php else: ?>
<div style="text-align:center;padding:40px;color:var(--ink-soft)">
  Select a class above to view students and subjects.
</div>
<?php endif; // selClass ?>

</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
