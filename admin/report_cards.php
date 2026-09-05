<?php
$pageTitle   = 'Report Cards';
$activeAdmin = 'report_cards';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['sys_admin','school_admin','principal','vice_principal','registrar','teacher','class_teacher']);

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();

$canGenerate = can('generate_report_cards'); // vice_principal, principal, school_admin, sys_admin, registrar
$canComment  = can('generate_report_cards'); // same — teacher comments via teacher portal
$canPublish  = isVicePrincipal();            // vice_principal and above

// ── Generate / publish actions ────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verifyCsrf();
    $action = $_POST['action']??'';
    $stdId  = (int)($_POST['student_id']??0);
    $clsId  = (int)($_POST['class_id']??0);

    if ($action==='generate' && $stdId && $clsId) {
        // Compute yearly avg from approved assessment_scores
        $avgRow = $pdo->prepare("SELECT ROUND(AVG(marks_obtained/max_marks*100),2) avg_pct FROM assessment_scores WHERE student_id=? AND academic_year_id=? AND status='approved' AND marks_obtained IS NOT NULL");
        $avgRow->execute([$stdId,$ayId]); $avg = $avgRow->fetchColumn();
        // Attendance
        $att = $pdo->prepare("SELECT SUM(status='Present') present,SUM(status='Absent') absent,SUM(status='Late') tardy FROM attendance WHERE student_id=? AND academic_year_id=?");
        $att->execute([$stdId,$ayId]); $attRow=$att->fetch();
        $totalDays = ($attRow['present']??0)+($attRow['absent']??0)+($attRow['tardy']??0);
        $attPct = $totalDays>0 ? round(($attRow['present']/$totalDays)*100,1) : null;
        // Upsert report card
        $pdo->prepare("INSERT INTO report_cards (student_id,class_id,academic_year_id,days_present,days_absent,days_tardy,attendance_pct,yearly_average,status,generated_at,generated_by)
            VALUES (?,?,?,?,?,?,?,?,'generated',NOW(),?)
            ON DUPLICATE KEY UPDATE days_present=VALUES(days_present),days_absent=VALUES(days_absent),days_tardy=VALUES(days_tardy),attendance_pct=VALUES(attendance_pct),yearly_average=VALUES(yearly_average),status='generated',generated_at=NOW(),generated_by=VALUES(generated_by)")
           ->execute([$stdId,$clsId,$ayId,$attRow['present']??0,$attRow['absent']??0,$attRow['tardy']??0,$attPct,$avg,currentUser()['id']]);
        flash('success','Report card generated.');
    } elseif ($action==='bulk_generate' && $clsId) {
        $students=$pdo->prepare("SELECT id FROM students WHERE current_class_id=? AND status='Active'")->execute([$clsId]) ? $pdo->query("SELECT id FROM students WHERE current_class_id=$clsId AND status='Active'")->fetchAll(PDO::FETCH_COLUMN) : [];
        foreach ($students as $sid) {
            $avgRow=$pdo->prepare("SELECT ROUND(AVG(marks_obtained/max_marks*100),2) FROM assessment_scores WHERE student_id=? AND academic_year_id=? AND status='approved'")->execute([$sid,$ayId]) ? $pdo->query("SELECT ROUND(AVG(marks_obtained/max_marks*100),2) FROM assessment_scores WHERE student_id=$sid AND academic_year_id=$ayId AND status='approved'")->fetchColumn() : null;
            $pdo->prepare("INSERT INTO report_cards (student_id,class_id,academic_year_id,yearly_average,status,generated_at,generated_by) VALUES (?,?,?,?,'generated',NOW(),?) ON DUPLICATE KEY UPDATE yearly_average=VALUES(yearly_average),status='generated',generated_at=NOW(),generated_by=VALUES(generated_by)")->execute([$sid,$clsId,$ayId,$avgRow,currentUser()['id']]);
        }
        flash('success','Report cards generated for all students in class.');
    } elseif ($action==='save_comment') {
        $rcId = (int)($_POST['rc_id']??0);
        $pdo->prepare("UPDATE report_cards SET teacher_comment=?,principal_comment=?,conduct=?,promotion_status=? WHERE id=?")->execute([trim($_POST['teacher_comment']??''),trim($_POST['principal_comment']??''),$_POST['conduct']??'Good',$_POST['promotion_status']??'Pending',$rcId]);
        flash('success','Comments saved.');
    } elseif ($action==='publish') {
        $rcId=(int)($_POST['rc_id']??0);
        $pdo->prepare("UPDATE report_cards SET status='published',published_at=NOW() WHERE id=?")->execute([$rcId]);
        flash('success','Report card published.');
    }
    redirect(BASE_URL.'/admin/report_cards.php?'.http_build_query(array_filter(['class_id'=>$_POST['class_id']??''])));
}

$classes   = $pdo->prepare("SELECT id,name FROM classes WHERE academic_year_id=? ORDER BY name")->execute([$ayId]) ? $pdo->query("SELECT id,name FROM classes WHERE academic_year_id=$ayId ORDER BY name")->fetchAll() : [];
$selClass  = (int)($_GET['class_id']??0);
$selStd    = (int)($_GET['student_id']??0);
$students  = []; $rcData = null; $subjects = []; $scoreData = [];

if ($selClass) {
    $sts=$pdo->prepare("SELECT s.*,rc.id rc_id,rc.status rc_status,rc.yearly_average,rc.promotion_status,rc.teacher_comment,rc.principal_comment,rc.days_present,rc.days_absent,rc.days_tardy,rc.generated_at FROM students s LEFT JOIN report_cards rc ON rc.student_id=s.id AND rc.academic_year_id=? WHERE s.current_class_id=? AND s.status='Active' ORDER BY s.last_name,s.first_name");
    $sts->execute([$ayId,$selClass]); $students=$sts->fetchAll();
}
if ($selStd && $selClass) {
    $rc=$pdo->prepare("SELECT * FROM report_cards WHERE student_id=? AND academic_year_id=? LIMIT 1")->execute([$selStd,$ayId]) ? $pdo->query("SELECT * FROM report_cards WHERE student_id=$selStd AND academic_year_id=$ayId LIMIT 1")->fetch() : null;
    $rcData=$rc;
    // Get subjects and scores
    $subs=$pdo->prepare("SELECT DISTINCT s.id,s.name FROM assessment_scores asc2 JOIN subjects s ON s.id=asc2.subject_id WHERE asc2.student_id=? AND asc2.academic_year_id=? ORDER BY s.name");
    $subs->execute([$selStd,$ayId]); $subjects=$subs->fetchAll();
    foreach ($subjects as $sub) {
        $scores=$pdo->prepare("SELECT ac.name,ac.sequence,asc2.marks_obtained,asc2.max_marks FROM assessment_scores asc2 JOIN assessment_configs ac ON ac.id=asc2.assessment_config_id WHERE asc2.student_id=? AND asc2.subject_id=? AND asc2.academic_year_id=? ORDER BY ac.sequence");
        $scores->execute([$selStd,$sub['id'],$ayId]); $scoreData[$sub['id']]=$scores->fetchAll();
    }
}
?>

<div class="page-heading">
  <div><div class="eyebrow">Assessment <span></span></div><h1>Report Cards</h1><p><?= e($ay) ?></p></div>
  <?php if ($selClass && !$selStd): ?>
  <form method="post">
    <?= csrfField() ?><input type="hidden" name="action" value="bulk_generate"/><input type="hidden" name="class_id" value="<?= $selClass ?>"/>
    <button type="submit" class="button button-secondary" onclick="return confirm('Generate report cards for all active students in this class?')">⚡ Generate All</button>
  </form>
  <?php endif; ?>
</div>

<form method="get" class="filter-row" style="margin-bottom:20px">
  <select name="class_id" class="filter-button" onchange="this.form.submit()" style="min-width:180px">
    <option value="">Select Class…</option>
    <?php foreach($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $selClass==$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
  </select>
  <?php if ($selClass && !empty($students)): ?>
  <select name="student_id" class="filter-button" onchange="this.form.submit()" style="min-width:200px">
    <option value="">Select Student…</option>
    <?php foreach($students as $st): ?><option value="<?= $st['id'] ?>" <?= $selStd==$st['id']?'selected':'' ?>><?= e($st['first_name'].' '.$st['last_name']) ?></option><?php endforeach; ?>
  </select>
  <?php endif; ?>
</form>

<?php if ($selClass && !$selStd && !empty($students)): ?>
<!-- Class list -->
<div class="table-wrap">
  <table>
    <thead><tr><th>Student</th><th>Student ID</th><th>Yearly Avg</th><th>Days Present</th><th>RC Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($students as $st): ?>
      <tr>
        <td><strong><?= e($st['first_name'].' '.$st['last_name']) ?></strong></td>
        <td class="muted"><?= e($st['student_id']) ?></td>
        <td><?= $st['yearly_average'] ? fmtPct((float)$st['yearly_average']) : '—' ?></td>
        <td><?= $st['days_present'] ?? '—' ?></td>
        <td><?= $st['rc_status'] ? statusBadge($st['rc_status']) : '<span class="muted">Not generated</span>' ?></td>
        <td>
          <div style="display:flex;gap:5px">
            <a href="?class_id=<?= $selClass ?>&student_id=<?= $st['id'] ?>" class="filter-button button-sm">View</a>
            <form method="post" style="display:inline">
              <?= csrfField() ?><input type="hidden" name="action" value="generate"/><input type="hidden" name="student_id" value="<?= $st['id'] ?>"/><input type="hidden" name="class_id" value="<?= $selClass ?>"/>
              <button type="submit" class="filter-button button-sm"><?= $st['rc_status'] ? 'Regenerate':'Generate' ?></button>
            </form>
            <?php if ($st['rc_status']==='generated'): ?>
            <a href="<?= BASE_URL ?>/letters/report_card_pdf.php?student_id=<?= $st['id'] ?>&ay_id=<?= $ayId ?>" target="_blank" class="filter-button button-sm">📄 PDF</a>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php elseif ($selStd && $selClass): ?>
<!-- Individual report card view -->
<?php
$stdInfo = $pdo->query("SELECT s.*,g.name grade_name,c.name class_name FROM students s LEFT JOIN grades g ON g.id=s.current_grade_id LEFT JOIN classes c ON c.id=s.current_class_id WHERE s.id=$selStd")->fetch();
$schoolName = setting('school_name','KARN HIGH SCHOOL');
?>
<div style="margin-bottom:16px;display:flex;gap:10px;align-items:center">
  <a href="?class_id=<?= $selClass ?>" class="text-link">← Back to class</a>
  <?php if ($rcData): ?>
  <a href="<?= BASE_URL ?>/letters/report_card_pdf.php?student_id=<?= $selStd ?>&ay_id=<?= $ayId ?>" target="_blank" class="button button-secondary button-sm">📄 Download PDF</a>
  <button onclick="printSection('rcPreview')" class="button button-secondary button-sm">🖨️ Print</button>
  <?php endif; ?>
</div>

<?php if (!$rcData): ?>
<div class="alert alert-warning">Report card not generated yet. <form method="post" style="display:inline"><?= csrfField() ?><input type="hidden" name="action" value="generate"/><input type="hidden" name="student_id" value="<?= $selStd ?>"/><input type="hidden" name="class_id" value="<?= $selClass ?>"/><button type="submit" class="button button-primary button-sm">Generate Now</button></form></div>
<?php else: ?>

<!-- Comment / status form -->
<div class="form-section" style="margin-bottom:20px">
  <div class="form-section-title">Comments &amp; Status</div>
  <form method="post">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save_comment"/>
    <input type="hidden" name="rc_id" value="<?= $rcData['id'] ?>"/>
    <input type="hidden" name="class_id" value="<?= $selClass ?>"/>
    <div class="form-row">
      <div class="form-group"><label>Teacher Comment<textarea name="teacher_comment" rows="2"><?= e($rcData['teacher_comment']??'') ?></textarea></label></div>
      <div class="form-group"><label>Principal Comment<textarea name="principal_comment" rows="2"><?= e($rcData['principal_comment']??'') ?></textarea></label></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Conduct<select name="conduct"><option value="Excellent" <?= ($rcData['conduct']??'')==='Excellent'?'selected':'' ?>>Excellent</option><option value="Very Good" <?= ($rcData['conduct']??'')==='Very Good'?'selected':'' ?>>Very Good</option><option value="Good" <?= ($rcData['conduct']??'')=='Good'?'selected':'' ?>>Good</option><option value="Fair" <?= ($rcData['conduct']??'')=='Fair'?'selected':'' ?>>Fair</option><option value="Needs Improvement" <?= ($rcData['conduct']??'')==='Needs Improvement'?'selected':'' ?>>Needs Improvement</option></select></label></div>
      <div class="form-group"><label>Promotion Status<select name="promotion_status"><option value="Promoted" <?= ($rcData['promotion_status']??'')==='Promoted'?'selected':'' ?>>Promoted</option><option value="Not Promoted" <?= ($rcData['promotion_status']??'')==='Not Promoted'?'selected':'' ?>>Not Promoted</option><option value="Repeating" <?= ($rcData['promotion_status']??'')==='Repeating'?'selected':'' ?>>Repeating</option><option value="Graduated" <?= ($rcData['promotion_status']??'')==='Graduated'?'selected':'' ?>>Graduated</option><option value="Pending">Pending</option></select></label></div>
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end">
      <button type="submit" class="button button-secondary">Save Comments</button>
      <form method="post" style="display:inline"><?= csrfField() ?><input type="hidden" name="action" value="publish"/><input type="hidden" name="rc_id" value="<?= $rcData['id'] ?>"/><input type="hidden" name="class_id" value="<?= $selClass ?>"/><button type="submit" class="button button-primary" <?= $rcData['status']==='published'?'disabled':'' ?>>Publish Report Card</button></form>
    </div>
  </form>
</div>

<!-- PRINTABLE REPORT CARD -->
<div id="rcPreview" class="report-card-paper">
  <!-- Header -->
  <div class="rc-header">
    <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="KHS Logo" class="rc-logo"/>
    <div style="flex:1">
      <div class="rc-school-name"><?= e($schoolName) ?></div>
      <div class="rc-subtitle">Karnplay, Nimba County, Liberia &nbsp;|&nbsp; <?= e(setting('school_phone','+231 886 417 711')) ?></div>
      <div style="font-size:14px;font-weight:700;margin-top:6px;color:var(--ink)">STUDENT REPORT CARD — Academic Year: <?= e($ay) ?></div>
    </div>
  </div>

  <!-- Student info -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 20px;font-size:12px;margin-bottom:16px;padding:10px;background:var(--bg);border-radius:6px">
    <div><strong>Student Name:</strong> <?= e($stdInfo['first_name'].($stdInfo['middle_name']?' '.$stdInfo['middle_name']:'').' '.$stdInfo['last_name']) ?></div>
    <div><strong>Student ID:</strong> <?= e($stdInfo['student_id']) ?></div>
    <div><strong>Grade/Class:</strong> <?= e($stdInfo['grade_name']??'').' / '.e($stdInfo['class_name']??'') ?></div>
    <div><strong>Gender:</strong> <?= e($stdInfo['gender']??'') ?></div>
    <div><strong>Academic Year:</strong> <?= e($ay) ?></div>
  </div>

  <!-- Scores table -->
  <table class="rc-table">
    <thead>
      <tr>
        <th rowspan="2" style="text-align:left;min-width:120px">Subject</th>
        <th colspan="4">Semester 1</th>
        <th colspan="4">Semester 2</th>
        <th rowspan="2">Yearly Avg</th>
        <th rowspan="2">Grade</th>
      </tr>
      <tr>
        <th>1st P.</th><th>2nd P.</th><th>3rd P.</th><th>Sem.Exam</th>
        <th>4th P.</th><th>5th P.</th><th>6th P.</th><th>Sem.Exam</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($subjects as $sub):
        $cols = ['1st Period'=>'—','2nd Period'=>'—','3rd Period'=>'—','Semester 1 Examination'=>'—','4th Period'=>'—','5th Period'=>'—','6th Period'=>'—','Semester 2 Examination'=>'—'];
        $allVals = [];
        foreach ($scoreData[$sub['id']] as $sc) {
            foreach ($cols as $k=>$_) {
                if (stripos($sc['name'],$k)!==false || $sc['name']===$k) {
                    $pct = $sc['max_marks']>0 ? round($sc['marks_obtained']/$sc['max_marks']*100,1) : null;
                    $cols[$k] = $pct !== null ? $pct : '—';
                    if ($pct!==null) $allVals[]=$pct;
                }
            }
        }
        $subjAvg = count($allVals) ? round(array_sum($allVals)/count($allVals),1) : null;
        $gl = $subjAvg!==null ? gradeLetter($subjAvg,$ayId) : '—';
      ?>
      <tr>
        <td style="text-align:left;font-weight:600"><?= e($sub['name']) ?></td>
        <?php foreach($cols as $v): ?><td><?= $v ?></td><?php endforeach; ?>
        <td style="font-weight:700"><?= $subjAvg ?? '—' ?></td>
        <td style="font-weight:700;color:<?= in_array($gl,['A','B','C','D'])?'var(--green)':'var(--error)' ?>"><?= $gl ?></td>
      </tr>
      <?php endforeach; ?>
      <!-- Totals row -->
      <tr style="background:var(--primary-soft)">
        <td style="text-align:left;font-weight:800">YEARLY AVERAGE</td>
        <td colspan="9"></td>
        <td style="font-weight:800"><?= $rcData['yearly_average'] ? round($rcData['yearly_average'],1).'%' : '—' ?></td>
        <td style="font-weight:800"><?= $rcData['yearly_average'] ? gradeLetter((float)$rcData['yearly_average'],$ayId) : '—' ?></td>
      </tr>
    </tbody>
  </table>

  <!-- Attendance -->
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;margin:16px 0;font-size:12px">
    <div style="text-align:center;padding:8px;background:var(--bg);border-radius:6px"><strong style="display:block;font-size:16px"><?= $rcData['days_present']??0 ?></strong>Days Present</div>
    <div style="text-align:center;padding:8px;background:var(--bg);border-radius:6px"><strong style="display:block;font-size:16px"><?= $rcData['days_absent']??0 ?></strong>Days Absent</div>
    <div style="text-align:center;padding:8px;background:var(--bg);border-radius:6px"><strong style="display:block;font-size:16px"><?= $rcData['days_tardy']??0 ?></strong>Times Tardy</div>
    <div style="text-align:center;padding:8px;background:var(--bg);border-radius:6px"><strong style="display:block;font-size:16px"><?= $rcData['attendance_pct']?$rcData['attendance_pct'].'%':'—' ?></strong>Attendance Rate</div>
  </div>

  <!-- Comments -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:12px;margin-bottom:16px">
    <div style="padding:10px;border:1px solid var(--line);border-radius:6px"><strong>Conduct:</strong> <?= e($rcData['conduct']??'—') ?><br><strong>Teacher Comment:</strong><br><?= nl2br(e($rcData['teacher_comment']??'')) ?: '<em style="color:var(--ink-faint)">No comment</em>' ?></div>
    <div style="padding:10px;border:1px solid var(--line);border-radius:6px"><strong>Principal Comment:</strong><br><?= nl2br(e($rcData['principal_comment']??'')) ?: '<em style="color:var(--ink-faint)">No comment</em>' ?><br><br><strong>Promotion:</strong> <span style="font-weight:700;color:var(--primary)"><?= e($rcData['promotion_status']??'Pending') ?></span></div>
  </div>

  <!-- Signatures -->
  <div class="rc-signature">
    <div class="rc-sig-line">Class Teacher</div>
    <div class="rc-sig-line">Academic Dean</div>
    <div class="rc-sig-line">Principal</div>
  </div>

  <div style="text-align:center;margin-top:16px;font-size:10px;color:var(--ink-faint)">
    <?= e($schoolName) ?> &middot; Karnplay, Nimba County, Liberia &middot; Generated: <?= date('M d, Y') ?>
  </div>
</div>

<?php endif; ?>
<?php endif; ?>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
