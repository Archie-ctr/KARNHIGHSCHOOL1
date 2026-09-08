<?php
$pageTitle='Finance Dashboard'; $activeAdmin='dashboard';
require_once dirname(dirname(__DIR__)).'/includes/admin_header.php';
requireRole(['accountant','principal','sys_admin','super_admin','school_admin']);

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();
$fn   = explode(' ', currentUser()['name'] ?? 'Accountant')[0];
$hour = (int)date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

// ── Core financials ───────────────────────────────────────────
$totalLRD     = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='LRD' AND academic_year_id=$ayId")->fetchColumn();
$totalUSD     = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='USD' AND academic_year_id=$ayId")->fetchColumn();
$todayLRD     = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='LRD' AND DATE(payment_date)=CURDATE()")->fetchColumn();
$todayTxCount = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE DATE(payment_date)=CURDATE()")->fetchColumn();
$payCount     = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE academic_year_id=$ayId")->fetchColumn();
$paidStudents = (int)$pdo->query("SELECT COUNT(DISTINCT student_id) FROM payments WHERE academic_year_id=$ayId")->fetchColumn();
$totalStudents= (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status='Active' AND academic_year_id=$ayId")->fetchColumn();
$unpaid       = max(0, $totalStudents - $paidStudents);

$targetLRD     = (float)setting('annual_fee_target','2920000');
$collectionPct = $targetLRD > 0 ? min(100, round($totalLRD / $targetLRD * 100, 1)) : 0;

try {
    $approvedExpenses = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM expense_requests WHERE status IN ('approved','paid') AND currency='LRD' AND academic_year_id=$ayId")->fetchColumn();
    $paidExpenses     = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM expense_requests WHERE status='paid' AND currency='LRD' AND academic_year_id=$ayId")->fetchColumn();
    $pendingExpenses  = (int)$pdo->query("SELECT COUNT(*) FROM expense_requests WHERE status='pending' AND academic_year_id=$ayId")->fetchColumn();
    $pendingWaivers   = (int)$pdo->query("SELECT COUNT(*) FROM fee_waivers WHERE status='pending' AND academic_year_id=$ayId")->fetchColumn();
} catch (Throwable $e) { $approvedExpenses = 0; $paidExpenses = 0; $pendingExpenses = 0; $pendingWaivers = 0; }

$balance = $totalLRD - $paidExpenses;

// ── Recent payments ───────────────────────────────────────────
$recentPay = $pdo->query(
    "SELECT p.receipt_number, CONCAT(s.first_name,' ',s.last_name) sname,
            s.student_id sid, p.amount, p.currency, p.payment_method, p.payment_date
     FROM payments p JOIN students s ON s.id=p.student_id
     ORDER BY p.created_at DESC LIMIT 8"
)->fetchAll();

// ── Monthly collection trend ──────────────────────────────────
$monthlyTrend = $pdo->prepare(
    "SELECT DATE_FORMAT(payment_date,'%b') mon,
            SUM(CASE WHEN currency='LRD' THEN amount ELSE 0 END) lrd
     FROM payments WHERE academic_year_id=?
     GROUP BY DATE_FORMAT(payment_date,'%Y-%m'), DATE_FORMAT(payment_date,'%b')
     ORDER BY MIN(payment_date) ASC LIMIT 8"
);
$monthlyTrend->execute([$ayId]);
$monthlyTrend = $monthlyTrend->fetchAll();
$maxMonthly   = max(array_column($monthlyTrend,'lrd') ?: [1]);

// ── Payment method split ──────────────────────────────────────
$byMethod = $pdo->prepare(
    "SELECT payment_method,
            SUM(CASE WHEN currency='LRD' THEN amount ELSE 0 END) lrd,
            COUNT(*) cnt
     FROM payments WHERE academic_year_id=? GROUP BY payment_method ORDER BY lrd DESC"
);
$byMethod->execute([$ayId]); $byMethod = $byMethod->fetchAll();

// ── Top outstanding students ──────────────────────────────────
$topOutstanding = $pdo->prepare(
    "SELECT s.student_id student_code, CONCAT(s.first_name,' ',s.last_name) sname,
            g.name grade_name,
            COALESCE((SELECT SUM(f.amount) FROM fee_structures f WHERE f.academic_year_id=? AND f.currency='LRD' AND f.is_active=1 AND (f.grade_id IS NULL OR f.grade_id=s.current_grade_id)),0) due,
            COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.student_id=s.id AND p.academic_year_id=? AND p.currency='LRD'),0) paid
     FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id
     WHERE s.academic_year_id=? AND s.status='Active'
     HAVING due > 0 AND paid < due
     ORDER BY (due-paid) DESC LIMIT 6"
);
$topOutstanding->execute([$ayId,$ayId,$ayId]);
$topOutstanding = $topOutstanding->fetchAll();

$needsAttention = $pendingExpenses + $pendingWaivers;
?>

<div class="page-heading">
  <div>
    <div class="eyebrow"><?= date('l, F d, Y') ?> <span></span></div>
    <h1><?= $greet ?>, <?= e($fn) ?>.</h1>
    <p>Finance Dashboard — <?= e($ay) ?></p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="<?= BASE_URL ?>/admin/finance_reports.php" class="button button-secondary">📊 Reports</a>
    <a href="<?= BASE_URL ?>/admin/finance.php" class="button button-primary">+ Record Payment</a>
  </div>
</div>

<!-- Approval alert -->
<?php if ($needsAttention > 0): ?>
<div style="background:linear-gradient(135deg,#701422,#3e0c19);color:#fff;border-radius:var(--radius);padding:14px 20px;margin-bottom:20px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
  <span style="font-size:1.3rem">🔔</span>
  <span style="font-size:13px;flex:1"><strong><?= $needsAttention ?> item<?= $needsAttention!==1?'s':'' ?></strong> need attention</span>
  <?php if ($pendingExpenses): ?>
  <a href="<?= BASE_URL ?>/admin/expenses.php?tab=expenses" style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);border-radius:var(--radius-sm);padding:6px 14px;text-decoration:none;color:#fff;font-size:12.5px">
    💸 <?= $pendingExpenses ?> expense<?= $pendingExpenses!==1?'s':'' ?> pending
  </a>
  <?php endif; ?>
  <?php if ($pendingWaivers): ?>
  <a href="<?= BASE_URL ?>/admin/expenses.php?tab=waivers" style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);border-radius:var(--radius-sm);padding:6px 14px;text-decoration:none;color:#fff;font-size:12.5px">
    💳 <?= $pendingWaivers ?> waiver<?= $pendingWaivers!==1?'s':'' ?> pending
  </a>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ═══════ KPI METRICS ═══════ -->
<div class="metric-grid finance-metrics" style="margin-bottom:24px">
  <div class="metric-card">
    <div class="metric-top"><span>Collected (LRD)</span><div class="metric-icon">💵</div></div>
    <strong>LRD <?= number_format($totalLRD) ?></strong><small><i></i><?= $collectionPct ?>% of target</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Today's Collection</span><div class="metric-icon">📅</div></div>
    <strong>LRD <?= number_format($todayLRD) ?></strong><small><i></i><?= $todayTxCount ?> transaction<?= $todayTxCount!==1?'s':'' ?></small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Collected (USD)</span><div class="metric-icon">💲</div></div>
    <strong>USD <?= number_format($totalUSD,2) ?></strong><small><i></i><?= $payCount ?> total receipts</small>
  </div>
  <div class="metric-card <?= $unpaid>0?'finance-metrics':'' ?>">
    <div class="metric-top"><span>Yet to Pay</span><div class="metric-icon">⏳</div></div>
    <strong style="color:<?= $unpaid>0?'var(--error)':'var(--green)' ?>"><?= number_format($unpaid) ?></strong>
    <small><i></i>of <?= $totalStudents ?> students</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Expenses Paid</span><div class="metric-icon">💸</div></div>
    <strong>LRD <?= number_format($paidExpenses) ?></strong><small><i></i>LRD <?= number_format($approvedExpenses) ?> approved</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Net Balance</span><div class="metric-icon">⚖️</div></div>
    <strong style="color:<?= $balance>=0?'var(--green)':'var(--error)' ?>">LRD <?= number_format(abs($balance)) ?></strong>
    <small><i></i><?= $balance>=0?'Surplus':'Deficit' ?></small>
  </div>
</div>

<!-- Collection progress -->
<div class="panel" style="padding:20px 22px;margin-bottom:20px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
    <strong>Annual Target: LRD <?= number_format($targetLRD) ?></strong>
    <span class="status <?= $collectionPct>=80?'approved':($collectionPct>=50?'new-s':'warning') ?>"><?= $collectionPct ?>% collected</span>
  </div>
  <div class="progress-bar"><div class="progress-fill green" style="width:<?= $collectionPct ?>%"></div></div>
</div>

<!-- ═══════ QUICK ACCESS ═══════ -->
<h3 style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--ink-soft);margin-bottom:10px">Quick Access</h3>

<!-- Fee & Payment Management -->
<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:7px">💰 Fee &amp; Payment Management</div>
<div class="quick-grid" style="margin-bottom:16px">
  <a href="<?= BASE_URL ?>/admin/finance.php"              class="quick-item"><span class="qi-icon">💳</span><div><strong>Record Payment</strong><small>Cash, bank, mobile</small></div></a>
  <a href="<?= BASE_URL ?>/admin/fee_structures.php"       class="quick-item"><span class="qi-icon">📋</span><div><strong>Fee Structures</strong><small>Manage fee types</small></div></a>
  <a href="<?= BASE_URL ?>/admin/student_statements.php"   class="quick-item"><span class="qi-icon">📊</span><div><strong>Student Statements</strong><small>Balances &amp; history</small></div></a>
  <a href="<?= BASE_URL ?>/admin/expenses.php"             class="quick-item"><span class="qi-icon">💸</span><div><strong>Expenses &amp; Waivers</strong><small><?= $pendingExpenses+$pendingWaivers ?> pending</small></div></a>
</div>

<!-- Accounting & Reconciliation -->
<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:7px">📒 Accounting</div>
<div class="quick-grid" style="margin-bottom:16px">
  <a href="<?= BASE_URL ?>/admin/accounting.php"                    class="quick-item"><span class="qi-icon">📒</span><div><strong>Cashbook</strong><small>All entries</small></div></a>
  <a href="<?= BASE_URL ?>/admin/accounting.php?tab=reconciliation" class="quick-item"><span class="qi-icon">🔄</span><div><strong>Reconciliation</strong><small>Bank &amp; mobile money</small></div></a>
  <a href="<?= BASE_URL ?>/admin/accounting.php?tab=monthly"        class="quick-item"><span class="qi-icon">📅</span><div><strong>Monthly Summary</strong><small>Collection trend</small></div></a>
  <a href="<?= BASE_URL ?>/admin/accounting.php?tab=add_entry"      class="quick-item"><span class="qi-icon">➕</span><div><strong>Add Entry</strong><small>Manual cashbook</small></div></a>
</div>

<!-- Reports -->
<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:7px">📊 Financial Reports</div>
<div class="quick-grid" style="margin-bottom:24px">
  <a href="<?= BASE_URL ?>/admin/finance_reports.php?tab=summary"     class="quick-item"><span class="qi-icon">📈</span><div><strong>Financial Summary</strong><small>Income &amp; expenses</small></div></a>
  <a href="<?= BASE_URL ?>/admin/finance_reports.php?tab=daily"       class="quick-item"><span class="qi-icon">📅</span><div><strong>Daily Collection</strong><small>Today's receipts</small></div></a>
  <a href="<?= BASE_URL ?>/admin/finance_reports.php?tab=outstanding"  class="quick-item"><span class="qi-icon">⏳</span><div><strong>Outstanding Fees</strong><small><?= $unpaid ?> students</small></div></a>
  <a href="<?= BASE_URL ?>/admin/finance_reports.php?tab=income_vs"   class="quick-item"><span class="qi-icon">⚖️</span><div><strong>Income vs Expenses</strong><small>Net position</small></div></a>
  <a href="<?= BASE_URL ?>/admin/finance_reports.php?tab=expenses_rep" class="quick-item"><span class="qi-icon">💸</span><div><strong>Expense Report</strong><small>By category</small></div></a>
</div>

<!-- ═══════ PANELS ═══════ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px" class="acc-dash-grid">

  <!-- Monthly trend chart -->
  <div class="panel" style="padding:22px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <h3 style="font-weight:700;font-size:14px">📅 Monthly Collections (LRD)</h3>
      <a href="<?= BASE_URL ?>/admin/finance_reports.php?tab=summary" class="filter-button">Details →</a>
    </div>
    <?php if (!empty($monthlyTrend)): ?>
    <div style="display:flex;align-items:flex-end;gap:6px;height:64px;margin-bottom:8px">
      <?php foreach ($monthlyTrend as $m):
        $h = $maxMonthly > 0 ? max(6, round($m['lrd']/$maxMonthly*58)) : 6;
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px">
        <span style="font-size:8.5px;color:var(--ink-soft)"><?= number_format($m['lrd']/1000,0) ?>K</span>
        <div style="width:100%;height:<?= $h ?>px;background:var(--green);border-radius:3px 3px 0 0;opacity:.85"></div>
        <span style="font-size:9px;color:var(--ink-soft)"><?= $m['mon'] ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?><p style="color:var(--ink-faint);font-size:13px">No data yet.</p><?php endif; ?>
  </div>

  <!-- Payment method split -->
  <div class="panel" style="padding:22px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <h3 style="font-weight:700;font-size:14px">💳 By Payment Method</h3>
      <a href="<?= BASE_URL ?>/admin/accounting.php?tab=methods" class="filter-button">Details →</a>
    </div>
    <?php foreach ($byMethod as $m):
      $w = $totalLRD > 0 ? min(100, round($m['lrd']/$totalLRD*100)) : 0;
      $colors = ['Cash'=>'var(--green)','Bank'=>'var(--primary)','Mobile Money'=>'#e64980','Cheque'=>'var(--warning)','Other'=>'var(--ink-soft)'];
      $c = $colors[$m['payment_method']] ?? 'var(--primary)';
    ?>
    <div style="margin-bottom:8px">
      <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:2px">
        <span><?= e($m['payment_method']) ?> <span style="color:var(--ink-soft)">(<?= $m['cnt'] ?>)</span></span>
        <strong>LRD <?= number_format($m['lrd']) ?></strong>
      </div>
      <div style="height:6px;background:var(--bg2);border-radius:3px;overflow:hidden">
        <div style="width:<?= $w ?>%;height:100%;background:<?= $c ?>;border-radius:3px"></div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($byMethod)):?><p style="color:var(--ink-faint);font-size:13px">No payments yet.</p><?php endif; ?>
  </div>

</div>

<!-- ═══════ RECENT PAYMENTS + TOP OUTSTANDING ═══════ -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px" class="acc-dash-grid">

  <!-- Recent payments -->
  <div class="panel activity-panel">
    <div class="panel-heading">
      <div><h3>Recent Payments</h3></div>
      <a href="<?= BASE_URL ?>/admin/finance.php" class="filter-button">All →</a>
    </div>
    <?php if (empty($recentPay)): ?>
    <p style="color:var(--ink-faint);font-size:13px;padding:12px">No payments yet.</p>
    <?php else: foreach ($recentPay as $p): ?>
    <div class="activity">
      <span class="activity-dot green"></span>
      <div>
        <strong><?= e($p['sname']) ?></strong>
        <p><?= e($p['receipt_number']) ?> · <?= e($p['payment_method']) ?></p>
        <small><?= e($p['currency']) ?> <?= number_format($p['amount'],2) ?> · <?= date('M d, Y', strtotime($p['payment_date'])) ?></small>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Top outstanding -->
  <div class="panel" style="padding:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <h3 style="font-weight:700;font-size:14px">⏳ Top Outstanding Balances</h3>
      <a href="<?= BASE_URL ?>/admin/finance_reports.php?tab=outstanding" class="filter-button">All →</a>
    </div>
    <?php if (empty($topOutstanding)): ?>
    <div style="text-align:center;padding:20px">
      <span style="font-size:1.8rem">✅</span>
      <p style="color:var(--ink-soft);font-size:13px;margin-top:8px">All students paid in full.</p>
    </div>
    <?php else: foreach ($topOutstanding as $s): $bal = $s['due'] - $s['paid']; ?>
    <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:7px 0;border-bottom:1px solid var(--line-soft);font-size:12.5px">
      <div>
        <strong><?= e($s['sname']) ?></strong>
        <div style="font-size:11px;color:var(--ink-faint)"><?= e($s['student_code']) ?> · <?= e($s['grade_name']??'—') ?></div>
      </div>
      <strong style="color:var(--error);white-space:nowrap">LRD <?= number_format($bal) ?></strong>
    </div>
    <?php endforeach; endif; ?>
  </div>

</div>

<style>@media(max-width:640px){.acc-dash-grid{grid-template-columns:1fr !important}}</style>
<?php require_once dirname(dirname(__DIR__)).'/includes/admin_footer.php'; ?>
