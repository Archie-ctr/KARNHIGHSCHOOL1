<?php
$pageTitle   = 'Staff Attendance';
$activeAdmin = 'staff_attendance';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['sys_admin','super_admin','school_admin','principal']);

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();
$today = date('Y-m-d');

// ── Ensure table exists ───────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS staff_attendance (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id     INT UNSIGNED NOT NULL,
        date        DATE         NOT NULL,
        status      ENUM('Present','Absent','Late','Half-day','Leave') NOT NULL DEFAULT 'Present',
        remarks     VARCHAR(255) NULL,
        recorded_by INT UNSIGNED NULL,
        created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_staff_date (user_id, date),
        INDEX idx_sa_date (date),
        INDEX idx_sa_ay   (date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

// ── POST: record / bulk attendance ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'record_bulk') {
        $date       = $_POST['date'] ?? $today;
        $statuses   = $_POST['status'] ?? [];     // [user_id => status]
        $remarks    = $_POST['remarks'] ?? [];
        $recordedBy = currentUserId();
        foreach ($statuses as $uid => $status) {
            $uid = (int)$uid;
            if (!$uid) continue;
            $allowed = ['Present','Absent','Late','Half-day','Leave'];
            if (!in_array($status, $allowed, true)) $status = 'Present';
            $rem = trim($remarks[$uid] ?? '') ?: null;
            $pdo->prepare(
                "INSERT INTO staff_attendance (user_id,date,status,remarks,recorded_by)
                 VALUES (?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE status=VALUES(status),remarks=VALUES(remarks),recorded_by=VALUES(recorded_by)"
            )->execute([$uid, $date, $status, $rem, $recordedBy]);
        }
        auditLog('create','staff_attendance','attendance',0,'','Bulk staff attendance for '.$date);
        flash('success','Staff attendance recorded for '.$date.'.');
    }
    redirect(BASE_URL.'/admin/staff_attendance.php?date='.urlencode($_POST['date']??$today));
}

// ── Filters ───────────────────────────────────────────────────
$dateF  = $_GET['date']   ?? $today;
$monthF = substr($dateF, 0, 7);          // YYYY-MM
$tab    = $_GET['tab']    ?? 'daily';
$q      = trim($_GET['q'] ?? '');

// All active staff (non-student/parent/applicant roles)
$staffQuery = "SELECT u.id, u.name, u.email, r.label role_label
               FROM users u
               JOIN roles r ON r.id=u.role_id
               WHERE u.is_active=1
                 AND r.name NOT IN ('student','parent','applicant')
               ORDER BY r.id, u.name";
if ($q) {
    $staffQuery = "SELECT u.id, u.name, u.email, r.label role_label
                   FROM users u JOIN roles r ON r.id=u.role_id
                   WHERE u.is_active=1 AND r.name NOT IN ('student','parent','applicant')
                     AND (u.name LIKE ? OR u.email LIKE ? OR r.label LIKE ?)
                   ORDER BY r.id, u.name";
}
if ($q) {
    $stmt = $pdo->prepare($staffQuery);
    $like = "%$q%";
    $stmt->execute([$like,$like,$like]);
} else {
    $stmt = $pdo->query($staffQuery);
}
$allStaff = $stmt->fetchAll();

// Today's / selected-date attendance
$dayAttendance = [];
$dayStmt = $pdo->prepare(
    "SELECT user_id, status, remarks FROM staff_attendance WHERE date=?"
);
$dayStmt->execute([$dateF]);
foreach ($dayStmt->fetchAll() as $row) {
    $dayAttendance[$row['user_id']] = $row;
}

// Monthly summary
$monthStats = $pdo->prepare(
    "SELECT u.id, u.name, r.label role_label,
            SUM(sa.status='Present')  present_days,
            SUM(sa.status='Absent')   absent_days,
            SUM(sa.status='Late')     late_days,
            SUM(sa.status='Half-day') halfday_days,
            SUM(sa.status='Leave')    leave_days,
            COUNT(sa.id)              total_recorded
     FROM users u
     JOIN roles r ON r.id=u.role_id
     LEFT JOIN staff_attendance sa ON sa.user_id=u.id
           AND DATE_FORMAT(sa.date,'%Y-%m') = ?
     WHERE u.is_active=1 AND r.name NOT IN ('student','parent','applicant')
     GROUP BY u.id, u.name, r.label
     ORDER BY r.id, u.name"
);
$monthStats->execute([$monthF]);
$monthData = $monthStats->fetchAll();

// Summary for today
$totalStaff   = count($allStaff);
$presentToday = count(array_filter($dayAttendance, fn($r) => $r['status']==='Present'));
$absentToday  = count(array_filter($dayAttendance, fn($r) => $r['status']==='Absent'));
$lateToday    = count(array_filter($dayAttendance, fn($r) => $r['status']==='Late'));
$notRecorded  = $totalStaff - count($dayAttendance);

$statusColors = ['Present'=>'approved','Absent'=>'warning','Late'=>'pending','Half-day'=>'new-s','Leave'=>'new-s'];
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">Staff Management <span></span></div>
    <h1>Staff Attendance</h1>
    <p><?= e($ay) ?> &mdash; <?= date('F d, Y', strtotime($dateF)) ?></p>
  </div>
</div>

<!-- Metrics -->
<div class="metric-grid" style="margin-bottom:24px">
  <div class="metric-card">
    <div class="metric-top"><span>Total Staff</span><div class="metric-icon">👥</div></div>
    <strong><?= $totalStaff ?></strong><small><i></i>Active users</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Present Today</span><div class="metric-icon" style="background:var(--green-soft);color:var(--green)">✓</div></div>
    <strong style="color:var(--green)"><?= $presentToday ?></strong><small><i></i><?= date('M d', strtotime($dateF)) ?></small>
  </div>
  <div class="metric-card <?= $absentToday > 0?'finance-metrics':'' ?>">
    <div class="metric-top"><span>Absent</span><div class="metric-icon" style="background:var(--error-soft);color:var(--error)">✗</div></div>
    <strong style="color:<?= $absentToday>0?'var(--error)':'inherit' ?>"><?= $absentToday ?></strong><small><i></i>Today</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Not Yet Recorded</span><div class="metric-icon">⏳</div></div>
    <strong><?= $notRecorded ?></strong><small><i></i>Today</small>
  </div>
</div>

<!-- Tabs -->
<div class="tab-bar" style="margin-bottom:16px">
  <a href="?tab=daily&date=<?= urlencode($dateF) ?>"   class="tab-btn <?= $tab==='daily'  ?'active':'' ?>">📆 Daily Record</a>
  <a href="?tab=monthly&date=<?= urlencode($dateF) ?>" class="tab-btn <?= $tab==='monthly'?'active':'' ?>">📊 Monthly Summary</a>
  <a href="?tab=history"                               class="tab-btn <?= $tab==='history'?'active':'' ?>">📋 History</a>
</div>

<?php if ($tab === 'daily'): ?>
<!-- ── DAILY ATTENDANCE ───────────────────────────────────────── -->
<form method="post" action="">
  <?= csrfField() ?><input type="hidden" name="action" value="record_bulk"/>
  <div class="filter-row" style="margin-bottom:14px">
    <label style="font-size:13px;font-weight:600">Date: <input type="date" name="date" value="<?= e($dateF) ?>" max="<?= $today ?>" style="margin-left:6px;padding:8px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit" onchange="this.form.submit()"/></label>
    <div class="table-search" style="margin-left:auto">🔍<input type="search" name="q" placeholder="Filter staff…" value="<?= e($q) ?>"/></div>
    <button type="submit" class="button button-secondary button-sm" name="just_filter" value="1">Filter</button>
  </div>

  <div class="table-wrap">
    <table>
      <thead><tr><th>Staff Member</th><th>Role</th><th>Status</th><th>Remarks</th></tr></thead>
      <tbody>
        <?php foreach ($allStaff as $staff):
          $rec = $dayAttendance[$staff['id']] ?? null;
          $cur = $rec['status'] ?? 'Present';
        ?>
        <tr>
          <td>
            <strong><?= e($staff['name']) ?></strong>
            <div style="font-size:11.5px;color:var(--ink-faint)"><?= e($staff['email']) ?></div>
          </td>
          <td class="muted"><?= e($staff['role_label']) ?></td>
          <td>
            <select name="status[<?= $staff['id'] ?>]"
                    style="padding:7px 10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-size:12.5px;font-family:inherit">
              <?php foreach(['Present','Absent','Late','Half-day','Leave'] as $s): ?>
              <option value="<?= $s ?>" <?= $cur===$s?'selected':'' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td>
            <input type="text" name="remarks[<?= $staff['id'] ?>]"
                   value="<?= e($rec['remarks']??'') ?>"
                   placeholder="Optional note"
                   style="padding:7px 10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-size:12.5px;font-family:inherit;width:100%"/>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div style="display:flex;justify-content:flex-end;padding:16px 0">
    <button type="submit" class="button button-primary">💾 Save Attendance for <?= date('M d, Y', strtotime($dateF)) ?></button>
  </div>
</form>

<?php elseif ($tab === 'monthly'): ?>
<!-- ── MONTHLY SUMMARY ───────────────────────────────────────── -->
<div class="filter-row" style="margin-bottom:14px">
  <label style="font-size:13px;font-weight:600">Month:
    <input type="month" value="<?= $monthF ?>"
           onchange="window.location='?tab=monthly&date='+this.value+'-01'"
           style="margin-left:6px;padding:8px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit"/>
  </label>
</div>
<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Staff Member</th><th>Role</th>
        <th style="color:var(--green)">Present</th>
        <th style="color:var(--error)">Absent</th>
        <th style="color:var(--warning)">Late</th>
        <th>Half-day</th><th>Leave</th><th>Rate</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($monthData as $row):
        $rate = $row['total_recorded'] > 0
            ? round(($row['present_days'] / $row['total_recorded']) * 100, 1) : null;
      ?>
      <tr>
        <td><strong><?= e($row['name']) ?></strong></td>
        <td class="muted"><?= e($row['role_label']) ?></td>
        <td style="color:var(--green);font-weight:700"><?= $row['present_days'] ?: '—' ?></td>
        <td style="color:<?= $row['absent_days']>0?'var(--error)':'var(--ink-faint)' ?>;font-weight:700"><?= $row['absent_days'] ?: '—' ?></td>
        <td style="color:var(--warning)"><?= $row['late_days'] ?: '—' ?></td>
        <td class="muted"><?= $row['halfday_days'] ?: '—' ?></td>
        <td class="muted"><?= $row['leave_days'] ?: '—' ?></td>
        <td>
          <?php if ($rate !== null): ?>
          <span class="status <?= $rate>=90?'approved':($rate>=75?'new-s':'warning') ?>"><?= $rate ?>%</span>
          <?php else: ?><span class="muted">—</span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<div style="display:flex;justify-content:flex-end;margin-top:10px">
  <a href="<?= BASE_URL ?>/admin/reports.php?export=csv&type=staff_attendance&month=<?= urlencode($monthF) ?>"
     class="button button-secondary button-sm">📥 Export CSV</a>
</div>

<?php elseif ($tab === 'history'): ?>
<!-- ── HISTORY ───────────────────────────────────────────────── -->
<?php
$histPage = max(1,(int)($_GET['page']??1)); $histPer = 30;
$histTotal= (int)$pdo->query("SELECT COUNT(*) FROM staff_attendance")->fetchColumn();
$histPg   = paginate($histTotal, $histPer, $histPage);
$history  = $pdo->query(
    "SELECT sa.*,u.name staff_name,r.label role_label
     FROM staff_attendance sa
     JOIN users u ON u.id=sa.user_id
     JOIN roles r ON r.id=u.role_id
     ORDER BY sa.date DESC, u.name
     LIMIT $histPer OFFSET {$histPg['offset']}"
)->fetchAll();
?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Date</th><th>Staff</th><th>Role</th><th>Status</th><th>Remarks</th></tr></thead>
    <tbody>
      <?php if (empty($history)): ?>
      <tr><td colspan="5" style="text-align:center;padding:28px;color:var(--ink-faint)">No attendance history yet.</td></tr>
      <?php else: foreach ($history as $h): ?>
      <tr>
        <td class="muted"><?= date('M d, Y', strtotime($h['date'])) ?></td>
        <td><strong><?= e($h['staff_name']) ?></strong></td>
        <td class="muted"><?= e($h['role_label']) ?></td>
        <td><span class="status <?= $statusColors[$h['status']]??'new-s' ?>"><?= e($h['status']) ?></span></td>
        <td class="muted"><?= e($h['remarks'] ?? '—') ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php if ($histPg['pages']>1): ?>
<div class="pagination">
  <?php if ($histPg['page']>1): ?><a href="?tab=history&page=<?=$histPg['page']-1?>">&laquo;</a><?php endif; ?>
  <?php for($p=max(1,$histPg['page']-2);$p<=min($histPg['pages'],$histPg['page']+2);$p++): ?>
    <?php if($p===$histPg['page']): ?><span class="current"><?=$p?></span><?php else: ?><a href="?tab=history&page=<?=$p?>"><?=$p?></a><?php endif; ?>
  <?php endfor; ?>
  <?php if ($histPg['page']<$histPg['pages']): ?><a href="?tab=history&page=<?=$histPg['page']+1?>">&raquo;</a><?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
