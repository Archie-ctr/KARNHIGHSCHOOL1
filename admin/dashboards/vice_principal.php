<?php
$pageTitle='Vice Principal Dashboard'; $activeAdmin='dashboard';
require_once dirname(dirname(__DIR__)).'/includes/admin_header.php';
requireRole(['vice_principal','vice_principal_alt','academic_dean','principal','sys_admin','super_admin','school_admin']);

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();
$fn   = explode(' ', currentUser()['name'] ?? 'VP')[0];
$hour = (int)date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$approvals = countPendingApprovals();
$total = $approvals['_total'] ?? 0;

// ── KPI metrics ───────────────────────────────────────────────
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status='Active' AND academic_year_id=$ayId")->fetchColumn();
$totalTeachers = (int)$pdo->query("SELECT COUNT(*) FROM teachers WHERE status='Active'")->fetchColumn();
$totalClasses  = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE academic_year_id=$ayId")->fetchColumn();

// Marks pending VP review
$pendingMarks = (int)$pdo->query(
    "SELECT COUNT(DISTINCT class_id,subject_id,assessment_config_id)
     FROM assessment_scores WHERE status IN ('submitted','resubmitted') AND academic_year_id=$ayId"
)->fetchColumn();

// Attendance today and year rate
$attToday = $pdo->query("SELECT ROUND(SUM(status='Present')/NULLIF(COUNT(*),0)*100,1) FROM attendance WHERE date=CURDATE()")->fetchColumn();
try {
    $attYearRate = (float)$pdo->query("SELECT ROUND(SUM(status='Present')/NULLIF(COUNT(*),0)*100,1) FROM attendance WHERE academic_year_id=$ayId")->fetchColumn();
} catch (Throwable $e) { $attYearRate = 0; }

// Open discipline cases
$openCases = (int)$pdo->query("SELECT COUNT(*) FROM discipline_records WHERE resolved=0 AND academic_year_id=$ayId")->fetchColumn();

// At-risk students (below 50% OR below 75% attendance)
try {
    $atRiskCount = (int)$pdo->query(
        "SELECT COUNT(DISTINCT s.id)
         FROM students s
         LEFT JOIN (SELECT student_id, ROUND(AVG(marks_obtained/max_marks*100),1) avg_pct
                    FROM assessment_scores WHERE academic_year_id=$ayId AND max_marks>0
                      AND status IN ('approved','published')
                    GROUP BY student_id) m ON m.student_id=s.id
         LEFT JOIN (SELECT student_id, ROUND(SUM(status='Present')/NULLIF(COUNT(*),0)*100,1) att_rate
                    FROM attendance WHERE academic_year_id=$ayId GROUP BY student_id) a ON a.student_id=s.id
         WHERE s.academic_year_id=$ayId AND s.status='Active'
           AND ((m.avg_pct IS NOT NULL AND m.avg_pct < 50) OR (a.att_rate IS NOT NULL AND a.att_rate < 75))"
    )->fetchColumn();
} catch (Throwable $e) { $atRiskCount = 0; }

// Published report cards
$publishedRC = (int)$pdo->query("SELECT COUNT(*) FROM report_cards WHERE status='published' AND academic_year_id=$ayId")->fetchColumn();

// Overall academic average
try {
    $overallAvg = (float)$pdo->query(
        "SELECT ROUND(AVG(marks_obtained/max_marks*100),1) FROM assessment_scores
         WHERE academic_year_id=$ayId AND max_marks>0 AND status IN ('approved','published')"
    )->fetchColumn();
} catch (Throwable $e) { $overallAvg = 0; }

// ── Marks pending staff attendance ───────────────────────────
try {
    $staffAttToday = $pdo->query(
        "SELECT COUNT(DISTINCT user_id) FROM staff_attendance WHERE date=CURDATE()"
    )->fetchColumn();
} catch (Throwable $e) { $staffAttToday = null; }

// ── Grade performance snapshot ────────────────────────────────
$gradeSnap = $pdo->prepare(
    "SELECT g.name, g.sequence,
            COUNT(DISTINCT s.id) students,
            ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1) avg_pct
     FROM students s
     LEFT JOIN assessment_scores asc2 ON asc2.student_id=s.id
           AND asc2.academic_year_id=? AND asc2.max_marks>0
           AND asc2.status IN ('approved','published')
     JOIN grades g ON g.id=s.current_grade_id
     WHERE s.academic_year_id=? AND s.status='Active'
     GROUP BY g.id, g.name, g.sequence ORDER BY g.sequence"
);
$gradeSnap->execute([$ayId,$ayId]);
$gradeSnap = $gradeSnap->fetchAll();

// ── Teacher submission status ─────────────────────────────────
$teacherSubmit = $pdo->prepare(
    "SELECT CONCAT(u.first_name,' ',u.last_name) tname,
            SUM(CASE WHEN asc2.status='draft' THEN 1 ELSE 0 END) drafts,
            SUM(CASE WHEN asc2.status IN ('submitted','resubmitted') THEN 1 ELSE 0 END) submitted
     FROM teachers t JOIN users u ON u.id=t.user_id
     LEFT JOIN assessment_scores asc2 ON asc2.entered_by=u.id AND asc2.academic_year_id=?
     WHERE t.status='Active'
     GROUP BY u.id ORDER BY submitted DESC LIMIT 8"
);
$teacherSubmit->execute([$ayId]);
$teacherSubmit = $teacherSubmit->fetchAll();

// ── Recent discipline cases ───────────────────────────────────
$recentDisc = $pdo->prepare(
    "SELECT d.*, CONCAT(s.first_name,' ',s.last_name) sname, g.name grade_name
     FROM discipline_records d JOIN students s ON s.id=d.student_id
     LEFT JOIN grades g ON g.id=s.current_grade_id
     WHERE d.academic_year_id=? ORDER BY d.incident_date DESC LIMIT 6"
);
$recentDisc->execute([$ayId]);
$recentDisc = $recentDisc->fetchAll();

// ── Attendance by grade ───────────────────────────────────────
try {
    $attByGrade = $pdo->prepare(
        "SELECT g.name grade_name,
                ROUND(SUM(a.status='Present')/NULLIF(COUNT(*),0)*100,1) rate
         FROM attendance a JOIN students s ON s.id=a.student_id
         JOIN grades g ON g.id=s.current_grade_id
         WHERE a.academic_year_id=?
         GROUP BY g.id, g.name, g.sequence ORDER BY g.sequence"
    );
    $attByGrade->execute([$ayId]);
    $attByGrade = $attByGrade->fetchAll();
} catch (Throwable $e) { $attByGrade = []; }

// ── Pending promotion candidates ─────────────────────────────
try {
    $promoPending = (int)$pdo->query(
        "SELECT COUNT(*) FROM promotion_records WHERE academic_year_id=$ayId AND status='Promoted'"
    )->fetchColumn();
} catch (Throwable $e) { $promoPending = 0; }

function pColor(float $v): string {
    return $v >= 70 ? 'var(--green)' : ($v >= 50 ? 'var(--warning)' : 'var(--error)');
}
?>

<div class="page-heading">
  <div>
    <div class="eyebrow"><?= date('l, F d, Y') ?> <span></span></div>
    <h1><?= $greet ?>, <?= e($fn) ?>.</h1>
    <p>Vice Principal — <?= e($ay) ?></p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="<?= BASE_URL ?>/admin/vp_academic_overview.php" class="button button-secondary">📊 Academic Overview</a>
    <?php if ($total > 0): ?>
    <a href="<?= BASE_URL ?>/admin/approval_center.php" class="button button-primary" style="position:relative">
      ✅ Approvals
      <span style="position:absolute;top:-8px;right:-8px;background:var(--error);color:#fff;font-size:11px;font-weight:800;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;line-height:1"><?= $total ?></span>
    </a>
    <?php else: ?>
    <a href="<?= BASE_URL ?>/admin/approval_center.php" class="button button-primary">✅ Approval Center</a>
    <?php endif; ?>
  </div>
</div>

<!-- Pending marks alert -->
<?php if ($pendingMarks > 0): ?>
<div class="alert alert-info" style="margin-bottom:20px">
  ✏️ <strong><?= $pendingMarks ?> mark batch<?= $pendingMarks!==1?'es':'' ?></strong> submitted by teachers and awaiting your review.
  <a href="<?= BASE_URL ?>/admin/marks_approval.php" style="font-weight:700;margin-left:8px">Review now →</a>
</div>
<?php endif; ?>
<?php if ($atRiskCount > 0): ?>
<div class="alert alert-warn" style="margin-bottom:20px">
  ⚠️ <strong><?= $atRiskCount ?> at-risk student<?= $atRiskCount!==1?'s':'' ?></strong> identified with low marks or attendance below 75%.
  <a href="<?= BASE_URL ?>/admin/vp_academic_overview.php?tab=atrisk" style="font-weight:700;margin-left:8px">View list →</a>
</div>
<?php endif; ?>

<!-- ═══════ KPI METRICS ═══════ -->
<div class="metric-grid" style="margin-bottom:24px">
  <div class="metric-card">
    <div class="metric-top"><span>Active Students</span><div class="metric-icon">🎓</div></div>
    <strong><?= number_format($totalStudents) ?></strong><small><i></i><?= e($ay) ?></small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Academic Average</span><div class="metric-icon">📊</div></div>
    <strong style="color:<?= pColor($overallAvg) ?>"><?= $overallAvg ?>%</strong>
    <small><i></i>Approved marks</small>
  </div>
  <div class="metric-card <?= $attYearRate < 80 ? 'finance-metrics' : '' ?>">
    <div class="metric-top"><span>Attendance Rate</span><div class="metric-icon">📆</div></div>
    <strong style="color:<?= $attYearRate >= 80 ? 'var(--green)' : 'var(--error)' ?>"><?= $attYearRate ?>%</strong>
    <small><i></i>Today: <?= $attToday !== null ? $attToday.'%' : '—' ?></small>
  </div>
  <div class="metric-card <?= $pendingMarks > 0 ? 'finance-metrics' : '' ?>">
    <div class="metric-top"><span>Marks Pending</span><div class="metric-icon">✏️</div></div>
    <strong style="color:<?= $pendingMarks > 0 ? 'var(--error)' : 'inherit' ?>"><?= $pendingMarks ?></strong>
    <small><i></i>Batches to review</small>
  </div>
  <div class="metric-card <?= $openCases > 0 ? 'finance-metrics' : '' ?>">
    <div class="metric-top"><span>Open Discipline</span><div class="metric-icon">⚖️</div></div>
    <strong style="color:<?= $openCases > 0 ? 'var(--warning)' : 'inherit' ?>"><?= $openCases ?></strong>
    <small><i></i>Unresolved cases</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Report Cards</span><div class="metric-icon">📑</div></div>
    <strong><?= $publishedRC ?></strong><small><i></i>Published</small>
  </div>
</div>

<!-- ═══════ QUICK ACCESS ═══════ -->
<h3 style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-soft);margin-bottom:10px">Quick Access</h3>

<!-- Academic Supervision -->
<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:7px">📚 Academic Supervision</div>
<div class="quick-grid" style="margin-bottom:16px">
  <a href="<?= BASE_URL ?>/admin/vp_academic_overview.php"            class="quick-item"><span class="qi-icon">📊</span><div><strong>Academic Overview</strong><small>Performance &amp; coverage</small></div></a>
  <a href="<?= BASE_URL ?>/admin/marks_approval.php"                  class="quick-item"><span class="qi-icon">✏️</span><div><strong>Marks Approval</strong><small><?= $pendingMarks ?> pending</small></div></a>
  <a href="<?= BASE_URL ?>/admin/vp_academic_overview.php?tab=teachers" class="quick-item"><span class="qi-icon">👩‍🏫</span><div><strong>Teacher Supervision</strong><small>Workload &amp; submissions</small></div></a>
  <a href="<?= BASE_URL ?>/admin/vp_academic_overview.php?tab=atrisk"  class="quick-item"><span class="qi-icon">⚠️</span><div><strong>At-Risk Students</strong><small><?= $atRiskCount ?> identified</small></div></a>
  <a href="<?= BASE_URL ?>/admin/timetable.php"                       class="quick-item"><span class="qi-icon">⏰</span><div><strong>Timetable</strong><small>Teaching schedules</small></div></a>
  <a href="<?= BASE_URL ?>/admin/broadsheets.php"                     class="quick-item"><span class="qi-icon">📃</span><div><strong>Broadsheets</strong><small>Class performance</small></div></a>
</div>

<!-- Examination & Assessment -->
<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:7px">📝 Examination &amp; Assessment</div>
<div class="quick-grid" style="margin-bottom:16px">
  <a href="<?= BASE_URL ?>/admin/marks_approval.php"                  class="quick-item"><span class="qi-icon">🔍</span><div><strong>Review Submissions</strong><small>Verify &amp; approve</small></div></a>
  <a href="<?= BASE_URL ?>/admin/results.php"                         class="quick-item"><span class="qi-icon">📊</span><div><strong>Results</strong><small>View all results</small></div></a>
  <a href="<?= BASE_URL ?>/admin/report_cards.php"                    class="quick-item"><span class="qi-icon">📑</span><div><strong>Report Cards</strong><small><?= $publishedRC ?> published</small></div></a>
</div>

<!-- Student Affairs & Attendance -->
<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:7px">👨‍🎓 Student Affairs &amp; Attendance</div>
<div class="quick-grid" style="margin-bottom:16px">
  <a href="<?= BASE_URL ?>/admin/students.php"                        class="quick-item"><span class="qi-icon">🎓</span><div><strong>Students</strong><small><?= number_format($totalStudents) ?> active</small></div></a>
  <a href="<?= BASE_URL ?>/admin/attendance.php"                      class="quick-item"><span class="qi-icon">📆</span><div><strong>Student Attendance</strong><small><?= $attYearRate ?>% year rate</small></div></a>
  <a href="<?= BASE_URL ?>/admin/staff_attendance.php"                class="quick-item"><span class="qi-icon">👩‍🏫</span><div><strong>Teacher Attendance</strong><small><?= $staffAttToday !== null ? "$staffAttToday recorded today" : 'Monitor' ?></small></div></a>
  <a href="<?= BASE_URL ?>/admin/promotion.php"                       class="quick-item"><span class="qi-icon">⬆️</span><div><strong>Promotion</strong><small>Review candidates</small></div></a>
  <a href="<?= BASE_URL ?>/admin/discipline.php"                      class="quick-item"><span class="qi-icon">⚖️</span><div><strong>Discipline</strong><small><?= $openCases ?> open case<?= $openCases!==1?'s':'' ?></small></div></a>
  <a href="<?= BASE_URL ?>/admin/documents.php"                       class="quick-item"><span class="qi-icon">📄</span><div><strong>Documents</strong><small>Student files</small></div></a>
</div>

<!-- Reports & Communications -->
<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:7px">📊 Reports &amp; Communications</div>
<div class="quick-grid" style="margin-bottom:24px">
  <a href="<?= BASE_URL ?>/admin/reports.php?tab=academic"            class="quick-item"><span class="qi-icon">📈</span><div><strong>Academic Reports</strong><small>Performance exports</small></div></a>
  <a href="<?= BASE_URL ?>/admin/reports.php?tab=attendance_rep"      class="quick-item"><span class="qi-icon">📆</span><div><strong>Attendance Reports</strong><small>Trends &amp; absentees</small></div></a>
  <a href="<?= BASE_URL ?>/admin/reports.php?tab=staff"               class="quick-item"><span class="qi-icon">👥</span><div><strong>Teacher Reports</strong><small>Staff data</small></div></a>
  <a href="<?= BASE_URL ?>/admin/reports.php?tab=discipline_rep"      class="quick-item"><span class="qi-icon">⚖️</span><div><strong>Discipline Reports</strong><small>Incidents &amp; trends</small></div></a>
  <a href="<?= BASE_URL ?>/admin/announcements.php"                   class="quick-item"><span class="qi-icon">📢</span><div><strong>Announcements</strong><small>School notices</small></div></a>
</div>

<!-- ═══════ GRADE PERFORMANCE SNAPSHOT ═══════ -->
<?php if (!empty($gradeSnap)): ?>
<div class="panel" style="padding:22px;margin-bottom:20px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px">
    <h3 style="font-weight:700;font-size:14px">📚 Grade Performance — <?= e($ay) ?></h3>
    <a href="<?= BASE_URL ?>/admin/vp_academic_overview.php" class="filter-button">Full Analysis →</a>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px">
    <?php foreach ($gradeSnap as $g):
      $col = pColor((float)($g['avg_pct'] ?? 0));
    ?>
    <div style="padding:12px;background:var(--bg2);border-radius:var(--radius-sm);border-left:3px solid <?= $col ?>;text-align:center">
      <div style="font-size:11px;font-weight:700;color:var(--ink-soft);margin-bottom:4px"><?= e($g['name']) ?></div>
      <div style="font-size:1.3rem;font-weight:800;color:<?= $col ?>;line-height:1"><?= $g['avg_pct'] ? $g['avg_pct'].'%' : '—' ?></div>
      <div style="font-size:10px;color:var(--ink-soft);margin-top:3px"><?= $g['students'] ?> students</div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ═══════ TWO-COLUMN: DISCIPLINE + ATTENDANCE ═══════ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px" class="vp-dash-grid">

  <!-- Recent Discipline -->
  <div class="panel activity-panel">
    <div class="panel-heading">
      <div><h3>Recent Discipline</h3><p>Latest incidents — <?= e($ay) ?></p></div>
      <a href="<?= BASE_URL ?>/admin/discipline.php" class="filter-button">All →</a>
    </div>
    <?php if (empty($recentDisc)): ?>
    <p style="color:var(--ink-faint);font-size:13px;padding:12px">No discipline incidents this year.</p>
    <?php else: foreach ($recentDisc as $d): ?>
    <div class="activity">
      <span class="activity-dot <?= $d['resolved'] ? 'green' : 'pink' ?>"></span>
      <div>
        <strong><?= e($d['sname']) ?></strong>
        <p><?= e($d['category']) ?> · <?= e($d['grade_name'] ?? '—') ?></p>
        <small><?= date('M d, Y', strtotime($d['incident_date'])) ?> · <?= $d['resolved'] ? '<span style="color:var(--green)">Resolved</span>' : '<span style="color:var(--error)">Open</span>' ?></small>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Attendance by Grade -->
  <div class="panel" style="padding:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <h3 style="font-weight:700;font-size:14px">📆 Attendance by Grade</h3>
      <a href="<?= BASE_URL ?>/admin/attendance.php" class="filter-button">Manage →</a>
    </div>
    <?php if (empty($attByGrade)): ?>
    <p style="color:var(--ink-faint);font-size:13px">No attendance data yet.</p>
    <?php else: foreach ($attByGrade as $a):
      $rc = $a['rate'] >= 90 ? 'var(--green)' : ($a['rate'] >= 75 ? 'var(--warning)' : 'var(--error)');
    ?>
    <div style="margin-bottom:8px">
      <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:3px">
        <span><?= e($a['grade_name']) ?></span><strong style="color:<?= $rc ?>"><?= $a['rate'] ?>%</strong>
      </div>
      <div style="height:6px;background:var(--bg2);border-radius:3px;overflow:hidden">
        <div style="width:<?= min(100,$a['rate']) ?>%;height:100%;background:<?= $rc ?>;border-radius:3px"></div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

</div>

<!-- ═══════ TEACHER SUBMISSION STATUS ═══════ -->
<?php if (!empty($teacherSubmit)): ?>
<div class="panel" style="padding:22px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
    <h3 style="font-weight:700;font-size:14px">👩‍🏫 Teacher Marks Submission — <?= e($ay) ?></h3>
    <a href="<?= BASE_URL ?>/admin/vp_academic_overview.php?tab=teachers" class="filter-button">Full View →</a>
  </div>
  <div class="table-wrap" style="border:none">
    <table>
      <thead><tr><th>Teacher</th><th style="text-align:center;color:var(--green)">Submitted</th><th style="text-align:center;color:var(--warning)">Draft</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($teacherSubmit as $t):
          $hasIssue = $t['drafts'] > 0;
        ?>
        <tr>
          <td><strong><?= e($t['tname']) ?></strong></td>
          <td style="text-align:center"><strong style="color:var(--green)"><?= $t['submitted'] ?></strong></td>
          <td style="text-align:center"><?= $t['drafts'] > 0 ? '<strong style="color:var(--warning)">'.$t['drafts'].'</strong>' : '<span class="muted">—</span>' ?></td>
          <td><span class="status <?= $hasIssue ? 'pending' : ($t['submitted'] > 0 ? 'approved' : 'new-s') ?>"><?= $hasIssue ? 'Has drafts' : ($t['submitted'] > 0 ? 'Submitted' : 'No data') ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<style>@media(max-width:640px){.vp-dash-grid{grid-template-columns:1fr !important}}</style>
<?php require_once dirname(dirname(__DIR__)).'/includes/admin_footer.php'; ?>
