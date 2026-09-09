<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('student');
$pdo=db(); $user=currentUser(); $activePage='assessments';
$ayId=currentAcademicYearId(); $ay=currentAcademicYearName();

$student=$pdo->prepare("SELECT s.*,g.name grade_name,c.name class_name FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id LEFT JOIN classes c ON c.id=s.current_class_id WHERE s.user_id=? LIMIT 1");
$student->execute([$user['id']]); $student=$student->fetch();
if(!$student) redirect(BASE_URL.'/portal/student/');

$tab=$_GET['tab']??'assignments';
$classId=$student['current_class_id']; $studentId=$student['id'];

// ── Assessments (classwork/homework/tests) ──────────────────
try{
    $assessments=$pdo->query(
        "SELECT ta.*,sub.name subject_name,
                (SELECT score FROM assessment_submissions WHERE assessment_id=ta.id AND student_id=$studentId LIMIT 1) my_score,
                (SELECT graded_at FROM assessment_submissions WHERE assessment_id=ta.id AND student_id=$studentId LIMIT 1) graded_at
         FROM teacher_assessments ta
         JOIN subjects sub ON sub.id=ta.subject_id
         WHERE ta.class_id=$classId AND ta.academic_year_id=$ayId
         ORDER BY FIELD(ta.assessment_type,'test','homework','classwork','quiz','project','other'),ta.due_date ASC"
    )->fetchAll();
}catch(Throwable $e){$assessments=[];}

// ── Quizzes ─────────────────────────────────────────────────
try{
    $quizzes=$pdo->query(
        "SELECT tq.*,sub.name subject_name,
                (SELECT score FROM quiz_attempts WHERE quiz_id=tq.id AND student_id=$studentId ORDER BY id DESC LIMIT 1) my_score,
                (SELECT attempt_no FROM quiz_attempts WHERE quiz_id=tq.id AND student_id=$studentId ORDER BY id DESC LIMIT 1) attempts_used,
                (SELECT submitted_at FROM quiz_attempts WHERE quiz_id=tq.id AND student_id=$studentId ORDER BY id DESC LIMIT 1) last_attempt
         FROM teacher_quizzes tq
         JOIN subjects sub ON sub.id=tq.subject_id
         WHERE tq.class_id=$classId AND tq.academic_year_id=$ayId AND tq.is_published=1
         ORDER BY tq.created_at DESC"
    )->fetchAll();
}catch(Throwable $e){$quizzes=[];}

// ── Submission history ───────────────────────────────────────
try{
    $submissions=$pdo->query(
        "SELECT asub.*,ta.title,ta.assessment_type,ta.max_score,sub.name subject_name
         FROM assessment_submissions asub
         JOIN teacher_assessments ta ON ta.id=asub.assessment_id
         JOIN subjects sub ON sub.id=ta.subject_id
         WHERE asub.student_id=$studentId AND ta.academic_year_id=$ayId
         ORDER BY asub.graded_at DESC LIMIT 30"
    )->fetchAll();
}catch(Throwable $e){$submissions=[];}

// ── Quiz attempts ────────────────────────────────────────────
try{
    $attempts=$pdo->query(
        "SELECT qa.*,tq.title quiz_title,tq.time_limit_mins,sub.name subject_name
         FROM quiz_attempts qa
         JOIN teacher_quizzes tq ON tq.id=qa.quiz_id
         JOIN subjects sub ON sub.id=tq.subject_id
         WHERE qa.student_id=$studentId AND tq.academic_year_id=$ayId
         ORDER BY qa.submitted_at DESC LIMIT 20"
    )->fetchAll();
}catch(Throwable $e){$attempts=[];}

$typeColors=['classwork'=>'var(--blue)','homework'=>'var(--gold)','test'=>'var(--error)','quiz'=>'var(--warning)','project'=>'var(--green)','other'=>'var(--ink-soft)'];
$typeIcons =['classwork'=>'📘','homework'=>'📓','test'=>'📋','quiz'=>'🧠','project'=>'🗂️','other'=>'📄'];
$now=date('Y-m-d');
$overdue=array_filter($assessments,fn($a)=>$a['due_date']&&$a['due_date']<$now&&$a['my_score']===null);
$upcoming=array_filter($assessments,fn($a)=>!$a['due_date']||$a['due_date']>=$now);
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Assessments — Student Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">

<div class="page-heading">
  <div><h1>Assessments</h1>
  <p><?=e($student['class_name']??'').(isset($student['grade_name'])?' &mdash; '.e($student['grade_name']):'').' &mdash; '.e($ay)?></p></div>
</div>

<?php if(count($overdue)>0):?>
<div class="alert alert-warning">⚠️ <strong><?=count($overdue)?> overdue assessment<?=count($overdue)!=1?'s':''?></strong> — check with your teacher about submission.</div>
<?php endif;?>

<div class="metric-grid" style="margin-bottom:16px">
  <div class="metric-card"><div class="metric-top"><span>Assessments</span><div class="metric-icon">📝</div></div><strong><?=count($assessments)?></strong><small><i></i><?=e($ay)?></small></div>
  <div class="metric-card <?=count($overdue)>0?'finance-metrics':''?>"><div class="metric-top"><span>Overdue</span><div class="metric-icon">⚠️</div></div><strong style="color:<?=count($overdue)>0?'var(--error)':'var(--green)'?>"><?=count($overdue)?></strong></div>
  <div class="metric-card"><div class="metric-top"><span>Quizzes</span><div class="metric-icon">🧠</div></div><strong><?=count($quizzes)?></strong><small><i></i>Published</small></div>
  <div class="metric-card"><div class="metric-top"><span>Submitted</span><div class="metric-icon">✅</div></div><strong><?=count($submissions)?></strong><small><i></i>Graded</small></div>
</div>

<div class="tab-bar" style="margin-bottom:16px">
  <a href="?tab=assignments"  class="tab-btn <?=$tab==='assignments' ?'active':''?>">📝 Assignments &amp; Tests</a>
  <a href="?tab=quizzes"      class="tab-btn <?=$tab==='quizzes'     ?'active':''?>">🧠 Online Quizzes</a>
  <a href="?tab=results"      class="tab-btn <?=$tab==='results'     ?'active':''?>">📊 Quiz Results</a>
  <a href="?tab=history"      class="tab-btn <?=$tab==='history'     ?'active':''?>">📋 Submission History</a>
</div>

<?php if($tab==='assignments'): ?>
<?php if(empty($assessments)):?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:40px;margin-bottom:12px">📝</div>
  <h3 style="margin-bottom:6px">No assessments yet</h3>
  <p style="color:var(--ink-soft)">Your teachers haven't posted any assessments yet for <?=e($ay)?>.</p>
</div>
<?php else:?>
<div style="display:flex;flex-direction:column;gap:10px">
  <?php foreach($assessments as $a):
    $isOverdue=$a['due_date']&&$a['due_date']<$now&&$a['my_score']===null;
    $isDue=$a['due_date']&&$a['due_date']>=$now&&$a['due_date']<=date('Y-m-d',strtotime('+3 days'));
    $col=$typeColors[$a['assessment_type']??'other']??'var(--ink-soft)';
    $icon=$typeIcons[$a['assessment_type']??'other']??'📄';
    $pct=$a['my_score']!==null&&$a['max_score']>0?round($a['my_score']/$a['max_score']*100,1):null;
  ?>
  <div class="panel" style="padding:18px;border-left:4px solid <?=$isOverdue?'var(--error)':($isDue?'var(--warning)':$col)?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px">
      <div>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px">
          <span style="font-size:16px"><?=$icon?></span>
          <strong style="font-size:14px"><?=e($a['title'])?></strong>
          <span style="font-size:11px;padding:2px 8px;border-radius:10px;background:<?=$col?>22;color:<?=$col?>;font-weight:700"><?=ucfirst($a['assessment_type']??'other')?></span>
          <?php if($isOverdue):?><span class="status warning" style="font-size:11px">Overdue</span><?php elseif($isDue):?><span class="status new-s" style="font-size:11px">Due Soon</span><?php endif;?>
        </div>
        <div style="font-size:12.5px;color:var(--ink-soft)"><?=e($a['subject_name'])?> <?=$a['due_date']?' &middot; Due: <strong style="color:'.($isOverdue?'var(--error)':'inherit').'">'.date('d M Y',strtotime($a['due_date'])).'</strong>':''?></div>
        <?php if($a['description']):?><div style="font-size:12.5px;color:var(--ink2);margin-top:5px"><?=e(mb_substr($a['description'],0,120))?><?=mb_strlen($a['description'])>120?'…':''?></div><?php endif;?>
      </div>
      <div style="text-align:right;flex-shrink:0">
        <?php if($pct!==null):?>
        <div style="font-size:18px;font-weight:800;color:<?=$pct>=50?'var(--green)':'var(--error)'?>"><?=$pct?>%</div>
        <div style="font-size:11px;color:var(--ink-soft)"><?=$a['my_score']?> / <?=$a['max_score']?></div>
        <?php elseif($a['my_score']===null&&!$isOverdue):?>
        <span style="font-size:12px;color:var(--ink-faint)">Pending grade</span>
        <?php else:?>
        <span style="font-size:12px;color:var(--ink-faint)">Not graded</span>
        <?php endif;?>
        <?php if($a['graded_at']):?><div style="font-size:11px;color:var(--ink-soft);margin-top:2px">Graded <?=date('d M',strtotime($a['graded_at']))?></div><?php endif;?>
      </div>
    </div>
  </div>
  <?php endforeach;?>
</div>
<?php endif;?>

<?php elseif($tab==='quizzes'): ?>
<?php if(empty($quizzes)):?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:40px;margin-bottom:12px">🧠</div>
  <h3 style="margin-bottom:6px">No quizzes available</h3>
  <p style="color:var(--ink-soft)">Your teachers haven't published any quizzes yet.</p>
</div>
<?php else:?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px">
  <?php foreach($quizzes as $q):
    $attempted=$q['last_attempt']!==null;
    $canRetry=!$attempted||($q['attempts_used']<$q['max_attempts']);
    $isOpen=(!$q['start_date']||$q['start_date']<=date('Y-m-d H:i:s'))&&(!$q['end_date']||$q['end_date']>=date('Y-m-d H:i:s'));
    $pct=$q['my_score']!==null&&(float)$q['total_marks']>0?round($q['my_score']/(float)$q['total_marks']*100,1):null;
  ?>
  <div class="panel" style="padding:18px;border-top:3px solid <?=$attempted?($pct>=50?'var(--green)':'var(--error)'):'var(--primary)'?>">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:4px"><?=e($q['title'])?></h3>
    <div style="font-size:12.5px;color:var(--ink-soft);margin-bottom:10px"><?=e($q['subject_name'])?></div>
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px">
      <?php if($q['time_limit_mins']):?><span style="font-size:11.5px;background:var(--bg2);padding:3px 8px;border-radius:8px">⏱ <?=$q['time_limit_mins']?> min</span><?php endif;?>
      <span style="font-size:11.5px;background:var(--bg2);padding:3px 8px;border-radius:8px">🔄 <?=$q['attempts_used']??0?>/<?=$q['max_attempts']?> attempts</span>
      <?php if(!$isOpen):?><span style="font-size:11px;background:var(--error-soft);color:var(--error);padding:3px 8px;border-radius:8px;font-weight:700">Closed</span><?php endif;?>
    </div>
    <?php if($pct!==null):?>
    <div style="background:var(--bg2);border-radius:var(--radius-sm);padding:10px;margin-bottom:10px;text-align:center">
      <div style="font-size:22px;font-weight:800;color:<?=$pct>=50?'var(--green)':'var(--error)'?>"><?=$pct?>%</div>
      <div style="font-size:12px;color:var(--ink-soft)"><?=$q['my_score']?> / <?=$q['total_marks']?> marks</div>
    </div>
    <?php endif;?>
    <?php if($q['description']):?><p style="font-size:12.5px;color:var(--ink2);margin-bottom:10px"><?=e(mb_substr($q['description'],0,80))?></p><?php endif;?>
    <?php if($isOpen&&$canRetry):?>
    <a href="<?=BASE_URL?>/portal/student/take_quiz.php?quiz_id=<?=$q['id']?>" class="button button-primary" style="width:100%;text-align:center"><?=$attempted?'Retake Quiz':'Start Quiz'?></a>
    <?php elseif($attempted):?>
    <div class="button button-secondary" style="width:100%;text-align:center;cursor:default;opacity:.6">Max attempts reached</div>
    <?php else:?>
    <div class="button button-secondary" style="width:100%;text-align:center;cursor:default;opacity:.6">Not available</div>
    <?php endif;?>
  </div>
  <?php endforeach;?>
</div>
<?php endif;?>

<?php elseif($tab==='results'): ?>
<?php if(empty($attempts)):?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:40px;margin-bottom:12px">📊</div>
  <h3>No quiz results yet</h3>
  <p style="color:var(--ink-soft)">Complete some quizzes to see your results here.</p>
</div>
<?php else:?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Quiz</th><th>Subject</th><th>Score</th><th>Percentage</th><th>Attempt</th><th>Submitted</th></tr></thead>
    <tbody>
      <?php foreach($attempts as $a):
        $pct=$a['total_marks']>0?round($a['score']/$a['total_marks']*100,1):null;
      ?>
      <tr>
        <td><strong><?=e($a['quiz_title'])?></strong></td>
        <td class="muted"><?=e($a['subject_name'])?></td>
        <td><strong><?=$a['score']!==null?$a['score']:'—'?></strong><?=$a['total_marks']?' / '.$a['total_marks']:''?></td>
        <td><?php if($pct!==null):?><span style="font-weight:700;color:<?=$pct>=50?'var(--green)':'var(--error)'?>"><?=$pct?>%</span><?php else:?>—<?php endif;?></td>
        <td class="muted">#<?=$a['attempt_no']?></td>
        <td class="muted"><?=$a['submitted_at']?date('d M Y H:i',strtotime($a['submitted_at'])):'In progress'?></td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>
<?php endif;?>

<?php else: // history tab ?>
<?php if(empty($submissions)):?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:40px;margin-bottom:12px">📋</div>
  <h3>No submission history yet</h3>
</div>
<?php else:?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Assessment</th><th>Type</th><th>Subject</th><th>Score</th><th>%</th><th>Feedback</th><th>Graded</th></tr></thead>
    <tbody>
      <?php foreach($submissions as $s):
        $pct=$s['max_score']>0&&$s['score']!==null?round($s['score']/$s['max_score']*100,1):null;
        $icon=$typeIcons[$s['assessment_type']??'other']??'📄';
      ?>
      <tr>
        <td><strong><?=$icon?> <?=e($s['title'])?></strong></td>
        <td><span style="font-size:11px;color:<?=$typeColors[$s['assessment_type']??'other']?>;font-weight:700"><?=ucfirst($s['assessment_type']??'other')?></span></td>
        <td class="muted"><?=e($s['subject_name'])?></td>
        <td><strong><?=$s['score']!==null?$s['score']:'—'?></strong><?=$s['max_score']?' / '.$s['max_score']:''?></td>
        <td><?php if($pct!==null):?><span style="color:<?=$pct>=50?'var(--green)':'var(--error)'?>;font-weight:700"><?=$pct?>%</span><?php else:?>—<?php endif;?></td>
        <td style="max-width:160px;font-size:12.5px;color:var(--ink-soft)"><?=e(mb_substr($s['feedback']??'—',0,60))?></td>
        <td class="muted"><?=$s['graded_at']?date('d M Y',strtotime($s['graded_at'])):'Pending'?></td>
      </tr>
      <?php endforeach;?>
    </tbody>
  </table>
</div>
<?php endif;?>
<?php endif;?>

</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
