<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['discipline_officer','principal','vice_principal','sys_admin','school_admin']);
$activePage='notifications'; $pdo=db(); $user=currentUser(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
$officer=null; try{$s=$pdo->prepare("SELECT * FROM staff WHERE user_id=? LIMIT 1");$s->execute([$user['id']]);$officer=$s->fetch()?:null;}catch(Throwable $e){}
if(!$officer) $officer=['first_name'=>$user['username']??'Officer','last_name'=>'','id'=>0];

// Ensure parent_notifications table
try{$pdo->exec("CREATE TABLE IF NOT EXISTS parent_notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, student_id INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL, incident_id INT UNSIGNED NULL,
  notification_type VARCHAR(40) NOT NULL DEFAULT 'call',
  message TEXT NULL, parent_response TEXT NULL,
  meeting_date DATETIME NULL, meeting_notes TEXT NULL, meeting_attended TINYINT(1) NULL,
  sent_by INT UNSIGNED NOT NULL, sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  responded_at DATETIME NULL,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  INDEX idx_pn_incident (incident_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");}catch(Throwable $e){}

$tab=($_GET['tab']??'pending');
$preIncident=(int)($_GET['incident_id']??0);

if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf(); $pa=$_POST['action']??'';

    if($pa==='notify'){
        try{
            $incId=(int)$_POST['incident_id'];
            $sid  =(int)$_POST['student_id'];
            $type =trim($_POST['notification_type']??'call');
            $msg  =trim($_POST['message']??'');
            $mDate=$_POST['meeting_date']?:null;
            // Insert notification log
            $pdo->prepare("INSERT INTO parent_notifications (student_id,academic_year_id,incident_id,notification_type,message,meeting_date,sent_by) VALUES (?,?,?,?,?,?,?)")
                ->execute([$sid,$ayId,$incId,$type,$msg,$mDate,$user['id']]);
            // Mark incident as notified
            $pdo->prepare("UPDATE discipline_records SET parent_notified=1,parent_notification_date=COALESCE(parent_notification_date,NOW()),parent_notification_notes=? WHERE id=?")
                ->execute([$msg,$incId]);
            // Case note
            $pdo->prepare("INSERT INTO discipline_case_notes (incident_id,note_type,note,added_by) VALUES (?,'general',?,?)")
                ->execute([$incId,"Parent notified via ".ucwords(str_replace('_',' ',$type)).": ".($msg?:'-'),$user['id']]);
            flash('success','Parent notification recorded.');
        }catch(Throwable $e){flash('error','Failed: '.$e->getMessage());}
        redirect(BASE_URL.'/portal/discipline/notifications.php?tab=history');
    }

    if($pa==='record_response'){
        try{
            $pnId=(int)$_POST['pn_id'];
            $resp=trim($_POST['parent_response']??'');
            $mNotes=trim($_POST['meeting_notes']??'');
            $attended=$_POST['meeting_attended']??null;
            $pdo->prepare("UPDATE parent_notifications SET parent_response=?,meeting_notes=?,meeting_attended=?,responded_at=NOW() WHERE id=?")
                ->execute([$resp,$mNotes,$attended!==null?(int)$attended:null,$pnId]);
            flash('success','Response recorded.');
        }catch(Throwable $e){flash('error','Failed.');}
        redirect(BASE_URL.'/portal/discipline/notifications.php?tab=history');
    }
}

// Incidents awaiting notification
try{
    $pending=$pdo->query(
        "SELECT dr.*,CONCAT(s.first_name,' ',s.last_name) sname,s.student_id sid,
                c.name cname, s.id spk,
                sg.first_name gfirst,sg.last_name glast,sg.phone gphone,sg.email gemail
         FROM discipline_records dr
         JOIN students s ON s.id=dr.student_id
         LEFT JOIN classes c ON c.id=s.current_class_id
         LEFT JOIN guardians sg ON sg.student_id=s.id AND sg.is_primary=1
         WHERE dr.academic_year_id=$ayId AND (dr.parent_notified IS NULL OR dr.parent_notified=0)
           AND dr.status NOT IN ('closed')
         ORDER BY dr.severity DESC,dr.date_occurred ASC LIMIT 50"
    )->fetchAll();
}catch(Throwable $e){$pending=[];}

// Notification history
try{
    $history=$pdo->query(
        "SELECT pn.*,CONCAT(s.first_name,' ',s.last_name) sname,s.student_id sid,
                c.name cname,
                COALESCE(dr.violation_type,dr.category,'Incident') vtype
         FROM parent_notifications pn
         JOIN students s ON s.id=pn.student_id
         LEFT JOIN classes c ON c.id=s.current_class_id
         LEFT JOIN discipline_records dr ON dr.id=pn.incident_id
         WHERE pn.academic_year_id=$ayId
         ORDER BY pn.sent_at DESC LIMIT 80"
    )->fetchAll();
}catch(Throwable $e){$history=[];}

$notifTypes=['call'=>'Phone Call','sms'=>'SMS','email'=>'Email','letter'=>'Written Letter',
             'meeting'=>'In-Person Meeting','whatsapp'=>'WhatsApp','other'=>'Other'];
$sevCol=['minor'=>'var(--green)','moderate'=>'var(--warning)','serious'=>'var(--error)','critical'=>'#7c0000'];

// Pre-load incident if linked from case view
$preInc=null;
if($preIncident){
    try{$preInc=$pdo->query("SELECT dr.*,CONCAT(s.first_name,' ',s.last_name) sname,s.student_id sid,s.id spk,
        c.name cname,sg.first_name gfirst,sg.last_name glast,sg.phone gphone,sg.email gemail
        FROM discipline_records dr JOIN students s ON s.id=dr.student_id
        LEFT JOIN classes c ON c.id=s.current_class_id
        LEFT JOIN guardians sg ON sg.student_id=s.id AND sg.is_primary=1
        WHERE dr.id=$preIncident")->fetch();}catch(Throwable $e){}
}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Parent Communications — Discipline Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">

<div class="page-heading">
  <div><h1>Parent Communications</h1><p>Notifications, meeting records, parent responses — <?=e($ay)?></p></div>
</div>
<?php foreach(getFlash() as $f):?><div class="alert alert-<?=$f['type']?>"><?=e($f['message'])?></div><?php endforeach;?>

<?php if($tab==='pending'&&count($pending)>0):?>
<div style="background:rgba(239,68,68,.06);border:1.5px solid var(--error);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:16px">
  <strong style="color:var(--error)">📞 <?=count($pending)?> incident<?=count($pending)!=1?'s':''?> awaiting parent notification</strong>
</div>
<?php endif;?>

<!-- Tabs -->
<div class="tab-bar" style="margin-bottom:16px">
  <a href="?tab=pending"  class="tab-btn <?=$tab==='pending' ?'active':''?>">📞 Pending (<?=count($pending)?>)</a>
  <a href="?tab=history"  class="tab-btn <?=$tab==='history' ?'active':''?>">📋 History (<?=count($history)?>)</a>
  <a href="?tab=notify"   class="tab-btn <?=$tab==='notify'  ?'active':''?>">+ Send Notification</a>
</div>

<?php if($tab==='notify'||$preIncident): ?>
<!-- ── NOTIFY FORM ── -->
<div class="panel" style="padding:22px;margin-bottom:16px">
  <h3 style="font-weight:700;margin-bottom:16px">Record Parent Notification</h3>
  <form method="post">
    <?=csrfField()?><input type="hidden" name="action" value="notify"/>
    <div class="form-grid">
      <div class="form-group">
        <label>Related Case <span style="color:var(--error)">*</span></label>
        <?php if($preInc):?>
        <input type="hidden" name="incident_id" value="<?=$preInc['id']?>"/>
        <input type="hidden" name="student_id"  value="<?=$preInc['spk']?>"/>
        <div style="padding:10px;background:var(--bg2);border-radius:var(--radius-sm);font-size:13px">
          <strong><?=e($preInc['sname'])?></strong> — <?=e(ucwords($preInc['violation_type']??$preInc['category']??'Incident'))?>
          <div style="font-size:12px;color:var(--ink-soft)"><?=date('d M Y',strtotime($preInc['date_occurred']??$preInc['incident_date']??'now'))?></div>
        </div>
        <?php else:?>
        <select name="incident_id" required>
          <option value="">— Select case —</option>
          <?php foreach(array_merge($pending,array_slice(array_values(array_filter(array_map(function($h){return null;},$history))),0)) as $ign):endforeach;?>
          <?php try{$allCases=$pdo->query("SELECT dr.id,CONCAT(s.first_name,' ',s.last_name,' — ',COALESCE(dr.violation_type,dr.category,'Incident'),' (',DATE_FORMAT(COALESCE(dr.date_occurred,dr.incident_date),'%d %b %Y'),')') lbl,dr.student_id FROM discipline_records dr JOIN students s ON s.id=dr.student_id WHERE dr.academic_year_id=$ayId ORDER BY dr.date_occurred DESC LIMIT 50")->fetchAll();}catch(Throwable $e){$allCases=[];}?>
          <?php foreach($allCases as $ac):?><option value="<?=$ac['id']?>" <?=$preIncident===$ac['id']?'selected':''?>><?=e($ac['lbl'])?></option><?php endforeach;?>
        </select>
        <div class="form-group" style="margin-top:10px">
          <label>Student</label>
          <select name="student_id" required>
            <option value="">— Select —</option>
            <?php try{$sts=$pdo->query("SELECT id,student_id,first_name,last_name FROM students WHERE status='Active' ORDER BY last_name,first_name")->fetchAll();}catch(Throwable $e){$sts=[];}?>
            <?php foreach($sts as $st):?><option value="<?=$st['id']?>"><?=e($st['last_name'].', '.$st['first_name'].' ('.$st['student_id'].')')?></option><?php endforeach;?>
          </select>
        </div>
        <?php endif;?>
      </div>
      <div class="form-group">
        <label>Notification Method <span style="color:var(--error)">*</span></label>
        <select name="notification_type" required>
          <?php foreach($notifTypes as $k=>$v):?><option value="<?=$k?>"><?=$v?></option><?php endforeach;?>
        </select>
      </div>
      <div class="form-group">
        <label>Meeting Date (if applicable)</label>
        <input type="datetime-local" name="meeting_date"/>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Message / Summary of Communication</label>
        <textarea name="message" rows="4" placeholder="Describe what was communicated to the parent/guardian, their reaction, any commitments made…"></textarea>
      </div>
    </div>
    <?php if($preInc&&($preInc['gfirst']??false)):?>
    <div style="background:var(--primary-soft);border-radius:var(--radius-sm);padding:12px 14px;margin-bottom:12px;font-size:13px">
      <strong>Guardian on file:</strong> <?=e($preInc['gfirst'].' '.$preInc['glast'])?>
      <?php if($preInc['gphone']):?> &middot; 📞 <?=e($preInc['gphone'])?><?php endif;?>
      <?php if($preInc['gemail']):?> &middot; ✉️ <?=e($preInc['gemail'])?><?php endif;?>
    </div>
    <?php endif;?>
    <div style="display:flex;gap:10px">
      <button type="submit" class="button button-primary">📞 Record Notification</button>
      <a href="notifications.php" class="button button-secondary">Cancel</a>
    </div>
  </form>
</div>

<?php elseif($tab==='pending'): ?>
<!-- ── PENDING LIST ── -->
<?php if(empty($pending)):?>
<div style="text-align:center;padding:56px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:10px">✅</div>
  <h3 style="font-weight:700">All parents notified</h3>
  <p style="color:var(--ink-soft)">No incidents awaiting parent notification.</p>
</div>
<?php else:?>
<div style="display:flex;flex-direction:column;gap:12px">
  <?php foreach($pending as $inc): $sev=$inc['severity']??'minor'; $sc2=$sevCol[$sev]??'var(--ink)';?>
  <div class="panel" style="padding:18px;border-left:4px solid <?=$sc2?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px">
      <div>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px">
          <strong><?=e($inc['sname'])?></strong>
          <span style="font-size:11px;color:var(--ink-faint)"><?=e($inc['sid'])?> &middot; <?=e($inc['cname']??'—')?></span>
          <span style="font-size:11px;color:<?=$sc2?>;font-weight:700"><?=ucfirst($sev)?></span>
        </div>
        <div style="font-size:12.5px;color:var(--ink2)"><?=e(ucwords($inc['violation_type']??$inc['category']??'Other'))?> &middot; <?=date('d M Y',strtotime($inc['date_occurred']??$inc['incident_date']??'now'))?></div>
        <?php if($inc['gfirst']??false):?>
        <div style="font-size:12.5px;color:var(--ink-soft);margin-top:5px">
          👤 <?=e($inc['gfirst'].' '.$inc['glast'])?>
          <?php if($inc['gphone']):?> &middot; 📞 <?=e($inc['gphone'])?><?php endif;?>
          <?php if($inc['gemail']):?> &middot; ✉️ <?=e($inc['gemail'])?><?php endif;?>
        </div>
        <?php else:?><div style="font-size:12px;color:var(--error);margin-top:4px">⚠️ No guardian record on file</div><?php endif;?>
      </div>
      <a href="?tab=notify&incident_id=<?=$inc['id']?>" class="button button-primary button-sm" style="flex-shrink:0">📞 Notify Now</a>
    </div>
  </div>
  <?php endforeach;?>
</div>
<?php endif;?>

<?php else: ?>
<!-- ── HISTORY ── -->
<?php if(empty($history)):?><p style="color:var(--ink-faint);font-size:13px;padding:20px 0">No notifications recorded yet.</p>
<?php else:?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Date</th><th>Student</th><th>Incident</th><th>Method</th><th>Response</th><th>Meeting</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach($history as $h):?>
      <tr>
        <td class="muted"><?=date('d M Y',strtotime($h['sent_at']))?></td>
        <td><strong><?=e($h['sname'])?></strong><div style="font-size:11px;color:var(--ink-faint)"><?=e($h['sid'])?></div></td>
        <td><?=e(ucwords($h['vtype']??'—'))?></td>
        <td><?=e($notifTypes[$h['notification_type']]??ucfirst($h['notification_type']))?></td>
        <td><?php if($h['parent_response']):?><span style="font-size:12px"><?=e(mb_substr($h['parent_response'],0,50))?></span><?php else:?><span class="muted">—</span><?php endif;?></td>
        <td><?php if($h['meeting_date']):?>
          <span style="font-size:12px;color:<?=$h['meeting_attended']===null?'var(--warning)':($h['meeting_attended']?'var(--green)':'var(--error)')?>">
            <?=date('d M',strtotime($h['meeting_date']))?>
            <?=$h['meeting_attended']===null?'(scheduled)':($h['meeting_attended']?'✓ Attended':'✗ Absent')?>
          </span>
        <?php else:?><span class="muted">—</span><?php endif;?></td>
        <td>
          <?php if(!$h['parent_response']||($h['meeting_date']&&$h['meeting_attended']===null)):?>
          <button onclick="document.getElementById('resp<?=$h['id']?>').style.display='table-row'" class="button button-secondary button-sm">Record Response</button>
          <?php endif;?>
        </td>
      </tr>
      <!-- Response row (hidden) -->
      <tr id="resp<?=$h['id']?>" style="display:none;background:var(--bg2)">
        <td colspan="7" style="padding:14px">
          <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <?=csrfField()?><input type="hidden" name="action" value="record_response"/><input type="hidden" name="pn_id" value="<?=$h['id']?>"/>
            <div class="form-group" style="flex:2;min-width:200px"><label>Parent Response</label><textarea name="parent_response" rows="2" placeholder="What did the parent say?"><?=e($h['parent_response']??'')?></textarea></div>
            <?php if($h['meeting_date']):?>
            <div class="form-group"><label>Meeting Notes</label><textarea name="meeting_notes" rows="2" placeholder="Meeting outcome…"><?=e($h['meeting_notes']??'')?></textarea></div>
            <div class="form-group"><label>Meeting Attended?</label><select name="meeting_attended"><option value="">—</option><option value="1">Yes</option><option value="0">No</option></select></div>
            <?php endif;?>
            <button type="submit" class="button button-primary button-sm" style="margin-bottom:4px">💾 Save</button>
          </form>
        </td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>
<?php endif;?>
<?php endif;?>

</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
