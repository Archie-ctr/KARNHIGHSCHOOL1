<?php
$pageTitle='System Administrator'; $activeAdmin='dashboard';
require_once dirname(dirname(__DIR__)).'/includes/admin_header.php';
requireRole(['sys_admin','super_admin']);
$pdo=db(); $fn=explode(' ',currentUser()['name']??'Admin')[0];

// ── Metrics ──────────────────────────────────────────────────
$totalUsers   = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$activeUsers  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active=1")->fetchColumn();
$totalRoles   = (int)$pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
$totalPerms   = (int)$pdo->query("SELECT COUNT(*) FROM permissions")->fetchColumn();
$auditToday   = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$failedLogins = (int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action='failed_login' AND DATE(created_at)=CURDATE()")->fetchColumn();

$dbSize = $pdo->query("SELECT ROUND(SUM(data_length+index_length)/1024/1024,2) FROM information_schema.tables WHERE table_schema='karnhighschool'")->fetchColumn();
$tables = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='karnhighschool'")->fetchColumn();

// Backup
$backupDir   = BASE_PATH.'/backups';
$backupFiles = is_dir($backupDir) ? array_filter(glob($backupDir.'/*.sql'), 'is_file') : [];
$lastBackup  = !empty($backupFiles) ? max(array_map('filemtime', $backupFiles)) : null;

// Recent logins & audit
$recentLogins = $pdo->query("SELECT u.name,u.email,r.label role_label,u.last_login FROM users u JOIN roles r ON r.id=u.role_id WHERE u.last_login IS NOT NULL ORDER BY u.last_login DESC LIMIT 6")->fetchAll();
$recentAudit  = $pdo->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 8")->fetchAll();

// Locked accounts
$lockedCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_active=0")->fetchColumn();
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">System Control <span></span></div>
    <h1>Good <?= date('G')<12?'morning':(date('G')<17?'afternoon':'evening') ?>, <?= e($fn) ?>.</h1>
    <p>System Administrator &mdash; Full Technical Access</p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="<?= BASE_URL ?>/admin/security.php" class="button button-secondary">🔐 Security</a>
    <a href="<?= BASE_URL ?>/admin/backup.php"   class="button button-secondary">💾 Backup</a>
    <a href="<?= BASE_URL ?>/admin/settings.php" class="button button-primary">⚙️ Settings</a>
  </div>
</div>

<?php if ($failedLogins > 10): ?>
<div class="alert alert-error" style="margin-bottom:20px">
  ⚠️ <strong><?= $failedLogins ?> failed login attempts today.</strong>
  <a href="<?= BASE_URL ?>/admin/security.php?tab=failed_logins" style="margin-left:10px">View details →</a>
</div>
<?php endif; ?>

<!-- Metrics -->
<div class="metric-grid" style="margin-bottom:24px">
  <div class="metric-card">
    <div class="metric-top"><span>Total Users</span><div class="metric-icon">👥</div></div>
    <strong><?= $totalUsers ?></strong>
    <small><i></i><?= $activeUsers ?> active · <?= $lockedCount > 0 ? "$lockedCount locked" : 'none locked' ?></small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Roles / Permissions</span><div class="metric-icon">🔑</div></div>
    <strong><?= $totalRoles ?></strong>
    <small><i></i><?= $totalPerms ?> permissions defined</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Database</span><div class="metric-icon">🗄️</div></div>
    <strong><?= $dbSize ?> MB</strong>
    <small><i></i><?= $tables ?> tables</small>
  </div>
  <div class="metric-card <?= $failedLogins > 5 ? 'finance-metrics' : '' ?>">
    <div class="metric-top"><span>Audit Events Today</span><div class="metric-icon">🔍</div></div>
    <strong><?= $auditToday ?></strong>
    <small><i></i><?= $failedLogins ?> failed logins</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Last Backup</span><div class="metric-icon">💾</div></div>
    <strong style="font-size:14px"><?= $lastBackup ? date('M d, H:i', $lastBackup) : 'Never' ?></strong>
    <small><i></i><?= count($backupFiles) ?> backup<?= count($backupFiles)!==1?'s':'' ?> stored</small>
  </div>
</div>

<!-- Quick Access — all sys_admin sections -->
<div style="margin-bottom:24px">
  <h3 style="font-size:13px;font-weight:700;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.07em;margin-bottom:12px">Quick Access</h3>
  <div class="quick-grid">
    <!-- User & Access Management -->
    <a href="<?= BASE_URL ?>/admin/users.php"        class="quick-item"><span class="qi-icon">👥</span><div><strong>Users</strong><small>Create, edit, deactivate</small></div></a>
    <a href="<?= BASE_URL ?>/admin/roles.php"        class="quick-item"><span class="qi-icon">🔑</span><div><strong>Roles &amp; Permissions</strong><small>RBAC configuration</small></div></a>
    <a href="<?= BASE_URL ?>/admin/users.php?action=sessions" class="quick-item"><span class="qi-icon">🖥️</span><div><strong>Active Sessions</strong><small>Login history</small></div></a>

    <!-- Security -->
    <a href="<?= BASE_URL ?>/admin/security.php"             class="quick-item"><span class="qi-icon">🔐</span><div><strong>Security Centre</strong><small>Logins, locks, audit</small></div></a>
    <a href="<?= BASE_URL ?>/admin/audit_logs.php"           class="quick-item"><span class="qi-icon">📋</span><div><strong>Audit Logs</strong><small>All system events</small></div></a>
    <a href="<?= BASE_URL ?>/admin/security.php?tab=failed_logins" class="quick-item"><span class="qi-icon">⚠️</span><div><strong>Failed Logins</strong><small>Monitor attempts</small></div></a>

    <!-- System Configuration -->
    <a href="<?= BASE_URL ?>/admin/settings.php"             class="quick-item"><span class="qi-icon">⚙️</span><div><strong>School Settings</strong><small>Profile, logo, contact</small></div></a>
    <a href="<?= BASE_URL ?>/admin/settings.php?tab=academic" class="quick-item"><span class="qi-icon">🎓</span><div><strong>Academic Settings</strong><small>Grading, promotion, attendance</small></div></a>
    <a href="<?= BASE_URL ?>/admin/settings.php?tab=finance" class="quick-item"><span class="qi-icon">💰</span><div><strong>Finance Settings</strong><small>Fees, categories, currency</small></div></a>
    <a href="<?= BASE_URL ?>/admin/academic_years.php"       class="quick-item"><span class="qi-icon">📅</span><div><strong>Academic Years</strong><small>Terms and periods</small></div></a>

    <!-- Integrations -->
    <a href="<?= BASE_URL ?>/admin/settings.php?tab=notifications" class="quick-item"><span class="qi-icon">📢</span><div><strong>Notifications</strong><small>Email &amp; SMS settings</small></div></a>
    <a href="<?= BASE_URL ?>/admin/settings.php?tab=integrations"  class="quick-item"><span class="qi-icon">🔌</span><div><strong>Integrations</strong><small>SMTP, SMS, payments</small></div></a>

    <!-- Backup & Monitor -->
    <a href="<?= BASE_URL ?>/admin/backup.php"               class="quick-item"><span class="qi-icon">💾</span><div><strong>Backup &amp; Restore</strong><small>DB backup, export</small></div></a>
    <a href="<?= BASE_URL ?>/admin/system_monitor.php"       class="quick-item"><span class="qi-icon">📊</span><div><strong>System Monitor</strong><small>Storage, health, PHP</small></div></a>
    <a href="<?= BASE_URL ?>/admin/system_monitor.php?tab=error_log" class="quick-item"><span class="qi-icon">🔴</span><div><strong>Error Log</strong><small>PHP &amp; system errors</small></div></a>
  </div>
</div>

<!-- Recent activity panels -->
<div class="dash-columns">
  <div class="panel activity-panel">
    <div class="panel-heading">
      <div><h3>Recent Logins</h3></div>
      <a href="<?= BASE_URL ?>/admin/security.php?tab=login_history" class="filter-button">All →</a>
    </div>
    <?php if (empty($recentLogins)): ?>
    <p style="color:var(--ink-faint);font-size:13px;padding:12px">No logins recorded.</p>
    <?php else: foreach ($recentLogins as $u): ?>
    <div class="activity">
      <span class="activity-dot green"></span>
      <div>
        <strong><?= e($u['name']) ?></strong>
        <p><?= e($u['email']) ?> · <?= e($u['role_label']) ?></p>
        <small><?= date('M d, Y H:i', strtotime($u['last_login'])) ?></small>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <div class="panel activity-panel">
    <div class="panel-heading">
      <div><h3>Audit Log</h3><p>Recent system events</p></div>
      <a href="<?= BASE_URL ?>/admin/audit_logs.php" class="filter-button">All →</a>
    </div>
    <?php foreach ($recentAudit as $log): ?>
    <div class="activity">
      <span class="activity-dot <?= in_array($log['action'],['delete','failed_login','lock'])?'pink':'blue' ?>"></span>
      <div>
        <strong><?= e($log['action']) ?> / <?= e($log['module']) ?></strong>
        <p><?= e($log['user_name'] ?? 'System') ?></p>
        <small><?= date('M d H:i', strtotime($log['created_at'])) ?></small>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require_once dirname(dirname(__DIR__)).'/includes/admin_footer.php'; ?>
