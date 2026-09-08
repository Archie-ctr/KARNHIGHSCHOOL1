<?php
// ============================================================
// Student Portal — Dashboard
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('student');

$pdo        = db();
$user       = currentUser();
$activePage = 'dashboard';
$ayId       = currentAcademicYearId();
$ay         = currentAcademicYearName();

// Student record
$student = $pdo->prepare(
    "SELECT s.*, g.name grade_name, c.name class_name
     FROM students s
     LEFT JOIN grades  g ON g.id = s.current_grade_id
     LEFT JOIN classes c ON c.id = s.current_class_id
     WHERE s.user_id = ? LIMIT 1"
);
$student->execute([$user['id']]);
$student = $student->fetch();

if (!$student) {
    echo '<div style="font-family:sans-serif;padding:48px;text-align:center">
            <h2>Student record not found</h2>
            <p>Please contact the school office to link your account.</p>
            <a href="'.BASE_URL.'/admin/logout.php">Sign out</a>
          </div>';
    exit;
}

$ini = strtoupper(substr($student['first_name'],0,1).substr($student['last_name'],0,1));

// Attendance stats
try {
    $attStats = $pdo->prepare(
        "SELECT SUM(status='Present') p, SUM(status='Absent') a, COUNT(*) t
         FROM attendance WHERE student_id=? AND academic_year_id=?"
    );
    $attStats->execute([$student['id'], $ayId]);
    $att = $attStats->fetch();
    $attPct = ($att['t'] > 0) ? round(($att['p'] / $att['t']) * 100, 1) : null;
} catch (Throwable $e) { $att = ['p'=>0,'a'=>0,'t'=>0]; $attPct = null; }

// Academic average
try {
    $avgStmt = $pdo->prepare(
        "SELECT ROUND(AVG(marks_obtained/max_marks*100),1)
         FROM assessment_scores
         WHERE student_id=? AND academic_year_id=?
           AND status IN ('submitted','approved')
           AND marks_obtained IS NOT NULL AND max_marks > 0"
    );
    $avgStmt->execute([$student['id'], $ayId]);
    $avg = $avgStmt->fetchColumn();
} catch (Throwable $e) { $avg = null; }

// Fees paid
try {
    $feesStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount),0)
         FROM payments WHERE student_id=? AND academic_year_id=? AND currency='LRD'"
    );
    $feesStmt->execute([$student['id'], $ayId]);
    $paid = (float)$feesStmt->fetchColumn();
} catch (Throwable $e) { $paid = 0; }

// Total fees due
try {
    $dueStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount),0)
         FROM fee_structures
         WHERE academic_year_id=? AND is_active=1 AND currency='LRD'
           AND (grade_id IS NULL OR grade_id=?)"
    );
    $dueStmt->execute([$ayId, $student['current_grade_id']]);
    $due = (float)$dueStmt->fetchColumn();
} catch (Throwable $e) { $due = 0; }

// Latest announcements
try {
    $anns = $pdo->query(
        "SELECT title, message, published_at
         FROM announcements
         WHERE target IN ('all','students')
           AND (expires_at IS NULL OR expires_at > NOW())
         ORDER BY published_at DESC LIMIT 4"
    )->fetchAll();
} catch (Throwable $e) { $anns = []; }

// Currently borrowed books
try {
    $borrowed = $pdo->prepare(
        "SELECT COUNT(*) FROM library_borrowings
         WHERE student_id=? AND returned_at IS NULL"
    );
    $borrowed->execute([$student['id']]);
    $borrowedCount = (int)$borrowed->fetchColumn();
} catch (Throwable $e) { $borrowedCount = 0; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Student Portal — KARN HIGH SCHOOL</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>

<div class="portal-content">

  <!-- Header -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
    <div>
      <h1 style="font-size:24px;font-weight:800;margin-bottom:4px">
        Hello, <?= e($student['first_name']) ?>! 👋
      </h1>
      <p style="color:var(--ink-soft);font-size:13px">
        <?= e($student['grade_name'] ?? '') ?>
        <?= $student['class_name'] ? ' / '.e($student['class_name']) : '' ?>
        &mdash; <?= e($ay) ?>
      </p>
    </div>
    <div style="display:flex;align-items:center;gap:12px">
      <div class="avatar" style="width:44px;height:44px;font-size:15px"><?= e($ini) ?></div>
      <div>
        <strong style="font-size:14px"><?= e($student['first_name'].' '.$student['last_name']) ?></strong>
        <div style="font-size:11px;color:var(--ink-faint)"><?= e($student['student_id']) ?></div>
      </div>
    </div>
  </div>

  <!-- Metric cards -->
  <div class="metric-grid" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr));margin-bottom:24px">
    <div class="metric-card">
      <div class="metric-top"><span>Current Average</span><div class="metric-icon">📊</div></div>
      <strong><?= $avg ? $avg.'%' : '—' ?></strong>
      <small><i></i><?= e($ay) ?></small>
    </div>
    <div class="metric-card">
      <div class="metric-top"><span>Attendance Rate</span><div class="metric-icon">📆</div></div>
      <strong><?= $attPct !== null ? $attPct.'%' : '—' ?></strong>
      <small><i></i>Days present: <?= $att['p'] ?? 0 ?></small>
    </div>
    <div class="metric-card <?= ($due > 0 && $paid < $due) ? 'finance-metrics' : '' ?>">
      <div class="metric-top"><span>Fees Balance</span><div class="metric-icon">💰</div></div>
      <strong style="<?= ($due > 0 && $paid < $due) ? 'color:var(--error)' : 'color:var(--green)' ?>">
        LRD <?= number_format($due - $paid) ?>
      </strong>
      <small><i></i><?= ($due > 0 && $paid >= $due) ? 'Fully paid' : 'Outstanding' ?></small>
    </div>
    <div class="metric-card">
      <div class="metric-top"><span>Books Borrowed</span><div class="metric-icon">📖</div></div>
      <strong><?= $borrowedCount ?></strong>
      <small><i></i>Currently out</small>
    </div>
  </div>

  <!-- Quick links -->
  <div style="margin-bottom:24px">
    <h3 style="font-size:13px;font-weight:700;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.07em;margin-bottom:12px">Quick Access</h3>
    <div class="quick-grid">
      <a href="<?= BASE_URL ?>/portal/student/my_results.php" class="quick-item">
        <span class="qi-icon">📊</span>
        <div><strong>My Results</strong><small>View marks &amp; grades</small></div>
      </a>
      <a href="<?= BASE_URL ?>/portal/student/report_card.php" class="quick-item">
        <span class="qi-icon">📑</span>
        <div><strong>Report Card</strong><small>Download report card</small></div>
      </a>
      <a href="<?= BASE_URL ?>/portal/student/fees.php" class="quick-item">
        <span class="qi-icon">💰</span>
        <div><strong>Fees &amp; Payments</strong><small>View balance &amp; history</small></div>
      </a>
      <a href="<?= BASE_URL ?>/portal/student/timetable.php" class="quick-item">
        <span class="qi-icon">📅</span>
        <div><strong>Timetable</strong><small>Class schedule</small></div>
      </a>
      <a href="<?= BASE_URL ?>/portal/student/subjects.php" class="quick-item">
        <span class="qi-icon">📚</span>
        <div><strong>My Subjects</strong><small>Subjects this year</small></div>
      </a>
      <a href="<?= BASE_URL ?>/portal/student/library.php" class="quick-item">
        <span class="qi-icon">📖</span>
        <div><strong>Library</strong><small>Borrowed books</small></div>
      </a>
    </div>
  </div>

  <!-- Announcements -->
  <?php if (!empty($anns)): ?>
  <div class="panel">
    <div class="panel-heading">
      <div><h3>Latest Announcements</h3></div>
      <a href="<?= BASE_URL ?>/portal/student/announcements.php" class="filter-button">View all →</a>
    </div>
    <?php foreach ($anns as $ann): ?>
    <div class="activity">
      <span class="activity-dot pink"></span>
      <div>
        <strong><?= e($ann['title']) ?></strong>
        <p><?= e(mb_substr($ann['message'], 0, 120)).(mb_strlen($ann['message']) > 120 ? '…' : '') ?></p>
        <small><?= date('M d, Y', strtotime($ann['published_at'])) ?></small>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
