<?php
// ============================================================
// Student Portal — Dashboard
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth();
requireRole('student');

$pdo  = db();
$user = currentUser();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();

// Get student record linked to this user
$student = $pdo->prepare("SELECT s.*,g.name grade_name,c.name class_name FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id LEFT JOIN classes c ON c.id=s.current_class_id WHERE s.user_id=? LIMIT 1");
$student->execute([$user['id']]); $student=$student->fetch();

if (!$student) {
    echo '<div style="font-family:sans-serif;padding:40px;text-align:center"><h2>Student record not found</h2><p>Please contact the school office to link your account.</p><a href="'.BASE_URL.'/admin/logout.php">Sign out</a></div>';
    exit;
}

// Metrics
$attStats = $pdo->prepare("SELECT SUM(status='Present') p,SUM(status='Absent') a,COUNT(*) t FROM attendance WHERE student_id=? AND academic_year_id=?");
$attStats->execute([$student['id'],$ayId]); $att=$attStats->fetch();
$attPct = ($att['t']>0) ? round(($att['p']/$att['t'])*100,1) : null;

$avgScore = $pdo->prepare("SELECT ROUND(AVG(marks_obtained/max_marks*100),1) FROM assessment_scores WHERE student_id=? AND academic_year_id=? AND status IN ('submitted','approved') AND marks_obtained IS NOT NULL");
$avgScore->execute([$student['id'],$ayId]); $avg=$avgScore->fetchColumn();

$feesPaid = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE student_id=? AND academic_year_id=? AND currency='LRD'");
$feesPaid->execute([$student['id'],$ayId]); $paid=$feesPaid->fetchColumn();

$announcements = $pdo->query("SELECT title,message,published_at FROM announcements WHERE target IN ('all','students') AND (expires_at IS NULL OR expires_at>NOW()) ORDER BY published_at DESC LIMIT 5")->fetchAll();

$ini = strtoupper(substr($student['first_name'],0,1).substr($student['last_name'],0,1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Student Portal — KARN HIGH SCHOOL</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
  <aside class="portal-sidebar">
    <div class="portal-brand">
      <div class="brand">
        <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="KHS"/><span><strong>KHS</strong><small>Student Portal</small></span>
      </div>
    </div>
    <nav class="portal-nav">
      <a href="<?= BASE_URL ?>/portal/student/" class="active">🏠 Dashboard</a>
      <a href="<?= BASE_URL ?>/portal/student/my_results.php">📊 My Results</a>
      <a href="<?= BASE_URL ?>/portal/student/attendance.php">📆 My Attendance</a>
      <a href="<?= BASE_URL ?>/portal/student/report_card.php">📑 Report Card</a>
      <a href="<?= BASE_URL ?>/portal/student/fees.php">💰 Fees & Payments</a>
      <a href="<?= BASE_URL ?>/portal/student/timetable.php">📅 Timetable</a>
      <a href="<?= BASE_URL ?>/portal/student/announcements.php">📢 Announcements</a>
    </nav>
    <div class="sidebar-bottom" style="border-top:1px solid var(--line);padding:12px">
      <a href="<?= BASE_URL ?>/admin/logout.php" style="color:var(--error);font-size:13px;font-weight:600">Sign Out</a>
    </div>
  </aside>

  <div class="portal-content">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
      <div>
        <h1 style="font-size:26px;font-weight:800;margin-bottom:4px">Hello, <?= e($student['first_name']) ?>! 👋</h1>
        <p style="color:var(--ink-soft)"><?= e($student['grade_name']??'') ?><?= $student['class_name']?' / '.e($student['class_name']):'' ?> &mdash; <?= e($ay) ?></p>
      </div>
      <div style="display:flex;align-items:center;gap:12px">
        <div class="avatar" style="width:46px;height:46px;font-size:16px"><?= e($ini) ?></div>
        <div><strong><?= e($student['first_name'].' '.$student['last_name']) ?></strong><div style="font-size:12px;color:var(--ink-faint)"><?= e($student['student_id']) ?></div></div>
      </div>
    </div>

    <!-- Metric cards -->
    <div class="metric-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
      <div class="metric-card"><div class="metric-top"><span>Current Average</span><div class="metric-icon">📊</div></div><strong><?= $avg ? $avg.'%' : '—' ?></strong><small><i></i><?= e($ay) ?></small></div>
      <div class="metric-card"><div class="metric-top"><span>Attendance Rate</span><div class="metric-icon">📆</div></div><strong><?= $attPct !== null ? $attPct.'%' : '—' ?></strong><small><i></i>Days present: <?= $att['p']??0 ?></small></div>
      <div class="metric-card finance-metrics"><div class="metric-top"><span>Fees Paid (LRD)</span><div class="metric-icon">💰</div></div><strong>LRD <?= number_format($paid) ?></strong><small><i></i><?= e($ay) ?></small></div>
    </div>

    <!-- Quick links -->
    <div class="quick-grid" style="margin-bottom:24px">
      <a href="<?= BASE_URL ?>/portal/student/my_results.php" class="quick-item"><span class="qi-icon">📊</span><div><strong>My Results</strong><small>View marks & grades</small></div></a>
      <a href="<?= BASE_URL ?>/portal/student/report_card.php" class="quick-item"><span class="qi-icon">📑</span><div><strong>Report Card</strong><small>Download report card</small></div></a>
      <a href="<?= BASE_URL ?>/portal/student/fees.php" class="quick-item"><span class="qi-icon">💰</span><div><strong>Fees</strong><small>Payment history</small></div></a>
      <a href="<?= BASE_URL ?>/portal/student/timetable.php" class="quick-item"><span class="qi-icon">📅</span><div><strong>Timetable</strong><small>Class schedule</small></div></a>
    </div>

    <!-- Announcements -->
    <?php if (!empty($announcements)): ?>
    <div class="panel">
      <div class="panel-heading"><div><h3>Announcements</h3></div><a href="<?= BASE_URL ?>/portal/student/announcements.php" class="filter-button">View all →</a></div>
      <?php foreach ($announcements as $ann): ?>
      <div class="activity">
        <span class="activity-dot pink"></span>
        <div><strong><?= e($ann['title']) ?></strong><p><?= e(mb_substr($ann['message'],0,100)).(mb_strlen($ann['message'])>100?'…':'') ?></p><small><?= date('M d, Y',strtotime($ann['published_at'])) ?></small></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
