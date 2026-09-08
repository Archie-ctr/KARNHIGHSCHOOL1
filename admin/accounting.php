<?php
$pageTitle   = 'Accounting';
$activeAdmin = 'accounting';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['sys_admin','super_admin','school_admin','principal','accountant']);

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();
$tab  = $_GET['tab'] ?? 'cashbook';
$today = date('Y-m-d');

// ── Ensure cashbook / reconciliation tables ───────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cashbook_entries (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        entry_date      DATE         NOT NULL,
        entry_type      ENUM('income','expense') NOT NULL,
        category        VARCHAR(100) NOT NULL,
        description     VARCHAR(255) NOT NULL,
        reference       VARCHAR(80)  NULL,
        amount          DECIMAL(12,2) NOT NULL,
        currency        VARCHAR(5)   NOT NULL DEFAULT 'LRD',
        payment_method  ENUM('Cash','Bank','Mobile Money','Cheque','Other') NOT NULL DEFAULT 'Cash',
        bank_name       VARCHAR(100) NULL,
        reconciled      TINYINT(1)   NOT NULL DEFAULT 0,
        recorded_by     INT UNSIGNED NOT NULL,
        academic_year_id INT UNSIGNED NOT NULL,
        created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_cb_date (entry_date),
        INDEX idx_cb_ay   (academic_year_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

// ── POST: manual cashbook entry ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_entry') {
        $date   = $_POST['entry_date']    ?? $today;
        $type   = $_POST['entry_type']    ?? 'income';
        $cat    = trim($_POST['category'] ?? '');
        $desc   = trim($_POST['description'] ?? '');
        $ref    = trim($_POST['reference']   ?? '') ?: null;
        $amt    = (float)($_POST['amount']   ?? 0);
        $cur    = $_POST['currency']         ?? 'LRD';
        $method = $_POST['payment_method']   ?? 'Cash';
        $bank   = trim($_POST['bank_name']   ?? '') ?: null;
        if ($cat && $desc && $amt > 0) {
            $pdo->prepare(
                "INSERT INTO cashbook_entries (entry_date,entry_type,category,description,reference,amount,currency,payment_method,bank_name,recorded_by,academic_year_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)"
            )->execute([$date,$type,$cat,$desc,$ref,$amt,$cur,$method,$bank,currentUserId(),$ayId]);
            auditLog('create','accounting','cashbook_entry',(int)$pdo->lastInsertId(),'','Manual entry: '.$desc);
            flash('success','Cashbook entry added.');
        }

    } elseif ($action === 'reconcile') {
        $ids = $_POST['entry_ids'] ?? [];
        if (!empty($ids)) {
            $in = implode(',', array_map('intval', $ids));
            $pdo->exec("UPDATE cashbook_entries SET reconciled=1 WHERE id IN ($in)");
            $pdo->exec("UPDATE payments SET reconciled=1 WHERE id IN ($in)");
            auditLog('reconcile','accounting','cashbook',0,'','Reconciled '.count($ids).' entries');
            flash('success', count($ids).' entries marked as reconciled.');
        }
    }
    redirect(BASE_URL.'/admin/accounting.php?tab='.urlencode($tab));
}

// ── Data ──────────────────────────────────────────────────────

// Daily collection (today)
$todayIncomeLRD = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='LRD' AND DATE(payment_date)=CURDATE()")->fetchColumn();
$todayIncomeUSD = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='USD' AND DATE(payment_date)=CURDATE()")->fetchColumn();
$todayTxCount   = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE DATE(payment_date)=CURDATE()")->fetchColumn();

// Year totals
$yearLRD = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='LRD' AND academic_year_id=$ayId")->fetchColumn();
$yearUSD = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='USD' AND academic_year_id=$ayId")->fetchColumn();

// Expense totals (from expense_requests)
try {
    $yearExpenses = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM expense_requests WHERE status IN ('approved','paid') AND currency='LRD' AND academic_year_id=$ayId")->fetchColumn();
    $paidExpenses = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM expense_requests WHERE status='paid' AND currency='LRD' AND academic_year_id=$ayId")->fetchColumn();
} catch (Throwable $e) { $yearExpenses = 0; $paidExpenses = 0; }

// Cashbook entries
$cbDateF = $_GET['cb_date'] ?? '';
$cbType  = $_GET['cb_type'] ?? '';
$cbWhere = "WHERE academic_year_id=$ayId";
if ($cbDateF) $cbWhere .= " AND entry_date='".addslashes($cbDateF)."'";
if ($cbType)  $cbWhere .= " AND entry_type='".addslashes($cbType)."'";
try {
    $cbPage = max(1,(int)($_GET['cb_page']??1)); $cbPer = 25;
    $cbTotal= (int)$pdo->query("SELECT COUNT(*) FROM cashbook_entries $cbWhere")->fetchColumn();
    $cbPg   = paginate($cbTotal, $cbPer, $cbPage);
    $cbEntries = $pdo->query(
        "SELECT ce.*, u.name recorder_name FROM cashbook_entries ce
         LEFT JOIN users u ON u.id=ce.recorded_by
         $cbWhere ORDER BY ce.entry_date DESC, ce.created_at DESC
         LIMIT $cbPer OFFSET {$cbPg['offset']}"
    )->fetchAll();
} catch (Throwable $e) { $cbEntries=[]; $cbTotal=0; $cbPg=['pages'=>0,'page'=>1]; }

// Monthly breakdown this year (from payments table)
$monthlyIncome = $pdo->prepare(
    "SELECT DATE_FORMAT(payment_date,'%Y-%m') mon,
            SUM(CASE WHEN currency='LRD' THEN amount ELSE 0 END) lrd,
            SUM(CASE WHEN currency='USD' THEN amount ELSE 0 END) usd,
            COUNT(*) txns
     FROM payments WHERE academic_year_id=?
     GROUP BY DATE_FORMAT(payment_date,'%Y-%m') ORDER BY mon"
);
$monthlyIncome->execute([$ayId]);
$monthlyIncome = $monthlyIncome->fetchAll();

// Payment method breakdown
$byMethod = $pdo->prepare(
    "SELECT payment_method,
            SUM(CASE WHEN currency='LRD' THEN amount ELSE 0 END) lrd,
            COUNT(*) cnt
     FROM payments WHERE academic_year_id=?
     GROUP BY payment_method ORDER BY lrd DESC"
);
$byMethod->execute([$ayId]);
$byMethod = $byMethod->fetchAll();

// Unreconciled payments today
try {
    $unreconciled = $pdo->query(
        "SELECT COUNT(*) FROM payments WHERE DATE(payment_date)=CURDATE() AND (reconciled=0 OR reconciled IS NULL)"
    )->fetchColumn();
} catch (Throwable $e) { $unreconciled = 0; }

$balance = $yearLRD - $paidExpenses;
$categories = ['Tuition','Registration','Examination','Sports','Library','Computer','Transportation','Scholarship','Waiver','Other'];
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">Finance <span></span></div>
    <h1>Accounting</h1>
    <p><?= e($ay) ?> &mdash; <?= date('F d, Y') ?></p>
  </div>
  <a href="<?= BASE_URL ?>/admin/finance.php" class="button button-secondary">💳 Record Payment</a>
</div>

<!-- Summary metrics -->
<div class="metric-grid finance-metrics" style="margin-bottom:24px">
  <div class="metric-card">
    <div class="metric-top"><span>Today's Collection (LRD)</span><div class="metric-icon">📅</div></div>
    <strong>LRD <?= number_format($todayIncomeLRD) ?></strong>
    <small><i></i><?= $todayTxCount ?> transaction<?= $todayTxCount!==1?'s':'' ?></small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Year Income (LRD)</span><div class="metric-icon">💵</div></div>
    <strong>LRD <?= number_format($yearLRD) ?></strong>
    <small><i></i>USD <?= number_format($yearUSD,2) ?></small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Year Expenses (LRD)</span><div class="metric-icon">💸</div></div>
    <strong>LRD <?= number_format($paidExpenses) ?></strong>
    <small><i></i>LRD <?= number_format($yearExpenses) ?> approved</small>
  </div>
  <div class="metric-card <?= $balance < 0 ? 'finance-metrics' : '' ?>">
    <div class="metric-top"><span>Net Balance (LRD)</span><div class="metric-icon">⚖️</div></div>
    <strong style="color:<?= $balance >= 0 ? 'var(--green)' : 'var(--error)' ?>">LRD <?= number_format(abs($balance)) ?></strong>
    <small><i></i><?= $balance >= 0 ? 'Surplus' : 'Deficit' ?></small>
  </div>
</div>

<!-- Tabs -->
<div class="tab-bar" style="margin-bottom:16px">
  <a href="?tab=cashbook"      class="tab-btn <?= $tab==='cashbook'      ?'active':'' ?>">📒 Cashbook</a>
  <a href="?tab=reconciliation" class="tab-btn <?= $tab==='reconciliation'?'active':'' ?>">🔄 Reconciliation <?= $unreconciled>0?"({$unreconciled})":'' ?></a>
  <a href="?tab=monthly"       class="tab-btn <?= $tab==='monthly'       ?'active':'' ?>">📅 Monthly</a>
  <a href="?tab=methods"       class="tab-btn <?= $tab==='methods'       ?'active':'' ?>">💳 By Method</a>
  <a href="?tab=add_entry"     class="tab-btn <?= $tab==='add_entry'     ?'active':'' ?>">➕ Add Entry</a>
</div>

<?php if ($tab === 'cashbook'): ?>
<!-- ── CASHBOOK ───────────────────────────────────────────── -->
<form method="get" class="filter-row" style="margin-bottom:12px">
  <input type="hidden" name="tab" value="cashbook"/>
  <label style="font-size:13px;font-weight:600">Date:
    <input type="date" name="cb_date" value="<?= e($cbDateF) ?>"
           style="margin-left:6px;padding:8px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit"/>
  </label>
  <select name="cb_type" class="filter-button" onchange="this.form.submit()">
    <option value="">All types</option>
    <option value="income"  <?= $cbType==='income' ?'selected':'' ?>>Income</option>
    <option value="expense" <?= $cbType==='expense'?'selected':'' ?>>Expense</option>
  </select>
  <button class="button button-secondary button-sm">Filter</button>
  <?php if ($cbDateF || $cbType): ?><a href="?tab=cashbook" class="filter-button">Clear</a><?php endif; ?>
</form>

<!-- Today's auto-entries from payments -->
<?php if (!$cbDateF && !$cbType):
    $todayPayments = $pdo->query(
        "SELECT p.receipt_number,CONCAT(s.first_name,' ',s.last_name) sname,p.amount,p.currency,p.payment_method,p.payment_date
         FROM payments p JOIN students s ON s.id=p.student_id
         WHERE DATE(p.payment_date)=CURDATE() ORDER BY p.created_at DESC LIMIT 15"
    )->fetchAll();
    if (!empty($todayPayments)): ?>
<div class="panel" style="padding:0;overflow:hidden;margin-bottom:16px">
  <div style="padding:12px 18px;background:var(--green-soft);border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center">
    <strong style="font-size:13px;color:var(--green)">📅 Today's Payments — LRD <?= number_format($todayIncomeLRD) ?> collected</strong>
    <span class="muted" style="font-size:12px"><?= count($todayPayments) ?> transactions</span>
  </div>
  <div class="table-wrap" style="border:none">
    <table>
      <thead><tr><th>Receipt</th><th>Student</th><th>Amount</th><th>Method</th><th>Time</th></tr></thead>
      <tbody>
        <?php foreach ($todayPayments as $p): ?>
        <tr>
          <td class="muted"><?= e($p['receipt_number']) ?></td>
          <td><strong><?= e($p['sname']) ?></strong></td>
          <td><strong><?= e($p['currency']) ?> <?= number_format($p['amount'],2) ?></strong></td>
          <td><?= e($p['payment_method']) ?></td>
          <td class="muted"><?= date('H:i', strtotime($p['payment_date'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; endif; ?>

<!-- Manual entries -->
<?php if (empty($cbEntries) && !$cbDateF && !$cbType): ?>
<div style="text-align:center;padding:32px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <p style="color:var(--ink-soft)">No manual cashbook entries yet. Use "Add Entry" to record expenses and other income.</p>
</div>
<?php elseif (!empty($cbEntries)): ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Date</th><th>Type</th><th>Category</th><th>Description</th><th>Ref</th><th>Amount</th><th>Method</th><th>Reconciled</th></tr></thead>
    <tbody>
      <?php foreach ($cbEntries as $e): ?>
      <tr>
        <td class="muted"><?= date('M d, Y', strtotime($e['entry_date'])) ?></td>
        <td><span class="status <?= $e['entry_type']==='income'?'approved':'warning' ?>"><?= ucfirst($e['entry_type']) ?></span></td>
        <td><?= e($e['category']) ?></td>
        <td><?= e($e['description']) ?></td>
        <td class="muted"><?= e($e['reference']??'—') ?></td>
        <td><strong style="color:<?= $e['entry_type']==='income'?'var(--green)':'var(--error)' ?>"><?= $e['entry_type']==='expense'?'-':'+' ?><?= e($e['currency']) ?> <?= number_format($e['amount'],2) ?></strong></td>
        <td class="muted"><?= e($e['payment_method']) ?></td>
        <td><?= $e['reconciled'] ? '<span class="status approved">✓</span>' : '<span class="status pending">Pending</span>' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php if ($cbPg['pages'] > 1): ?>
<div class="pagination">
  <?php if($cbPg['page']>1):?><a href="?tab=cashbook&cb_page=<?=$cbPg['page']-1?>&cb_date=<?=urlencode($cbDateF)?>&cb_type=<?=urlencode($cbType)?>">&laquo;</a><?php endif; ?>
  <?php for($p=max(1,$cbPg['page']-2);$p<=min($cbPg['pages'],$cbPg['page']+2);$p++): ?>
    <?php if($p===$cbPg['page']):?><span class="current"><?=$p?></span><?php else:?><a href="?tab=cashbook&cb_page=<?=$p?>&cb_date=<?=urlencode($cbDateF)?>&cb_type=<?=urlencode($cbType)?>"><?=$p?></a><?php endif; ?>
  <?php endfor; ?>
  <?php if($cbPg['page']<$cbPg['pages']):?><a href="?tab=cashbook&cb_page=<?=$cbPg['page']+1?>&cb_date=<?=urlencode($cbDateF)?>&cb_type=<?=urlencode($cbType)?>">&raquo;</a><?php endif; ?>
</div>
<?php endif; endif; ?>

<?php elseif ($tab === 'reconciliation'): ?>
<!-- ── RECONCILIATION ─────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px" class="acc-grid">
  <!-- Today's unreconciled payments -->
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">💳 Cash / Bank Reconciliation — Today</h3>
    <?php
    try {
        $unreconciledPays = $pdo->query(
            "SELECT p.id, p.receipt_number, CONCAT(s.first_name,' ',s.last_name) sname,
                    p.amount, p.currency, p.payment_method, p.payment_date
             FROM payments p JOIN students s ON s.id=p.student_id
             WHERE DATE(p.payment_date)=CURDATE()
               AND (p.reconciled=0 OR p.reconciled IS NULL)
             ORDER BY p.payment_method, p.created_at DESC LIMIT 30"
        )->fetchAll();
    } catch (Throwable $e) { $unreconciledPays=[]; }
    ?>
    <?php if (empty($unreconciledPays)): ?>
    <div style="text-align:center;padding:24px"><span style="font-size:2rem">✅</span><p style="color:var(--ink-soft);font-size:13px;margin-top:8px">All today's payments reconciled.</p></div>
    <?php else: ?>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="reconcile"/>
      <input type="hidden" name="tab" value="reconciliation"/>
      <div class="table-wrap" style="border:none;margin-bottom:12px">
        <table>
          <thead><tr><th><input type="checkbox" id="selAll" onchange="document.querySelectorAll('.rec-chk').forEach(c=>c.checked=this.checked)"/></th><th>Receipt</th><th>Student</th><th>Amount</th><th>Method</th></tr></thead>
          <tbody>
            <?php foreach ($unreconciledPays as $p): ?>
            <tr>
              <td><input type="checkbox" name="entry_ids[]" value="<?= $p['id'] ?>" class="rec-chk"/></td>
              <td class="muted"><?= e($p['receipt_number']) ?></td>
              <td><strong><?= e($p['sname']) ?></strong></td>
              <td><strong><?= e($p['currency']) ?> <?= number_format($p['amount'],2) ?></strong></td>
              <td><?= e($p['payment_method']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <button type="submit" class="button button-primary button-sm">✓ Mark Selected as Reconciled</button>
    </form>
    <?php endif; ?>
  </div>

  <!-- Reconciliation summary by method -->
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">📊 By Payment Method — <?= e($ay) ?></h3>
    <?php foreach ($byMethod as $m): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--line-soft);font-size:13px">
      <div>
        <strong><?= e($m['payment_method']) ?></strong>
        <span style="font-size:11px;color:var(--ink-soft);margin-left:6px"><?= $m['cnt'] ?> tx</span>
      </div>
      <strong>LRD <?= number_format($m['lrd']) ?></strong>
    </div>
    <?php endforeach; ?>
    <?php if(empty($byMethod)):?><p style="color:var(--ink-faint);font-size:13px">No payments yet.</p><?php endif; ?>
    <div style="margin-top:14px;padding-top:10px;border-top:1.5px solid var(--line);display:flex;justify-content:space-between;font-size:13px;font-weight:700">
      <span>Total LRD</span><span>LRD <?= number_format($yearLRD) ?></span>
    </div>
  </div>
</div>

<?php elseif ($tab === 'monthly'): ?>
<!-- ── MONTHLY BREAKDOWN ───────────────────────────────────── -->
<?php if (empty($monthlyIncome)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <p style="color:var(--ink-soft)">No payment data for <?= e($ay) ?>.</p>
</div>
<?php else:
  $maxMonthly = max(array_column($monthlyIncome,'lrd') ?: [1]); ?>
<div class="panel" style="padding:22px;margin-bottom:20px">
  <h3 style="font-weight:700;font-size:14px;margin-bottom:20px">📅 Monthly Collections — <?= e($ay) ?></h3>
  <!-- Bar chart -->
  <div style="display:flex;align-items:flex-end;gap:10px;height:100px;margin-bottom:16px">
    <?php foreach ($monthlyIncome as $m):
      $h = $maxMonthly > 0 ? max(8, round($m['lrd'] / $maxMonthly * 88)) : 8;
    ?>
    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:3px">
      <span style="font-size:9px;font-weight:700;color:var(--green)"><?= number_format($m['lrd']/1000,0) ?>K</span>
      <div style="width:100%;height:<?= $h ?>px;background:var(--green);border-radius:3px 3px 0 0;opacity:.85"></div>
      <span style="font-size:9px;color:var(--ink-soft);white-space:nowrap"><?= date('M Y', strtotime($m['mon'].'-01')) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <!-- Table -->
  <div class="table-wrap" style="border:none">
    <table>
      <thead><tr><th>Month</th><th>LRD Collected</th><th>USD Collected</th><th>Transactions</th></tr></thead>
      <tbody>
        <?php foreach (array_reverse($monthlyIncome) as $m): ?>
        <tr>
          <td><strong><?= date('F Y', strtotime($m['mon'].'-01')) ?></strong></td>
          <td><strong style="color:var(--green)">LRD <?= number_format($m['lrd']) ?></strong></td>
          <td class="muted">USD <?= number_format($m['usd'],2) ?></td>
          <td class="muted"><?= $m['txns'] ?></td>
        </tr>
        <?php endforeach; ?>
        <tr style="background:var(--bg2)">
          <td><strong>Total</strong></td>
          <td><strong>LRD <?= number_format($yearLRD) ?></strong></td>
          <td><strong>USD <?= number_format($yearUSD,2) ?></strong></td>
          <td><strong><?= array_sum(array_column($monthlyIncome,'txns')) ?></strong></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php elseif ($tab === 'methods'): ?>
<!-- ── BY PAYMENT METHOD ──────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-bottom:20px">
  <?php
  $methodColors = ['Cash'=>'var(--green)','Bank'=>'var(--primary)','Mobile Money'=>'#e64980','Cheque'=>'var(--warning)','Other'=>'var(--ink-soft)'];
  $methodIcons  = ['Cash'=>'💵','Bank'=>'🏦','Mobile Money'=>'📱','Cheque'=>'📋','Other'=>'💱'];
  foreach ($byMethod as $m):
    $col = $methodColors[$m['payment_method']] ?? 'var(--ink-soft)';
    $ico = $methodIcons[$m['payment_method']] ?? '💰';
  ?>
  <div class="panel" style="padding:22px;border-top:3px solid <?= $col ?>">
    <div style="font-size:1.8rem;margin-bottom:8px"><?= $ico ?></div>
    <h3 style="font-weight:700;font-size:14px;color:<?= $col ?>;margin-bottom:4px"><?= e($m['payment_method']) ?></h3>
    <div style="font-size:1.3rem;font-weight:800">LRD <?= number_format($m['lrd']) ?></div>
    <div style="font-size:12px;color:var(--ink-soft);margin-top:4px"><?= $m['cnt'] ?> transaction<?= $m['cnt']!==1?'s':'' ?></div>
  </div>
  <?php endforeach; ?>
  <?php if(empty($byMethod)):?><div class="panel" style="padding:24px"><p style="color:var(--ink-faint)">No payments yet.</p></div><?php endif; ?>
</div>

<?php elseif ($tab === 'add_entry'): ?>
<!-- ── ADD CASHBOOK ENTRY ─────────────────────────────────── -->
<div class="panel" style="padding:28px;max-width:580px">
  <h3 style="font-weight:700;margin-bottom:16px">📒 Add Cashbook Entry</h3>
  <p style="font-size:13px;color:var(--ink-soft);margin-bottom:18px">
    Use this to record additional income, out-of-pocket expenses, or other transactions not captured through the Payments module.
  </p>
  <form method="post">
    <?= csrfField() ?><input type="hidden" name="action" value="add_entry"/>
    <input type="hidden" name="tab" value="cashbook"/>
    <div class="form-row">
      <div class="form-group"><label>Type *<select name="entry_type" required><option value="income">Income</option><option value="expense">Expense</option></select></label></div>
      <div class="form-group"><label>Date *<input type="date" name="entry_date" required value="<?= $today ?>"/></label></div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Category *
          <select name="category" required>
            <?php foreach ($categories as $c): ?><option value="<?= $c ?>"><?= $c ?></option><?php endforeach; ?>
          </select>
        </label>
      </div>
      <div class="form-group"><label>Reference<input name="reference" placeholder="Invoice #, receipt # etc."/></label></div>
    </div>
    <div class="form-group"><label>Description *<input name="description" required placeholder="Brief description of this entry"/></label></div>
    <div class="form-row">
      <div class="form-group"><label>Amount *<input type="number" name="amount" required min="0.01" step="0.01" placeholder="0.00"/></label></div>
      <div class="form-group"><label>Currency<select name="currency"><option value="LRD">LRD</option><option value="USD">USD</option></select></label></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Payment Method<select name="payment_method"><option>Cash</option><option>Bank</option><option>Mobile Money</option><option>Cheque</option><option>Other</option></select></label></div>
      <div class="form-group"><label>Bank Name<input name="bank_name" placeholder="Optional"/></label></div>
    </div>
    <button type="submit" class="button button-primary">Add Cashbook Entry</button>
  </form>
</div>
<?php endif; ?>

<style>@media(max-width:640px){.acc-grid{grid-template-columns:1fr !important}}</style>
<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
