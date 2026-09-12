<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['teacher','class_teacher']);

$activePage = 'quizzes';
$pdo = db(); $user = currentUser(); $ayId = currentAcademicYearId(); $ay = currentAcademicYearName();

include __DIR__.'/includes/resolve_teacher.php';

// ── Ensure tables ─────────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS teacher_quizzes (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        teacher_id      INT UNSIGNED NOT NULL,
        class_id        INT UNSIGNED NOT NULL,
        subject_id      INT UNSIGNED NULL,
        academic_year_id INT UNSIGNED NOT NULL,
        title           VARCHAR(200) NOT NULL,
        description     TEXT         NULL,
        time_limit_mins INT          NULL,
        max_attempts    INT          NOT NULL DEFAULT 1,
        is_published    TINYINT(1)   NOT NULL DEFAULT 0,
        start_date      DATETIME     NULL,
        end_date        DATETIME     NULL,
        created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tq_teacher (teacher_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_questions (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        quiz_id     INT UNSIGNED NOT NULL,
        question    TEXT         NOT NULL,
        type        ENUM('mcq','true_false','short_answer') NOT NULL DEFAULT 'mcq',
        options     JSON         NULL,
        answer      TEXT         NOT NULL,
        marks       DECIMAL(5,2) NOT NULL DEFAULT 1,
        sequence    INT          NOT NULL DEFAULT 1,
        INDEX idx_qq_quiz (quiz_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS quiz_attempts (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        quiz_id     INT UNSIGNED NOT NULL,
        student_id  INT UNSIGNED NOT NULL,
        responses   JSON         NULL,
        score       DECIMAL(8,2) NULL,
        total_marks DECIMAL(8,2) NULL,
        submitted_at DATETIME    NULL,
        attempt_no  INT          NOT NULL DEFAULT 1,
        auto_graded TINYINT(1)   NOT NULL DEFAULT 0,
        INDEX idx_qa_quiz    (quiz_id),
        INDEX idx_qa_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

// ── POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create_quiz') {
        $clsId = (int)($_POST['class_id']   ?? 0);
        $subId = (int)($_POST['subject_id'] ?? 0) ?: null;
        $title = trim($_POST['title']       ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $time  = (int)($_POST['time_limit'] ?? 0) ?: null;
        $att   = max(1,(int)($_POST['max_attempts']??1));
        $start = $_POST['start_date'] ? $_POST['start_date'].' '.($_POST['start_time']??'00:00').':00' : null;
        $end   = $_POST['end_date']   ? $_POST['end_date'].  ' '.($_POST['end_time']  ??'23:59').':00' : null;
        if ($clsId && $title) {
            $pdo->prepare(
                "INSERT INTO teacher_quizzes (teacher_id,class_id,subject_id,academic_year_id,title,description,time_limit_mins,max_attempts,start_date,end_date)
                 VALUES (?,?,?,?,?,?,?,?,?,?)"
            )->execute([$teacherId,$clsId,$subId,$ayId,$title,$desc?:null,$time,$att,$start,$end]);
            flash('success','Quiz created. Now add questions.');
            redirect(BASE_URL.'/portal/teacher/quizzes.php?quiz_id='.(int)$pdo->lastInsertId());
        }

    } elseif ($action === 'add_question') {
        $qid  = (int)($_POST['quiz_id']  ?? 0);
        $qTxt = trim($_POST['question']  ?? '');
        $type = $_POST['q_type']         ?? 'mcq';
        $ans  = trim($_POST['answer']    ?? '');
        $marks= (float)($_POST['marks']  ?? 1);
        $opts = null;
        if ($type === 'mcq') {
            $opts = array_filter(array_map('trim', [
                $_POST['opt_a']??'', $_POST['opt_b']??'', $_POST['opt_c']??'', $_POST['opt_d']??''
            ]));
            $opts = json_encode(array_values($opts));
        } elseif ($type === 'true_false') {
            $opts = json_encode(['True','False']);
        }
        if ($qid && $qTxt && $ans) {
            $seq = (int)$pdo->query("SELECT COALESCE(MAX(sequence),0)+1 FROM quiz_questions WHERE quiz_id=$qid")->fetchColumn();
            $pdo->prepare(
                "INSERT INTO quiz_questions (quiz_id,question,type,options,answer,marks,sequence) VALUES (?,?,?,?,?,?,?)"
            )->execute([$qid,$qTxt,$type,$opts,$ans,$marks,$seq]);
            flash('success','Question added.');
        }
        redirect(BASE_URL.'/portal/teacher/quizzes.php?quiz_id='.($_POST['quiz_id']??''));

    } elseif ($action === 'delete_question') {
        $id = (int)($_POST['question_id']??0);
        $pdo->prepare("DELETE FROM quiz_questions WHERE id=?")->execute([$id]);
        redirect(BASE_URL.'/portal/teacher/quizzes.php?quiz_id='.($_POST['quiz_id']??''));

    } elseif ($action === 'toggle_publish') {
        $qid = (int)($_POST['quiz_id']??0);
        $pdo->prepare("UPDATE teacher_quizzes SET is_published=NOT is_published WHERE id=? AND teacher_id=?")->execute([$qid,$teacherId]);
        redirect(BASE_URL.'/portal/teacher/quizzes.php?quiz_id='.$qid);

    } elseif ($action === 'delete_quiz') {
        $qid = (int)($_POST['quiz_id']??0);
        $pdo->prepare("DELETE FROM quiz_questions WHERE quiz_id=?")->execute([$qid]);
        $pdo->prepare("DELETE FROM teacher_quizzes WHERE id=? AND teacher_id=?")->execute([$qid,$teacherId]);
        flash('success','Quiz deleted.');
        redirect(BASE_URL.'/portal/teacher/quizzes.php');
    }
}

// ── Data ──────────────────────────────────────────────────────
$quizId = (int)($_GET['quiz_id'] ?? 0);
$tab    = $_GET['tab'] ?? 'questions';

$myClasses  = $pdo->prepare("SELECT DISTINCT c.id,c.name FROM teacher_assignments ta JOIN classes c ON c.id=ta.class_id WHERE ta.teacher_id=? AND ta.academic_year_id=? ORDER BY c.name"); $myClasses->execute([$teacherId,$ayId]); $myClasses=$myClasses->fetchAll();
$mySubjects = $pdo->prepare("SELECT DISTINCT s.id,s.name FROM teacher_assignments ta JOIN subjects s ON s.id=ta.subject_id WHERE ta.teacher_id=? AND ta.academic_year_id=? ORDER BY s.name"); $mySubjects->execute([$teacherId,$ayId]); $mySubjects=$mySubjects->fetchAll();

$quizzes = $pdo->query(
    "SELECT tq.*, c.name class_name, s.name subject_name,
            (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id=tq.id) q_count,
            (SELECT COUNT(DISTINCT student_id) FROM quiz_attempts WHERE quiz_id=tq.id) attempts
     FROM teacher_quizzes tq LEFT JOIN classes c ON c.id=tq.class_id LEFT JOIN subjects s ON s.id=tq.subject_id
     WHERE tq.teacher_id=$teacherId AND tq.academic_year_id=$ayId ORDER BY tq.created_at DESC"
)->fetchAll();

$currentQuiz  = null; $questions = []; $attempts = [];
if ($quizId) {
    $currentQuiz = $pdo->query("SELECT tq.*,c.name class_name FROM teacher_quizzes tq LEFT JOIN classes c ON c.id=tq.class_id WHERE tq.id=$quizId AND tq.teacher_id=$teacherId")->fetch();
    if ($currentQuiz) {
        $questions = $pdo->query("SELECT * FROM quiz_questions WHERE quiz_id=$quizId ORDER BY sequence")->fetchAll();
        $attempts  = $pdo->query(
            "SELECT qa.*, CONCAT(s.first_name,' ',s.last_name) sname, s.student_id sid
             FROM quiz_attempts qa JOIN students s ON s.id=qa.student_id
             WHERE qa.quiz_id=$quizId ORDER BY qa.submitted_at DESC LIMIT 40"
        )->fetchAll();
    }
}

$totalMarks = count($questions) > 0 ? array_sum(array_column($questions,'marks')) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Quizzes — Teacher Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <?php if ($currentQuiz): ?>
  <!-- ── QUIZ DETAIL VIEW ────────────────────────────────── -->
  <div class="page-heading">
    <div>
      <a href="<?= BASE_URL ?>/portal/teacher/quizzes.php" style="font-size:13px;color:var(--ink-soft);display:block;margin-bottom:6px">← All Quizzes</a>
      <h1><?= e($currentQuiz['title']) ?></h1>
      <p><?= e($currentQuiz['class_name']) ?> · <?= count($questions) ?> questions · <?= $totalMarks ?> marks total</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <form method="post" style="display:inline">
        <?= csrfField() ?><input type="hidden" name="action" value="toggle_publish"/><input type="hidden" name="quiz_id" value="<?= $quizId ?>"/>
        <button type="submit" class="button <?= $currentQuiz['is_published']?'button-secondary':'button-primary' ?>">
          <?= $currentQuiz['is_published'] ? '🔒 Unpublish' : '🌐 Publish' ?>
        </button>
      </form>
      <form method="post" onsubmit="return confirm('Delete this quiz and all questions?')" style="display:inline">
        <?= csrfField() ?><input type="hidden" name="action" value="delete_quiz"/><input type="hidden" name="quiz_id" value="<?= $quizId ?>"/>
        <button type="submit" class="button" style="background:var(--error);color:#fff">🗑️ Delete</button>
      </form>
    </div>
  </div>

  <!-- Quiz info card -->
  <div class="panel" style="padding:16px;margin-bottom:16px;background:var(--bg2);display:flex;gap:16px;flex-wrap:wrap">
    <?php foreach ([
      ['Status', $currentQuiz['is_published']?'<span class="status approved">Published</span>':'<span class="status pending">Draft</span>'],
      ['Time Limit', $currentQuiz['time_limit_mins']?$currentQuiz['time_limit_mins'].' min':'Unlimited'],
      ['Max Attempts', $currentQuiz['max_attempts']],
      ['Start', $currentQuiz['start_date']?date('M d H:i',strtotime($currentQuiz['start_date'])):'—'],
      ['End',   $currentQuiz['end_date']  ?date('M d H:i',strtotime($currentQuiz['end_date']))  :'—'],
      ['Attempts', count($attempts).' student'.count($attempts)!=1?'s':''],
    ] as [$k,$v]): ?>
    <div style="font-size:12px"><span style="color:var(--ink-soft);display:block"><?= $k ?></span><strong><?= $v ?></strong></div>
    <?php endforeach; ?>
  </div>

  <div class="tab-bar" style="margin-bottom:14px">
    <a href="?quiz_id=<?= $quizId ?>&tab=questions" class="tab-btn <?= $tab==='questions'?'active':'' ?>">❓ Questions (<?= count($questions) ?>)</a>
    <a href="?quiz_id=<?= $quizId ?>&tab=attempts"  class="tab-btn <?= $tab==='attempts' ?'active':'' ?>">📊 Attempts (<?= count($attempts) ?>)</a>
    <a href="?quiz_id=<?= $quizId ?>&tab=add"       class="tab-btn <?= $tab==='add'       ?'active':'' ?>">➕ Add Question</a>
  </div>

  <?php if ($tab === 'questions'): ?>
  <?php if (empty($questions)): ?>
  <div style="text-align:center;padding:40px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
    <p style="color:var(--ink-soft)">No questions yet. Use "Add Question" to build your quiz.</p>
  </div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:8px">
    <?php foreach ($questions as $i => $q):
      $opts = $q['options'] ? json_decode($q['options'],true) : null;
      $typeIcons2 = ['mcq'=>'🔘','true_false'=>'✓/✗','short_answer'=>'📝'];
    ?>
    <div class="panel" style="padding:16px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
        <div style="flex:1">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
            <span style="font-size:11px;font-weight:700;background:var(--primary);color:#fff;padding:2px 8px;border-radius:20px">Q<?= $i+1 ?></span>
            <span style="font-size:11px;color:var(--ink-soft)"><?= $typeIcons2[$q['type']]??'•' ?> <?= ucfirst(str_replace('_',' ',$q['type'])) ?></span>
            <span style="font-size:11px;color:var(--ink-soft)"><?= $q['marks'] ?> mark<?= $q['marks']!=1?'s':'' ?></span>
          </div>
          <p style="font-size:13.5px;font-weight:600;margin-bottom:6px"><?= e($q['question']) ?></p>
          <?php if ($opts): ?>
          <div style="display:flex;flex-direction:column;gap:3px">
            <?php foreach ($opts as $optIdx => $opt):
              $letter = chr(65+$optIdx);
              $isCorrect = strtolower(trim($opt)) === strtolower(trim($q['answer'])) || strtolower($letter) === strtolower(trim($q['answer']));
            ?>
            <span style="font-size:12.5px;<?= $isCorrect?'color:var(--green);font-weight:700':'' ?>">
              <?= $letter ?>. <?= e($opt) ?><?= $isCorrect?' ✓':'' ?>
            </span>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <span style="font-size:12px;color:var(--green)">✓ Answer: <?= e($q['answer']) ?></span>
          <?php endif; ?>
        </div>
        <form method="post" onsubmit="return confirm('Delete question?')">
          <?= csrfField() ?><input type="hidden" name="action" value="delete_question"/>
          <input type="hidden" name="question_id" value="<?= $q['id'] ?>"/>
          <input type="hidden" name="quiz_id" value="<?= $quizId ?>"/>
          <button type="submit" class="filter-button button-sm" style="color:var(--error)">🗑️</button>
        </form>
      </div>
    </div>
    <?php endforeach; endif; ?>

  <?php elseif ($tab === 'attempts'): ?>
  <?php if (empty($attempts)): ?>
  <div style="text-align:center;padding:40px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
    <p style="color:var(--ink-soft)">No attempts yet. <?= $currentQuiz['is_published']?'Students can take the quiz.':'Publish the quiz for students.' ?></p>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Student</th><th>Score</th><th>%</th><th>Attempt #</th><th>Submitted</th></tr></thead>
      <tbody>
        <?php foreach ($attempts as $a): $pct = $a['total_marks']>0?round($a['score']/$a['total_marks']*100,1):null; ?>
        <tr>
          <td><strong><?= e($a['sname']) ?></strong><div style="font-size:11px;color:var(--ink-faint)"><?= e($a['sid']) ?></div></td>
          <td><strong><?= $a['score']??'—' ?> / <?= $a['total_marks']??$totalMarks ?></strong></td>
          <td style="color:<?= $pct!==null?($pct>=50?'var(--green)':'var(--error)'):'inherit' ?>"><?= $pct!==null?$pct.'%':'—' ?></td>
          <td class="muted"><?= $a['attempt_no'] ?></td>
          <td class="muted"><?= $a['submitted_at']?date('M d, Y H:i',strtotime($a['submitted_at'])):'In progress' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php elseif ($tab === 'add'): ?>
  <!-- Add Question Form -->
  <div class="panel" style="padding:24px;max-width:560px">
    <h3 style="font-weight:700;margin-bottom:16px">Add Question</h3>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="add_question"/>
      <input type="hidden" name="quiz_id" value="<?= $quizId ?>"/>
      <div class="form-group"><label>Question Text *<textarea name="question" required rows="2" placeholder="Type your question here…"></textarea></label></div>
      <div class="form-row">
        <div class="form-group"><label>Type<select name="q_type" id="qType" onchange="toggleQType(this.value)">
          <option value="mcq">Multiple Choice</option>
          <option value="true_false">True / False</option>
          <option value="short_answer">Short Answer</option>
        </select></label></div>
        <div class="form-group"><label>Marks<input type="number" name="marks" value="1" min="0.5" step="0.5"/></label></div>
      </div>
      <!-- MCQ options -->
      <div id="mcqOpts">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px">
          <div class="form-group"><label>Option A<input name="opt_a" placeholder="Option A"/></label></div>
          <div class="form-group"><label>Option B<input name="opt_b" placeholder="Option B"/></label></div>
          <div class="form-group"><label>Option C<input name="opt_c" placeholder="Option C"/></label></div>
          <div class="form-group"><label>Option D<input name="opt_d" placeholder="Option D"/></label></div>
        </div>
        <div class="form-group"><label>Correct Answer (A, B, C, or D)<input name="answer" placeholder="e.g. A" maxlength="1"/></label></div>
      </div>
      <!-- T/F answer -->
      <div id="tfOpts" style="display:none">
        <div class="form-group"><label>Correct Answer<select name="answer" id="tfAns"><option value="True">True</option><option value="False">False</option></select></label></div>
      </div>
      <!-- Short answer -->
      <div id="saOpts" style="display:none">
        <div class="form-group"><label>Expected Answer (keywords)<input name="answer" id="saAns" placeholder="e.g. photosynthesis"/></label></div>
      </div>
      <button type="submit" class="button button-primary" style="margin-top:4px">Add Question</button>
    </form>
  </div>
  <script>
  function toggleQType(t){
    document.getElementById('mcqOpts').style.display = t==='mcq' ? '' : 'none';
    document.getElementById('tfOpts').style.display  = t==='true_false' ? '' : 'none';
    document.getElementById('saOpts').style.display  = t==='short_answer' ? '' : 'none';
    // Reset answer fields
    document.querySelector('#mcqOpts input[name="answer"]').required = t==='mcq';
    document.getElementById('tfAns').name = t==='true_false' ? 'answer' : '_';
    document.getElementById('saAns').name = t==='short_answer'? 'answer' : '_';
  }
  </script>
  <?php endif; ?>

  <?php else: ?>
  <!-- ── QUIZ LIST VIEW ──────────────────────────────────── -->
  <div class="page-heading">
    <div><h1>Online Quizzes</h1><p>Create, manage and track quizzes — <?= e($ay) ?></p></div>
    <button class="button button-primary" onclick="document.getElementById('createQuizModal').style.display='flex'">+ Create Quiz</button>
  </div>
  <?php if (empty($quizzes)): ?>
  <div style="text-align:center;padding:56px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
    <div style="font-size:40px;margin-bottom:12px">🧠</div>
    <h3 style="margin-bottom:6px">No quizzes yet</h3>
    <p style="color:var(--ink-soft)">Create your first quiz to assess your students online.</p>
  </div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:10px">
    <?php foreach ($quizzes as $q): ?>
    <div class="panel" style="padding:16px 20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
      <span style="font-size:1.4rem">🧠</span>
      <div style="flex:1">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          <h3 style="font-weight:700;font-size:14px"><?= e($q['title']) ?></h3>
          <span class="status <?= $q['is_published']?'approved':'pending' ?>" style="font-size:10px"><?= $q['is_published']?'Published':'Draft' ?></span>
        </div>
        <div style="font-size:12px;color:var(--ink-soft);display:flex;gap:12px;flex-wrap:wrap;margin-top:3px">
          <span>🏫 <?= e($q['class_name']) ?></span>
          <span>❓ <?= $q['q_count'] ?> question<?= $q['q_count']!=1?'s':'' ?></span>
          <?php if($q['time_limit_mins']):?><span>⏱️ <?= $q['time_limit_mins'] ?> min</span><?php endif; ?>
          <span>👩‍🎓 <?= $q['attempts'] ?> attempt<?= $q['attempts']!=1?'s':'' ?></span>
          <?php if($q['end_date'] && $q['end_date']<date('Y-m-d H:i:s')):?><span style="color:var(--error)">⏰ Expired</span><?php endif; ?>
        </div>
      </div>
      <a href="?quiz_id=<?= $q['id'] ?>" class="filter-button button-sm">Manage →</a>
    </div>
    <?php endforeach; endif; endif; ?>

</div>
</div>

<!-- Create Quiz Modal -->
<div id="createQuizModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:var(--radius-lg);max-width:520px;width:100%;padding:28px;box-shadow:var(--shadow-lg);max-height:90vh;overflow-y:auto">
    <h3 style="margin-bottom:18px">Create Quiz</h3>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="create_quiz"/>
      <div class="form-group"><label>Quiz Title *<input name="title" required placeholder="e.g. Chapter 4 Quiz"/></label></div>
      <div class="form-row">
        <div class="form-group"><label>Class *<select name="class_id" required><?php foreach($myClasses as $c):?><option value="<?=$c['id']?>"><?=e($c['name'])?></option><?php endforeach;?></select></label></div>
        <div class="form-group"><label>Subject<select name="subject_id"><option value="">—</option><?php foreach($mySubjects as $s):?><option value="<?=$s['id']?>"><?=e($s['name'])?></option><?php endforeach;?></select></label></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Time Limit (minutes)<input type="number" name="time_limit" placeholder="Leave blank = unlimited" min="1"/></label></div>
        <div class="form-group"><label>Max Attempts<input type="number" name="max_attempts" value="1" min="1" max="10"/></label></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Start Date<input type="date" name="start_date"/></label></div>
        <div class="form-group"><label>Start Time<input type="time" name="start_time" value="08:00"/></label></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>End Date<input type="date" name="end_date"/></label></div>
        <div class="form-group"><label>End Time<input type="time" name="end_time" value="23:59"/></label></div>
      </div>
      <div class="form-group"><label>Description<textarea name="description" rows="2" placeholder="Optional"/></textarea></label></div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:14px">
        <button type="button" onclick="document.getElementById('createQuizModal').style.display='none'" class="button button-secondary">Cancel</button>
        <button type="submit" class="button button-primary">Create Quiz</button>
      </div>
    </form>
  </div>
</div>
<script>
document.getElementById('createQuizModal').addEventListener('click',function(e){if(e.target===this)this.style.display='none'});
</script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body></html>
