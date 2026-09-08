<?php
// ============================================================
// Parent Portal — Dashboard
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('parent');

$activePage = 'dashboard';
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();
$user = currentUser();

include __DIR__.'/includes/resolve_child.php';

// Per-child metrics
$attPct = null; $avg = null; $paid = 0; $due = 0; $balance = 0;
$recentPay = []; $borrowedCount = 0; $anns = [];

if ($child) {
    try {
        $at = db()->prepare("SELECT SUM(status='Present') p, COUNT(*) t FROM attendance WHERE student_id=? AND academic_year_id=?");
        $at->execute([$child['id'], $ayId]); $at = $at->fetch();
        $attPct = ($at['t'] > 0) ? round($at['p'] / $at['t'] * 100, 1) : null;
    } catch (Throwable $e) {}

    try {
        $av = db()->prepare("SELECT ROUND(AVG(marks_obtained/max_marks*100),1) FROM assessment_scores WHERE student_id=? AND academic_year_id=? AND status IN ('submitted','approved') AND marks_obtained IS NOT NULL AND max_marks > 0");
        $av->execute([$child['id'], $ayId]); $avg = $av->fetchColumn();
    } catch (Throwable $e) {}

    try {
        $paid = (float)db()->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE student_id={$child['id']} AND currency='LRD' AND academic_year_id=$ayId")->fetchColumn();
        $due  = (float)db()->query("SELECT COALESCE(SUM(amount),0) FROM fee_structures WHERE academic_year_id=$ayId AND is_active=1 AND currency='LRD' AND (grade_id IS NULL OR grade_id={$child['current_grade_id']})")->fetchColumn();
        $balance = $due - $paid;
    } catch (Throwable $e) {}

    try {
        $rp = db()->prepare("SELECT receipt_number, amount, currency, payment_date, payment_method FROM payments WHERE student_id=? ORDER BY payment_date DESC LIMIT 4");
        $rp->execute([$child['id']]); $recentPay = $rp->fetchAll();
    } catch (Throwable $e) {}

    try {
        $bl = db()->prepare("SELECT COUNT(*) FROM library_borrowings WHERE student_id=? AND returned_at IS NULL");
        $bl->execute([$child['id']]); $borrowedCount = (int)$bl->fetchColumn();
    } catch (Throwable $e) {}
}

try {
    $anns = db()->query("SELECT title, message, published_at FROM announcements WHERE target IN ('all','parents') AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY published_at DESC LIMIT 4")->fetchAll();
} catch (Throwable $e) { $anns = []; }

$firstName = explode(' ', $user['name'])[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Parent Portal — KHS</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <!-- Header -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
    <div>
      <h1 style="font-size:24px;font-weight:800;margin-bottom:4px">Welcome, <?= e($firstName) ?>! 👋</h1>
      <p style="color:var(--ink-soft);font-size:13px">Parent Portal &mdash; <?= e($ay) ?></p>
    </div>
  </div>

  <?php if (empty($children)): ?>
  <div class="alert alert-warning">No children linked to your account. Please contact the school registrar to link your children.</div>

  <?php else: ?>

  <!-- Child info card -->
  <?php if ($child):
    $ini = strtoupper(substr($child['first_name'],0,1).substr($child['last_name'],0,1));
  ?>
  <div class="panel" style="display:flex;align-items:center;gap:16px;padding:20px 22px;margin-bottom:20px;flex-wrap:wrap">
    <div class="avatar" style="width:52px;height:52px;font-size:18px;flex-shrink:0"><?= e($ini) ?></div>
    <div>
      <h2 style="font-size:18px;font-weight:800"><?= e($child['first_name'].' '.$child['last_name']) ?></h2>
      <p style="color:var(--ink-soft);font-size:13px">
        <?= e($child['grade_name'] ?? '') ?><?= $child['class_name'] ? ' / '.e($child['class_name']) : '' ?>
        &nbsp;·&nbsp; <?= e($child['student_id']) ?>
      </p>
    </div>
    <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap">
      <a href="child_profile.php?child_id=<?= $child['id'] ?>" class="filter-button">View Profile</a>
      <a href="child_attendance.php?child_id=<?= $child['id'] ?>" class="filter-button">Attendance</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- Metrics -->
  <div class="metric-grid" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr));margin-bottom:20px">
    <div class="metric-card">
      <div class="metric-top"><span>Current Average</span><div class="metric-icon">📊</div></div>
      <strong><?= $avg ? $avg.'%' : '—' ?></strong>
      <small><i></i><?= e($ay) ?></small>
    </div>
    <div class="metric-card">
      <div class="metric-top"><span>Attendance Rate</span><div class="metric-icon">📆</div></div>
      <strong style="color:<?= $attPct !== null && $attPct < 80 ? 'var(--error)' : '' ?>"><?= $attPct !== null ? $attPct.'%' : '—' ?></strong>
      <small><i></i><?= $attPct !== null && $attPct < 80 ? 'Below 80%' : 'This year' ?></small>
    </div>
    <div class="metric-card <?= $balance > 0 ? 'finance-metrics' : '' ?>">
      <div class="metric-top"><span>Fee Balance</span><div class="metric-icon">💰</div></div>
      <strong style="color:<?= $balance > 0 ? 'var(--error)' : 'var(--green)' ?>">LRD <?= number_format($balance) ?></strong>
      <small><i></i><?= $balance > 0 ? 'Outstanding' : 'Fully paid' ?></small>
    </div>
    <div class="metric-card">
      <div class="metric-top"><span>Books Borrowed</span><div class="metric-icon">📖</div></div>
      <strong><?= $borrowedCount ?></strong>
      <small><i></i>Currently out</small>
    </div>
  </div>

  <!-- Quick access -->
  <div style="margin-bottom:20px">
    <h3 style="font-size:13px;font-weight:700;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.07em;margin-bottom:12px">Quick Access</h3>
    <div class="quick-grid">
      <?php $cq = $child ? '?child_id='.$child['id'] : ''; ?>
      <a href="child_results.php<?= $cq ?>"  class="quick-item"><span class="qi-icon">📊</span><div><strong>Grades</strong><small>View marks &amp; results</small></div></a>
      <a href="report_card.php<?= $cq ?>"    class="quick-item"><span class="qi-icon">📑</span><div><strong>Report Card</strong><small>Download report</small></div></a>
      <a href="child_attendance.php<?= $cq ?>" class="quick-item"><span class="qi-icon">📆</span><div><strong>Attendance</strong><small>Attendance record</small></div></a>
      <a href="timetable.php<?= $cq ?>"      class="quick-item"><span class="qi-icon">📅</span><div><strong>Timetable</strong><small>Class schedule</small></div></a>
      <a href="fees.php<?= $cq ?>"           class="quick-item"><span class="qi-icon">💰</span><div><strong>Fees</strong><small>Payments &amp; balance</small></div></a>
      <a href="announcements.php"             class="quick-item"><span class="qi-icon">📢</span><div><strong>Notices</strong><small>School announcements</small></div></a>
    </div>
  </div>

  <!-- Recent payments -->
  <?php if (!empty($recentPay)): ?>
  <div class="panel" style="margin-bottom:20px">
    <div class="panel-heading">
      <div><h3>Recent Payments</h3></div>
      <a href="fees.php<?= $child ? '?child_id='.$child['id'] : '' ?>" class="filter-button">All payments →</a>
    </div>
    <div class="table-wrap" style="border:none;border-radius:0">
      <table>
        <thead><tr><th>Receipt</th><th>Amount</th><th>Method</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($recentPay as $p): ?>
          <tr>
            <td class="muted"><?= e($p['receipt_number']) ?></td>
            <td><strong><?= e($p['currency']) ?> <?= number_format($p['amount'], 2) ?></strong></td>
            <td><?= e($p['payment_method']) ?></td>
            <td class="muted"><?= date('M d, Y', strtotime($p['payment_date'])) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <?php endif; ?>

  <!-- Announcements -->
  <?php if (!empty($anns)): ?>
  <div class="panel">
    <div class="panel-heading">
      <div><h3>School Announcements</h3></div>
      <a href="announcements.php" class="filter-button">View all →</a>
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
