<?php
$pageTitle   = 'Security';
$activeAdmin = 'security';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['sys_admin','super_admin']);

$pdo = db();
$tab = $_GET['tab'] ?? 'login_history';

// ── POST actions ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'unlock_user') {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE users SET is_active=1, failed_logins=0, locked_until=NULL WHERE id=?")->execute([$id]);
            auditLog('unlock','security','user',$id,'','Account unlocked by sys_admin');
            flash('success','Account unlocked.');
        }
    } elseif ($action === 'lock_user') {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($id && $id !== currentUserId()) {
            $pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$id]);
            auditLog('lock','security','user',$id,'','Account locked by sys_admin');
            flash('success','Account locked.');
        }
    } elseif ($action === 'clear_failed') {
        $pdo->exec("UPDATE users SET failed_logins=0, locked_until=NULL");
        auditLog('clear_failed_logins','security','users',0,'','Cleared all failed login counters');
        flash('success','All failed login counters cleared.');
    } elseif ($action === 'clear_audit') {
        $days = (int)($_POST['days'] ?? 90);
        if ($days >= 30) {
            $pdo->prepare("DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)")->execute([$days]);
            $rows = $pdo->rowCount();
            flash('success',"Deleted $rows audit entries older than $days days.");
        }
    }
    redirect(BASE_URL.'/admin/security.php?tab='.urlencode($tab));
}

// ── Data queries ──────────────────────────────────────────────
$q    = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 30;

// Login history (audit_logs filtered to login actions)
$loginWhere = "WHERE al.action IN ('login','failed_login','logout')";
if ($q) $loginWhere .= " AND (al.user_name LIKE '%".addslashes($q)."%' OR al.ip_address LIKE '%".addslashes($q)."%')";
$loginCount = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs al $loginWhere")->fetchColumn();
$pgLogin    = paginate($loginCount, $per, $page);
$loginLogs  = $pdo->query("SELECT * FROM audit_logs al $loginWhere ORDER BY al.created_at DESC LIMIT $per OFFSET {$pgLogin['offset']}")->fetchAll();

// Failed logins — last 7 days
$failedRecent = $pdo->query(
    "SELECT al.user_name, al.ip_address, COUNT(*) attempts,
            MAX(al.created_at) last_attempt
     FROM audit_logs al
     WHERE al.action='failed_login'
       AND al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY al.user_name, al.ip_address
     ORDER BY attempts DESC LIMIT 30"
)->fetchAll();

// Locked / suspicious accounts
$lockedUsers = $pdo->query(
    "SELECT u.id, u.name, u.email, u.is_active, u.last_login,
            r.label role_label,
            COALESCE(u.failed_logins,0) failed_logins
     FROM users u JOIN roles r ON r.id=u.role_id
     WHERE u.is_active=0 OR COALESCE(u.failed_logins,0) >= 3
     ORDER BY u.is_active ASC, failed_logins DESC"
)->fetchAll();

// Permission changes last 30 days
$permChanges = $pdo->query(
    "SELECT * FROM audit_logs
     WHERE module IN ('users','roles','permissions','security')
       AND action IN ('create','update','delete','change_role','toggle_active','lock','unlock','reset_password')
     ORDER BY created_at DESC LIMIT 50"
)->fetchAll();

// Summary stats
$totalUsers    = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$activeUsers   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
$inactiveUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active=0")->fetchColumn();
$failedToday   = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action='failed_login' AND DATE(created_at)=CURDATE()")->fetchColumn();
$loginsToday   = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action='login' AND DATE(created_at)=CURDATE()")->fetchColumn();
$auditTotal    = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn();
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">System <span></span></div>
    <h1>Security Centre</h1>
    <p>Login history, failed logins, account locks and permission audit.</p>
  </div>
  <div style="display:flex;gap:8px">
    <form method="post" onsubmit="return confirm('Clear all failed login counters?')">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="clear_failed"/>
      <input type="hidden" name="tab"    value="<?= e($tab) ?>"/>
      <button type="submit" class="button button-secondary">🔓 Clear Failed Logins</button>
    </form>
    <a href="<?= BASE_URL ?>/admin/audit_logs.php" class="button button-secondary">📋 Full Audit Log</a>
  </div>
</div>

<!-- Summary Metrics -->
<div class="metric-grid" style="margin-bottom:24px">
  <div class="metric-card">
    <div class="metric-top"><span>Total Users</span><div class="metric-icon">👥</div></div>
    <strong><?= $totalUsers ?></strong>
    <small><i></i><?= $activeUsers ?> active · <?= $inactiveUsers ?> inactive</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Logins Today</span><div class="metric-icon" style="background:var(--green-soft);color:var(--green)">✓</div></div>
    <strong><?= $loginsToday ?></strong>
    <small><i></i>Successful</small>
  </div>
  <div class="metric-card <?= $failedToday > 5 ? 'finance-metrics' : '' ?>">
    <div class="metric-top"><span>Failed Logins Today</span><div class="metric-icon" style="background:var(--error-soft);color:var(--error)">✗</div></div>
    <strong style="color:<?= $failedToday > 5 ? 'var(--error)' : 'inherit' ?>"><?= $failedToday ?></strong>
    <small><i></i><?= $failedToday > 5 ? 'Suspicious activity' : 'Normal range' ?></small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Audit Events Total</span><div class="metric-icon">🔍</div></div>
    <strong><?= number_format($auditTotal) ?></strong>
    <small><i></i>All time</small>
  </div>
</div>

<!-- Tabs -->
<div class="tab-bar" style="margin-bottom:16px">
  <a href="?tab=login_history"  class="tab-btn <?= $tab==='login_history'  ?'active':'' ?>">🔑 Login History</a>
  <a href="?tab=failed_logins"  class="tab-btn <?= $tab==='failed_logins'  ?'active':'' ?>">⚠️ Failed Logins</a>
  <a href="?tab=account_locks"  class="tab-btn <?= $tab==='account_locks'  ?'active':'' ?>">🔒 Account Locks (<?= count($lockedUsers) ?>)</a>
  <a href="?tab=permission_log" class="tab-btn <?= $tab==='permission_log' ?'active':'' ?>">📋 Permission Changes</a>
  <a href="?tab=cleanup"        class="tab-btn <?= $tab==='cleanup'        ?'active':'' ?>">🗑️ Log Cleanup</a>
</div>

<?php if ($tab === 'login_history'): ?>
<!-- ── LOGIN HISTORY ──────────────────────────────────────── -->
<form method="get" class="filter-row" style="margin-bottom:12px">
  <input type="hidden" name="tab" value="login_history"/>
  <div class="table-search">🔍<input type="search" name="q" placeholder="Username or IP…" value="<?= e($q) ?>"/></div>
  <button class="button button-primary button-sm">Search</button>
  <?php if ($q): ?><a href="?tab=login_history" class="filter-button">Clear</a><?php endif; ?>
</form>
<div class="table-wrap">
  <table>
    <thead><tr><th>Date &amp; Time</th><th>User</th><th>Action</th><th>IP Address</th><th>Details</th></tr></thead>
    <tbody>
      <?php if (empty($loginLogs)): ?>
      <tr><td colspan="5" style="text-align:center;padding:28px;color:var(--ink-faint)">No login records found.</td></tr>
      <?php else: foreach ($loginLogs as $l): ?>
      <tr>
        <td class="muted" style="white-space:nowrap"><?= date('M d, Y H:i:s', strtotime($l['created_at'])) ?></td>
        <td><strong><?= e($l['user_name'] ?? '—') ?></strong></td>
        <td><span class="status <?= $l['action']==='login'?'approved':($l['action']==='failed_login'?'warning':'new-s') ?>"><?= e($l['action']) ?></span></td>
        <td class="muted"><?= e($l['ip_address'] ?? '—') ?></td>
        <td class="muted" style="font-size:12px"><?= e(mb_substr($l['new_value']??'',0,80)) ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php if ($pgLogin['pages'] > 1): ?>
<div class="pagination">
  <?php if ($pgLogin['page']>1): ?><a href="?tab=login_history&page=<?=$pgLogin['page']-1?>&q=<?=urlencode($q)?>">&laquo;</a><?php endif; ?>
  <?php for ($p=max(1,$pgLogin['page']-2); $p<=min($pgLogin['pages'],$pgLogin['page']+2); $p++): ?>
    <?php if ($p===$pgLogin['page']): ?><span class="current"><?=$p?></span><?php else: ?><a href="?tab=login_history&page=<?=$p?>&q=<?=urlencode($q)?>"><?=$p?></a><?php endif; ?>
  <?php endfor; ?>
  <?php if ($pgLogin['page']<$pgLogin['pages']): ?><a href="?tab=login_history&page=<?=$pgLogin['page']+1?>&q=<?=urlencode($q)?>">&raquo;</a><?php endif; ?>
</div>
<?php endif; ?>

<?php elseif ($tab === 'failed_logins'): ?>
<!-- ── FAILED LOGINS ─────────────────────────────────────── -->
<div class="table-wrap">
  <table>
    <thead><tr><th>Username/Email</th><th>IP Address</th><th>Attempts (7 days)</th><th>Last Attempt</th><th>Risk</th></tr></thead>
    <tbody>
      <?php if (empty($failedRecent)): ?>
      <tr><td colspan="5" style="text-align:center;padding:28px;color:var(--ink-faint)">No failed logins in the past 7 days.</td></tr>
      <?php else: foreach ($failedRecent as $f): ?>
      <tr>
        <td><strong><?= e($f['user_name'] ?? 'Unknown') ?></strong></td>
        <td class="muted"><?= e($f['ip_address'] ?? '—') ?></td>
        <td><strong style="color:<?= $f['attempts']>=5?'var(--error)':($f['attempts']>=3?'var(--warning)':'inherit') ?>"><?= $f['attempts'] ?></strong></td>
        <td class="muted"><?= date('M d, Y H:i', strtotime($f['last_attempt'])) ?></td>
        <td>
          <?php if ($f['attempts'] >= 10): ?>
            <span class="status warning">🔴 High</span>
          <?php elseif ($f['attempts'] >= 5): ?>
            <span class="status pending">🟠 Medium</span>
          <?php else: ?>
            <span class="status new-s">🟡 Low</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php elseif ($tab === 'account_locks'): ?>
<!-- ── ACCOUNT LOCKS ─────────────────────────────────────── -->
<?php if (empty($lockedUsers)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">✅</div>
  <p style="color:var(--ink-soft)">No locked or suspicious accounts.</p>
</div>
<?php else: ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Failed Logins</th><th>Last Login</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($lockedUsers as $u): ?>
      <tr>
        <td>
          <strong><?= e($u['name']) ?></strong>
          <div style="font-size:11px;color:var(--ink-faint)"><?= e($u['email'] ?? '—') ?></div>
        </td>
        <td class="muted"><?= e($u['role_label']) ?></td>
        <td><span class="status <?= $u['is_active'] ? 'approved' : 'warning' ?>"><?= $u['is_active'] ? 'Active' : 'Inactive/Locked' ?></span></td>
        <td><strong style="color:<?= $u['failed_logins']>=3?'var(--error)':'inherit' ?>"><?= $u['failed_logins'] ?></strong></td>
        <td class="muted"><?= $u['last_login'] ? date('M d, Y H:i', strtotime($u['last_login'])) : 'Never' ?></td>
        <td>
          <div style="display:flex;gap:6px">
            <?php if (!$u['is_active']): ?>
            <form method="post" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="action"  value="unlock_user"/>
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>"/>
              <input type="hidden" name="tab"     value="account_locks"/>
              <button type="submit" class="filter-button button-sm" style="color:var(--green)">🔓 Unlock</button>
            </form>
            <?php else: ?>
            <form method="post" onsubmit="return confirm('Lock this account?')" style="display:inline">
              <?= csrfField() ?>
              <input type="hidden" name="action"  value="lock_user"/>
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>"/>
              <input type="hidden" name="tab"     value="account_locks"/>
              <button type="submit" class="filter-button button-sm" style="color:var(--error)">🔒 Lock</button>
            </form>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/admin/users.php?q=<?= urlencode($u['email']??$u['name']) ?>" class="filter-button button-sm">✏️ Edit</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php elseif ($tab === 'permission_log'): ?>
<!-- ── PERMISSION CHANGES ────────────────────────────────── -->
<div class="table-wrap">
  <table>
    <thead><tr><th>Date &amp; Time</th><th>Action</th><th>Module</th><th>By</th><th>Record</th><th>Change</th></tr></thead>
    <tbody>
      <?php if (empty($permChanges)): ?>
      <tr><td colspan="6" style="text-align:center;padding:28px;color:var(--ink-faint)">No permission changes recorded.</td></tr>
      <?php else: foreach ($permChanges as $l): ?>
      <tr>
        <td class="muted" style="white-space:nowrap"><?= date('M d, Y H:i', strtotime($l['created_at'])) ?></td>
        <td><span class="status <?= in_array($l['action'],['delete','lock'])?'warning':($l['action']==='create'?'approved':'new-s') ?>"><?= e($l['action']) ?></span></td>
        <td class="muted"><?= e($l['module']) ?></td>
        <td><strong><?= e($l['user_name'] ?? 'System') ?></strong></td>
        <td class="muted"><?= e($l['record_type']?$l['record_type'].'#'.$l['record_id']:'') ?></td>
        <td style="font-size:12px;max-width:220px">
          <?php if ($l['old_value']): ?><span style="color:var(--error)">- <?= e(mb_substr($l['old_value'],0,50)) ?></span><br><?php endif; ?>
          <?php if ($l['new_value']): ?><span style="color:var(--green)">+ <?= e(mb_substr($l['new_value'],0,50)) ?></span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php elseif ($tab === 'cleanup'): ?>
<!-- ── LOG CLEANUP ───────────────────────────────────────── -->
<div class="panel" style="padding:28px;max-width:560px">
  <h3 style="font-weight:700;margin-bottom:8px">🗑️ Audit Log Cleanup</h3>
  <p style="font-size:13px;color:var(--ink-soft);margin-bottom:20px">
    Delete old audit log entries to free database space. This action cannot be undone.
    There are currently <strong><?= number_format($auditTotal) ?></strong> total audit entries.
  </p>
  <form method="post" onsubmit="return confirm('Delete old audit logs? This cannot be undone.')">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="clear_audit"/>
    <input type="hidden" name="tab"    value="cleanup"/>
    <div class="form-group" style="margin-bottom:16px">
      <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px">Delete entries older than</label>
      <select name="days" style="padding:10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-size:14px;font-family:inherit">
        <option value="30">30 days</option>
        <option value="60">60 days</option>
        <option value="90" selected>90 days</option>
        <option value="180">180 days</option>
        <option value="365">1 year</option>
      </select>
    </div>
    <button type="submit" class="button" style="background:var(--error);color:#fff">🗑️ Delete Old Logs</button>
  </form>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
