<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['discipline_officer','principal','vice_principal','sys_admin','school_admin']);
$activePage='investigations'; $pdo=db(); $user=currentUser(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
$officer=null; try{$s=$pdo->prepare("SELECT * FROM staff WHERE user_id=? LIMIT 1");$s->execute([$user['id']]);$officer=$s->fetch()?:null;}catch(Throwable $e){}
if(!$officer) $officer=['first_name'=>$user['username']??'Officer','last_name'=>'','id'=>0];

$incidentId=(int)($_GET['id']??0);
$fStatus   =trim($_GET['status']??'');

if($_SERVER['REQUEST_METHOD']==='POST'){
    verifyCsrf();
    $pa=$_POST['action']??'';
    $id=(int)($_POST['incident_id']??$incidentId);

    if($pa==='update_investigation'&&$id){
        try{
            $ns=$_POST['new_status']??'investigating';
            $extra=''; $params=[$ns];
            if($ns==='investigating'){$extra.=',investigation_started=COALESCE(investigation_started,NOW()),investigation_officer=?';$params[]=$_POST['investigation_officer']??'';}
            if(in_array($ns,['resolved','closed'])){$extra.=',investigation_closed=COALESCE(investigation_closed,NOW())';}
            $params[]=$id;
            $pdo->prepare("UPDATE discipline_records SET status=?$extra,updated_at=NOW() WHERE id=?")->execute($params);
            if(!empty(trim($_POST['investigation_notes']??''))){
                $pdo->prepare("UPDATE discipline_records SET investigation_notes=?,investigation_officer=COALESCE(NULLIF(?,''),investigation_officer) WHERE id=?")->execute([trim($_POST['investigation_notes']),$_POST['investigation_officer']??'',$id]);
            }
            if(!empty(trim($_POST['case_note']??''))){
                $pdo->prepare("INSERT INTO discipline_case_notes (incident_id,note_type,note,added_by) VALUES (?,'investigation',?,?)")->execute([$id,trim($_POST['case_note']),$user['id']]);
            }
            flash('success','Investigation updated.');
        }catch(Throwable $e){flash('error','Failed: '.$e->getMessage());}
        redirect(BASE_URL.'/portal/discipline/investigations.php?id='.$id);
    }

    if($pa==='add_witness_note'&&$id){
        try{
            $pdo->prepare("INSERT INTO discipline_case_notes (incident_id,note_type,note,added_by) VALUES (?,'witness',?,?)")->execute([$id,trim($_POST['witness_note']),$user['id']]);
            flash('success','Witness statement recorded.');
        }catch(Throwable $e){flash('error','Failed.');}
        redirect(BASE_URL.'/portal/discipline/investigations.php?id='.$id.'#witnesses');
    }

    if($pa==='add_evidence'&&$id){
        try{
            $pdo->prepare("INSERT INTO discipline_case_notes (incident_id,note_type,note,added_by) VALUES (?,'evidence',?,?)")->execute([$id,trim($_POST['evidence_note']),$user['id']]);
            flash('success','Evidence note added.');
        }catch(Throwable $e){flash('error','Failed.');}
        redirect(BASE_URL.'/portal/discipline/investigations.php?id='.$id.'#evidence');
    }
}

// Single case
$incident=null; $notes=[];
if($incidentId){
    try{
        $incident=$pdo->query(
            "SELECT dr.*,CONCAT(s.first_name,' ',s.last_name) sname,s.student_id sid,
                    s.id spk,c.name cname,g.name gname
             FROM discipline_records dr
             JOIN students s ON s.id=dr.student_id
             LEFT JOIN classes c ON c.id=s.current_class_id
             LEFT JOIN grades g ON g.id=s.current_grade_id
             WHERE dr.id=$incidentId"
        )->fetch();
    }catch(Throwable $e){}
    try{
        $notes=$pdo->query(
            "SELECT cn.*,u.username uname FROM discipline_case_notes cn
             LEFT JOIN users u ON u.id=cn.added_by
             WHERE cn.incident_id=$incidentId ORDER BY cn.added_at ASC"
        )->fetchAll();
    }catch(Throwable $e){$notes=[];}
}

// Active cases list
try{
    $where="dr.academic_year_id=$ayId";
    if($fStatus) $where.=" AND dr.status='".addslashes($fStatus)."'";
    else $where.=" AND dr.status IN ('open','investigating','pending_decision')";
    $cases=$pdo->query(
        "SELECT dr.*,CONCAT(s.first_name,' ',s.last_name) sname,s.student_id sid,c.name cname,
                (SELECT COUNT(*) FROM discipline_case_notes dcn WHERE dcn.incident_id=dr.id) note_count
         FROM discipline_records dr
         JOIN students s ON s.id=dr.student_id
         LEFT JOIN classes c ON c.id=s.current_class_id
         WHERE $where ORDER BY dr.severity DESC,dr.date_occurred ASC LIMIT 60"
    )->fetchAll();
}catch(Throwable $e){$cases=[];}

$sc     =['open'=>'var(--error)','investigating'=>'var(--warning)','pending_decision'=>'var(--blue)','returned'=>'var(--gold)','resolved'=>'var(--green)','closed'=>'var(--ink-soft)'];
$scl    =['open'=>'Open','investigating'=>'Investigating','pending_decision'=>'Pending Decision','returned'=>'Returned','resolved'=>'Resolved','closed'=>'Closed'];
$sevc   =['minor'=>'var(--green)','moderate'=>'var(--warning)','serious'=>'var(--error)','critical'=>'#7c0000'];
$statuses=['open','investigating','pending_decision','returned','resolved','closed'];
$workflow=[['open','Open'],['investigating','Investigating'],['pending_decision','Pending Decision'],['returned','Returned'],['resolved','Resolved'],['closed','Closed']];
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Investigations — Discipline Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
<style>
.case-timeline{display:flex;gap:0;margin-bottom:20px;overflow-x:auto}
.ct-step{flex:1;min-width:80px;text-align:center;padding:8px 4px;border-bottom:3px solid var(--line);font-size:11px;font-weight:700;color:var(--ink-soft)}
.ct-step.active{border-color:var(--primary);color:var(--primary)}
.ct-step.done{border-color:var(--green);color:var(--green)}
.note-badge{display:inline-block;font-size:10px;font-weight:700;padding:2px 7px;border-radius:8px;margin-right:4px}
.nb-witness{background:var(--blue-soft);color:var(--blue)}
.nb-evidence{background:var(--gold-soft);color:var(--gold)}
.nb-investigation{background:var(--warning-soft);color:var(--warning)}
.nb-general{background:var(--bg2);color:var(--ink-soft)}
.nb-resolution{background:var(--green-soft);color:var(--green)}
</style>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">

<div class="page-heading">
  <div><h1>Investigations</h1><p>Active cases requiring action — <?=e($ay)?></p></div>
  <a href="incidents.php?action=new" class="button button-primary">+ Report Incident</a>
</div>
<?php foreach(getFlash() as $f):?><div class="alert alert-<?=$f['type']?>"><?=e($f['message'])?></div><?php endforeach;?>

<?php if($incidentId&&$incident): ?>
<!-- ── SINGLE CASE INVESTIGATION ── -->
<a href="investigations.php" style="font-size:13px;color:var(--primary);text-decoration:none;margin-bottom:12px;display:inline-block">← Back to Active Cases</a>

<!-- Timeline -->
<div class="case-timeline">
  <?php $ord=['open','investigating','pending_decision','returned','resolved','closed']; $ci=array_search($incident['status']??'open',$ord);
  foreach($workflow as [$st,$lbl]): $idx=array_search($st,$ord); $cls=$idx<$ci?'done':($idx===$ci?'active':'');?>
  <div class="ct-step <?=$cls?>"><?=$lbl?></div>
  <?php endforeach;?>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:16px">
  <div>
    <!-- Summary -->
    <div class="panel" style="padding:20px;margin-bottom:14px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;margin-bottom:14px">
        <div>
          <h3 style="font-weight:800;font-size:16px;margin-bottom:3px"><?=e(ucwords($incident['violation_type']??$incident['category']??'Incident'))?></h3>
          <p style="font-size:13px;color:var(--ink-soft)"><?=e($incident['sname'])?> &middot; <?=e($incident['sid'])?> &middot; <?=e($incident['cname']??'—')?>
          &middot; <?=date('d M Y',strtotime($incident['date_occurred']??$incident['incident_date']??'now'))?></p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <span style="padding:4px 12px;border-radius:10px;font-size:12px;font-weight:700;background:<?=$sc[$incident['status']??'open']?>;color:#fff"><?=$scl[$incident['status']??'open']?></span>
          <span style="padding:4px 12px;border-radius:10px;font-size:12px;font-weight:700;color:<?=$sevc[$incident['severity']??'minor']?>;border:1.5px solid <?=$sevc[$incident['severity']??'minor']?>"><?=ucfirst($incident['severity']??'minor')?></span>
        </div>
      </div>
      <p style="font-size:13.5px;white-space:pre-line;border-top:1px solid var(--line);padding-top:12px"><?=e($incident['description'])?></p>
      <?php if($incident['witnesses']):?>
      <p style="margin-top:10px;font-size:13px"><strong>Witnesses recorded:</strong> <?=e($incident['witnesses'])?></p>
      <?php endif;?>
    </div>

    <!-- Investigation notes panel -->
    <div class="panel" style="padding:20px;margin-bottom:14px">
      <h4 style="font-weight:700;font-size:13px;margin-bottom:12px">🔍 Investigation Notes</h4>
      <?php if($incident['investigation_notes']):?>
      <div style="background:var(--warning-soft);border-left:3px solid var(--warning);border-radius:var(--radius-sm);padding:12px 14px;margin-bottom:12px;font-size:13.5px;white-space:pre-line"><?=e($incident['investigation_notes'])?></div>
      <div style="font-size:12px;color:var(--ink-soft);margin-bottom:12px">Officer: <?=e($incident['investigation_officer']??'—')?>
      <?php if($incident['investigation_started']):?> &middot; Started: <?=date('d M Y',strtotime($incident['investigation_started']))?><?php endif;?></div>
      <?php else:?><p style="color:var(--ink-faint);font-size:13px;margin-bottom:12px">No formal investigation notes yet.</p><?php endif;?>
      <form method="post" action="?id=<?=$incidentId?>">
        <?=csrfField()?><input type="hidden" name="action" value="update_investigation"/>
        <input type="hidden" name="incident_id" value="<?=$incidentId?>"/>
        <div class="form-grid">
          <div class="form-group"><label>Move to Status</label>
            <select name="new_status" style="width:100%;padding:8px 10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit;font-size:13px">
              <?php foreach($statuses as $st):if($st===($incident['status']??'open'))continue;?>
              <option value="<?=$st?>"><?=$scl[$st]?></option><?php endforeach;?>
            </select>
          </div>
          <div class="form-group"><label>Investigating Officer</label>
            <input type="text" name="investigation_officer" value="<?=e($incident['investigation_officer']??'')?>" placeholder="Name…" style="width:100%;padding:8px 10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit;font-size:13px"/>
          </div>
          <div class="form-group" style="grid-column:1/-1"><label>Update Investigation Notes</label>
            <textarea name="investigation_notes" rows="3" placeholder="Findings, interviews conducted, conclusions…" style="width:100%;padding:10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit;font-size:13px;resize:vertical"><?=e($incident['investigation_notes']??'')?></textarea>
          </div>
          <div class="form-group" style="grid-column:1/-1"><label>Case Note for this update</label>
            <textarea name="case_note" rows="2" placeholder="Note to add to case timeline…" style="width:100%;padding:10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit;font-size:13px;resize:vertical"></textarea>
          </div>
        </div>
        <button type="submit" class="button button-primary">💾 Save Investigation Update</button>
      </form>
    </div>

    <!-- Witness statements -->
    <div class="panel" style="padding:20px;margin-bottom:14px" id="witnesses">
      <h4 style="font-weight:700;font-size:13px;margin-bottom:12px">👁 Witness Statements</h4>
      <?php $witnesses=array_filter($notes,fn($n)=>$n['note_type']==='witness');?>
      <?php if(empty($witnesses)):?><p style="color:var(--ink-faint);font-size:13px;margin-bottom:12px">No witness statements recorded yet.</p><?php else:foreach($witnesses as $w):?>
      <div style="background:var(--blue-soft);border-left:3px solid var(--blue);border-radius:var(--radius-sm);padding:12px 14px;margin-bottom:8px">
        <div style="font-size:11px;color:var(--blue);font-weight:700;margin-bottom:4px">Witness Statement &middot; <?=e($w['uname']??'Officer')?> &middot; <?=date('d M Y H:i',strtotime($w['added_at']))?></div>
        <p style="font-size:13px;margin:0;white-space:pre-line"><?=e($w['note'])?></p>
      </div>
      <?php endforeach;endif;?>
      <form method="post" action="?id=<?=$incidentId?>#witnesses" style="margin-top:10px">
        <?=csrfField()?><input type="hidden" name="action" value="add_witness_note"/>
        <input type="hidden" name="incident_id" value="<?=$incidentId?>"/>
        <textarea name="witness_note" rows="3" required placeholder="Record witness statement — name and account of events…" style="width:100%;padding:10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit;font-size:13px;resize:vertical;margin-bottom:8px"></textarea>
        <button type="submit" class="button button-secondary button-sm">+ Record Witness Statement</button>
      </form>
    </div>

    <!-- Evidence -->
    <div class="panel" style="padding:20px;margin-bottom:14px" id="evidence">
      <h4 style="font-weight:700;font-size:13px;margin-bottom:12px">🗂 Evidence &amp; Documents</h4>
      <?php $evidence=array_filter($notes,fn($n)=>$n['note_type']==='evidence');?>
      <?php if(empty($evidence)):?><p style="color:var(--ink-faint);font-size:13px;margin-bottom:12px">No evidence recorded.</p><?php else:foreach($evidence as $ev):?>
      <div style="background:var(--gold-soft);border-left:3px solid var(--gold);border-radius:var(--radius-sm);padding:12px 14px;margin-bottom:8px">
        <div style="font-size:11px;color:var(--gold);font-weight:700;margin-bottom:4px">Evidence &middot; <?=e($ev['uname']??'Officer')?> &middot; <?=date('d M Y H:i',strtotime($ev['added_at']))?></div>
        <p style="font-size:13px;margin:0;white-space:pre-line"><?=e($ev['note'])?></p>
      </div>
      <?php endforeach;endif;?>
      <form method="post" action="?id=<?=$incidentId?>#evidence" style="margin-top:10px">
        <?=csrfField()?><input type="hidden" name="action" value="add_evidence"/>
        <input type="hidden" name="incident_id" value="<?=$incidentId?>"/>
        <textarea name="evidence_note" rows="3" required placeholder="Describe evidence, documents, CCTV footage, physical items…" style="width:100%;padding:10px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit;font-size:13px;resize:vertical;margin-bottom:8px"></textarea>
        <button type="submit" class="button button-secondary button-sm">+ Record Evidence</button>
      </form>
    </div>

    <!-- Full case timeline -->
    <div class="panel" style="padding:20px">
      <h4 style="font-weight:700;font-size:13px;margin-bottom:12px">📋 Full Case Timeline</h4>
      <?php if(empty($notes)):?><p style="color:var(--ink-faint);font-size:13px">No notes yet.</p>
      <?php else:foreach($notes as $n):
        $badgeClass=['witness'=>'nb-witness','evidence'=>'nb-evidence','investigation'=>'nb-investigation','resolution'=>'nb-resolution'][$n['note_type']]??'nb-general';
      ?>
      <div style="border-bottom:1px solid var(--line);padding:10px 0">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:5px">
          <span class="note-badge <?=$badgeClass?>"><?=ucfirst(str_replace('_',' ',$n['note_type']))?><?=$n['is_private']?' 🔒':''?></span>
          <span style="font-size:11px;color:var(--ink-soft)"><?=e($n['uname']??'Officer')?> &middot; <?=date('d M Y H:i',strtotime($n['added_at']))?></span>
        </div>
        <p style="font-size:13px;margin:0;white-space:pre-line"><?=e($n['note'])?></p>
      </div>
      <?php endforeach;endif;?>
    </div>
  </div>

  <!-- Right: actions -->
  <div style="display:flex;flex-direction:column;gap:12px">
    <div class="panel" style="padding:18px">
      <h4 style="font-weight:700;font-size:13px;margin-bottom:12px">⚡ Quick Actions</h4>
      <div style="display:flex;flex-direction:column;gap:8px">
        <a href="incidents.php?id=<?=$incidentId?>" class="button button-secondary" style="text-align:center">📄 View Full Case</a>
        <a href="warnings.php?action=new&incident_id=<?=$incidentId?>&student_id=<?=$incident['spk']??''?>" class="button button-secondary" style="text-align:center">📋 Issue Disciplinary Action</a>
        <a href="notifications.php?incident_id=<?=$incidentId?>" class="button button-secondary <?=$incident['parent_notified']?'':'button-primary'?>" style="text-align:center">📞 <?=$incident['parent_notified']?'Parent Comm History':'Notify Parent'?></a>
        <a href="recommendations.php?action=new&incident_id=<?=$incidentId?>&student_id=<?=$incident['spk']??''?>" class="button button-secondary" style="text-align:center">📨 Escalate to VP/Principal</a>
      </div>
    </div>
    <div class="panel" style="padding:18px">
      <h4 style="font-weight:700;font-size:13px;margin-bottom:10px">📊 Case Summary</h4>
      <div style="font-size:13px;display:flex;flex-direction:column;gap:7px">
        <div style="display:flex;justify-content:space-between"><span class="muted">Notes</span><strong><?=count($notes)?></strong></div>
        <div style="display:flex;justify-content:space-between"><span class="muted">Witnesses</span><strong><?=count(array_filter($notes,fn($n)=>$n['note_type']==='witness'))?></strong></div>
        <div style="display:flex;justify-content:space-between"><span class="muted">Evidence</span><strong><?=count(array_filter($notes,fn($n)=>$n['note_type']==='evidence'))?></strong></div>
        <div style="display:flex;justify-content:space-between"><span class="muted">Parent Notified</span><strong style="color:<?=$incident['parent_notified']?'var(--green)':'var(--error)'?>"><?=$incident['parent_notified']?'Yes':'No'?></strong></div>
        <?php if($incident['investigation_started']):?><div style="display:flex;justify-content:space-between"><span class="muted">Started</span><strong><?=date('d M',strtotime($incident['investigation_started']))?></strong></div><?php endif;?>
      </div>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ── ACTIVE CASES LIST ── -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
  <?php foreach([''=> 'Active (all)','open'=>'Open','investigating'=>'Investigating','pending_decision'=>'Pending Decision'] as $st=>$lbl):?>
  <a href="?status=<?=$st?>" style="padding:6px 14px;border-radius:20px;font-size:12.5px;font-weight:700;border:1.5px solid <?=$fStatus===$st?'var(--primary)':'var(--line)'?>;background:<?=$fStatus===$st?'var(--primary)':'#fff'?>;color:<?=$fStatus===$st?'#fff':'var(--ink2)'?>;text-decoration:none"><?=$lbl?></a>
  <?php endforeach;?>
</div>
<?php if(empty($cases)):?>
<div style="text-align:center;padding:56px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:40px;margin-bottom:12px">✅</div>
  <h3 style="font-weight:700;margin-bottom:6px">No Active Cases</h3>
  <p style="color:var(--ink-soft)">All incidents have been resolved or closed.</p>
</div>
<?php else:?>
<div style="display:flex;flex-direction:column;gap:10px">
  <?php foreach($cases as $c):
    $stc=$sc[$c['status']??'open']; $sev=$c['severity']??'minor';
    $sevc2=['minor'=>'var(--green)','moderate'=>'var(--warning)','serious'=>'var(--error)','critical'=>'#7c0000'][$sev]??'var(--ink)';
  ?>
  <div class="panel" style="padding:18px;border-left:4px solid <?=$sevc2?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px">
      <div>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px">
          <strong style="font-size:14px"><?=e($c['sname'])?></strong>
          <span style="font-size:11px;color:var(--ink-faint)"><?=e($c['sid'])?></span>
          <span style="font-size:11px;padding:2px 8px;border-radius:10px;background:<?=$stc?>;color:#fff;font-weight:600"><?=$scl[$c['status']??'open']?></span>
          <span style="font-size:11px;color:<?=$sevc2?>;font-weight:700"><?=ucfirst($sev)?></span>
        </div>
        <div style="font-size:12.5px;color:var(--ink-soft)">
          <?=e($c['cname']??'—')?> &middot; <?=e(ucwords($c['violation_type']??$c['category']??'Other'))?>
          &middot; <?=date('d M Y',strtotime($c['date_occurred']??$c['incident_date']??'now'))?>
          <?php if($c['note_count']>0):?> &middot; <span style="color:var(--primary)"><?=$c['note_count']?> note<?=$c['note_count']!=1?'s':''?></span><?php endif;?>
        </div>
        <div style="font-size:12.5px;color:var(--ink2);margin-top:5px"><?=e(mb_substr($c['description'],0,100)).(mb_strlen($c['description'])>100?'…':'')?></div>
      </div>
      <div style="display:flex;gap:8px;flex-shrink:0">
        <a href="?id=<?=$c['id']?>" class="button button-primary button-sm">🔍 Investigate</a>
        <a href="incidents.php?id=<?=$c['id']?>" class="button button-secondary button-sm">View</a>
      </div>
    </div>
  </div>
  <?php endforeach;?>
</div>
<?php endif;?>
<?php endif;?>

</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
