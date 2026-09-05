<?php
$pageTitle   = 'Promotion';
$activeAdmin = 'promotion';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['principal','academic_dean','super_admin','registrar']);

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();

// ── Process bulk promotion ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='promote') {
    verifyCsrf();
    $gradeId = (int)($_POST['grade_id']??0);
    $nextGradeId = (int)($_POST['next_grade_id']??0);
    $decisions   = $_POST['decision']??[];

    $promoted=0; $repeated=0; $graduated=0;
    foreach ($decisions as $stdId => $dec) {
        $stdId = (int)$stdId;
        $toGrade = ($dec==='Promoted' && $nextGradeId) ? $nextGradeId : null;
        $pdo->prepare("INSERT INTO promotion_records (student_id,academic_year_id,from_grade_id,to_grade_id,status,processed_by,processed_at) VALUES (?,?,?,?,?,?,NOW())")
           ->execute([$stdId,$ayId,$gradeId,$toGrade,$dec,currentUser()['id']]);
        if ($dec==='Promoted' && $toGrade)   { $pdo->prepare("UPDATE students SET current_grade_id=?,status='Active',updated_at=NOW() WHERE id=?")->execute([$toGrade,$stdId]); $promoted++; }
        elseif ($dec==='Repeating')          { $pdo->prepare("UPDATE students SET status='Active',updated_at=NOW() WHERE id=?")->execute([$stdId]); $repeated++; }
        elseif ($dec==='Graduated')          { $pdo->prepare("UPDATE students SET status='Graduated',graduation_date=CURDATE(),updated_at=NOW() WHERE id=?")->execute([$stdId]); $graduated++; }
        elseif (in_array($dec,['Transferred','Withdrawn'],true)) { $pdo->prepare("UPDATE students SET status=?,updated_at=NOW() WHERE id=?")->execute([$dec,$stdId]); }
    }
    auditLog('bulk_promotion','promotion','grade',$gradeId,'','Promoted:'.$promoted.' Repeated:'.$repeated.' Graduated:'.$graduated);
    flash('success',"Promotion complete — Promoted: $promoted, Repeating: $repeated, Graduated: $graduated.");
    redirect(BASE_URL.'/admin/promotion.php');
}

$grades = $pdo->query("SELECT id,name,sequence FROM grades WHERE is_active=1 ORDER BY sequence")->fetchAll();
$selGrade = (int)($_GET['grade_id']??0);
$students = []; $existing = [];

if ($selGrade) {
    $sts=$pdo->prepare("SELECT s.*,g.name grade_name,ROUND(AVG(asc2.marks_obtained),1) avg_mark FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id LEFT JOIN assessment_scores asc2 ON asc2.student_id=s.id AND asc2.academic_year_id=? AND asc2.status='approved' WHERE s.current_grade_id=? AND s.status='Active' GROUP BY s.id ORDER BY s.last_name,s.first_name");
    $sts->execute([$ayId,$selGrade]); $students=$sts->fetchAll();
    $ex=$pdo->prepare("SELECT student_id,status FROM promotion_records WHERE academic_year_id=? AND from_grade_id=?");
    $ex->execute([$ayId,$selGrade]); $existing=array_column($ex->fetchAll(),null,'student_id');
}

// Next grade
$nextGrade = null;
if ($selGrade) {
    $curSeq = (int)($pdo->prepare("SELECT sequence FROM grades WHERE id=?")->execute([$selGrade]) ? $pdo->query("SELECT sequence FROM grades WHERE id=$selGrade")->fetchColumn() : 0);
    $nextGrade = $pdo->prepare("SELECT id,name FROM grades WHERE sequence=? AND is_active=1 LIMIT 1")->execute([$curSeq+1]) ? $pdo->query("SELECT id,name FROM grades WHERE sequence=".($curSeq+1)." AND is_active=1 LIMIT 1")->fetch() : null;
}
$isGrade12 = $selGrade && (int)$pdo->query("SELECT sequence FROM grades WHERE id=$selGrade")->fetchColumn() >= 13;
?>

<div class="page-heading">
  <div><div class="eyebrow">End of Year <span></span></div><h1>Promotion Management</h1><p>Review and process student promotion decisions for <?= e($ay) ?>.</p></div>
</div>

<div class="alert alert-warning alert-sticky">⚠️ Promotion is permanent. Please review results carefully before processing. Always take a backup before bulk operations.</div>

<form method="get" class="filter-row" style="margin-bottom:24px">
  <select name="grade_id" class="filter-button" onchange="this.form.submit()" style="min-width:180px">
    <option value="">Select Grade to promote…</option>
    <?php foreach($grades as $g): ?><option value="<?= $g['id'] ?>" <?= $selGrade==$g['id']?'selected':'' ?>><?= e($g['name']) ?></option><?php endforeach; ?>
  </select>
</form>

<?php if ($selGrade && !empty($students)): ?>
<div class="form-section">
  <div class="form-section-title">
    <?= e($pdo->query("SELECT name FROM grades WHERE id=$selGrade")->fetchColumn()) ?> — <?= count($students) ?> students
    <?php if ($nextGrade): ?><span class="status new-s" style="margin-left:8px">→ <?= e($nextGrade['name']) ?></span><?php endif; ?>
  </div>
  <form method="post">
    <?= csrfField() ?>
    <input type="hidden" name="action"        value="promote"/>
    <input type="hidden" name="grade_id"      value="<?= $selGrade ?>"/>
    <input type="hidden" name="next_grade_id" value="<?= $nextGrade['id']??0 ?>"/>

    <div class="table-wrap" style="margin-bottom:16px">
      <table>
        <thead><tr><th>#</th><th>Student</th><th>Student ID</th><th>Avg Mark</th><th>Previous Decision</th><th>Decision</th></tr></thead>
        <tbody>
          <?php foreach ($students as $i => $st):
            $prevDec = $existing[$st['id']]['status'] ?? null;
            $locked  = $prevDec !== null;
            $passMark = (float)setting('passing_grade','70');
            $defaultDec = $prevDec ?? (($st['avg_mark']??0) >= $passMark ? 'Promoted' : 'Repeating');
            if ($isGrade12) $defaultDec = $prevDec ?? 'Graduated';
          ?>
          <tr>
            <td class="muted"><?= $i+1 ?></td>
            <td><strong><?= e($st['first_name'].' '.$st['last_name']) ?></strong></td>
            <td class="muted"><?= e($st['student_id']) ?></td>
            <td><?= $st['avg_mark'] ?? '—' ?></td>
            <td><?= $prevDec ? statusBadge($prevDec) : '<span class="muted">Not set</span>' ?></td>
            <td>
              <?php if ($locked): ?>
                <?= statusBadge($prevDec) ?>
                <input type="hidden" name="decision[<?= $st['id'] ?>]" value="<?= e($prevDec) ?>"/>
              <?php else: ?>
                <select name="decision[<?= $st['id'] ?>]" style="padding:6px 10px;border:1px solid var(--line);border-radius:6px;font-size:13px;background:var(--bg)">
                  <?php $opts = $isGrade12 ? ['Graduated','Repeating','Withdrawn'] : ['Promoted','Repeating','Not Promoted','Transferred','Withdrawn']; ?>
                  <?php foreach($opts as $opt): ?><option value="<?= $opt ?>" <?= $defaultDec===$opt?'selected':'' ?>><?= $opt ?></option><?php endforeach; ?>
                </select>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if (!array_filter(array_keys($existing), fn($k) => isset($existing[$k]))): ?>
    <div style="display:flex;justify-content:flex-end;gap:10px">
      <button type="submit" class="button button-danger" onclick="return confirm('Are you sure? This will update all student records. This cannot be undone.')">⬆️ Process Promotion</button>
    </div>
    <?php else: ?>
    <div class="alert alert-info">Promotion has already been processed for this grade this year.</div>
    <?php endif; ?>
  </form>
</div>
<?php elseif ($selGrade): ?>
<div style="text-align:center;padding:40px;color:var(--ink-faint)">No active students in this grade.</div>
<?php endif; ?>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
