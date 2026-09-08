<?php
// ============================================================
// Parent Portal — Fees & Payments
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('parent');

$activePage = 'fees';
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();

include __DIR__.'/includes/resolve_child.php';

$payments = []; $totalPaid = 0; $totalDue = 0;

if ($child) {
    try {
        $p = db()->prepare("SELECT p.*, f.fee_type FROM payments p LEFT JOIN fee_structures f ON f.id=p.fee_structure_id WHERE p.student_id=? ORDER BY p.payment_date DESC");
        $p->execute([$child['id']]); $payments = $p->fetchAll();
        $totalPaid = (float)db()->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE student_id={$child['id']} AND currency='LRD' AND academic_year_id=$ayId")->fetchColumn();
        $totalDue  = (float)db()->query("SELECT COALESCE(SUM(amount),0) FROM fee_structures WHERE academic_year_id=$ayId AND is_active=1 AND currency='LRD' AND (grade_id IS NULL OR grade_id={$child['current_grade_id']})")->fetchColumn();
    } catch (Throwable $e) {}
}
$balance = $totalDue - $totalPaid;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Fees — Parent Portal</title>
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
      <p><?= $child ? e($child['first_name'].' '.$child['last_name']).' &mdash; ' : '' ?><?= e($ay) ?></p>
    </div>
  </div>

  <?php if (empty($children)): ?>
  <div class="alert alert-warning">No children linked. Please contact the school registrar.</div>

  <?php elseif (!$child): ?>
  <div class="alert alert-warning">Child not found. <a href="<?= BASE_URL ?>/portal/parent/">Go back</a>.</div>

  <?php else: ?>
  <div class="metric-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px">
    <div class="metric-card">
      <div class="metric-top"><span>Total Due</span><div class="metric-icon">📋</div></div>
      <strong>LRD <?= number_format($totalDue) ?></strong>
      <small><i></i>Full year</small>
    </div>
    <div class="metric-card">
      <div class="metric-top"><span>Paid</span><div class="metric-icon">✅</div></div>
      <strong style="color:var(--green)">LRD <?= number_format($totalPaid) ?></strong>
      <small><i></i><?= count($payments) ?> payment<?= count($payments)!==1?'s':'' ?></small>
    </div>
    <div class="metric-card <?= $balance > 0 ? 'finance-metrics' : '' ?>">
      <div class="metric-top"><span>Balance</span><div class="metric-icon">⏳</div></div>
      <strong style="color:<?= $balance > 0 ? 'var(--error)' : 'var(--green)' ?>">LRD <?= number_format(abs($balance)) ?></strong>
      <small><i></i><?= $balance > 0 ? 'Outstanding' : 'Paid in full' ?></small>
    </div>
  </div>

  <?php if ($totalDue > 0): ?>
  <div class="panel" style="padding:18px 22px;margin-bottom:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <strong style="font-size:13px">Payment Progress</strong>
      <span><?= round(min(100, $totalPaid / $totalDue * 100), 1) ?>% paid</span>
    </div>
    <div class="progress-bar"><div class="progress-fill green" style="width:<?= min(100, $totalPaid / $totalDue * 100) ?>%"></div></div>
    <?php if ($balance > 0): ?>
    <p style="font-size:12px;color:var(--error);margin-top:8px">⚠️ Outstanding balance: LRD <?= number_format($balance) ?>. Please visit the Bursar's office.</p>
    <?php else: ?>
    <p style="font-size:12px;color:var(--green);margin-top:8px">✓ All fees are fully paid.</p>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="table-wrap">
    <table>
      <thead><tr><th>Receipt</th><th>Fee Type</th><th>Amount</th><th>Method</th><th>Date</th><th></th></tr></thead>
      <tbody>
        <?php if (empty($payments)): ?>
        <tr><td colspan="6" style="text-align:center;padding:28px;color:var(--ink-faint)">No payment records found.</td></tr>
        <?php else: foreach ($payments as $p): ?>
        <tr>
          <td class="muted"><?= e($p['receipt_number'] ?? '—') ?></td>
          <td><?= e($p['fee_type'] ?? 'Payment') ?></td>
          <td><strong><?= e($p['currency']) ?> <?= number_format($p['amount'], 2) ?></strong></td>
          <td><?= e($p['payment_method']) ?></td>
          <td class="muted"><?= date('M d, Y', strtotime($p['payment_date'])) ?></td>
          <td><a href="<?= BASE_URL ?>/letters/receipt_pdf.php?payment_id=<?= $p['id'] ?>" class="filter-button button-sm" target="_blank">📄 Receipt</a></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
