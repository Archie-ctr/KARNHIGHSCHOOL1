<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['discipline_officer','principal','vice_principal','sys_admin','school_admin']);
$activePage='recommendations'; $pdo=db(); $user=currentUser(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
$officer=null; try{$s=$pdo->prepare("SELECT * FROM staff WHERE user_id=? LIMIT 1");$s->execute([$user['id']]);$officer=$s->fetch()?:null;}catch(Throwable $e){}
if(!$officer) $officer=['first_name'=>$user['username']??'Officer','last_name'=>'','id'=>0];

try{$pdo->exec("CREATE TABLE IF NOT EXISTS discipline_recommendations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, student_id INT UNSIGNED NOT NULL,
  academic_year_id INT UNSIGNED NOT NULL, incident_id INT UNSIGNED NULL,
  recommendation_type VARCHAR(60) NOT NULL, description TEXT NOT NULL,
  urgency VARCHAR(20) NOT NULL DEFAULT 'medium', recommended_by INT UNSIGNED NOT NULL,
  reviewed_by INT UNSIGNED NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending',
  decision_notes TEXT NULL, decided_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");}catch(Throwable $e){}

$showNew=isset($_GET['action'])&&$_GET['action']==='new';
$preIncident=(int)($_GET['incident_id']??0); $preStudent=(int)($_GET['student_id']??0);

if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf(); $pa=$_POST['action']??'';
    if($pa==='create'){
        try{
            $pdo->prepare("INSERT INTO discipline_recommendations (student_id,academic_year_id,incident_id,recommendation_type,description,urgency,recommended_by,status,created_at) VALUES (?,?,?,?,?,?,?,'pending',NOW())")
                ->execute([(int)$_POST['student_id'],$ayId,(int)$_POST['incident_id']?:null,$_POST['recommendation_type'],trim($_POST['description']),$_POST['urgency']??'medium',$user['id']]);
            // Case note
            if((int)$_POST['incident_id']){
                $pdo->prepare("INSERT INTO discipline_case_notes (incident_id,note_type,note,added_by) VALUES (?,'investigation',?,?)")
                    ->execute([(int)$_POST['incident_id'],"Escalated to VP/Principal: ".$_POST['recommendation_type'],$user['id']]);
                // Update case status to pending_decision
                $pdo->prepare("UPDATE discipline_records SET status='pending_decision',escalated_to=?,escalated_at=NOW(),updated_at=NOW() WHERE id=?")
                    ->execute([$_POST['recommendation_type'],(int)$_POST['incident_id']]);
            }
            flash('success','Recommendation submitted to VP/Principal.');
        }catch(Throwable $e){flash('error','Failed: '.$e->getMessage());}
        redirect(BASE_URL.'/portal/discipline/recommendations.php');
    }
    if($pa==='update_status'){
        $rid=(int)$_POST['rec_id'];
        try{
            $pdo->prepare("UPDATE discipline_recommendations SET status=?,reviewed_by=?,decision_notes=?,decided_at=NOW() WHERE id=?")
                ->execute([$_POST['new_status'],$user['id'],trim($_POST['decision_notes']??''),$rid]);
            flash('success','Decision recorded.');
        }catch(Throwable $e){flash('error','Failed.');}
        redirect(BASE_URL.'/portal/discipline/recommendations.php');
    }
}

try{
    $recs=$pdo->query(
        "SELECT dr.*,CONCAT(s.first_name,' ',s.last_name) sname,s.student_id sid,c.name cname,
                COALESCE(inc.violation_type,inc.category,'—') vtype,
                u.username reviewed_by_name
         FROM discipline_recommendations dr
         JOIN students s ON s.id=dr.student_id
         LEFT JOIN classes c ON c.id=s.current_class_id
         LEFT JOIN discipline_records inc ON inc.id=dr.incident_id
         LEFT JOIN users u ON u.id=dr.reviewed_by
         WHERE dr.academic_year_id=$ayId
         ORDER BY FIELD(dr.urgency,'critical','high','medium','low'),dr.created_at DESC LIMIT 100"
    )->fetchAll();
}catch(Throwable $e){$recs=[];}

try{$allStudents=$pdo->query("SELECT id,student_id,first_name,last_name FROM students WHERE status='Active' ORDER BY last_name,first_name")->fetchAll();}catch(Throwable $e){$allStudents=[];}
try{$openCases=$pdo->query("SELECT dr.id,CONCAT(s.first_name,' ',s.last_name,' — ',COALESCE(dr.violation_type,dr.category,'Incident'),' (',DATE_FORMAT(COALESCE(dr.date_occurred,dr.incident_date),'%d %b %Y'),')') lbl,dr.student_id FROM discipline_records dr JOIN students s ON s.id=dr.student_id WHERE dr.academic_year_id=$ayId AND dr.status NOT IN ('closed','resolved') ORDER BY dr.date_occurred DESC LIMIT 60")->fetchAll();}catch(Throwable $e){$openCases=[];}

$recTypes=['Suspension (1–5 days)','Extended Suspension (5+ days)','Expulsion','Counselling Programme',
           'Behavioural Contract','Permanent Record Note','Transfer Recommendation','Parent Conference',
           'Community Service','Restorative Practices','Other Formal Action'];
$urgencies=['low'=>'Low','medium'=>'Medium','high'=>'High','critical'=>'Critical'];
$urgColors=['low'=>'var(--green)','medium'=>'var(--warning)','high'=>'var(--error)','critical'=>'#7c0000'];
$sColors=['pending'=>'var(--warning)','approved'=>'var(--green)','rejected'=>'var(--error)','implemented'=>'var(--blue)'];
$pending=array_filter($recs,fn($r)=>$r['status']==='pending');
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Recommendations — Discipline Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">

<div class="page-heading">
  <div>
    <h1>Escalate to VP / Principal</h1>
    <p>Recommendations for major disciplinary decisions — <?=e($ay)?></p>
  </div>
  <a href="?action=new" class="button button-primary">+ New Recommendation</a>
</div>
<?php foreach(getFlash() as $f):?><div class="alert alert-<?=$f['type']?>"><?=e($f['message'])?></div><?php endforeach;?>

<?php if(count($pending)>0):?>
<div class="alert alert-warning">⏳ <strong><?=count($pending)?> recommendation<?=count($pending)!=1?'s':''?></strong> awaiting VP/Principal decision.</div>
<?php endif;?>

<?php if($showNew): ?>
<div class="panel" style="padding:22px;margin-bottom:16px">
  <h3 style="font-weight:700;margin-bottom:4px">New Recommendation to VP / Principal</h3>
  <p style="font-size:13px;color:var(--ink-soft);margin-bottom:16px">Major disciplinary decisions (suspensions, expulsions, formal records) must be approved by the Vice Principal or Principal.</p>
  <form method="post">
    <?=csrfField()?><input type="hidden" name="action" value="create"/>
    <div class="form-grid">
      <div class="form-group">
        <label>Student <span style="color:var(--error)">*</span></label>
        <select name="student_id" required id="studentSel" onchange="filterCases()">
          <option value="">— Select student —</option>
          <?php foreach($allStudents as $st):?><option value="<?=$st['id']?>" <?=$preStudent===$st['id']?'selected':''?>><?=e($st['last_name'].', '.$st['first_name'].' ('.$st['student_id'].')')?></option><?php endforeach;?>
        </select>
      </div>
      <div class="form-group">
        <label>Related Case <span style="color:var(--error)">*</span></label>
        <select name="incident_id" required id="caseSel">
          <option value="">— Select case —</option>
          <?php foreach($openCases as $oc):?><option value="<?=$oc['id']?>" data-student="<?=$oc['student_id']?>" <?=$preIncident===$oc['id']?'selected':''?>><?=e($oc['lbl'])?></option><?php endforeach;?>
        </select>
      </div>
      <div class="form-group">
        <label>Recommendation Type <span style="color:var(--error)">*</span></label>
        <select name="recommendation_type" required>
          <?php foreach($recTypes as $rt):?><option value="<?=$rt?>"><?=$rt?></option><?php endforeach;?>
        </select>
      </div>
      <div class="form-group">
        <label>Urgency</label>
        <select name="urgency">
          <?php foreach($urgencies as $uk=>$ul):?><option value="<?=$uk?>"><?=$ul?></option><?php endforeach;?>
        </select>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Justification / Description <span style="color:var(--error)">*</span></label>
        <textarea name="description" rows="5" required placeholder="Provide full justification for this recommendation. Include previous warnings, incident history, witness accounts and any mitigating factors…"></textarea>
      </div>
    </div>
    <div class="alert alert-info" style="margin-bottom:12px">📌 Submitting this recommendation will automatically move the related case to <strong>Pending Decision</strong> status.</div>
    <div style="display:flex;gap:10px">
      <button type="submit" class="button button-primary">📨 Submit Recommendation</button>
      <a href="recommendations.php" class="button button-secondary">Cancel</a>
    </div>
  </form>
</div>
<script>
function filterCases(){
  var sid=document.getElementById('studentSel').value;
  var opts=document.getElementById('caseSel').options;
  for(var i=1;i<opts.length;i++){
    opts[i].style.display=(!sid||opts[i].dataset.student===sid)?'':'none';
  }
}
filterCases();
</script>
<?php endif;?>

<!-- Recommendations table -->
<div class="table-wrap">
  <table>
    <thead><tr><th>Date</th><th>Student</th><th>Incident</th><th>Recommendation</th><th>Urgency</th><th>Status</th><th>Decision</th><th>Action</th></tr></thead>
    <tbody>
      <?php if(empty($recs)):?>
      <tr><td colspan="8" style="text-align:center;color:var(--ink-faint);padding:32px">No recommendations yet.</td></tr>
      <?php else:foreach($recs as $r):
        $uc=$urgColors[$r['urgency']??'medium']??'var(--ink)';
        $sc2=$sColors[$r['status']??'pending']??'var(--ink-soft)';
      ?>
      <tr>
        <td class="muted"><?=date('d M Y',strtotime($r['created_at']))?></td>
        <td><strong><?=e($r['sname'])?></strong><div style="font-size:11px;color:var(--ink-faint)"><?=e($r['sid'])?></div></td>
        <td style="font-size:12.5px"><?=e(ucwords($r['vtype']??'—'))?></td>
        <td><strong style="font-size:13px"><?=e($r['recommendation_type'])?></strong>
          <?php if($r['description']):?><div style="font-size:11.5px;color:var(--ink-soft)"><?=e(mb_substr($r['description'],0,60))?><?=mb_strlen($r['description'])>60?'…':''?></div><?php endif;?></td>
        <td><span style="font-size:11px;font-weight:700;color:<?=$uc?>"><?=ucfirst($r['urgency']??'medium')?></span></td>
        <td><span style="padding:3px 10px;border-radius:10px;font-size:11px;font-weight:700;background:<?=$sc2?>;color:#fff"><?=ucfirst($r['status']??'pending')?></span></td>
        <td style="font-size:12px;color:var(--ink-soft)"><?=$r['decision_notes']?e(mb_substr($r['decision_notes'],0,50)):'—'?><?php if($r['decided_at']):?><div style="font-size:11px"><?=date('d M',strtotime($r['decided_at']))?></div><?php endif;?></td>
        <td>
          <?php if(in_array(currentRole(),['principal','vice_principal','sys_admin','school_admin'])&&$r['status']==='pending'):?>
          <button onclick="document.getElementById('dec<?=$r['id']?>').style.display='table-row'" class="button button-secondary button-sm">Decide</button>
          <?php elseif($r['incident_id']):?>
          <a href="incidents.php?id=<?=$r['incident_id']?>" style="font-size:12px;color:var(--primary)">View Case</a>
          <?php endif;?>
        </td>
      </tr>
      <!-- Decision row -->
      <tr id="dec<?=$r['id']?>" style="display:none;background:var(--bg2)">
        <td colspan="8" style="padding:14px">
          <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <?=csrfField()?><input type="hidden" name="action" value="update_status"/><input type="hidden" name="rec_id" value="<?=$r['id']?>"/>
            <div class="form-group"><label>Decision</label>
              <select name="new_status" style="padding:8px 10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit;font-size:13px">
                <option value="approved">Approved</option><option value="rejected">Rejected</option><option value="implemented">Implemented</option>
              </select>
            </div>
            <div class="form-group" style="flex:2;min-width:220px"><label>Decision Notes</label>
              <input type="text" name="decision_notes" placeholder="Briefly state the decision…" style="width:100%;padding:8px 10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit;font-size:13px"/>
            </div>
            <button type="submit" class="button button-primary button-sm" style="margin-bottom:4px">💾 Record Decision</button>
          </form>
        </td>
      </tr>
      <?php endforeach;endif;?>
    </tbody>
  </table>
</div>

</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
