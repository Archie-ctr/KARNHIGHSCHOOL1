<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('parent');
$activePage='alerts'; $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
include __DIR__.'/includes/resolve_child.php';
$pdo=db(); $cq=$selChild?'?child_id='.$selChild:'';

// Collect all alert types for current child
$alerts=[]; // [type, severity, title, message, link, date]

if($child){
    $cid=$child['id']; $gid=$child['current_grade_id']??0;
    $fname=e($child['first_name']);

    // ── Absence alerts ──────────────────────────────────────
    try{
        $absences=$pdo->query("SELECT date,remarks FROM attendance WHERE student_id=$cid AND status='Absent' AND academic_year_id=$ayId ORDER BY date DESC LIMIT 5")->fetchAll();
        foreach($absences as $a){
            $alerts[]=['absence','error','Absence Recorded',"$fname was marked absent on ".date('d M Y',strtotime($a['date'])).($a['remarks']?' ('.$a['remarks'].')':''),'child_attendance.php'.$cq,$a['date']];
        }
        // Consecutive absence check (2+ in a row)
        $consec=$pdo->query("SELECT COUNT(*) FROM attendance WHERE student_id=$cid AND status='Absent' AND date>=DATE_SUB(CURDATE(),INTERVAL 3 DAY) AND academic_year_id=$ayId")->fetchColumn();
        if($consec>=2) $alerts[]=['absence','critical','Multiple Absences',"$fname has been absent $consec days in the last 3 days. Please contact the school if there's an ongoing issue.",'child_attendance.php'.$cq,date('Y-m-d')];
    }catch(Throwable $e){}

    // ── Attendance rate warning ──────────────────────────────
    try{
        $at=$pdo->query("SELECT SUM(status='Present') p,COUNT(*) t FROM attendance WHERE student_id=$cid AND academic_year_id=$ayId")->fetch();
        $rate=$at['t']>0?round($at['p']/$at['t']*100,1):null;
        if($rate!==null&&$rate<75) $alerts[]=['attendance','warning','Low Attendance Rate',"$fname's attendance is $rate% — below the required 75%. This may affect eligibility for exams.",'child_attendance.php'.$cq,date('Y-m-d')];
    }catch(Throwable $e){}

    // ── Fee reminders ────────────────────────────────────────
    try{
        $paid=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE student_id=$cid AND currency='LRD' AND academic_year_id=$ayId")->fetchColumn();
        $due =(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM fee_structures WHERE academic_year_id=$ayId AND is_active=1 AND currency='LRD' AND (grade_id IS NULL OR grade_id=$gid)")->fetchColumn();
        $bal=max(0,$due-$paid);
        if($bal>0) $alerts[]=['fees','warning','Outstanding Fee Balance',"$fname has an outstanding fee balance of LRD ".number_format($bal).". Please make payment at the school accounts office.",'fees.php'.$cq,date('Y-m-d')];
    }catch(Throwable $e){}

    // ── New results published ────────────────────────────────
    try{
        $newRes=$pdo->query("SELECT COUNT(*) FROM assessment_scores WHERE student_id=$cid AND status='published' AND academic_year_id=$ayId AND created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)")->fetchColumn();
        if($newRes>0) $alerts[]=['results','info','New Results Available',"$newRes new result".($newRes!=1?'s':''). " have been published for $fname in the last 7 days.",'child_results.php'.$cq,date('Y-m-d')];
    }catch(Throwable $e){}

    // ── Overdue assignments ──────────────────────────────────
    try{
        $overdueAsm=$pdo->query("SELECT COUNT(*) FROM teacher_assessments ta WHERE ta.class_id={$child['current_class_id']} AND ta.academic_year_id=$ayId AND ta.due_date<CURDATE() AND ta.id NOT IN (SELECT assessment_id FROM assessment_submissions WHERE student_id=$cid)")->fetchColumn();
        if($overdueAsm>0) $alerts[]=['assignment','warning','Overdue Assignments',"$fname has $overdueAsm overdue assignment".($overdueAsm!=1?'s':'').". Please follow up with their teacher(s).",'assignments.php'.$cq,date('Y-m-d')];
    }catch(Throwable $e){}

    // ── Upcoming assignments (due in 3 days) ─────────────────
    try{
        $dueAsm=$pdo->query("SELECT title,due_date,sub.name sname FROM teacher_assessments ta JOIN subjects sub ON sub.id=ta.subject_id WHERE ta.class_id={$child['current_class_id']} AND ta.academic_year_id=$ayId AND ta.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(),INTERVAL 3 DAY) LIMIT 5")->fetchAll();
        foreach($dueAsm as $da) $alerts[]=['assignment','info','Assignment Due Soon',$da['title'].' ('.$da['sname'].') due '.date('d M',strtotime($da['due_date'])).'.',BASE_URL.'/portal/parent/assignments.php'.$cq,$da['due_date']];
    }catch(Throwable $e){}

    // ── Discipline notifications ──────────────────────────────
    try{
        $unackDisc=$pdo->query("SELECT dr.violation_type,dr.category,dr.date_occurred,dr.parent_notified FROM discipline_records dr WHERE dr.student_id=$cid AND dr.academic_year_id=$ayId AND dr.parent_notified=1 AND dr.status NOT IN ('closed','resolved')")->fetchAll();
        foreach($unackDisc as $d) $alerts[]=['discipline','error','Discipline Notice',"A ".ucwords($d['violation_type']??$d['category']??'discipline')." incident was reported for $fname on ".date('d M',strtotime($d['date_occurred']??'now')).'. The school has notified you.','discipline.php'.$cq,$d['date_occurred']??date('Y-m-d')];
    }catch(Throwable $e){}

    // ── School announcements (last 3) ────────────────────────
    try{
        $schoolAnns=$pdo->query("SELECT title,message,published_at FROM announcements WHERE target IN ('all','parents') AND (expires_at IS NULL OR expires_at>NOW()) ORDER BY published_at DESC LIMIT 3")->fetchAll();
        foreach($schoolAnns as $an) $alerts[]=['announcement','info',$an['title'],mb_substr($an['message'],0,120).(mb_strlen($an['message'])>120?'…':''),'announcements.php',$an['published_at']];
    }catch(Throwable $e){}
}

// Sort alerts by severity then date
$sevOrder=['critical'=>0,'error'=>1,'warning'=>2,'info'=>3];
usort($alerts,function($a,$b)use($sevOrder){
    $sa=$sevOrder[$a[1]]??4; $sb=$sevOrder[$b[1]]??4;
    if($sa!==$sb) return $sa-$sb;
    return strtotime($b[5])-strtotime($a[5]);
});

$typeColors=['absence'=>'var(--error)','attendance'=>'var(--warning)','fees'=>'var(--warning)','results'=>'var(--green)','assignment'=>'var(--blue)','discipline'=>'#7c0000','announcement'=>'var(--primary)'];
$typeIcons=['absence'=>'📆','attendance'=>'📊','fees'=>'💰','results'=>'📊','assignment'=>'📝','discipline'=>'⚠️','announcement'=>'📢'];
$sevColors=['critical'=>'var(--error)','error'=>'var(--error)','warning'=>'var(--warning)','info'=>'var(--blue)'];
$sevBg=['critical'=>'rgba(168,40,40,.08)','error'=>'rgba(239,68,68,.05)','warning'=>'rgba(184,134,11,.06)','info'=>'rgba(44,95,138,.06)'];
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Alerts — Parent Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">
<div class="page-heading">
  <div>
    <h1>Alerts &amp; Notifications</h1>
    <p><?=$child?e($child['first_name'])."'s alerts":'All alerts'?> &mdash; <?=e($ay)?></p>
  </div>
  <a href="index.php<?=$cq?>" class="button button-secondary">← Dashboard</a>
</div>
<?php if(!$child):?><div class="alert alert-warning">Select a child to view their alerts.</div>
<?php else:?>

<!-- Alert type counts -->
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
  <?php
  $typeCounts=[];
  foreach($alerts as $a) $typeCounts[$a[1]]=($typeCounts[$a[1]]??0)+1;
  $urgentCount=($typeCounts['critical']??0)+($typeCounts['error']??0);
  $warnCount=$typeCounts['warning']??0;
  $infoCount=$typeCounts['info']??0;
  ?>
  <?php if($urgentCount>0):?><div style="padding:8px 16px;background:var(--error-soft);border:1.5px solid var(--error);border-radius:var(--radius-sm);font-weight:700;color:var(--error);font-size:13px">🚨 <?=$urgentCount?> Urgent</div><?php endif;?>
  <?php if($warnCount>0):?><div style="padding:8px 16px;background:var(--warning-soft);border:1.5px solid var(--warning);border-radius:var(--radius-sm);font-weight:700;color:var(--warning);font-size:13px">⚠️ <?=$warnCount?> Warning<?=$warnCount!=1?'s':''?></div><?php endif;?>
  <?php if($infoCount>0):?><div style="padding:8px 16px;background:var(--blue-soft);border:1.5px solid var(--blue);border-radius:var(--radius-sm);font-weight:700;color:var(--blue);font-size:13px">ℹ️ <?=$infoCount?> Notice<?=$infoCount!=1?'s':''?></div><?php endif;?>
  <?php if(empty($alerts)):?><div style="padding:8px 16px;background:var(--green-soft);border:1.5px solid var(--green);border-radius:var(--radius-sm);font-weight:700;color:var(--green);font-size:13px">✅ No alerts — all clear!</div><?php endif;?>
</div>

<?php if(empty($alerts)):?>
<div style="text-align:center;padding:64px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:48px;margin-bottom:16px">🌟</div>
  <h3 style="font-weight:800;margin-bottom:6px">All clear!</h3>
  <p style="color:var(--ink-soft)">No alerts for <?=e($child['first_name'])?> right now. Keep up the great work!</p>
</div>
<?php else:?>
<div style="display:flex;flex-direction:column;gap:10px">
<?php foreach($alerts as [$type,$sev,$title,$message,$link,$date]):
  $tCol=$typeColors[$type]??'var(--primary)';
  $tIcon=$typeIcons[$type]??'🔔';
  $sCol=$sevColors[$sev]??'var(--ink)';
  $sBg=$sevBg[$sev]??'var(--bg2)';
?>
<div style="padding:16px 18px;background:<?=$sBg?>;border:1px solid <?=$sCol?>33;border-left:4px solid <?=$sCol?>;border-radius:var(--radius-sm)">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px">
    <div style="display:flex;align-items:flex-start;gap:12px">
      <span style="font-size:20px;flex-shrink:0"><?=$tIcon?></span>
      <div>
        <div style="font-weight:700;font-size:14px;margin-bottom:3px;color:<?=$sCol?>"><?=$title?></div>
        <div style="font-size:13.5px;color:var(--ink2);line-height:1.5"><?=$message?></div>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:10px;flex-shrink:0">
      <span style="font-size:11.5px;color:var(--ink-soft)"><?=date('d M Y',strtotime($date))?></span>
      <?php if($link):?>
      <a href="<?=e($link)?>" class="button button-secondary button-sm" style="white-space:nowrap">View →</a>
      <?php endif;?>
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
