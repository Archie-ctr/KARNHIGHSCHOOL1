<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['ict_officer','sys_admin','school_admin']);
$activePage='assets'; $pdo=db(); $user=currentUser();
$officer=null; try{$s=$pdo->prepare("SELECT * FROM staff WHERE user_id=? LIMIT 1");$s->execute([$user['id']]);$officer=$s->fetch()?:null;}catch(Throwable $e){}
if(!$officer) $officer=['first_name'=>$user['username']??'ICT','last_name'=>'','id'=>0];

$action=$_GET['action']??''; $assetId=(int)($_GET['id']??0);

if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf(); $pa=$_POST['action']??'';
    if($pa==='create'){
        try{
            $ref=strtoupper(trim($_POST['asset_id']));
            if(!$ref) $ref='KHS-'.strtoupper(substr($_POST['asset_type']??'IT',0,3)).'-'.str_pad((int)$pdo->query("SELECT COUNT(*)+1 FROM ict_assets")->fetchColumn(),3,'0',STR_PAD_LEFT);
            $pdo->prepare("INSERT INTO ict_assets (asset_id,asset_type,brand,model,serial_number,purchase_date,warranty_expiry,assigned_to_name,location,condition_status,os_installed,software_notes,ip_address,mac_address,notes,status,added_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$ref,$_POST['asset_type'],trim($_POST['brand']??''),trim($_POST['model']??''),trim($_POST['serial_number']??''),$_POST['purchase_date']?:null,$_POST['warranty_expiry']?:null,trim($_POST['assigned_to_name']??''),trim($_POST['location']??''),$_POST['condition_status']??'Good',trim($_POST['os_installed']??''),trim($_POST['software_notes']??''),trim($_POST['ip_address']??''),trim($_POST['mac_address']??''),trim($_POST['notes']??''),$_POST['status']??'active',$user['id']]);
            flash('success','Asset '.$ref.' registered.');
        }catch(Throwable $e){flash('error','Failed: '.$e->getMessage());}
        redirect(BASE_URL.'/portal/ict/assets.php');
    }
    if($pa==='update'&&$assetId){
        try{
            $pdo->prepare("UPDATE ict_assets SET asset_type=?,brand=?,model=?,serial_number=?,purchase_date=?,warranty_expiry=?,assigned_to_name=?,location=?,condition_status=?,os_installed=?,software_notes=?,ip_address=?,mac_address=?,notes=?,status=?,updated_at=NOW() WHERE id=?")
                ->execute([$_POST['asset_type'],trim($_POST['brand']??''),trim($_POST['model']??''),trim($_POST['serial_number']??''),$_POST['purchase_date']?:null,$_POST['warranty_expiry']?:null,trim($_POST['assigned_to_name']??''),trim($_POST['location']??''),$_POST['condition_status']??'Good',trim($_POST['os_installed']??''),trim($_POST['software_notes']??''),trim($_POST['ip_address']??''),trim($_POST['mac_address']??''),trim($_POST['notes']??''),$_POST['status']??'active',$assetId]);
            flash('success','Asset updated.');
        }catch(Throwable $e){flash('error','Failed: '.$e->getMessage());}
        redirect(BASE_URL.'/portal/ict/assets.php?id='.$assetId);
    }
    if($pa==='log_maintenance'&&$assetId){
        try{
            $pdo->prepare("INSERT INTO ict_maintenance_log (asset_id,maintenance_type,description,performed_by,performed_at,cost,status,logged_by) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$assetId,$_POST['maintenance_type'],trim($_POST['description']),$_POST['performed_by']??'',$_POST['performed_at']??date('Y-m-d H:i:s'),(float)($_POST['cost']??0)?:null,$_POST['maint_status']??'completed',$user['id']]);
            // Update asset condition if changed
            if(!empty($_POST['new_condition'])) $pdo->prepare("UPDATE ict_assets SET condition_status=?,updated_at=NOW() WHERE id=?")->execute([$_POST['new_condition'],$assetId]);
            flash('success','Maintenance record added.');
        }catch(Throwable $e){flash('error','Failed: '.$e->getMessage());}
        redirect(BASE_URL.'/portal/ict/assets.php?id='.$assetId.'#maintenance');
    }
    if($pa==='delete'&&$assetId){
        try{$pdo->prepare("DELETE FROM ict_assets WHERE id=?")->execute([$assetId]);flash('success','Asset removed.');}
        catch(Throwable $e){flash('error','Cannot delete — maintenance records exist. Decommission instead.');}
        redirect(BASE_URL.'/portal/ict/assets.php');
    }
}

$asset=null; $maint=[];
if($assetId){
    try{$asset=$pdo->query("SELECT * FROM ict_assets WHERE id=$assetId")->fetch();}catch(Throwable $e){}
    try{$maint=$pdo->query("SELECT ml.*,u.username logged_by_name FROM ict_maintenance_log ml LEFT JOIN users u ON u.id=ml.logged_by WHERE ml.asset_id=$assetId ORDER BY ml.performed_at DESC")->fetchAll();}catch(Throwable $e){$maint=[];}
}

$fSearch=trim($_GET['q']??''); $fType=$_GET['type']??''; $fStatus=$_GET['status']??'active'; $fCond=$_GET['condition']??'';
$where=['1=1']; $params=[];
if($fStatus){$where[]="a.status=?";$params[]=$fStatus;}
if($fType){$where[]="a.asset_type=?";$params[]=$fType;}
if($fCond){$where[]="a.condition_status=?";$params[]=$fCond;}
if($fSearch){$where[]="(a.asset_id LIKE ? OR a.brand LIKE ? OR a.model LIKE ? OR a.serial_number LIKE ? OR a.assigned_to_name LIKE ? OR a.location LIKE ?)";$p="%$fSearch%";$params=array_merge($params,[$p,$p,$p,$p,$p,$p]);}
try{
    $assets=$pdo->prepare("SELECT a.* FROM ict_assets a WHERE ".implode(' AND ',$where)." ORDER BY a.asset_type,a.asset_id LIMIT 200");
    $assets->execute($params);$assets=$assets->fetchAll();
}catch(Throwable $e){$assets=[];}

$assetTypes=['Desktop','Laptop','Printer','Projector','Server','Router','Switch','Access Point','UPS','Monitor','Tablet','Camera','Scanner','Other'];
$conditions=['Good','Fair','Needs Repair','Damaged','Decommissioned'];
$statuses=['active','in_repair','reserved','decommissioned','lost'];
$maintTypes=['repair','service','upgrade','replacement','inspection','cleaning','software_install','other'];
$condColors=['Good'=>'var(--green)','Fair'=>'var(--warning)','Needs Repair'=>'var(--error)','Damaged'=>'var(--error)','Decommissioned'=>'var(--ink-soft)'];
$stColors=['active'=>'var(--green)','in_repair'=>'var(--warning)','reserved'=>'var(--blue)','decommissioned'=>'var(--ink-soft)','lost'=>'var(--error)'];
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>IT Assets — ICT Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">
<div class="page-heading">
  <div><h1><?=$assetId&&$asset?e($asset['asset_id'].': '.$asset['brand'].' '.$asset['model']):($action==='new'?'Register New Asset':'IT Assets')?></h1>
  <p><?=!$assetId&&$action!=='new'?count($assets).' asset'.((count($assets))!=1?'s':'').' found':'ICT Equipment Register'?></p></div>
  <div style="display:flex;gap:8px">
    <?php if($assetId):?><a href="assets.php" class="button button-secondary">← All Assets</a><?php endif;?>
    <?php if(!$assetId&&$action!=='new'):?><a href="?action=new" class="button button-primary">+ Register Asset</a><?php endif;?>
  </div>
</div>
<?php foreach(getFlash() as $f):?><div class="alert alert-<?=$f['type']?>"><?=e($f['message'])?></div><?php endforeach;?>

<?php if($action==='new'||($assetId&&$action==='edit'&&$asset)): ?>
<div class="panel" style="padding:22px;margin-bottom:16px">
  <h3 style="font-weight:700;margin-bottom:16px"><?=$assetId?'Edit Asset':'Register New IT Asset'?></h3>
  <form method="post" action="?<?=$assetId?"id=$assetId":''?>">
    <?=csrfField()?><input type="hidden" name="action" value="<?=$assetId?'update':'create'?>"/>
    <div class="form-grid">
      <div class="form-group"><label>Asset ID<?=!$assetId?' <small style="color:var(--ink-soft)">(auto if blank)</small>':''?></label><input type="text" name="asset_id" value="<?=e($asset['asset_id']??'')?>" placeholder="e.g. KHS-PC-001" <?=$assetId?'readonly style="background:var(--bg2)"':''?>/></div>
      <div class="form-group"><label>Asset Type <span style="color:var(--error)">*</span></label>
        <select name="asset_type" required><?php foreach($assetTypes as $t):?><option value="<?=$t?>" <?=($asset['asset_type']??'')===$t?'selected':''?>><?=$t?></option><?php endforeach;?></select>
      </div>
      <div class="form-group"><label>Brand</label><input type="text" name="brand" value="<?=e($asset['brand']??'')?>" placeholder="e.g. Dell, HP, Cisco"/></div>
      <div class="form-group"><label>Model</label><input type="text" name="model" value="<?=e($asset['model']??'')?>" placeholder="e.g. OptiPlex 3080"/></div>
      <div class="form-group"><label>Serial Number</label><input type="text" name="serial_number" value="<?=e($asset['serial_number']??'')?>"/></div>
      <div class="form-group"><label>Condition</label>
        <select name="condition_status"><?php foreach($conditions as $c):?><option value="<?=$c?>" <?=($asset['condition_status']??'Good')===$c?'selected':''?>><?=$c?></option><?php endforeach;?></select>
      </div>
      <div class="form-group"><label>Status</label>
        <select name="status"><?php foreach($statuses as $st):?><option value="<?=$st?>" <?=($asset['status']??'active')===$st?'selected':''?>><?=ucfirst(str_replace('_',' ',$st))?></option><?php endforeach;?></select>
      </div>
      <div class="form-group"><label>Assigned To</label><input type="text" name="assigned_to_name" value="<?=e($asset['assigned_to_name']??'')?>" placeholder="Person name or Lab/Room"/></div>
      <div class="form-group"><label>Location</label><input type="text" name="location" value="<?=e($asset['location']??'')?>" placeholder="e.g. ICT Lab, Staff Room"/></div>
      <div class="form-group"><label>Purchase Date</label><input type="date" name="purchase_date" value="<?=e($asset['purchase_date']??'')?>"/></div>
      <div class="form-group"><label>Warranty Expiry</label><input type="date" name="warranty_expiry" value="<?=e($asset['warranty_expiry']??'')?>"/></div>
      <div class="form-group"><label>OS / Firmware</label><input type="text" name="os_installed" value="<?=e($asset['os_installed']??'')?>" placeholder="e.g. Windows 11 Pro"/></div>
      <div class="form-group"><label>IP Address</label><input type="text" name="ip_address" value="<?=e($asset['ip_address']??'')?>" placeholder="e.g. 192.168.1.10"/></div>
      <div class="form-group"><label>MAC Address</label><input type="text" name="mac_address" value="<?=e($asset['mac_address']??'')?>"/></div>
      <div class="form-group" style="grid-column:1/-1"><label>Software / Notes</label><textarea name="software_notes" rows="2" placeholder="Installed software, licence keys, special config…"><?=e($asset['software_notes']??'')?></textarea></div>
      <div class="form-group" style="grid-column:1/-1"><label>Additional Notes</label><textarea name="notes" rows="2"><?=e($asset['notes']??'')?></textarea></div>
    </div>
    <div style="display:flex;gap:10px;margin-top:8px"><button type="submit" class="button button-primary">💾 <?=$assetId?'Update':'Register Asset'?></button><a href="assets.php<?=$assetId?"?id=$assetId":''?>" class="button button-secondary">Cancel</a></div>
  </form>
</div>

<?php elseif($assetId&&$asset): ?>
<div style="display:grid;grid-template-columns:1fr 280px;gap:16px">
  <div>
    <div class="panel" style="padding:20px;margin-bottom:14px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;margin-bottom:14px">
        <div><h3 style="font-weight:800;font-size:16px;margin-bottom:2px"><?=e($asset['brand']??'')?> <?=e($asset['model']??$asset['asset_type'])?></h3>
          <div style="font-size:12px;font-family:monospace;color:var(--ink-soft)"><?=e($asset['asset_id'])?> &middot; <?=e($asset['asset_type'])?></div></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <span style="padding:4px 12px;border-radius:10px;font-size:12px;font-weight:700;color:<?=$condColors[$asset['condition_status']??'Good']?>;border:1.5px solid <?=$condColors[$asset['condition_status']??'Good']?>"><?=e($asset['condition_status']??'Good')?></span>
          <span style="padding:4px 12px;border-radius:10px;font-size:12px;font-weight:700;background:<?=$stColors[$asset['status']??'active']?>;color:#fff"><?=ucfirst(str_replace('_',' ',$asset['status']??'active'))?></span>
          <a href="?id=<?=$assetId?>&action=edit" class="button button-secondary button-sm">✏️ Edit</a>
        </div>
      </div>
      <div class="form-grid">
        <div><label>Serial Number</label><p style="font-family:monospace;font-size:13px"><?=e($asset['serial_number']??'—')?></p></div>
        <div><label>Assigned To</label><p><?=e($asset['assigned_to_name']??'—')?></p></div>
        <div><label>Location</label><p><?=e($asset['location']??'—')?></p></div>
        <div><label>OS / Firmware</label><p><?=e($asset['os_installed']??'—')?></p></div>
        <div><label>IP Address</label><p style="font-family:monospace"><?=e($asset['ip_address']??'—')?></p></div>
        <div><label>MAC Address</label><p style="font-family:monospace"><?=e($asset['mac_address']??'—')?></p></div>
        <div><label>Purchase Date</label><p><?=$asset['purchase_date']?date('d M Y',strtotime($asset['purchase_date'])):'—'?></p></div>
        <div><label>Warranty Expiry</label><p style="color:<?=$asset['warranty_expiry']&&$asset['warranty_expiry']<date('Y-m-d')?'var(--error)':'inherit'?>"><?=$asset['warranty_expiry']?date('d M Y',strtotime($asset['warranty_expiry'])):'—'?><?=$asset['warranty_expiry']&&$asset['warranty_expiry']<date('Y-m-d')?' ⚠️ Expired':''?></p></div>
        <?php if($asset['software_notes']):?><div style="grid-column:1/-1"><label>Software</label><p style="white-space:pre-line"><?=e($asset['software_notes'])?></p></div><?php endif;?>
        <?php if($asset['notes']):?><div style="grid-column:1/-1"><label>Notes</label><p><?=e($asset['notes'])?></p></div><?php endif;?>
      </div>
    </div>

    <!-- Maintenance log -->
    <div class="panel" style="padding:20px" id="maintenance">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <h3 style="font-weight:700;font-size:13px">🔧 Maintenance History</h3>
        <button onclick="document.getElementById('maintForm').style.display='block';this.style.display='none'" class="button button-secondary button-sm">+ Log Maintenance</button>
      </div>
      <div id="maintForm" style="display:none;background:var(--bg2);border-radius:var(--radius-sm);padding:14px;margin-bottom:14px">
        <form method="post" action="?id=<?=$assetId?>#maintenance">
          <?=csrfField()?><input type="hidden" name="action" value="log_maintenance"/>
          <div class="form-grid">
            <div class="form-group"><label>Type</label><select name="maintenance_type"><?php foreach($maintTypes as $mt):?><option value="<?=$mt?>"><?=ucfirst(str_replace('_',' ',$mt))?></option><?php endforeach;?></select></div>
            <div class="form-group"><label>Performed By</label><input type="text" name="performed_by" placeholder="Technician / Vendor"/></div>
            <div class="form-group"><label>Date</label><input type="datetime-local" name="performed_at" value="<?=date('Y-m-d\TH:i')?>"/></div>
            <div class="form-group"><label>Cost (GHS)</label><input type="number" name="cost" min="0" step="0.01" placeholder="0.00"/></div>
            <div class="form-group"><label>Update Condition</label><select name="new_condition"><option value="">No change</option><?php foreach($conditions as $c):?><option value="<?=$c?>"><?=$c?></option><?php endforeach;?></select></div>
            <div class="form-group"><label>Maintenance Status</label><select name="maint_status"><option value="completed">Completed</option><option value="in_progress">In Progress</option><option value="pending">Pending</option></select></div>
            <div class="form-group" style="grid-column:1/-1"><label>Description *</label><textarea name="description" rows="2" required placeholder="What was done?"></textarea></div>
          </div>
          <div style="display:flex;gap:8px"><button type="submit" class="button button-primary button-sm">💾 Save</button><button type="button" onclick="document.getElementById('maintForm').style.display='none';this.closest('div').previousElementSibling.querySelector('button').style.display='inline-flex'" class="button button-secondary button-sm">Cancel</button></div>
        </form>
      </div>
      <?php if(empty($maint)):?><p class="muted" style="font-size:13px">No maintenance records yet.</p>
      <?php else:?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Date</th><th>Type</th><th>Description</th><th>By</th><th>Cost</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach($maint as $m):?>
            <tr>
              <td class="muted"><?=date('d M Y',strtotime($m['performed_at']))?></td>
              <td><?=e(ucfirst(str_replace('_',' ',$m['maintenance_type'])))?></td>
              <td style="max-width:200px"><?=e(mb_substr($m['description'],0,80))?><?=mb_strlen($m['description'])>80?'…':''?></td>
              <td class="muted"><?=e($m['performed_by']??'—')?></td>
              <td><?=$m['cost']?'GHS '.number_format($m['cost'],2):'—'?></td>
              <td><span class="status <?=$m['status']==='completed'?'approved':'new-s'?>" style="font-size:10px"><?=ucfirst($m['status'])?></span></td>
            </tr>
            <?php endforeach;?>
          </tbody>
        </table>
      </div>
      <?php endif;?>
    </div>
  </div>
  <div style="display:flex;flex-direction:column;gap:12px">
    <div class="panel" style="padding:18px">
      <h4 style="font-weight:700;font-size:13px;margin-bottom:10px">⚡ Actions</h4>
      <div style="display:flex;flex-direction:column;gap:8px">
        <a href="?id=<?=$assetId?>&action=edit" class="button button-secondary" style="text-align:center">✏️ Edit Asset</a>
        <a href="tickets.php?action=new&asset_id=<?=$assetId?>" class="button button-secondary" style="text-align:center">🎫 Log Ticket for this Asset</a>
        <form method="post" action="?id=<?=$assetId?>" onsubmit="return confirm('Delete this asset?')"><<?=csrfField()?><input type="hidden" name="action" value="delete"/><button class="button button-danger" style="width:100%">🗑 Delete</button></form>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<form method="get" class="filter-bar" style="margin-bottom:12px">
  <input type="text" name="q" placeholder="Asset ID, brand, model, serial…" value="<?=e($fSearch)?>"/>
  <select name="type"><option value="">All Types</option><?php foreach($assetTypes as $t):?><option value="<?=$t?>" <?=$fType===$t?'selected':''?>><?=$t?></option><?php endforeach;?></select>
  <select name="status"><option value="active" <?=$fStatus==='active'?'selected':''?>>Active</option><option value="in_repair" <?=$fStatus==='in_repair'?'selected':''?>>In Repair</option><option value="" <?=$fStatus===''?'selected':''?>>All</option></select>
  <select name="condition"><option value="">All Conditions</option><?php foreach($conditions as $c):?><option value="<?=$c?>" <?=$fCond===$c?'selected':''?>><?=$c?></option><?php endforeach;?></select>
  <button type="submit" class="button button-secondary button-sm">Filter</button>
  <a href="assets.php" class="button button-secondary button-sm">Reset</a>
</form>
<div class="table-wrap">
  <table>
    <thead><tr><th>Asset ID</th><th>Type</th><th>Brand / Model</th><th>Serial</th><th>Assigned To</th><th>Location</th><th>Condition</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php if(empty($assets)):?><tr><td colspan="9" style="text-align:center;color:var(--ink-faint);padding:32px">No assets found. <a href="?action=new">Register one →</a></td></tr>
      <?php else:foreach($assets as $a):?>
      <tr>
        <td style="font-family:monospace;font-size:12px;font-weight:700"><a href="?id=<?=$a['id']?>" style="color:var(--primary)"><?=e($a['asset_id'])?></a></td>
        <td class="muted"><?=e($a['asset_type'])?></td>
        <td><strong><?=e($a['brand']??'—')?></strong><div style="font-size:11px;color:var(--ink-faint)"><?=e($a['model']??'')?></div></td>
        <td style="font-family:monospace;font-size:11px" class="muted"><?=e($a['serial_number']??'—')?></td>
        <td class="muted"><?=e($a['assigned_to_name']??'—')?></td>
        <td class="muted"><?=e($a['location']??'—')?></td>
        <td><span style="font-size:11px;font-weight:700;color:<?=$condColors[$a['condition_status']??'Good']?>"><?=e($a['condition_status']??'Good')?></span></td>
        <td><span style="font-size:11px;padding:2px 7px;border-radius:10px;background:<?=$stColors[$a['status']??'active']?>;color:#fff;font-weight:600"><?=ucfirst(str_replace('_',' ',$a['status']??'active'))?></span></td>
        <td><a href="?id=<?=$a['id']?>" class="button button-secondary button-sm">View</a></td>
      </tr>
      <?php endforeach;endif;?>
    </tbody>
  </table>
</div>
<?php endif;?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
