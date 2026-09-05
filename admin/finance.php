<?php
$pageTitle   = 'Finance';
$activeAdmin = 'finance';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['sys_admin','school_admin','principal','accountant']);

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();

$canRecord  = can('record_payment');         // accountant, school_admin, principal, sys_admin
$canManage  = can('manage_fees');            // same
$canReport  = can('view_financial_reports'); // same

// ── Record payment ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action = $_POST['action']??'';
    if ($action === 'add_payment' && $canRecord) {
        $sid    = (int)($_POST['student_id']??0);
        $amount = (float)($_POST['amount']??0);
        $cur    = $_POST['currency']??'LRD';
        $method = $_POST['payment_method']??'Cash';
        $date   = $_POST['payment_date']??date('Y-m-d');
        $feeId  = ($_POST['fee_structure_id']??null) ?: null;
        $notes  = trim($_POST['notes']??'');
        if ($sid && $amount > 0) {
            $rec = generateReceiptNumber();
            $pdo->prepare("INSERT INTO payments (receipt_number,student_id,fee_structure_id,amount,currency,payment_method,payment_date,academic_year_id,notes,recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
               ->execute([$rec,$sid,$feeId,$amount,$cur,$method,$date,$ayId,$notes?:null,currentUser()['id']]);
            auditLog('create','finance','payment',(int)$pdo->lastInsertId(),'','Receipt: '.$rec);
            flash('success','Payment recorded. Receipt: '.$rec);
        }
    }
    redirect(BASE_URL.'/admin/finance.php');
}

// Metrics
$totalLRD  = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='LRD' AND academic_year_id=$ayId")->fetchColumn();
$totalUSD  = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='USD' AND academic_year_id=$ayId")->fetchColumn();
$payCount  = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE academic_year_id=$ayId")->fetchColumn();
$paidStd   = (int)$pdo->query("SELECT COUNT(DISTINCT student_id) FROM payments WHERE academic_year_id=$ayId")->fetchColumn();
$totalStd  = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status='Active'")->fetchColumn();
$unpaid    = max(0,$totalStd-$paidStd);

// Search & paginate
$q     = trim($_GET['q']??'');
$page  = max(1,(int)($_GET['page']??1));
$per   = 20;
$wsql  = $q ? "WHERE p.receipt_number LIKE ? OR CONCAT(s.first_name,' ',s.last_name) LIKE ?" : '';
$params= $q ? ["%$q%","%$q%"] : [];
$cnt   = $pdo->prepare("SELECT COUNT(*) FROM payments p JOIN students s ON s.id=p.student_id $wsql"); $cnt->execute($params); $total=(int)$cnt->fetchColumn();
$pg    = paginate($total,$per,$page);
$rows  = $pdo->prepare("SELECT p.*,CONCAT(s.first_name,' ',s.last_name) sname,s.student_id sid,g.name grade_name FROM payments p JOIN students s ON s.id=p.student_id LEFT JOIN grades g ON g.id=s.current_grade_id $wsql ORDER BY p.created_at DESC LIMIT $per OFFSET {$pg['offset']}");
$rows->execute($params); $payments=$rows->fetchAll();

$feeStructures = $pdo->prepare("SELECT id,fee_type,amount,currency FROM fee_structures WHERE academic_year_id=? AND is_active=1 ORDER BY fee_type")->execute([$ayId]) ? $pdo->query("SELECT id,fee_type,amount,currency FROM fee_structures WHERE academic_year_id=$ayId AND is_active=1 ORDER BY fee_type")->fetchAll() : [];
$allStudents   = $pdo->query("SELECT id,student_id,CONCAT(first_name,' ',last_name) name FROM students WHERE status='Active' ORDER BY first_name")->fetchAll();
?>

<div class="page-heading">
  <div><div class="eyebrow">Bursar Office <span></span></div><h1>Finance</h1><p><?= e($ay) ?></p></div>
  <?php if ($canRecord): ?><button class="button button-primary" onclick="document.getElementById('addPayModal').style.display='flex'">+ Record Payment</button><?php endif; ?></div>

<div class="metric-grid finance-metrics">
  <div class="metric-card"><div class="metric-top"><span>Collected (LRD)</span><div class="metric-icon">💵</div></div><strong>LRD <?= number_format($totalLRD) ?></strong><small><i></i>Academic year</small></div>
  <div class="metric-card"><div class="metric-top"><span>Collected (USD)</span><div class="metric-icon">💲</div></div><strong>USD <?= number_format($totalUSD,2) ?></strong><small><i></i>Academic year</small></div>
  <div class="metric-card"><div class="metric-top"><span>Payments Received</span><div class="metric-icon">✅</div></div><strong><?= number_format($payCount) ?></strong><small><i></i>Total transactions</small></div>
  <div class="metric-card"><div class="metric-top"><span>Students Yet to Pay</span><div class="metric-icon">⏳</div></div><strong><?= number_format($unpaid) ?></strong><small><i></i>Of <?= $totalStd ?> active</small></div>
</div>

<!-- Progress bar -->
<?php $targetLRD=2920000; $pct=min(100,round(($totalLRD/$targetLRD)*100)); ?>
<div class="panel" style="padding:20px 22px;margin-bottom:20px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
    <strong>Collection target: LRD <?= number_format($targetLRD) ?></strong>
    <span class="status approved"><?= $pct ?>% collected</span>
  </div>
  <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%"></div></div>
</div>

<div class="list-content">
  <form method="get" class="filter-row">
    <div class="table-search">🔍<input type="search" name="q" placeholder="Receipt, student name…" value="<?= e($q) ?>"/></div>
    <button type="submit" class="button button-primary button-sm">Search</button>
    <?php if ($q): ?><a href="<?= BASE_URL ?>/admin/finance.php" class="filter-button">Clear</a><?php endif; ?>
    <a href="<?= BASE_URL ?>/admin/fee_structures.php" class="filter-button">⚙️ Fee Structures</a>
    <a href="<?= BASE_URL ?>/admin/student_statements.php" class="filter-button">📊 Statements</a>
  </form>

  <div class="table-wrap">
    <table>
      <thead><tr><th>Receipt</th><th>Student</th><th>Grade</th><th>Amount</th><th>Method</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
        <?php if (empty($payments)): ?>
        <tr><td colspan="7" style="text-align:center;padding:36px;color:var(--ink-faint)">No payments found.</td></tr>
        <?php else: ?>
        <?php foreach ($payments as $p): ?>
        <tr>
          <td class="muted"><?= e($p['receipt_number']) ?></td>
          <td><strong><?= e($p['sname']) ?></strong><div style="font-size:11px;color:var(--ink-faint)"><?= e($p['sid']) ?></div></td>
          <td class="muted"><?= e($p['grade_name']??'—') ?></td>
          <td><strong><?= e($p['currency']) ?> <?= number_format($p['amount'],2) ?></strong></td>
          <td><?= e($p['payment_method']) ?></td>
          <td class="muted"><?= date('M d, Y',strtotime($p['payment_date'])) ?></td>
          <td><a href="<?= BASE_URL ?>/letters/receipt_pdf.php?payment_id=<?= $p['id'] ?>" class="filter-button button-sm" target="_blank">📄 Receipt</a></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pg['pages']>1): ?>
  <div class="pagination">
    <?php if ($pg['page']>1): ?><a href="?page=<?=$pg['page']-1?>&q=<?=urlencode($q)?>">&laquo;</a><?php endif; ?>
    <?php for($p2=max(1,$pg['page']-2);$p2<=min($pg['pages'],$pg['page']+2);$p2++): ?>
      <?php if($p2===$pg['page']): ?><span class="current"><?=$p2?></span><?php else: ?><a href="?page=<?=$p2?>&q=<?=urlencode($q)?>"><?=$p2?></a><?php endif; ?>
    <?php endfor; ?>
    <?php if ($pg['page']<$pg['pages']): ?><a href="?page=<?=$pg['page']+1?>&q=<?=urlencode($q)?>">&raquo;</a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Record Payment Modal -->
<div id="addPayModal" style="display:none;position:fixed;inset:0;background:rgba(26,26,31,.5);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:var(--surface);border-radius:var(--radius-lg);width:100%;max-width:520px;box-shadow:var(--shadow-lg);padding:28px">
    <h3 style="margin-bottom:18px">Record Payment</h3>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="add_payment"/>
      <div class="form-row full"><div class="form-group"><label>Student *<select name="student_id" required><option value="">Select student…</option><?php foreach($allStudents as $st): ?><option value="<?= $st['id'] ?>"><?= e($st['name'].' ('.$st['student_id'].')') ?></option><?php endforeach; ?></select></label></div></div>
      <div class="form-row full"><div class="form-group"><label>Fee Type<select name="fee_structure_id"><option value="">Other / Manual</option><?php foreach($feeStructures as $f): ?><option value="<?= $f['id'] ?>"><?= e($f['fee_type'].' — '.$f['currency'].' '.number_format($f['amount'])) ?></option><?php endforeach; ?></select></label></div></div>
      <div class="form-row">
        <div class="form-group"><label>Amount *<input type="number" name="amount" required min="0.01" step="0.01" placeholder="0.00"/></label></div>
        <div class="form-group"><label>Currency<select name="currency"><option value="LRD">LRD</option><option value="USD">USD</option></select></label></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Method<select name="payment_method"><option>Cash</option><option>Mobile money</option><option>Bank transfer</option><option>Cheque</option></select></label></div>
        <div class="form-group"><label>Date *<input type="date" name="payment_date" required value="<?= date('Y-m-d') ?>"/></label></div>
      </div>
      <div class="form-row full"><div class="form-group"><label>Notes<input name="notes" placeholder="Optional note…"/></label></div></div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px">
        <button type="button" class="button button-secondary" onclick="document.getElementById('addPayModal').style.display='none'">Cancel</button>
        <button type="submit" class="button button-primary">Save Payment →</button>
      </div>
    </form>
  </div>
</div>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
