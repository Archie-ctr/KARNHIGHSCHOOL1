<?php
// ── SECURITY MONITORING ────────────────────────────────────────
// Reads audit_logs only. No access to marks, finance, health.
// ──────────────────────────────────────────────────────────────
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['ict_officer','sys_admin','school_admin']);
$activePage='security'; $pdo=db(); $user=currentUser();
$officer=null; try{$s=$pdo->prepare("SELECT * FROM staff WHERE user_id=? LIMIT 1");$s->execute([$user['id']]);$officer=$s->fetch()?:null;}catch(Throwable $e){}
if(!$officer) $officer=['first_name'=>$user['username']??'ICT','last_name'=>'','id'=>0];

$tab=$_GET['tab']??'audit';

// Audit log
$fModule=trim($_GET['module']??''); $fSearch=trim($_GET['q']??''); $fUser=(int)($_GET['filter_user']??0); $fDate=$_GET['date']??'';
$where=['1=1']; $params=[];
if($fModule){$where[]="al.module=?";$params[]=$fModule;}
if($fUser){$where[]="al.user_id=?";$params[]=$fUser;}
if($fDate){$where[]="DATE(al.created_at)=?";$params[]=$fDate;}
if($fSearch){$where[]="(al.action LIKE ? OR al.description LIKE ? OR u.username LIKE ?)";$p="%$fSearch%";$params=array_merge($params,[$p,$p,$p]);}

try{
    $logs=$pdo->prepare("SELECT al.*,u.username,u.role FROM audit_logs al LEFT JOIN users u ON u.id=al.user_id WHERE ".implode(' AND ',$where)." ORDER BY al.created_at DESC LIMIT 200");
    $logs->execute($params);$logs=$logs->fetchAll();
}catch(Throwable $e){$logs=[];}

// Modules list for filter
try{$modules=$pdo->query("SELECT DISTINCT module FROM audit_logs WHERE module IS NOT NULL ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);}catch(Throwable $e){$modules=[];}

// Recent login activity
try{$logins=$pdo->query("SELECT al.*,u.username,u.role FROM audit_logs al LEFT JOIN users u ON u.id=al.user_id WHERE al.action IN ('login','login_failed','logout') ORDER BY al.created_at DESC LIMIT 50")->fetchAll();}catch(Throwable $e){$logins=[];}

// Failed logins in last 24h
try{$failedLogins=(int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action='login_failed' AND created_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)")->fetchColumn();}catch(Throwable $e){$failedLogins=0;}
// Active users today
try{$activeToday=(int)$pdo->query("SELECT COUNT(DISTINCT user_id) FROM audit_logs WHERE DATE(created_at)=CURDATE()")->fetchColumn();}catch(Throwable $e){$activeToday=0;}
// Total events today
try{$eventsToday=(int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at)=CURDATE()")->fetchColumn();}catch(Throwable $e){$eventsToday=0;}
// Logins today
try{$loginsToday=(int)$pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action='login' AND DATE(created_at)=CURDATE()")->fetchColumn();}catch(Throwable $e){$loginsToday=0;}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Security Monitor — ICT Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">
<div class="page-heading">
  <div><h1>Security Monitor</h1><p>System activity, access monitoring, audit trail</p></div>
</div>
<div class="alert alert-info" style="margin-bottom:16px">
  🔐 <strong>Access restricted:</strong> This view shows system activity logs only. Academic, financial and health records are not accessible.
</div>

<!-- Today stats -->
<div class="metric-grid" style="margin-bottom:16px">
  <div class="metric-card"><div class="metric-top"><span>Events Today</span><div class="metric-icon">📋</div></div><strong><?=$eventsToday?></strong></div>
  <div class="metric-card"><div class="metric-top"><span>Active Users Today</span><div class="metric-icon">👥</div></div><strong><?=$activeToday?></strong></div>
  <div class="metric-card"><div class="metric-top"><span>Logins Today</span><div class="metric-icon">🔑</div></div><strong><?=$loginsToday?></strong></div>
  <div class="metric-card <?=$failedLogins>5?'finance-metrics':''?>"><div class="metric-top"><span>Failed Logins (24h)</span><div class="metric-icon">⚠️</div></div><strong style="color:<?=$failedLogins>5?'var(--error)':($failedLogins>0?'var(--warning)':'var(--green)')?>"><?=$failedLogins?></strong></div>
</div>

<div class="tab-bar" style="margin-bottom:16px">
  <a href="?tab=audit"  class="tab-btn <?=$tab==='audit' ?'active':''?>">📋 Audit Log</a>
  <a href="?tab=logins" class="tab-btn <?=$tab==='logins'?'active':''?>">🔑 Login Activity</a>
</div>

<?php if($tab==='logins'): ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Time</th><th>User</th><th>Role</th><th>Action</th><th>IP Address</th><th>Details</th></tr></thead>
    <tbody>
      <?php if(empty($logins)):?><tr><td colspan="6" style="text-align:center;color:var(--ink-faint);padding:24px">No login records.</td></tr>
      <?php else:foreach($logins as $l): $failed=$l['action']==='login_failed';?>
      <tr <?=$failed?'style="background:rgba(239,68,68,.04)"':''?>>
        <td class="muted" style="font-size:12px;font-family:monospace"><?=date('d M Y H:i:s',strtotime($l['created_at']))?></td>
        <td><strong><?=e($l['username']??'Unknown')?></strong></td>
        <td class="muted" style="font-size:12px"><?=e($l['role']??'—')?></td>
        <td>
          <?php if($failed):?><span style="color:var(--error);font-weight:700;font-size:12px">Failed Login</span>
          <?php elseif($l['action']==='logout'):?><span style="color:var(--ink-soft);font-size:12px">Logout</span>
          <?php else:?><span style="color:var(--green);font-weight:700;font-size:12px">Login</span><?php endif;?>
        </td>
        <td style="font-family:monospace;font-size:11px" class="muted"><?=e($l['ip_address']??'—')?></td>
        <td style="font-size:12px;color:var(--ink-soft)"><?=e(mb_substr($l['description']??'',0,60))?></td>
      </tr>
      <?php endforeach;endif;?>
    </tbody>
  </table>
</div>

<?php else: ?><!-- Audit log -->
<form method="get" class="filter-bar" style="margin-bottom:12px">
  <input type="hidden" name="tab" value="audit"/>
  <input type="text" name="q" placeholder="Search action, description…" value="<?=e($fSearch)?>"/>
  <select name="module"><option value="">All Modules</option><?php foreach($modules as $m):?><option value="<?=$m?>" <?=$fModule===$m?'selected':''?>><?=e($m)?></option><?php endforeach;?></select>
  <input type="date" name="date" value="<?=e($fDate)?>" title="Filter by date"/>
  <button type="submit" class="button button-secondary button-sm">Filter</button>
  <a href="?tab=audit" class="button button-secondary button-sm">Reset</a>
</form>
<div class="table-wrap">
  <table>
    <thead><tr><th>Timestamp</th><th>User</th><th>Role</th><th>Module</th><th>Action</th><th>Description</th><th>IP</th></tr></thead>
    <tbody>
      <?php if(empty($logs)):?><tr><td colspan="7" style="text-align:center;color:var(--ink-faint);padding:24px">No audit records found.</td></tr>
      <?php else:foreach($logs as $l): $isSensitive=in_array($l['module']??'',['finance','marks','health','payroll']);?>
      <?php if($isSensitive) continue; // Never display financial/health/marks entries ?>
      <tr>
        <td style="font-family:monospace;font-size:11px;white-space:nowrap"><?=date('d M H:i:s',strtotime($l['created_at']))?></td>
        <td><strong style="font-size:13px"><?=e($l['username']??'System')?></strong></td>
        <td class="muted" style="font-size:11px"><?=e($l['role']??'—')?></td>
        <td><span style="font-size:11px;padding:2px 7px;border-radius:8px;background:var(--bg2);font-weight:600"><?=e($l['module']??'system')?></span></td>
        <td style="font-size:12px;font-weight:600"><?=e($l['action']??'—')?></td>
        <td style="max-width:220px;font-size:12px;color:var(--ink-soft)"><?=e(mb_substr($l['description']??'',0,80))?><?=mb_strlen($l['description']??'')>80?'…':''?></td>
        <td style="font-family:monospace;font-size:11px" class="muted"><?=e($l['ip_address']??'—')?></td>
      </tr>
      <?php endforeach;endif;?>
    </tbody>
  </table>
</div>
<p style="font-size:12px;color:var(--ink-faint);margin-top:8px">⚠️ Financial, academic marks and health module entries are filtered from this view per the ICT role security policy.</p>
<?php endif;?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
