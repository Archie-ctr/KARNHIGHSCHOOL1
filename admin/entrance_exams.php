<?php
$pageTitle   = 'Entrance Exams';
$activeAdmin = 'entrance_exams';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['principal','registrar','academic_dean','super_admin']);

$pdo  = db();
$ayId = currentAcademicYearId();

// ── Handle actions ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action = $_POST['action']??'';

    if ($action==='create_exam') {
        $pdo->prepare("INSERT INTO entrance_exams (title,grade_id,academic_year_id,duration_minutes,total_questions,passing_score,start_datetime,end_datetime,randomize_q,randomize_a,show_result,allowed_attempts,security_level,is_online,status,instructions,created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$_POST['title'],$_POST['grade_id'],$ayId,$_POST['duration'],$_POST['total_q'],$_POST['pass_score'],$_POST['start']??null,$_POST['end']??null,$_POST['randomize_q']??0,$_POST['randomize_a']??0,$_POST['show_result']??'immediate',$_POST['attempts']??1,$_POST['security']??'standard',$_POST['is_online']??1,'draft',trim($_POST['instructions']??'')?:null,currentUser()['id']]);
        flash('success','Entrance exam created.');
    } elseif ($action==='activate') {
        $id=(int)($_POST['exam_id']??0);
        $pdo->prepare("UPDATE entrance_exams SET status='active' WHERE id=?")->execute([$id]);
        flash('success','Exam activated.');
    } elseif ($action==='close') {
        $id=(int)($_POST['exam_id']??0);
        $pdo->prepare("UPDATE entrance_exams SET status='closed' WHERE id=?")->execute([$id]);
        flash('success','Exam closed.');
    } elseif ($action==='add_question') {
        $examId=(int)($_POST['exam_id']??0);
        $pdo->prepare("INSERT INTO entrance_questions (exam_id,subject_id,grade_id,question,q_type,difficulty,marks,correct_answer,explanation,is_active)
            VALUES (?,?,?,?,?,?,?,?,?,1)")
           ->execute([$examId,$_POST['subject_id']??null,null,$_POST['question'],$_POST['q_type']??'mcq',$_POST['difficulty']??'medium',$_POST['marks']??1,$_POST['correct_answer']??null,trim($_POST['explanation']??'')?:null]);
        $qId=(int)$pdo->lastInsertId();
        // MCQ options
        if (($_POST['q_type']??'mcq')==='mcq') {
            foreach (['a','b','c','d'] as $seq => $key) {
                $opt = trim($_POST['option_'.$key]??'');
                if ($opt) {
                    $isCorrect = ($_POST['correct_option']??'')===$key ? 1:0;
                    $pdo->prepare("INSERT INTO entrance_question_options (question_id,option_text,is_correct,sequence) VALUES (?,?,?,?)")->execute([$qId,$opt,$isCorrect,$seq+1]);
                }
            }
        }
        flash('success','Question added.');
    }
    redirect(BASE_URL.'/admin/entrance_exams.php'.($_POST['exam_id']?'?exam_id='.(int)$_POST['exam_id']:''));
}

$examId   = (int)($_GET['exam_id']??0);
$exams    = $pdo->prepare("SELECT e.*,g.name grade_name,(SELECT COUNT(*) FROM entrance_questions WHERE exam_id=e.id) qcount,(SELECT COUNT(*) FROM entrance_exam_attempts WHERE exam_id=e.id) attempts FROM entrance_exams e JOIN grades g ON g.id=e.grade_id WHERE e.academic_year_id=? ORDER BY e.created_at DESC");
$exams->execute([$ayId]); $exams=$exams->fetchAll();

$selExam  = $examId ? $pdo->query("SELECT e.*,g.name grade_name FROM entrance_exams e JOIN grades g ON g.id=e.grade_id WHERE e.id=$examId")->fetch() : null;
$questions = $examId ? $pdo->query("SELECT q.*,s.name subj_name FROM entrance_questions q LEFT JOIN subjects s ON s.id=q.subject_id WHERE q.exam_id=$examId ORDER BY q.id")->fetchAll() : [];
$attempts  = $examId ? $pdo->query("SELECT a.*,app.first_name,app.last_name,app.application_number FROM entrance_exam_attempts a JOIN applications app ON app.id=a.application_id WHERE a.exam_id=$examId ORDER BY a.submitted_at DESC LIMIT 50")->fetchAll() : [];

$grades   = $pdo->query("SELECT id,name FROM grades WHERE is_active=1 ORDER BY sequence")->fetchAll();
$subjects = $pdo->query("SELECT id,name FROM subjects WHERE is_active=1 ORDER BY name")->fetchAll();
?>

<div class="page-heading">
  <div><div class="eyebrow">Admissions <span></span></div><h1>Entrance Examinations</h1></div>
  <button class="button button-primary" onclick="document.getElementById('createExamModal').style.display='flex'">+ Create Exam</button>
</div>

<!-- Exams list -->
<?php if (!$selExam): ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Title</th><th>Grade</th><th>Duration</th><th>Questions</th><th>Pass Score</th><th>Attempts</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php if (empty($exams)): ?>
      <tr><td colspan="8" style="text-align:center;padding:36px;color:var(--ink-faint)">No exams created yet.</td></tr>
      <?php else: ?>
      <?php foreach ($exams as $ex): ?>
      <tr>
        <td><strong><?= e($ex['title']) ?></strong></td>
        <td><?= e($ex['grade_name']) ?></td>
        <td><?= $ex['duration_minutes'] ?> min</td>
        <td><?= $ex['qcount'] ?>/<?= $ex['total_questions'] ?></td>
        <td><?= $ex['passing_score'] ?>%</td>
        <td><?= $ex['attempts'] ?></td>
        <td><?= statusBadge($ex['status']) ?></td>
        <td>
          <div style="display:flex;gap:5px">
            <a href="?exam_id=<?= $ex['id'] ?>" class="filter-button button-sm">Manage</a>
            <?php if ($ex['status']==='draft'): ?>
            <form method="post" style="display:inline"><?= csrfField() ?><input type="hidden" name="action" value="activate"/><input type="hidden" name="exam_id" value="<?= $ex['id'] ?>"/><button type="submit" class="filter-button button-sm" style="color:var(--green)">Activate</button></form>
            <?php elseif ($ex['status']==='active'): ?>
            <form method="post" style="display:inline"><?= csrfField() ?><input type="hidden" name="action" value="close"/><input type="hidden" name="exam_id" value="<?= $ex['id'] ?>"/><button type="submit" class="filter-button button-sm" style="color:var(--warning)">Close</button></form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php else: ?>
<!-- Exam detail view -->
<div style="margin-bottom:16px"><a href="<?= BASE_URL ?>/admin/entrance_exams.php" class="text-link">← All Exams</a></div>

<div class="form-section">
  <div class="form-section-title" style="display:flex;justify-content:space-between">
    <span><?= e($selExam['title']) ?> — <?= e($selExam['grade_name']) ?></span>
    <?= statusBadge($selExam['status']) ?>
  </div>
  <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;font-size:13px">
    <div><span style="color:var(--ink-faint)">Duration</span><br><strong><?= $selExam['duration_minutes'] ?> minutes</strong></div>
    <div><span style="color:var(--ink-faint)">Questions</span><br><strong><?= count($questions) ?>/<?= $selExam['total_questions'] ?></strong></div>
    <div><span style="color:var(--ink-faint)">Pass Score</span><br><strong><?= $selExam['passing_score'] ?>%</strong></div>
    <div><span style="color:var(--ink-faint)">Security</span><br><strong><?= ucfirst($selExam['security_level']) ?></strong></div>
  </div>
</div>

<!-- Tab navigation -->
<div class="tab-bar tab-container" style="margin-bottom:0">
  <button class="tab-btn active" onclick="showTab('tab-q')">Questions (<?= count($questions) ?>)</button>
  <button class="tab-btn" onclick="showTab('tab-results')">Results (<?= count($attempts) ?>)</button>
</div>

<!-- Questions tab -->
<div id="tab-q" class="tab-panel active" style="margin-top:16px">
  <div style="display:flex;justify-content:flex-end;margin-bottom:12px">
    <button class="button button-primary button-sm" onclick="document.getElementById('addQModal').style.display='flex'">+ Add Question</button>
  </div>
  <?php if (empty($questions)): ?>
  <div style="text-align:center;padding:32px;color:var(--ink-faint);background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">No questions yet. Add questions to this exam.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Question</th><th>Subject</th><th>Type</th><th>Marks</th><th>Difficulty</th></tr></thead>
      <tbody>
        <?php foreach ($questions as $i => $q): ?>
        <tr>
          <td class="muted"><?= $i+1 ?></td>
          <td style="max-width:420px"><?= e(mb_substr($q['question'],0,100)).(mb_strlen($q['question'])>100?'…':'') ?></td>
          <td class="muted"><?= e($q['subj_name']??'General') ?></td>
          <td><span class="status new-s"><?= e(strtoupper($q['q_type'])) ?></span></td>
          <td><?= $q['marks'] ?></td>
          <td><?= e(ucfirst($q['difficulty'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Results tab -->
<div id="tab-results" class="tab-panel" style="margin-top:16px">
  <?php if (empty($attempts)): ?>
  <div style="text-align:center;padding:32px;color:var(--ink-faint);background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">No attempts yet.</div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Applicant</th><th>App Number</th><th>Score</th><th>Percentage</th><th>Result</th><th>Submitted</th></tr></thead>
      <tbody>
        <?php foreach ($attempts as $att): ?>
        <tr>
          <td><strong><?= e($att['first_name'].' '.$att['last_name']) ?></strong></td>
          <td class="muted"><?= e($att['application_number']) ?></td>
          <td><?= $att['score']!==null ? fmtMark($att['score']).'/'.$att['max_score'] : '—' ?></td>
          <td><?= $att['percentage']!==null ? fmtPct($att['percentage']) : '—' ?></td>
          <td><?= $att['passed']===null ? statusBadge('pending') : ($att['passed'] ? statusBadge('approved') : statusBadge('warning')) ?></td>
          <td class="muted"><?= $att['submitted_at'] ? date('M d, Y H:i',strtotime($att['submitted_at'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Add Question Modal -->
<div id="addQModal" style="display:none;position:fixed;inset:0;background:rgba(26,26,31,.5);z-index:200;align-items:center;justify-content:center;padding:20px;overflow-y:auto">
  <div style="background:var(--surface);border-radius:var(--radius-lg);width:100%;max-width:580px;box-shadow:var(--shadow-lg);padding:28px;max-height:90vh;overflow-y:auto">
    <h3 style="margin-bottom:18px">Add Question</h3>
    <form method="post">
      <?= csrfField() ?>
      <input type="hidden" name="action"  value="add_question"/>
      <input type="hidden" name="exam_id" value="<?= $examId ?>"/>
      <div class="form-row full"><div class="form-group"><label>Question *<textarea name="question" required rows="3" style="resize:vertical"></textarea></label></div></div>
      <div class="form-row">
        <div class="form-group"><label>Type<select name="q_type" id="qTypeSelect" onchange="toggleOptions(this.value)"><option value="mcq">MCQ</option><option value="truefalse">True/False</option><option value="short">Short Answer</option></select></label></div>
        <div class="form-group"><label>Subject<select name="subject_id"><option value="">General</option><?php foreach($subjects as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></label></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Marks<input type="number" name="marks" value="1" min="0.5" step="0.5"/></label></div>
        <div class="form-group"><label>Difficulty<select name="difficulty"><option value="easy">Easy</option><option value="medium" selected>Medium</option><option value="hard">Hard</option></select></label></div>
      </div>
      <div id="mcqOptions">
        <div class="form-row full"><div class="form-group"><label>Correct option<select name="correct_option"><option value="a">A</option><option value="b">B</option><option value="c">C</option><option value="d">D</option></select></label></div></div>
        <?php foreach(['a'=>'Option A','b'=>'Option B','c'=>'Option C','d'=>'Option D'] as $k=>$l): ?>
        <div class="form-row full"><div class="form-group"><label><?= $l ?><input name="option_<?= $k ?>" placeholder="<?= $l ?>…"/></label></div></div>
        <?php endforeach; ?>
      </div>
      <div id="shortAnswer" style="display:none">
        <div class="form-row full"><div class="form-group"><label>Correct Answer / Keywords<input name="correct_answer" placeholder="Expected answer…"/></label></div></div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px">
        <button type="button" class="button button-secondary" onclick="document.getElementById('addQModal').style.display='none'">Cancel</button>
        <button type="submit" class="button button-primary">Add Question →</button>
      </div>
    </form>
  </div>
</div>

<script>
function showTab(id) {
  document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  event.target.classList.add('active');
}
function toggleOptions(type) {
  document.getElementById('mcqOptions').style.display = (type==='mcq'||type==='truefalse') ? 'block':'none';
  document.getElementById('shortAnswer').style.display = (type==='short') ? 'block':'none';
}
</script>
<?php endif; ?>

<!-- Create Exam Modal -->
<div id="createExamModal" style="display:none;position:fixed;inset:0;background:rgba(26,26,31,.5);z-index:200;align-items:center;justify-content:center;padding:20px;overflow-y:auto">
  <div style="background:var(--surface);border-radius:var(--radius-lg);width:100%;max-width:560px;box-shadow:var(--shadow-lg);padding:28px;max-height:90vh;overflow-y:auto">
    <h3 style="margin-bottom:18px">Create Entrance Exam</h3>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="create_exam"/>
      <div class="form-row full"><div class="form-group"><label>Exam Title *<input name="title" required placeholder="e.g. 2026/2027 Grade 10 Entrance"/></label></div></div>
      <div class="form-row">
        <div class="form-group"><label>Grade *<select name="grade_id" required><option value="">Select…</option><?php foreach($grades as $g): ?><option value="<?= $g['id'] ?>"><?= e($g['name']) ?></option><?php endforeach; ?></select></label></div>
        <div class="form-group"><label>Duration (minutes)<input type="number" name="duration" value="60" min="10"/></label></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Total Questions<input type="number" name="total_q" value="60" min="1"/></label></div>
        <div class="form-group"><label>Pass Score (%)<input type="number" name="pass_score" value="70" min="0" max="100"/></label></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Start Date/Time<input type="datetime-local" name="start"/></label></div>
        <div class="form-group"><label>End Date/Time<input type="datetime-local" name="end"/></label></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Randomize Questions<select name="randomize_q"><option value="1">Yes</option><option value="0">No</option></select></label></div>
        <div class="form-group"><label>Randomize Answers<select name="randomize_a"><option value="1">Yes</option><option value="0">No</option></select></label></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Show Result<select name="show_result"><option value="immediate">Immediately</option><option value="manual">After Review</option></select></label></div>
        <div class="form-group"><label>Security Level<select name="security"><option value="standard">Standard</option><option value="basic">Basic</option><option value="strict">Strict</option></select></label></div>
      </div>
      <div class="form-row full"><div class="form-group"><label>Instructions<textarea name="instructions" rows="3" placeholder="Instructions for candidates…"></textarea></label></div></div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:16px">
        <button type="button" class="button button-secondary" onclick="document.getElementById('createExamModal').style.display='none'">Cancel</button>
        <button type="submit" class="button button-primary">Create Exam →</button>
      </div>
    </form>
  </div>
</div>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
