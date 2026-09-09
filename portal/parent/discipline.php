<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('parent');
$activePage='discipline'; $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
include __DIR__.'/includes/resolve_child.php';
$pdo=db(); $cq=$selChild?'?child_id='.$selChild:'';

// POST: meeting request response / acknowledgement
if($_SERVER['REQUEST_METHOD']==='POST'&&$child){
    verifyCsrf(); $pa=$_POST['action']??'';
    if($pa==='acknowledge'){
        $incId=(int)$_POST['incident_id'];
        // Log parent response to notification
        try{
            $pdo->prepare("UPDATE discipline_records SET parent_notification_notes=CONCAT(COALESCE(parent_notification_notes,''),' | Parent acknowledged: ',?) WHERE id=? AND student_id=?")
                ->execute([date('d M Y H:i'),$incId,$child['id']]);
            // Also log meeting request note
            if(!empty(trim($_POST['parent_note']??''))){
                $pdo->prepare("INSERT INTO discipline_case_notes (incident_id,note_type,note,added_by) VALUES (?,'general',?,?)")
                    ->execute([$incId,'Parent response: '.trim($_POST['parent_note']),$_SESSION['user']['id']??0]);
            }
            flash('success','Acknowledged. The school has been notified of your response.');
        }catch(Throwable $e){flash('error','Failed: '.$e->getMessage());}
        redirect(BASE_URL.'/portal/parent/discipline.php'.$cq);
    }
}

$incidents=[]; $parentNotifs=[]; $actions=[];

if($child){
    $cid=$child['id'];
    try{
        $incidents=$pdo->query(
            "SELECT dr.*,c.name class_name FROM discipline_records dr
             LEFT JOIN classes c ON c.id=(SELECT current_class_id FROM students WHERE id=$cid)
             WHERE dr.student_id=$cid AND dr.academic_year_id=$ayId
             ORDER BY dr.date_occurred DESC,dr.id DESC"
        )->fetchAll();
    }catch(Throwable $e){}
    try{
        $parentNotifs=$pdo->query(
            "SELECT pn.*,dr.violation_type,dr.category vcat FROM parent_notifications pn
             LEFT JOIN discipline_records dr ON dr.id=pn.incident_id
             WHERE pn.student_id=$cid AND pn.academic_year_id=$ayId
             ORDER BY pn.sent_at DESC LIMIT 20"
        )->fetchAll();
    }catch(Throwable $e){}
    try{
        $actions=$pdo->query(
            "SELECT da.*,dr.violation_type,dr.category vcat FROM discipline_actions da
             LEFT JOIN discipline_records dr ON dr.id=da.incident_id
             WHERE da.student_id=$cid AND da.academic_year_id=$ayId
             ORDER BY da.assigned_at DESC"
        )->fetchAll();
    }catch(Throwable $e){}
}

$openIncidents=array_filter($incidents,fn($i)=>!in_array($i['status']??'open',['resolved','closed']));
$sc=['open'=>'var(--error)','investigating'=>'var(--warning)','pending_decision'=>'var(--blue)','resolved'=>'var(--green)','closed'=>'var(--ink-soft)'];
$sl=['open'=>'Open','investigating'=>'Investigating','pending_decision'=>'Pending Decision','resolved'=>'Resolved','closed'=>'Closed'];
$sevc=['minor'=>'var(--green)','moderate'=>'var(--warning)','serious'=>'var(--error)','critical'=>'#7c0000'];
$tab=$_GET['tab']??'incidents';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Discipline — Parent Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">
<div class="page-heading">
  <div><h1><?=$child?e($child['first_name'])."'s":'Child'?> Discipline Records</h1>
  <p><?=e($ay)?></p></div>
  <a href="index.php<?=$cq?>" class="button button-secondary">← Dashboard</a>
</div>
<?php foreach(getFlash() as $f):?><div class="alert alert-<?=$f['type']?>"><?=e($f['message'])?></div><?php endforeach;?>
<?php if(!$child):?><div class="alert alert-warning">Select a child to view discipline records.</div>
<?php else:?>

<?php if(!empty($openIncidents)):?>
<div style="background:rgba(239,68,68,.06);border:1.5px solid var(--error);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:16px">
  <strong style="color:var(--error)">⚠️ <?=count($openIncidents)?> open discipline case<?=count($openIncidents)!=1?'s':''?> for <?=e($child['first_name'])?></strong>
</div>
<?php endif;?>

<div class="metric-grid" style="margin-bottom:16px">
  <div class="metric-card <?=count($openIncidents)>0?'finance-metrics':''?>"><div class="metric-top"><span>Open Cases</span><div class="metric-icon">⚠️</div></div><strong style="color:<?=count($openIncidents)>0?'var(--error)':'var(--green)'?>"><?=count($openIncidents)?></strong></div>
  <div class="metric-card"><div class="metric-top"><span>Total Incidents</span><div class="metric-icon">📋</div></div><strong><?=count($incidents)?></strong><small><i></i><?=e($ay)?></small></div>
  <div class="metric-card"><div class="metric-top"><span>Parent Notifications</span><div class="metric-icon">📞</div></div><strong><?=count($parentNotifs)?></strong></div>
  <div class="metric-card"><div class="metric-top"><span>Actions Issued</span><div class="metric-icon">⚡</div></div><strong><?=count($actions)?></strong></div>
</div>

<div class="tab-bar" style="margin-bottom:16px">
  <a href="?tab=incidents<?=$selChild?'&child_id='.$selChild:''?>"     class="tab-btn <?=$tab==='incidents'    ?'active':''?>">📋 Incidents (<?=count($incidents)?>)</a>
  <a href="?tab=actions<?=$selChild?'&child_id='.$selChild:''?>"       class="tab-btn <?=$tab==='actions'      ?'active':''?>">⚡ Actions (<?=count($actions)?>)</a>
  <a href="?tab=notifications<?=$selChild?'&child_id='.$selChild:''?>" class="tab-btn <?=$tab==='notifications'?'active':''?>">📞 Notifications (<?=count($parentNotifs)?>)</a>
</div>

<?php if($tab==='incidents'): ?>
<?php if(empty($incidents)):?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:40px;margin-bottom:12px">✅</div>
  <h3 style="margin-bottom:6px">No discipline records</h3>
  <p style="color:var(--ink-soft)"><?=e($child['first_name'])?> has no discipline incidents this year.</p>
</div>
<?php else:?>
<div style="display:flex;flex-direction:column;gap:12px">
<?php foreach($incidents as $inc):
  $sev=$inc['severity']??'minor'; $stc=$sc[$inc['status']??'open']??'var(--ink-soft)';
  $sevcol=$sevc[$sev]??'var(--ink)';
  $open=!in_array($inc['status']??'open',['resolved','closed']);
?>
<div class="panel" style="padding:18px;border-left:4px solid <?=$sevcol?>">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px">
    <div>
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px">
        <strong style="font-size:14px"><?=e(ucwords($inc['violation_type']??$inc['category']??'Incident'))?></strong>
        <span style="font-size:11px;padding:2px 8px;border-radius:10px;background:<?=$stc?>;color:#fff;font-weight:600"><?=$sl[$inc['status']??'open']?></span>
        <span style="font-size:11px;color:<?=$sevcol?>;font-weight:700"><?=ucfirst($sev)?></span>
      </div>
      <div style="font-size:12.5px;color:var(--ink-soft)"><?=date('d M Y',strtotime($inc['date_occurred']??$inc['incident_date']??'now'))?>
        <?php if($inc['location']):?> &middot; <?=e($inc['location'])?><?php endif;?>
      </div>
      <div style="font-size:12.5px;color:var(--ink2);margin-top:5px"><?=e(mb_substr($inc['description'],0,120))?><?=mb_strlen($inc['description'])>120?'…':''?></div>
      <?php if($inc['action_taken']&&$inc['action_taken']!=='Pending'):?>
      <div style="font-size:12.5px;color:var(--ink-soft);margin-top:4px"><strong>Action taken:</strong> <?=e($inc['action_taken'])?></div>
      <?php endif;?>
      <?php if($inc['parent_notified']):?>
      <div style="font-size:12px;color:var(--green);margin-top:4px">✓ School has notified you of this incident</div>
      <?php endif;?>
    </div>
    <?php if($open&&$inc['parent_notified']):?>
    <button onclick="document.getElementById('ack<?=$inc['id']?>').style.display='block';this.style.display='none'" class="button button-secondary button-sm" style="flex-shrink:0">✓ Acknowledge</button>
    <?php endif;?>
  </div>
  <!-- Ack form -->
  <div id="ack<?=$inc['id']?>" style="display:none;margin-top:12px;border-top:1px solid var(--line);padding-top:12px">
    <form method="post">
      <?=csrfField()?><input type="hidden" name="action" value="acknowledge"/><input type="hidden" name="incident_id" value="<?=$inc['id']?>"/>
      <div class="form-group" style="margin-bottom:8px">
        <label style="font-size:12.5px">Your response / note to school (optional)</label>
        <textarea name="parent_note" rows="2" placeholder="e.g. We have spoken to our child about this matter…" style="width:100%;padding:8px 10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit;font-size:13px;resize:vertical"></textarea>
      </div>
      <button type="submit" class="button button-primary button-sm">Send Acknowledgement</button>
    </form>
  </div>
</div>
<?php endforeach;?>
</div>
<?php endif;?>

<?php elseif($tab==='actions'): ?>
<?php if(empty($actions)):?><p style="color:var(--ink-faint);padding:24px 0;text-align:center">No disciplinary actions issued this year.</p>
<?php else:?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Date</th><th>Action</th><th>Related Incident</th><th>Duration</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach($actions as $a):?>
      <tr>
        <td class="muted"><?=date('d M Y',strtotime($a['assigned_at']))?></td>
        <td><strong><?=e($a['action_type'])?></strong><?php if($a['description']):?><div style="font-size:11.5px;color:var(--ink-soft)"><?=e(mb_substr($a['description'],0,60))?></div><?php endif;?></td>
        <td class="muted"><?=e(ucwords($a['violation_type']??$a['vcat']??'—'))?></td>
        <td class="muted"><?=$a['duration_days']?$a['duration_days'].' day'.($a['duration_days']!=1?'s':''):'—'?></td>
        <td><span class="status <?=$a['status']==='completed'?'approved':'new-s'?>" style="font-size:11px"><?=ucfirst($a['status'])?></span></td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>
<?php endif;?>

<?php else: // notifications tab ?>
<?php if(empty($parentNotifs)):?><p style="color:var(--ink-faint);padding:24px 0;text-align:center">No parent notifications on record.</p>
<?php else:?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Date</th><th>Method</th><th>Incident</th><th>Message</th><th>Response</th></tr></thead>
    <tbody>
      <?php foreach($parentNotifs as $n): $notifTypeLabels=['call'=>'Phone Call','sms'=>'SMS','email'=>'Email','letter'=>'Letter','meeting'=>'Meeting','whatsapp'=>'WhatsApp'];?>
      <tr>
        <td class="muted"><?=date('d M Y',strtotime($n['sent_at']))?></td>
        <td><?=e($notifTypeLabels[$n['notification_type']]??ucfirst($n['notification_type']??'—'))?></td>
        <td class="muted"><?=e(ucwords($n['violation_type']??$n['vcat']??'—'))?></td>
        <td style="max-width:180px;font-size:12.5px;color:var(--ink-soft)"><?=e(mb_substr($n['message']??'—',0,60))?></td>
        <td style="max-width:150px;font-size:12px;color:var(--ink-soft)"><?=e(mb_substr($n['parent_response']??'—',0,50))?></td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>
<?php endif;?>
<?php endif;?>
<?php endif;?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
