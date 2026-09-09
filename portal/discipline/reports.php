<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['discipline_officer','principal','vice_principal','sys_admin','school_admin']);
$activePage='reports'; $pdo=db(); $user=currentUser(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
$officer=null; try{$s=$pdo->prepare("SELECT * FROM staff WHERE user_id=? LIMIT 1");$s->execute([$user['id']]);$officer=$s->fetch()?:null;}catch(Throwable $e){}
if(!$officer) $officer=['first_name'=>$user['username']??'Officer','last_name'=>'','id'=>0];

$tab=$_GET['tab']??'summary';

// ── All KPIs ─────────────────────────────────────────────────
$stats=[];
foreach([
    'total'     =>"SELECT COUNT(*) FROM discipline_records WHERE academic_year_id=$ayId",
    'open'      =>"SELECT COUNT(*) FROM discipline_records WHERE academic_year_id=$ayId AND status='open'",
    'invest'    =>"SELECT COUNT(*) FROM discipline_records WHERE academic_year_id=$ayId AND status='investigating'",
    'pending'   =>"SELECT COUNT(*) FROM discipline_records WHERE academic_year_id=$ayId AND status='pending_decision'",
    'resolved'  =>"SELECT COUNT(*) FROM discipline_records WHERE academic_year_id=$ayId AND status='resolved'",
    'closed'    =>"SELECT COUNT(*) FROM discipline_records WHERE academic_year_id=$ayId AND status='closed'",
    'notified'  =>"SELECT COUNT(*) FROM discipline_records WHERE academic_year_id=$ayId AND parent_notified=1",
    'actions'   =>"SELECT COUNT(*) FROM discipline_actions WHERE academic_year_id=$ayId",
    'recs'      =>"SELECT COUNT(*) FROM discipline_recommendations WHERE academic_year_id=$ayId",
] as $k=>$q){ try{$stats[$k]=(int)$pdo->query($q)->fetchColumn();}catch(Throwable $e){$stats[$k]=0;} }

// ── By type ───────────────────────────────────────────────────
try{$byType=$pdo->query("SELECT COALESCE(violation_type,category,'Other') vt,COUNT(*) n,
    SUM(status='resolved') res,SUM(severity IN ('serious','critical')) serious
    FROM discipline_records WHERE academic_year_id=$ayId GROUP BY vt ORDER BY n DESC")->fetchAll();}catch(Throwable $e){$byType=[];}

// ── By class ─────────────────────────────────────────────────
try{$byClass=$pdo->query("SELECT c.name cn,COUNT(*) n,COUNT(DISTINCT dr.student_id) students
    FROM discipline_records dr JOIN students s ON s.id=dr.student_id JOIN classes c ON c.id=s.current_class_id
    WHERE dr.academic_year_id=$ayId GROUP BY c.id ORDER BY n DESC")->fetchAll();}catch(Throwable $e){$byClass=[];}

// ── Monthly trend ─────────────────────────────────────────────
try{$monthly=$pdo->query("SELECT DATE_FORMAT(COALESCE(date_occurred,incident_date),'%b %Y') mon,
    MONTH(COALESCE(date_occurred,incident_date)) mn,YEAR(COALESCE(date_occurred,incident_date)) yr,COUNT(*) n
    FROM discipline_records WHERE academic_year_id=$ayId AND (date_occurred IS NOT NULL OR incident_date IS NOT NULL)
    GROUP BY yr,mn ORDER BY yr,mn")->fetchAll();}catch(Throwable $e){$monthly=[];}

// ── Repeat offenders ─────────────────────────────────────────
try{$repeats=$pdo->query("SELECT s.student_id sid,CONCAT(s.first_name,' ',s.last_name) sname,
    c.name cname,COUNT(*) cnt,
    GROUP_CONCAT(DISTINCT COALESCE(dr.violation_type,dr.category,'Other') SEPARATOR ', ') types
    FROM discipline_records dr JOIN students s ON s.id=dr.student_id
    LEFT JOIN classes c ON c.id=s.current_class_id
    WHERE dr.academic_year_id=$ayId GROUP BY s.id HAVING cnt>1 ORDER BY cnt DESC LIMIT 20")->fetchAll();}catch(Throwable $e){$repeats=[];}

// ── By severity ───────────────────────────────────────────────
try{$bySev=$pdo->query("SELECT COALESCE(severity,'minor') sev,COUNT(*) n FROM discipline_records WHERE academic_year_id=$ayId GROUP BY sev ORDER BY FIELD(sev,'critical','serious','moderate','minor')")->fetchAll();}catch(Throwable $e){$bySev=[];}

// ── By action type ────────────────────────────────────────────
try{$byAction=$pdo->query("SELECT action_type,COUNT(*) n FROM discipline_actions WHERE academic_year_id=$ayId GROUP BY action_type ORDER BY n DESC")->fetchAll();}catch(Throwable $e){$byAction=[];}

$maxType=max(array_column($byType,'n')?:[1]);
$maxCls =max(array_column($byClass,'n')?:[1]);
$maxMon =max(array_column($monthly,'n')?:[1]);
$maxAct =max(array_column($byAction,'n')?:[1]);
$sevCol=['minor'=>'var(--green)','moderate'=>'var(--warning)','serious'=>'var(--error)','critical'=>'#7c0000'];
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Discipline Reports — KHS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">

<div class="page-heading">
  <div><h1>Discipline Reports</h1><p><?=e($ay)?></p></div>
  <a href="incidents.php" class="button button-secondary">← Cases</a>
</div>

<!-- KPIs -->
<div class="metric-grid" style="margin-bottom:16px">
  <div class="metric-card"><div class="metric-top"><span>Total Cases</span><div class="metric-icon">📋</div></div><strong><?=$stats['total']?></strong><small><i></i><?=e($ay)?></small></div>
  <div class="metric-card"><div class="metric-top"><span>Open / Active</span><div class="metric-icon">⚠️</div></div><strong style="color:<?=$stats['open']+$stats['invest']>0?'var(--error)':'inherit'?>"><?=$stats['open']+$stats['invest']?></strong></div>
  <div class="metric-card"><div class="metric-top"><span>Resolved</span><div class="metric-icon">✅</div></div><strong style="color:var(--green)"><?=$stats['resolved']?></strong><small><i></i><?=$stats['total']>0?round($stats['resolved']/$stats['total']*100).'%':'0%'?> rate</small></div>
  <div class="metric-card"><div class="metric-top"><span>Parents Notified</span><div class="metric-icon">📞</div></div><strong><?=$stats['notified']?></strong><small><i></i>of <?=$stats['total']?></small></div>
  <div class="metric-card"><div class="metric-top"><span>Repeat Offenders</span><div class="metric-icon">🔁</div></div><strong style="color:<?=count($repeats)>0?'var(--error)':'inherit'?>"><?=count($repeats)?></strong></div>
  <div class="metric-card"><div class="metric-top"><span>Actions Issued</span><div class="metric-icon">⚡</div></div><strong><?=$stats['actions']?></strong></div>
</div>

<!-- Tabs -->
<div class="tab-bar" style="margin-bottom:16px">
  <a href="?tab=summary"  class="tab-btn <?=$tab==='summary' ?'active':''?>">📊 Summary</a>
  <a href="?tab=by_type"  class="tab-btn <?=$tab==='by_type' ?'active':''?>">📋 By Type</a>
  <a href="?tab=by_class" class="tab-btn <?=$tab==='by_class'?'active':''?>">🏫 By Class</a>
  <a href="?tab=trend"    class="tab-btn <?=$tab==='trend'   ?'active':''?>">📈 Monthly Trend</a>
  <a href="?tab=repeats"  class="tab-btn <?=$tab==='repeats' ?'active':''?>">🔁 Repeat Offenders</a>
  <a href="?tab=actions"  class="tab-btn <?=$tab==='actions' ?'active':''?>">⚡ Actions Issued</a>
</div>

<?php if($tab==='summary'): ?>
<!-- Case status breakdown -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
  <div class="panel" style="padding:18px">
    <h3 style="font-weight:700;font-size:13px;margin-bottom:14px">📊 Case Status Breakdown</h3>
    <?php foreach(['open'=>['Open','var(--error)'],'investigating'=>['Investigating','var(--warning)'],'pending_decision'=>['Pending Decision','var(--blue)'],'resolved'=>['Resolved','var(--green)'],'closed'=>['Closed','var(--ink-soft)']] as $st=>[$lbl,$col]):
      $n=$stats[$st]??0; $w=$stats['total']>0?round($n/$stats['total']*100):0;?>
    <div style="margin-bottom:8px">
      <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:2px"><span><?=$lbl?></span><strong style="color:<?=$col?>"><?=$n?></strong></div>
      <div style="height:7px;background:var(--bg2);border-radius:4px"><div style="width:<?=$w?>%;height:100%;background:<?=$col?>;border-radius:4px"></div></div>
    </div>
    <?php endforeach;?>
  </div>
  <div class="panel" style="padding:18px">
    <h3 style="font-weight:700;font-size:13px;margin-bottom:14px">⚡ By Severity</h3>
    <?php if(empty($bySev)):?><p class="muted" style="font-size:13px">No data.</p><?php else:foreach($bySev as $sv):
      $col=$sevCol[$sv['sev']]??'var(--ink)'; $w=$stats['total']>0?round($sv['n']/$stats['total']*100):0;?>
    <div style="margin-bottom:8px">
      <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:2px"><span style="color:<?=$col?>;font-weight:600"><?=ucfirst($sv['sev'])?></span><strong><?=$sv['n']?></strong></div>
      <div style="height:7px;background:var(--bg2);border-radius:4px"><div style="width:<?=$w?>%;height:100%;background:<?=$col?>;border-radius:4px"></div></div>
    </div>
    <?php endforeach;endif;?>
  </div>
</div>
<!-- Top types and classes -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
  <div class="panel" style="padding:18px">
    <h3 style="font-weight:700;font-size:13px;margin-bottom:14px">📋 Top Violation Types</h3>
    <?php foreach(array_slice($byType,0,6) as $t):$w=round($t['n']/$maxType*100);?>
    <div style="margin-bottom:8px">
      <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:2px"><span><?=e(ucwords($t['vt']))?></span><strong><?=$t['n']?></strong></div>
      <div style="height:7px;background:var(--bg2);border-radius:4px"><div style="width:<?=$w?>%;height:100%;background:var(--warning);border-radius:4px"></div></div>
    </div>
    <?php endforeach;?>
  </div>
  <div class="panel" style="padding:18px">
    <h3 style="font-weight:700;font-size:13px;margin-bottom:14px">🏫 Top Classes</h3>
    <?php foreach(array_slice($byClass,0,6) as $c):$w=round($c['n']/$maxCls*100);?>
    <div style="margin-bottom:8px">
      <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:2px"><span><?=e($c['cn'])?></span><strong><?=$c['n']?></strong></div>
      <div style="height:7px;background:var(--bg2);border-radius:4px"><div style="width:<?=$w?>%;height:100%;background:var(--primary);border-radius:4px"></div></div>
    </div>
    <?php endforeach;?>
  </div>
</div>

<?php elseif($tab==='by_type'): ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Violation Type</th><th>Total</th><th>Resolved</th><th>Serious/Critical</th><th>Resolution %</th></tr></thead>
    <tbody>
      <?php foreach($byType as $t):?>
      <tr>
        <td><strong><?=e(ucwords($t['vt']))?></strong></td>
        <td><?=$t['n']?></td>
        <td style="color:var(--green)"><?=$t['res']?></td>
        <td style="color:<?=$t['serious']>0?'var(--error)':'var(--ink-faint)'?>"><?=$t['serious']?$t['serious']:'—'?></td>
        <td><?=$t['n']>0?round($t['res']/$t['n']*100).'%':'—'?></td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>

<?php elseif($tab==='by_class'): ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Class</th><th>Total Incidents</th><th>Unique Students</th><th>Incidents/Student</th></tr></thead>
    <tbody>
      <?php foreach($byClass as $c):?>
      <tr>
        <td><strong><?=e($c['cn'])?></strong></td>
        <td><strong style="color:<?=$c['n']>5?'var(--error)':($c['n']>2?'var(--warning)':'inherit')?>"><?=$c['n']?></strong></td>
        <td><?=$c['students']?></td>
        <td><?=$c['students']>0?round($c['n']/$c['students'],1):'—'?></td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>

<?php elseif($tab==='trend'): ?>
<?php if(empty($monthly)):?>
<p style="color:var(--ink-faint)">No monthly data available.</p>
<?php else:?>
<div class="panel" style="padding:20px;margin-bottom:16px">
  <h3 style="font-weight:700;font-size:13px;margin-bottom:14px">📈 Monthly Incident Trend</h3>
  <div style="display:flex;align-items:flex-end;gap:8px;height:100px;margin-bottom:10px">
    <?php foreach($monthly as $m): $h=max(6,round($m['n']/$maxMon*90));?>
    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px">
      <span style="font-size:9px;color:var(--ink-soft)"><?=$m['n']?></span>
      <div style="width:100%;height:<?=$h?>px;background:<?=$m['n']>5?'var(--error)':($m['n']>2?'var(--warning)':'var(--primary)')?>;border-radius:3px 3px 0 0;opacity:.85"></div>
      <span style="font-size:9px;color:var(--ink-soft)"><?=$m['mon']?></span>
    </div>
    <?php endforeach;?>
  </div>
</div>
<div class="table-wrap">
  <table>
    <thead><tr><th>Month</th><th>Incidents</th><th>Trend</th></tr></thead>
    <tbody>
      <?php $prev=null;foreach($monthly as $m): $diff=$prev!==null?$m['n']-$prev:null; $prev=$m['n'];?>
      <tr>
        <td><?=$m['mon']?></td>
        <td><strong><?=$m['n']?></strong></td>
        <td><?php if($diff!==null): $col=$diff>0?'var(--error)':($diff<0?'var(--green)':'var(--ink-soft)');?>
          <span style="color:<?=$col?>;font-weight:700"><?=$diff>0?'▲':($diff<0?'▼':'→')?> <?=abs($diff)?></span>
        <?php else:?><span class="muted">—</span><?php endif;?></td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>
<?php endif;?>

<?php elseif($tab==='repeats'): ?>
<?php if(empty($repeats)):?>
<div style="text-align:center;padding:40px"><div style="font-size:36px;margin-bottom:10px">✅</div><p style="color:var(--ink-soft)">No repeat offenders found this year.</p></div>
<?php else:?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Student</th><th>Class</th><th>Incidents</th><th>Risk</th><th>Violation Types</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach($repeats as $r): $risk=$r['cnt']>=5?'Critical':($r['cnt']>=3?'High':'Moderate'); $rc=['Critical'=>'#7c0000','High'=>'var(--error)','Moderate'=>'var(--warning)'][$risk];?>
      <tr>
        <td><strong><?=e($r['sname'])?></strong><div style="font-size:11px;color:var(--ink-faint)"><?=e($r['sid'])?></div></td>
        <td class="muted"><?=e($r['cname']??'—')?></td>
        <td><strong style="color:<?=$rc?>;font-size:16px"><?=$r['cnt']?></strong></td>
        <td><span style="font-size:11px;font-weight:800;color:<?=$rc?>"><?=$risk?></span></td>
        <td style="font-size:12px;color:var(--ink-soft)"><?=e(mb_substr($r['types'],0,60))?><?=mb_strlen($r['types'])>60?'…':''?></td>
        <td>
          <a href="students.php?id=<?=/* need student PK */'0'?>" style="font-size:12px;color:var(--primary)">History</a>
          &middot; <a href="recommendations.php?action=new" style="font-size:12px;color:var(--error)">Escalate</a>
        </td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>
<?php endif;?>

<?php elseif($tab==='actions'): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
  <div class="panel" style="padding:18px">
    <h3 style="font-weight:700;font-size:13px;margin-bottom:14px">⚡ Actions by Type</h3>
    <?php if(empty($byAction)):?><p class="muted" style="font-size:13px">No actions recorded.</p><?php else:foreach($byAction as $a):$w=round($a['n']/$maxAct*100);?>
    <div style="margin-bottom:8px">
      <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:2px"><span><?=e($a['action_type'])?></span><strong><?=$a['n']?></strong></div>
      <div style="height:7px;background:var(--bg2);border-radius:4px"><div style="width:<?=$w?>%;height:100%;background:var(--primary);border-radius:4px"></div></div>
    </div>
    <?php endforeach;endif;?>
  </div>
  <div class="panel" style="padding:18px">
    <h3 style="font-weight:700;font-size:13px;margin-bottom:14px">📨 Recommendations</h3>
    <div style="display:flex;flex-direction:column;gap:8px">
      <?php foreach(['pending'=>['Pending','var(--warning)'],'approved'=>['Approved','var(--green)'],'rejected'=>['Rejected','var(--error)'],'implemented'=>['Implemented','var(--blue)']] as $st=>[$lbl,$col]):
        try{$n=(int)$pdo->query("SELECT COUNT(*) FROM discipline_recommendations WHERE academic_year_id=$ayId AND status='$st'")->fetchColumn();}catch(Throwable $e){$n=0;}?>
      <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--line)"><span><?=$lbl?></span><strong style="color:<?=$col?>"><?=$n?></strong></div>
      <?php endforeach;?>
    </div>
  </div>
</div>
<?php endif;?>

</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
