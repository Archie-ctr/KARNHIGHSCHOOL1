<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['discipline_officer','principal','vice_principal','sys_admin','school_admin']);
$activePage='actions'; $pdo=db(); $user=currentUser(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
$officer=null; try{$s=$pdo->prepare("SELECT * FROM staff WHERE user_id=? LIMIT 1");$s->execute([$user['id']]);$officer=$s->fetch()?:null;}catch(Throwable $e){}
if(!$officer) $officer=['first_name'=>$user['username']??'Officer','last_name'=>'','id'=>0];

// Ensure tables exist
try{$pdo->exec("CREATE TABLE IF NOT EXISTS discipline_warnings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, student_id INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL, incident_id INT UNSIGNED NULL,
  warning_type VARCHAR(60) NOT NULL DEFAULT 'Verbal Warning', level TINYINT NOT NULL DEFAULT 1,
  description TEXT NOT NULL, action_required TEXT NULL, follow_up_date DATE NULL,
  issued_by INT UNSIGNED NOT NULL, issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved TINYINT(1) NOT NULL DEFAULT 0, resolved_at DATETIME NULL, resolution_notes TEXT NULL,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");}catch(Throwable $e){}
try{$pdo->exec("CREATE TABLE IF NOT EXISTS discipline_actions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, student_id INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL, incident_id INT UNSIGNED NULL,
  action_type VARCHAR(60) NOT NULL, description TEXT NULL, start_date DATE NULL,
  end_date DATE NULL, duration_days INT NULL,
  assigned_by INT UNSIGNED NOT NULL, assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status VARCHAR(20) NOT NULL DEFAULT 'active', completed_at DATETIME NULL, notes TEXT NULL,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");}catch(Throwable $e){}

// POST
if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf(); $pa=$_POST['action']??'';
    if($pa==='issue_action'){
        try{
            $sid=(int)$_POST['student_id']; $at=$_POST['action_type']; $desc=trim($_POST['description']??'');
            $incId=(int)$_POST['incident_id']?:null;
            $start=$_POST['start_date']?:null; $end=$_POST['end_date']?:null;
            $days=(int)$_POST['duration_days']?:null;
            $pdo->prepare("INSERT INTO discipline_actions (student_id,academic_year_id,incident_id,action_type,description,start_date,end_date,duration_days,assigned_by,status)
                VALUES (?,?,?,?,?,?,?,?,?,'active')")->execute([$sid,$ayId,$incId,$at,$desc,$start,$end,$days,$user['id']]);
            // If it's a warning, also log to discipline_warnings
            if(in_array($at,['Verbal Warning','Written Warning','First Warning','Second Warning','Final Warning'])){
                $pdo->prepare("INSERT INTO discipline_warnings (student_id,academic_year_id,incident_id,warning_type,level,description,issued_by)
                    VALUES (?,?,?,?,?,?,?)")->execute([$sid,$ayId,$incId,$at,(int)($_POST['warning_level']??1),$desc,$user['id']]);
            }
            // Auto-note on the incident if linked
            if($incId){
                $pdo->prepare("INSERT INTO discipline_case_notes (incident_id,note_type,note,added_by) VALUES (?,'resolution',?,?)")
                    ->execute([$incId,"Disciplinary action issued: $at".(($desc)?"\n$desc":''),$user['id']]);
            }
            flash('success',$at.' issued successfully.');
        }catch(Throwable $e){flash('error','Failed: '.$e->getMessage());}
        redirect(BASE_URL.'/portal/discipline/warnings.php');
    }
    if($pa==='complete_action'){
        $id=(int)$_POST['action_id'];
        try{$pdo->prepare("UPDATE discipline_actions SET status='completed',completed_at=NOW(),notes=CONCAT(COALESCE(notes,''),' | Completed: ',?) WHERE id=?")->execute([trim($_POST['completion_note']??'Completed'),$id]);flash('success','Action marked complete.');}
        catch(Throwable $e){flash('error','Failed.');}
        redirect(BASE_URL.'/portal/discipline/warnings.php');
    }
    if($pa==='resolve_warning'){
        $id=(int)$_POST['warning_id'];
        try{$pdo->prepare("UPDATE discipline_warnings SET resolved=1,resolved_at=NOW(),resolution_notes=? WHERE id=?")->execute([trim($_POST['resolution_notes']??'Resolved'),$id]);flash('success','Warning resolved.');}
        catch(Throwable $e){flash('error','Failed.');}
        redirect(BASE_URL.'/portal/discipline/warnings.php');
    }
}

// Load data
$tab=$_GET['tab']??'actions';
$fStudent=(int)($_GET['student_id']??0); $fType=trim($_GET['type']??'');

try{
    $aWhere="da.academic_year_id=$ayId"; if($fStudent)$aWhere.=" AND da.student_id=$fStudent"; if($fType)$aWhere.=" AND da.action_type='".addslashes($fType)."'";
    $actions=$pdo->query("SELECT da.*,CONCAT(s.first_name,' ',s.last_name) sname,s.student_id sid,c.name cname
        FROM discipline_actions da JOIN students s ON s.id=da.student_id LEFT JOIN classes c ON c.id=s.current_class_id
        WHERE $aWhere ORDER BY da.assigned_at DESC LIMIT 100")->fetchAll();
}catch(Throwable $e){$actions=[];}

try{
    $wWhere="dw.academic_year_id=$ayId"; if($fStudent)$wWhere.=" AND dw.student_id=$fStudent";
    $warnings=$pdo->query("SELECT dw.*,CONCAT(s.first_name,' ',s.last_name) sname,s.student_id sid,c.name cname
        FROM discipline_warnings dw JOIN students s ON s.id=dw.student_id LEFT JOIN classes c ON c.id=s.current_class_id
        WHERE $wWhere ORDER BY dw.issued_at DESC LIMIT 100")->fetchAll();
}catch(Throwable $e){$warnings=[];}

try{$allStudents=$pdo->query("SELECT id,student_id,first_name,last_name FROM students WHERE status='Active' ORDER BY last_name,first_name")->fetchAll();}catch(Throwable $e){$allStudents=[];}
try{$openCases=$pdo->query("SELECT dr.id,CONCAT(s.first_name,' ',s.last_name,' — ',COALESCE(dr.violation_type,dr.category,'Incident'),' (',DATE_FORMAT(COALESCE(dr.date_occurred,dr.incident_date),'%d %b'),')'  ) lbl,dr.student_id FROM discipline_records dr JOIN students s ON s.id=dr.student_id WHERE dr.academic_year_id=$ayId AND dr.status NOT IN ('closed') ORDER BY dr.date_occurred DESC LIMIT 50")->fetchAll();}catch(Throwable $e){$openCases=[];}

$preIncident=(int)($_GET['incident_id']??0); $preStudent=(int)($_GET['student_id']??0);
$showNew=isset($_GET['action'])&&$_GET['action']==='new';

$actionTypes=['Verbal Warning','Written Warning','First Warning','Second Warning','Final Warning',
              'Detention','Counselling Referral','Suspension','Community Service',
              'Written Apology','Behavioural Contract','Parent Meeting','Expulsion Recommendation','Other'];
$actionColors=['Verbal Warning'=>'var(--green)','Written Warning'=>'var(--green)','First Warning'=>'var(--warning)',
               'Second Warning'=>'var(--warning)','Final Warning'=>'var(--error)','Detention'=>'var(--warning)',
               'Counselling Referral'=>'var(--blue)','Suspension'=>'var(--error)',
               'Community Service'=>'var(--gold)','Parent Meeting'=>'var(--blue)',
               'Expulsion Recommendation'=>'#7c0000','Other'=>'var(--ink-soft)'];
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Disciplinary Actions — KHS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">

<div class="page-heading">
  <div><h1>Disciplinary Actions</h1><p>Warnings, detentions, counselling, suspensions — <?=e($ay)?></p></div>
  <a href="?action=new" class="button button-primary">+ Issue Action</a>
</div>
<?php foreach(getFlash() as $f):?><div class="alert alert-<?=$f['type']?>"><?=e($f['message'])?></div><?php endforeach;?>

<?php if($showNew): ?>
<!-- ── ISSUE NEW ACTION FORM ── -->
<div class="panel" style="padding:22px;margin-bottom:16px">
  <h3 style="font-weight:700;margin-bottom:16px">Issue Disciplinary Action</h3>
  <form method="post">
    <?=csrfField()?><input type="hidden" name="action" value="issue_action"/>
    <div class="form-grid">
      <div class="form-group">
        <label>Student <span style="color:var(--error)">*</span></label>
        <select name="student_id" required>
          <option value="">— Select student —</option>
          <?php foreach($allStudents as $st):?><option value="<?=$st['id']?>" <?=$preStudent===$st['id']?'selected':''?>><?=e($st['last_name'].', '.$st['first_name'].' ('.$st['student_id'].')')?></option><?php endforeach;?>
        </select>
      </div>
      <div class="form-group">
        <label>Action Type <span style="color:var(--error)">*</span></label>
        <select name="action_type" required id="actionTypeSelect" onchange="toggleWarningLevel()">
          <?php foreach($actionTypes as $at):?><option value="<?=$at?>"><?=$at?></option><?php endforeach;?>
        </select>
      </div>
      <div class="form-group" id="warnLevelGroup" style="display:none">
        <label>Warning Level</label>
        <select name="warning_level">
          <option value="1">Level 1 — Minor</option>
          <option value="2">Level 2 — Moderate</option>
          <option value="3">Level 3 — Serious</option>
        </select>
      </div>
      <div class="form-group">
        <label>Related Case (optional)</label>
        <select name="incident_id">
          <option value="">— None —</option>
          <?php foreach($openCases as $oc):?><option value="<?=$oc['id']?>" <?=$preIncident===$oc['id']?'selected':''?>><?=e($oc['lbl'])?></option><?php endforeach;?>
        </select>
      </div>
      <div class="form-group"><label>Start Date</label><input type="date" name="start_date" value="<?=date('Y-m-d')?>"/></div>
      <div class="form-group"><label>End Date / Duration</label><input type="date" name="end_date"/></div>
      <div class="form-group"><label>Duration (days)</label><input type="number" name="duration_days" min="1" placeholder="e.g. 3"/></div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Description / Details <span style="color:var(--error)">*</span></label>
        <textarea name="description" rows="3" required placeholder="Describe the action and reason in detail…"></textarea>
      </div>
    </div>
    <div style="display:flex;gap:10px;margin-top:8px">
      <button type="submit" class="button button-primary">📋 Issue Action</button>
      <a href="warnings.php" class="button button-secondary">Cancel</a>
    </div>
  </form>
</div>
<script>function toggleWarningLevel(){var v=document.getElementById('actionTypeSelect').value;var warnings=['Verbal Warning','Written Warning','First Warning','Second Warning','Final Warning'];document.getElementById('warnLevelGroup').style.display=warnings.includes(v)?'block':'none';}toggleWarningLevel();</script>
<?php endif;?>

<!-- Tabs -->
<div class="tab-bar" style="margin-bottom:16px">
  <a href="?tab=actions"  class="tab-btn <?=$tab==='actions' ?'active':''?>">⚡ All Actions (<?=count($actions)?>)</a>
  <a href="?tab=warnings" class="tab-btn <?=$tab==='warnings'?'active':''?>">⚠️ Warnings (<?=count($warnings)?>)</a>
</div>

<?php if($tab==='actions'): ?>
<!-- Action type filter chips -->
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
  <a href="?tab=actions" style="padding:4px 12px;border-radius:16px;font-size:12px;font-weight:700;border:1.5px solid <?=!$fType?'var(--primary)':'var(--line)'?>;background:<?=!$fType?'var(--primary)':'#fff'?>;color:<?=!$fType?'#fff':'var(--ink-soft)'?>;text-decoration:none">All</a>
  <?php foreach(['Detention','Counselling Referral','Suspension','Community Service','Parent Meeting','Expulsion Recommendation'] as $ft):?>
  <a href="?tab=actions&type=<?=urlencode($ft)?>" style="padding:4px 12px;border-radius:16px;font-size:12px;font-weight:700;border:1.5px solid <?=$fType===$ft?'var(--primary)':'var(--line)'?>;background:<?=$fType===$ft?'var(--primary)':'#fff'?>;color:<?=$fType===$ft?'#fff':'var(--ink-soft)'?>;text-decoration:none"><?=$ft?></a>
  <?php endforeach;?>
</div>
<div class="table-wrap">
  <table>
    <thead><tr><th>Date</th><th>Student</th><th>Class</th><th>Action</th><th>Duration</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
      <?php if(empty($actions)):?><tr><td colspan="7" style="text-align:center;color:var(--ink-faint);padding:32px">No disciplinary actions yet.</td></tr>
      <?php else:foreach($actions as $a):$ac=$actionColors[$a['action_type']]??'var(--ink-soft)';?>
      <tr>
        <td class="muted"><?=date('d M Y',strtotime($a['assigned_at']))?></td>
        <td><strong><?=e($a['sname'])?></strong><div style="font-size:11px;color:var(--ink-faint)"><?=e($a['sid'])?></div></td>
        <td class="muted"><?=e($a['cname']??'—')?></td>
        <td><span style="font-weight:700;color:<?=$ac?>"><?=e($a['action_type'])?></span><?php if($a['description']):?><div style="font-size:11.5px;color:var(--ink-soft)"><?=e(mb_substr($a['description'],0,60))?><?=mb_strlen($a['description'])>60?'…':''?></div><?php endif;?></td>
        <td class="muted"><?=$a['duration_days']?$a['duration_days'].'d':($a['end_date']?date('d M',strtotime($a['start_date']??$a['assigned_at'])).' – '.date('d M',strtotime($a['end_date'])):'—')?></td>
        <td><span class="status <?=$a['status']==='completed'?'approved':'new-s'?>" style="font-size:11px"><?=ucfirst($a['status'])?></span></td>
        <td>
          <?php if($a['status']==='active'):?>
          <form method="post" style="display:inline">
            <?=csrfField()?><input type="hidden" name="action" value="complete_action"/><input type="hidden" name="action_id" value="<?=$a['id']?>"/><input type="hidden" name="completion_note" value="Completed"/>
            <button class="button button-secondary button-sm">✓ Complete</button>
          </form>
          <?php else:?><span class="muted" style="font-size:12px"><?=$a['completed_at']?date('d M',strtotime($a['completed_at'])):'—'?></span><?php endif;?>
        </td>
      </tr>
      <?php endforeach;endif;?>
    </tbody>
  </table>
</div>

<?php else: ?>
<!-- Warnings tab -->
<div class="table-wrap">
  <table>
    <thead><tr><th>Date</th><th>Student</th><th>Class</th><th>Type</th><th>Level</th><th>Follow-up</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
      <?php if(empty($warnings)):?><tr><td colspan="8" style="text-align:center;color:var(--ink-faint);padding:32px">No warnings issued yet.</td></tr>
      <?php else:foreach($warnings as $w):?>
      <tr>
        <td class="muted"><?=date('d M Y',strtotime($w['issued_at']))?></td>
        <td><strong><?=e($w['sname'])?></strong><div style="font-size:11px;color:var(--ink-faint)"><?=e($w['sid'])?></div></td>
        <td class="muted"><?=e($w['cname']??'—')?></td>
        <td><?=e($w['warning_type'])?></td>
        <td><span style="font-weight:700;color:<?=$w['level']>=3?'var(--error)':($w['level']==2?'var(--warning)':'var(--green)')?>">L<?=$w['level']?></span></td>
        <td class="muted"><?=$w['follow_up_date']?date('d M',strtotime($w['follow_up_date'])):'—'?></td>
        <td><span class="status <?=$w['resolved']?'approved':'warning'?>" style="font-size:11px"><?=$w['resolved']?'Resolved':'Pending'?></span></td>
        <td>
          <?php if(!$w['resolved']):?>
          <form method="post" style="display:inline">
            <?=csrfField()?><input type="hidden" name="action" value="resolve_warning"/><input type="hidden" name="warning_id" value="<?=$w['id']?>"/><input type="hidden" name="resolution_notes" value="Resolved"/>
            <button class="button button-secondary button-sm">✓ Resolve</button>
          </form>
          <?php else:?><span class="muted" style="font-size:12px"><?=$w['resolved_at']?date('d M',strtotime($w['resolved_at'])):'—'?></span><?php endif;?>
        </td>
      </tr>
      <?php endforeach;endif;?>
    </tbody>
  </table>
</div>
<?php endif;?>

</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
