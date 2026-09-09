<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('parent');
$activePage='dashboard'; $ayId=currentAcademicYearId(); $ay=currentAcademicYearName(); $user=currentUser();

include __DIR__.'/includes/resolve_child.php';

// ── Per-child metrics ────────────────────────────────────────
$attPct=null; $attStats=['p'=>0,'a'=>0,'l'=>0,'t'=>0]; $avg=null;
$paid=0; $due=0; $balance=0; $borrowedCount=0; $overdueBooks=0;
$anns=[]; $recentAtt=[]; $recentResults=[];
$upcomingAsm=0; $unreadAlerts=0; $openDisc=0; $availQuizzes=0;

$pdo=db();

if($child){
    $cid=$child['id']; $gid=$child['current_grade_id']??0;

    // Attendance
    try{
        $s=$pdo->prepare("SELECT SUM(status='Present') p,SUM(status='Absent') a,SUM(status='Late') l,COUNT(*) t FROM attendance WHERE student_id=? AND academic_year_id=?");
        $s->execute([$cid,$ayId]); $attStats=$s->fetch();
        $attPct=$attStats['t']>0?round($attStats['p']/$attStats['t']*100,1):null;
    }catch(Throwable $e){}

    // Academic average
    try{
        $avg=$pdo->query("SELECT ROUND(AVG(marks_obtained/max_marks*100),1) FROM assessment_scores WHERE student_id=$cid AND academic_year_id=$ayId AND status IN ('approved','published') AND max_marks>0")->fetchColumn();
    }catch(Throwable $e){}

    // Fees
    try{
        $paid=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE student_id=$cid AND currency='LRD' AND academic_year_id=$ayId")->fetchColumn();
        $due =(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM fee_structures WHERE academic_year_id=$ayId AND is_active=1 AND currency='LRD' AND (grade_id IS NULL OR grade_id=$gid)")->fetchColumn();
        $balance=max(0,$due-$paid);
    }catch(Throwable $e){}

    // Library
    try{
        $borrowedCount=(int)$pdo->query("SELECT COUNT(*) FROM library_transactions WHERE student_id=$cid AND status='Issued'")->fetchColumn();
        $overdueBooks=(int)$pdo->query("SELECT COUNT(*) FROM library_transactions WHERE student_id=$cid AND status='Issued' AND due_date<CURDATE()")->fetchColumn();
    }catch(Throwable $e){}

    // Upcoming assessments
    try{$upcomingAsm=(int)$pdo->query("SELECT COUNT(*) FROM teacher_assessments WHERE class_id={$child['current_class_id']} AND academic_year_id=$ayId AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 7 DAY)")->fetchColumn();}catch(Throwable $e){}

    // Open discipline
    try{$openDisc=(int)$pdo->query("SELECT COUNT(*) FROM discipline_records WHERE student_id=$cid AND academic_year_id=$ayId AND status NOT IN ('resolved','closed')")->fetchColumn();}catch(Throwable $e){}

    // Available quizzes
    try{$availQuizzes=(int)$pdo->query("SELECT COUNT(*) FROM teacher_quizzes WHERE class_id={$child['current_class_id']} AND academic_year_id=$ayId AND is_published=1 AND (end_date IS NULL OR end_date>=NOW())")->fetchColumn();}catch(Throwable $e){}

    // Recent attendance (last 10)
    try{$recentAtt=$pdo->query("SELECT date,status,remarks FROM attendance WHERE student_id=$cid AND academic_year_id=$ayId ORDER BY date DESC LIMIT 10")->fetchAll();}catch(Throwable $e){}

    // Recent results
    try{
        $recentResults=$pdo->query(
            "SELECT sub.name sname,ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1) avg_pct
             FROM assessment_scores asc2 JOIN subjects sub ON sub.id=asc2.subject_id
             WHERE asc2.student_id=$cid AND asc2.academic_year_id=$ayId AND asc2.status IN ('approved','published') AND asc2.max_marks>0
             GROUP BY sub.id,sub.name ORDER BY avg_pct ASC LIMIT 6"
        )->fetchAll();
    }catch(Throwable $e){}
}

// Announcements
try{$anns=$pdo->query("SELECT title,message,published_at FROM announcements WHERE target IN ('all','parents') AND (expires_at IS NULL OR expires_at>NOW()) ORDER BY published_at DESC LIMIT 5")->fetchAll();}catch(Throwable $e){}

$hour=(int)date('G'); $greet=$hour<12?'Good morning':($hour<17?'Good afternoon':'Good evening');
$parentName=$user['username']??'Parent';
$cq=$selChild?'?child_id='.$selChild:'';

function pColor(float $v):string{return $v>=70?'var(--green)':($v>=50?'var(--warning)':'var(--error)');}
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Parent Portal — KHS</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">

<div class="page-heading">
  <div>
    <p style="font-size:13px;color:var(--ink-soft);margin-bottom:2px"><?=date('l, F j, Y')?></p>
    <h1><?=$greet?>, <?=e($parentName)?>! 👋</h1>
    <p>Parent Portal &mdash; <?=e($ay)?></p>
  </div>
</div>

<?php if(empty($children)):?>
<div class="alert alert-warning">No children linked to your account. Contact the school registrar to link your children.</div>
<?php else:?>

<!-- Alert banners -->
<?php if($openDisc>0):?>
<div class="alert alert-warning" style="margin-bottom:10px">⚠️ <strong><?=$openDisc?> open discipline case<?=$openDisc!=1?'s':''?></strong> for <?=e($child['first_name']??'your child')?>. <a href="discipline.php<?=$cq?>" style="font-weight:700">View details →</a></div>
<?php endif;?>
<?php if($overdueBooks>0):?>
<div class="alert alert-warning" style="margin-bottom:10px">📚 <?=e($child['first_name']??'Your child')?> has <strong><?=$overdueBooks?> overdue library book<?=$overdueBooks!=1?'s':''?></strong>. Please remind them to return.</div>
<?php endif;?>
<?php if($balance>100):?>
<div class="alert alert-info" style="margin-bottom:10px">💰 Outstanding fee balance: <strong>LRD <?=number_format($balance)?></strong>. <a href="fees.php<?=$cq?>" style="font-weight:700">View details →</a></div>
<?php endif;?>
<?php if($attPct!==null&&$attPct<75):?>
<div class="alert alert-warning" style="margin-bottom:10px">📆 <?=e($child['first_name']??'Your child')?>'s attendance is <strong><?=$attPct?>%</strong> — below the 75% minimum. <a href="child_attendance.php<?=$cq?>" style="font-weight:700">View →</a></div>
<?php endif;?>

<!-- Child info strip -->
<?php if($child):
  $ini=strtoupper(substr($child['first_name'],0,1).substr($child['last_name'],0,1));
?>
<div class="panel" style="display:flex;align-items:center;gap:16px;padding:18px 20px;margin-bottom:16px;flex-wrap:wrap">
  <div class="avatar" style="width:52px;height:52px;font-size:18px;font-weight:800;flex-shrink:0"><?=e($ini)?></div>
  <div style="flex:1;min-width:0">
    <h2 style="font-size:17px;font-weight:800;margin-bottom:2px"><?=e($child['first_name'].' '.$child['last_name'])?></h2>
    <p style="font-size:12.5px;color:var(--ink-soft)">
      <?=e($child['grade_name']??'—')?> <?=$child['class_name']?' / '.e($child['class_name']):''?>
      &middot; ID: <strong><?=e($child['student_id']??'—')?></strong>
    </p>
  </div>
  <?php if(count($children)>1):?>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <?php foreach($children as $ch):$s=$ch['id']==$selChild;?>
    <a href="?child_id=<?=$ch['id']?>" style="padding:6px 12px;border-radius:20px;font-size:12px;font-weight:700;text-decoration:none;border:1.5px solid <?=$s?'var(--primary)':'var(--line)'?>;background:<?=$s?'var(--primary)':'#fff'?>;color:<?=$s?'#fff':'var(--ink-soft)'?>"><?=e($ch['first_name'])?></a>
    <?php endforeach;?>
  </div>
  <?php endif;?>
</div>
<?php endif;?>

<!-- KPIs -->
<div class="metric-grid" style="margin-bottom:16px">
  <div class="metric-card <?=$attPct!==null&&$attPct<75?'finance-metrics':''?>">
    <div class="metric-top"><span>Attendance</span><div class="metric-icon">📆</div></div>
    <strong style="color:<?=$attPct!==null?($attPct>=80?'var(--green)':($attPct>=70?'var(--warning)':'var(--error)')):'inherit'?>"><?=$attPct!==null?$attPct.'%':'—'?></strong>
    <small><i></i><?=$attStats['a']??0?> absent, <?=$attStats['l']??0?> late</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Academic Average</span><div class="metric-icon">📊</div></div>
    <strong style="color:<?=$avg?pColor((float)$avg):'inherit'?>"><?=$avg?$avg.'%':'—'?></strong>
    <small><i></i><?=e($ay)?></small>
  </div>
  <div class="metric-card <?=$balance>0?'finance-metrics':''?>">
    <div class="metric-top"><span>Fee Balance</span><div class="metric-icon">💰</div></div>
    <strong style="color:<?=$balance>0?'var(--error)':'var(--green)'?>">LRD <?=number_format($balance)?></strong>
    <small><i></i><?=$balance<=0?'Fully paid':'Outstanding'?></small>
  </div>
  <div class="metric-card <?=$openDisc>0?'finance-metrics':''?>">
    <div class="metric-top"><span>Discipline</span><div class="metric-icon">⚠️</div></div>
    <strong style="color:<?=$openDisc>0?'var(--error)':'var(--green)'?>"><?=$openDisc?></strong>
    <small><i></i>Open case<?=$openDisc!=1?'s':''?></small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Assessments Due</span><div class="metric-icon">📝</div></div>
    <strong style="color:<?=$upcomingAsm>0?'var(--warning)':'inherit'?>"><?=$upcomingAsm?></strong>
    <small><i></i>This week</small>
  </div>
  <div class="metric-card <?=$overdueBooks>0?'finance-metrics':''?>">
    <div class="metric-top"><span>Library</span><div class="metric-icon">📖</div></div>
    <strong style="color:<?=$overdueBooks>0?'var(--error)':'inherit'?>"><?=$borrowedCount?></strong>
    <small><i></i><?=$overdueBooks>0?$overdueBooks.' overdue':'On loan'?></small>
  </div>
</div>

<!-- Quick actions -->
<div class="panel" style="padding:18px;margin-bottom:16px">
  <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-soft);margin-bottom:10px">Quick Access</div>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:10px">
    <a href="child_attendance.php<?=$cq?>"  class="quick-item" style="flex-direction:row;align-items:center;gap:11px;padding:13px"><span class="qi-icon">📆</span><div><strong>Attendance</strong><small><?=$attPct!==null?$attPct.'% rate':'Daily records'?></small></div></a>
    <a href="child_results.php<?=$cq?>"     class="quick-item" style="flex-direction:row;align-items:center;gap:11px;padding:13px"><span class="qi-icon">📊</span><div><strong>Results</strong><small>Grades &amp; marks</small></div></a>
    <a href="assignments.php<?=$cq?>"       class="quick-item <?=$upcomingAsm>0?'style=&quot;border-color:var(--warning)&quot;':''?>" style="flex-direction:row;align-items:center;gap:11px;padding:13px<?=$upcomingAsm>0?';border-color:var(--warning)':''?>"><span class="qi-icon">📝</span><div><strong>Assignments</strong><small><?=$upcomingAsm?> due this week</small></div></a>
    <a href="quizzes.php<?=$cq?>"           class="quick-item" style="flex-direction:row;align-items:center;gap:11px;padding:13px"><span class="qi-icon">🧠</span><div><strong>Quizzes</strong><small><?=$availQuizzes?> available</small></div></a>
    <a href="fees.php<?=$cq?>"             class="quick-item <?=$balance>0?'style=&quot;border-color:var(--error)&quot;':''?>" style="flex-direction:row;align-items:center;gap:11px;padding:13px<?=$balance>0?';border-color:var(--error)':''?>"><span class="qi-icon">💰</span><div><strong>Fees</strong><small><?=$balance>0?'LRD '.number_format($balance).' due':'Paid'?></small></div></a>
    <a href="discipline.php<?=$cq?>"        class="quick-item <?=$openDisc>0?'style=&quot;border-color:var(--error)&quot;':''?>" style="flex-direction:row;align-items:center;gap:11px;padding:13px<?=$openDisc>0?';border-color:var(--error)':''?>"><span class="qi-icon">⚠️</span><div><strong>Discipline</strong><small><?=$openDisc?> open</small></div></a>
    <a href="timetable.php<?=$cq?>"         class="quick-item" style="flex-direction:row;align-items:center;gap:11px;padding:13px"><span class="qi-icon">📅</span><div><strong>Timetable</strong><small>Weekly schedule</small></div></a>
    <a href="report_card.php<?=$cq?>"       class="quick-item" style="flex-direction:row;align-items:center;gap:11px;padding:13px"><span class="qi-icon">📑</span><div><strong>Report Card</strong><small>Download</small></div></a>
    <a href="alerts.php<?=$cq?>"            class="quick-item" style="flex-direction:row;align-items:center;gap:11px;padding:13px"><span class="qi-icon">🔔</span><div><strong>Alerts</strong><small>Notifications</small></div></a>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
  <!-- Subject performance -->
  <?php if(!empty($recentResults)):?>
  <div class="panel" style="padding:18px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <h3 style="font-weight:700;font-size:13px">📊 Subject Performance</h3>
      <a href="child_results.php<?=$cq?>" style="font-size:12px;color:var(--primary)">All →</a>
    </div>
    <?php $maxPct=max(array_column($recentResults,'avg_pct')?:[1]); foreach($recentResults as $r): $w=round($r['avg_pct']/$maxPct*100); $col=pColor($r['avg_pct']);?>
    <div style="margin-bottom:8px">
      <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:2px">
        <span><?=e($r['sname'])?></span>
        <strong style="color:<?=$col?>"><?=$r['avg_pct']?>%</strong>
      </div>
      <div style="height:6px;background:var(--bg2);border-radius:4px"><div style="width:<?=$w?>%;height:100%;background:<?=$col?>;border-radius:4px"></div></div>
    </div>
    <?php endforeach;?>
  </div>
  <?php endif;?>

  <!-- Recent attendance -->
  <?php if(!empty($recentAtt)):?>
  <div class="panel" style="padding:18px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <h3 style="font-weight:700;font-size:13px">📆 Recent Attendance</h3>
      <a href="child_attendance.php<?=$cq?>" style="font-size:12px;color:var(--primary)">All →</a>
    </div>
    <?php foreach($recentAtt as $a):
      $col=['Present'=>'var(--green)','Absent'=>'var(--error)','Late'=>'var(--warning)','Excused'=>'var(--blue)'][$a['status']]??'var(--ink-soft)';
    ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--line)">
      <span style="font-size:12.5px"><?=date('D, d M',strtotime($a['date']))?></span>
      <span style="font-size:12px;font-weight:700;color:<?=$col?>"><?=$a['status']?></span>
    </div>
    <?php endforeach;?>
  </div>
  <?php endif;?>
</div>

<!-- Announcements -->
<?php if(!empty($anns)):?>
<div class="panel activity-panel">
  <div class="panel-heading">
    <div><h3>📢 School Announcements</h3></div>
    <a href="announcements.php" class="filter-button">All →</a>
  </div>
  <?php foreach($anns as $ann):?>
  <div class="activity">
    <span class="activity-dot pink"></span>
    <div>
      <strong><?=e($ann['title'])?></strong>
      <p><?=e(mb_substr($ann['message'],0,100)).(mb_strlen($ann['message'])>100?'…':'')?></p>
      <small><?=date('M d, Y',strtotime($ann['published_at']))?></small>
    </div>
  </div>
  <?php endforeach;?>
</div>
<?php endif;?>

<?php endif;// end children check ?>

</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
