<?php
// ============================================================
// Student Portal — Take Quiz
// Accessible by: student role only
// URL: take_quiz.php?quiz_id=N
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('student');

$pdo      = db();
$user     = currentUser();
$quizId   = (int)($_GET['quiz_id'] ?? 0);
$ayId     = currentAcademicYearId();

if (!$quizId) { redirect(BASE_URL.'/portal/student/assessments.php?tab=quizzes'); }

// ── Load student record ────────────────────────────────────────
$student = $pdo->prepare(
    "SELECT s.*,g.name grade_name,c.name class_name
     FROM students s
     LEFT JOIN grades  g ON g.id=s.current_grade_id
     LEFT JOIN classes c ON c.id=s.current_class_id
     WHERE s.user_id=? LIMIT 1"
);
$student->execute([$user['id']]); $student = $student->fetch();
if (!$student) {
    echo '<div style="font-family:sans-serif;padding:48px;text-align:center">
            <h2>Student record not found</h2>
            <p>Contact the school office to link your account.</p>
            <a href="'.BASE_URL.'/admin/logout.php">Sign Out</a></div>';
    exit;
}
$studentId = $student['id'];

// ── Load quiz ──────────────────────────────────────────────────
$quiz = $pdo->query(
    "SELECT tq.*,c.name class_name,sub.name subject_name
     FROM teacher_quizzes tq
     LEFT JOIN classes  c   ON c.id  =tq.class_id
     LEFT JOIN subjects sub ON sub.id=tq.subject_id
     WHERE tq.id=$quizId AND tq.is_published=1"
)->fetch();

if (!$quiz) {
    echo '<div style="font-family:sans-serif;padding:48px;text-align:center">
            <h2>Quiz not found or not available</h2>
            <a href="'.BASE_URL.'/portal/student/assessments.php?tab=quizzes">← Back to Assessments</a></div>';
    exit;
}

// Security: quiz must belong to student's class
if ($quiz['class_id'] != $student['current_class_id']) {
    echo '<div style="font-family:sans-serif;padding:48px;text-align:center">
            <h2>You are not enrolled in this quiz\'s class</h2>
            <a href="'.BASE_URL.'/portal/student/assessments.php?tab=quizzes">← Back</a></div>';
    exit;
}

// Check date window
$now = date('Y-m-d H:i:s');
if ($quiz['start_date'] && $quiz['start_date'] > $now) {
    echo '<div style="font-family:sans-serif;padding:48px;text-align:center">
            <h2>Quiz not yet open</h2>
            <p>This quiz opens on '.date('d M Y H:i',strtotime($quiz['start_date'])).'.</p>
            <a href="'.BASE_URL.'/portal/student/assessments.php?tab=quizzes">← Back</a></div>'; exit;
}
if ($quiz['end_date'] && $quiz['end_date'] < $now) {
    echo '<div style="font-family:sans-serif;padding:48px;text-align:center">
            <h2>Quiz closed</h2>
            <p>This quiz closed on '.date('d M Y H:i',strtotime($quiz['end_date'])).'.</p>
            <a href="'.BASE_URL.'/portal/student/assessments.php?tab=quizzes">← Back</a></div>'; exit;
}

// ── Check attempts ─────────────────────────────────────────────
$attemptsDone = (int)$pdo->query(
    "SELECT COUNT(*) FROM quiz_attempts
     WHERE quiz_id=$quizId AND student_id=$studentId AND in_progress=0"
)->fetchColumn();

if ($attemptsDone >= $quiz['max_attempts']) {
    // Show results instead
    $lastAttempt = $pdo->query(
        "SELECT * FROM quiz_attempts WHERE quiz_id=$quizId AND student_id=$studentId
         ORDER BY id DESC LIMIT 1"
    )->fetch();
    $pct = ($lastAttempt && $quiz['total_marks']>0)
         ? round($lastAttempt['score']/$quiz['total_marks']*100,1) : null;
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Quiz Complete — '.e($quiz['title']).'</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="'.BASE_URL.'/assets/css/style.css"/>
    </head><body><div class="portal-grid">';
    include __DIR__.'/includes/nav.php';
    $activePage='assessments';
    echo '<div class="portal-content">
    <div class="page-heading"><div><h1>'.e($quiz['title']).'</h1><p>'.e($quiz['subject_name']).'</p></div>
    <a href="assessments.php?tab=quizzes" class="button button-secondary">← Back to Quizzes</a></div>
    <div class="panel" style="padding:32px;text-align:center;max-width:440px;margin:0 auto">
      <div style="font-size:48px;margin-bottom:12px">'.($pct>=70?'🎉':($pct>=50?'👍':'📝')).'</div>
      <h2 style="font-weight:800;font-size:22px;margin-bottom:8px">Quiz Complete</h2>
      <p style="color:var(--ink-soft);margin-bottom:20px">You have used all '.($quiz['max_attempts']).' attempt(s) for this quiz.</p>
      '.($lastAttempt?'<div style="font-size:36px;font-weight:800;color:'.($pct>=70?'var(--green)':($pct>=50?'var(--warning)':'var(--error)')).'">'.($pct!==null?$pct.'%':'—').'</div>
      <div style="color:var(--ink-soft);font-size:14px;margin-bottom:20px">'.($lastAttempt['score']).' / '.($quiz['total_marks']).' marks</div>':'').'
      <a href="assessments.php?tab=quizzes" class="button button-primary" style="width:100%;text-align:center">Back to Assessments</a>
    </div></div></div>
    <script src="'.BASE_URL.'/assets/js/main.js"></script></body></html>';
    exit;
}

// ── Resume or create in-progress attempt ───────────────────────
$currentAttempt = $pdo->query(
    "SELECT * FROM quiz_attempts
     WHERE quiz_id=$quizId AND student_id=$studentId AND in_progress=1
     ORDER BY id DESC LIMIT 1"
)->fetch();

if (!$currentAttempt) {
    $attemptNo = $attemptsDone + 1;
    $pdo->prepare(
        "INSERT INTO quiz_attempts (quiz_id,student_id,attempt_no,in_progress,started_at,responses)
         VALUES (?,?,?,1,NOW(),'{}')  "
    )->execute([$quizId, $studentId, $attemptNo]);
    $attemptId = (int)$pdo->lastInsertId();
    $savedResponses = [];
} else {
    $attemptId = (int)$currentAttempt['id'];
    $savedResponses = json_decode($currentAttempt['responses'] ?? '{}', true) ?: [];
}

// ── Load questions ─────────────────────────────────────────────
$questions = $pdo->query(
    "SELECT * FROM quiz_questions WHERE quiz_id=$quizId ORDER BY sequence ASC"
)->fetchAll();

if (empty($questions)) {
    echo '<div style="font-family:sans-serif;padding:48px;text-align:center">
            <h2>No questions in this quiz yet</h2>
            <a href="assessments.php?tab=quizzes">← Back</a></div>'; exit;
}

$totalMarks = $quiz['total_marks'] ?? array_sum(array_column($questions,'marks'));
$totalQ     = count($questions);
$timeSecs   = $quiz['time_limit_mins'] ? $quiz['time_limit_mins'] * 60 : null;

// Calculate time remaining
if ($timeSecs && $currentAttempt && $currentAttempt['started_at']) {
    $elapsed  = time() - strtotime($currentAttempt['started_at']);
    $timeSecs = max(0, $timeSecs - $elapsed);
}

$qNum = (int)($_GET['q'] ?? 1);
$qNum = max(1, min($qNum, $totalQ));
$currentQ = $questions[$qNum - 1];
$opts = $currentQ['options'] ? json_decode($currentQ['options'], true) : [];
$savedAns = $savedResponses[$currentAttempt['id'] ?? $attemptId][$currentQ['id']]
          ?? $savedResponses[$currentQ['id']]
          ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title><?= e($quiz['title']) ?> — Quiz</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
  <style>
    body { margin:0; background:var(--bg); }
    /* ── Quiz shell layout ── */
    .quiz-header {
      background:var(--primary-deep); color:#fff;
      padding:12px 20px; display:flex; align-items:center; justify-content:space-between;
      position:sticky; top:0; z-index:50; gap:12px; flex-wrap:wrap;
    }
    .quiz-header h1 { font-size:15px; font-weight:700; margin:0; }
    .quiz-shell { display:grid; grid-template-columns:240px 1fr; min-height:calc(100vh - 56px); }
    .quiz-nav-panel {
      background:var(--surface); border-right:1px solid var(--line);
      padding:16px; overflow-y:auto;
    }
    .quiz-nav-panel h3 { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--ink-soft); margin-bottom:10px; }
    .q-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:5px; margin-bottom:14px; }
    .q-btn {
      aspect-ratio:1; border-radius:6px; font-size:12px; font-weight:700;
      border:1.5px solid var(--line); background:var(--bg); color:var(--ink-soft);
      cursor:pointer; transition:all .15s; text-decoration:none; display:flex; align-items:center; justify-content:center;
    }
    .q-btn.answered  { background:var(--green-soft);  border-color:var(--green);  color:var(--green); }
    .q-btn.current   { background:var(--primary);      border-color:var(--primary-deep); color:#fff; }
    .q-btn.flagged   { background:var(--warning-soft); border-color:var(--warning); color:var(--warning); }
    /* ── Question area ── */
    .quiz-content { padding:28px; max-width:800px; }
    .q-card { background:var(--surface); border:1px solid var(--line); border-radius:var(--radius); padding:28px; margin-bottom:20px; }
    .q-number { font-size:11px; font-weight:700; color:var(--ink-soft); text-transform:uppercase; letter-spacing:.08em; margin-bottom:10px; }
    .q-text { font-size:16px; font-weight:600; line-height:1.65; margin-bottom:20px; color:var(--ink); }
    .q-marks { font-size:12px; color:var(--ink-soft); margin-bottom:16px; }
    /* ── Answer options ── */
    .option-label {
      display:flex; align-items:center; gap:12px; padding:13px 16px;
      border:2px solid var(--line); border-radius:var(--radius-sm);
      cursor:pointer; margin-bottom:10px; transition:all .2s; font-size:14px; font-weight:500;
      background:var(--surface);
    }
    .option-label:hover { border-color:var(--primary); background:var(--primary-soft); }
    .option-label input[type=radio] { display:none; }
    .option-label.selected { border-color:var(--primary); background:var(--primary-soft); }
    .opt-letter {
      width:30px; height:30px; border-radius:50%; background:var(--bg);
      border:1.5px solid var(--line); display:flex; align-items:center; justify-content:center;
      font-size:12px; font-weight:700; color:var(--ink-soft); flex-shrink:0; transition:all .2s;
    }
    .option-label.selected .opt-letter { background:var(--primary); border-color:var(--primary); color:#fff; }
    /* Short answer */
    .short-input {
      width:100%; padding:12px 14px; border:2px solid var(--line); border-radius:var(--radius-sm);
      font-family:inherit; font-size:14px; resize:vertical; min-height:80px; transition:border .2s;
    }
    .short-input:focus { outline:none; border-color:var(--primary); }
    /* Navigation buttons */
    .quiz-nav-btns { display:flex; gap:10px; flex-wrap:wrap; }
    /* Timer */
    .quiz-timer { font-size:18px; font-weight:800; font-variant-numeric:tabular-nums; }
    .quiz-timer.warning { color:#ffd080; animation:pulse 1s infinite; }
    .quiz-timer.critical { color:#ff8080; animation:pulse 0.5s infinite; }
    /* Progress */
    .q-progress { background:rgba(255,255,255,.2); border-radius:4px; height:4px; width:160px; }
    .q-progress-fill { background:#fff; height:100%; border-radius:4px; transition:width .3s; }
    /* Mobile */
    @media(max-width:768px) {
      .quiz-shell { grid-template-columns:1fr; }
      .quiz-nav-panel { display:none; }
      .quiz-content { padding:16px; }
    }
    @media print { .quiz-header,.quiz-nav-panel,.quiz-nav-btns { display:none; } }
  </style>
</head>
<body>

<!-- ── Header Bar ── -->
<div class="quiz-header">
  <div style="min-width:0;overflow:hidden">
    <h1><?= e($quiz['title']) ?></h1>
    <div style="font-size:11px;opacity:.7"><?= e($quiz['subject_name']) ?> &middot; <?= e($quiz['class_name']) ?></div>
  </div>
  <div style="display:flex;align-items:center;gap:16px;flex-shrink:0">
    <?php if ($timeSecs !== null): ?>
    <div style="text-align:center">
      <div style="font-size:10px;opacity:.7;margin-bottom:1px">Time Left</div>
      <div class="quiz-timer" id="timerDisplay">--:--</div>
    </div>
    <?php endif; ?>
    <div style="text-align:center">
      <div style="font-size:10px;opacity:.7;margin-bottom:2px">Progress</div>
      <div class="q-progress" style="width:120px"><div class="q-progress-fill" id="progressFill" style="width:<?= round(count(array_filter($savedResponses))/max($totalQ,1)*100) ?>%"></div></div>
      <div style="font-size:10px;opacity:.7;margin-top:2px" id="progressText"><?= count(array_filter($savedResponses)) ?>/<?= $totalQ ?> answered</div>
    </div>
    <button class="button button-ghost button-sm" onclick="confirmSubmit()" style="white-space:nowrap">
      Submit Quiz
    </button>
  </div>
</div>

<!-- ── Shell ── -->
<div class="quiz-shell">

  <!-- Left nav: question grid -->
  <div class="quiz-nav-panel">
    <h3>Questions</h3>
    <div class="q-grid" id="qGrid">
      <?php foreach ($questions as $i => $q):
        $qn = $i + 1;
        $ans = $savedResponses[$q['id']] ?? null;
        $cls = ($qn === $qNum) ? 'current' : ($ans !== null ? 'answered' : '');
      ?>
      <a href="?quiz_id=<?= $quizId ?>&q=<?= $qn ?>" class="q-btn <?= $cls ?>" data-qnum="<?= $qn ?>"
         title="Q<?= $qn ?>: <?= e(mb_substr($q['question'],0,50)) ?>"><?= $qn ?></a>
      <?php endforeach; ?>
    </div>

    <div style="font-size:11.5px;margin-top:6px;display:flex;flex-direction:column;gap:5px">
      <div style="display:flex;align-items:center;gap:6px"><span class="q-btn answered" style="width:20px;height:20px;font-size:9px;flex-shrink:0"></span> Answered</div>
      <div style="display:flex;align-items:center;gap:6px"><span class="q-btn" style="width:20px;height:20px;font-size:9px;flex-shrink:0"></span> Not answered</div>
      <div style="display:flex;align-items:center;gap:6px"><span class="q-btn current" style="width:20px;height:20px;font-size:9px;flex-shrink:0"></span> Current</div>
    </div>

    <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--line)">
      <div style="font-size:12px;color:var(--ink-soft);margin-bottom:6px">Answered: <strong id="answeredCount"><?= count(array_filter($savedResponses)) ?></strong> of <?= $totalQ ?></div>
      <div style="font-size:12px;color:var(--ink-soft)">Total marks: <strong><?= $totalMarks ?></strong></div>
    </div>

    <button onclick="confirmSubmit()" class="button button-primary" style="width:100%;margin-top:14px;text-align:center">
      ✓ Submit Quiz
    </button>
  </div>

  <!-- Main: current question -->
  <div class="quiz-content">
    <form id="quizForm" method="post" action="take_quiz_submit.php">
      <input type="hidden" name="quiz_id"    value="<?= $quizId ?>"/>
      <input type="hidden" name="attempt_id" value="<?= $attemptId ?>"/>
      <input type="hidden" name="action"     value="save_answer"/>
      <input type="hidden" name="q_num"      value="<?= $qNum ?>"/>
      <input type="hidden" name="question_id" value="<?= $currentQ['id'] ?>"/>
      <input type="hidden" name="next_q"     value="" id="nextQField"/>
      <input type="hidden" name="submit_final" value="0" id="submitFinalField"/>

      <!-- Question card -->
      <div class="q-card">
        <div class="q-number">Question <?= $qNum ?> of <?= $totalQ ?></div>
        <div class="q-text"><?= nl2br(e($currentQ['question'])) ?></div>
        <div class="q-marks"><?= $currentQ['marks'] ?> mark<?= $currentQ['marks']!=1?'s':'' ?></div>

        <?php if ($currentQ['type'] === 'mcq'): ?>
        <!-- MCQ Options -->
        <?php $letters=['A','B','C','D','E']; foreach ($opts as $idx => $opt): $letter=$letters[$idx]??chr(65+$idx); ?>
        <label class="option-label <?= $savedAns===$letter?'selected':'' ?>" id="opt-<?= $letter ?>">
          <input type="radio" name="answer" value="<?= $letter ?>"
                 <?= $savedAns===$letter?'checked':'' ?>
                 onchange="selectOption(this,'<?= $letter ?>')"/>
          <span class="opt-letter"><?= $letter ?></span>
          <span><?= e($opt) ?></span>
        </label>
        <?php endforeach; ?>

        <?php elseif ($currentQ['type'] === 'true_false'): ?>
        <!-- True/False -->
        <label class="option-label <?= $savedAns==='True'?'selected':'' ?>" id="opt-True">
          <input type="radio" name="answer" value="True" <?= $savedAns==='True'?'checked':'' ?>
                 onchange="selectOption(this,'True')"/>
          <span class="opt-letter">T</span><span>True</span>
        </label>
        <label class="option-label <?= $savedAns==='False'?'selected':'' ?>" id="opt-False">
          <input type="radio" name="answer" value="False" <?= $savedAns==='False'?'checked':'' ?>
                 onchange="selectOption(this,'False')"/>
          <span class="opt-letter">F</span><span>False</span>
        </label>

        <?php else: ?>
        <!-- Short answer -->
        <textarea name="answer" class="short-input" id="shortAnswer"
                  placeholder="Type your answer here…"
                  oninput="markShortChanged()"><?= e($savedAns??'') ?></textarea>
        <div style="font-size:12px;color:var(--ink-soft);margin-top:6px">💡 Type your answer clearly. It will be saved automatically.</div>
        <?php endif; ?>
      </div>

      <!-- Navigation buttons -->
      <div class="quiz-nav-btns">
        <?php if ($qNum > 1): ?>
        <button type="button" class="button button-secondary"
                onclick="navigate(<?= $qNum-1 ?>)">← Previous</button>
        <?php endif; ?>

        <?php if ($qNum < $totalQ): ?>
        <button type="button" class="button button-primary"
                onclick="navigate(<?= $qNum+1 ?>)" id="nextBtn">
          Next →
        </button>
        <?php else: ?>
        <button type="button" class="button button-primary"
                onclick="confirmSubmit()">
          ✓ Submit Quiz
        </button>
        <?php endif; ?>

        <?php if ($currentQ['type']==='short_answer'): ?>
        <button type="button" class="button button-secondary"
                onclick="saveShortAnswer()" id="saveShortBtn">
          💾 Save Answer
        </button>
        <?php endif; ?>
      </div>
    </form>

    <!-- Question info footer -->
    <div style="margin-top:20px;padding:14px;background:var(--bg2);border-radius:var(--radius-sm);font-size:12.5px;color:var(--ink-soft)">
      📌 Your answers are saved automatically when you move between questions.
      <?php if ($quiz['time_limit_mins']): ?>
      ⏱ You have <strong><?= $quiz['time_limit_mins'] ?> minutes</strong> to complete this quiz.
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Submit confirmation modal ── -->
<div id="submitModal" style="display:none;position:fixed;inset:0;background:rgba(26,26,31,.6);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:var(--surface);border-radius:var(--radius-lg);padding:28px;max-width:400px;width:100%;box-shadow:var(--shadow-lg)">
    <h3 style="font-weight:800;margin-bottom:8px">Submit Quiz?</h3>
    <p style="color:var(--ink-soft);margin-bottom:6px">You have answered <strong id="modalAnswered">0</strong> of <?= $totalQ ?> questions.</p>
    <p style="color:var(--ink-soft);margin-bottom:20px">Once submitted you cannot change your answers. Are you sure?</p>
    <div style="display:flex;gap:10px">
      <button class="button button-primary" onclick="doSubmit()" style="flex:1;text-align:center">✓ Yes, Submit</button>
      <button class="button button-secondary" onclick="closeModal()" style="flex:1;text-align:center">Cancel</button>
    </div>
  </div>
</div>

<script>
// ── State ──────────────────────────────────────────────────────
const quizId     = <?= $quizId ?>;
const attemptId  = <?= $attemptId ?>;
const totalQ     = <?= $totalQ ?>;
const timeSecs   = <?= $timeSecs ?? 'null' ?>;
const baseUrl    = '<?= BASE_URL ?>';
let   answered   = <?= json_encode(array_keys(array_filter($savedResponses, fn($v)=>$v!==null&&$v!==''))) ?>;
let   timerInt   = null;
let   timeLeft   = timeSecs;
let   shortChanged = false;

// ── Timer ──────────────────────────────────────────────────────
if (timeSecs !== null) {
  const disp = document.getElementById('timerDisplay');
  function tick() {
    if (timeLeft <= 0) { clearInterval(timerInt); doSubmit(); return; }
    const m = Math.floor(timeLeft/60), s = timeLeft%60;
    disp.textContent = String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
    disp.className = 'quiz-timer' + (timeLeft<=120?' warning':'') + (timeLeft<=30?' critical':'');
    timeLeft--;
  }
  tick();
  timerInt = setInterval(tick, 1000);
}

// ── MCQ / T-F selection ────────────────────────────────────────
function selectOption(radio, letter) {
  // Visually select
  document.querySelectorAll('.option-label').forEach(l=>l.classList.remove('selected'));
  const lbl = document.getElementById('opt-'+letter);
  if (lbl) lbl.classList.add('selected');
  // Auto-save via fetch
  saveAnswer(letter);
}

function saveAnswer(value) {
  const qId = document.querySelector('[name=question_id]').value;
  fetch(baseUrl+'/portal/student/take_quiz_submit.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
      action:'save_answer', quiz_id:quizId, attempt_id:attemptId,
      question_id:qId, answer:value, csrf_token: '<?= csrfToken() ?>'
    })
  })
  .then(r=>r.json())
  .then(d=>{
    if (d.ok) updateProgress(d.answered, d.total);
    // Update grid button
    const qn = parseInt(document.querySelector('[name=q_num]').value);
    const btn = document.querySelector('[data-qnum="'+qn+'"]');
    if (btn) { btn.classList.remove('current'); btn.classList.add('answered'); btn.classList.add('current'); }
  })
  .catch(()=>{});
}

// ── Short answer ───────────────────────────────────────────────
function markShortChanged() { shortChanged = true; }

function saveShortAnswer(nav) {
  const val = document.getElementById('shortAnswer')?.value?.trim();
  if (val !== undefined) saveAnswer(val);
  shortChanged = false;
}

// ── Progress ───────────────────────────────────────────────────
function updateProgress(answeredCount, total) {
  const pct = total>0 ? Math.round(answeredCount/total*100) : 0;
  const fill = document.getElementById('progressFill');
  const text = document.getElementById('progressText');
  const cnt  = document.getElementById('answeredCount');
  if (fill) fill.style.width = pct+'%';
  if (text) text.textContent = answeredCount+'/'+total+' answered';
  if (cnt)  cnt.textContent  = answeredCount;
  document.getElementById('modalAnswered').textContent = answeredCount;
}

// ── Navigation ─────────────────────────────────────────────────
function navigate(toQ) {
  if (shortChanged) saveShortAnswer();
  window.location.href = '?quiz_id='+quizId+'&q='+toQ;
}

// ── Submit ─────────────────────────────────────────────────────
function confirmSubmit() {
  const cnt = document.querySelectorAll('.q-btn.answered').length;
  document.getElementById('modalAnswered').textContent = cnt;
  document.getElementById('submitModal').style.display = 'flex';
}
function closeModal() { document.getElementById('submitModal').style.display = 'none'; }

function doSubmit() {
  clearInterval(timerInt);
  if (shortChanged) {
    const val = document.getElementById('shortAnswer')?.value?.trim();
    if (val) {
      const qId = document.querySelector('[name=question_id]').value;
      // Save synchronously before final submit
      const xhr = new XMLHttpRequest();
      xhr.open('POST', baseUrl+'/portal/student/take_quiz_submit.php', false);
      xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
      xhr.send(new URLSearchParams({action:'save_answer',quiz_id:quizId,attempt_id:attemptId,question_id:qId,answer:val,csrf_token:'<?= csrfToken() ?>'}));
    }
  }
  // Final submit
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = baseUrl+'/portal/student/take_quiz_submit.php';
  [['action','submit_final'],['quiz_id',quizId],['attempt_id',attemptId],['csrf_token','<?= csrfToken() ?>']].forEach(([n,v])=>{
    const i=document.createElement('input');i.type='hidden';i.name=n;i.value=v;form.appendChild(i);
  });
  document.body.appendChild(form);
  form.submit();
}

// ── Keyboard nav ──────────────────────────────────────────────
document.addEventListener('keydown', e=>{
  if (e.target.tagName==='TEXTAREA'||e.target.tagName==='INPUT') return;
  if (e.key==='ArrowRight'||e.key==='ArrowDown') {
    const qn = parseInt(document.querySelector('[name=q_num]').value);
    if (qn<totalQ) navigate(qn+1);
  }
  if (e.key==='ArrowLeft'||e.key==='ArrowUp') {
    const qn = parseInt(document.querySelector('[name=q_num]').value);
    if (qn>1) navigate(qn-1);
  }
});

// ── Warn on leave ─────────────────────────────────────────────
window.addEventListener('beforeunload', e => {
  e.preventDefault(); e.returnValue='';
});
</script>
</body>
</html>
