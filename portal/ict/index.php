<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['ict_officer','sys_admin','school_admin']);
// ── SECURITY: no access to marks, finance, health ──────────
$activePage='dashboard'; $pdo=db(); $user=currentUser(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
$officer=null; try{$s=$pdo->prepare("SELECT * FROM staff WHERE user_id=? LIMIT 1");$s->execute([$user['id']]);$officer=$s->fetch()?:null;}catch(Throwable $e){}
if(!$officer) $officer=['first_name'=>$user['username']??'ICT','last_name'=>'Officer','id'=>0];

// Ensure tables exist
foreach(["CREATE TABLE IF NOT EXISTS ict_assets (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,asset_id VARCHAR(40) NOT NULL UNIQUE,asset_type VARCHAR(40) NOT NULL,brand VARCHAR(80) NULL,model VARCHAR(100) NULL,serial_number VARCHAR(80) NULL,purchase_date DATE NULL,warranty_expiry DATE NULL,assigned_to_name VARCHAR(150) NULL,location VARCHAR(120) NULL,condition_status VARCHAR(30) NOT NULL DEFAULT 'Good',os_installed VARCHAR(80) NULL,status VARCHAR(20) NOT NULL DEFAULT 'active',notes TEXT NULL,added_by INT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,INDEX idx_ict_type(asset_type)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS ict_tickets (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,ticket_ref VARCHAR(20) NOT NULL,category VARCHAR(40) NOT NULL,subject VARCHAR(200) NOT NULL,description TEXT NOT NULL,priority VARCHAR(10) NOT NULL DEFAULT 'medium',reported_by INT UNSIGNED NOT NULL,reporter_name VARCHAR(120) NULL,reporter_role VARCHAR(40) NULL,assigned_to INT UNSIGNED NULL,status VARCHAR(20) NOT NULL DEFAULT 'open',resolution_notes TEXT NULL,opened_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,resolved_at DATETIME NULL,updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,INDEX idx_tkt_status(status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS ict_ticket_comments (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,ticket_id INT UNSIGNED NOT NULL,comment TEXT NOT NULL,is_internal TINYINT(1) NOT NULL DEFAULT 0,added_by INT UNSIGNED NOT NULL,added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS ict_network_devices (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,device_name VARCHAR(120) NOT NULL,device_type VARCHAR(40) NOT NULL,ip_address VARCHAR(45) NULL,location VARCHAR(120) NULL,brand VARCHAR(80) NULL,model VARCHAR(100) NULL,status VARCHAR(20) NOT NULL DEFAULT 'online',last_seen DATETIME NULL,notes TEXT NULL,added_by INT UNSIGNED NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS ict_maintenance_log (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,asset_id INT UNSIGNED NOT NULL,maintenance_type VARCHAR(40) NOT NULL,description TEXT NOT NULL,performed_by VARCHAR(120) NULL,performed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,cost DECIMAL(8,2) NULL,status VARCHAR(20) NOT NULL DEFAULT 'completed',logged_by INT UNSIGNED NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS ict_password_requests (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,reason VARCHAR(200) NULL,requested_by INT UNSIGNED NOT NULL,handled_by INT UNSIGNED NULL,status VARCHAR(20) NOT NULL DEFAULT 'pending',notes TEXT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,handled_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
] as $sql){ try{$pdo->exec($sql);}catch(Throwable $e){} }

// KPIs
$kpis=[];
foreach([
  'total_assets'     =>"SELECT COUNT(*) FROM ict_assets WHERE status='active'",
  'needs_repair'     =>"SELECT COUNT(*) FROM ict_assets WHERE condition_status IN ('Needs Repair','Damaged') AND status='active'",
  'open_tickets'     =>"SELECT COUNT(*) FROM ict_tickets WHERE status='open'",
  'in_progress'      =>"SELECT COUNT(*) FROM ict_tickets WHERE status='in_progress'",
  'critical_tickets' =>"SELECT COUNT(*) FROM ict_tickets WHERE priority='critical' AND status NOT IN ('resolved','closed')",
  'network_devices'  =>"SELECT COUNT(*) FROM ict_network_devices",
  'offline_devices'  =>"SELECT COUNT(*) FROM ict_network_devices WHERE status='offline'",
  'pending_resets'   =>"SELECT COUNT(*) FROM ict_password_requests WHERE status='pending'",
] as $k=>$q){ try{$kpis[$k]=(int)$pdo->query($q)->fetchColumn();}catch(Throwable $e){$kpis[$k]=0;} }

// Recent tickets
try{$recentTickets=$pdo->query("SELECT * FROM ict_tickets WHERE status NOT IN ('closed') ORDER BY FIELD(priority,'critical','high','medium','low'), opened_at DESC LIMIT 8")->fetchAll();}catch(Throwable $e){$recentTickets=[];}
// Assets needing attention
try{$alertAssets=$pdo->query("SELECT * FROM ict_assets WHERE condition_status IN ('Needs Repair','Damaged') OR (warranty_expiry IS NOT NULL AND warranty_expiry<DATE_ADD(CURDATE(),INTERVAL 30 DAY)) ORDER BY condition_status LIMIT 6")->fetchAll();}catch(Throwable $e){$alertAssets=[];}
// Asset type breakdown
try{$byType=$pdo->query("SELECT asset_type,COUNT(*) n FROM ict_assets WHERE status='active' GROUP BY asset_type ORDER BY n DESC")->fetchAll();}catch(Throwable $e){$byType=[];}

$hour=(int)date('G'); $greet=$hour<12?'Good morning':($hour<17?'Good afternoon':'Good evening');
$pc=['open'=>'var(--error)','in_progress'=>'var(--warning)','pending_parts'=>'var(--gold)','resolved'=>'var(--green)','closed'=>'var(--ink-soft)'];
$pp=['critical'=>'#7c0000','high'=>'var(--error)','medium'=>'var(--warning)','low'=>'var(--green)'];
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>ICT Dashboard — KHS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">

<div class="page-heading">
  <div><h1><?=$greet?>, <?=e($officer['first_name'])?>!</h1><p>ICT Department &mdash; <?=date('l, F j, Y')?></p></div>
  <a href="tickets.php?action=new" class="button button-primary">+ New Support Ticket</a>
</div>
<?php foreach(getFlash() as $f):?><div class="alert alert-<?=$f['type']?>"><?=e($f['message'])?></div><?php endforeach;?>

<?php if($kpis['critical_tickets']>0):?>
<div class="alert alert-warning">🚨 <strong><?=$kpis['critical_tickets']?> critical ticket<?=$kpis['critical_tickets']!=1?'s':''?></strong> need immediate attention. <a href="tickets.php?priority=critical" style="font-weight:700">View now →</a></div>
<?php endif;?>
<?php if($kpis['pending_resets']>0):?>
<div class="alert alert-info">🔑 <strong><?=$kpis['pending_resets']?> password reset<?=$kpis['pending_resets']!=1?'s':''?></strong> pending. <a href="users.php?tab=resets" style="font-weight:700">Handle now →</a></div>
<?php endif;?>

<!-- KPIs -->
<div class="metric-grid">
  <div class="metric-card"><div class="metric-top"><span>Active Assets</span><div class="metric-icon">💻</div></div><strong><?=$kpis['total_assets']?></strong><small><i></i>IT equipment</small></div>
  <div class="metric-card <?=$kpis['needs_repair']>0?'finance-metrics':''?>"><div class="metric-top"><span>Need Repair</span><div class="metric-icon">🔧</div></div><strong style="color:<?=$kpis['needs_repair']>0?'var(--error)':'var(--green)'?>"><?=$kpis['needs_repair']?></strong><small><i></i>Assets flagged</small></div>
  <div class="metric-card <?=$kpis['open_tickets']>0?'finance-metrics':''?>"><div class="metric-top"><span>Open Tickets</span><div class="metric-icon">🎫</div></div><strong style="color:<?=$kpis['open_tickets']>0?'var(--error)':'inherit'?>"><?=$kpis['open_tickets']?></strong><small><i></i><?=$kpis['in_progress']?> in progress</small></div>
  <div class="metric-card <?=$kpis['critical_tickets']>0?'finance-metrics':''?>"><div class="metric-top"><span>Critical</span><div class="metric-icon">🚨</div></div><strong style="color:<?=$kpis['critical_tickets']>0?'#7c0000':'inherit'?>"><?=$kpis['critical_tickets']?></strong><small><i></i>Urgent issues</small></div>
  <div class="metric-card <?=$kpis['offline_devices']>0?'finance-metrics':''?>"><div class="metric-top"><span>Network Devices</span><div class="metric-icon">🌐</div></div><strong><?=$kpis['network_devices']?></strong><small><i></i><?=$kpis['offline_devices']?> offline</small></div>
  <div class="metric-card"><div class="metric-top"><span>Password Resets</span><div class="metric-icon">🔑</div></div><strong style="color:<?=$kpis['pending_resets']>0?'var(--warning)':'inherit'?>"><?=$kpis['pending_resets']?></strong><small><i></i>Pending</small></div>
</div>

<!-- Quick actions -->
<div class="panel" style="padding:18px;margin-bottom:16px">
  <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:10px">Quick Actions</div>
  <div class="quick-grid">
    <a href="tickets.php?action=new" class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px"><span class="qi-icon">🎫</span><div><strong>New Ticket</strong><small>Log support request</small></div></a>
    <a href="assets.php?action=new"  class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px"><span class="qi-icon">💻</span><div><strong>Add Asset</strong><small>Register new equipment</small></div></a>
    <a href="network.php"            class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px"><span class="qi-icon">🌐</span><div><strong>Network Status</strong><small><?=$kpis['offline_devices']?> offline</small></div></a>
    <a href="users.php?tab=resets"   class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px"><span class="qi-icon">🔑</span><div><strong>Password Resets</strong><small><?=$kpis['pending_resets']?> pending</small></div></a>
    <a href="inventory.php"          class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px"><span class="qi-icon">📦</span><div><strong>Inventory</strong><small>Asset register</small></div></a>
    <a href="security.php"           class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px"><span class="qi-icon">🔐</span><div><strong>Security Log</strong><small>Monitor activity</small></div></a>
  </div>
</div>

<div style="display:grid;grid-template-columns:3fr 2fr;gap:16px;margin-bottom:16px">
  <!-- Open tickets -->
  <div class="panel" style="padding:18px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <h3 style="font-weight:700;font-size:13px">🎫 Active Tickets</h3>
      <a href="tickets.php" style="font-size:12px;color:var(--primary)">All →</a>
    </div>
    <?php if(empty($recentTickets)):?><p class="muted" style="font-size:13px">No open tickets.</p>
    <?php else:?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Ref</th><th>Subject</th><th>Category</th><th>Priority</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach($recentTickets as $t):?>
          <tr>
            <td style="font-size:11px;font-family:monospace"><a href="tickets.php?id=<?=$t['id']?>" style="color:var(--primary)"><?=e($t['ticket_ref'])?></a></td>
            <td style="max-width:160px"><div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px"><?=e($t['subject'])?></div><div style="font-size:11px;color:var(--ink-faint)"><?=e($t['reporter_name']??'Unknown')?></div></td>
            <td class="muted" style="font-size:12px"><?=e($t['category'])?></td>
            <td><span style="font-size:11px;font-weight:800;color:<?=$pp[$t['priority']??'medium']?>"><?=ucfirst($t['priority'])?></span></td>
            <td><span style="font-size:11px;padding:2px 7px;border-radius:10px;background:<?=$pc[$t['status']??'open']?>;color:#fff;font-weight:600"><?=ucfirst(str_replace('_',' ',$t['status']))?></span></td>
          </tr>
          <?php endforeach;?>
        </tbody>
      </table>
    </div>
    <?php endif;?>
  </div>
  <!-- Asset breakdown + alerts -->
  <div style="display:flex;flex-direction:column;gap:12px">
    <div class="panel" style="padding:18px">
      <h3 style="font-weight:700;font-size:13px;margin-bottom:12px">💻 Assets by Type</h3>
      <?php if(empty($byType)):?><p class="muted" style="font-size:13px">No assets registered.</p>
      <?php else: $maxT=max(array_column($byType,'n')); foreach($byType as $t): $w=round($t['n']/$maxT*100);?>
      <div style="margin-bottom:7px">
        <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:2px"><span><?=e($t['asset_type'])?></span><strong><?=$t['n']?></strong></div>
        <div style="height:6px;background:var(--bg2);border-radius:4px"><div style="width:<?=$w?>%;height:100%;background:var(--blue);border-radius:4px"></div></div>
      </div>
      <?php endforeach;endif;?>
    </div>
    <?php if(!empty($alertAssets)):?>
    <div class="panel" style="padding:18px;border-top:3px solid var(--warning)">
      <h3 style="font-weight:700;font-size:13px;margin-bottom:10px;color:var(--warning)">⚠️ Assets Need Attention</h3>
      <?php foreach($alertAssets as $a):?>
      <div style="padding:7px 0;border-bottom:1px solid var(--line)">
        <a href="assets.php?id=<?=$a['id']?>" style="font-size:13px;font-weight:600;color:var(--ink);text-decoration:none"><?=e($a['asset_id'])?> — <?=e($a['brand']??'')?> <?=e($a['model']??'')?></a>
        <div style="font-size:11.5px;color:var(--warning)"><?=e($a['condition_status'])?></div>
      </div>
      <?php endforeach;?>
    </div>
    <?php endif;?>
  </div>
</div>

</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
