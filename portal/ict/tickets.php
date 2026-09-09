<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['ict_officer','sys_admin','school_admin']);
$activePage='tickets'; $pdo=db(); $user=currentUser();
$officer=null; try{$s=$pdo->prepare("SELECT * FROM staff WHERE user_id=? LIMIT 1");$s->execute([$user['id']]);$officer=$s->fetch()?:null;}catch(Throwable $e){}
if(!$officer) $officer=['first_name'=>$user['username']??'ICT','last_name'=>'','id'=>0];

$action=$_GET['action']??''; $ticketId=(int)($_GET['id']??0);
$preAsset=(int)($_GET['asset_id']??0);

if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf(); $pa=$_POST['action']??'';
    if($pa==='create'){
        try{
            $yr=date('Y'); $seq=(int)$pdo->query("SELECT COUNT(*)+1 FROM ict_tickets WHERE YEAR(opened_at)=$yr")->fetchColumn();
            $ref='TKT-'.$yr.'-'.str_pad($seq,4,'0',STR_PAD_LEFT);
            $pdo->prepare("INSERT INTO ict_tickets (ticket_ref,category,subject,description,priority,reported_by,reporter_name,reporter_role,related_asset_id,status,assigned_to) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$ref,$_POST['category'],trim($_POST['subject']),trim($_POST['description']),$_POST['priority']??'medium',$user['id'],$officer['first_name'].' '.$officer['last_name'],currentRole(),(int)$_POST['asset_id']?:null,'open',$user['id']]);
            flash('success','Ticket '.$ref.' created.');
        }catch(Throwable $e){flash('error','Failed: '.$e->getMessage());}
        redirect(BASE_URL.'/portal/ict/tickets.php');
    }
    if($pa==='update_status'&&$ticketId){
        try{
            $ns=$_POST['new_status'];
            $extra=''; $params=[$ns];
            if($ns==='resolved'){$extra.=',resolved_at=NOW()';}
            if($ns==='closed'){$extra.=',closed_at=NOW()';}
            $params[]=$ticketId;
            $pdo->prepare("UPDATE ict_tickets SET status=?$extra,assigned_to=?,priority=?,updated_at=NOW() WHERE id=?")->execute([$ns,(int)$_POST['assigned_to']??$user['id'],$_POST['priority']??'medium',$ticketId]);
            if(!empty(trim($_POST['resolution_notes']??''))) $pdo->prepare("UPDATE ict_tickets SET resolution_notes=? WHERE id=?")->execute([trim($_POST['resolution_notes']),$ticketId]);
            flash('success','Ticket updated.');
        }catch(Throwable $e){flash('error','Failed: '.$e->getMessage());}
        redirect(BASE_URL.'/portal/ict/tickets.php?id='.$ticketId);
    }
    if($pa==='add_comment'&&$ticketId){
        try{
            $pdo->prepare("INSERT INTO ict_ticket_comments (ticket_id,comment,is_internal,added_by) VALUES (?,?,?,?)")
                ->execute([$ticketId,trim($_POST['comment']),isset($_POST['is_internal'])?1:0,$user['id']]);
            flash('success','Comment added.');
        }catch(Throwable $e){flash('error','Failed.');}
        redirect(BASE_URL.'/portal/ict/tickets.php?id='.$ticketId.'#comments');
    }
}

$ticket=null; $comments=[];
if($ticketId){
    try{$ticket=$pdo->query("SELECT t.*,a.asset_id asset_ref,a.brand a_brand,a.model a_model FROM ict_tickets t LEFT JOIN ict_assets a ON a.id=t.related_asset_id WHERE t.id=$ticketId")->fetch();}catch(Throwable $e){}
    try{$comments=$pdo->query("SELECT c.*,u.username uname FROM ict_ticket_comments c LEFT JOIN users u ON u.id=c.added_by WHERE c.ticket_id=$ticketId ORDER BY c.added_at ASC")->fetchAll();}catch(Throwable $e){$comments=[];}
}

$fStatus=$_GET['status']??''; $fPriority=$_GET['priority']??''; $fSearch=trim($_GET['q']??'');
$where=['1=1']; $params=[];
if($fStatus){$where[]="status=?";$params[]=$fStatus;}
if($fPriority){$where[]="priority=?";$params[]=$fPriority;}
if($fSearch){$where[]="(subject LIKE ? OR ticket_ref LIKE ? OR reporter_name LIKE ?)";$p="%$fSearch%";$params=array_merge($params,[$p,$p,$p]);}
try{
    $tickets=$pdo->prepare("SELECT t.*,a.asset_id a_ref FROM ict_tickets t LEFT JOIN ict_assets a ON a.id=t.related_asset_id WHERE ".implode(' AND ',$where)." ORDER BY FIELD(t.priority,'critical','high','medium','low'),t.opened_at DESC LIMIT 100");
    $tickets->execute($params);$tickets=$tickets->fetchAll();
}catch(Throwable $e){$tickets=[];}

try{$allAssets=$pdo->query("SELECT id,asset_id,brand,model FROM ict_assets WHERE status='active' ORDER BY asset_id")->fetchAll();}catch(Throwable $e){$allAssets=[];}

$categories=['Hardware','Software','Network','Account/Password','Email','Printer','Projector','Server','Device Repair','Software Installation','User Support','Other'];
$priorities=['low','medium','high','critical'];
$statuses=['open','in_progress','pending_parts','resolved','closed'];
$pc=['open'=>'var(--error)','in_progress'=>'var(--warning)','pending_parts'=>'var(--gold)','resolved'=>'var(--green)','closed'=>'var(--ink-soft)'];
$pp=['critical'=>'#7c0000','high'=>'var(--error)','medium'=>'var(--warning)','low'=>'var(--green)'];
$stLabels=['open'=>'Open','in_progress'=>'In Progress','pending_parts'=>'Pending Parts','resolved'=>'Resolved','closed'=>'Closed'];
$workflow=[['open','Open'],['in_progress','In Progress'],['pending_parts','Pending Parts'],['resolved','Resolved'],['closed','Closed']];
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Support Tickets — ICT Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
<style>
.ticket-timeline{display:flex;gap:0;margin-bottom:20px;overflow-x:auto}
.tt-step{flex:1;min-width:90px;text-align:center;padding:7px 4px;border-bottom:3px solid var(--line);font-size:11px;font-weight:700;color:var(--ink-soft)}
.tt-step.active{border-color:var(--primary);color:var(--primary)}
.tt-step.done{border-color:var(--green);color:var(--green)}
</style>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">
<div class="page-heading">
  <div><h1><?=$ticketId&&$ticket?'Ticket '.$ticket['ticket_ref']:($action==='new'?'New Support Ticket':'Support Tickets')?></h1>
  <p>IT Support Management</p></div>
  <div style="display:flex;gap:8px">
    <?php if($ticketId):?><a href="tickets.php" class="button button-secondary">← All Tickets</a><?php endif;?>
    <?php if(!$ticketId&&$action!=='new'):?><a href="?action=new" class="button button-primary">+ New Ticket</a><?php endif;?>
  </div>
</div>
<?php foreach(getFlash() as $f):?><div class="alert alert-<?=$f['type']?>"><?=e($f['message'])?></div><?php endforeach;?>

<?php if($action==='new'): ?>
<div class="panel" style="padding:22px;margin-bottom:16px">
  <h3 style="font-weight:700;margin-bottom:16px">Log New Support Ticket</h3>
  <form method="post">
    <?=csrfField()?><input type="hidden" name="action" value="create"/>
    <div class="form-grid">
      <div class="form-group"><label>Category <span style="color:var(--error)">*</span></label>
        <select name="category" required><?php foreach($categories as $c):?><option value="<?=$c?>"><?=$c?></option><?php endforeach;?></select>
      </div>
      <div class="form-group"><label>Priority</label>
        <select name="priority"><?php foreach($priorities as $p):?><option value="<?=$p?>" <?=$p==='medium'?'selected':''?>><?=ucfirst($p)?></option><?php endforeach;?></select>
      </div>
      <div class="form-group" style="grid-column:1/-1"><label>Subject <span style="color:var(--error)">*</span></label><input type="text" name="subject" required placeholder="Brief description of the problem"/></div>
      <div class="form-group"><label>Related Asset (optional)</label>
        <select name="asset_id"><option value="">— None —</option>
          <?php foreach($allAssets as $a):?><option value="<?=$a['id']?>" <?=$preAsset===$a['id']?'selected':''?>><?=e($a['asset_id'].' — '.$a['brand'].' '.$a['model'])?></option><?php endforeach;?>
        </select>
      </div>
      <div class="form-group" style="grid-column:1/-1"><label>Description <span style="color:var(--error)">*</span></label><textarea name="description" rows="4" required placeholder="Describe the issue in detail — steps to reproduce, error messages, affected users…"></textarea></div>
    </div>
    <div style="display:flex;gap:10px;margin-top:8px"><button type="submit" class="button button-primary">🎫 Create Ticket</button><a href="tickets.php" class="button button-secondary">Cancel</a></div>
  </form>
</div>

<?php elseif($ticketId&&$ticket): ?>
<!-- Workflow timeline -->
<div class="ticket-timeline">
  <?php $ord=['open','in_progress','pending_parts','resolved','closed']; $ci=array_search($ticket['status']??'open',$ord);
  foreach($workflow as [$st,$lbl]): $idx=array_search($st,$ord); $cls=$idx<$ci?'done':($idx===$ci?'active':'');?>
  <div class="tt-step <?=$cls?>"><?=$lbl?></div>
  <?php endforeach;?>
</div>

<div style="display:grid;grid-template-columns:1fr 300px;gap:16px">
  <div>
    <div class="panel" style="padding:20px;margin-bottom:14px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;margin-bottom:14px">
        <div>
          <h3 style="font-weight:800;font-size:16px;margin-bottom:3px"><?=e($ticket['subject'])?></h3>
          <div style="font-size:13px;color:var(--ink-soft)"><?=e($ticket['category'])?> &middot; Opened <?=date('d M Y H:i',strtotime($ticket['opened_at']))?></div>
          <?php if($ticket['a_ref']):?><div style="font-size:12.5px;color:var(--ink-soft);margin-top:2px">🖥 <a href="assets.php?id=<?=$ticket['related_asset_id']?>" style="color:var(--primary)"><?=e($ticket['a_ref'])?> — <?=e($ticket['a_brand'].' '.$ticket['a_model'])?></a></div><?php endif;?>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <span style="font-size:12px;padding:4px 12px;border-radius:10px;background:<?=$pc[$ticket['status']??'open']?>;color:#fff;font-weight:700"><?=$stLabels[$ticket['status']??'open']?></span>
          <span style="font-size:12px;padding:4px 12px;border-radius:10px;color:<?=$pp[$ticket['priority']??'medium']?>;border:1.5px solid <?=$pp[$ticket['priority']??'medium']?>;font-weight:700"><?=ucfirst($ticket['priority']??'medium')?></span>
        </div>
      </div>
      <div style="background:var(--bg2);border-radius:var(--radius-sm);padding:14px;font-size:13.5px;white-space:pre-line"><?=e($ticket['description'])?></div>
      <?php if($ticket['resolution_notes']):?>
      <div style="margin-top:12px;background:var(--green-soft);border-left:3px solid var(--green);border-radius:var(--radius-sm);padding:12px 14px">
        <div style="font-size:11px;font-weight:700;color:var(--green);margin-bottom:4px">RESOLUTION</div>
        <div style="font-size:13px;white-space:pre-line"><?=e($ticket['resolution_notes'])?></div>
      </div>
      <?php endif;?>
    </div>

    <!-- Comments -->
    <div class="panel" style="padding:20px" id="comments">
      <h3 style="font-weight:700;font-size:13px;margin-bottom:14px">💬 Comments &amp; Updates</h3>
      <?php if(empty($comments)):?><p class="muted" style="font-size:13px;margin-bottom:12px">No comments yet.</p>
      <?php else:foreach($comments as $c):?>
      <div style="border-bottom:1px solid var(--line);padding:10px 0">
        <div style="display:flex;justify-content:space-between;font-size:11.5px;color:var(--ink-soft);margin-bottom:5px">
          <span style="font-weight:700;color:var(--ink)"><?=e($c['uname']??'Officer')?><?=$c['is_internal']?' <span style="color:var(--warning);font-size:10px">🔒 Internal</span>':''?></span>
          <span><?=date('d M Y H:i',strtotime($c['added_at']))?></span>
        </div>
        <p style="font-size:13.5px;margin:0;white-space:pre-line"><?=e($c['comment'])?></p>
      </div>
      <?php endforeach;endif;?>
      <form method="post" action="?id=<?=$ticketId?>#comments" style="margin-top:14px">
        <?=csrfField()?><input type="hidden" name="action" value="add_comment"/>
        <textarea name="comment" rows="3" required placeholder="Add a comment or update…" style="width:100%;padding:10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit;font-size:13px;resize:vertical;margin-bottom:8px"></textarea>
        <div style="display:flex;justify-content:space-between;align-items:center">
          <label style="display:flex;align-items:center;gap:6px;font-size:12.5px;cursor:pointer"><input type="checkbox" name="is_internal" style="width:auto"/> Internal note (not visible to reporter)</label>
          <button type="submit" class="button button-primary button-sm">+ Add Comment</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Right: update status -->
  <div style="display:flex;flex-direction:column;gap:12px">
    <div class="panel" style="padding:18px">
      <h4 style="font-weight:700;font-size:13px;margin-bottom:12px">🔄 Update Ticket</h4>
      <form method="post" action="?id=<?=$ticketId?>">
        <?=csrfField()?><input type="hidden" name="action" value="update_status"/>
        <div class="form-group" style="margin-bottom:10px"><label>Status</label>
          <select name="new_status" style="width:100%;padding:8px 10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit;font-size:13px">
            <?php foreach($statuses as $st):?><option value="<?=$st?>" <?=($ticket['status']??'open')===$st?'selected':''?>><?=$stLabels[$st]?></option><?php endforeach;?>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:10px"><label>Priority</label>
          <select name="priority" style="width:100%;padding:8px 10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit;font-size:13px">
            <?php foreach($priorities as $p):?><option value="<?=$p?>" <?=($ticket['priority']??'medium')===$p?'selected':''?>><?=ucfirst($p)?></option><?php endforeach;?>
          </select>
        </div>
        <input type="hidden" name="assigned_to" value="<?=$user['id']?>"/>
        <div class="form-group" style="margin-bottom:10px"><label>Resolution Notes</label>
          <textarea name="resolution_notes" rows="3" placeholder="How was this resolved?" style="width:100%;padding:8px 10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit;font-size:13px;resize:vertical"><?=e($ticket['resolution_notes']??'')?></textarea>
        </div>
        <button type="submit" class="button button-primary" style="width:100%">Update Ticket</button>
      </form>
    </div>
    <div class="panel" style="padding:18px">
      <h4 style="font-weight:700;font-size:13px;margin-bottom:10px">📋 Info</h4>
      <div style="font-size:13px;display:flex;flex-direction:column;gap:6px">
        <div style="display:flex;justify-content:space-between"><span class="muted">Ref</span><strong style="font-family:monospace"><?=e($ticket['ticket_ref'])?></strong></div>
        <div style="display:flex;justify-content:space-between"><span class="muted">Reported by</span><span><?=e($ticket['reporter_name']??'—')?></span></div>
        <div style="display:flex;justify-content:space-between"><span class="muted">Opened</span><span><?=date('d M Y',strtotime($ticket['opened_at']))?></span></div>
        <?php if($ticket['resolved_at']):?><div style="display:flex;justify-content:space-between"><span class="muted">Resolved</span><span style="color:var(--green)"><?=date('d M Y',strtotime($ticket['resolved_at']))?></span></div><?php endif;?>
        <div style="display:flex;justify-content:space-between"><span class="muted">Comments</span><strong><?=count($comments)?></strong></div>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<!-- Ticket list -->
<form method="get" class="filter-bar" style="margin-bottom:12px">
  <input type="text" name="q" placeholder="Search subject, ref, reporter…" value="<?=e($fSearch)?>"/>
  <select name="status"><option value="">All Statuses</option><?php foreach($statuses as $st):?><option value="<?=$st?>" <?=$fStatus===$st?'selected':''?>><?=$stLabels[$st]?></option><?php endforeach;?></select>
  <select name="priority"><option value="">All Priorities</option><?php foreach($priorities as $p):?><option value="<?=$p?>" <?=$fPriority===$p?'selected':''?>><?=ucfirst($p)?></option><?php endforeach;?></select>
  <button type="submit" class="button button-secondary button-sm">Filter</button>
  <a href="tickets.php" class="button button-secondary button-sm">Reset</a>
</form>
<div class="table-wrap">
  <table>
    <thead><tr><th>Ref</th><th>Subject</th><th>Category</th><th>Priority</th><th>Reported By</th><th>Opened</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php if(empty($tickets)):?><tr><td colspan="8" style="text-align:center;color:var(--ink-faint);padding:32px">No tickets found.</td></tr>
      <?php else:foreach($tickets as $t):?>
      <tr>
        <td style="font-family:monospace;font-size:11px"><a href="?id=<?=$t['id']?>" style="color:var(--primary)"><?=e($t['ticket_ref'])?></a></td>
        <td style="max-width:180px"><div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px"><?=e($t['subject'])?></div><?php if($t['a_ref']):?><div style="font-size:10px;color:var(--ink-faint)"><?=e($t['a_ref'])?></div><?php endif;?></td>
        <td class="muted" style="font-size:12px"><?=e($t['category'])?></td>
        <td><span style="font-size:11px;font-weight:800;color:<?=$pp[$t['priority']??'medium']?>"><?=ucfirst($t['priority']??'medium')?></span></td>
        <td class="muted" style="font-size:12px"><?=e($t['reporter_name']??'—')?></td>
        <td class="muted"><?=date('d M Y',strtotime($t['opened_at']))?></td>
        <td><span style="font-size:11px;padding:2px 7px;border-radius:10px;background:<?=$pc[$t['status']??'open']?>;color:#fff;font-weight:600"><?=$stLabels[$t['status']??'open']?></span></td>
        <td><a href="?id=<?=$t['id']?>" class="button button-secondary button-sm">View</a></td>
      </tr>
      <?php endforeach;endif;?>
    </tbody>
  </table>
</div>
<?php endif;?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
