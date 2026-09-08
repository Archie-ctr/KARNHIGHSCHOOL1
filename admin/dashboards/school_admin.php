<?php
$pageTitle='School Administrator'; $activeAdmin='dashboard';
require_once dirname(dirname(__DIR__)).'/includes/admin_header.php';
requireRole(['school_admin','sys_admin','super_admin']);

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();
$fn   = explode(' ', currentUser()['name'] ?? 'Admin')[0];
$hour = (int)date('G');
$greet= $hour<12 ? 'Good morning' : ($hour<17 ? 'Good afternoon' : 'Good evening');

// ── Core metrics ──────────────────────────────────────────────
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status='Active' AND academic_year_id=$ayId")->fetchColumn();
$totalTeachers = (int)$pdo->query("SELECT COUNT(*) FROM teachers WHERE status='Active'")->fetchColumn();
$totalClasses  = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE academic_year_id=$ayId")->fetchColumn();
$pendingApps   = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status IN ('Application Submitted','Under Review')")->fetchColumn();
$totalGuardians= (int)$pdo->query("SELECT COUNT(*) FROM guardians")->fetchColumn();

// Attendance rate today
try {
    $attToday = $pdo->query("SELECT ROUND(SUM(status='Present')/COUNT(*)*100,1) FROM attendance WHERE date=CURDATE()")->fetchColumn();
} catch (Throwable $e) { $attToday = null; }

// Staff count (non-student/parent)
$totalStaff = (int)$pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE u.is_active=1 AND r.name NOT IN ('student','parent','applicant')")->fetchColumn();

// Recent announcements
$recentAnns = $pdo->query("SELECT title, published_at FROM announcements WHERE (expires_at IS NULL OR expires_at>NOW()) ORDER BY published_at DESC LIMIT 5")->fetchAll();

// Recent students
$recentStudents = $pdo->prepare("SELECT first_name,last_name,student_id,current_grade_id,admission_date FROM students WHERE academic_year_id=? ORDER BY admission_date DESC,id DESC LIMIT 5");
$recentStudents->execute([$ayId]); $recentStudents=$recentStudents->fetchAll();

// Approval center
$approvals = countPendingApprovals();

// Departments and houses counts
try {
    $deptCount  = (int)$pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
} catch (Throwable $e) { $deptCount = 0; }
try {
    $houseCount = (int)$pdo->query("SELECT COUNT(*) FROM school_houses")->fetchColumn();
} catch (Throwable $e) { $houseCount = 0; }
?>

<div class="page-heading">
  <div>
    <div class="eyebrow"><?= date('l, F d, Y') ?> <span></span></div>
    <h1><?= $greet ?>, <?= e($fn) ?>.</h1>
    <p>School Administrator &mdash; <?= e($ay) ?></p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if ($approvals['_total'] > 0 && can('approvals.act')): ?>
    <a href="<?= BASE_URL ?>/admin/approval_center.php" class="button button-secondary">
      ✅ Approvals <span style="background:var(--error);color:#fff;border-radius:20px;padding:1px 7px;font-size:11px;margin-left:4px"><?= $approvals['_total'] ?></span>
    </a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/admin/settings.php" class="button button-primary">⚙️ Settings</a>
  </div>
</div>

<!-- ── Key Metrics ────────────────────────────────────────────── -->
<div class="metric-grid" style="margin-bottom:24px">
  <div class="metric-card">
    <div class="metric-top"><span>Active Students</span><div class="metric-icon">🎓</div></div>
    <strong><?= number_format($totalStudents) ?></strong>
    <small><i></i><?= e($ay) ?></small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Teaching Staff</span><div class="metric-icon">👩‍🏫</div></div>
    <strong><?= $totalTeachers ?></strong>
    <small><i></i><?= $totalStaff ?> total staff</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Classes</span><div class="metric-icon">🏫</div></div>
    <strong><?= $totalClasses ?></strong>
    <small><i></i><?= e($ay) ?></small>
  </div>
  <div class="metric-card <?= $pendingApps>0?'finance-metrics':'' ?>">
    <div class="metric-top"><span>Pending Admissions</span><div class="metric-icon">📋</div></div>
    <strong><?= $pendingApps ?></strong>
    <small><i></i>Awaiting review</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Guardians</span><div class="metric-icon">👨‍👩‍👧</div></div>
    <strong><?= number_format($totalGuardians) ?></strong>
    <small><i></i>On record</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Attendance Today</span><div class="metric-icon">📆</div></div>
    <strong><?= $attToday !== null ? $attToday.'%' : '—' ?></strong>
    <small><i></i><?= date('M d') ?></small>
  </div>
</div>

<!-- ── Quick Access Grid — All school_admin sections ─────────── -->
<h3 style="font-size:13px;font-weight:700;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.07em;margin-bottom:12px">Quick Access</h3>

<!-- 🏫 School Management -->
<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-soft);margin-bottom:8px;padding-left:2px">🏫 School Management</div>
<div class="quick-grid" style="margin-bottom:20px">
  <a href="<?= BASE_URL ?>/admin/settings.php"            class="quick-item"><span class="qi-icon">⚙️</span><div><strong>School Profile</strong><small>Name, logo, contacts</small></div></a>
  <a href="<?= BASE_URL ?>/admin/settings.php?tab=school" class="quick-item"><span class="qi-icon">📞</span><div><strong>Contact Info</strong><small>Phone, email, address</small></div></a>
  <a href="<?= BASE_URL ?>/admin/academic_years.php"       class="quick-item"><span class="qi-icon">📅</span><div><strong>Academic Calendar</strong><small>Years, terms, periods</small></div></a>
  <a href="<?= BASE_URL ?>/admin/events_admin.php"         class="quick-item"><span class="qi-icon">🎉</span><div><strong>School Events</strong><small>Calendar events</small></div></a>
  <a href="<?= BASE_URL ?>/admin/departments.php"          class="quick-item"><span class="qi-icon">🏢</span><div><strong>Departments &amp; Houses</strong><small><?= $deptCount ?> depts · <?= $houseCount ?> houses</small></div></a>
  <a href="<?= BASE_URL ?>/admin/announcements.php"        class="quick-item"><span class="qi-icon">📢</span><div><strong>Announcements</strong><small>School communications</small></div></a>
</div>

<!-- 👩‍🎓 Student Administration -->
<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-soft);margin-bottom:8px;padding-left:2px">👩‍🎓 Student Administration</div>
<div class="quick-grid" style="margin-bottom:20px">
  <a href="<?= BASE_URL ?>/admin/students.php"             class="quick-item"><span class="qi-icon">🎓</span><div><strong>Student Directory</strong><small><?= number_format($totalStudents) ?> active students</small></div></a>
  <a href="<?= BASE_URL ?>/admin/students.php"             class="quick-item"><span class="qi-icon">📝</span><div><strong>Registration</strong><small>Add / enroll students</small></div></a>
  <a href="<?= BASE_URL ?>/admin/guardians.php"            class="quick-item"><span class="qi-icon">👨‍👩‍👧</span><div><strong>Parent &amp; Guardians</strong><small><?= number_format($totalGuardians) ?> records</small></div></a>
  <a href="<?= BASE_URL ?>/admin/documents.php"            class="quick-item"><span class="qi-icon">📄</span><div><strong>Student Documents</strong><small>Files &amp; certificates</small></div></a>
  <a href="<?= BASE_URL ?>/admin/student_idcards.php"      class="quick-item"><span class="qi-icon">🪪</span><div><strong>ID Cards</strong><small>Print student IDs</small></div></a>
  <a href="<?= BASE_URL ?>/admin/student_statistics.php"   class="quick-item"><span class="qi-icon">📊</span><div><strong>Student Statistics</strong><small>Enrollment analytics</small></div></a>
  <a href="<?= BASE_URL ?>/admin/promotion.php"            class="quick-item"><span class="qi-icon">⬆️</span><div><strong>Promotion</strong><small>Advance students</small></div></a>
</div>

<!-- 👨‍🏫 Staff Management -->
<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-soft);margin-bottom:8px;padding-left:2px">👨‍🏫 Staff Management</div>
<div class="quick-grid" style="margin-bottom:20px">
  <a href="<?= BASE_URL ?>/admin/teachers_admin.php"       class="quick-item"><span class="qi-icon">👩‍🏫</span><div><strong>Teacher Records</strong><small><?= $totalTeachers ?> active teachers</small></div></a>
  <a href="<?= BASE_URL ?>/admin/users.php"                class="quick-item"><span class="qi-icon">👥</span><div><strong>All Staff Accounts</strong><small><?= $totalStaff ?> staff users</small></div></a>
  <a href="<?= BASE_URL ?>/admin/teacher_assignments.php"  class="quick-item"><span class="qi-icon">🔗</span><div><strong>Staff Assignments</strong><small>Classes &amp; subjects</small></div></a>
  <a href="<?= BASE_URL ?>/admin/staff_attendance.php"     class="quick-item"><span class="qi-icon">📆</span><div><strong>Staff Attendance</strong><small>Daily staff record</small></div></a>
  <a href="<?= BASE_URL ?>/admin/departments.php"          class="quick-item"><span class="qi-icon">🏢</span><div><strong>Departments</strong><small><?= $deptCount ?> departments</small></div></a>
</div>

<!-- 📚 Academic Administration -->
<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-soft);margin-bottom:8px;padding-left:2px">📚 Academic Administration</div>
<div class="quick-grid" style="margin-bottom:20px">
  <a href="<?= BASE_URL ?>/admin/classes.php"              class="quick-item"><span class="qi-icon">🏫</span><div><strong>Classes</strong><small><?= $totalClasses ?> this year</small></div></a>
  <a href="<?= BASE_URL ?>/admin/subjects.php"             class="quick-item"><span class="qi-icon">📚</span><div><strong>Subjects</strong><small>Subject catalogue</small></div></a>
  <a href="<?= BASE_URL ?>/admin/timetable.php"            class="quick-item"><span class="qi-icon">⏰</span><div><strong>Timetable</strong><small>Class schedules</small></div></a>
  <a href="<?= BASE_URL ?>/admin/attendance.php"           class="quick-item"><span class="qi-icon">📆</span><div><strong>Student Attendance</strong><small><?= $attToday !== null ? $attToday.'% today' : 'Monitor' ?></small></div></a>
</div>

<!-- 📊 Reports -->
<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-soft);margin-bottom:8px;padding-left:2px">📊 Reports</div>
<div class="quick-grid" style="margin-bottom:24px">
  <a href="<?= BASE_URL ?>/admin/reports.php?tab=student"        class="quick-item"><span class="qi-icon">🎓</span><div><strong>Student Reports</strong><small>Register, statistics</small></div></a>
  <a href="<?= BASE_URL ?>/admin/reports.php?tab=staff"          class="quick-item"><span class="qi-icon">👥</span><div><strong>Staff Reports</strong><small>Teachers &amp; staff</small></div></a>
  <a href="<?= BASE_URL ?>/admin/reports.php?tab=attendance_rep" class="quick-item"><span class="qi-icon">📆</span><div><strong>Attendance Reports</strong><small>Rates &amp; absentees</small></div></a>
  <a href="<?= BASE_URL ?>/admin/reports.php?tab=admin_rep"      class="quick-item"><span class="qi-icon">📋</span><div><strong>Admin Reports</strong><small>Enrollment, depts, AY</small></div></a>
  <a href="<?= BASE_URL ?>/admin/student_statistics.php"         class="quick-item"><span class="qi-icon">📈</span><div><strong>Statistics</strong><small>Charts &amp; trends</small></div></a>
</div>

<!-- ── Recent activity panels ────────────────────────────────── -->
<div class="dash-columns">
  <!-- Recent new students -->
  <div class="panel activity-panel">
    <div class="panel-heading">
      <div><h3>Recent Enrollments</h3><p><?= e($ay) ?></p></div>
      <a href="<?= BASE_URL ?>/admin/students.php" class="filter-button">All students →</a>
    </div>
    <?php if (empty($recentStudents)): ?>
    <p style="color:var(--ink-faint);font-size:13px;padding:12px">No students enrolled yet.</p>
    <?php else: foreach ($recentStudents as $st): ?>
    <div class="activity">
      <span class="activity-dot green"></span>
      <div>
        <strong><?= e($st['first_name'].' '.$st['last_name']) ?></strong>
        <p><?= e($st['student_id']) ?></p>
        <small><?= $st['admission_date'] ? date('M d, Y', strtotime($st['admission_date'])) : '—' ?></small>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Recent announcements -->
  <div class="panel activity-panel">
    <div class="panel-heading">
      <div><h3>Announcements</h3><p>Latest notices</p></div>
      <a href="<?= BASE_URL ?>/admin/announcements.php" class="filter-button">All →</a>
    </div>
    <?php if (empty($recentAnns)): ?>
    <p style="color:var(--ink-faint);font-size:13px;padding:12px">No announcements yet.</p>
    <?php else: foreach ($recentAnns as $ann): ?>
    <div class="activity">
      <span class="activity-dot blue"></span>
      <div>
        <strong><?= e($ann['title']) ?></strong>
        <small><?= date('M d, Y', strtotime($ann['published_at'])) ?></small>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<?php require_once dirname(dirname(__DIR__)).'/includes/admin_footer.php'; ?>
