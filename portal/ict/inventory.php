<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['ict_officer','sys_admin','school_admin']);
$activePage='inventory'; $pdo=db(); $user=currentUser();
$officer=null; try{$s=$pdo->prepare("SELECT * FROM staff WHERE user_id=? LIMIT 1");$s->execute([$user['id']]);$officer=$s->fetch()?:null;}catch(Throwable $e){}
if(!$officer) $officer=['first_name'=>$user['username']??'ICT','last_name'=>'','id'=>0];

$tab=$_GET['tab']??'register';

// Inventory summary stats
$stats=[];
foreach([
  'total'         =>"SELECT COUNT(*) FROM ict_assets",
  'active'        =>"SELECT COUNT(*) FROM ict_assets WHERE status='active'",
  'in_repair'     =>"SELECT COUNT(*) FROM ict_assets WHERE status='in_repair'",
  'decommissioned'=>"SELECT COUNT(*) FROM ict_assets WHERE status='decommissioned'",
  'lost'          =>"SELECT COUNT(*) FROM ict_assets WHERE status='lost'",
  'good'          =>"SELECT COUNT(*) FROM ict_assets WHERE condition_status='Good' AND status='active'",
  'needs_repair'  =>"SELECT COUNT(*) FROM ict_assets WHERE condition_status='Needs Repair'",
  'damaged'       =>"SELECT COUNT(*) FROM ict_assets WHERE condition_status='Damaged'",
  'warranty_exp'  =>"SELECT COUNT(*) FROM ict_assets WHERE warranty_expiry IS NOT NULL AND warranty_expiry<CURDATE() AND status='active'",
  'warranty_soon' =>"SELECT COUNT(*) FROM ict_assets WHERE warranty_expiry IS NOT NULL AND warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 30 DAY)",
] as $k=>$q){ try{$stats[$k]=(int)$pdo->query($q)->fetchColumn();}catch(Throwable $e){$stats[$k]=0;} }

// By type breakdown
try{$byType=$pdo->query("SELECT asset_type,COUNT(*) total,SUM(status='active') active,SUM(condition_status IN ('Needs Repair','Damaged')) needs_attn FROM ict_assets GROUP BY asset_type ORDER BY total DESC")->fetchAll();}catch(Throwable $e){$byType=[];}
// By location
try{$byLoc=$pdo->query("SELECT COALESCE(location,'Unknown') location,COUNT(*) total FROM ict_assets WHERE status='active' GROUP BY location ORDER BY total DESC LIMIT 10")->fetchAll();}catch(Throwable $e){$byLoc=[];}
// Full register
try{$register=$pdo->query("SELECT a.*,u.username assigned_user FROM ict_assets a LEFT JOIN users u ON u.id=a.assigned_to_id ORDER BY a.asset_type,a.asset_id")->fetchAll();}catch(Throwable $e){$register=[];}
// Maintenance scheduled
try{$scheduled=$pdo->query("SELECT ml.*,a.asset_id,a.brand,a.model FROM ict_maintenance_log ml JOIN ict_assets a ON a.id=ml.asset_id WHERE ml.status IN ('in_progress','pending') ORDER BY ml.performed_at ASC LIMIT 20")->fetchAll();}catch(Throwable $e){$scheduled=[];}

$condColors=['Good'=>'var(--green)','Fair'=>'var(--warning)','Needs Repair'=>'var(--error)','Damaged'=>'var(--error)','Decommissioned'=>'var(--ink-soft)'];
$stColors=['active'=>'var(--green)','in_repair'=>'var(--warning)','reserved'=>'var(--blue)','decommissioned'=>'var(--ink-soft)','lost'=>'var(--error)'];
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>ICT Inventory — ICT Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">
<div class="page-heading">
  <div><h1>ICT Inventory</h1><p>Complete asset register and condition tracking</p></div>
  <a href="assets.php?action=new" class="button button-primary">+ Register Asset</a>
</div>

<!-- Inventory KPIs -->
<div class="metric-grid" style="margin-bottom:16px">
  <div class="metric-card"><div class="metric-top"><span>Total Assets</span><div class="metric-icon">📦</div></div><strong><?=$stats['total']?></strong><small><i></i><?=$stats['active']?> active</small></div>
  <div class="metric-card <?=$stats['needs_repair']>0?'finance-metrics':''?>"><div class="metric-top"><span>Needs Repair</span><div class="metric-icon">🔧</div></div><strong style="color:<?=$stats['needs_repair']>0?'var(--error)':'inherit'?>"><?=$stats['needs_repair']?></strong><small><i></i><?=$stats['damaged']?> damaged</small></div>
  <div class="metric-card"><div class="metric-top"><span>In Repair</span><div class="metric-icon">⚙️</div></div><strong style="color:var(--warning)"><?=$stats['in_repair']?></strong></div>
  <div class="metric-card <?=$stats['lost']>0?'finance-metrics':''?>"><div class="metric-top"><span>Lost</span><div class="metric-icon">❓</div></div><strong style="color:<?=$stats['lost']>0?'var(--error)':'inherit'?>"><?=$stats['lost']?></strong></div>
  <div class="metric-card <?=$stats['warranty_exp']>0?'finance-metrics':''?>"><div class="metric-top"><span>Warranty Expired</span><div class="metric-icon">📅</div></div><strong style="color:<?=$stats['warranty_exp']>0?'var(--error)':'inherit'?>"><?=$stats['warranty_exp']?></strong><small><i></i><?=$stats['warranty_soon']?> expiring soon</small></div>
  <div class="metric-card"><div class="metric-top"><span>Good Condition</span><div class="metric-icon">✅</div></div><strong style="color:var(--green)"><?=$stats['good']?></strong><small><i></i>of <?=$stats['active']?> active</small></div>
</div>

<div class="tab-bar" style="margin-bottom:16px">
  <a href="?tab=register"    class="tab-btn <?=$tab==='register'?'active':''?>">📋 Full Register</a>
  <a href="?tab=by_type"     class="tab-btn <?=$tab==='by_type' ?'active':''?>">📊 By Type</a>
  <a href="?tab=by_location" class="tab-btn <?=$tab==='by_location'?'active':''?>">📍 By Location</a>
  <a href="?tab=maintenance" class="tab-btn <?=$tab==='maintenance'?'active':''?>">🔧 Maintenance</a>
</div>

<?php if($tab==='register'): ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Asset ID</th><th>Type</th><th>Brand / Model</th><th>Serial</th><th>Assigned To</th><th>Location</th><th>Condition</th><th>Status</th><th>Warranty</th></tr></thead>
    <tbody>
      <?php if(empty($register)):?><tr><td colspan="9" style="text-align:center;color:var(--ink-faint);padding:32px">No assets registered. <a href="assets.php?action=new">Add one →</a></td></tr>
      <?php else:foreach($register as $a):?>
      <tr>
        <td><a href="assets.php?id=<?=$a['id']?>" style="font-family:monospace;font-size:12px;color:var(--primary);font-weight:700"><?=e($a['asset_id'])?></a></td>
        <td class="muted"><?=e($a['asset_type'])?></td>
        <td><strong style="font-size:13px"><?=e($a['brand']??'—')?></strong><div style="font-size:11px;color:var(--ink-faint)"><?=e($a['model']??'')?></div></td>
        <td style="font-family:monospace;font-size:11px" class="muted"><?=e($a['serial_number']??'—')?></td>
        <td class="muted"><?=e($a['assigned_to_name']??$a['assigned_user']??'—')?></td>
        <td class="muted"><?=e($a['location']??'—')?></td>
        <td><span style="font-size:11px;font-weight:700;color:<?=$condColors[$a['condition_status']??'Good']?>"><?=e($a['condition_status']??'Good')?></span></td>
        <td><span style="font-size:11px;padding:2px 7px;border-radius:10px;background:<?=$stColors[$a['status']??'active']?>;color:#fff;font-weight:600"><?=ucfirst(str_replace('_',' ',$a['status']??'active'))?></span></td>
        <td style="font-size:11px;color:<?=$a['warranty_expiry']&&$a['warranty_expiry']<date('Y-m-d')?'var(--error)':'var(--ink-soft)'?>"><?=$a['warranty_expiry']?date('d M Y',strtotime($a['warranty_expiry'])):'—'?></td>
      </tr>
      <?php endforeach;endif;?>
    </tbody>
  </table>
</div>

<?php elseif($tab==='by_type'): ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Asset Type</th><th>Total</th><th>Active</th><th>Needs Attention</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($byType as $t):?>
      <tr>
        <td><strong><?=e($t['asset_type'])?></strong></td>
        <td><?=$t['total']?></td>
        <td style="color:var(--green)"><?=$t['active']?></td>
        <td style="color:<?=$t['needs_attn']>0?'var(--error)':'var(--ink-faint)'?>"><?=$t['needs_attn']?$t['needs_attn']:'—'?></td>
        <td><a href="assets.php?type=<?=urlencode($t['asset_type'])?>" style="font-size:12px;color:var(--primary)">View all →</a></td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>

<?php elseif($tab==='by_location'): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
  <?php $maxL=max(array_column($byLoc,'total')?:[1]); foreach($byLoc as $l): $w=round($l['total']/$maxL*100);?>
  <div style="padding:12px 16px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius-sm)">
    <div style="display:flex;justify-content:space-between;margin-bottom:6px"><strong><?=e($l['location'])?></strong><span style="font-weight:700;color:var(--primary)"><?=$l['total']?> asset<?=$l['total']!=1?'s':''?></span></div>
    <div style="height:7px;background:var(--bg2);border-radius:4px"><div style="width:<?=$w?>%;height:100%;background:var(--blue);border-radius:4px"></div></div>
    <a href="assets.php?q=<?=urlencode($l['location'])?>" style="font-size:11px;color:var(--primary);margin-top:4px;display:block">View assets →</a>
  </div>
  <?php endforeach;?>
</div>

<?php else: ?><!-- Maintenance tab -->
<div class="table-wrap">
  <table>
    <thead><tr><th>Asset</th><th>Type</th><th>Description</th><th>Assigned Technician</th><th>Scheduled</th><th>Cost</th><th>Status</th></tr></thead>
    <tbody>
      <?php if(empty($scheduled)):?><tr><td colspan="7" style="text-align:center;color:var(--ink-faint);padding:24px">No pending maintenance tasks.</td></tr>
      <?php else:foreach($scheduled as $m):?>
      <tr>
        <td><a href="assets.php?id=<?=$m['asset_id_rel']??'0'?>" style="font-family:monospace;font-size:12px;color:var(--primary)"><?=e($m['asset_id'])?></a><div style="font-size:11px;color:var(--ink-faint)"><?=e($m['brand']??'')?> <?=e($m['model']??'')?></div></td>
        <td class="muted"><?=e(ucfirst(str_replace('_',' ',$m['maintenance_type'])))?></td>
        <td style="max-width:180px"><?=e(mb_substr($m['description'],0,70))?></td>
        <td class="muted"><?=e($m['performed_by']??'—')?></td>
        <td class="muted"><?=date('d M Y',strtotime($m['performed_at']))?></td>
        <td><?=$m['cost']?'GHS '.number_format($m['cost'],2):'—'?></td>
        <td><span class="status <?=$m['status']==='completed'?'approved':'new-s'?>" style="font-size:10px"><?=ucfirst($m['status'])?></span></td>
      </tr>
      <?php endforeach;endif;?>
    </tbody>
  </table>
</div>
<?php endif;?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
