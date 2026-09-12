<?php
$pageTitle   = 'Financial Reports';
$activeAdmin = 'finance_reports';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['sys_admin','super_admin','school_admin','principal','accountant']);

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();
$tab  = $_GET['tab'] ?? 'summary';

// ── CSV exports ───────────────────────────────────────────────
$export = trim($_GET['export'] ?? '');
$type   = trim($_GET['type']   ?? '');
if ($export === 'csv' && $type) {
    $filename = 'finance_'.$type.'_'.date('Y-m-d').'.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $fp = fopen('php://output','w');
    switch ($type) {
        case 'daily':
            fputcsv($fp,['Receipt','Student','Student ID','Grade','Amount','Currency','Method','Date','Recorded By']);
            $rows=$pdo->query("SELECT p.receipt_number,CONCAT(s.first_name,' ',s.last_name),s.student_id,g.name,p.amount,p.currency,p.payment_method,p.payment_date,u.name FROM payments p JOIN students s ON s.id=p.student_id LEFT JOIN grades g ON g.id=s.current_grade_id LEFT JOIN users u ON u.id=p.recorded_by WHERE DATE(p.payment_date)=CURDATE() ORDER BY p.created_at DESC");
            foreach($rows->fetchAll() as $r) fputcsv($fp,array_values($r)); break;
        case 'all_payments':
            fputcsv($fp,['Receipt','Student','Student ID','Grade','Amount','Currency','Method','Date','Academic Year']);
            $rows=$pdo->prepare("SELECT p.receipt_number,CONCAT(s.first_name,' ',s.last_name),s.student_id,g.name,p.amount,p.currency,p.payment_method,p.payment_date,ay.name FROM payments p JOIN students s ON s.id=p.student_id LEFT JOIN grades g ON g.id=s.current_grade_id LEFT JOIN academic_years ay ON ay.id=p.academic_year_id WHERE p.academic_year_id=? ORDER BY p.payment_date DESC");
            $rows->execute([$ayId]); foreach($rows->fetchAll() as $r) fputcsv($fp,array_values($r)); break;
        case 'outstanding':
            fputcsv($fp,['Student ID','Name','Grade','Total Due (LRD)','Total Paid (LRD)','Balance (LRD)']);
            $rows=$pdo->prepare("SELECT s.student_id,CONCAT(s.first_name,' ',s.last_name),g.name,COALESCE(f.due,0) total_due,COALESCE(p.paid,0) total_paid,COALESCE(f.due,0)-COALESCE(p.paid,0) balance FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id LEFT JOIN (SELECT grade_id,SUM(amount) due FROM fee_structures WHERE academic_year_id=? AND currency='LRD' AND is_active=1 GROUP BY grade_id) f ON (f.grade_id=s.current_grade_id OR f.grade_id IS NULL) LEFT JOIN (SELECT student_id,SUM(amount) paid FROM payments WHERE academic_year_id=? AND currency='LRD' GROUP BY student_id) p ON p.student_id=s.id WHERE s.academic_year_id=? AND s.status='Active' ORDER BY balance DESC");
            $rows->execute([$ayId,$ayId,$ayId]); foreach($rows->fetchAll() as $r) fputcsv($fp,array_values($r)); break;
        case 'expenses':
            fputcsv($fp,['Title','Category','Amount','Currency','Requested By','Status','Date']);
            try {
                $rows=$pdo->prepare("SELECT er.title,er.category,er.amount,er.currency,u.name,er.status,DATE(er.created_at) FROM expense_requests er LEFT JOIN users u ON u.id=er.requested_by WHERE er.academic_year_id=? ORDER BY er.created_at DESC");
                $rows->execute([$ayId]); foreach($rows->fetchAll() as $r) fputcsv($fp,array_values($r));
            } catch(Throwable $e){ fputcsv($fp,['No data']); } break;
    }
    fclose($fp); exit;
}

// ── Data ──────────────────────────────────────────────────────

// Core totals
$totalLRD     = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='LRD' AND academic_year_id=$ayId")->fetchColumn();
$totalUSD     = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='USD' AND academic_year_id=$ayId")->fetchColumn();
$todayLRD     = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='LRD' AND DATE(payment_date)=CURDATE()")->fetchColumn();
$payCount     = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE academic_year_id=$ayId")->fetchColumn();
$paidStudents = (int)$pdo->query("SELECT COUNT(DISTINCT student_id) FROM payments WHERE academic_year_id=$ayId")->fetchColumn();
$totalStudents= (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status='Active' AND academic_year_id=$ayId")->fetchColumn();
$unpaid       = max(0, $totalStudents - $paidStudents);

try {
    $approvedExpenses = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM expense_requests WHERE status IN ('approved','paid') AND currency='LRD' AND academic_year_id=$ayId")->fetchColumn();
    $paidExpenses     = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM expense_requests WHERE status='paid' AND currency='LRD' AND academic_year_id=$ayId")->fetchColumn();
} catch (Throwable $e) { $approvedExpenses = 0; $paidExpenses = 0; }

$balance = $totalLRD - $paidExpenses;

// Monthly income
$monthlyIncome = $pdo->prepare(
    "SELECT DATE_FORMAT(payment_date,'%Y-%m') mon,
            SUM(CASE WHEN currency='LRD' THEN amount ELSE 0 END) lrd,
            COUNT(*) txns
     FROM payments WHERE academic_year_id=?
     GROUP BY DATE_FORMAT(payment_date,'%Y-%m') ORDER BY mon"
);
$monthlyIncome->execute([$ayId]); $monthlyIncome = $monthlyIncome->fetchAll();

// Outstanding fees per student
$outstanding = $pdo->prepare(
    "SELECT s.id, s.student_id student_code,
            CONCAT(s.first_name,' ',s.last_name) student_name,
            g.name grade_name,
            COALESCE((SELECT SUM(f.amount) FROM fee_structures f WHERE f.academic_year_id=? AND f.currency='LRD' AND f.is_active=1 AND (f.grade_id IS NULL OR f.grade_id=s.current_grade_id)),0) total_due,
            COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.student_id=s.id AND p.academic_year_id=? AND p.currency='LRD'),0) total_paid
     FROM students s
     LEFT JOIN grades g ON g.id=s.current_grade_id
     WHERE s.academic_year_id=? AND s.status='Active'
     HAVING total_due > 0 AND total_paid < total_due
     ORDER BY (total_due-total_paid) DESC
     LIMIT 50"
);
$outstanding->execute([$ayId,$ayId,$ayId]);
$outstanding = $outstanding->fetchAll();

// Fee collection by grade
$byGrade = $pdo->prepare(
    "SELECT g.name grade_name, g.sequence,
            COUNT(DISTINCT s.id) students,
            COALESCE(SUM(p.amount),0) collected
     FROM students s
     LEFT JOIN grades g ON g.id=s.current_grade_id
     LEFT JOIN payments p ON p.student_id=s.id AND p.academic_year_id=? AND p.currency='LRD'
     WHERE s.academic_year_id=? AND s.status='Active'
     GROUP BY g.id,g.name,g.sequence ORDER BY g.sequence"
);
$byGrade->execute([$ayId,$ayId]); $byGrade = $byGrade->fetchAll();
$maxByGrade = max(array_column($byGrade,'collected') ?: [1]);

// Expense breakdown
try {
    $expByCategory = $pdo->prepare(
        "SELECT category, SUM(amount) total, COUNT(*) cnt
         FROM expense_requests WHERE academic_year_id=? AND status IN ('approved','paid') AND currency='LRD'
         GROUP BY category ORDER BY total DESC"
    );
    $expByCategory->execute([$ayId]); $expByCategory = $expByCategory->fetchAll();
} catch (Throwable $e) { $expByCategory = []; }

// Daily summary (last 10 collection days)
$recentDays = $pdo->query(
    "SELECT DATE(payment_date) day,
            SUM(CASE WHEN currency='LRD' THEN amount ELSE 0 END) lrd,
            COUNT(*) txns
     FROM payments WHERE academic_year_id=$ayId
     GROUP BY DATE(payment_date) ORDER BY day DESC LIMIT 10"
)->fetchAll();

// Target
$targetLRD = (float)setting('annual_fee_target','2920000');
$collectionPct = $targetLRD > 0 ? min(100, round($totalLRD/$targetLRD*100,1)) : 0;
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">Finance <span></span></div>
    <h1>Financial Reports</h1>
    <p><?= e($ay) ?></p>
  </div>
  <div style="display:flex;gap:8px">
    <a href="?export=csv&type=all_payments" class="button button-secondary button-sm">📥 All Payments CSV</a>
    <a href="<?= BASE_URL ?>/api/export.php?type=payments&format=excel" class="button button-secondary button-sm" style="background:#1d6f42;color:#fff" target="_blank">📊 Excel</a>
    <a href="<?= BASE_URL ?>/api/export.php?type=payments&format=pdf" class="button button-secondary button-sm" style="background:#c00200;color:#fff" target="_blank">🖨 PDF</a>
    <a href="<?= BASE_URL ?>/admin/accounting.php" class="button button-secondary">📒 Cashbook</a>
  </div>
</div>

<!-- KPI metrics -->
<div class="metric-grid finance-metrics" style="margin-bottom:24px">
  <div class="metric-card"><div class="metric-top"><span>Collected (LRD)</span><div class="metric-icon">💵</div></div><strong>LRD <?= number_format($totalLRD) ?></strong><small><i></i><?= $collectionPct ?>% of target</small></div>
  <div class="metric-card"><div class="metric-top"><span>Today (LRD)</span><div class="metric-icon">📅</div></div><strong>LRD <?= number_format($todayLRD) ?></strong><small><i></i><?= $payCount ?> total receipts</small></div>
  <div class="metric-card"><div class="metric-top"><span>Outstanding</span><div class="metric-icon">⏳</div></div><strong style="color:var(--error)"><?= $unpaid ?></strong><small><i></i>students yet to pay</small></div>
  <div class="metric-card"><div class="metric-top"><span>Net Balance</span><div class="metric-icon">⚖️</div></div><strong style="color:<?= $balance>=0?'var(--green)':'var(--error)' ?>">LRD <?= number_format(abs($balance)) ?></strong><small><i></i>Income − Paid expenses</small></div>
</div>

<!-- Tabs -->
<div class="tab-bar" style="margin-bottom:20px">
  <a href="?tab=summary"     class="tab-btn <?= $tab==='summary'    ?'active':'' ?>">📊 Summary</a>
  <a href="?tab=daily"       class="tab-btn <?= $tab==='daily'      ?'active':'' ?>">📅 Daily</a>
  <a href="?tab=outstanding" class="tab-btn <?= $tab==='outstanding'?'active':'' ?>">⏳ Outstanding (<?= count($outstanding) ?>)</a>
  <a href="?tab=by_grade"    class="tab-btn <?= $tab==='by_grade'   ?'active':'' ?>">🎓 By Grade</a>
  <a href="?tab=expenses_rep"class="tab-btn <?= $tab==='expenses_rep'?'active':'' ?>">💸 Expenses</a>
  <a href="?tab=income_vs"   class="tab-btn <?= $tab==='income_vs'  ?'active':'' ?>">📈 Income vs Expenses</a>
</div>

<?php if ($tab === 'summary'): ?>
<!-- ── FINANCIAL SUMMARY ──────────────────────────────────── -->
<!-- Collection target progress -->
<div class="panel" style="padding:22px;margin-bottom:20px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
    <div>
      <strong>Annual Collection Target: LRD <?= number_format($targetLRD) ?></strong>
      <span style="font-size:12px;color:var(--ink-soft);margin-left:8px">LRD <?= number_format($totalLRD) ?> collected</span>
    </div>
    <span class="status approved"><?= $collectionPct ?>% collected</span>
  </div>
  <div class="progress-bar"><div class="progress-fill green" style="width:<?= $collectionPct ?>%"></div></div>
  <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--ink-soft);margin-top:6px">
    <span>LRD 0</span>
    <span>LRD <?= number_format($targetLRD/2) ?></span>
    <span>LRD <?= number_format($targetLRD) ?></span>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="fin-grid">
  <!-- Income summary -->
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">💵 Income Summary — <?= e($ay) ?></h3>
    <?php foreach ([
      ['LRD Collected',      'LRD '.number_format($totalLRD),       'var(--green)'],
      ['USD Collected',      'USD '.number_format($totalUSD,2),      'var(--green)'],
      ['Total Transactions', number_format($payCount),               'inherit'],
      ['Students Paid',      number_format($paidStudents),           'var(--green)'],
      ['Students Outstanding',number_format($unpaid),                'var(--error)'],
    ] as [$label,$val,$color]): ?>
    <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--line-soft);font-size:13px">
      <span><?= $label ?></span><strong style="color:<?= $color ?>"><?= $val ?></strong>
    </div>
    <?php endforeach; ?>
  </div>
  <!-- Expense summary -->
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">💸 Expense Summary — <?= e($ay) ?></h3>
    <?php foreach ([
      ['Approved Expenses (LRD)', 'LRD '.number_format($approvedExpenses), 'var(--warning)'],
      ['Disbursed (Paid)',         'LRD '.number_format($paidExpenses),     'var(--error)'],
      ['Net Balance',              'LRD '.number_format(abs($balance)),     $balance>=0?'var(--green)':'var(--error)'],
    ] as [$label,$val,$color]): ?>
    <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--line-soft);font-size:13px">
      <span><?= $label ?></span><strong style="color:<?= $color ?>"><?= $val ?></strong>
    </div>
    <?php endforeach; ?>
    <?php if(empty($expByCategory)):?><p style="color:var(--ink-faint);font-size:12px;margin-top:10px">No expense records yet.</p><?php endif; ?>
    <a href="<?= BASE_URL ?>/admin/expenses.php" class="lnk" style="font-size:12px;margin-top:12px">Manage expenses →</a>
  </div>
</div>

<?php elseif ($tab === 'daily'): ?>
<!-- ── DAILY COLLECTION ───────────────────────────────────── -->
<div style="display:flex;justify-content:space-between;margin-bottom:12px">
  <strong style="font-size:14px">Today: LRD <?= number_format($todayLRD) ?></strong>
  <a href="?export=csv&type=daily" class="button button-secondary button-sm">📥 Today's CSV</a>
  <a href="<?= BASE_URL ?>/api/export.php?type=payments&format=excel" class="button button-secondary button-sm" style="background:#1d6f42;color:#fff" target="_blank">📊 Excel</a>
  <a href="<?= BASE_URL ?>/api/export.php?type=payments&format=pdf" class="button button-secondary button-sm" style="background:#c00200;color:#fff" target="_blank">🖨 PDF</a>
</div>
<?php
$todayAll = $pdo->query(
    "SELECT p.receipt_number, CONCAT(s.first_name,' ',s.last_name) sname, s.student_id sid,
            g.name grade_name, p.amount, p.currency, p.payment_method, p.payment_date
     FROM payments p JOIN students s ON s.id=p.student_id
     LEFT JOIN grades g ON g.id=s.current_grade_id
     WHERE DATE(p.payment_date)=CURDATE() ORDER BY p.created_at DESC"
)->fetchAll();
?>
<?php if (empty($todayAll)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">📅</div>
  <p style="color:var(--ink-soft)">No payments recorded today.</p>
  <a href="<?= BASE_URL ?>/admin/finance.php" class="button button-primary" style="margin-top:14px">+ Record Payment</a>
</div>
<?php else: ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Receipt</th><th>Student</th><th>Grade</th><th>Amount</th><th>Method</th><th>Time</th><th>Receipt</th></tr></thead>
    <tbody>
      <?php foreach ($todayAll as $p): ?>
      <tr>
        <td class="muted"><?= e($p['receipt_number']) ?></td>
        <td><strong><?= e($p['sname']) ?></strong><div style="font-size:11px;color:var(--ink-faint)"><?= e($p['sid']) ?></div></td>
        <td class="muted"><?= e($p['grade_name']??'—') ?></td>
        <td><strong><?= e($p['currency']) ?> <?= number_format($p['amount'],2) ?></strong></td>
        <td><?= e($p['payment_method']) ?></td>
        <td class="muted"><?= date('H:i', strtotime($p['payment_date'])) ?></td>
        <td><a href="<?= BASE_URL ?>/letters/receipt_pdf.php?payment_id=<?= 0 ?>" class="filter-button button-sm" target="_blank">📄</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
// Recent days summary
if (!empty($recentDays)):
?>
<div class="panel" style="padding:22px;margin-top:20px">
  <h3 style="font-weight:700;font-size:14px;margin-bottom:12px">📅 Recent Collection Days</h3>
  <div class="table-wrap" style="border:none">
    <table>
      <thead><tr><th>Date</th><th>LRD Collected</th><th>Transactions</th></tr></thead>
      <tbody>
        <?php foreach ($recentDays as $d): ?>
        <tr>
          <td><strong><?= date('l, M d, Y', strtotime($d['day'])) ?></strong></td>
          <td style="color:var(--green);font-weight:700">LRD <?= number_format($d['lrd']) ?></td>
          <td class="muted"><?= $d['txns'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; endif; ?>

<?php elseif ($tab === 'outstanding'): ?>
<!-- ── OUTSTANDING FEES ───────────────────────────────────── -->
<div style="display:flex;justify-content:space-between;margin-bottom:12px">
  <span style="font-size:13px;color:var(--ink-soft)"><?= count($outstanding) ?> student<?= count($outstanding)!==1?'s':'' ?> with outstanding balances</span>
  <a href="?export=csv&type=outstanding" class="button button-secondary button-sm">📥 Export CSV</a>
  <a href="<?= BASE_URL ?>/api/export.php?type=outstanding&format=excel" class="button button-secondary button-sm" style="background:#1d6f42;color:#fff" target="_blank">📊 Excel</a>
  <a href="<?= BASE_URL ?>/api/export.php?type=outstanding&format=pdf" class="button button-secondary button-sm" style="background:#c00200;color:#fff" target="_blank">🖨 PDF</a>
</div>
<?php if (empty($outstanding)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">✅</div>
  <p style="color:var(--ink-soft)">All students with fees have paid in full.</p>
</div>
<?php else: ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Student</th><th>Grade</th><th>Total Due (LRD)</th><th>Paid (LRD)</th><th>Balance (LRD)</th><th>Progress</th></tr></thead>
    <tbody>
      <?php foreach ($outstanding as $s):
        $bal = $s['total_due'] - $s['total_paid'];
        $pct = $s['total_due'] > 0 ? round($s['total_paid']/$s['total_due']*100) : 0;
      ?>
      <tr>
        <td>
          <strong><?= e($s['student_name']) ?></strong>
          <div style="font-size:11px;color:var(--ink-faint)"><?= e($s['student_code']) ?></div>
        </td>
        <td class="muted"><?= e($s['grade_name']??'—') ?></td>
        <td class="muted">LRD <?= number_format($s['total_due']) ?></td>
        <td style="color:var(--green)">LRD <?= number_format($s['total_paid']) ?></td>
        <td><strong style="color:var(--error)">LRD <?= number_format($bal) ?></strong></td>
        <td style="min-width:80px">
          <div style="height:7px;background:var(--bg2);border-radius:4px;overflow:hidden">
            <div style="width:<?= $pct ?>%;height:100%;background:<?= $pct>=80?'var(--green)':($pct>=50?'var(--warning)':'var(--error)') ?>;border-radius:4px"></div>
          </div>
          <span style="font-size:10px;color:var(--ink-soft)"><?= $pct ?>% paid</span>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php elseif ($tab === 'by_grade'): ?>
<!-- ── BY GRADE ───────────────────────────────────────────── -->
<div class="panel" style="padding:22px;margin-bottom:16px">
  <h3 style="font-weight:700;font-size:14px;margin-bottom:16px">🎓 Fee Collection by Grade — <?= e($ay) ?></h3>
  <?php foreach ($byGrade as $g):
    $w = $maxByGrade > 0 ? round($g['collected']/$maxByGrade*100) : 0;
  ?>
  <div style="margin-bottom:10px">
    <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:3px">
      <span><?= e($g['grade_name']) ?> <span style="color:var(--ink-soft)">(<?= $g['students'] ?> students)</span></span>
      <strong style="color:var(--green)">LRD <?= number_format($g['collected']) ?></strong>
    </div>
    <div style="height:8px;background:var(--bg2);border-radius:4px;overflow:hidden">
      <div style="width:<?= $w ?>%;height:100%;background:var(--green);border-radius:4px"></div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if(empty($byGrade)):?><p style="color:var(--ink-faint);font-size:13px">No data.</p><?php endif; ?>
</div>

<?php elseif ($tab === 'expenses_rep'): ?>
<!-- ── EXPENSES REPORT ────────────────────────────────────── -->
<div style="display:flex;justify-content:flex-end;margin-bottom:12px">
  <a href="?export=csv&type=expenses" class="button button-secondary button-sm">📥 Export CSV</a>
  <a href="<?= BASE_URL ?>/api/export.php?type=expenses&format=excel" class="button button-secondary button-sm" style="background:#1d6f42;color:#fff" target="_blank">📊 Excel</a>
  <a href="<?= BASE_URL ?>/api/export.php?type=expenses&format=pdf" class="button button-secondary button-sm" style="background:#c00200;color:#fff" target="_blank">🖨 PDF</a>
</div>
<?php if (empty($expByCategory)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">💸</div>
  <p style="color:var(--ink-soft)">No approved expense records for <?= e($ay) ?>.</p>
  <a href="<?= BASE_URL ?>/admin/expenses.php" class="button button-secondary" style="margin-top:14px">Manage Expenses</a>
</div>
<?php else:
  $maxExpCat = max(array_column($expByCategory,'total') ?: [1]);
?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="fin-grid">
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">💸 Expenses by Category</h3>
    <?php foreach ($expByCategory as $e): $w = round($e['total']/$maxExpCat*100); ?>
    <div style="margin-bottom:9px">
      <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:2px">
        <span><?= e($e['category']) ?> <span style="color:var(--ink-soft)">(<?= $e['cnt'] ?>)</span></span>
        <strong>LRD <?= number_format($e['total']) ?></strong>
      </div>
      <div style="height:7px;background:var(--bg2);border-radius:4px;overflow:hidden">
        <div style="width:<?= $w ?>%;height:100%;background:var(--error);border-radius:4px"></div>
      </div>
    </div>
    <?php endforeach; ?>
    <div style="margin-top:14px;padding-top:10px;border-top:1.5px solid var(--line);display:flex;justify-content:space-between;font-size:13px;font-weight:700">
      <span>Total Approved</span><span>LRD <?= number_format($approvedExpenses) ?></span>
    </div>
  </div>
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">📋 Expense Status</h3>
    <?php
    try {
        $expStatus = $pdo->prepare("SELECT status,COUNT(*) cnt,SUM(amount) total FROM expense_requests WHERE academic_year_id=? GROUP BY status ORDER BY cnt DESC");
        $expStatus->execute([$ayId]); $expStatus = $expStatus->fetchAll();
    } catch (Throwable $e) { $expStatus=[]; }
    $statusClr=['pending'=>'pending','approved'=>'new-s','paid'=>'approved','rejected'=>'warning'];
    foreach ($expStatus as $es):
    ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--line-soft);font-size:13px">
      <span class="status <?= $statusClr[$es['status']]??'new-s' ?>" style="font-size:11px"><?= ucfirst($es['status']) ?></span>
      <div style="text-align:right">
        <strong><?= $es['cnt'] ?></strong>
        <div style="font-size:11px;color:var(--ink-soft)">LRD <?= number_format($es['total']) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
    <a href="<?= BASE_URL ?>/admin/expenses.php" class="lnk" style="font-size:12px;margin-top:12px">Manage expenses →</a>
  </div>
</div>
<?php endif; ?>

<?php elseif ($tab === 'income_vs'): ?>
<!-- ── INCOME VS EXPENSES ─────────────────────────────────── -->
<div class="panel" style="padding:24px;margin-bottom:20px">
  <h3 style="font-weight:700;font-size:14px;margin-bottom:18px">📈 Income vs Expenses — <?= e($ay) ?></h3>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:20px">
    <div style="text-align:center;padding:18px;background:var(--green-soft);border-radius:var(--radius-sm)">
      <div style="font-size:2rem;font-weight:800;color:var(--green)">LRD <?= number_format($totalLRD/1000,1) ?>K</div>
      <div style="font-size:12px;color:var(--green);margin-top:4px;font-weight:600">Total Income</div>
    </div>
    <div style="text-align:center;padding:18px;background:var(--error-soft, #fff1f2);border-radius:var(--radius-sm)">
      <div style="font-size:2rem;font-weight:800;color:var(--error)">LRD <?= number_format($paidExpenses/1000,1) ?>K</div>
      <div style="font-size:12px;color:var(--error);margin-top:4px;font-weight:600">Paid Expenses</div>
    </div>
    <div style="text-align:center;padding:18px;background:<?= $balance>=0?'var(--green-soft)':'#fff1f2' ?>;border-radius:var(--radius-sm)">
      <div style="font-size:2rem;font-weight:800;color:<?= $balance>=0?'var(--green)':'var(--error)' ?>">LRD <?= number_format(abs($balance)/1000,1) ?>K</div>
      <div style="font-size:12px;color:<?= $balance>=0?'var(--green)':'var(--error)' ?>;margin-top:4px;font-weight:600"><?= $balance>=0?'Surplus':'Deficit' ?></div>
    </div>
  </div>
  <!-- Monthly trend -->
  <h4 style="font-size:13px;font-weight:700;margin-bottom:12px">Monthly Income Trend</h4>
  <?php if (!empty($monthlyIncome)):
    $maxM = max(array_column($monthlyIncome,'lrd') ?: [1]);
  ?>
  <div style="display:flex;align-items:flex-end;gap:8px;height:80px;margin-bottom:10px">
    <?php foreach ($monthlyIncome as $m):
      $h = $maxM > 0 ? max(6, round($m['lrd']/$maxM*72)) : 6;
    ?>
    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px">
      <span style="font-size:8px;color:var(--ink-soft)"><?= number_format($m['lrd']/1000,0) ?>K</span>
      <div style="width:100%;height:<?= $h ?>px;background:var(--green);border-radius:3px 3px 0 0;opacity:.85"></div>
      <span style="font-size:8px;color:var(--ink-soft)"><?= date('M', strtotime($m['mon'].'-01')) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <p style="color:var(--ink-faint);font-size:13px">No income data yet for <?= e($ay) ?>.</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<style>@media(max-width:640px){.fin-grid{grid-template-columns:1fr !important}}</style>
<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
