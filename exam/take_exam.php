<?php
// ============================================================
// KHSMIS — Online Entrance Examination Player
// Accessible by applicants via a secure token
// ============================================================
require_once dirname(__DIR__).'/config/db.php';
$pdo = db();

// Authenticate via token (app number + phone in query string, validated here)
$appNum = trim($_GET['app']  ?? $_SESSION['exam_app_num'] ?? '');
$phone  = trim($_GET['phone']?? $_SESSION['exam_phone']   ?? '');
$examId = (int)($_GET['exam_id'] ?? $_SESSION['exam_id']  ?? 0);

if (!$appNum || !$phone || !$examId) {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center"><h2>Access denied</h2><p>Invalid exam link. Please use the link provided in your entrance eligibility letter.</p></div>');
}

// Lookup application
$app = $pdo->prepare("SELECT * FROM applications WHERE application_number=? AND (phone=? OR guardian_phone=?) LIMIT 1");
$app->execute([$appNum,$phone,$phone]); $app=$app->fetch();
if (!$app) die('<p style="font-family:sans-serif;padding:40px;text-align:center">Application not found. Please contact the school.</p>');

// Lookup exam
$exam = $pdo->prepare("SELECT e.*,g.name grade_name FROM entrance_exams e JOIN grades g ON g.id=e.grade_id WHERE e.id=? AND e.status='active' LIMIT 1");
$exam->execute([$examId]); $exam=$exam->fetch();
if (!$exam) die('<p style="font-family:sans-serif;padding:40px;text-align:center">Exam not available. It may not have started yet or has closed.</p>');

// Check existing attempt
$attempt = $pdo->prepare("SELECT * FROM entrance_exam_attempts WHERE exam_id=? AND application_id=? AND attempt_number=1 LIMIT 1");
$attempt->execute([$examId,$app['id']]); $attempt=$attempt->fetch();

// Create attempt if new
if (!$attempt) {
    $pdo->prepare("INSERT INTO entrance_exam_attempts (exam_id,application_id,started_at,ip_address,user_agent,status) VALUES (?,?,NOW(),?,?,?)")
       ->execute([$examId,$app['id'],$_SERVER['REMOTE_ADDR']??null,substr($_SERVER['HTTP_USER_AGENT']??'',0,500),'in_progress']);
    $attemptId = (int)$pdo->lastInsertId();
} else {
    $attemptId = (int)$attempt['id'];
    if ($attempt['status']==='submitted') {
        // Show result page
        include __DIR__.'/exam_result.php';
        exit;
    }
}

// Load questions
$qs = $pdo->prepare("SELECT q.*,s.name subj_name FROM entrance_questions q LEFT JOIN subjects s ON s.id=q.subject_id WHERE q.exam_id=? AND q.is_active=1 ORDER BY q.id");
$qs->execute([$examId]); $questions=$qs->fetchAll();
if ($exam['randomize_q']) shuffle($questions);

// Load options for each MCQ
foreach ($questions as &$q) {
    if ($q['q_type']==='mcq'||$q['q_type']==='truefalse') {
        $opts=$pdo->prepare("SELECT * FROM entrance_question_options WHERE question_id=? ORDER BY sequence");
        $opts->execute([$q['id']]); $q['options']=$opts->fetchAll();
        if ($exam['randomize_a'] && $q['q_type']==='mcq') shuffle($q['options']);
    } else { $q['options']=[]; }
}
unset($q);

// Load saved answers
$savedAns=$pdo->prepare("SELECT question_id,answer_text,option_id FROM entrance_answers WHERE attempt_id=?");
$savedAns->execute([$attemptId]); $savedAns=array_column($savedAns->fetchAll(),null,'question_id');

$totalSec = $exam['duration_minutes'] * 60;
// Reduce by elapsed time
if ($attempt && $attempt['started_at']) {
    $elapsed = time() - strtotime($attempt['started_at']);
    $totalSec = max(0, $totalSec - $elapsed);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Entrance Examination — KARN HIGH SCHOOL</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
  <style>
    body { margin:0; background:var(--bg); }
    .exam-header { background:var(--primary-deep); color:#fff; padding:14px 24px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:50; }
    .exam-header h1 { font-size:16px; font-weight:700; }
    .exam-shell { display:grid; grid-template-columns:260px 1fr; min-height:calc(100vh - 56px); }
    .exam-nav-panel { background:var(--surface); border-right:1px solid var(--line); padding:16px; overflow-y:auto; }
    .exam-content { padding:28px; overflow-y:auto; }
    @media(max-width:768px){ .exam-shell{grid-template-columns:1fr;} .exam-nav-panel{display:none;} }
  </style>
</head>
<body>

<div class="exam-header">
  <div>
    <h1>KARN HIGH SCHOOL — Entrance Examination</h1>
    <span style="font-size:12px;opacity:.75"><?= e($exam['title']) ?> &middot; <?= e($app['first_name'].' '.$app['last_name']) ?></span>
  </div>
  <div style="display:flex;align-items:center;gap:20px">
    <div>
      <div style="font-size:11px;opacity:.75;margin-bottom:2px">Time remaining</div>
      <div class="exam-timer" id="examTimer">--:--</div>
    </div>
    <button class="button button-light button-sm" onclick="confirmSubmit()">Submit Exam</button>
  </div>
</div>

<div class="exam-shell">
  <!-- Question navigation -->
  <div class="exam-nav-panel">
    <div style="font-size:12px;font-weight:700;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px">Questions</div>
    <div class="exam-q-grid" id="examQGrid">
      <!-- Rendered by JS -->
    </div>
    <div style="margin-top:16px;display:flex;flex-direction:column;gap:6px;font-size:12px">
      <div style="display:flex;align-items:center;gap:6px"><span style="width:14px;height:14px;border-radius:3px;background:var(--green);display:inline-block"></span> Answered</div>
      <div style="display:flex;align-items:center;gap:6px"><span style="width:14px;height:14px;border-radius:3px;background:var(--gold);display:inline-block"></span> Flagged</div>
      <div style="display:flex;align-items:center;gap:6px"><span style="width:14px;height:14px;border-radius:3px;background:var(--bg-soft);border:1.5px solid var(--line);display:inline-block"></span> Not answered</div>
    </div>
  </div>

  <!-- Question panels -->
  <div class="exam-content">
    <input type="hidden" id="examAttemptId" value="<?= $attemptId ?>"/>
    <input type="hidden" id="examTotal"     value="<?= count($questions) ?>"/>

    <?php foreach ($questions as $qi => $q):
      $saved    = $savedAns[$q['id']] ?? null;
      $savedOpt = $saved['option_id']   ?? null;
      $savedTxt = $saved['answer_text'] ?? '';
    ?>
    <div class="question-panel" style="display:<?= $qi===0?'block':'none' ?>">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px">
        <span style="font-size:13px;font-weight:700;color:var(--ink-faint)">Question <?= $qi+1 ?> of <?= count($questions) ?></span>
        <div style="display:flex;gap:10px;align-items:center">
          <span style="font-size:12px;color:var(--primary);font-weight:600"><?= e($q['subj_name']??'General') ?> &middot; <?= $q['marks'] ?> mark<?= $q['marks']!=1?'s':'' ?></span>
          <button onclick="ExamPlayer.toggleFlag(<?= $qi ?>)" class="filter-button button-sm">🚩 Flag</button>
        </div>
      </div>
      <div class="exam-question"><?= nl2br(e($q['question'])) ?></div>

      <?php if ($q['q_type']==='mcq' && !empty($q['options'])): ?>
        <?php foreach ($q['options'] as $opt): ?>
        <label class="exam-option" onclick="this.classList.toggle('selected',true);document.querySelectorAll('.question-panel:not([style*=none]) .exam-option').forEach(o=>o!==this&&o.classList.remove('selected'));ExamPlayer.recordAnswer(<?= $qi ?>,<?= $opt['id'] ?>)">
          <input type="radio" name="q_<?= $qi ?>" value="<?= $opt['id'] ?>" <?= $savedOpt==$opt['id']?'checked':'' ?> style="pointer-events:none"/>
          <span><?= e($opt['option_text']) ?></span>
        </label>
        <?php endforeach; ?>

      <?php elseif ($q['q_type']==='truefalse'): ?>
        <?php foreach (['True','False'] as $tf): ?>
        <label class="exam-option" onclick="this.classList.toggle('selected',true);document.querySelectorAll('.question-panel:not([style*=none]) .exam-option').forEach(o=>o!==this&&o.classList.remove('selected'));ExamPlayer.recordAnswer(<?= $qi ?>,'<?= $tf ?>')">
          <input type="radio" name="q_<?= $qi ?>" value="<?= $tf ?>" <?= $savedTxt===$tf?'checked':'' ?> style="pointer-events:none"/>
          <span><?= $tf ?></span>
        </label>
        <?php endforeach; ?>

      <?php else: ?>
        <textarea oninput="ExamPlayer.recordAnswer(<?= $qi ?>,this.value)" rows="5" placeholder="Write your answer here…" style="width:100%;padding:12px;border:1px solid var(--line);border-radius:var(--radius-sm);font-size:14px;font-family:inherit;resize:vertical"><?= e($savedTxt) ?></textarea>
      <?php endif; ?>

      <div style="display:flex;justify-content:space-between;margin-top:20px">
        <button id="examPrev" onclick="ExamPlayer.prev()" class="button button-secondary button-sm" <?= $qi===0?'disabled':'' ?>>← Previous</button>
        <?php if ($qi < count($questions)-1): ?>
        <button id="examNext" onclick="ExamPlayer.next()" class="button button-primary button-sm">Next →</button>
        <?php else: ?>
        <button onclick="confirmSubmit()" class="button button-success button-sm">✓ Submit Exam</button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
// Init exam player
document.addEventListener('DOMContentLoaded', () => ExamPlayer.init(<?= $totalSec ?>));

function confirmSubmit() {
  if (confirm('Are you sure you want to submit the exam? You cannot change your answers after submission.')) {
    ExamPlayer.submitExam(false);
  }
}
</script>
</body>
</html>
