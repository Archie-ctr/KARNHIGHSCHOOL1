<?php
// ============================================================
// Student Portal — Quiz Result Page
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('student');

$pdo       = db();
$user      = currentUser();
$activePage= 'assessments';
$attemptId = (int)($_GET['attempt_id'] ?? 0);
$quizId    = (int)($_GET['quiz_id']    ?? 0);

$student = $pdo->prepare("SELECT id FROM students WHERE user_id=? LIMIT 1");
$student->execute([$user['id']]); $student=$student->fetch();
if (!$student) redirect(BASE_URL.'/portal/student/');
$studentId = (int)$student['id'];

// Load attempt
$attempt = null;
if ($attemptId) {
    $attempt = $pdo->query(
        "SELECT qa.*, tq.title quiz_title, tq.time_limit_mins, tq.total_marks,
                sub.name subject_name, c.name class_name
         FROM quiz_attempts qa
         JOIN teacher_quizzes tq ON tq.id=qa.quiz_id
         LEFT JOIN subjects sub ON sub.id=tq.subject_id
         LEFT JOIN classes  c   ON c.id =tq.class_id
         WHERE qa.id=$attemptId AND qa.student_id=$studentId"
    )->fetch();
}

if (!$attempt) redirect(BASE_URL.'/portal/student/assessments.php?tab=results');

$quizId   = (int)$attempt['quiz_id'];
$responses= json_decode($attempt['responses']??'{}',true)?:[];
$score    = (float)$attempt['score'];
$total    = (float)($attempt['total_marks']??0);
$pct      = $total>0?round($score/$total*100,1):null;

// Load questions with answers
$questions=$pdo->query(
    "SELECT * FROM quiz_questions WHERE quiz_id=$quizId ORDER BY sequence"
)->fetchAll();

$passed   = $pct!==null&&$pct>=70;
$emoji    = $pct===null?'📊':($pct>=90?'🏆':($pct>=70?'✅':($pct>=50?'👍':'📝')));
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Quiz Result — <?=e($attempt['quiz_title'])?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/>
<style>
.ans-row{padding:14px 16px;border-radius:var(--radius-sm);margin-bottom:10px;border:1.5px solid var(--line)}
.ans-row.correct{background:var(--green-soft);border-color:var(--green)}
.ans-row.wrong{background:var(--error-soft);border-color:var(--error)}
.ans-row.skipped{background:var(--bg2);border-color:var(--line)}
</style>
</head><body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php';?>
<div class="portal-content">

<?php foreach(getFlash() as $f):?><div class="alert alert-<?=$f['type']?>"><?=e($f['message'])?></div><?php endforeach;?>

<div class="page-heading">
  <div><h1>Quiz Result</h1><p><?=e($attempt['subject_name'])?> &mdash; <?=e($attempt['class_name'])?></p></div>
  <a href="assessments.php?tab=quizzes" class="button button-secondary">← Back to Quizzes</a>
</div>

<!-- Score banner -->
<div class="panel" style="padding:28px;margin-bottom:20px;text-align:center;border-top:4px solid <?=$passed?'var(--green)':'var(--error)'?>">
  <div style="font-size:48px;margin-bottom:10px"><?=$emoji?></div>
  <h2 style="font-weight:800;font-size:24px;margin-bottom:6px"><?=e($attempt['quiz_title'])?></h2>
  <p style="color:var(--ink-soft);margin-bottom:18px"><?=e($attempt['subject_name'])?> &middot; Submitted <?=$attempt['submitted_at']?date('d M Y H:i',strtotime($attempt['submitted_at'])):'—'?></p>
  <div style="display:flex;justify-content:center;align-items:center;gap:32px;flex-wrap:wrap">
    <div>
      <div style="font-size:40px;font-weight:800;color:<?=$passed?'var(--green)':'var(--error)'?>"><?=$pct!==null?$pct.'%':'—'?></div>
      <div style="font-size:13px;color:var(--ink-soft)">Score</div>
    </div>
    <div>
      <div style="font-size:28px;font-weight:800"><?=$score?> / <?=$total?></div>
      <div style="font-size:13px;color:var(--ink-soft)">Marks</div>
    </div>
    <div>
      <div style="font-size:24px;font-weight:800;color:<?=$passed?'var(--green)':'var(--error)'?>"><?=$passed?'PASSED':'FAILED'?></div>
      <div style="font-size:13px;color:var(--ink-soft)">Result (Pass ≥ 70%)</div>
    </div>
    <?php if($attempt['time_spent']):?>
    <div>
      <div style="font-size:24px;font-weight:800"><?=floor($attempt['time_spent']/60)?>:<?=str_pad($attempt['time_spent']%60,2,'0',STR_PAD_LEFT)?></div>
      <div style="font-size:13px;color:var(--ink-soft)">Time spent</div>
    </div>
    <?php endif;?>
  </div>
  <!-- Progress bar -->
  <div style="max-width:360px;margin:18px auto 0">
    <div style="height:10px;background:var(--bg2);border-radius:6px;overflow:hidden">
      <div style="width:<?=$pct??0?>%;height:100%;background:<?=$passed?'var(--green)':'var(--error)'?>;border-radius:6px;transition:width 1s"></div>
    </div>
  </div>
</div>

<!-- Question-by-question breakdown -->
<div class="panel" style="padding:20px">
  <h3 style="font-weight:700;font-size:14px;margin-bottom:16px">📋 Answer Review</h3>
  <?php $qNum=0; foreach($questions as $q): $qNum++;
    $studentAns = trim($responses[$q['id']]??'');
    $correctAns = trim($q['answer']??'');
    $opts       = $q['options']?json_decode($q['options'],true):[];
    $letters    = ['A','B','C','D','E'];
    // Determine correct/wrong/skipped
    $isSkipped  = $studentAns==='';
    $isCorrect  = !$isSkipped && strtolower($studentAns)===strtolower($correctAns);
    $rowClass   = $isSkipped?'skipped':($isCorrect?'correct':'wrong');
    $icon       = $isSkipped?'—':($isCorrect?'✅':'❌');
    // Get option text for MCQ
    $ansText    = $studentAns;
    $corrText   = $correctAns;
    if($opts && $q['type']==='mcq'){
      $idx=array_search($studentAns,$letters);
      if($idx!==false&&isset($opts[$idx])) $ansText=$studentAns.': '.$opts[$idx];
      $cidx=array_search($correctAns,$letters);
      if($cidx!==false&&isset($opts[$cidx])) $corrText=$correctAns.': '.$opts[$cidx];
    }
  ?>
  <div class="ans-row <?=$rowClass?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px">
      <div style="flex:1;min-width:0">
        <div style="font-size:11px;font-weight:700;color:var(--ink-soft);margin-bottom:4px">
          Q<?=$qNum?> &middot; <?=ucfirst(str_replace('_',' ',$q['type']))?> &middot; <?=$q['marks']?> mark<?=$q['marks']!=1?'s':''?>
        </div>
        <div style="font-size:14px;font-weight:600;margin-bottom:8px"><?=nl2br(e($q['question']))?></div>
        <div style="font-size:13px">
          <span style="color:var(--ink-soft)">Your answer: </span>
          <strong style="color:<?=$isSkipped?'var(--ink-soft)':($isCorrect?'var(--green)':'var(--error)')?>"><?=$ansText?:'(not answered)'?></strong>
        </div>
        <?php if(!$isCorrect&&!$isSkipped):?>
        <div style="font-size:13px;margin-top:3px">
          <span style="color:var(--ink-soft)">Correct answer: </span>
          <strong style="color:var(--green)"><?=e($corrText)?></strong>
        </div>
        <?php endif;?>
      </div>
      <div style="font-size:20px;flex-shrink:0"><?=$icon?></div>
    </div>
  </div>
  <?php endforeach;?>
</div>

<div style="display:flex;gap:10px;margin-top:16px;flex-wrap:wrap">
  <a href="assessments.php?tab=quizzes" class="button button-primary">← All Quizzes</a>
  <a href="assessments.php?tab=results" class="button button-secondary">📊 My Results</a>
</div>

</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script>
</body></html>
