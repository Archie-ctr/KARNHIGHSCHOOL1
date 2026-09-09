<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['ict_officer','sys_admin','school_admin']);
$activePage='network'; $pdo=db(); $user=currentUser();
$officer=null; try{$s=$pdo->prepare("SELECT * FROM staff WHERE user_id=? LIMIT 1");$s->execute([$user['id']]);$officer=$s->fetch()?:null;}catch(Throwable $e){}
if(!$officer) $officer=['first_name'=>$user['username']??'ICT','last_name'=>'','id'=>0];

$action=$_GET['action']??''; $devId=(int)($_GET['id']??0);

if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf(); $pa=$_POST['action']??'';
    if($pa==='create'){
        try{
            $pdo->prepare("INSERT INTO ict_network_devices (device_name,device_type,ip_address,mac_address,location,brand,model,firmware_version,ssid,status,notes,added_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([trim($_POST['device_name']),$_POST['device_type'],trim($_POST['ip_address']??''),trim($_POST['mac_address']??''),trim($_POST['location']??''),trim($_POST['brand']??''),trim($_POST['model']??''),trim($_POST['firmware_version']??''),trim($_POST['ssid']??''),$_POST['status']??'online',trim($_POST['notes']??''),$user['id']]);
            flash('success','Network device added.');
        }catch(Throwable $e){flash('error','Failed: '.$e->getMessage());}
        redirect(BASE_URL.'/portal/ict/network.php');
    }
    if($pa==='update'&&$devId){
        try{
            $pdo->prepare("UPDATE ict_network_devices SET device_name=?,device_type=?,ip_address=?,mac_address=?,location=?,brand=?,model=?,firmware_version=?,ssid=?,status=?,uptime_note=?,notes=?,updated_at=NOW() WHERE id=?")
                ->execute([trim($_POST['device_name']),$_POST['device_type'],trim($_POST['ip_address']??''),trim($_POST['mac_address']??''),trim($_POST['location']??''),trim($_POST['brand']??''),trim($_POST['model']??''),trim($_POST['firmware_version']??''),trim($_POST['ssid']??''),$_POST['status']??'online',trim($_POST['uptime_note']??''),trim($_POST['notes']??''),$devId]);
            flash('success','Device updated.');
        }catch(Throwable $e){flash('error','Failed.');}
        redirect(BASE_URL.'/portal/ict/network.php');
    }
    if($pa==='update_status'&&$devId){
        try{$pdo->prepare("UPDATE ict_network_devices SET status=?,last_seen=NOW(),updated_at=NOW() WHERE id=?")->execute([$_POST['status'],$devId]);flash('success','Status updated.');}
        catch(Throwable $e){flash('error','Failed.');}
        redirect(BASE_URL.'/portal/ict/network.php');
    }
    if($pa==='delete'&&$devId){
        try{$pdo->prepare("DELETE FROM ict_network_devices WHERE id=?")->execute([$devId]);flash('success','Device removed.');}
        catch(Throwable $e){flash('error','Delete failed.');}
        redirect(BASE_URL.'/portal/ict/network.php');
    }
}

try{$devices=$pdo->query("SELECT * FROM ict_network_devices ORDER BY FIELD(status,'offline','degraded','maintenance','online','unknown'),device_type,device_name")->fetchAll();}catch(Throwable $e){$devices=[];}
$byStatus=['online'=>0,'offline'=>0,'degraded'=>0,'maintenance'=>0,'unknown'=>0];
foreach($devices as $d) $byStatus[$d['status']??'unknown']=($byStatus[$d['status']??'unknown']??0)+1;

$devTypes=['Router','Switch','Access Point','Firewall','Server','Modem','NAS','UPS','CCTV DVR','Other'];
$statuses=['online','offline','degraded','maintenance','unknown'];
$stColors=['online'=>'var(--green)','offline'=>'var(--error)','degraded'=>'var(--warning)','maintenance'=>'var(--blue)','unknown'=>'var(--ink-soft)'];
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Network — ICT Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">
<div class="page-heading">
  <div><h1>Network</h1><p>Network devices and status monitoring</p></div>
  <a href="?action=new" class="button button-primary">+ Add Device</a>
</div>
<?php foreach(getFlash() as $f):?><div class="alert alert-<?=$f['type']?>"><?=e($f['message'])?></div><?php endforeach;?>

<!-- Status summary -->
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px">
  <?php foreach(['online'=>['🟢','Online','var(--green)'],'offline'=>['🔴','Offline','var(--error)'],'degraded'=>['🟡','Degraded','var(--warning)'],'maintenance'=>['🔵','Maintenance','var(--blue)'],'unknown'=>['⚪','Unknown','var(--ink-soft)']] as $st=>[$emoji,$lbl,$col]):?>
  <a href="network.php<?=$st!=='online'?"?status=$st":''?>" style="flex:1;min-width:100px;padding:12px 14px;background:var(--surface);border:1px solid var(--line);border-top:3px solid <?=$col?>;border-radius:var(--radius-sm);text-align:center;text-decoration:none">
    <div style="font-size:18px;font-weight:800;color:<?=$col?>"><?=$byStatus[$st]??0?></div>
    <div style="font-size:11.5px;font-weight:700;color:var(--ink-soft)"><?=$emoji?> <?=$lbl?></div>
  </a>
  <?php endforeach;?>
</div>

<?php if($action==='new'||($devId&&$action==='edit')): ?>
<?php $dev=null; if($devId) try{$dev=$pdo->query("SELECT * FROM ict_network_devices WHERE id=$devId")->fetch();}catch(Throwable $e){}?>
<div class="panel" style="padding:22px;margin-bottom:16px">
  <h3 style="font-weight:700;margin-bottom:16px"><?=$devId?'Edit Device':'Add Network Device'?></h3>
  <form method="post" action="?<?=$devId?"id=$devId":''?>">
    <?=csrfField()?><input type="hidden" name="action" value="<?=$devId?'update':'create'?>"/>
    <div class="form-grid">
      <div class="form-group"><label>Device Name <span style="color:var(--error)">*</span></label><input type="text" name="device_name" required value="<?=e($dev['device_name']??'')?>" placeholder="e.g. Main Router, Lab AP-01"/></div>
      <div class="form-group"><label>Type <span style="color:var(--error)">*</span></label><select name="device_type" required><?php foreach($devTypes as $t):?><option value="<?=$t?>" <?=($dev['device_type']??'')===$t?'selected':''?>><?=$t?></option><?php endforeach;?></select></div>
      <div class="form-group"><label>Brand</label><input type="text" name="brand" value="<?=e($dev['brand']??'')?>" placeholder="e.g. Cisco, TP-Link"/></div>
      <div class="form-group"><label>Model</label><input type="text" name="model" value="<?=e($dev['model']??'')?>"/></div>
      <div class="form-group"><label>IP Address</label><input type="text" name="ip_address" value="<?=e($dev['ip_address']??'')?>" placeholder="e.g. 192.168.1.1"/></div>
      <div class="form-group"><label>MAC Address</label><input type="text" name="mac_address" value="<?=e($dev['mac_address']??'')?>"/></div>
      <div class="form-group"><label>Location</label><input type="text" name="location" value="<?=e($dev['location']??'')?>" placeholder="e.g. Server Room, Block A"/></div>
      <div class="form-group"><label>SSID (Wi-Fi only)</label><input type="text" name="ssid" value="<?=e($dev['ssid']??'')?>" placeholder="Wi-Fi network name"/></div>
      <div class="form-group"><label>Firmware Version</label><input type="text" name="firmware_version" value="<?=e($dev['firmware_version']??'')?>"/></div>
      <div class="form-group"><label>Status</label><select name="status"><?php foreach($statuses as $st):?><option value="<?=$st?>" <?=($dev['status']??'online')===$st?'selected':''?>><?=ucfirst($st)?></option><?php endforeach;?></select></div>
      <?php if($devId):?><div class="form-group"><label>Uptime Note</label><input type="text" name="uptime_note" value="<?=e($dev['uptime_note']??'')?>" placeholder="e.g. Up 14d 3h"/></div><?php endif;?>
      <div class="form-group" style="grid-column:1/-1"><label>Notes</label><textarea name="notes" rows="2" placeholder="Configuration notes, VLAN info…"><?=e($dev['notes']??'')?></textarea></div>
    </div>
    <div style="display:flex;gap:10px;margin-top:8px"><button type="submit" class="button button-primary">💾 Save</button><a href="network.php" class="button button-secondary">Cancel</a></div>
  </form>
</div>
<?php endif;?>

<!-- Devices list -->
<?php $fSt=$_GET['status']??''; $filtDevices=$fSt?array_filter($devices,fn($d)=>$d['status']===$fSt):$devices;?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Device</th><th>Type</th><th>IP Address</th><th>Location</th><th>SSID</th><th>Firmware</th><th>Status</th><th>Last Seen</th><th>Actions</th></tr></thead>
    <tbody>
      <?php if(empty($filtDevices)):?><tr><td colspan="9" style="text-align:center;color:var(--ink-faint);padding:32px">No devices found. <a href="?action=new">Add one →</a></td></tr>
      <?php else:foreach($filtDevices as $d): $col=$stColors[$d['status']??'unknown'];?>
      <tr>
        <td><strong><?=e($d['device_name'])?></strong><div style="font-size:11px;color:var(--ink-faint)"><?=e($d['brand']??'')?> <?=e($d['model']??'')?></div></td>
        <td class="muted"><?=e($d['device_type'])?></td>
        <td style="font-family:monospace;font-size:12px"><?=e($d['ip_address']??'—')?></td>
        <td class="muted"><?=e($d['location']??'—')?></td>
        <td class="muted" style="font-size:12px"><?=e($d['ssid']??'—')?></td>
        <td class="muted" style="font-size:11px"><?=e($d['firmware_version']??'—')?></td>
        <td>
          <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;padding:3px 9px;border-radius:10px;background:<?=$col?>;color:#fff;font-weight:700"><?=ucfirst($d['status']??'unknown')?></span>
        </td>
        <td class="muted" style="font-size:11px"><?=$d['last_seen']?date('d M H:i',strtotime($d['last_seen'])):'Never'?></td>
        <td>
          <div style="display:flex;gap:4px">
            <a href="?id=<?=$d['id']?>&action=edit" class="button button-secondary button-sm">Edit</a>
            <form method="post" action="?id=<?=$d['id']?>" style="display:inline">
              <?=csrfField()?><input type="hidden" name="action" value="update_status"/>
              <select name="status" onchange="this.form.submit()" style="padding:5px 8px;border:1px solid var(--line);border-radius:6px;font-size:11px">
                <?php foreach($statuses as $st):?><option value="<?=$st?>" <?=($d['status']??'')===$st?'selected':''?>><?=ucfirst($st)?></option><?php endforeach;?>
              </select>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach;endif;?>
    </tbody>
  </table>
</div>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
