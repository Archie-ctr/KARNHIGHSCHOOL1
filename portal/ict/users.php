<?php
// ── SECURITY: ICT portal user support ─────────────────────────
// This page shows basic user account info ONLY.
// It deliberately does NOT query:
//   assessment_scores, annual_results (academic marks)
//   payments, fee_structures (financial data)
//   student_health_records (health data)
// ───────────────────────────────────────────────────────────────
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['ict_officer','sys_admin','school_admin']);
$activePage='users'; $pdo=db(); $user=currentUser();
$officer=null; try{$s=$pdo->prepare("SELECT * FROM staff WHERE user_id=? LIMIT 1");$s->execute([$user['id']]);$officer=$s->fetch()?:null;}catch(Throwable $e){}
if(!$officer) $officer=['first_name'=>$user['username']??'ICT','last_name'=>'','id'=>0];

$tab=$_GET['tab']??'accounts'; $userId=(int)($_GET['user_id']??0);

if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf(); $pa=$_POST['action']??'';
    if($pa==='log_reset'){
        try{
            $pdo->prepare("INSERT INTO ict_password_requests (user_id,reason,requested_by,status,notes,handled_by,handled_at) VALUES (?,?,?,?,?,?,NOW())")
                ->execute([(int)$_POST['target_user_id'],trim($_POST['reason']??''),$user['id'],$_POST['resolution']??'pending',trim($_POST['notes']??''),$_POST['resolution']==='completed'?$user['id']:null]);
            if($_POST['resolution']==='completed'){
                // Log action without touching the actual password (admin task)
                flash('success','Password reset logged. Complete the actual reset in Admin → Users.');
            } else { flash('success','Password reset request logged.'); }
        }catch(Throwable $e){flash('error','Failed: '.$e->getMessage());}
        redirect(BASE_URL.'/portal/ict/users.php?tab=resets');
    }
    if($pa==='resolve_reset'){
        $rid=(int)$_POST['request_id'];
        try{$pdo->prepare("UPDATE ict_password_requests SET status='completed',handled_by=?,handled_at=NOW(),notes=CONCAT(COALESCE(notes,''),' | Resolved: ',?) WHERE id=?")->execute([$user['id'],trim($_POST['resolution_note']??'Completed'),$rid]);flash('success','Reset marked complete.');}
        catch(Throwable $e){flash('error','Failed.');}
        redirect(BASE_URL.'/portal/ict/users.php?tab=resets');
    }
    if($pa==='assign_device'){
        $uid=(int)$_POST['target_user_id']; $aid=(int)$_POST['asset_id'];
        try{
            $uname=$pdo->query("SELECT username FROM users WHERE id=$uid")->fetchColumn();
            $pdo->prepare("UPDATE ict_assets SET assigned_to_id=?,assigned_to_name=?,updated_at=NOW() WHERE id=?")->execute([$uid,$uname,$aid]);
            flash('success','Device assigned to '.$uname.'.');
        }catch(Throwable $e){flash('error','Failed: '.$e->getMessage());}
        redirect(BASE_URL.'/portal/ict/users.php?tab=accounts');
    }
}

// Safe user list — only account fields, no academic/financial data
try{
    $fSearch=trim($_GET['q']??''); $fRole=trim($_GET['role']??'');
    $where=['1=1']; $params=[];
    if($fSearch){$where[]="(u.username LIKE ? OR u.email LIKE ?)";$p="%$fSearch%";$params=[$p,$p];}
    if($fRole){$where[]="u.role=?";$params[]=$fRole;}
    $users=$pdo->prepare("SELECT u.id,u.username,u.email,u.role,u.status,u.created_at,u.last_login FROM users u WHERE ".implode(' AND ',$where)." ORDER BY u.role,u.username LIMIT 200");
    $users->execute($params);$users=$users->fetchAll();
}catch(Throwable $e){$users=[];}

// Password reset requests
try{$resets=$pdo->query("SELECT r.*,u.username,u.email,u.role FROM ict_password_requests r JOIN users u ON u.id=r.user_id ORDER BY r.created_at DESC LIMIT 50")->fetchAll();}catch(Throwable $e){$resets=[];}
$pendingResets=array_filter($resets,fn($r)=>$r['status']==='pending');

// Assigned devices for a user
$userDevices=[];
if($userId){
    try{$userDevices=$pdo->query("SELECT * FROM ict_assets WHERE assigned_to_id=$userId ORDER BY asset_type")->fetchAll();}catch(Throwable $e){}
}

try{$availAssets=$pdo->query("SELECT id,asset_id,brand,model,asset_type FROM ict_assets WHERE status='active' ORDER BY asset_type,asset_id")->fetchAll();}catch(Throwable $e){$availAssets=[];}
$roles=['student','parent','teacher','class_teacher','discipline_officer','librarian','ict_officer','accountant','registrar','principal','vice_principal','school_admin','sys_admin'];
$roleColors=['sys_admin'=>'#7c0000','school_admin'=>'var(--primary)','principal'=>'var(--primary)','vice_principal'=>'var(--blue)','teacher'=>'var(--green)','class_teacher'=>'var(--green)','student'=>'var(--ink-soft)'];
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>User Support — ICT Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">
<div class="page-heading">
  <div><h1>User Support</h1><p>Account assistance, password resets, device assignment</p></div>
</div>
<div class="alert alert-info" style="margin-bottom:16px">
  🔐 <strong>Data access note:</strong> This view shows account information only. Academic marks, financial records and health data are not accessible from the ICT portal.
</div>
<?php foreach(getFlash() as $f):?><div class="alert alert-<?=$f['type']?>"><?=e($f['message'])?></div><?php endforeach;?>

<div class="tab-bar" style="margin-bottom:16px">
  <a href="?tab=accounts" class="tab-btn <?=$tab==='accounts'?'active':''?>">👥 Accounts (<?=count($users)?>)</a>
  <a href="?tab=resets"   class="tab-btn <?=$tab==='resets'  ?'active':''?>">🔑 Password Resets <?=count($pendingResets)>0?"(<strong style='color:var(--error)'>".count($pendingResets)."</strong>)":''?></a>
  <a href="?tab=assign"   class="tab-btn <?=$tab==='assign'  ?'active':''?>">💻 Assign Device</a>
</div>

<?php if($tab==='accounts'): ?>
<form method="get" class="filter-bar" style="margin-bottom:12px">
  <input type="hidden" name="tab" value="accounts"/>
  <input type="text" name="q" placeholder="Username or email…" value="<?=e($fSearch??'')?>"/>
  <select name="role"><option value="">All Roles</option><?php foreach($roles as $r):?><option value="<?=$r?>" <?=($fRole??'')===$r?'selected':''?>><?=ucfirst(str_replace('_',' ',$r))?></option><?php endforeach;?></select>
  <button type="submit" class="button button-secondary button-sm">Filter</button>
</form>
<div class="table-wrap">
  <table>
    <thead><tr><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th>Account Created</th><th>Support</th></tr></thead>
    <tbody>
      <?php if(empty($users)):?><tr><td colspan="7" style="text-align:center;color:var(--ink-faint);padding:24px">No users found.</td></tr>
      <?php else:foreach($users as $u):$rc=$roleColors[$u['role']??'student']??'var(--ink-soft)';?>
      <tr>
        <td><strong><?=e($u['username'])?></strong></td>
        <td class="muted"><?=e($u['email']??'—')?></td>
        <td><span style="font-size:11px;font-weight:700;color:<?=$rc?>"><?=ucfirst(str_replace('_',' ',$u['role']??'—'))?></span></td>
        <td><span class="status <?=$u['status']==='Active'?'approved':'warning'?>" style="font-size:10px"><?=e($u['status']??'Active')?></span></td>
        <td class="muted" style="font-size:12px"><?=$u['last_login']?date('d M Y H:i',strtotime($u['last_login'])):'Never'?></td>
        <td class="muted" style="font-size:12px"><?=date('d M Y',strtotime($u['created_at']))?></td>
        <td>
          <button onclick="document.getElementById('reset<?=$u['id']?>').style.display='table-row'" class="button button-secondary button-sm">🔑 Reset</button>
        </td>
      </tr>
      <!-- Reset inline row -->
      <tr id="reset<?=$u['id']?>" style="display:none;background:var(--bg2)">
        <td colspan="7" style="padding:12px 18px">
          <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <?=csrfField()?><input type="hidden" name="action" value="log_reset"/><input type="hidden" name="target_user_id" value="<?=$u['id']?>"/>
            <div class="form-group"><label>Reason</label><input type="text" name="reason" placeholder="Forgot password, locked out…" style="padding:7px 10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit;font-size:13px"/></div>
            <div class="form-group"><label>Action</label>
              <select name="resolution" style="padding:7px 10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit;font-size:13px">
                <option value="pending">Log Request (admin will complete)</option>
                <option value="completed">Mark as Completed Now</option>
              </select>
            </div>
            <div class="form-group"><label>Notes</label><input type="text" name="notes" placeholder="Additional details…" style="padding:7px 10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit;font-size:13px;min-width:180px"/></div>
            <button type="submit" class="button button-primary button-sm" style="margin-bottom:4px">💾 Log</button>
            <button type="button" onclick="document.getElementById('reset<?=$u['id']?>').style.display='none'" class="button button-secondary button-sm" style="margin-bottom:4px">Cancel</button>
          </form>
        </td>
      </tr>
      <?php endforeach;endif;?>
    </tbody>
  </table>
</div>

<?php elseif($tab==='resets'): ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Date</th><th>User</th><th>Role</th><th>Reason</th><th>Status</th><th>Notes</th><th>Action</th></tr></thead>
    <tbody>
      <?php if(empty($resets)):?><tr><td colspan="7" style="text-align:center;color:var(--ink-faint);padding:24px">No reset requests.</td></tr>
      <?php else:foreach($resets as $r):?>
      <tr>
        <td class="muted"><?=date('d M Y',strtotime($r['created_at']))?></td>
        <td><strong><?=e($r['username'])?></strong><div style="font-size:11px;color:var(--ink-faint)"><?=e($r['email']??'')?></div></td>
        <td class="muted" style="font-size:12px"><?=ucfirst(str_replace('_',' ',$r['role']??'—'))?></td>
        <td><?=e($r['reason']??'—')?></td>
        <td><span class="status <?=$r['status']==='completed'?'approved':'pending'?>" style="font-size:10px"><?=ucfirst($r['status'])?></span></td>
        <td style="font-size:12px;color:var(--ink-soft)"><?=e(mb_substr($r['notes']??'',0,50))?></td>
        <td>
          <?php if($r['status']==='pending'):?>
          <form method="post" style="display:inline">
            <?=csrfField()?><input type="hidden" name="action" value="resolve_reset"/><input type="hidden" name="request_id" value="<?=$r['id']?>"/><input type="hidden" name="resolution_note" value="Completed"/>
            <button class="button button-primary button-sm">✓ Mark Done</button>
          </form>
          <?php else:?><span class="muted" style="font-size:11px"><?=$r['handled_at']?date('d M',strtotime($r['handled_at'])):'—'?></span><?php endif;?>
        </td>
      </tr>
      <?php endforeach;endif;?>
    </tbody>
  </table>
</div>

<?php else: ?><!-- Device assignment -->
<div class="panel" style="padding:22px;margin-bottom:16px">
  <h3 style="font-weight:700;margin-bottom:16px">Assign Device to User</h3>
  <form method="post">
    <?=csrfField()?><input type="hidden" name="action" value="assign_device"/>
    <div class="form-grid">
      <div class="form-group"><label>User <span style="color:var(--error)">*</span></label>
        <select name="target_user_id" required><option value="">— Select user —</option>
          <?php foreach($users as $u):?><option value="<?=$u['id']?>"><?=e($u['username'].' ('.ucfirst(str_replace('_',' ',$u['role']??'')).')')?></option><?php endforeach;?>
        </select>
      </div>
      <div class="form-group"><label>Asset <span style="color:var(--error)">*</span></label>
        <select name="asset_id" required><option value="">— Select asset —</option>
          <?php foreach($availAssets as $a):?><option value="<?=$a['id']?>"><?=e($a['asset_id'].' — '.$a['brand'].' '.$a['model'].' ('.$a['asset_type'].')')?></option><?php endforeach;?>
        </select>
      </div>
    </div>
    <button type="submit" class="button button-primary">💾 Assign Device</button>
  </form>
</div>
<div class="panel" style="padding:18px">
  <h3 style="font-weight:700;font-size:13px;margin-bottom:12px">Current Device Assignments</h3>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Asset ID</th><th>Type</th><th>Brand / Model</th><th>Assigned To</th><th>Location</th><th>Action</th></tr></thead>
      <tbody>
        <?php
        try{$assigned=$pdo->query("SELECT * FROM ict_assets WHERE assigned_to_id IS NOT NULL ORDER BY asset_type,asset_id LIMIT 100")->fetchAll();}catch(Throwable $e){$assigned=[];}
        if(empty($assigned)):?><tr><td colspan="6" style="text-align:center;color:var(--ink-faint);padding:20px">No assigned devices.</td></tr>
        <?php else:foreach($assigned as $a):?>
        <tr>
          <td style="font-family:monospace;font-size:12px"><a href="assets.php?id=<?=$a['id']?>" style="color:var(--primary)"><?=e($a['asset_id'])?></a></td>
          <td class="muted"><?=e($a['asset_type'])?></td>
          <td><?=e($a['brand']??'—')?> <?=e($a['model']??'')?></td>
          <td><strong><?=e($a['assigned_to_name']??'—')?></strong></td>
          <td class="muted"><?=e($a['location']??'—')?></td>
          <td>
            <form method="post" style="display:inline" onsubmit="return confirm('Unassign this device?')">
              <?=csrfField()?><input type="hidden" name="action" value="assign_device"/><input type="hidden" name="target_user_id" value="0"/><input type="hidden" name="asset_id" value="<?=$a['id']?>"/>
              <button class="button button-secondary button-sm">Unassign</button>
            </form>
          </td>
        </tr>
        <?php endforeach;endif;?>
      </tbody>
    </table>
  </div>
</div>
<?php endif;?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
