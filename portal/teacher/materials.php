<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['teacher','class_teacher']);

$activePage = 'materials';
$pdo = db(); $user = currentUser(); $ayId = currentAcademicYearId(); $ay = currentAcademicYearName();

$teacherRow = $pdo->prepare("SELECT * FROM teachers WHERE user_id=? LIMIT 1");
$teacherRow->execute([$user['id']]); $teacher = $teacherRow->fetch();
if (!$teacher) { redirect(BASE_URL.'/portal/teacher/'); }
$teacherId = $teacher['id'];

// ── Ensure table ──────────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS learning_materials (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        teacher_id      INT UNSIGNED NOT NULL,
        class_id        INT UNSIGNED NULL,
        subject_id      INT UNSIGNED NULL,
        academic_year_id INT UNSIGNED NOT NULL,
        title           VARCHAR(200) NOT NULL,
        description     TEXT         NULL,
        material_type   ENUM('note','pdf','assignment','study_guide','other') NOT NULL DEFAULT 'note',
        file_path       VARCHAR(255) NULL,
        file_name       VARCHAR(255) NULL,
        file_size       INT          NULL,
        is_published    TINYINT(1)   NOT NULL DEFAULT 1,
        created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_lm_teacher (teacher_id),
        INDEX idx_lm_ay      (academic_year_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}

// ── POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $title   = trim($_POST['title'] ?? '');
        $desc    = trim($_POST['description'] ?? '');
        $type    = $_POST['material_type'] ?? 'note';
        $classId = (int)($_POST['class_id']   ?? 0) ?: null;
        $subId   = (int)($_POST['subject_id'] ?? 0) ?: null;
        $pub     = (int)($_POST['is_published'] ?? 1);

        $filePath = null; $fileName = null; $fileSize = null;
        if (!empty($_FILES['material_file']['name']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK) {
            $path = uploadFile($_FILES['material_file'], 'materials/'.$teacherId, ['pdf','doc','docx','ppt','pptx','txt','jpg','jpeg','png','zip'], 20);
            if ($path) { $filePath=$path; $fileName=$_FILES['material_file']['name']; $fileSize=$_FILES['material_file']['size']; }
        }
        if ($title) {
            $pdo->prepare(
                "INSERT INTO learning_materials (teacher_id,class_id,subject_id,academic_year_id,title,description,material_type,file_path,file_name,file_size,is_published)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)"
            )->execute([$teacherId,$classId,$subId,$ayId,$title,$desc?:null,$type,$filePath,$fileName,$fileSize,$pub]);
            flash('success','Material uploaded: '.$title);
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['material_id'] ?? 0);
        $m  = $pdo->query("SELECT file_path FROM learning_materials WHERE id=$id AND teacher_id=$teacherId")->fetch();
        if ($m) {
            if ($m['file_path'] && file_exists(UPLOAD_DIR.'/'.$m['file_path'])) unlink(UPLOAD_DIR.'/'.$m['file_path']);
            $pdo->prepare("DELETE FROM learning_materials WHERE id=? AND teacher_id=?")->execute([$id,$teacherId]);
            flash('success','Material deleted.');
        }

    } elseif ($action === 'toggle') {
        $id = (int)($_POST['material_id'] ?? 0);
        $pdo->prepare("UPDATE learning_materials SET is_published=NOT is_published WHERE id=? AND teacher_id=?")->execute([$id,$teacherId]);
    }
    redirect(BASE_URL.'/portal/teacher/materials.php');
}

// ── Data ──────────────────────────────────────────────────────
$filterType  = trim($_GET['type']     ?? '');
$filterClass = (int)($_GET['class_id'] ?? 0);
$mWhere = "WHERE lm.teacher_id=$teacherId AND lm.academic_year_id=$ayId";
if ($filterType)  $mWhere .= " AND lm.material_type='".addslashes($filterType)."'";
if ($filterClass) $mWhere .= " AND lm.class_id=$filterClass";

$materials = $pdo->query(
    "SELECT lm.*, c.name class_name, s.name subject_name
     FROM learning_materials lm
     LEFT JOIN classes c  ON c.id  = lm.class_id
     LEFT JOIN subjects s ON s.id  = lm.subject_id
     $mWhere ORDER BY lm.created_at DESC"
)->fetchAll();

$myClasses  = $pdo->prepare("SELECT DISTINCT c.id,c.name FROM teacher_assignments ta JOIN classes c ON c.id=ta.class_id WHERE ta.teacher_id=? AND ta.academic_year_id=? ORDER BY c.name"); $myClasses->execute([$teacherId,$ayId]); $myClasses=$myClasses->fetchAll();
$mySubjects = $pdo->prepare("SELECT DISTINCT s.id,s.name FROM teacher_assignments ta JOIN subjects s ON s.id=ta.subject_id WHERE ta.teacher_id=? AND ta.academic_year_id=? ORDER BY s.name"); $mySubjects->execute([$teacherId,$ayId]); $mySubjects=$mySubjects->fetchAll();

$typeIcons = ['note'=>'📝','pdf'=>'📄','assignment'=>'📋','study_guide'=>'📚','other'=>'📎'];
$typeLabels= ['note'=>'Note','pdf'=>'PDF','assignment'=>'Assignment','study_guide'=>'Study Guide','other'=>'Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Learning Materials — Teacher Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <div class="page-heading">
    <div><h1>Learning Materials</h1><p>Upload and manage teaching resources for your classes</p></div>
    <button class="button button-primary" onclick="document.getElementById('uploadModal').style.display='flex'">+ Upload Material</button>
  </div>

  <!-- Filter -->
  <form method="get" class="filter-row" style="margin-bottom:16px">
    <select name="type" class="filter-button" onchange="this.form.submit()">
      <option value="">All types</option>
      <?php foreach ($typeLabels as $k => $l): ?><option value="<?= $k ?>" <?= $filterType===$k?'selected':'' ?>><?= $l ?></option><?php endforeach; ?>
    </select>
    <select name="class_id" class="filter-button" onchange="this.form.submit()">
      <option value="">All classes</option>
      <?php foreach ($myClasses as $c): ?><option value="<?= $c['id'] ?>" <?= $filterClass==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
    </select>
    <?php if ($filterType||$filterClass): ?><a href="<?= BASE_URL ?>/portal/teacher/materials.php" class="filter-button">Clear</a><?php endif; ?>
  </form>

  <!-- Materials grid -->
  <?php if (empty($materials)): ?>
  <div style="text-align:center;padding:56px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
    <div style="font-size:40px;margin-bottom:12px">📚</div>
    <h3 style="margin-bottom:6px">No materials yet</h3>
    <p style="color:var(--ink-soft)">Upload your first teaching resource using the button above.</p>
  </div>
  <?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px">
    <?php foreach ($materials as $m): $ico = $typeIcons[$m['material_type']]??'📎'; ?>
    <div class="panel" style="padding:18px;display:flex;flex-direction:column;gap:8px">
      <div style="display:flex;align-items:flex-start;gap:10px">
        <span style="font-size:1.5rem;flex-shrink:0"><?= $ico ?></span>
        <div style="flex:1;min-width:0">
          <h3 style="font-size:14px;font-weight:700;margin-bottom:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($m['title']) ?></h3>
          <span class="badge badge-grey"><?= $typeLabels[$m['material_type']]??$m['material_type'] ?></span>
        </div>
        <span class="status <?= $m['is_published']?'approved':'pending' ?>" style="font-size:10px;flex-shrink:0"><?= $m['is_published']?'Published':'Hidden' ?></span>
      </div>
      <?php if ($m['description']): ?><p style="font-size:12px;color:var(--ink-soft);line-height:1.5"><?= e(mb_substr($m['description'],0,80)) ?></p><?php endif; ?>
      <div style="font-size:11px;color:var(--ink-faint);display:flex;gap:10px;flex-wrap:wrap">
        <?php if($m['class_name']):?><span>🏫 <?= e($m['class_name']) ?></span><?php endif; ?>
        <?php if($m['subject_name']):?><span>📚 <?= e($m['subject_name']) ?></span><?php endif; ?>
        <span>📅 <?= date('M d, Y',strtotime($m['created_at'])) ?></span>
        <?php if($m['file_size']):?><span>📦 <?= round($m['file_size']/1024,1) ?> KB</span><?php endif; ?>
      </div>
      <div style="display:flex;gap:6px;margin-top:4px">
        <?php if ($m['file_path']): ?>
        <a href="<?= BASE_URL ?>/uploads/<?= e($m['file_path']) ?>" class="filter-button button-sm" target="_blank" download>📥 Download</a>
        <?php endif; ?>
        <form method="post" style="display:inline">
          <?= csrfField() ?><input type="hidden" name="action" value="toggle"/><input type="hidden" name="material_id" value="<?= $m['id'] ?>"/>
          <button type="submit" class="filter-button button-sm"><?= $m['is_published']?'Hide':'Publish' ?></button>
        </form>
        <form method="post" onsubmit="return confirm('Delete this material?')" style="display:inline">
          <?= csrfField() ?><input type="hidden" name="action" value="delete"/><input type="hidden" name="material_id" value="<?= $m['id'] ?>"/>
          <button type="submit" class="filter-button button-sm" style="color:var(--error)">🗑️</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>
</div>

<!-- Upload Modal -->
<div id="uploadModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:var(--radius-lg);max-width:500px;width:100%;padding:28px;box-shadow:var(--shadow-lg);max-height:90vh;overflow-y:auto">
    <h3 style="margin-bottom:18px">Upload Learning Material</h3>
    <form method="post" enctype="multipart/form-data">
      <?= csrfField() ?><input type="hidden" name="action" value="upload"/>
      <div class="form-group"><label>Title *<input name="title" required placeholder="e.g. Chapter 3 Notes"/></label></div>
      <div class="form-row">
        <div class="form-group"><label>Type<select name="material_type"><?php foreach($typeLabels as $k=>$l):?><option value="<?=$k?>"><?=$l?></option><?php endforeach;?></select></label></div>
        <div class="form-group"><label>Published<select name="is_published"><option value="1">Yes</option><option value="0">No (draft)</option></select></label></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Class<select name="class_id"><option value="">All classes</option><?php foreach($myClasses as $c):?><option value="<?=$c['id']?>"><?=e($c['name'])?></option><?php endforeach;?></select></label></div>
        <div class="form-group"><label>Subject<select name="subject_id"><option value="">All subjects</option><?php foreach($mySubjects as $s):?><option value="<?=$s['id']?>"><?=e($s['name'])?></option><?php endforeach;?></select></label></div>
      </div>
      <div class="form-group"><label>Description<textarea name="description" rows="2" placeholder="Optional description"></textarea></label></div>
      <div class="form-group"><label>File (PDF, DOC, PPT, Image, ZIP — max 20MB)<input type="file" name="material_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.zip"/></label></div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:14px">
        <button type="button" onclick="document.getElementById('uploadModal').style.display='none'" class="button button-secondary">Cancel</button>
        <button type="submit" class="button button-primary">Upload</button>
      </div>
    </form>
  </div>
</div>
<script>document.getElementById('uploadModal').addEventListener('click',function(e){if(e.target===this)this.style.display='none'});</script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body></html>
