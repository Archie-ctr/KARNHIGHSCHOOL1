<?php
$pageTitle='Registrar Dashboard'; $activeAdmin='dashboard';
require_once dirname(dirname(__DIR__)).'/includes/admin_header.php';
requireRole(['registrar','principal','vice_principal','sys_admin','super_admin','school_admin']);

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();
$fn   = explode(' ', currentUser()['name'] ?? 'Registrar')[0];
$hour = (int)date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

// ── Admissions metrics ────────────────────────────────────────
$newApps      = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status='Application Submitted'")->fetchColumn();
$underReview  = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status='Under Review'")->fetchColumn();
$pendingDocs  = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status='Documents needed'")->fetchColumn();
$totalApps    = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE academic_year_id=$ayId")->fetchColumn();
$admitted     = (int)$pdo->query("SELECT COUNT(*) FROM applications WHERE status='Admitted' AND academic_year_id=$ayId")->fetchColumn();

// ── Student records metrics ───────────────────────────────────
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status='Active' AND academic_year_id=$ayId")->fetchColumn();
$newStudents   = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE academic_year_id=$ayId AND YEAR(admission_date)=YEAR(CURDATE())")->fetchColumn();
$totalGuardians= (int)$pdo->query("SELECT COUNT(*) FROM guardians")->fetchColumn();

// ── Movement pending ──────────────────────────────────────────
try {
    $pendingMovements = (int)$pdo->query("SELECT COUNT(*) FROM student_movements WHERE status='pending' AND academic_year_id=$ayId")->fetchColumn();
} catch (Throwable $e) { $pendingMovements = 0; }

// ── Graduation ────────────────────────────────────────────────
try {
    $pendingGrad  = (int)$pdo->query("SELECT COUNT(*) FROM graduation_records WHERE status='eligible' AND academic_year_id=$ayId")->fetchColumn();
    $graduatedCnt = (int)$pdo->query("SELECT COUNT(*) FROM graduation_records WHERE status IN ('approved','graduated') AND academic_year_id=$ayId")->fetchColumn();
} catch (Throwable $e) { $pendingGrad = 0; $graduatedCnt = 0; }

// ── Promotion ─────────────────────────────────────────────────
try {
    $promotedCnt  = (int)$pdo->query("SELECT COUNT(*) FROM promotion_records WHERE status='Promoted' AND academic_year_id=$ayId")->fetchColumn();
    $repeatingCnt = (int)$pdo->query("SELECT COUNT(*) FROM promotion_records WHERE status='Repeating' AND academic_year_id=$ayId")->fetchColumn();
} catch (Throwable $e) { $promotedCnt = 0; $repeatingCnt = 0; }

// ── Recent applications ───────────────────────────────────────
$recentApps = $pdo->query(
    "SELECT application_number,first_name,last_name,grade_applying_for,status,created_at
     FROM applications ORDER BY created_at DESC LIMIT 6"
)->fetchAll();

// ── Enrollment by grade ───────────────────────────────────────
$byGrade = $pdo->prepare(
    "SELECT g.name grade_name, COUNT(s.id) cnt
     FROM students s JOIN grades g ON g.id=s.current_grade_id
     WHERE s.academic_year_id=? AND s.status='Active'
     GROUP BY g.id,g.name,g.sequence ORDER BY g.sequence"
);
$byGrade->execute([$ayId]); $byGrade = $byGrade->fetchAll();
$maxGrade = max(array_column($byGrade,'cnt') ?: [1]);

// ── Recent student registrations ─────────────────────────────
$recentStudents = $pdo->prepare(
    "SELECT s.student_id, s.first_name, s.last_name, g.name grade_name, s.admission_date, s.status
     FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id
     WHERE s.academic_year_id=? ORDER BY s.id DESC LIMIT 6"
);
$recentStudents->execute([$ayId]);
$recentStudents = $recentStudents->fetchAll();

$needsAttention = $newApps + $underReview + $pendingDocs + $pendingMovements;
?>

<div class="page-heading">
  <div>
    <div class="eyebrow"><?= date('l, F d, Y') ?> <span></span></div>
    <h1><?= $greet ?>, <?= e($fn) ?>.</h1>
    <p>Registrar — <?= e($ay) ?></p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="<?= BASE_URL ?>/admin/registrar_reports.php" class="button button-secondary">📊 Registrar Reports</a>
    <a href="<?= BASE_URL ?>/admin/applications.php" class="button button-primary">
      📋 Applications
      <?php if ($newApps > 0): ?>
      <span style="background:rgba(255,255,255,.25);border-radius:20px;padding:1px 7px;font-size:11px;margin-left:4px"><?= $newApps ?> new</span>
      <?php endif; ?>
    </a>
  </div>
</div>

<!-- Attention banner -->
<?php if ($needsAttention > 0): ?>
<div style="background:linear-gradient(135deg,#701422,#3e0c19);color:#fff;border-radius:var(--radius);padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
  <span style="font-size:1.3rem">🔴</span>
  <span style="font-size:13px;font-weight:600;flex:1">
    <strong><?= $needsAttention ?> item<?= $needsAttention!==1?'s':'' ?></strong> require your attention today
  </span>
  <?php foreach ([
    [$newApps,     'New applications',  BASE_URL.'/admin/applications.php'],
    [$underReview, 'Under review',      BASE_URL.'/admin/applications.php?status=Under+Review'],
    [$pendingDocs, 'Docs needed',       BASE_URL.'/admin/applications.php?status=Documents+needed'],
    [$pendingMovements, 'Movements pending', BASE_URL.'/admin/student_transfers.php'],
  ] as [$cnt,$label,$href]):
    if (!$cnt) continue; ?>
  <a href="<?= e($href) ?>"
     style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);border-radius:var(--radius-sm);padding:8px 14px;text-decoration:none;color:#fff;font-size:12.5px;white-space:nowrap">
    <strong><?= $cnt ?></strong> <?= $label ?>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ═══════ KPI METRICS ═══════ -->
<div class="metric-grid" style="margin-bottom:24px">
  <div class="metric-card <?= ($newApps+$underReview)>0?'finance-metrics':'' ?>">
    <div class="metric-top"><span>Active Applications</span><div class="metric-icon">📋</div></div>
    <strong><?= $newApps + $underReview ?></strong><small><i></i><?= $newApps ?> new · <?= $underReview ?> reviewing</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Active Students</span><div class="metric-icon">🎓</div></div>
    <strong><?= number_format($totalStudents) ?></strong><small><i></i><?= $newStudents ?> enrolled this year</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Admitted</span><div class="metric-icon">✅</div></div>
    <strong style="color:var(--green)"><?= $admitted ?></strong><small><i></i>This year</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Guardians</span><div class="metric-icon">👨‍👩‍👧</div></div>
    <strong><?= number_format($totalGuardians) ?></strong><small><i></i>On record</small>
  </div>
  <div class="metric-card <?= $pendingGrad>0?'finance-metrics':'' ?>">
    <div class="metric-top"><span>Graduation Eligible</span><div class="metric-icon">🎓</div></div>
    <strong><?= $pendingGrad ?></strong><small><i></i><?= $graduatedCnt ?> approved/graduated</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Promoted</span><div class="metric-icon">⬆️</div></div>
    <strong><?= $promotedCnt ?></strong><small><i></i><?= $repeatingCnt ?> repeating</small>
  </div>
</div>

<!-- ═══════ QUICK ACCESS ═══════ -->
<h3 style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-soft);margin-bottom:10px">Quick Access</h3>

<!-- Admissions -->
<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:7px">📝 Admissions</div>
<div class="quick-grid" style="margin-bottom:16px">
  <a href="<?= BASE_URL ?>/admin/applications.php"               class="quick-item"><span class="qi-icon">📋</span><div><strong>Applications</strong><small><?= $totalApps ?> this year</small></div></a>
  <a href="<?= BASE_URL ?>/admin/entrance_exams.php"             class="quick-item"><span class="qi-icon">📝</span><div><strong>Entrance Exams</strong><small>Schedule &amp; manage</small></div></a>
  <a href="<?= BASE_URL ?>/admin/admission_decisions.php"        class="quick-item"><span class="qi-icon">✔</span><div><strong>Admission Decisions</strong><small>Process results</small></div></a>
  <a href="<?= BASE_URL ?>/admin/documents.php"                  class="quick-item"><span class="qi-icon">📄</span><div><strong>Document Verification</strong><small>Verify uploads</small></div></a>
</div>

<!-- Student Records -->
<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:7px">👨‍🎓 Student Records</div>
<div class="quick-grid" style="margin-bottom:16px">
  <a href="<?= BASE_URL ?>/admin/students.php"                   class="quick-item"><span class="qi-icon">🎓</span><div><strong>Student Directory</strong><small><?= number_format($totalStudents) ?> active</small></div></a>
  <a href="<?= BASE_URL ?>/admin/guardians.php"                  class="quick-item"><span class="qi-icon">👨‍👩‍👧</span><div><strong>Parents &amp; Guardians</strong><small><?= number_format($totalGuardians) ?> records</small></div></a>
  <a href="<?= BASE_URL ?>/admin/student_idcards.php"            class="quick-item"><span class="qi-icon">🪪</span><div><strong>Student ID Cards</strong><small>Print IDs</small></div></a>
  <a href="<?= BASE_URL ?>/admin/registrar_records.php"          class="quick-item"><span class="qi-icon">📊</span><div><strong>Academic Records</strong><small>History &amp; transcripts</small></div></a>
</div>

<!-- Movements & Progression -->
<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:7px">🔄 Movements &amp; Progression</div>
<div class="quick-grid" style="margin-bottom:16px">
  <a href="<?= BASE_URL ?>/admin/student_transfers.php"          class="quick-item"><span class="qi-icon">➡️</span><div><strong>Transfers &amp; Withdrawals</strong><small><?= $pendingMovements ?> pending</small></div></a>
  <a href="<?= BASE_URL ?>/admin/promotion.php"                  class="quick-item"><span class="qi-icon">⬆️</span><div><strong>Promotion Records</strong><small><?= $promotedCnt ?> promoted</small></div></a>
  <a href="<?= BASE_URL ?>/admin/graduation.php"                 class="quick-item"><span class="qi-icon">🎓</span><div><strong>Graduation</strong><small><?= $pendingGrad ?> eligible</small></div></a>
  <a href="<?= BASE_URL ?>/admin/report_cards.php"               class="quick-item"><span class="qi-icon">📑</span><div><strong>Report Cards</strong><small>Transcripts &amp; records</small></div></a>
</div>

<!-- Reports -->
<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:7px">📊 Reports &amp; Documents</div>
<div class="quick-grid" style="margin-bottom:24px">
  <a href="<?= BASE_URL ?>/admin/registrar_reports.php?tab=enrollment"   class="quick-item"><span class="qi-icon">📈</span><div><strong>Enrollment Report</strong><small>By grade &amp; status</small></div></a>
  <a href="<?= BASE_URL ?>/admin/registrar_reports.php?tab=demographics" class="quick-item"><span class="qi-icon">👫</span><div><strong>Demographics</strong><small>Gender &amp; county</small></div></a>
  <a href="<?= BASE_URL ?>/admin/registrar_reports.php?tab=admissions"   class="quick-item"><span class="qi-icon">📋</span><div><strong>Admission History</strong><small>Year-over-year</small></div></a>
  <a href="<?= BASE_URL ?>/admin/registrar_reports.php?tab=graduation"   class="quick-item"><span class="qi-icon">🎓</span><div><strong>Graduation Report</strong><small>Certificates &amp; list</small></div></a>
</div>

<!-- ═══════ TWO-COLUMN: ENROLLMENT CHART + RECENT APPS ═══════ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px" class="reg-dash-grid">

  <!-- Enrollment by grade -->
  <div class="panel" style="padding:22px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <h3 style="font-weight:700;font-size:14px">🎓 Active Students by Grade</h3>
      <a href="<?= BASE_URL ?>/admin/registrar_reports.php?tab=enrollment" class="filter-button">Details →</a>
    </div>
    <?php foreach ($byGrade as $g):
      $w = round($g['cnt'] / $maxGrade * 100);
    ?>
    <div style="margin-bottom:8px">
      <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:2px">
        <span><?= e($g['grade_name']) ?></span><strong><?= $g['cnt'] ?></strong>
      </div>
      <div style="height:7px;background:var(--bg2);border-radius:4px;overflow:hidden">
        <div style="width:<?= $w ?>%;height:100%;background:var(--primary);border-radius:4px"></div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($byGrade)):?><p style="color:var(--ink-faint);font-size:13px">No enrollment data.</p><?php endif; ?>
  </div>

  <!-- Recent applications -->
  <div class="panel activity-panel">
    <div class="panel-heading">
      <div><h3>Recent Applications</h3></div>
      <a href="<?= BASE_URL ?>/admin/applications.php" class="filter-button">All →</a>
    </div>
    <?php if (empty($recentApps)): ?>
    <p style="color:var(--ink-faint);font-size:13px;padding:12px">No applications yet.</p>
    <?php else: foreach ($recentApps as $a):
      $ini = strtoupper(substr($a['first_name'],0,1).substr($a['last_name'],0,1));
    ?>
    <div class="activity">
      <span class="activity-dot <?= $a['status']==='Admitted'?'green':($a['status']==='Rejected'?'pink':'blue') ?>"></span>
      <div>
        <strong><?= e($a['first_name'].' '.$a['last_name']) ?></strong>
        <p><?= e($a['application_number']) ?> — <?= e($a['grade_applying_for']) ?></p>
        <small><?= date('M d, Y', strtotime($a['created_at'])) ?> · <?= statusBadge($a['status']) ?></small>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

</div>

<!-- Recent student registrations -->
<?php if (!empty($recentStudents)): ?>
<div class="panel" style="padding:22px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
    <h3 style="font-weight:700;font-size:14px">🆕 Recent Student Registrations</h3>
    <a href="<?= BASE_URL ?>/admin/students.php" class="filter-button">All Students →</a>
  </div>
  <div class="table-wrap" style="border:none">
    <table>
      <thead><tr><th>Student ID</th><th>Name</th><th>Grade</th><th>Admitted</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($recentStudents as $s): ?>
        <tr>
          <td class="muted"><?= e($s['student_id']) ?></td>
          <td><strong><?= e($s['first_name'].' '.$s['last_name']) ?></strong></td>
          <td class="muted"><?= e($s['grade_name'] ?? '—') ?></td>
          <td class="muted"><?= $s['admission_date'] ? date('M d, Y', strtotime($s['admission_date'])) : '—' ?></td>
          <td><span class="status <?= $s['status']==='Active'?'approved':'warning' ?>"><?= e($s['status']) ?></span></td>
          <td>
            <a href="<?= BASE_URL ?>/admin/registrar_records.php?student_id=<?= 0 ?>&tab=history&q=<?= urlencode($s['student_id']) ?>" class="filter-button button-sm">📊 Records</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<style>@media(max-width:640px){.reg-dash-grid{grid-template-columns:1fr !important}}</style>
<?php require_once dirname(dirname(__DIR__)).'/includes/admin_footer.php'; ?>
