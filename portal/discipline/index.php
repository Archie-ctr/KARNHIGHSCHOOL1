<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['discipline_officer','principal','vice_principal','sys_admin','school_admin']);
$activePage='dashboard'; $pdo=db(); $user=currentUser(); $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
$officer=null; try{$s=$pdo->prepare("SELECT * FROM staff WHERE user_id=? LIMIT 1");$s->execute([$user['id']]);$officer=$s->fetch()?:null;}catch(Throwable $e){}
if(!$officer) $officer=['first_name'=>$user['username']??'Discipline','last_name'=>'Officer','id'=>0];

// KPIs
$kpis=[];
foreach([
  'total'          =>"SELECT COUNT(*) FROM discipline_records WHERE academic_year_id=$ayId",
  'open'           =>"SELECT COUNT(*) FROM discipline_records WHERE academic_year_id=$ayId AND status='open'",
  'investigating'  =>"SELECT COUNT(*) FROM discipline_records WHERE academic_year_id=$ayId AND status='investigating'",
  'pending'        =>"SELECT COUNT(*) FROM discipline_records WHERE academic_year_id=$ayId AND status='pending_decision'",
  'resolved'       =>"SELECT COUNT(*) FROM discipline_records WHERE academic_year_id=$ayId AND status='resolved'",
  'week'           =>"SELECT COUNT(*) FROM discipline_records WHERE academic_year_id=$ayId AND date_occurred>=DATE_SUB(CURDATE(),INTERVAL 7 DAY)",
  'parent_pending' =>"SELECT COUNT(*) FROM discipline_records WHERE academic_year_id=$ayId AND (parent_notified IS NULL OR parent_notified=0) AND status NOT IN ('closed','resolved')",
  'repeat'         =>"SELECT COUNT(DISTINCT student_id) FROM (SELECT student_id FROM discipline_records WHERE academic_year_id=$ayId GROUP BY student_id HAVING COUNT(*)>1) t",
] as $k=>$q){ try{$kpis[$k]=(int)$pdo->query($q)->fetchColumn();}catch(Throwable $e){$kpis[$k]=0;} }

// Recent cases
try{$recent=$pdo->query("SELECT dr.*,CONCAT(s.first_name,' ',s.last_name) sname,s.student_id sid,c.name cname FROM discipline_records dr JOIN students s ON s.id=dr.student_id LEFT JOIN classes c ON c.id=s.current_class_id WHERE dr.academic_year_id=$ayId ORDER BY dr.date_occurred DESC,dr.id DESC LIMIT 8")->fetchAll();}catch(Throwable $e){$recent=[];}
// By type
try{$byType=$pdo->query("SELECT COALESCE(violation_type,category,'Other') vt,COUNT(*) n FROM discipline_records WHERE academic_year_id=$ayId GROUP BY vt ORDER BY n DESC LIMIT 6")->fetchAll();}catch(Throwable $e){$byType=[];}
// By class
try{$byClass=$pdo->query("SELECT c.name cn,COUNT(*) n FROM discipline_records dr JOIN students s ON s.id=dr.student_id JOIN classes c ON c.id=s.current_class_id WHERE dr.academic_year_id=$ayId GROUP BY c.id ORDER BY n DESC LIMIT 6")->fetchAll();}catch(Throwable $e){$byClass=[];}
$maxT=max(array_column($byType,'n')?:[1]); $maxC=max(array_column($byClass,'n')?:[1]);
$sc=['open'=>'var(--error)','investigating'=>'var(--warning)','pending_decision'=>'var(--blue)','resolved'=>'var(--green)','closed'=>'var(--ink-soft)'];
$hour=(int)date('G'); $greet=$hour<12?'Good morning':($hour<17?'Good afternoon':'Good evening');
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Discipline Dashboard — KHS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">

<div class="page-heading">
  <div><h1><?=$greet?>, <?=e($officer['first_name'])?>!</h1><p>Discipline Office &mdash; <?=e($ay)?></p></div>
  <a href="incidents.php?action=new" class="button button-primary">+ Report Incident</a>
</div>
<?php foreach(getFlash() as $f):?><div class="alert alert-<?=$f['type']?>"><?=e($f['message'])?></div><?php endforeach;?>

<?php if($kpis['parent_pending']>0):?>
<div class="alert alert-warning">📞 <strong><?=$kpis['parent_pending']?> incident<?=$kpis['parent_pending']!=1?'s':''?></strong> awaiting parent notification. <a href="notifications.php" style="font-weight:700">Notify now →</a></div>
<?php endif;?>

<!-- KPIs -->
<div class="metric-grid">
  <div class="metric-card"><div class="metric-top"><span>Total Cases</span><div class="metric-icon">📋</div></div><strong><?=$kpis['total']?></strong><small><i></i><?=e($ay)?></small></div>
  <div class="metric-card <?=$kpis['open']>0?'finance-metrics':''?>"><div class="metric-top"><span>Open</span><div class="metric-icon">⚠️</div></div><strong style="color:<?=$kpis['open']>0?'var(--error)':'inherit'?>"><?=$kpis['open']?></strong><small><i></i>Need action</small></div>
  <div class="metric-card <?=$kpis['investigating']>0?'finance-metrics':''?>"><div class="metric-top"><span>Investigating</span><div class="metric-icon">🔍</div></div><strong style="color:<?=$kpis['investigating']>0?'var(--warning)':'inherit'?>"><?=$kpis['investigating']?></strong><small><i></i>Active cases</small></div>
  <div class="metric-card <?=$kpis['pending']>0?'finance-metrics':''?>"><div class="metric-top"><span>Pending Decision</span><div class="metric-icon">⏳</div></div><strong style="color:<?=$kpis['pending']>0?'var(--blue)':'inherit'?>"><?=$kpis['pending']?></strong><small><i></i>VP/Principal review</small></div>
  <div class="metric-card"><div class="metric-top"><span>Resolved</span><div class="metric-icon">✅</div></div><strong style="color:var(--green)"><?=$kpis['resolved']?></strong><small><i></i><?=$kpis['total']>0?round($kpis['resolved']/$kpis['total']*100).'%':'0%'?> rate</small></div>
  <div class="metric-card <?=$kpis['repeat']>0?'finance-metrics':''?>"><div class="metric-top"><span>Repeat Offenders</span><div class="metric-icon">🔁</div></div><strong style="color:<?=$kpis['repeat']>0?'var(--error)':'inherit'?>"><?=$kpis['repeat']?></strong><small><i></i>This year</small></div>
</div>

<!-- Workflow strip -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
  <?php foreach([
    ['open','Open Cases',$kpis['open'],'var(--error)','incidents.php?status=open'],
    ['investigating','Investigating',$kpis['investigating'],'var(--warning)','investigations.php'],
    ['pending_decision','Pending Decision',$kpis['pending'],'var(--blue)','investigations.php?status=pending_decision'],
    ['resolved','Resolved',$kpis['resolved'],'var(--green)','incidents.php?status=resolved'],
  ] as [$st,$lbl,$cnt,$col,$url]):?>
  <a href="<?=$url?>" style="flex:1;min-width:120px;padding:12px 16px;background:var(--surface);border:1.5px solid var(--line);border-top:3px solid <?=$col?>;border-radius:var(--radius-sm);text-decoration:none;text-align:center">
    <div style="font-size:20px;font-weight:800;color:<?=$col?>"><?=$cnt?></div>
    <div style="font-size:11.5px;font-weight:700;color:var(--ink-soft);margin-top:2px"><?=$lbl?></div>
  </a>
  <?php endforeach;?>
</div>

<!-- Quick actions -->
<div class="panel" style="padding:18px;margin-bottom:16px">
  <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:10px">Quick Actions</div>
  <div class="quick-grid">
    <a href="incidents.php?action=new"    class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px"><span class="qi-icon">⚠️</span><div><strong>Report Incident</strong><small>Log new case</small></div></a>
    <a href="investigations.php"          class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px"><span class="qi-icon">🔍</span><div><strong>Investigations</strong><small><?=$kpis['open']+$kpis['investigating']?> active</small></div></a>
    <a href="warnings.php?action=new"     class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px"><span class="qi-icon">📋</span><div><strong>Issue Action</strong><small>Warning, detention…</small></div></a>
    <a href="notifications.php"           class="quick-item <?=$kpis['parent_pending']>0?'style="border-color:var(--warning)"':''?>" style="flex-direction:row;align-items:center;gap:12px;padding:14px<?=$kpis['parent_pending']>0?';border-color:var(--warning)':''?>"><span class="qi-icon">📞</span><div><strong>Parent Comms</strong><small><?=$kpis['parent_pending']?> pending</small></div></a>
    <a href="recommendations.php?action=new" class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px"><span class="qi-icon">📨</span><div><strong>Escalate Case</strong><small>To VP/Principal</small></div></a>
    <a href="reports.php"                 class="quick-item" style="flex-direction:row;align-items:center;gap:12px;padding:14px"><span class="qi-icon">📊</span><div><strong>Reports</strong><small>Statistics & trends</small></div></a>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
  <div class="panel" style="padding:18px">
    <h3 style="font-weight:700;font-size:13px;margin-bottom:14px">📊 By Violation Type</h3>
    <?php if(empty($byType)):?><p class="muted" style="font-size:13px">No data yet.</p><?php else:foreach($byType as $t):$w=round($t['n']/$maxT*100);?>
    <div style="margin-bottom:8px"><div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:2px"><span><?=e(ucwords($t['vt']))?></span><strong><?=$t['n']?></strong></div>
    <div style="height:7px;background:var(--bg2);border-radius:4px"><div style="width:<?=$w?>%;height:100%;background:var(--warning);border-radius:4px"></div></div></div>
    <?php endforeach;endif;?>
  </div>
  <div class="panel" style="padding:18px">
    <h3 style="font-weight:700;font-size:13px;margin-bottom:14px">🏫 By Class</h3>
    <?php if(empty($byClass)):?><p class="muted" style="font-size:13px">No data yet.</p><?php else:foreach($byClass as $c):$w=round($c['n']/$maxC*100);?>
    <div style="margin-bottom:8px"><div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:2px"><span><?=e($c['cn'])?></span><strong><?=$c['n']?></strong></div>
    <div style="height:7px;background:var(--bg2);border-radius:4px"><div style="width:<?=$w?>%;height:100%;background:var(--primary);border-radius:4px"></div></div></div>
    <?php endforeach;endif;?>
  </div>
</div>

<!-- Recent cases -->
<div class="panel" style="padding:18px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
    <h3 style="font-weight:700;font-size:13px">⏱ Recent Cases</h3>
    <a href="incidents.php" style="font-size:12px;color:var(--primary)">View all →</a>
  </div>
  <?php if(empty($recent)):?><p class="muted" style="font-size:13px">No incidents recorded yet.</p><?php else:?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date</th><th>Student</th><th>Class</th><th>Type</th><th>Severity</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach($recent as $r):
          $sev=$r['severity']??'minor'; $sevCol=['minor'=>'var(--green)','moderate'=>'var(--warning)','serious'=>'var(--error)','critical'=>'#7c0000'][$sev]??'var(--ink)';
          $stc=$sc[$r['status']??'open']??'var(--ink-soft)';
        ?>
        <tr>
          <td class="muted"><?=date('d M Y',strtotime($r['date_occurred']??$r['incident_date']??'now'))?></td>
          <td><strong><?=e($r['sname'])?></strong><div style="font-size:11px;color:var(--ink-faint)"><?=e($r['sid'])?></div></td>
          <td class="muted"><?=e($r['cname']??'—')?></td>
          <td><?=e(ucwords($r['violation_type']??$r['category']??'Other'))?></td>
          <td><span style="font-size:11px;font-weight:700;color:<?=$sevCol?>"><?=ucfirst($sev)?></span></td>
          <td><span style="font-size:11px;padding:2px 8px;border-radius:10px;background:<?=$stc?>;color:#fff;font-weight:600"><?=ucfirst(str_replace('_',' ',$r['status']??'open'))?></span></td>
          <td><a href="incidents.php?id=<?=$r['id']?>" style="font-size:12px;color:var(--primary)">View</a></td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
  </div>
  <?php endif;?>
</div>

</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
