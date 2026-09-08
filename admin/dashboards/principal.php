<?php
$pageTitle='Principal Dashboard'; $activeAdmin='dashboard';
require_once dirname(dirname(__DIR__)).'/includes/admin_header.php';
requireRole(['principal','super_admin','sys_admin','school_admin']);

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();
$approvals = countPendingApprovals();
$totalPending = $approvals['_total'] ?? 0;
$today = date('l, F d, Y');
$fn   = explode(' ', currentUser()['name'] ?? 'Principal')[0];
$hour = (int)date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

// ── Section 1: Executive KPIs ─────────────────────────────────
$totalStudents  = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status='Active' AND academic_year_id=$ayId")->fetchColumn();
$totalTeachers  = (int)$pdo->query("SELECT COUNT(*) FROM teachers WHERE status='Active'")->fetchColumn();
$totalStaff     = (int)$pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE u.is_active=1 AND r.name NOT IN ('student','parent','applicant')")->fetchColumn();
$totalClasses   = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE academic_year_id=$ayId")->fetchColumn();
$totalGuardians = (int)$pdo->query("SELECT COUNT(*) FROM guardians")->fetchColumn();

// Attendance today
$attToday = $pdo->query("SELECT SUM(status='Present') p, COUNT(*) t FROM attendance WHERE date=CURDATE()")->fetch();
$attRate  = ($attToday && $attToday['t'] > 0) ? round($attToday['p'] / $attToday['t'] * 100, 1) : null;

// Attendance year
try {
    $attYearRate = (float)$pdo->prepare(
        "SELECT ROUND(SUM(status='Present')/COUNT(*)*100,1) FROM attendance WHERE academic_year_id=?"
    )->execute([$ayId]) ? $pdo->query("SELECT ROUND(SUM(status='Present')/COUNT(*)*100,1) FROM attendance WHERE academic_year_id=$ayId")->fetchColumn() : null;
} catch (Throwable $e) { $attYearRate = null; }

// Academic performance
try {
    $overallAvg = (float)$pdo->query(
        "SELECT ROUND(AVG(marks_obtained/max_marks*100),1)
         FROM assessment_scores WHERE academic_year_id=$ayId AND max_marks>0 AND status IN ('approved','published')"
    )->fetchColumn();
} catch (Throwable $e) { $overallAvg = 0; }

// ── Section 2: Approval Center ───────────────────────────────
$pendingMarks      = (int)$pdo->query("SELECT COUNT(DISTINCT class_id,subject_id,assessment_config_id) FROM assessment_scores WHERE status IN ('submitted','resubmitted') AND academic_year_id=$ayId")->fetchColumn();
$pendingApps       = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status IN ('Application Submitted','Under Review')")->fetchColumn();
$openDiscipline    = (int)$pdo->query("SELECT COUNT(*) FROM discipline_records WHERE resolved=0 AND academic_year_id=$ayId")->fetchColumn();
$publishedRC       = (int)$pdo->query("SELECT COUNT(*) FROM report_cards WHERE status='published' AND academic_year_id=$ayId")->fetchColumn();

// Expense approvals pending
try {
    $pendingExpenses = (int)$pdo->query("SELECT COUNT(*) FROM expense_requests WHERE status='pending' AND academic_year_id=$ayId")->fetchColumn();
    $pendingWaivers  = (int)$pdo->query("SELECT COUNT(*) FROM fee_waivers WHERE status='pending' AND academic_year_id=$ayId")->fetchColumn();
} catch (Throwable $e) { $pendingExpenses = 0; $pendingWaivers = 0; }

// Pending movements (transfers/withdrawals)
try {
    $pendingMovements = (int)$pdo->query("SELECT COUNT(*) FROM student_movements WHERE status='pending' AND academic_year_id=$ayId")->fetchColumn();
} catch (Throwable $e) { $pendingMovements = 0; }

// Graduation pending
try {
    $pendingGrad = (int)$pdo->query("SELECT COUNT(*) FROM graduation_records WHERE status='eligible' AND academic_year_id=$ayId")->fetchColumn();
} catch (Throwable $e) { $pendingGrad = 0; }

// ── Section 3: Finance ───────────────────────────────────────
$feesLRD   = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='LRD' AND academic_year_id=$ayId")->fetchColumn();
$feesUSD   = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='USD' AND academic_year_id=$ayId")->fetchColumn();
$paidCount = (int)$pdo->query("SELECT COUNT(DISTINCT student_id) FROM payments WHERE academic_year_id=$ayId")->fetchColumn();
$unpaid    = max(0, $totalStudents - $paidCount);

// ── Section 4: Academic monitoring ──────────────────────────
$gradePerf = $pdo->prepare(
    "SELECT g.name, g.sequence,
            ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1) avg_pct,
            COUNT(DISTINCT s.id) students
     FROM students s
     LEFT JOIN assessment_scores asc2 ON asc2.student_id=s.id
       AND asc2.academic_year_id=? AND asc2.max_marks>0 AND asc2.status IN ('approved','published')
     JOIN grades g ON g.id=s.current_grade_id
     WHERE s.academic_year_id=? AND s.status='Active'
     GROUP BY g.id, g.name, g.sequence ORDER BY g.sequence"
);
$gradePerf->execute([$ayId, $ayId]);
$gradePerf = $gradePerf->fetchAll();

// ── Section 5: Recent activity feeds ────────────────────────
$recentApps = $pdo->query(
    "SELECT application_number,first_name,last_name,grade_applying_for,status,created_at
     FROM applications ORDER BY created_at DESC LIMIT 5"
)->fetchAll();

$recentDiscipline = $pdo->prepare(
    "SELECT d.*,CONCAT(s.first_name,' ',s.last_name) sname FROM discipline_records d
     JOIN students s ON s.id=d.student_id WHERE d.academic_year_id=? ORDER BY d.incident_date DESC LIMIT 5"
);
$recentDiscipline->execute([$ayId]);
$recentDiscipline = $recentDiscipline->fetchAll();

$recentPayments = $pdo->prepare(
    "SELECT p.receipt_number,p.amount,p.currency,p.payment_date,
            CONCAT(s.first_name,' ',s.last_name) sname
     FROM payments p JOIN students s ON s.id=p.student_id
     WHERE p.academic_year_id=? ORDER BY p.created_at DESC LIMIT 5"
);
$recentPayments->execute([$ayId]);
$recentPayments = $recentPayments->fetchAll();

// ── Section 6: Promotion/graduation ─────────────────────────
try {
    $promotionStats = $pdo->prepare(
        "SELECT status, COUNT(*) cnt FROM promotion_records WHERE academic_year_id=? GROUP BY status"
    );
    $promotionStats->execute([$ayId]);
    $promotionStats = $promotionStats->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Throwable $e) { $promotionStats = []; }

try {
    $gradStats = $pdo->prepare(
        "SELECT status, COUNT(*) cnt FROM graduation_records WHERE academic_year_id=? GROUP BY status"
    );
    $gradStats->execute([$ayId]);
    $gradStats = $gradStats->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Throwable $e) { $gradStats = []; }

$totalApprovalItems = $totalPending + $pendingExpenses + $pendingWaivers + $pendingMovements + $pendingGrad;
?>

<div class="page-heading">
  <div>
    <div class="eyebrow"><?= e($today) ?> <span></span></div>
    <h1><?= $greet ?>, <?= e($fn) ?>.</h1>
    <p>Principal — <?= e($ay) ?></p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <a href="<?= BASE_URL ?>/admin/executive_reports.php" class="button button-secondary">📊 Executive Reports</a>
    <a href="<?= BASE_URL ?>/admin/approval_center.php" class="button button-primary" style="position:relative">
      ✅ Approval Center
      <?php if ($totalApprovalItems > 0): ?>
      <span style="position:absolute;top:-8px;right:-8px;background:var(--error);color:#fff;font-size:11px;font-weight:800;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;line-height:1"><?= $totalApprovalItems ?></span>
      <?php endif; ?>
    </a>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
  APPROVAL ALERT BANNER — shown when anything needs action
═══════════════════════════════════════════════════════ -->
<?php if ($totalApprovalItems > 0): ?>
<div style="background:linear-gradient(135deg,#701422 0%,#3e0c19 100%);color:#fff;border-radius:var(--radius);padding:20px 24px;margin-bottom:24px">
  <div style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;opacity:.65;margin-bottom:12px">🔴 Requires Your Attention</div>
  <div style="display:flex;gap:12px;flex-wrap:wrap">
    <?php
    $actionItems = [];
    if ($pendingMarks)     $actionItems[] = ['✏️','Marks',       $pendingMarks,    BASE_URL.'/admin/marks_approval.php'];
    if ($pendingApps)      $actionItems[] = ['📋','Admissions',  $pendingApps,     BASE_URL.'/admin/applications.php'];
    if ($openDiscipline)   $actionItems[] = ['⚖️','Discipline',  $openDiscipline,  BASE_URL.'/admin/discipline.php'];
    if (($approvals['promotion']??0)>0) $actionItems[] = ['⬆️','Promotion', $approvals['promotion'], BASE_URL.'/admin/promotion.php'];
    if ($pendingExpenses)  $actionItems[] = ['💰','Expenses',    $pendingExpenses, BASE_URL.'/admin/expenses.php'];
    if ($pendingWaivers)   $actionItems[] = ['💳','Waivers',     $pendingWaivers,  BASE_URL.'/admin/expenses.php?tab=waivers'];
    if ($pendingMovements) $actionItems[] = ['➡️','Transfers',   $pendingMovements,BASE_URL.'/admin/student_transfers.php'];
    if ($pendingGrad)      $actionItems[] = ['🎓','Graduation',  $pendingGrad,     BASE_URL.'/admin/graduation.php'];
    foreach ($actionItems as [$ico,$label,$cnt,$href]): ?>
    <a href="<?= e($href) ?>"
       style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);border-radius:var(--radius-sm);padding:10px 16px;text-decoration:none;color:#fff;display:flex;align-items:center;gap:10px;transition:background .15s;min-width:120px"
       onmouseover="this.style.background='rgba(255,255,255,.22)'" onmouseout="this.style.background='rgba(255,255,255,.12)'">
      <span style="font-size:1.2rem"><?= $ico ?></span>
      <div>
        <div style="font-size:11px;font-weight:600;opacity:.7"><?= $label ?></div>
        <div style="font-size:1.3rem;font-weight:800;line-height:1"><?= $cnt ?></div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════
  SECTION 1: KEY METRICS (9 cards)
═══════════════════════════════════════════════════════ -->
<div class="metric-grid" style="margin-bottom:24px">
  <div class="metric-card">
    <div class="metric-top"><span>Active Students</span><div class="metric-icon">🎓</div></div>
    <strong><?= number_format($totalStudents) ?></strong><small><i></i><?= e($ay) ?></small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Teaching Staff</span><div class="metric-icon">👩‍🏫</div></div>
    <strong><?= $totalTeachers ?></strong><small><i></i><?= $totalStaff ?> total staff</small>
  </div>
  <div class="metric-card <?= $attRate!==null&&$attRate<80?'finance-metrics':'' ?>">
    <div class="metric-top"><span>Attendance Today</span><div class="metric-icon">📆</div></div>
    <strong style="color:<?= $attRate!==null?($attRate>=80?'var(--green)':'var(--error)'):'inherit' ?>"><?= $attRate!==null?$attRate.'%':'—' ?></strong>
    <small><i></i><?= $attToday&&$attToday['t']>0?$attToday['p'].' present / '.$attToday['t']:'No data' ?></small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Academic Average</span><div class="metric-icon">📊</div></div>
    <strong style="color:<?= $overallAvg>=70?'var(--green)':($overallAvg>=50?'var(--warning)':'var(--error)') ?>"><?= $overallAvg?>%</strong>
    <small><i></i>Approved marks</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Fees Collected (LRD)</span><div class="metric-icon">💰</div></div>
    <strong>LRD <?= number_format($feesLRD/1000,1) ?>K</strong>
    <small><i></i><?= $paidCount ?> paid · <?= $unpaid ?> outstanding</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Report Cards</span><div class="metric-icon">📑</div></div>
    <strong><?= $publishedRC ?></strong><small><i></i>Published</small>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════
  SECTION 2: QUICK ACCESS (all principal sections)
═══════════════════════════════════════════════════════ -->
<h3 style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-soft);margin-bottom:10px">Quick Access</h3>

<!-- Approvals & Oversight -->
<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:7px">📋 Approvals & Oversight</div>
<div class="quick-grid" style="margin-bottom:18px">
  <a href="<?= BASE_URL ?>/admin/approval_center.php"  class="quick-item"><span class="qi-icon">✅</span><div><strong>Approval Center</strong><small><?= $totalApprovalItems ?> pending</small></div></a>
  <a href="<?= BASE_URL ?>/admin/marks_approval.php"   class="quick-item"><span class="qi-icon">✏️</span><div><strong>Marks Approval</strong><small><?= $pendingMarks ?> batch<?= $pendingMarks!==1?'es':'' ?></small></div></a>
  <a href="<?= BASE_URL ?>/admin/applications.php"     class="quick-item"><span class="qi-icon">📋</span><div><strong>Admissions</strong><small><?= $pendingApps ?> pending</small></div></a>
  <a href="<?= BASE_URL ?>/admin/expenses.php"         class="quick-item"><span class="qi-icon">💰</span><div><strong>Expenses & Waivers</strong><small><?= $pendingExpenses + $pendingWaivers ?> awaiting approval</small></div></a>
  <a href="<?= BASE_URL ?>/admin/discipline.php"       class="quick-item"><span class="qi-icon">⚖️</span><div><strong>Discipline</strong><small><?= $openDiscipline ?> open case<?= $openDiscipline!==1?'s':'' ?></small></div></a>
</div>

<!-- Student Management -->
<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:7px">👩‍🎓 Student Management</div>
<div class="quick-grid" style="margin-bottom:18px">
  <a href="<?= BASE_URL ?>/admin/students.php"             class="quick-item"><span class="qi-icon">🎓</span><div><strong>All Students</strong><small><?= number_format($totalStudents) ?> active</small></div></a>
  <a href="<?= BASE_URL ?>/admin/student_transfers.php"    class="quick-item"><span class="qi-icon">➡️</span><div><strong>Transfers & Withdrawals</strong><small><?= $pendingMovements ?> pending</small></div></a>
  <a href="<?= BASE_URL ?>/admin/promotion.php"            class="quick-item"><span class="qi-icon">⬆️</span><div><strong>Promotion</strong><small><?= ($promotionStats['Promoted']??0) > 0 ? ($promotionStats['Promoted']??0).' promoted' : 'Manage' ?></small></div></a>
  <a href="<?= BASE_URL ?>/admin/graduation.php"           class="quick-item"><span class="qi-icon">🎓</span><div><strong>Graduation</strong><small><?= $pendingGrad ?> eligible · <?= ($gradStats['graduated']??0) ?> graduated</small></div></a>
  <a href="<?= BASE_URL ?>/admin/guardians.php"            class="quick-item"><span class="qi-icon">👨‍👩‍👧</span><div><strong>Guardians</strong><small><?= number_format($totalGuardians) ?> records</small></div></a>
  <a href="<?= BASE_URL ?>/admin/student_statistics.php"   class="quick-item"><span class="qi-icon">📊</span><div><strong>Student Statistics</strong><small>Enrollment analytics</small></div></a>
</div>

<!-- Academic Management -->
<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:7px">📚 Academic Management</div>
<div class="quick-grid" style="margin-bottom:18px">
  <a href="<?= BASE_URL ?>/admin/executive_reports.php"    class="quick-item"><span class="qi-icon">📈</span><div><strong>Performance Overview</strong><small>Academic analytics</small></div></a>
  <a href="<?= BASE_URL ?>/admin/results.php"              class="quick-item"><span class="qi-icon">📊</span><div><strong>Examination Results</strong><small>View & verify</small></div></a>
  <a href="<?= BASE_URL ?>/admin/broadsheets.php"          class="quick-item"><span class="qi-icon">📃</span><div><strong>Broadsheets</strong><small>Class performance</small></div></a>
  <a href="<?= BASE_URL ?>/admin/report_cards.php"         class="quick-item"><span class="qi-icon">📑</span><div><strong>Report Cards</strong><small><?= $publishedRC ?> published</small></div></a>
  <a href="<?= BASE_URL ?>/admin/timetable.php"            class="quick-item"><span class="qi-icon">⏰</span><div><strong>Timetable</strong><small>Schedule oversight</small></div></a>
  <a href="<?= BASE_URL ?>/admin/attendance.php"           class="quick-item"><span class="qi-icon">📆</span><div><strong>Attendance</strong><small><?= $attYearRate!==null?$attYearRate.'% this year':'Monitor' ?></small></div></a>
</div>

<!-- Finance & Administration -->
<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:7px">💰 Finance & Administration</div>
<div class="quick-grid" style="margin-bottom:18px">
  <a href="<?= BASE_URL ?>/admin/finance.php"              class="quick-item"><span class="qi-icon">💵</span><div><strong>Finance Dashboard</strong><small>LRD <?= number_format($feesLRD/1000,1) ?>K collected</small></div></a>
  <a href="<?= BASE_URL ?>/admin/expenses.php"             class="quick-item"><span class="qi-icon">📋</span><div><strong>Expenses</strong><small>Requests & waivers</small></div></a>
  <a href="<?= BASE_URL ?>/admin/teachers_admin.php"       class="quick-item"><span class="qi-icon">👩‍🏫</span><div><strong>Teachers</strong><small><?= $totalTeachers ?> active</small></div></a>
  <a href="<?= BASE_URL ?>/admin/users.php"                class="quick-item"><span class="qi-icon">👥</span><div><strong>Staff Accounts</strong><small><?= $totalStaff ?> staff</small></div></a>
</div>

<!-- Official Communication & Reports -->
<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:7px">📢 Communication & Reports</div>
<div class="quick-grid" style="margin-bottom:24px">
  <a href="<?= BASE_URL ?>/admin/announcements.php"        class="quick-item"><span class="qi-icon">📢</span><div><strong>Announcements</strong><small>School-wide notices</small></div></a>
  <a href="<?= BASE_URL ?>/admin/events_admin.php"         class="quick-item"><span class="qi-icon">🎉</span><div><strong>School Events</strong><small>Calendar management</small></div></a>
  <a href="<?= BASE_URL ?>/admin/messages.php"             class="quick-item"><span class="qi-icon">💬</span><div><strong>Messages</strong><small>Communications</small></div></a>
  <a href="<?= BASE_URL ?>/admin/executive_reports.php"    class="quick-item"><span class="qi-icon">📊</span><div><strong>Executive Reports</strong><small>Full analytics</small></div></a>
  <a href="<?= BASE_URL ?>/admin/reports.php"              class="quick-item"><span class="qi-icon">📥</span><div><strong>Data Exports</strong><small>CSV reports</small></div></a>
</div>

<!-- ═══════════════════════════════════════════════════════
  SECTION 3: ACADEMIC PERFORMANCE SNAPSHOT
═══════════════════════════════════════════════════════ -->
<?php if (!empty($gradePerf)): ?>
<div class="panel" style="padding:22px;margin-bottom:20px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px">
    <h3 style="font-weight:700;font-size:14px">📚 Academic Performance by Grade — <?= e($ay) ?></h3>
    <a href="<?= BASE_URL ?>/admin/executive_reports.php" class="filter-button">Full Report →</a>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px">
    <?php foreach ($gradePerf as $g):
      $avgColor = !$g['avg_pct']?'var(--ink-faint)':($g['avg_pct']>=70?'var(--green)':($g['avg_pct']>=50?'var(--warning)':'var(--error)'));
    ?>
    <div style="padding:12px 14px;background:var(--bg2);border-radius:var(--radius-sm);border-left:3px solid <?= $avgColor ?>">
      <div style="font-size:12px;font-weight:700;color:var(--ink-soft);margin-bottom:4px"><?= e($g['name']) ?></div>
      <div style="font-size:1.4rem;font-weight:800;color:<?= $avgColor ?>;line-height:1"><?= $g['avg_pct'] ? $g['avg_pct'].'%' : '—' ?></div>
      <div style="font-size:11px;color:var(--ink-soft);margin-top:3px"><?= $g['students'] ?> students</div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════
  SECTION 4: THREE-COLUMN ACTIVITY PANELS
═══════════════════════════════════════════════════════ -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px" class="principal-panels">

  <!-- Recent Applications -->
  <div class="panel activity-panel">
    <div class="panel-heading">
      <div><h3>Applications</h3><p>Recent submissions</p></div>
      <a href="<?= BASE_URL ?>/admin/applications.php" class="filter-button">All →</a>
    </div>
    <?php if (empty($recentApps)): ?>
    <p style="color:var(--ink-faint);font-size:13px;padding:12px">No applications yet.</p>
    <?php else: foreach ($recentApps as $a): ?>
    <div class="activity">
      <span class="activity-dot pink"></span>
      <div>
        <strong><?= e($a['first_name'].' '.$a['last_name']) ?></strong>
        <p><?= e($a['application_number']) ?> — <?= e($a['grade_applying_for']) ?></p>
        <small><?= date('M d, Y', strtotime($a['created_at'])) ?> · <?= statusBadge($a['status']) ?></small>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Recent Discipline -->
  <div class="panel activity-panel">
    <div class="panel-heading">
      <div><h3>Discipline</h3><p>Recent incidents</p></div>
      <a href="<?= BASE_URL ?>/admin/discipline.php" class="filter-button">All →</a>
    </div>
    <?php if (empty($recentDiscipline)): ?>
    <p style="color:var(--ink-faint);font-size:13px;padding:12px">No incidents this year.</p>
    <?php else: foreach ($recentDiscipline as $d): ?>
    <div class="activity">
      <span class="activity-dot <?= $d['resolved']?'green':'pink' ?>"></span>
      <div>
        <strong><?= e($d['sname']) ?></strong>
        <p><?= e($d['category']) ?> — <?= e(mb_substr($d['action_taken'],0,30)) ?></p>
        <small><?= date('M d, Y', strtotime($d['incident_date'])) ?> · <?= $d['resolved']?'<span style="color:var(--green)">Resolved</span>':'<span style="color:var(--error)">Open</span>' ?></small>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Recent Payments -->
  <div class="panel activity-panel">
    <div class="panel-heading">
      <div><h3>Finance</h3><p>Recent payments</p></div>
      <a href="<?= BASE_URL ?>/admin/finance.php" class="filter-button">All →</a>
    </div>
    <?php if (empty($recentPayments)): ?>
    <p style="color:var(--ink-faint);font-size:13px;padding:12px">No payments yet.</p>
    <?php else: foreach ($recentPayments as $p): ?>
    <div class="activity">
      <span class="activity-dot blue"></span>
      <div>
        <strong><?= e($p['sname']) ?></strong>
        <p><?= e($p['currency']) ?> <?= number_format($p['amount'],2) ?> — <?= e($p['receipt_number']) ?></p>
        <small><?= date('M d, Y', strtotime($p['payment_date'])) ?></small>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

</div>

<!-- ═══════════════════════════════════════════════════════
  SECTION 5: PROMOTION & GRADUATION STATUS
═══════════════════════════════════════════════════════ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px" class="principal-panels">
  <div class="panel" style="padding:20px">
    <div style="display:flex;justify-content:space-between;margin-bottom:12px">
      <h3 style="font-weight:700;font-size:13px">⬆️ Promotion Status — <?= e($ay) ?></h3>
      <a href="<?= BASE_URL ?>/admin/promotion.php" class="filter-button">Manage →</a>
    </div>
    <?php if (empty($promotionStats)): ?>
    <p style="color:var(--ink-faint);font-size:13px">No promotion records yet.</p>
    <?php else: foreach ($promotionStats as $status => $cnt): ?>
    <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--line-soft);font-size:12.5px">
      <span><?= ucfirst($status) ?></span><strong><?= $cnt ?></strong>
    </div>
    <?php endforeach; endif; ?>
  </div>
  <div class="panel" style="padding:20px">
    <div style="display:flex;justify-content:space-between;margin-bottom:12px">
      <h3 style="font-weight:700;font-size:13px">🎓 Graduation Status — <?= e($ay) ?></h3>
      <a href="<?= BASE_URL ?>/admin/graduation.php" class="filter-button">Manage →</a>
    </div>
    <?php if (empty($gradStats)): ?>
    <p style="color:var(--ink-faint);font-size:13px">No graduation records. <a href="<?= BASE_URL ?>/admin/graduation.php" style="color:var(--primary)">Generate list →</a></p>
    <?php else:
      $statusColors = ['eligible'=>'new-s','approved'=>'approved','graduated'=>'approved','withheld'=>'warning'];
      foreach ($gradStats as $status => $cnt): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;border-bottom:1px solid var(--line-soft);font-size:12.5px">
      <span class="status <?= $statusColors[$status]??'new-s' ?>" style="font-size:11px"><?= ucfirst($status) ?></span>
      <strong><?= $cnt ?></strong>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<style>
@media(max-width:900px){.principal-panels{grid-template-columns:1fr 1fr !important}}
@media(max-width:580px){.principal-panels{grid-template-columns:1fr !important}}
</style>

<?php require_once dirname(dirname(__DIR__)).'/includes/admin_footer.php'; ?>
