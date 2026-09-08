<?php
$pageTitle   = 'Expenses & Budget';
$activeAdmin = 'expenses';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['sys_admin','super_admin','school_admin','principal','accountant']);

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();
$canApprove   = isPrincipal();
$canRecord    = can('finance.create_payment') || isSchoolAdmin();
$canManageFee = can('finance.manage_fees');
$userId       = currentUserId();
$userName     = currentUser()['name'] ?? 'User';

// ── Ensure tables exist ───────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS expense_requests (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title           VARCHAR(200) NOT NULL,
        category        VARCHAR(60)  NOT NULL DEFAULT 'General',
        amount          DECIMAL(12,2) NOT NULL,
        currency        VARCHAR(5)   NOT NULL DEFAULT 'LRD',
        description     TEXT         NULL,
        requested_by    INT UNSIGNED NOT NULL,
        status          ENUM('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending',
        approved_by     INT UNSIGNED NULL,
        approved_at     DATETIME     NULL,
        rejection_reason TEXT        NULL,
        academic_year_id INT UNSIGNED NOT NULL,
        receipt_path    VARCHAR(255) NULL,
        created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_exp_ay (academic_year_id),
        INDEX idx_exp_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS fee_waivers (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        student_id      INT UNSIGNED NOT NULL,
        academic_year_id INT UNSIGNED NOT NULL,
        amount          DECIMAL(12,2) NOT NULL,
        currency        VARCHAR(5)   NOT NULL DEFAULT 'LRD',
        reason          TEXT         NOT NULL,
        requested_by    INT UNSIGNED NOT NULL,
        approved_by     INT UNSIGNED NULL,
        status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        approved_at     DATETIME     NULL,
        created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_fw_student (student_id),
        INDEX idx_fw_ay (academic_year_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

// ── POST actions ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $retTab = $_POST['tab'] ?? 'overview';

    if ($action === 'add_expense') {
        $title = trim($_POST['title'] ?? '');
        $cat   = trim($_POST['category'] ?? 'General');
        $amt   = (float)($_POST['amount'] ?? 0);
        $cur   = $_POST['currency'] ?? 'LRD';
        $desc  = trim($_POST['description'] ?? '');
        if ($title && $amt > 0) {
            $pdo->prepare(
                "INSERT INTO expense_requests (title,category,amount,currency,description,requested_by,academic_year_id)
                 VALUES (?,?,?,?,?,?,?)"
            )->execute([$title,$cat,$amt,$cur,$desc?:null,$userId,$ayId]);
            auditLog('create','expenses','expense_request',(int)$pdo->lastInsertId(),'','Request: '.$title);
            flash('success','Expense request submitted.');
        }

    } elseif ($action === 'approve_expense' && $canApprove) {
        $id = (int)($_POST['exp_id'] ?? 0);
        if ($id) {
            $pdo->prepare(
                "UPDATE expense_requests SET status='approved',approved_by=?,approved_at=NOW() WHERE id=? AND status='pending'"
            )->execute([$userId,$id]);
            auditLog('approve','expenses','expense_request',$id,'pending','approved');
            flash('success','Expense approved.');
        }

    } elseif ($action === 'reject_expense' && $canApprove) {
        $id     = (int)($_POST['exp_id'] ?? 0);
        $reason = trim($_POST['rejection_reason'] ?? '');
        if ($id) {
            $pdo->prepare(
                "UPDATE expense_requests SET status='rejected',approved_by=?,approved_at=NOW(),rejection_reason=? WHERE id=? AND status='pending'"
            )->execute([$userId,$reason?:null,$id]);
            auditLog('reject','expenses','expense_request',$id,'pending','rejected');
            flash('warning','Expense rejected.');
        }

    } elseif ($action === 'mark_paid' && ($canApprove || $canRecord)) {
        $id = (int)($_POST['exp_id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE expense_requests SET status='paid' WHERE id=? AND status='approved'")->execute([$id]);
            flash('success','Expense marked as paid.');
        }

    } elseif ($action === 'add_waiver') {
        $sid    = (int)($_POST['student_id'] ?? 0);
        $amt    = (float)($_POST['amount'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        if ($sid && $amt > 0 && $reason) {
            $pdo->prepare(
                "INSERT INTO fee_waivers (student_id,academic_year_id,amount,currency,reason,requested_by)
                 VALUES (?,?,?,'LRD',?,?)"
            )->execute([$sid,$ayId,$amt,$reason,$userId]);
            auditLog('create','expenses','fee_waiver',(int)$pdo->lastInsertId(),'','Waiver for student #'.$sid);
            flash('success','Fee waiver request submitted for principal approval.');
        }

    } elseif ($action === 'approve_waiver' && $canApprove) {
        $id = (int)($_POST['waiver_id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE fee_waivers SET status='approved',approved_by=?,approved_at=NOW() WHERE id=?")->execute([$userId,$id]);
            auditLog('approve','expenses','fee_waiver',$id,'pending','approved');
            flash('success','Fee waiver approved.');
        }

    } elseif ($action === 'reject_waiver' && $canApprove) {
        $id = (int)($_POST['waiver_id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE fee_waivers SET status='rejected',approved_by=?,approved_at=NOW() WHERE id=?")->execute([$userId,$id]);
            flash('warning','Fee waiver rejected.');
        }
    }
    redirect(BASE_URL.'/admin/expenses.php?tab='.$retTab);
}

// ── Data ──────────────────────────────────────────────────────
$tab = $_GET['tab'] ?? 'overview';

// Expense requests
$expenses = $pdo->prepare(
    "SELECT er.*, u.name requester_name, ua.name approver_name
     FROM expense_requests er
     LEFT JOIN users u  ON u.id  = er.requested_by
     LEFT JOIN users ua ON ua.id = er.approved_by
     WHERE er.academic_year_id=?
     ORDER BY er.created_at DESC"
);
$expenses->execute([$ayId]);
$expenses = $expenses->fetchAll();

// Summary stats
$totalRequested = array_sum(array_map(fn($e) => $e['status']!=='rejected' ? (float)$e['amount'] : 0, $expenses));
$totalApproved  = array_sum(array_map(fn($e) => in_array($e['status'],['approved','paid']) ? (float)$e['amount'] : 0, $expenses));
$totalPaid      = array_sum(array_map(fn($e) => $e['status']==='paid' ? (float)$e['amount'] : 0, $expenses));
$pendingCount   = count(array_filter($expenses, fn($e) => $e['status']==='pending'));

// Fee collected (income side)
$totalIncomeLRD = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='LRD' AND academic_year_id=$ayId")->fetchColumn();

// Fee waivers
$waivers = $pdo->prepare(
    "SELECT fw.*, CONCAT(s.first_name,' ',s.last_name) student_name, s.student_id student_code,
            u.name requester_name, ua.name approver_name
     FROM fee_waivers fw
     JOIN students s ON s.id=fw.student_id
     LEFT JOIN users u  ON u.id  = fw.requested_by
     LEFT JOIN users ua ON ua.id = fw.approved_by
     WHERE fw.academic_year_id=?
     ORDER BY fw.created_at DESC"
);
$waivers->execute([$ayId]);
$waivers = $waivers->fetchAll();

$pendingWaivers = count(array_filter($waivers, fn($w) => $w['status']==='pending'));

$allStudents = $pdo->query("SELECT id,student_id,CONCAT(first_name,' ',last_name) name FROM students WHERE status='Active' ORDER BY first_name")->fetchAll();

$categories = ['General','Infrastructure','Equipment','Supplies','Salaries','Events','Travel','Utilities','Maintenance','Other'];
$statusColors = ['pending'=>'pending','approved'=>'new-s','rejected'=>'warning','paid'=>'approved'];
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">Finance <span></span></div>
    <h1>Expenses &amp; Budget</h1>
    <p><?= e($ay) ?></p>
  </div>
  <?php if ($pendingCount > 0 && $canApprove): ?>
  <span class="status warning" style="font-size:14px;padding:8px 16px">
    ⚠️ <?= $pendingCount ?> expense<?= $pendingCount!==1?'s':'' ?> awaiting approval
  </span>
  <?php endif; ?>
</div>

<!-- Overview metrics -->
<div class="metric-grid" style="margin-bottom:24px">
  <div class="metric-card">
    <div class="metric-top"><span>Income (LRD)</span><div class="metric-icon">💵</div></div>
    <strong style="color:var(--green)">LRD <?= number_format($totalIncomeLRD) ?></strong>
    <small><i></i>Fees collected</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Expenses Approved</span><div class="metric-icon">📋</div></div>
    <strong>LRD <?= number_format($totalApproved) ?></strong>
    <small><i></i>Approved this year</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Expenses Paid</span><div class="metric-icon">✅</div></div>
    <strong style="color:var(--green)">LRD <?= number_format($totalPaid) ?></strong>
    <small><i></i>Disbursed</small>
  </div>
  <div class="metric-card <?= $pendingCount>0?'finance-metrics':'' ?>">
    <div class="metric-top"><span>Pending Approval</span><div class="metric-icon">⏳</div></div>
    <strong style="color:<?= $pendingCount>0?'var(--error)':'inherit' ?>"><?= $pendingCount ?></strong>
    <small><i></i><?= $pendingWaivers ?> waiver<?= $pendingWaivers!==1?'s':'' ?> too</small>
  </div>
</div>

<!-- Tabs -->
<div class="tab-bar" style="margin-bottom:16px">
  <a href="?tab=overview"   class="tab-btn <?= $tab==='overview'  ?'active':'' ?>">📊 Overview</a>
  <a href="?tab=expenses"   class="tab-btn <?= $tab==='expenses'  ?'active':'' ?>">📋 Expense Requests (<?= count($expenses) ?>)</a>
  <a href="?tab=waivers"    class="tab-btn <?= $tab==='waivers'   ?'active':'' ?>">💳 Fee Waivers (<?= count($waivers) ?>)</a>
  <?php if ($canRecord || $canApprove): ?>
  <a href="?tab=new"        class="tab-btn <?= $tab==='new'       ?'active':'' ?>">➕ New Request</a>
  <?php endif; ?>
</div>

<?php if ($tab === 'overview'): ?>
<!-- ── OVERVIEW ──────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="exp-grid">
  <!-- Expense by category -->
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">📋 Expenses by Category</h3>
    <?php
    $byCat = [];
    foreach ($expenses as $e) {
        if ($e['status']==='rejected') continue;
        $byCat[$e['category']] = ($byCat[$e['category']] ?? 0) + (float)$e['amount'];
    }
    arsort($byCat);
    $maxCat = max(array_values($byCat) ?: [1]);
    foreach ($byCat as $cat => $amt): $w = round($amt/$maxCat*100); ?>
    <div style="margin-bottom:8px">
      <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:3px">
        <span><?= e($cat) ?></span><strong>LRD <?= number_format($amt) ?></strong>
      </div>
      <div style="height:6px;background:var(--bg2);border-radius:3px;overflow:hidden">
        <div style="width:<?= $w ?>%;height:100%;background:var(--primary);border-radius:3px"></div>
      </div>
    </div>
    <?php endforeach; if(empty($byCat)): ?><p style="color:var(--ink-faint);font-size:13px">No expenses yet.</p><?php endif; ?>
  </div>

  <!-- Income vs Expenses -->
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">💰 Income vs Expenses — <?= e($ay) ?></h3>
    <?php $balance = $totalIncomeLRD - $totalPaid; ?>
    <?php foreach ([
      ['Income (Fees Collected)',   $totalIncomeLRD, 'var(--green)'],
      ['Expenses Approved',          $totalApproved,  'var(--primary)'],
      ['Expenses Paid',              $totalPaid,      'var(--warning)'],
      ['Balance (Income − Paid)',    $balance,        $balance>=0?'var(--green)':'var(--error)'],
    ] as [$label,$val,$color]): ?>
    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--line-soft);font-size:13px">
      <span><?= $label ?></span>
      <strong style="color:<?= $color ?>">LRD <?= number_format(abs($val)) ?><?= $val<0?' (deficit)':'' ?></strong>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php elseif ($tab === 'expenses'): ?>
<!-- ── EXPENSE REQUESTS ───────────────────────────────────── -->
<?php if (empty($expenses)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">📋</div>
  <p style="color:var(--ink-soft)">No expense requests for <?= e($ay) ?>.</p>
</div>
<?php else: ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Title</th><th>Category</th><th>Amount</th><th>Requested By</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($expenses as $e): ?>
      <tr>
        <td><strong><?= e($e['title']) ?></strong><?php if($e['description']): ?><div style="font-size:11px;color:var(--ink-soft)"><?= e(mb_substr($e['description'],0,60)) ?></div><?php endif; ?></td>
        <td><span class="badge badge-grey"><?= e($e['category']) ?></span></td>
        <td><strong><?= e($e['currency']) ?> <?= number_format($e['amount'],2) ?></strong></td>
        <td class="muted"><?= e($e['requester_name']) ?></td>
        <td class="muted"><?= date('M d, Y',strtotime($e['created_at'])) ?></td>
        <td><span class="status <?= $statusColors[$e['status']]??'new-s' ?>"><?= ucfirst($e['status']) ?></span></td>
        <td>
          <?php if ($canApprove && $e['status']==='pending'): ?>
          <div style="display:flex;gap:5px">
            <form method="post" style="display:inline">
              <?= csrfField() ?><input type="hidden" name="action" value="approve_expense"/>
              <input type="hidden" name="exp_id" value="<?= $e['id'] ?>"/>
              <input type="hidden" name="tab" value="expenses"/>
              <button type="submit" class="filter-button button-sm" style="color:var(--green)">✓ Approve</button>
            </form>
            <form method="post" onsubmit="this.querySelector('textarea')&&true" style="display:inline">
              <?= csrfField() ?><input type="hidden" name="action" value="reject_expense"/>
              <input type="hidden" name="exp_id" value="<?= $e['id'] ?>"/>
              <input type="hidden" name="tab" value="expenses"/>
              <button type="submit" class="filter-button button-sm" style="color:var(--error)">✗ Reject</button>
            </form>
          </div>
          <?php elseif ($e['status']==='approved' && ($canApprove||$canRecord)): ?>
          <form method="post" style="display:inline">
            <?= csrfField() ?><input type="hidden" name="action" value="mark_paid"/>
            <input type="hidden" name="exp_id" value="<?= $e['id'] ?>"/>
            <input type="hidden" name="tab" value="expenses"/>
            <button type="submit" class="filter-button button-sm">💸 Mark Paid</button>
          </form>
          <?php elseif ($e['status']==='rejected' && $e['rejection_reason']): ?>
          <span style="font-size:11px;color:var(--error)"><?= e(mb_substr($e['rejection_reason'],0,40)) ?></span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php elseif ($tab === 'waivers'): ?>
<!-- ── FEE WAIVERS ───────────────────────────────────────── -->
<?php if (empty($waivers)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">💳</div>
  <p style="color:var(--ink-soft)">No fee waiver requests for <?= e($ay) ?>.</p>
  <a href="?tab=new" class="button button-secondary" style="margin-top:14px">Request Fee Waiver</a>
</div>
<?php else: ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Student</th><th>Amount (LRD)</th><th>Reason</th><th>Requested By</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($waivers as $w): ?>
      <tr>
        <td><strong><?= e($w['student_name']) ?></strong><div style="font-size:11px;color:var(--ink-faint)"><?= e($w['student_code']) ?></div></td>
        <td><strong>LRD <?= number_format($w['amount'],2) ?></strong></td>
        <td style="max-width:200px;white-space:normal;font-size:12.5px"><?= e(mb_substr($w['reason'],0,80)) ?></td>
        <td class="muted"><?= e($w['requester_name']) ?></td>
        <td><span class="status <?= $statusColors[$w['status']]??'new-s' ?>"><?= ucfirst($w['status']) ?></span></td>
        <td>
          <?php if ($canApprove && $w['status']==='pending'): ?>
          <div style="display:flex;gap:5px">
            <form method="post" style="display:inline">
              <?= csrfField() ?><input type="hidden" name="action" value="approve_waiver"/>
              <input type="hidden" name="waiver_id" value="<?= $w['id'] ?>"/>
              <input type="hidden" name="tab" value="waivers"/>
              <button type="submit" class="filter-button button-sm" style="color:var(--green)">✓ Approve</button>
            </form>
            <form method="post" style="display:inline">
              <?= csrfField() ?><input type="hidden" name="action" value="reject_waiver"/>
              <input type="hidden" name="waiver_id" value="<?= $w['id'] ?>"/>
              <input type="hidden" name="tab" value="waivers"/>
              <button type="submit" class="filter-button button-sm" style="color:var(--error)">✗ Reject</button>
            </form>
          </div>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php elseif ($tab === 'new'): ?>
<!-- ── NEW REQUEST ────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="exp-grid">
  <!-- Expense Request Form -->
  <div class="panel" style="padding:24px">
    <h3 style="font-weight:700;margin-bottom:16px">📋 New Expense Request</h3>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="add_expense"/><input type="hidden" name="tab" value="expenses"/>
      <div class="form-group"><label>Title *<input name="title" required placeholder="e.g. Office supplies"/></label></div>
      <div class="form-row">
        <div class="form-group"><label>Category<select name="category"><?php foreach($categories as $c): ?><option><?= $c ?></option><?php endforeach; ?></select></label></div>
        <div class="form-group"><label>Currency<select name="currency"><option value="LRD">LRD</option><option value="USD">USD</option></select></label></div>
      </div>
      <div class="form-group"><label>Amount *<input type="number" name="amount" min="0.01" step="0.01" required placeholder="0.00"/></label></div>
      <div class="form-group"><label>Description<textarea name="description" rows="3" placeholder="Optional details"></textarea></label></div>
      <button type="submit" class="button button-primary">Submit Expense Request</button>
    </form>
  </div>

  <!-- Fee Waiver Form -->
  <div class="panel" style="padding:24px">
    <h3 style="font-weight:700;margin-bottom:16px">💳 Request Fee Waiver</h3>
    <p style="font-size:13px;color:var(--ink-soft);margin-bottom:14px">Waiver requests require principal approval.</p>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="add_waiver"/><input type="hidden" name="tab" value="waivers"/>
      <div class="form-group">
        <label>Student *
          <select name="student_id" required>
            <option value="">Select student…</option>
            <?php foreach($allStudents as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['student_id']) ?>)</option><?php endforeach; ?>
          </select>
        </label>
      </div>
      <div class="form-group"><label>Waiver Amount (LRD) *<input type="number" name="amount" min="1" step="0.01" required placeholder="0.00"/></label></div>
      <div class="form-group"><label>Reason *<textarea name="reason" rows="3" required placeholder="Reason for fee waiver…"></textarea></label></div>
      <button type="submit" class="button button-secondary">Submit Waiver Request</button>
    </form>
  </div>
</div>
<?php endif; ?>

<style>@media(max-width:640px){.exp-grid{grid-template-columns:1fr !important}}</style>
<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
