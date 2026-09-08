<?php
// ============================================================
// Student Portal — Fees & Payments
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('student');

$pdo        = db();
$user       = currentUser();
$activePage = 'fees';
$ayId       = currentAcademicYearId();
$ay         = currentAcademicYearName();

$student = $pdo->prepare(
    "SELECT id, first_name, last_name, student_id, current_grade_id
     FROM students WHERE user_id=? LIMIT 1"
);
$student->execute([$user['id']]);
$student = $student->fetch();
if (!$student) { redirect(BASE_URL.'/portal/student/'); }

// Payment history
$payments = $pdo->prepare(
    "SELECT p.*, f.fee_type
     FROM payments p
     LEFT JOIN fee_structures f ON f.id = p.fee_structure_id
     WHERE p.student_id = ?
     ORDER BY p.payment_date DESC"
);
$payments->execute([$student['id']]);
$payments = $payments->fetchAll();

// Totals
$totalPaid = (float)$pdo->prepare(
    "SELECT COALESCE(SUM(amount),0) FROM payments
     WHERE student_id=? AND currency='LRD' AND academic_year_id=?"
)->execute([$student['id'], $ayId]) ? $pdo->query(
    "SELECT COALESCE(SUM(amount),0) FROM payments
     WHERE student_id={$student['id']} AND currency='LRD' AND academic_year_id=$ayId"
)->fetchColumn() : 0;

$totalDue = (float)$pdo->query(
    "SELECT COALESCE(SUM(amount),0) FROM fee_structures
     WHERE academic_year_id=$ayId AND is_active=1 AND currency='LRD'
       AND (grade_id IS NULL OR grade_id={$student['current_grade_id']})"
)->fetchColumn();

$balance = $totalDue - $totalPaid;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Fees — Student Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <div class="page-heading">
    <div>
      <h1>Fees &amp; Payments</h1>
      <p><?= e($ay) ?></p>
    </div>
  </div>

  <!-- Summary -->
  <div class="metric-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px">
    <div class="metric-card">
      <div class="metric-top"><span>Total Due (LRD)</span><div class="metric-icon">📋</div></div>
      <strong>LRD <?= number_format($totalDue) ?></strong>
      <small><i></i>Full year fees</small>
    </div>
    <div class="metric-card">
      <div class="metric-top"><span>Paid (LRD)</span><div class="metric-icon">✅</div></div>
      <strong style="color:var(--green)">LRD <?= number_format($totalPaid) ?></strong>
      <small><i></i><?= count($payments) ?> payment<?= count($payments)!==1?'s':'' ?></small>
    </div>
    <div class="metric-card <?= $balance > 0 ? 'finance-metrics' : '' ?>">
      <div class="metric-top"><span>Balance (LRD)</span><div class="metric-icon">⏳</div></div>
      <strong style="color:<?= $balance > 0 ? 'var(--error)' : 'var(--green)' ?>">
        LRD <?= number_format(abs($balance)) ?>
      </strong>
      <small><i></i><?= $balance > 0 ? 'Outstanding — visit Bursar' : 'Fully paid' ?></small>
    </div>
  </div>

  <!-- Balance bar -->
  <?php if ($totalDue > 0): ?>
  <div class="panel" style="padding:20px 22px;margin-bottom:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <strong style="font-size:13px">Payment Progress</strong>
      <span><?= round(min(100, $totalPaid / $totalDue * 100), 1) ?>% paid</span>
    </div>
    <div class="progress-bar">
      <div class="progress-fill green" style="width:<?= min(100, $totalPaid / $totalDue * 100) ?>%"></div>
    </div>
    <?php if ($balance > 0): ?>
    <p style="font-size:12px;color:var(--error);margin-top:8px">
      ⚠️ You have an outstanding balance of LRD <?= number_format($balance) ?>. Please visit the Bursar's office.
    </p>
    <?php else: ?>
    <p style="font-size:12px;color:var(--green);margin-top:8px">✓ All fees for <?= e($ay) ?> are fully paid.</p>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Payment history -->
  <div class="panel" style="padding:20px 22px">
    <h3 style="font-size:14px;font-weight:700;margin-bottom:16px">Payment History</h3>
    <?php if (empty($payments)): ?>
    <p style="color:var(--ink-faint);font-size:13px;text-align:center;padding:20px">No payment records found.</p>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Receipt No.</th>
            <th>Date</th>
            <th>Fee Type</th>
            <th>Method</th>
            <th style="text-align:right">Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
          <tr>
            <td><strong style="font-size:12px"><?= e($p['receipt_number'] ?? '—') ?></strong></td>
            <td class="muted"><?= $p['payment_date'] ? date('M d, Y', strtotime($p['payment_date'])) : '—' ?></td>
            <td><?= e($p['fee_type'] ?? 'General') ?></td>
            <td class="muted"><?= e($p['payment_method'] ?? '—') ?></td>
            <td style="text-align:right;font-weight:700">
              <?= e($p['currency'] ?? 'LRD') ?> <?= number_format((float)$p['amount'], 2) ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <p style="margin-top:12px;font-size:12px;color:var(--ink-faint);text-align:center">
    For payment disputes or receipts, please contact the Bursar's office.
  </p>

</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
