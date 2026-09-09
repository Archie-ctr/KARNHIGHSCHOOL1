<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['teacher','class_teacher']);

$activePage = 'dashboard';
$pdo = db(); $user = currentUser(); $ayId = currentAcademicYearId(); $ay = currentAcademicYearName();

// Load teacher record
$teacherRow = $pdo->prepare("SELECT * FROM teachers WHERE user_id=? LIMIT 1");
$teacherRow->execute([$user['id']]); $teacher = $teacherRow->fetch();
if (!$teacher) {
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:60px;text-align:center">
          <h2>Teacher record not linked to your account.</h2>
          <p>Please contact the administrator.</p>
          <a href="'.BASE_URL.'/admin/logout.php" style="color:#c0392b">Sign Out</a></body></html>';
    exit;
}
$teacherId = $teacher['id'];

// ── My classes ────────────────────────────────────────────────
try {
    $myClasses = $pdo->prepare(
        "SELECT DISTINCT c.id, c.name, g.name grade_name,
                (SELECT COUNT(*) FROM students s WHERE s.current_class_id=c.id AND s.status='Active') enrol
         FROM teacher_assignments ta
         JOIN classes c ON c.id=ta.class_id
         JOIN grades  g ON g.id=c.grade_id
         WHERE ta.teacher_id=? AND ta.academic_year_id=?
         ORDER BY g.sequence, c.name"
    );
    $myClasses->execute([$teacherId, $ayId]);
    $myClasses = $myClasses->fetchAll();
} catch (Throwable $e) { $myClasses = []; }

// ── My subjects ───────────────────────────────────────────────
try {
    $mySubjects = $pdo->prepare(
        "SELECT DISTINCT sub.id, sub.name
         FROM teacher_assignments ta
         JOIN subjects sub ON sub.id=ta.subject_id
         WHERE ta.teacher_id=? AND ta.academic_year_id=?
         ORDER BY sub.name"
    );
    $mySubjects->execute([$teacherId, $ayId]);
    $mySubjects = $mySubjects->fetchAll();
} catch (Throwable $e) { $mySubjects = []; }

$totalStudents = array_sum(array_column($myClasses, 'enrol'));
$classIds      = implode(',', array_column($myClasses, 'id') ?: [0]);

// ── Marks ─────────────────────────────────────────────────────
try {
    $draftMarks = (int)$pdo->query(
        "SELECT COUNT(DISTINCT CONCAT(class_id,'-',subject_id,'-',COALESCE(assessment_config_id,0)))
         FROM assessment_scores
         WHERE entered_by={$user['id']} AND status='draft' AND academic_year_id=$ayId"
    )->fetchColumn();
} catch (Throwable $e) { $draftMarks = 0; }

try {
    $returnedMarks = (int)$pdo->query(
        "SELECT COUNT(*) FROM assessment_scores
         WHERE entered_by={$user['id']} AND status='returned' AND academic_year_id=$ayId"
    )->fetchColumn();
} catch (Throwable $e) { $returnedMarks = 0; }

// ── Attendance ────────────────────────────────────────────────
try {
    $attToday = (int)$pdo->query(
        "SELECT COUNT(DISTINCT class_id) FROM attendance
         WHERE DATE(date)=CURDATE() AND recorded_by={$user['id']}"
    )->fetchColumn();
} catch (Throwable $e) { $attToday = 0; }

try {
    $attRateToday = $pdo->query(
        "SELECT ROUND(SUM(status='Present')/NULLIF(COUNT(*),0)*100,1)
         FROM attendance
         WHERE date=CURDATE() AND class_id IN ($classIds)"
    )->fetchColumn();
} catch (Throwable $e) { $attRateToday = null; }

// ── Today's timetable ─────────────────────────────────────────
$todayDow = (int)date('N'); // 1=Monday … 7=Sunday
try {
    $todaySchedule = $pdo->query(
        "SELECT tt.*, sub.name subject_name, c.name class_name, tt.room
         FROM timetable tt
         JOIN subjects sub ON sub.id=tt.subject_id
         JOIN classes  c   ON c.id=tt.class_id
         WHERE tt.teacher_id=$teacherId
           AND tt.day_of_week=$todayDow
           AND tt.academic_year_id=$ayId
         ORDER BY tt.period_slot, tt.start_time"
    )->fetchAll();
} catch (Throwable $e) { $todaySchedule = []; }

// ── Materials / Quizzes / Assessments ─────────────────────────
// Ensure tables exist before counting (they're created on first page visit)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS learning_materials (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT UNSIGNED NOT NULL,
        class_id INT UNSIGNED NULL,
        subject_id INT UNSIGNED NULL,
        academic_year_id INT UNSIGNED NOT NULL,
        title VARCHAR(200) NOT NULL,
        description TEXT NULL,
        material_type ENUM('note','pdf','assignment','study_guide','other') NOT NULL DEFAULT 'note',
        file_path VARCHAR(255) NULL,
        file_name VARCHAR(255) NULL,
        file_size INT NULL,
        is_published TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_lm_teacher (teacher_id),
        INDEX idx_lm_ay (academic_year_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS teacher_quizzes (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT UNSIGNED NOT NULL,
        class_id INT UNSIGNED NOT NULL,
        subject_id INT UNSIGNED NULL,
        academic_year_id INT UNSIGNED NOT NULL,
        title VARCHAR(200) NOT NULL,
        description TEXT NULL,
        time_limit_mins INT NULL,
        max_attempts INT NOT NULL DEFAULT 1,
        is_published TINYINT(1) NOT NULL DEFAULT 0,
        start_date DATETIME NULL,
        end_date DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tq_teacher (teacher_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS teacher_assessments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT UNSIGNED NOT NULL,
        class_id INT UNSIGNED NOT NULL,
        subject_id INT UNSIGNED NULL,
        academic_year_id INT UNSIGNED NOT NULL,
        title VARCHAR(200) NOT NULL,
        assessment_type ENUM('classwork','homework','test','quiz','project','other') NOT NULL DEFAULT 'classwork',
        description TEXT NULL,
        max_score DECIMAL(8,2) NOT NULL DEFAULT 100,
        due_date DATE NULL,
        is_graded TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ta_teacher (teacher_id),
        INDEX idx_ta_class (class_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

try { $materialCount=(int)$pdo->query("SELECT COUNT(*) FROM learning_materials WHERE teacher_id=$teacherId AND academic_year_id=$ayId AND is_published=1")->fetchColumn(); } catch(Throwable $e){$materialCount=0;}
try { $quizCount    =(int)$pdo->query("SELECT COUNT(*) FROM teacher_quizzes WHERE teacher_id=$teacherId AND academic_year_id=$ayId")->fetchColumn();} catch(Throwable $e){$quizCount=0;}
try { $asmCount     =(int)$pdo->query("SELECT COUNT(*) FROM teacher_assessments WHERE teacher_id=$teacherId AND academic_year_id=$ayId")->fetchColumn();} catch(Throwable $e){$asmCount=0;}

// ── Class performance ─────────────────────────────────────────
try {
    $classPerf = $pdo->prepare(
        "SELECT c.name class_name,
                ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1) avg_pct
         FROM assessment_scores asc2
         JOIN classes c ON c.id=asc2.class_id
         WHERE asc2.entered_by=? AND asc2.academic_year_id=?
           AND asc2.max_marks>0 AND asc2.status IN ('approved','published')
         GROUP BY c.id, c.name ORDER BY c.name"
    );
    $classPerf->execute([$user['id'], $ayId]);
    $classPerf = $classPerf->fetchAll();
} catch (Throwable $e) { $classPerf = []; }

// ── Recent announcements ──────────────────────────────────────
try {
    $announcements = $pdo->query(
        "SELECT title, message, published_at
         FROM announcements
         WHERE target IN ('all','teachers')
           AND (expires_at IS NULL OR expires_at > NOW())
         ORDER BY published_at DESC LIMIT 5"
    )->fetchAll();
} catch (Throwable $e) { $announcements = []; }

// ── Pending marks needing attention ──────────────────────────
try {
    $pendingMarksBatches = $pdo->query(
        "SELECT c.name class_name, sub.name subject_name, asc2.status, COUNT(*) cnt
         FROM assessment_scores asc2
         JOIN classes c ON c.id=asc2.class_id
         JOIN subjects sub ON sub.id=asc2.subject_id
         WHERE asc2.entered_by={$user['id']} AND asc2.academic_year_id=$ayId
           AND asc2.status IN ('draft','returned')
         GROUP BY asc2.class_id, asc2.subject_id, asc2.status
         LIMIT 5"
    )->fetchAll();
} catch (Throwable $e) { $pendingMarksBatches = []; }

$hour  = (int)date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$todayDowName = date('l'); // Monday, Tuesday …

function perfColor(float $v): string {
    return $v >= 70 ? 'var(--green)' : ($v >= 50 ? 'var(--warning)' : 'var(--error)');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Teacher Dashboard — KHS</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">

<?php include __DIR__.'/includes/nav.php'; ?>

<div class="portal-content">

  <!-- Mobile topbar -->
  <div class="ts-topbar">
    <button class="ts-hamburger" id="sidebarOpen" aria-label="Open menu">
      <span></span><span></span><span></span>
    </button>
    <span class="ts-topbar-title">Teacher Portal</span>
    <div style="width:36px"></div><!-- spacer -->
  </div>

  <!-- Page heading -->
  <div class="page-heading">
    <div>
      <p style="font-size:13px;color:var(--ink-soft);margin-bottom:2px"><?= date('l, F j, Y') ?></p>
      <h1><?= $greet ?>, <?= e($teacher['first_name']) ?>!</h1>
      <p><?= e($teacher['specialization'] ?? 'Teacher') ?> &mdash; <?= e($ay) ?></p>
    </div>
    <a href="<?= BASE_URL ?>/portal/teacher/take_attendance.php" class="button button-primary">📆 Take Attendance</a>
  </div>

  <!-- Flash messages -->
  <?php foreach (getFlash() as $f): ?>
  <div class="alert alert-<?= $f['type'] ?>"><?= e($f['message']) ?></div>
  <?php endforeach; ?>

  <!-- No assignments notice -->
  <?php if (empty($myClasses)): ?>
  <div class="alert alert-info" style="margin-bottom:20px">
    ℹ️ You have no class assignments for <strong><?= e($ay) ?></strong> yet. Contact admin to assign you to classes and subjects.
  </div>
  <?php endif; ?>

  <!-- Action alerts -->
  <?php if ($returnedMarks > 0): ?>
  <div class="alert alert-warning" style="margin-bottom:12px">
    ↩️ <strong><?= $returnedMarks ?> mark record<?= $returnedMarks !== 1 ? 's' : '' ?></strong> returned for correction.
    <a href="<?= BASE_URL ?>/portal/teacher/enter_marks.php" style="font-weight:700;margin-left:8px">Fix now →</a>
  </div>
  <?php endif; ?>
  <?php if ($draftMarks > 0): ?>
  <div class="alert alert-info" style="margin-bottom:12px">
    ✏️ <strong><?= $draftMarks ?> unsaved mark batch<?= $draftMarks !== 1 ? 'es' : '' ?></strong> awaiting submission.
    <a href="<?= BASE_URL ?>/portal/teacher/enter_marks.php" style="font-weight:700;margin-left:8px">Submit now →</a>
  </div>
  <?php endif; ?>

  <!-- ── KPI METRICS ── -->
  <div class="metric-grid">
    <div class="metric-card">
      <div class="metric-top"><span>My Classes</span><div class="metric-icon">🏫</div></div>
      <strong><?= count($myClasses) ?></strong>
      <small><i></i><?= count($mySubjects) ?> subject<?= count($mySubjects)!==1?'s':'' ?></small>
    </div>
    <div class="metric-card">
      <div class="metric-top"><span>My Students</span><div class="metric-icon">🎓</div></div>
      <strong><?= $totalStudents ?></strong>
      <small><i></i>Across all classes</small>
    </div>
    <div class="metric-card <?= ($attRateToday!==null&&$attRateToday<80)?'finance-metrics':'' ?>">
      <div class="metric-top"><span>Today's Attendance</span><div class="metric-icon">📆</div></div>
      <strong style="color:<?= $attRateToday!==null?($attRateToday>=80?'var(--green)':'var(--error)'):'inherit' ?>">
        <?= $attRateToday !== null ? $attRateToday.'%' : ($attToday > 0 ? $attToday.' class'.($attToday!==1?'es':'').' done' : '—') ?>
      </strong>
      <small><i></i><?= $attToday ?> class<?= $attToday!==1?'es':'' ?> recorded</small>
    </div>
    <div class="metric-card <?= $draftMarks>0||$returnedMarks>0?'finance-metrics':'' ?>">
      <div class="metric-top"><span>Marks Pending</span><div class="metric-icon">✏️</div></div>
      <strong style="color:<?= $draftMarks>0||$returnedMarks>0?'var(--warning)':'inherit' ?>">
        <?= $draftMarks + $returnedMarks ?>
      </strong>
      <small><i></i><?= $draftMarks ?> draft<?php if($returnedMarks>0):?>, <?=$returnedMarks?> returned<?php endif;?></small>
    </div>
    <div class="metric-card">
      <div class="metric-top"><span>Assessments</span><div class="metric-icon">📝</div></div>
      <strong><?= $asmCount ?></strong>
      <small><i></i><?= $quizCount ?> quiz<?= $quizCount!==1?'zes':'' ?></small>
    </div>
    <div class="metric-card">
      <div class="metric-top"><span>Materials</span><div class="metric-icon">📚</div></div>
      <strong><?= $materialCount ?></strong>
      <small><i></i>Published</small>
    </div>
  </div>

  <!-- ── TODAY'S TIMETABLE ── -->
  <?php if (!empty($todaySchedule)): ?>
  <div class="panel" style="margin-bottom:20px">
    <div class="panel-heading">
      <div>
        <h3>📅 Today — <?= $todayDowName ?></h3>
        <p><?= count($todaySchedule) ?> period<?= count($todaySchedule)!==1?'s':'' ?> scheduled</p>
      </div>
      <a href="<?= BASE_URL ?>/portal/teacher/timetable.php" class="filter-button">Full timetable →</a>
    </div>
    <div style="padding:0 20px 16px;display:flex;flex-wrap:wrap;gap:10px">
      <?php foreach ($todaySchedule as $p): ?>
      <div style="padding:12px 16px;background:var(--primary-soft);border-left:3px solid var(--primary);border-radius:var(--radius-sm);min-width:140px">
        <div style="font-size:11px;font-weight:700;color:var(--primary);margin-bottom:2px">
          <?= e(date('g:i A', strtotime($p['start_time']??'00:00'))) ?>
          <?php if (!empty($p['end_time'])): ?>– <?= e(date('g:i A', strtotime($p['end_time']))) ?><?php endif; ?>
        </div>
        <div style="font-size:13px;font-weight:700"><?= e($p['subject_name']) ?></div>
        <div style="font-size:12px;color:var(--ink-soft)"><?= e($p['class_name']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── QUICK ACCESS ── -->
  <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-soft);margin-bottom:10px;padding:0 2px">Quick Access</div>

  <?php if (hasRole('class_teacher')): ?>
  <div style="font-size:11px;font-weight:600;color:var(--primary);margin-bottom:8px;padding:0 2px">🏫 Class Teacher</div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;margin-bottom:20px">
    <a href="<?= BASE_URL ?>/portal/teacher/class_dashboard.php" class="quick-item" style="border-color:var(--primary);flex-direction:row;align-items:center;gap:12px;padding:14px 16px">
      <span class="qi-icon">🏫</span>
      <div><strong>Class Dashboard</strong><small>Welfare, discipline, overview</small></div>
    </a>
    <a href="<?= BASE_URL ?>/portal/teacher/class_reports.php" class="quick-item" style="border-color:var(--primary);flex-direction:row;align-items:center;gap:12px;padding:14px 16px">
      <span class="qi-icon">📑</span>
      <div><strong>Class Reports</strong><small>Attendance, performance, comments</small></div>
    </a>
  </div>
  <?php endif; ?>

  <div style="font-size:11px;font-weight:600;color:var(--ink-soft);margin-bottom:8px;padding:0 2px">📚 Teaching</div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:20px">
    <a href="<?= BASE_URL ?>/portal/teacher/my_classes.php"  class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px 16px">
      <span class="qi-icon">🏫</span><div><strong>My Classes</strong><small><?= count($myClasses) ?> assigned</small></div>
    </a>
    <a href="<?= BASE_URL ?>/portal/teacher/students.php" class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px 16px">
      <span class="qi-icon">🎓</span><div><strong>My Students</strong><small><?= $totalStudents ?> total</small></div>
    </a>
    <a href="<?= BASE_URL ?>/portal/teacher/timetable.php" class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px 16px">
      <span class="qi-icon">📅</span><div><strong>Timetable</strong><small>My schedule</small></div>
    </a>
    <a href="<?= BASE_URL ?>/portal/teacher/materials.php" class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px 16px">
      <span class="qi-icon">📚</span><div><strong>Materials</strong><small><?= $materialCount ?> published</small></div>
    </a>
  </div>

  <div style="font-size:11px;font-weight:600;color:var(--ink-soft);margin-bottom:8px;padding:0 2px">📝 Assessment &amp; Marks</div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px;margin-bottom:20px">
    <a href="<?= BASE_URL ?>/portal/teacher/take_attendance.php" class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px 16px">
      <span class="qi-icon">📆</span><div><strong>Attendance</strong><small><?= $attToday ?> class<?= $attToday!==1?'es':'' ?> done</small></div>
    </a>
    <a href="<?= BASE_URL ?>/portal/teacher/enter_marks.php" class="quick-item <?= $draftMarks>0?'style="border-color:var(--warning)"':'' ?>" style="flex-direction:row;align-items:center;gap:12px;padding:14px 16px<?= $draftMarks>0?';border-color:var(--warning)':'' ?>">
      <span class="qi-icon">✏️</span><div><strong>Enter Marks</strong><small><?= $draftMarks ?> draft<?= $draftMarks!==1?'s':'' ?></small></div>
    </a>
    <a href="<?= BASE_URL ?>/portal/teacher/results.php" class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px 16px">
      <span class="qi-icon">📊</span><div><strong>Results</strong><small>Class performance</small></div>
    </a>
    <a href="<?= BASE_URL ?>/portal/teacher/assessments.php" class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px 16px">
      <span class="qi-icon">📝</span><div><strong>Assessments</strong><small><?= $asmCount ?> created</small></div>
    </a>
    <a href="<?= BASE_URL ?>/portal/teacher/quizzes.php" class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px 16px">
      <span class="qi-icon">🧠</span><div><strong>Online Quizzes</strong><small><?= $quizCount ?> quiz<?= $quizCount!==1?'zes':'' ?></small></div>
    </a>
    <a href="<?= BASE_URL ?>/portal/teacher/announcements.php" class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px 16px">
      <span class="qi-icon">📢</span><div><strong>Announcements</strong><small>Notices</small></div>
    </a>
  </div>

  <!-- ── CLASS PERFORMANCE ── -->
  <?php if (!empty($classPerf)): ?>
  <div class="panel" style="margin-bottom:20px">
    <div class="panel-heading">
      <div><h3>📊 Class Performance — <?= e($ay) ?></h3><p>Average mark across approved assessments</p></div>
      <a href="<?= BASE_URL ?>/portal/teacher/results.php" class="filter-button">Details →</a>
    </div>
    <div style="padding:0 20px 20px;display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:12px">
      <?php foreach ($classPerf as $cp):
        $pct = (float)($cp['avg_pct'] ?? 0);
        $col = perfColor($pct);
      ?>
      <div style="padding:14px;background:var(--bg2);border-radius:var(--radius-sm);border-top:3px solid <?= $col ?>;text-align:center">
        <div style="font-size:11px;font-weight:700;color:var(--ink-soft);margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em"><?= e($cp['class_name']) ?></div>
        <div style="font-size:1.6rem;font-weight:800;color:<?= $col ?>;line-height:1"><?= $pct > 0 ? $pct.'%' : '—' ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── BOTTOM PANELS ── -->
  <div class="td-panels">

    <!-- My classes list -->
    <?php if (!empty($myClasses)): ?>
    <div class="panel">
      <div class="panel-heading">
        <div><h3>🏫 My Classes</h3><p><?= e($ay) ?></p></div>
        <a href="<?= BASE_URL ?>/portal/teacher/my_classes.php" class="filter-button">All →</a>
      </div>
      <div style="padding:0 20px 16px">
        <?php foreach ($myClasses as $c): ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid var(--line)">
          <div>
            <strong style="font-size:13.5px"><?= e($c['name']) ?></strong>
            <span style="font-size:11.5px;color:var(--ink-soft);margin-left:6px"><?= e($c['grade_name']) ?></span>
          </div>
          <span style="font-size:12px;font-weight:700;color:var(--primary);background:var(--primary-soft);padding:3px 10px;border-radius:10px">
            <?= $c['enrol'] ?> students
          </span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- My subjects + Announcements -->
    <div style="display:flex;flex-direction:column;gap:16px">

      <!-- Subjects -->
      <?php if (!empty($mySubjects)): ?>
      <div class="panel">
        <div class="panel-heading">
          <div><h3>📖 My Subjects</h3></div>
        </div>
        <div style="padding:0 20px 16px;display:flex;flex-wrap:wrap;gap:8px">
          <?php foreach ($mySubjects as $sub): ?>
          <span style="background:var(--primary-soft);color:var(--primary);font-size:12.5px;font-weight:700;padding:5px 12px;border-radius:20px">
            <?= e($sub['name']) ?>
          </span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Pending marks detail -->
      <?php if (!empty($pendingMarksBatches)): ?>
      <div class="panel">
        <div class="panel-heading">
          <div><h3>⏳ Marks Needing Action</h3></div>
          <a href="<?= BASE_URL ?>/portal/teacher/enter_marks.php" class="filter-button">Go →</a>
        </div>
        <div style="padding:0 20px 16px">
          <?php foreach ($pendingMarksBatches as $mb): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--line)">
            <div>
              <strong style="font-size:13px"><?= e($mb['class_name']) ?></strong>
              <span style="font-size:12px;color:var(--ink-soft);margin-left:6px"><?= e($mb['subject_name']) ?></span>
            </div>
            <span class="status <?= $mb['status']==='returned'?'warning':'' ?>" style="font-size:11px">
              <?= ucfirst($mb['status']) ?> (<?= $mb['cnt'] ?>)
            </span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Announcements -->
      <div class="panel activity-panel">
        <div class="panel-heading">
          <div><h3>📢 Announcements</h3></div>
          <a href="<?= BASE_URL ?>/portal/teacher/announcements.php" class="filter-button">All →</a>
        </div>
        <?php if (empty($announcements)): ?>
        <p style="color:var(--ink-faint);font-size:13px;padding:12px 20px">No announcements.</p>
        <?php else: foreach ($announcements as $ann): ?>
        <div class="activity">
          <span class="activity-dot blue"></span>
          <div>
            <strong><?= e($ann['title']) ?></strong>
            <p><?= e(mb_substr($ann['message'], 0, 90)).(mb_strlen($ann['message'])>90?'…':'') ?></p>
            <small><?= date('M j, Y', strtotime($ann['published_at'])) ?></small>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>

    </div><!-- end right col -->
  </div><!-- end td-panels -->

</div><!-- portal-content -->
</div><!-- portal-grid -->

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
// Sidebar open/close
const sidebar  = document.getElementById('teacherSidebar');
const overlay  = document.getElementById('sidebarOverlay');
const openBtn  = document.getElementById('sidebarOpen');
const closeBtn = document.getElementById('sidebarClose');
function openSidebar()  { sidebar.classList.add('open'); overlay.classList.add('open'); document.body.style.overflow='hidden'; }
function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('open'); document.body.style.overflow=''; }
if (openBtn)  openBtn.addEventListener('click', openSidebar);
if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
if (overlay)  overlay.addEventListener('click', closeSidebar);
</script>
</body>
</html>
