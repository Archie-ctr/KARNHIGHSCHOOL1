<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['teacher','class_teacher']);

$activePage = 'assessments';
$pdo = db(); $user = currentUser(); $ayId = currentAcademicYearId(); $ay = currentAcademicYearName();

$teacherRow = $pdo->prepare("SELECT * FROM teachers WHERE user_id=? LIMIT 1");
$teacherRow->execute([$user['id']]); $teacher = $teacherRow->fetch();
if (!$teacher) { redirect(BASE_URL.'/portal/teacher/'); }
$teacherId = $teacher['id'];

// ── Ensure table ──────────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS teacher_assessments (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        teacher_id      INT UNSIGNED NOT NULL,
        class_id        INT UNSIGNED NOT NULL,
        subject_id      INT UNSIGNED NULL,
        academic_year_id INT UNSIGNED NOT NULL,
        title           VARCHAR(200) NOT NULL,
        assessment_type ENUM('classwork','homework','test','quiz','project','other') NOT NULL DEFAULT 'classwork',
        description     TEXT         NULL,
        max_score       DECIMAL(8,2) NOT NULL DEFAULT 100,
        due_date        DATE         NULL,
        is_graded       TINYINT(1)   NOT NULL DEFAULT 0,
        created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ta_teacher (teacher_id),
        INDEX idx_ta_class   (class_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS assessment_submissions (
        id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        assessment_id  INT UNSIGNED NOT NULL,
        student_id     INT UNSIGNED NOT NULL,
        score          DECIMAL(8,2) NULL,
        feedback       TEXT         NULL,
        graded_at      DATETIME     NULL,
        UNIQUE KEY uq_sub (assessment_id, student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

// ── POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $clsId   = (int)($_POST['class_id']   ?? 0);
        $subId   = (int)($_POST['subject_id'] ?? 0) ?: null;
        $title   = trim($_POST['title']       ?? '');
        $type    = $_POST['assessment_type']  ?? 'classwork';
        $desc    = trim($_POST['description'] ?? '');
        $max     = (float)($_POST['max_score'] ?? 100);
        $due     = $_POST['due_date']          ?? null;
        if ($clsId && $title) {
            $pdo->prepare(
                "INSERT INTO teacher_assessments (teacher_id,class_id,subject_id,academic_year_id,title,assessment_type,description,max_score,due_date)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            )->execute([$teacherId,$clsId,$subId,$ayId,$title,$type,$desc?:null,$max,$due?:null]);
            flash('success','Assessment created: '.$title);
        }

    } elseif ($action === 'grade') {
        $asmId   = (int)($_POST['assessment_id'] ?? 0);
        $scores  = $_POST['scores']    ?? [];
        $feedback= $_POST['feedback']  ?? [];
        foreach ($scores as $sid => $score) {
            $sid   = (int)$sid;
            $score = $score==='' ? null : (float)$score;
            $fb    = trim($feedback[$sid] ?? '') ?: null;
            $pdo->prepare(
                "INSERT INTO assessment_submissions (assessment_id,student_id,score,feedback,graded_at)
                 VALUES (?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE score=VALUES(score),feedback=VALUES(feedback),graded_at=NOW()"
            )->execute([$asmId,$sid,$score,$fb]);
        }
        $pdo->prepare("UPDATE teacher_assessments SET is_graded=1 WHERE id=?")->execute([$asmId]);
        flash('success','Grades saved.');

    } elseif ($action === 'delete') {
        $id = (int)($_POST['assessment_id'] ?? 0);
        $pdo->prepare("DELETE FROM teacher_assessments WHERE id=? AND teacher_id=?")->execute([$id,$teacherId]);
        flash('success','Assessment deleted.');
    }
    redirect(BASE_URL.'/portal/teacher/assessments.php?'.http_build_query(array_filter(['class_id'=>$_POST['class_id']??'','view_id'=>$_POST['view_id']??''])));
}

// ── Data ──────────────────────────────────────────────────────
$selClass = (int)($_GET['class_id'] ?? 0);
$viewId   = (int)($_GET['view_id']  ?? 0);

$myClasses  = $pdo->prepare("SELECT DISTINCT c.id,c.name FROM teacher_assignments ta JOIN classes c ON c.id=ta.class_id WHERE ta.teacher_id=? AND ta.academic_year_id=? ORDER BY c.name"); $myClasses->execute([$teacherId,$ayId]); $myClasses=$myClasses->fetchAll();
if (!$selClass && count($myClasses)===1) $selClass=$myClasses[0]['id'];
$mySubjects = $pdo->prepare("SELECT DISTINCT s.id,s.name FROM teacher_assignments ta JOIN subjects s ON s.id=ta.subject_id WHERE ta.teacher_id=? AND ta.academic_year_id=? ORDER BY s.name"); $mySubjects->execute([$teacherId,$ayId]); $mySubjects=$mySubjects->fetchAll();

$asmWhere = "WHERE ta.teacher_id=$teacherId AND ta.academic_year_id=$ayId";
if ($selClass) $asmWhere .= " AND ta.class_id=$selClass";
$assessments = $pdo->query(
    "SELECT ta.*, c.name class_name, s.name subject_name,
            (SELECT COUNT(*) FROM assessment_submissions asub WHERE asub.assessment_id=ta.id) graded_count
     FROM teacher_assessments ta
     LEFT JOIN classes c  ON c.id  = ta.class_id
     LEFT JOIN subjects s ON s.id  = ta.subject_id
     $asmWhere ORDER BY ta.created_at DESC"
)->fetchAll();

// Grading view
$gradingData = null; $studentsForGrading = [];
if ($viewId) {
    $gradingData = $pdo->query("SELECT ta.*,c.name class_name FROM teacher_assessments ta LEFT JOIN classes c ON c.id=ta.class_id WHERE ta.id=$viewId AND ta.teacher_id=$teacherId")->fetch();
    if ($gradingData) {
        $studentsForGrading = $pdo->prepare(
            "SELECT s.id,s.student_id,s.first_name,s.last_name,
                    asub.score,asub.feedback
             FROM students s
             LEFT JOIN assessment_submissions asub ON asub.assessment_id=? AND asub.student_id=s.id
             WHERE s.current_class_id=? AND s.status='Active' ORDER BY s.last_name"
        );
        $studentsForGrading->execute([$viewId,$gradingData['class_id']]);
        $studentsForGrading = $studentsForGrading->fetchAll();
    }
}

$typeIcons =['classwork'=>'✏️','homework'=>'🏠','test'=>'📋','quiz'=>'🧠','project'=>'🔬','other'=>'📎'];
$typeColors=['classwork'=>'var(--primary)','homework'=>'var(--green)','test'=>'var(--warning)','quiz'=>'#e64980','project'=>'var(--error)','other'=>'var(--ink-soft)'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Assessments — Teacher Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <div class="page-heading">
    <div><h1>Assessments</h1><p>Classwork, homework, tests and grading</p></div>
    <?php if (!$viewId): ?>
    <button class="button button-primary" onclick="document.getElementById('createModal').style.display='flex'">+ Create Assessment</button>
    <?php else: ?>
    <a href="<?= BASE_URL ?>/portal/teacher/assessments.php?class_id=<?= $selClass ?>" class="button button-secondary">← Back to List</a>
    <?php endif; ?>
  </div>

  <?php if ($viewId && $gradingData): ?>
  <!-- ── GRADING VIEW ────────────────────────────────────── -->
  <div class="panel" style="padding:18px;margin-bottom:20px;background:var(--bg2)">
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <span style="font-size:1.5rem"><?= $typeIcons[$gradingData['assessment_type']]??'📋' ?></span>
      <div>
        <h3 style="font-weight:700"><?= e($gradingData['title']) ?></h3>
        <span style="font-size:12px;color:var(--ink-soft)"><?= e($gradingData['class_name']) ?> · Max: <?= $gradingData['max_score'] ?> points
          <?= $gradingData['due_date']?' · Due: '.date('M d, Y',strtotime($gradingData['due_date'])):'' ?>
        </span>
      </div>
    </div>
  </div>
  <form method="post">
    <?= csrfField() ?><input type="hidden" name="action" value="grade"/>
    <input type="hidden" name="assessment_id" value="<?= $viewId ?>"/>
    <input type="hidden" name="class_id" value="<?= $selClass ?>"/>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Student</th><th>Score (max <?= $gradingData['max_score'] ?>)</th><th>Feedback</th></tr></thead>
        <tbody>
          <?php foreach ($studentsForGrading as $s): ?>
          <tr>
            <td><strong><?= e($s['first_name'].' '.$s['last_name']) ?></strong><div style="font-size:11px;color:var(--ink-faint)"><?= e($s['student_id']) ?></div></td>
            <td style="width:120px"><input type="number" name="scores[<?= $s['id'] ?>]" value="<?= $s['score']??'' ?>" min="0" max="<?= $gradingData['max_score'] ?>" step="0.5" style="width:100%;padding:8px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit"/></td>
            <td><input type="text" name="feedback[<?= $s['id'] ?>]" value="<?= e($s['feedback']??'') ?>" placeholder="Optional feedback" style="width:100%;padding:8px;border:1.5px solid var(--line);border-radius:var(--radius-sm);font-family:inherit"/></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="display:flex;justify-content:flex-end;margin-top:14px">
      <button type="submit" class="button button-primary">💾 Save Grades</button>
    </div>
  </form>

  <?php else: ?>
  <!-- ── ASSESSMENT LIST ────────────────────────────────── -->
  <div class="filter-row" style="margin-bottom:14px">
    <form method="get" style="display:flex;gap:8px;flex-wrap:wrap">
      <select name="class_id" class="filter-button" onchange="this.form.submit()">
        <option value="">All classes</option>
        <?php foreach ($myClasses as $c): ?><option value="<?= $c['id'] ?>" <?= $selClass==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </form>
  </div>

  <?php if (empty($assessments)): ?>
  <div style="text-align:center;padding:56px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
    <div style="font-size:40px;margin-bottom:12px">📝</div>
    <h3 style="margin-bottom:6px">No assessments yet</h3>
    <p style="color:var(--ink-soft)">Create your first classwork, homework or test.</p>
  </div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:10px">
    <?php foreach ($assessments as $a): $ico=$typeIcons[$a['assessment_type']]??'📋'; $col=$typeColors[$a['assessment_type']]??'var(--primary)'; ?>
    <div class="panel" style="padding:16px 20px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
      <span style="font-size:1.3rem;flex-shrink:0"><?= $ico ?></span>
      <div style="flex:1">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
          <h3 style="font-weight:700;font-size:14px"><?= e($a['title']) ?></h3>
          <span style="font-size:11px;font-weight:700;color:<?= $col ?>"><?= ucfirst($a['assessment_type']) ?></span>
        </div>
        <div style="font-size:12px;color:var(--ink-soft);margin-top:2px;display:flex;gap:12px;flex-wrap:wrap">
          <span>🏫 <?= e($a['class_name']) ?></span>
          <?php if($a['subject_name']):?><span>📚 <?= e($a['subject_name']) ?></span><?php endif; ?>
          <span>📊 Max: <?= $a['max_score'] ?></span>
          <?php if($a['due_date']):?><span>📅 Due: <?= date('M d, Y',strtotime($a['due_date'])) ?></span><?php endif; ?>
          <span><?= $a['is_graded'] ? '<span style="color:var(--green)">✓ Graded ('.$a['graded_count'].' students)</span>' : '<span style="color:var(--ink-faint)">Not graded yet</span>' ?></span>
        </div>
      </div>
      <div style="display:flex;gap:6px;flex-shrink:0">
        <a href="?class_id=<?= $selClass ?>&view_id=<?= $a['id'] ?>" class="filter-button button-sm">📋 Grade</a>
        <form method="post" onsubmit="return confirm('Delete?')" style="display:inline">
          <?= csrfField() ?><input type="hidden" name="action" value="delete"/>
          <input type="hidden" name="assessment_id" value="<?= $a['id'] ?>"/>
          <input type="hidden" name="class_id" value="<?= $selClass ?>"/>
          <button type="submit" class="filter-button button-sm" style="color:var(--error)">🗑️</button>
        </form>
      </div>
    </div>
    <?php endforeach; endif; endif; ?>

</div>
</div>

<!-- Create Modal -->
<div id="createModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:var(--radius-lg);max-width:480px;width:100%;padding:28px;box-shadow:var(--shadow-lg)">
    <h3 style="margin-bottom:18px">Create Assessment</h3>
    <form method="post">
      <?= csrfField() ?><input type="hidden" name="action" value="create"/>
      <input type="hidden" name="class_id" value="<?= $selClass ?>"/>
      <div class="form-group"><label>Title *<input name="title" required placeholder="e.g. Chapter 2 Test"/></label></div>
      <div class="form-row">
        <div class="form-group"><label>Type<select name="assessment_type"><?php foreach($typeIcons as $k=>$ico):?><option value="<?=$k?>"><?=$ico?> <?=ucfirst($k)?></option><?php endforeach;?></select></label></div>
        <div class="form-group"><label>Max Score<input type="number" name="max_score" value="100" min="1" step="0.5"/></label></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Class *<select name="class_id" required><?php foreach($myClasses as $c):?><option value="<?=$c['id']?>" <?=$selClass==$c['id']?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select></label></div>
        <div class="form-group"><label>Subject<select name="subject_id"><option value="">—</option><?php foreach($mySubjects as $s):?><option value="<?=$s['id']?>"><?=e($s['name'])?></option><?php endforeach;?></select></label></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Due Date<input type="date" name="due_date"/></label></div>
      </div>
      <div class="form-group"><label>Description<textarea name="description" rows="2" placeholder="Optional"></textarea></label></div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:14px">
        <button type="button" onclick="document.getElementById('createModal').style.display='none'" class="button button-secondary">Cancel</button>
        <button type="submit" class="button button-primary">Create</button>
      </div>
    </form>
  </div>
</div>
<script>document.getElementById('createModal').addEventListener('click',function(e){if(e.target===this)this.style.display='none'});</script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body></html>
