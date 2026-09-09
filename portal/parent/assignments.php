<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('parent');
$activePage='assignments'; $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
include __DIR__.'/includes/resolve_child.php';
$pdo=db(); $cq=$selChild?'?child_id='.$selChild:'';

$tab=$_GET['tab']??'all'; $now=date('Y-m-d');
$assessments=[]; $submissions=[];

if($child){
    $cid=$child['id']; $classId=$child['current_class_id'];
    try{
        $assessments=$pdo->query(
            "SELECT ta.*,sub.name subject_name,
                    (SELECT score FROM assessment_submissions WHERE assessment_id=ta.id AND student_id=$cid LIMIT 1) my_score,
                    (SELECT feedback FROM assessment_submissions WHERE assessment_id=ta.id AND student_id=$cid LIMIT 1) feedback,
                    (SELECT graded_at FROM assessment_submissions WHERE assessment_id=ta.id AND student_id=$cid LIMIT 1) graded_at
             FROM teacher_assessments ta
             JOIN subjects sub ON sub.id=ta.subject_id
             WHERE ta.class_id=$classId AND ta.academic_year_id=$ayId
             ORDER BY ta.due_date ASC,FIELD(ta.assessment_type,'test','homework','classwork','quiz','project','other')"
        )->fetchAll();
    }catch(Throwable $e){}
    try{
        $submissions=$pdo->query(
            "SELECT asub.*,ta.title,ta.assessment_type,ta.max_score,ta.due_date,sub.name subject_name
             FROM assessment_submissions asub
             JOIN teacher_assessments ta ON ta.id=asub.assessment_id
             JOIN subjects sub ON sub.id=ta.subject_id
             WHERE asub.student_id=$cid AND ta.academic_year_id=$ayId
             ORDER BY asub.graded_at DESC LIMIT 40"
        )->fetchAll();
    }catch(Throwable $e){}
}

$overdue=array_filter($assessments,fn($a)=>$a['due_date']&&$a['due_date']<$now&&$a['my_score']===null);
$upcoming=array_filter($assessments,fn($a)=>$a['due_date']&&$a['due_date']>=$now&&$a['due_date']<=date('Y-m-d',strtotime('+7 days'))&&$a['my_score']===null);
$graded=array_filter($assessments,fn($a)=>$a['my_score']!==null);
$typeColors=['classwork'=>'var(--blue)','homework'=>'var(--gold)','test'=>'var(--error)','quiz'=>'var(--warning)','project'=>'var(--green)','other'=>'var(--ink-soft)'];
$typeIcons =['classwork'=>'📘','homework'=>'📓','test'=>'📋','quiz'=>'🧠','project'=>'🗂️','other'=>'📄'];
$filtered=$tab==='overdue'?$overdue:($tab==='upcoming'?$upcoming:($tab==='graded'?$graded:$assessments));
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Assignments — Parent Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">
<div class="page-heading">
  <div><h1><?=$child?e($child['first_name'])."'s":'Child'?> Assignments</h1>
  <p><?=$child?e($child['grade_name']??'').' &mdash; ':''?><?=e($ay)?></p></div>
  <a href="index.php<?=$cq?>" class="button button-secondary">← Dashboard</a>
</div>
<?php if(!$child):?><div class="alert alert-warning">Select a child to view their assignments.</div>
<?php else:?>
<div class="metric-grid" style="margin-bottom:16px">
  <div class="metric-card"><div class="metric-top"><span>Total</span><div class="metric-icon">📝</div></div><strong><?=count($assessments)?></strong></div>
  <div class="metric-card <?=count($overdue)>0?'finance-metrics':''?>"><div class="metric-top"><span>Overdue</span><div class="metric-icon">⚠️</div></div><strong style="color:<?=count($overdue)>0?'var(--error)':'var(--green)'?>"><?=count($overdue)?></strong></div>
  <div class="metric-card"><div class="metric-top"><span>Due This Week</span><div class="metric-icon">📅</div></div><strong style="color:<?=count($upcoming)>0?'var(--warning)':'inherit'?>"><?=count($upcoming)?></strong></div>
  <div class="metric-card"><div class="metric-top"><span>Graded</span><div class="metric-icon">✅</div></div><strong><?=count($graded)?></strong></div>
</div>
<div class="tab-bar" style="margin-bottom:16px">
  <a href="?tab=all<?=$selChild?'&child_id='.$selChild:''?>"      class="tab-btn <?=$tab==='all'     ?'active':''?>">All (<?=count($assessments)?>)</a>
  <a href="?tab=overdue<?=$selChild?'&child_id='.$selChild:''?>"  class="tab-btn <?=$tab==='overdue' ?'active':''?>">⚠️ Overdue (<?=count($overdue)?>)</a>
  <a href="?tab=upcoming<?=$selChild?'&child_id='.$selChild:''?>" class="tab-btn <?=$tab==='upcoming'?'active':''?>">📅 Due Soon (<?=count($upcoming)?>)</a>
  <a href="?tab=graded<?=$selChild?'&child_id='.$selChild:''?>"   class="tab-btn <?=$tab==='graded'  ?'active':''?>">✅ Graded (<?=count($graded)?>)</a>
</div>
<?php if(empty($filtered)):?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:40px;margin-bottom:12px">📝</div>
  <h3 style="margin-bottom:6px">No <?=$tab==='all'?'':$tab?> assignments</h3>
  <p style="color:var(--ink-soft)">Nothing to show for this filter.</p>
</div>
<?php else:?>
<div style="display:flex;flex-direction:column;gap:10px">
<?php foreach($filtered as $a):
  $isOv=$a['due_date']&&$a['due_date']<$now&&$a['my_score']===null;
  $isDue=$a['due_date']&&$a['due_date']>=$now&&$a['due_date']<=date('Y-m-d',strtotime('+3 days'));
  $col=$typeColors[$a['assessment_type']??'other']??'var(--ink-soft)';
  $icon=$typeIcons[$a['assessment_type']??'other']??'📄';
  $pct=$a['my_score']!==null&&$a['max_score']>0?round($a['my_score']/$a['max_score']*100,1):null;
?>
<div class="panel" style="padding:18px;border-left:4px solid <?=$isOv?'var(--error)':($isDue?'var(--warning)':$col)?>">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px">
    <div>
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px">
        <span style="font-size:16px"><?=$icon?></span>
        <strong style="font-size:14px"><?=e($a['title'])?></strong>
        <span style="font-size:11px;padding:2px 7px;border-radius:8px;background:<?=$col?>22;color:<?=$col?>;font-weight:700"><?=ucfirst($a['assessment_type']??'other')?></span>
        <?php if($isOv):?><span class="status warning" style="font-size:11px">Overdue</span><?php elseif($isDue):?><span class="status new-s" style="font-size:11px">Due Soon</span><?php endif;?>
      </div>
      <div style="font-size:12.5px;color:var(--ink-soft)"><?=e($a['subject_name'])?>
        <?=$a['due_date']?' &middot; Due: <strong style="color:'.($isOv?'var(--error)':'inherit').'">'.date('d M Y',strtotime($a['due_date'])).'</strong>':''?>
      </div>
      <?php if($a['description']):?><div style="font-size:12.5px;color:var(--ink2);margin-top:5px"><?=e(mb_substr($a['description'],0,120))?><?=mb_strlen($a['description'])>120?'…':''?></div><?php endif;?>
      <?php if($a['feedback']):?>
      <div style="margin-top:8px;background:var(--green-soft);border-left:3px solid var(--green);padding:8px 12px;border-radius:var(--radius-sm)">
        <div style="font-size:11px;font-weight:700;color:var(--green);margin-bottom:3px">Teacher Feedback</div>
        <div style="font-size:12.5px"><?=e($a['feedback'])?></div>
      </div>
      <?php endif;?>
    </div>
    <div style="text-align:right;flex-shrink:0">
      <?php if($pct!==null):?>
      <div style="font-size:20px;font-weight:800;color:<?=$pct>=50?'var(--green)':'var(--error)'?>"><?=$pct?>%</div>
      <div style="font-size:11px;color:var(--ink-soft)"><?=$a['my_score']?> / <?=$a['max_score']?></div>
      <?php if($a['graded_at']):?><div style="font-size:11px;color:var(--ink-soft)">Graded <?=date('d M',strtotime($a['graded_at']))?></div><?php endif;?>
      <?php else:?><span style="font-size:12px;color:var(--ink-faint)">Pending</span><?php endif;?>
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
