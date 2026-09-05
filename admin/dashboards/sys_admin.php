<?php
$pageTitle='System Administrator'; $activeAdmin='dashboard';
require_once dirname(dirname(__DIR__)).'/includes/admin_header.php';
$pdo=db(); $fn=explode(' ',currentUser()['name']??'Admin')[0];
$totalUsers=(int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalRoles=(int)$pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
$totalPerms=(int)$pdo->query("SELECT COUNT(*) FROM permissions")->fetchColumn();
$auditLogs=(int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE created_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)")->fetchColumn();
$tables=$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='karnhighschool'")->fetchColumn();
$recentLogins=$pdo->query("SELECT u.name,u.email,r.label role_label,u.last_login FROM users u JOIN roles r ON r.id=u.role_id WHERE u.last_login IS NOT NULL ORDER BY u.last_login DESC LIMIT 8")->fetchAll();
$recentAudit=$pdo->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 10")->fetchAll();
?>
<div class="page-heading">
  <div><div class="eyebrow">System Control <span></span></div><h1>Good <?=date('G')<12?'morning':(date('G')<17?'afternoon':'evening')?>, <?=e($fn)?>.</h1><p>System Administrator — Full Access</p></div>
  <a href="<?=BASE_URL?>/admin/settings.php" class="button button-primary">⚙️ System Settings</a>
</div>

<div class="metric-grid" style="margin-bottom:24px">
  <div class="metric-card"><div class="metric-top"><span>Total Users</span><div class="metric-icon">👥</div></div><strong><?=$totalUsers?></strong><small><i></i>All roles</small></div>
  <div class="metric-card"><div class="metric-top"><span>Roles Defined</span><div class="metric-icon">🔑</div></div><strong><?=$totalRoles?></strong><small><i></i><?=$totalPerms?> permissions</small></div>
  <div class="metric-card"><div class="metric-top"><span>DB Tables</span><div class="metric-icon">🗄️</div></div><strong><?=$tables?></strong><small><i></i>karnhighschool DB</small></div>
  <div class="metric-card"><div class="metric-top"><span>Audit Events (24h)</span><div class="metric-icon">🔍</div></div><strong><?=$auditLogs?></strong><small><i></i>Last 24 hours</small></div>
</div>

<div class="quick-grid" style="margin-bottom:20px">
  <a href="<?=BASE_URL?>/admin/users.php"    class="quick-item"><span class="qi-icon">👥</span><div><strong>Users</strong><small>Manage accounts</small></div></a>
  <a href="<?=BASE_URL?>/admin/roles.php"    class="quick-item"><span class="qi-icon">🔑</span><div><strong>Roles & Permissions</strong><small>RBAC config</small></div></a>
  <a href="<?=BASE_URL?>/admin/audit_logs.php" class="quick-item"><span class="qi-icon">🔍</span><div><strong>Audit Logs</strong><small>Security events</small></div></a>
  <a href="<?=BASE_URL?>/admin/settings.php" class="quick-item"><span class="qi-icon">⚙️</span><div><strong>Settings</strong><small>System config</small></div></a>
</div>

<div class="dash-columns">
  <div class="panel activity-panel">
    <div class="panel-heading"><div><h3>Recent Logins</h3></div></div>
    <?php foreach($recentLogins as $u): ?>
    <div class="activity">
      <span class="activity-dot green"></span>
      <div><strong><?=e($u['name'])?></strong><p><?=e($u['email'])?> · <?=e($u['role_label'])?></p><small><?=date('M d, Y H:i',strtotime($u['last_login']))?></small></div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="panel activity-panel">
    <div class="panel-heading"><div><h3>Audit Log</h3><p>Recent events</p></div><a href="<?=BASE_URL?>/admin/audit_logs.php" class="filter-button">All →</a></div>
    <?php foreach($recentAudit as $log): ?>
    <div class="activity">
      <span class="activity-dot <?=in_array($log['action'],['delete','failed_login'])?'pink':'blue'?>"></span>
      <div><strong><?=e($log['action'])?> / <?=e($log['module'])?></strong><p><?=e($log['user_name']??'System')?></p><small><?=date('M d H:i',strtotime($log['created_at']))?></small></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require_once dirname(dirname(__DIR__)).'/includes/admin_footer.php'; ?>
