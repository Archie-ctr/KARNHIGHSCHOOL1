<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('parent');
$activePage='quizzes'; $ayId=currentAcademicYearId(); $ay=currentAcademicYearName();
include __DIR__.'/includes/resolve_child.php';
$pdo=db(); $cq=$selChild?'?child_id='.$selChild:'';

$quizzes=[]; $attempts=[]; $avgScore=null;

if($child){
    $cid=$child['id']; $classId=$child['current_class_id'];
    try{
        $quizzes=$pdo->query(
            "SELECT tq.*,sub.name subject_name,
                    (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id=tq.id AND student_id=$cid) attempt_count,
                    (SELECT score FROM quiz_attempts WHERE quiz_id=tq.id AND student_id=$cid ORDER BY id DESC LIMIT 1) last_score,
                    (SELECT total_marks FROM quiz_attempts WHERE quiz_id=tq.id AND student_id=$cid ORDER BY id DESC LIMIT 1) total_marks_val,
                    (SELECT submitted_at FROM quiz_attempts WHERE quiz_id=tq.id AND student_id=$cid ORDER BY id DESC LIMIT 1) last_attempt
             FROM teacher_quizzes tq
             JOIN subjects sub ON sub.id=tq.subject_id
             WHERE tq.class_id=$classId AND tq.academic_year_id=$ayId AND tq.is_published=1
             ORDER BY tq.created_at DESC"
        )->fetchAll();
    }catch(Throwable $e){}
    try{
        $attempts=$pdo->query(
            "SELECT qa.*,tq.title quiz_title,sub.name subject_name,tq.time_limit_mins
             FROM quiz_attempts qa
             JOIN teacher_quizzes tq ON tq.id=qa.quiz_id
             JOIN subjects sub ON sub.id=tq.subject_id
             WHERE qa.student_id=$cid AND tq.academic_year_id=$ayId
             ORDER BY qa.submitted_at DESC LIMIT 30"
        )->fetchAll();
    }catch(Throwable $e){}
    if(!empty($attempts)){
        $ptotals=array_filter(array_map(fn($a)=>$a['total_marks']>0?round($a['score']/$a['total_marks']*100,1):null,$attempts),fn($v)=>$v!==null);
        if(count($ptotals)) $avgScore=round(array_sum($ptotals)/count($ptotals),1);
    }
}

$participated=count(array_filter($quizzes,fn($q)=>$q['attempt_count']>0));
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Quiz Performance — Parent Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">
<div class="page-heading">
  <div><h1><?=$child?e($child['first_name'])."'s":'Child'?> Quiz Performance</h1>
  <p><?=e($ay)?></p></div>
  <a href="index.php<?=$cq?>" class="button button-secondary">← Dashboard</a>
</div>
<?php if(!$child):?><div class="alert alert-warning">Select a child to view their quiz performance.</div>
<?php else:?>
<div class="metric-grid" style="margin-bottom:16px">
  <div class="metric-card"><div class="metric-top"><span>Quizzes Available</span><div class="metric-icon">🧠</div></div><strong><?=count($quizzes)?></strong></div>
  <div class="metric-card"><div class="metric-top"><span>Participated</span><div class="metric-icon">✅</div></div><strong><?=$participated?></strong><small><i></i>of <?=count($quizzes)?></small></div>
  <div class="metric-card"><div class="metric-top"><span>Total Attempts</span><div class="metric-icon">🔄</div></div><strong><?=count($attempts)?></strong></div>
  <div class="metric-card"><div class="metric-top"><span>Average Score</span><div class="metric-icon">📊</div></div><strong style="color:<?=$avgScore!==null?($avgScore>=50?'var(--green)':'var(--error)'):'inherit'?>"><?=$avgScore!==null?$avgScore.'%':'—'?></strong></div>
</div>

<div class="tab-bar" style="margin-bottom:16px">
  <a href="?tab=overview<?=$selChild?'&child_id='.$selChild:''?>" class="tab-btn <?=($_GET['tab']??'overview')==='overview'?'active':''?>">📋 Quiz Overview</a>
  <a href="?tab=attempts<?=$selChild?'&child_id='.$selChild:''?>" class="tab-btn <?=($_GET['tab']??'')==='attempts'?'active':''?>">📊 Attempt History</a>
</div>

<?php if(($_GET['tab']??'overview')==='attempts'): ?>
<?php if(empty($attempts)):?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:40px;margin-bottom:12px">🧠</div>
  <h3>No quiz attempts yet</h3>
  <p style="color:var(--ink-soft)"><?=e($child['first_name'])?> hasn't completed any quizzes.</p>
</div>
<?php else:?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Quiz</th><th>Subject</th><th>Score</th><th>%</th><th>Attempt</th><th>Time Limit</th><th>Submitted</th></tr></thead>
    <tbody>
      <?php foreach($attempts as $a): $pct=$a['total_marks']>0?round($a['score']/$a['total_marks']*100,1):null;?>
      <tr>
        <td><strong><?=e($a['quiz_title'])?></strong></td>
        <td class="muted"><?=e($a['subject_name'])?></td>
        <td><strong><?=$a['score']!==null?$a['score']:'—'?></strong><?=$a['total_marks']?' / '.$a['total_marks']:''?></td>
        <td><?php if($pct!==null):?><span style="font-weight:700;color:<?=$pct>=50?'var(--green)':'var(--error)'?>"><?=$pct?>%</span><?php else:?>—<?php endif;?></td>
        <td class="muted">#<?=$a['attempt_no']??1?></td>
        <td class="muted"><?=$a['time_limit_mins']?$a['time_limit_mins'].' min':'—'?></td>
        <td class="muted"><?=$a['submitted_at']?date('d M Y H:i',strtotime($a['submitted_at'])):'In progress'?></td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>
<?php endif;?>
<?php else: // overview ?>
<?php if(empty($quizzes)):?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:40px;margin-bottom:12px">🧠</div>
  <h3>No quizzes published yet</h3>
  <p style="color:var(--ink-soft)">Teachers haven't published any quizzes for <?=e($child['first_name'])?>'s class.</p>
</div>
<?php else:?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:12px">
<?php foreach($quizzes as $q):
  $attempted=$q['attempt_count']>0;
  $pct=$q['last_score']!==null&&(float)$q['total_marks_val']>0?round($q['last_score']/(float)$q['total_marks_val']*100,1):null;
?>
<div class="panel" style="padding:18px;border-top:3px solid <?=$attempted?($pct!==null&&$pct>=50?'var(--green)':'var(--error)'):'var(--line)'?>">
  <h3 style="font-weight:700;font-size:14px;margin-bottom:3px"><?=e($q['title'])?></h3>
  <div style="font-size:12.5px;color:var(--ink-soft);margin-bottom:10px"><?=e($q['subject_name'])?></div>
  <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;font-size:11.5px">
    <?php if($q['time_limit_mins']):?><span style="background:var(--bg2);padding:3px 8px;border-radius:8px">⏱ <?=$q['time_limit_mins']?> min</span><?php endif;?>
    <span style="background:var(--bg2);padding:3px 8px;border-radius:8px">🔄 <?=$q['attempt_count']?>/<?=$q['max_attempts']?> attempts</span>
  </div>
  <?php if($attempted&&$pct!==null):?>
  <div style="display:flex;justify-content:space-between;align-items:center;padding:10px;background:var(--bg2);border-radius:var(--radius-sm)">
    <span style="font-size:12px;color:var(--ink-soft)">Best score</span>
    <span style="font-size:20px;font-weight:800;color:<?=$pct>=50?'var(--green)':'var(--error)'?>"><?=$pct?>%</span>
  </div>
  <?php elseif(!$attempted):?>
  <div style="background:var(--warning-soft);border-radius:var(--radius-sm);padding:8px 12px;font-size:12.5px;color:var(--warning);font-weight:600">Not attempted yet</div>
  <?php endif;?>
  <?php if($q['last_attempt']):?><div style="font-size:11px;color:var(--ink-faint);margin-top:8px">Last attempt: <?=date('d M Y',strtotime($q['last_attempt']))?></div><?php endif;?>
</div>
<?php endforeach;?>
</div>
<?php endif;?>
<?php endif;?>
<?php endif;?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
