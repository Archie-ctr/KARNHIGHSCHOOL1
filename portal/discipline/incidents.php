<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['discipline_officer','principal','vice_principal','sys_admin','school_admin']);
$activePage='incidents'; $pdo=db(); $user=currentUser(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
$officer=null; try{$s=$pdo->prepare("SELECT * FROM staff WHERE user_id=? LIMIT 1");$s->execute([$user['id']]);$officer=$s->fetch()?:null;}catch(Throwable $e){}
if(!$officer) $officer=['first_name'=>$user['username']??'Officer','last_name'=>'','id'=>0];

$action     = $_GET['action'] ?? '';
$incidentId = (int)($_GET['id'] ?? 0);

// ── POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $pa = $_POST['action'] ?? '';

    if ($pa === 'create') {
        try {
            $pdo->prepare(
                "INSERT INTO discipline_records
                 (student_id,academic_year_id,date_occurred,incident_date,
                  violation_type,category,description,severity,location,
                  witnesses,action_taken,status,reported_by,parent_notified,created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())"
            )->execute([
                (int)$_POST['student_id'], $ayId,
                $_POST['date_occurred'], $_POST['date_occurred'],
                $_POST['violation_type'], $_POST['violation_type'],
                trim($_POST['description']), $_POST['severity']??'minor',
                trim($_POST['location']??''), trim($_POST['witnesses']??''),
                $_POST['action_taken']??'Pending', $_POST['status']??'open',
                $user['id'], isset($_POST['parent_notified'])?1:0,
            ]);
            $newId = (int)$pdo->lastInsertId();
            // Add opening note if provided
            if (!empty(trim($_POST['opening_note']??''))) {
                $pdo->prepare("INSERT INTO discipline_case_notes (incident_id,note_type,note,added_by) VALUES (?,'general',?,?)")
                    ->execute([$newId, trim($_POST['opening_note']), $user['id']]);
            }
            flash('success','Incident case created successfully.');
            redirect(BASE_URL.'/portal/discipline/incidents.php?id='.$newId);
        } catch (Throwable $e) { flash('error','Failed: '.$e->getMessage()); redirect(BASE_URL.'/portal/discipline/incidents.php?action=new'); }
    }

    if ($pa === 'update' && $incidentId) {
        try {
            $pdo->prepare(
                "UPDATE discipline_records SET violation_type=?,category=?,description=?,severity=?,
                 location=?,witnesses=?,action_taken=?,status=?,parent_notified=?,updated_at=NOW() WHERE id=?"
            )->execute([
                $_POST['violation_type'], $_POST['violation_type'],
                trim($_POST['description']), $_POST['severity']??'minor',
                trim($_POST['location']??''), trim($_POST['witnesses']??''),
                $_POST['action_taken']??'Pending', $_POST['status']??'open',
                isset($_POST['parent_notified'])?1:0, $incidentId
            ]);
            flash('success','Case updated.');
        } catch (Throwable $e) { flash('error','Update failed: '.$e->getMessage()); }
        redirect(BASE_URL.'/portal/discipline/incidents.php?id='.$incidentId);
    }

    if ($pa === 'add_note' && $incidentId) {
        try {
            $pdo->prepare("INSERT INTO discipline_case_notes (incident_id,note_type,note,added_by,is_private) VALUES (?,?,?,?,?)")
                ->execute([$incidentId, $_POST['note_type']??'general', trim($_POST['note']), $user['id'], isset($_POST['is_private'])?1:0]);
            flash('success','Note added.');
        } catch (Throwable $e) { flash('error','Failed: '.$e->getMessage()); }
        redirect(BASE_URL.'/portal/discipline/incidents.php?id='.$incidentId.'#notes');
    }

    if ($pa === 'transition' && $incidentId) {
        $newStatus = $_POST['new_status'] ?? '';
        $allowed   = ['open','investigating','pending_decision','returned','resolved','closed'];
        if (in_array($newStatus, $allowed, true)) {
            try {
                $extra = '';
                $params = [$newStatus];
                if ($newStatus === 'investigating') {
                    $extra  = ',investigation_started=COALESCE(investigation_started,NOW()),investigation_officer=?';
                    $params[]= $_POST['investigation_officer'] ?? '';
                }
                if (in_array($newStatus, ['resolved','closed'])) {
                    $extra .= ',investigation_closed=COALESCE(investigation_closed,NOW())';
                }
                $params[] = $incidentId;
                $pdo->prepare("UPDATE discipline_records SET status=?$extra,updated_at=NOW() WHERE id=?")->execute($params);
                // Auto-note
                $label = ['investigating'=>'Case moved to Investigating','pending_decision'=>'Case escalated — Pending Decision','returned'=>'Case returned for more information','resolved'=>'Case marked Resolved','closed'=>'Case Closed'];
                $note  = $label[$newStatus] ?? "Status changed to $newStatus";
                if (!empty(trim($_POST['transition_note']??''))) $note .= ': '.trim($_POST['transition_note']);
                $pdo->prepare("INSERT INTO discipline_case_notes (incident_id,note_type,note,added_by) VALUES (?,'investigation',?,?)")->execute([$incidentId,$note,$user['id']]);
                flash('success','Case status updated to '.ucfirst(str_replace('_',' ',$newStatus)).'.');
            } catch (Throwable $e) { flash('error','Transition failed.'); }
        }
        redirect(BASE_URL.'/portal/discipline/incidents.php?id='.$incidentId);
    }

    if ($pa === 'delete' && $incidentId) {
        try { $pdo->prepare("DELETE FROM discipline_records WHERE id=?")->execute([$incidentId]); flash('success','Case deleted.'); }
        catch (Throwable $e) { flash('error','Delete failed.'); }
        redirect(BASE_URL.'/portal/discipline/incidents.php');
    }
}

// ── Load single case ──────────────────────────────────────────
$incident = null; $caseNotes = []; $caseActions = [];
if ($incidentId) {
    try {
        $incident = $pdo->query(
            "SELECT dr.*,CONCAT(s.first_name,' ',s.last_name) sname,s.student_id sid,
                    s.id student_pk,c.name cname,g.name gname,
                    g2.first_name guard_first,g2.last_name guard_last,g2.phone guard_phone,g2.email guard_email
             FROM discipline_records dr
             JOIN students s ON s.id=dr.student_id
             LEFT JOIN classes c ON c.id=s.current_class_id
             LEFT JOIN grades g ON g.id=s.current_grade_id
             LEFT JOIN guardians g2 ON g2.student_id=s.id AND g2.is_primary=1
             WHERE dr.id=$incidentId"
        )->fetch();
    } catch(Throwable $e){}
    try {
        $caseNotes = $pdo->query(
            "SELECT cn.*,u.username added_by_name FROM discipline_case_notes cn
             LEFT JOIN users u ON u.id=cn.added_by
             WHERE cn.incident_id=$incidentId ORDER BY cn.added_at ASC"
        )->fetchAll();
    } catch(Throwable $e){$caseNotes=[];}
    try {
        $caseActions = $pdo->query(
            "SELECT da.*,CONCAT(s.first_name,' ',s.last_name) sname FROM discipline_actions da
             JOIN students s ON s.id=da.student_id
             WHERE da.incident_id=$incidentId ORDER BY da.assigned_at DESC"
        )->fetchAll();
    } catch(Throwable $e){$caseActions=[];}
}

// ── List ──────────────────────────────────────────────────────
$fStatus   = $_GET['status']   ?? '';
$fSeverity = $_GET['severity'] ?? '';
$fClass    = (int)($_GET['class_id']??0);
$fSearch   = trim($_GET['q']??'');
$fType     = $_GET['type']??'';
$where     = ["dr.academic_year_id=$ayId"]; $params=[];
if($fStatus)   { $where[]="dr.status=?";             $params[]=$fStatus; }
if($fSeverity) { $where[]="dr.severity=?";            $params[]=$fSeverity; }
if($fClass)    { $where[]="s.current_class_id=?";     $params[]=$fClass; }
if($fType)     { $where[]="(dr.violation_type=? OR dr.category=?)"; $params[]=[$fType,$fType]; $params=array_merge(array_slice($params,0,-1),[$fType,$fType]); }
if($fSearch)   { $where[]="(s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ?)"; $p="%$fSearch%"; $params=array_merge($params,[$p,$p,$p]); }
try {
    $incidents=$pdo->prepare(
        "SELECT dr.*,CONCAT(s.first_name,' ',s.last_name) sname,s.student_id sid,c.name cname
         FROM discipline_records dr
         JOIN students s ON s.id=dr.student_id
         LEFT JOIN classes c ON c.id=s.current_class_id
         WHERE ".implode(' AND ',$where)." ORDER BY dr.date_occurred DESC,dr.id DESC LIMIT 100"
    );
    $incidents->execute($params); $incidents=$incidents->fetchAll();
} catch(Throwable $e){$incidents=[];}

try{$allStudents=$pdo->query("SELECT id,student_id,first_name,last_name FROM students WHERE status='Active' ORDER BY last_name,first_name")->fetchAll();}catch(Throwable $e){$allStudents=[];}
try{$allClasses =$pdo->query("SELECT id,name FROM classes ORDER BY name")->fetchAll();}catch(Throwable $e){$allClasses=[];}

$vTypes    = ['Misconduct','Bullying','Truancy','Fighting','Cheating','Vandalism','Disrespect','Drug/Substance','Uniform Violation','Theft','Harassment','Sexual Misconduct','Threatening Behaviour','Damage to Property','Other'];
$severities= ['minor','moderate','serious','critical'];
$statuses  = ['open','investigating','pending_decision','returned','resolved','closed'];
$statusLabels=['open'=>'Open','investigating'=>'Investigating','pending_decision'=>'Pending Decision','returned'=>'Returned','resolved'=>'Resolved','closed'=>'Closed'];
$sc        = ['open'=>'var(--error)','investigating'=>'var(--warning)','pending_decision'=>'var(--blue)','returned'=>'var(--gold)','resolved'=>'var(--green)','closed'=>'var(--ink-soft)'];
$sevCol    = ['minor'=>'var(--green)','moderate'=>'var(--warning)','serious'=>'var(--error)','critical'=>'#7c0000'];
$noteTypes = ['general'=>'General Note','witness'=>'Witness Statement','evidence'=>'Evidence','investigation'=>'Investigation Note','resolution'=>'Resolution Note'];
$actionTypes=['Warning','Detention','Counselling Referral','Suspension','Community Service','Written Apology','Behavioural Contract','Parent Meeting','Expulsion Recommendation','Other'];
$workflow   = [['open','Open'],['investigating','Investigating'],['pending_decision','Pending Decision'],['returned','Returned'],['resolved','Resolved'],['closed','Closed']];
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Discipline Cases — KHS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
<style>
.case-timeline{display:flex;gap:0;margin-bottom:20px;overflow-x:auto}
.ct-step{flex:1;min-width:90px;text-align:center;padding:8px 4px;border-bottom:3px solid var(--line);font-size:11px;font-weight:700;color:var(--ink-soft);cursor:default}
.ct-step.active{border-color:var(--primary);color:var(--primary)}
.ct-step.done{border-color:var(--green);color:var(--green)}
.note-card{background:var(--bg2);border-radius:var(--radius-sm);padding:12px 14px;margin-bottom:8px}
.note-card.witness{border-left:3px solid var(--blue)}
.note-card.evidence{border-left:3px solid var(--gold)}
.note-card.investigation{border-left:3px solid var(--warning)}
.note-card.resolution{border-left:3px solid var(--green)}
</style>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">

<div class="page-heading">
  <div><h1><?= $incidentId&&$incident?'Case #'.$incidentId.': '.e(mb_substr($incident['violation_type']??$incident['category']??'Incident',0,40)):($action==='new'?'Report New Incident':'Cases / Incidents')?></h1>
  <p><?=e($ay)?></p></div>
  <div style="display:flex;gap:8px">
    <?php if($incidentId):?><a href="incidents.php" class="button button-secondary">← All Cases</a><?php endif;?>
    <?php if(!$incidentId&&$action!=='new'):?><a href="?action=new" class="button button-primary">+ Report Incident</a><?php endif;?>
  </div>
</div>
<?php foreach(getFlash() as $f):?><div class="alert alert-<?=$f['type']?>"><?=e($f['message'])?></div><?php endforeach;?>

<?php if($action==='new' || ($incidentId && $action==='edit' && $incident)): ?>
<!-- ── CREATE / EDIT FORM ── -->
<div class="panel" style="padding:22px;margin-bottom:16px">
  <h3 style="font-weight:700;margin-bottom:16px"><?=$incidentId?'Edit Case':'Report New Incident'?></h3>
  <form method="post" action="?<?=$incidentId?"id=$incidentId":''?>">
    <?=csrfField()?><input type="hidden" name="action" value="<?=$incidentId?'update':'create'?>"/>
    <div class="form-grid">
      <?php if(!$incidentId):?>
      <div class="form-group">
        <label>Student <span style="color:var(--error)">*</span></label>
        <select name="student_id" required><option value="">— Select —</option>
          <?php foreach($allStudents as $st):?><option value="<?=$st['id']?>"><?=e($st['last_name'].', '.$st['first_name'].' ('.$st['student_id'].')')?></option><?php endforeach;?>
        </select>
      </div>
      <div class="form-group">
        <label>Date of Incident <span style="color:var(--error)">*</span></label>
        <input type="date" name="date_occurred" required value="<?=date('Y-m-d')?>" max="<?=date('Y-m-d')?>"/>
      </div>
      <?php endif;?>
      <div class="form-group">
        <label>Violation Type <span style="color:var(--error)">*</span></label>
        <select name="violation_type" required>
          <?php foreach($vTypes as $vt):?><option value="<?=$vt?>" <?=($incident['violation_type']??'')===$vt?'selected':''?>><?=$vt?></option><?php endforeach;?>
        </select>
      </div>
      <div class="form-group">
        <label>Severity</label>
        <select name="severity">
          <?php foreach($severities as $sv):?><option value="<?=$sv?>" <?=($incident['severity']??'minor')===$sv?'selected':''?>><?=ucfirst($sv)?></option><?php endforeach;?>
        </select>
      </div>
      <div class="form-group">
        <label>Location</label>
        <input type="text" name="location" placeholder="e.g. Classroom 3A, Compound, Canteen" value="<?=e($incident['location']??'')?>"/>
      </div>
      <div class="form-group">
        <label>Initial Status</label>
        <select name="status">
          <?php foreach($statuses as $st):?><option value="<?=$st?>" <?=($incident['status']??'open')===$st?'selected':''?>><?=$statusLabels[$st]?></option><?php endforeach;?>
        </select>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Description <span style="color:var(--error)">*</span></label>
        <textarea name="description" rows="4" required placeholder="Describe the incident in full detail…"><?=e($incident['description']??'')?></textarea>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Witness Names</label>
        <input type="text" name="witnesses" placeholder="Names of any witnesses (comma-separated)" value="<?=e($incident['witnesses']??'')?>"/>
      </div>
      <div class="form-group">
        <label>Immediate Action Taken</label>
        <input type="text" name="action_taken" placeholder="e.g. Student sent to office" value="<?=e($incident['action_taken']??'Pending')?>"/>
      </div>
      <?php if(!$incidentId):?>
      <div class="form-group" style="grid-column:1/-1">
        <label>Opening Case Note (optional)</label>
        <textarea name="opening_note" rows="2" placeholder="Any initial observations or context…"></textarea>
      </div>
      <?php endif;?>
      <div class="form-group" style="grid-column:1/-1">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" name="parent_notified" <?=!empty($incident['parent_notified'])?'checked':''?> style="width:auto"/> Parent has already been notified
        </label>
      </div>
    </div>
    <div style="display:flex;gap:10px;margin-top:8px">
      <button type="submit" class="button button-primary">💾 <?=$incidentId?'Update Case':'Create Case'?></button>
      <a href="incidents.php<?=$incidentId?"?id=$incidentId":''?>" class="button button-secondary">Cancel</a>
    </div>
  </form>
</div>

<?php elseif($incidentId && $incident): ?>
<!-- ── CASE DETAIL VIEW ── -->

<!-- Case workflow timeline -->
<div class="case-timeline">
  <?php
  $statusOrder=['open','investigating','pending_decision','returned','resolved','closed'];
  $curIdx=array_search($incident['status']??'open',$statusOrder);
  foreach($workflow as [$st,$lbl]):
    $idx=array_search($st,$statusOrder);
    $cls=$idx<$curIdx?'done':($idx===$curIdx?'active':'');
  ?>
  <div class="ct-step <?=$cls?>"><?=$lbl?></div>
  <?php endforeach;?>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:16px">
  <div>
    <!-- Case summary card -->
    <div class="panel" style="padding:20px;margin-bottom:16px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;margin-bottom:16px">
        <div>
          <h3 style="font-weight:800;font-size:16px;margin-bottom:4px"><?=e(ucwords($incident['violation_type']??$incident['category']??'Incident'))?></h3>
          <div style="font-size:13px;color:var(--ink-soft)"><?=e($incident['sname'])?> &middot; <?=e($incident['sid'])?> &middot; <?=e($incident['cname']??'—')?></div>
          <div style="font-size:12.5px;color:var(--ink-soft);margin-top:2px">
            <?=date('d M Y',strtotime($incident['date_occurred']??$incident['incident_date']??'now'))?>
            <?php if($incident['location']):?> &middot; <?=e($incident['location'])?><?php endif;?>
          </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <span style="padding:4px 12px;border-radius:10px;font-size:12px;font-weight:700;background:<?=$sc[$incident['status']??'open']?>;color:#fff"><?=$statusLabels[$incident['status']??'open']?></span>
          <span style="padding:4px 12px;border-radius:10px;font-size:12px;font-weight:700;color:<?=$sevCol[$incident['severity']??'minor']?>;border:1.5px solid <?=$sevCol[$incident['severity']??'minor']?>"><?=ucfirst($incident['severity']??'minor')?></span>
          <a href="?id=<?=$incidentId?>&action=edit" class="button button-secondary button-sm">✏️ Edit</a>
        </div>
      </div>
      <div class="form-grid">
        <div style="grid-column:1/-1"><label>Description</label><p style="white-space:pre-line;font-size:13.5px"><?=e($incident['description'])?></p></div>
        <?php if($incident['witnesses']):?><div style="grid-column:1/-1"><label>Witnesses</label><p><?=e($incident['witnesses'])?></p></div><?php endif;?>
        <?php if($incident['action_taken']&&$incident['action_taken']!=='Pending'):?><div style="grid-column:1/-1"><label>Immediate Action</label><p><?=e($incident['action_taken'])?></p></div><?php endif;?>
        <div><label>Parent Notified</label><p style="color:<?=$incident['parent_notified']?'var(--green)':'var(--error)'?>;font-weight:700"><?=$incident['parent_notified']?'Yes ✓':'No ✗'?><?php if($incident['parent_notification_date']):?> <span style="font-size:11px;color:var(--ink-soft)">(<?=date('d M',strtotime($incident['parent_notification_date']))?>) </span><?php endif;?></p></div>
        <?php if($incident['investigation_officer']):?><div><label>Investigating Officer</label><p><?=e($incident['investigation_officer'])?></p></div><?php endif;?>
      </div>
    </div>

    <!-- Case notes / timeline -->
    <div class="panel" style="padding:20px;margin-bottom:16px" id="notes">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <h3 style="font-weight:700;font-size:13px">📝 Case Notes &amp; Timeline</h3>
        <button onclick="document.getElementById('addNoteForm').style.display='block';this.style.display='none'" class="button button-secondary button-sm">+ Add Note</button>
      </div>
      <!-- Add note form -->
      <div id="addNoteForm" style="display:none;background:var(--bg2);border-radius:var(--radius-sm);padding:14px;margin-bottom:14px">
        <form method="post" action="?id=<?=$incidentId?>#notes">
          <?=csrfField()?><input type="hidden" name="action" value="add_note"/>
          <div class="form-grid">
            <div class="form-group"><label>Note Type</label>
              <select name="note_type"><?php foreach($noteTypes as $nt=>$nl):?><option value="<?=$nt?>"><?=$nl?></option><?php endforeach;?></select>
            </div>
            <div class="form-group" style="display:flex;align-items:center;gap:8px;padding-top:20px">
              <label style="display:flex;align-items:center;gap:6px;cursor:pointer;white-space:nowrap">
                <input type="checkbox" name="is_private" style="width:auto"/> Private (officer only)
              </label>
            </div>
            <div class="form-group" style="grid-column:1/-1"><label>Note <span style="color:var(--error)">*</span></label>
              <textarea name="note" rows="3" required placeholder="Enter note details…"></textarea>
            </div>
          </div>
          <div style="display:flex;gap:8px">
            <button type="submit" class="button button-primary button-sm">💾 Save Note</button>
            <button type="button" onclick="document.getElementById('addNoteForm').style.display='none';this.parentNode.parentNode.querySelector('button').style.display='inline-flex'" class="button button-secondary button-sm">Cancel</button>
          </div>
        </form>
      </div>
      <!-- Notes list -->
      <?php if(empty($caseNotes)):?>
      <p style="color:var(--ink-faint);font-size:13px">No notes yet. Add the first case note above.</p>
      <?php else:foreach($caseNotes as $cn):?>
      <div class="note-card <?=$cn['note_type']?>">
        <div style="display:flex;justify-content:space-between;font-size:11.5px;color:var(--ink-soft);margin-bottom:6px">
          <span style="font-weight:700;color:var(--ink)"><?=ucfirst(str_replace('_',' ',$cn['note_type']))?><?=$cn['is_private']?' 🔒':''?></span>
          <span><?=e($cn['added_by_name']??'Officer')?> &middot; <?=date('d M Y H:i',strtotime($cn['added_at']))?></span>
        </div>
        <p style="font-size:13.5px;white-space:pre-line;margin:0"><?=e($cn['note'])?></p>
      </div>
      <?php endforeach;endif;?>
    </div>

    <!-- Actions taken -->
    <?php if(!empty($caseActions)):?>
    <div class="panel" style="padding:20px;margin-bottom:16px">
      <h3 style="font-weight:700;font-size:13px;margin-bottom:14px">⚡ Disciplinary Actions</h3>
      <?php foreach($caseActions as $a):?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:9px 0;border-bottom:1px solid var(--line)">
        <div><strong><?=e($a['action_type'])?></strong><div style="font-size:12px;color:var(--ink-soft)"><?=$a['start_date']?date('d M Y',strtotime($a['start_date'])):date('d M Y',strtotime($a['assigned_at']))?><?php if($a['duration_days']):?> &middot; <?=$a['duration_days']?> day<?=$a['duration_days']!=1?'s':''?><?php endif;?></div></div>
        <span class="status <?=$a['status']==='completed'?'approved':'new-s'?>" style="font-size:11px"><?=ucfirst($a['status'])?></span>
      </div>
      <?php endforeach;?>
    </div>
    <?php endif;?>
  </div>

  <!-- Right panel: actions -->
  <div style="display:flex;flex-direction:column;gap:12px">

    <!-- Status transition -->
    <div class="panel" style="padding:18px">
      <h4 style="font-weight:700;font-size:13px;margin-bottom:12px">🔄 Update Case Status</h4>
      <form method="post" action="?id=<?=$incidentId?>">
        <?=csrfField()?><input type="hidden" name="action" value="transition"/>
        <div class="form-group" style="margin-bottom:10px">
          <label>Move to</label>
          <select name="new_status" style="width:100%;padding:8px 10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit;font-size:13px">
            <?php foreach($statuses as $st):if($st===($incident['status']??'open'))continue;?>
            <option value="<?=$st?>"><?=$statusLabels[$st]?></option>
            <?php endforeach;?>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:10px">
          <label>Investigating Officer (if applicable)</label>
          <input type="text" name="investigation_officer" value="<?=e($incident['investigation_officer']??'')?>" placeholder="Officer name" style="width:100%;padding:8px 10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit;font-size:13px"/>
        </div>
        <div class="form-group" style="margin-bottom:10px">
          <label>Transition Note</label>
          <textarea name="transition_note" rows="2" placeholder="Optional note about this transition…" style="width:100%;padding:8px 10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit;font-size:13px;resize:vertical"></textarea>
        </div>
        <button type="submit" class="button button-primary" style="width:100%">Update Status</button>
      </form>
    </div>

    <!-- Quick links -->
    <div class="panel" style="padding:18px">
      <h4 style="font-weight:700;font-size:13px;margin-bottom:12px">⚡ Quick Actions</h4>
      <div style="display:flex;flex-direction:column;gap:8px">
        <a href="warnings.php?action=new&incident_id=<?=$incidentId?>&student_id=<?=$incident['student_pk']??''?>" class="button button-secondary" style="text-align:center">📋 Issue Disciplinary Action</a>
        <a href="notifications.php?incident_id=<?=$incidentId?>" class="button button-secondary <?=$incident['parent_notified']?'':'button-primary'?>" style="text-align:center">📞 <?=$incident['parent_notified']?'View Parent Comm':'Notify Parent'?></a>
        <a href="recommendations.php?action=new&incident_id=<?=$incidentId?>&student_id=<?=$incident['student_pk']??''?>" class="button button-secondary" style="text-align:center">📨 Escalate to VP/Principal</a>
        <a href="students.php?id=<?=$incident['student_pk']??''?>" class="button button-secondary" style="text-align:center">🎓 Student History</a>
      </div>
    </div>

    <!-- Guardian info -->
    <?php if($incident['guard_first']??false):?>
    <div class="panel" style="padding:18px">
      <h4 style="font-weight:700;font-size:13px;margin-bottom:10px">👨‍👩‍👧 Guardian</h4>
      <div style="font-size:13px"><strong><?=e($incident['guard_first'].' '.$incident['guard_last'])?></strong></div>
      <?php if($incident['guard_phone']):?><div style="font-size:12.5px;color:var(--ink-soft);margin-top:4px">📞 <?=e($incident['guard_phone'])?></div><?php endif;?>
      <?php if($incident['guard_email']):?><div style="font-size:12.5px;color:var(--ink-soft);margin-top:2px">✉️ <?=e($incident['guard_email'])?></div><?php endif;?>
    </div>
    <?php endif;?>

    <!-- Delete -->
    <form method="post" action="?id=<?=$incidentId?>" onsubmit="return confirm('Permanently delete this case? This cannot be undone.')">
      <?=csrfField()?><input type="hidden" name="action" value="delete"/>
      <button class="button button-danger" style="width:100%">🗑 Delete Case</button>
    </form>
  </div>
</div><!-- grid -->

<?php else: ?>
<!-- ── CASE LIST ── -->
<form method="get" class="filter-bar" style="margin-bottom:16px">
  <input type="text" name="q" placeholder="Search student…" value="<?=e($fSearch)?>"/>
  <select name="status"><option value="">All Statuses</option><?php foreach($statuses as $st):?><option value="<?=$st?>" <?=$fStatus===$st?'selected':''?>><?=$statusLabels[$st]?></option><?php endforeach;?></select>
  <select name="severity"><option value="">All Severities</option><?php foreach($severities as $sv):?><option value="<?=$sv?>" <?=$fSeverity===$sv?'selected':''?>><?=ucfirst($sv)?></option><?php endforeach;?></select>
  <select name="class_id"><option value="">All Classes</option><?php foreach($allClasses as $cl):?><option value="<?=$cl['id']?>" <?=$fClass==$cl['id']?'selected':''?>><?=e($cl['name'])?></option><?php endforeach;?></select>
  <button type="submit" class="button button-secondary button-sm">Filter</button>
  <a href="incidents.php" class="button button-secondary button-sm">Reset</a>
</form>
<div class="table-wrap">
  <table>
    <thead><tr><th>Date</th><th>Student</th><th>Class</th><th>Type</th><th>Severity</th><th>Status</th><th>Parent</th><th></th></tr></thead>
    <tbody>
      <?php if(empty($incidents)):?>
      <tr><td colspan="8" style="text-align:center;color:var(--ink-faint);padding:32px">No cases found. <a href="?action=new">Report one →</a></td></tr>
      <?php else:foreach($incidents as $inc):
        $stc=$sc[$inc['status']??'open']??'var(--ink-soft)';
        $sev=$inc['severity']??'minor';
      ?>
      <tr>
        <td class="muted"><?=date('d M Y',strtotime($inc['date_occurred']??$inc['incident_date']??'now'))?></td>
        <td><strong><?=e($inc['sname'])?></strong><div style="font-size:11px;color:var(--ink-faint)"><?=e($inc['sid'])?></div></td>
        <td class="muted"><?=e($inc['cname']??'—')?></td>
        <td><?=e(ucwords($inc['violation_type']??$inc['category']??'Other'))?></td>
        <td><span style="font-size:11px;font-weight:700;color:<?=$sevCol[$sev]??'inherit'?>"><?=ucfirst($sev)?></span></td>
        <td><span style="font-size:11px;padding:2px 8px;border-radius:10px;background:<?=$stc?>;color:#fff;font-weight:600"><?=$statusLabels[$inc['status']??'open']?></span></td>
        <td style="color:<?=$inc['parent_notified']?'var(--green)':'var(--error)'?>;font-weight:700;font-size:12px"><?=$inc['parent_notified']?'✓ Yes':'✗ No'?></td>
        <td><a href="?id=<?=$inc['id']?>" class="button button-secondary button-sm">View</a></td>
      </tr>
      <?php endforeach;endif;?>
    </tbody>
  </table>
</div>
<?php endif;?>

</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
