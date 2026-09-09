<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['teacher','class_teacher']);

$activePage = 'dashboard';
$pdo = db(); $user = currentUser(); $ayId = currentAcademicYearId(); $ay = currentAcademicYearName();

$teacherRow = $pdo->prepare("SELECT * FROM teachers WHERE user_id=? LIMIT 1");
$teacherRow->execute([$user['id']]); $teacher = $teacherRow->fetch();
if (!$teacher) {
    echo '<div style="font-family:sans-serif;padding:48px;text-align:center"><h2>Teacher record not linked</h2><p>Contact admin.</p><a href="'.BASE_URL.'/admin/logout.php">Sign Out</a></div>';
    exit;
}
$teacherId = $teacher['id'];
$ini = strtoupper(substr($teacher['first_name'],0,1).substr($teacher['last_name'],0,1));

// My classes & subjects
$myClasses = $pdo->prepare("SELECT DISTINCT c.id,c.name,g.name grade_name,(SELECT COUNT(*) FROM students s WHERE s.current_class_id=c.id AND s.status='Active') enrol FROM teacher_assignments ta JOIN classes c ON c.id=ta.class_id JOIN grades g ON g.id=c.grade_id WHERE ta.teacher_id=? AND ta.academic_year_id=? ORDER BY g.sequence,c.name");
$myClasses->execute([$teacherId,$ayId]); $myClasses = $myClasses->fetchAll();

$mySubjects = $pdo->prepare("SELECT DISTINCT s.name FROM teacher_assignments ta JOIN subjects s ON s.id=ta.subject_id WHERE ta.teacher_id=? AND ta.academic_year_id=?");
$mySubjects->execute([$teacherId,$ayId]); $mySubjects = $mySubjects->fetchAll();

$totalStudents = array_sum(array_column($myClasses,'enrol'));

// Draft marks pending submission
$draftMarks = (int)$pdo->prepare("SELECT COUNT(DISTINCT class_id,subject_id,assessment_config_id) FROM assessment_scores WHERE entered_by=? AND status='draft' AND academic_year_id=?")->execute([$user['id'],$ayId]) ? $pdo->query("SELECT COUNT(DISTINCT class_id,subject_id,assessment_config_id) FROM assessment_scores WHERE entered_by={$user['id']} AND status='draft' AND academic_year_id=$ayId")->fetchColumn() : 0;

// Returned marks
$returnedMarks = (int)$pdo->query("SELECT COUNT(*) FROM assessment_scores WHERE entered_by={$user['id']} AND status='returned' AND academic_year_id=$ayId")->fetchColumn();

// Attendance taken today
try {
    $attToday = (int)$pdo->query("SELECT COUNT(DISTINCT class_id) FROM attendance WHERE DATE(date)=CURDATE() AND recorded_by={$user['id']}")->fetchColumn();
} catch (Throwable $e) { $attToday = 0; }

// Today's attendance rate across my classes
try {
    $classIds = implode(',', array_column($myClasses,'id') ?: [0]);
    $attRateToday = $pdo->query("SELECT ROUND(SUM(status='Present')/NULLIF(COUNT(*),0)*100,1) FROM attendance WHERE date=CURDATE() AND class_id IN ($classIds)")->fetchColumn();
} catch (Throwable $e) { $attRateToday = null; }

// Materials count
try {
    $materialCount = (int)$pdo->query("SELECT COUNT(*) FROM learning_materials WHERE teacher_id=$teacherId AND academic_year_id=$ayId AND is_published=1")->fetchColumn();
} catch (Throwable $e) { $materialCount = 0; }

// Quizzes count
try {
    $quizCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_quizzes WHERE teacher_id=$teacherId AND academic_year_id=$ayId")->fetchColumn();
} catch (Throwable $e) { $quizCount = 0; }

// Assessments count
try {
    $asmCount = (int)$pdo->query("SELECT COUNT(*) FROM teacher_assessments WHERE teacher_id=$teacherId AND academic_year_id=$ayId")->fetchColumn();
} catch (Throwable $e) { $asmCount = 0; }

// Announcements
$announcements = $pdo->query("SELECT title,message,published_at FROM announcements WHERE target IN ('all','teachers') AND (expires_at IS NULL OR expires_at>NOW()) ORDER BY published_at DESC LIMIT 4")->fetchAll();

// Class performance overview
$classPerf = $pdo->prepare(
    "SELECT c.name class_name, ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1) avg_pct
     FROM assessment_scores asc2
     JOIN classes c ON c.id=asc2.class_id
     WHERE asc2.entered_by=? AND asc2.academic_year_id=? AND asc2.max_marks>0
       AND asc2.status IN ('approved','published')
     GROUP BY c.id,c.name ORDER BY c.name"
);
$classPerf->execute([$user['id'],$ayId]); $classPerf=$classPerf->fetchAll();

$hour = (int)date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Teacher Portal — KHS</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <!-- Header -->
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
      <h1 style="font-size:24px;font-weight:800;margin-bottom:4px"><?= $greet ?>, <?= e($teacher['first_name']) ?>! 👩‍🏫</h1>
      <p style="color:var(--ink-soft);font-size:13px"><?= e($teacher['specialization']??'Teacher') ?> &mdash; <?= e($ay) ?></p>
    </div>
    <div style="font-size:12px;color:var(--ink-soft);text-align:right">
      <span><?= date('l, F d, Y') ?></span>
    </div>
  </div>

  <!-- Alerts -->
  <?php if ($returnedMarks > 0): ?>
  <div class="alert alert-warn" style="margin-bottom:16px">
    ↩️ <strong><?= $returnedMarks ?> mark record<?= $returnedMarks!==1?'s':'' ?></strong> returned for correction.
    <a href="<?= BASE_URL ?>/portal/teacher/enter_marks.php" style="font-weight:700;margin-left:8px">Correct now →</a>
  </div>
  <?php endif; ?>
  <?php if ($draftMarks > 0): ?>
  <div class="alert alert-info" style="margin-bottom:16px">
    ✏️ <strong><?= $draftMarks ?> draft mark batch<?= $draftMarks!==1?'es':'' ?></strong> awaiting submission.
    <a href="<?= BASE_URL ?>/portal/teacher/enter_marks.php" style="font-weight:700;margin-left:8px">Submit now →</a>
  </div>
  <?php endif; ?>

  <!-- KPI Metrics -->
  <div class="metric-grid" style="grid-template-columns:repeat(auto-fill,minmax(140px,1fr));margin-bottom:24px">
    <div class="metric-card"><div class="metric-top"><span>My Classes</span><div class="metric-icon">🏫</div></div><strong><?= count($myClasses) ?></strong><small><i></i><?= e($ay) ?></small></div>
    <div class="metric-card"><div class="metric-top"><span>My Students</span><div class="metric-icon">🎓</div></div><strong><?= $totalStudents ?></strong><small><i></i>Across all classes</small></div>
    <div class="metric-card"><div class="metric-top"><span>Subjects</span><div class="metric-icon">📚</div></div><strong><?= count($mySubjects) ?></strong><small><i></i>Assigned</small></div>
    <div class="metric-card <?= $draftMarks>0?'finance-metrics':'' ?>"><div class="metric-top"><span>Draft Marks</span><div class="metric-icon">✏️</div></div><strong style="color:<?= $draftMarks>0?'var(--warning)':'inherit' ?>"><?= $draftMarks ?></strong><small><i></i>Pending submission</small></div>
    <div class="metric-card"><div class="metric-top"><span>Today's Attendance</span><div class="metric-icon">📆</div></div><strong><?= $attRateToday !== null ? $attRateToday.'%' : ($attToday.' taken') ?></strong><small><i></i><?= date('M d') ?></small></div>
    <div class="metric-card"><div class="metric-top"><span>Materials</span><div class="metric-icon">📚</div></div><strong><?= $materialCount ?></strong><small><i></i>Published</small></div>
  </div>

  <!-- Quick access sections -->
  <h3 style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-soft);margin-bottom:10px">Quick Access</h3>

  <!-- Teaching -->
  <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:7px">📚 Teaching</div>
  <div class="quick-grid" style="margin-bottom:16px">
    <a href="<?= BASE_URL ?>/portal/teacher/my_classes.php"  class="quick-item"><span class="qi-icon">🏫</span><div><strong>My Classes</strong><small><?= count($myClasses) ?> assigned</small></div></a>
    <a href="<?= BASE_URL ?>/portal/teacher/timetable.php"   class="quick-item"><span class="qi-icon">📅</span><div><strong>Timetable</strong><small>My schedule</small></div></a>
    <a href="<?= BASE_URL ?>/portal/teacher/students.php"    class="quick-item"><span class="qi-icon">🎓</span><div><strong>My Students</strong><small><?= $totalStudents ?> students</small></div></a>
    <a href="<?= BASE_URL ?>/portal/teacher/materials.php"   class="quick-item"><span class="qi-icon">📚</span><div><strong>Learning Materials</strong><small><?= $materialCount ?> published</small></div></a>
  </div>

  <!-- Assessment & Marks -->
  <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:7px">📝 Assessment &amp; Marks</div>
  <div class="quick-grid" style="margin-bottom:16px">
    <a href="<?= BASE_URL ?>/portal/teacher/enter_marks.php" class="quick-item"><span class="qi-icon">✏️</span><div><strong>Enter Marks</strong><small><?= $draftMarks ?> draft<?= $draftMarks!==1?'s':'' ?></small></div></a>
    <a href="<?= BASE_URL ?>/portal/teacher/results.php"     class="quick-item"><span class="qi-icon">📊</span><div><strong>Results</strong><small>Class performance</small></div></a>
    <a href="<?= BASE_URL ?>/portal/teacher/assessments.php" class="quick-item"><span class="qi-icon">📝</span><div><strong>Assessments</strong><small><?= $asmCount ?> created</small></div></a>
    <a href="<?= BASE_URL ?>/portal/teacher/quizzes.php"     class="quick-item"><span class="qi-icon">🧠</span><div><strong>Online Quizzes</strong><small><?= $quizCount ?> quiz<?= $quizCount!==1?'zes':'' ?></small></div></a>
  </div>

  <!-- Attendance & Communication -->
  <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:7px">📆 Attendance &amp; Communication</div>
  <div class="quick-grid" style="margin-bottom:24px">
    <a href="<?= BASE_URL ?>/portal/teacher/take_attendance.php" class="quick-item"><span class="qi-icon">📆</span><div><strong>Take Attendance</strong><small><?= $attToday ?> class<?= $attToday!==1?'es':'' ?> done today</small></div></a>
    <a href="<?= BASE_URL ?>/portal/teacher/announcements.php"   class="quick-item"><span class="qi-icon">📢</span><div><strong>Announcements</strong><small>School notices</small></div></a>
  </div>

  <!-- Class performance snapshot -->
  <?php if (!empty($classPerf)): ?>
  <div class="panel" style="padding:20px;margin-bottom:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <h3 style="font-weight:700;font-size:14px">📊 Class Performance — <?= e($ay) ?></h3>
      <a href="<?= BASE_URL ?>/portal/teacher/results.php" class="filter-button">Details →</a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px">
      <?php foreach ($classPerf as $cp):
        $col = ($cp['avg_pct']??0) >= 70 ? 'var(--green)' : (($cp['avg_pct']??0) >= 50 ? 'var(--warning)' : 'var(--error)');
      ?>
      <div style="padding:12px;background:var(--bg2);border-radius:var(--radius-sm);border-left:3px solid <?= $col ?>;text-align:center">
        <div style="font-size:11px;font-weight:700;color:var(--ink-soft);margin-bottom:4px"><?= e($cp['class_name']) ?></div>
        <div style="font-size:1.4rem;font-weight:800;color:<?= $col ?>;line-height:1"><?= $cp['avg_pct']??'—' ?>%</div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- My Classes list + Announcements -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px" class="teacher-panels">

    <!-- My Classes -->
    <?php if (!empty($myClasses)): ?>
    <div class="panel" style="padding:20px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <h3 style="font-weight:700;font-size:14px">🏫 My Classes</h3>
        <a href="<?= BASE_URL ?>/portal/teacher/my_classes.php" class="filter-button">All →</a>
      </div>
      <?php foreach ($myClasses as $c): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--line-soft)">
        <div>
          <strong style="font-size:13px"><?= e($c['name']) ?></strong>
          <span style="font-size:11px;color:var(--ink-soft);margin-left:6px"><?= e($c['grade_name']) ?></span>
        </div>
        <span style="font-size:12px;font-weight:600;color:var(--primary)"><?= $c['enrol'] ?> students</span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Announcements -->
    <div class="panel activity-panel">
      <div class="panel-heading">
        <div><h3>Announcements</h3></div>
        <a href="<?= BASE_URL ?>/portal/teacher/announcements.php" class="filter-button">All →</a>
      </div>
      <?php if (empty($announcements)): ?>
      <p style="color:var(--ink-faint);font-size:13px;padding:12px">No announcements.</p>
      <?php else: foreach ($announcements as $ann): ?>
      <div class="activity">
        <span class="activity-dot blue"></span>
        <div>
          <strong><?= e($ann['title']) ?></strong>
          <p><?= e(mb_substr($ann['message'],0,80)).(mb_strlen($ann['message'])>80?'…':'') ?></p>
          <small><?= date('M d, Y', strtotime($ann['published_at'])) ?></small>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>

  </div>

</div>
</div>
<style>@media(max-width:640px){.teacher-panels{grid-template-columns:1fr !important}}</style>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
