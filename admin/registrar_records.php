<?php
$pageTitle   = 'Academic Records';
$activeAdmin = 'registrar_records';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['sys_admin','super_admin','school_admin','principal','vice_principal','registrar']);

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();
$tab  = $_GET['tab'] ?? 'search';

// ── Student search ────────────────────────────────────────────
$q       = trim($_GET['q']       ?? '');
$gradeF  = (int)($_GET['grade_id'] ?? 0);
$statusF = trim($_GET['status']    ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$per     = 20;

$where = []; $params = [];
if ($q)      { $where[] = '(s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ? OR s.admission_number LIKE ?)'; $like="%$q%"; array_push($params,$like,$like,$like,$like); }
if ($gradeF) { $where[] = 's.current_grade_id=?'; $params[] = $gradeF; }
if ($statusF){ $where[] = 's.status=?';            $params[] = $statusF; }
$wsql = $where ? 'WHERE '.implode(' AND ',$where) : '';

$cnt  = $pdo->prepare("SELECT COUNT(*) FROM students s $wsql"); $cnt->execute($params); $total = (int)$cnt->fetchColumn();
$pg   = paginate($total, $per, $page);
$rows = $pdo->prepare(
    "SELECT s.*, g.name grade_name, c.name class_name
     FROM students s
     LEFT JOIN grades g ON g.id=s.current_grade_id
     LEFT JOIN classes c ON c.id=s.current_class_id
     $wsql ORDER BY s.last_name, s.first_name LIMIT $per OFFSET {$pg['offset']}"
);
$rows->execute($params);
$students = $rows->fetchAll();

// ── Single student record ─────────────────────────────────────
$sid     = (int)($_GET['student_id'] ?? 0);
$student = null; $scores = []; $promotionHist = []; $gradRecord = null; $movementHist = [];

if ($sid) {
    $s = $pdo->prepare(
        "SELECT s.*, g.name grade_name, c.name class_name
         FROM students s
         LEFT JOIN grades g ON g.id=s.current_grade_id
         LEFT JOIN classes c ON c.id=s.current_class_id
         WHERE s.id=? LIMIT 1"
    );
    $s->execute([$sid]); $student = $s->fetch();

    if ($student) {
        // All academic years with scores
        $scores = $pdo->prepare(
            "SELECT ay.name ay_name, ay.id ay_id,
                    g.name grade_name,
                    sub.name subject_name,
                    ac.name config_name, ac.sequence cfg_seq,
                    asc2.marks_obtained, asc2.max_marks, asc2.status,
                    ROUND(asc2.marks_obtained/asc2.max_marks*100,1) pct
             FROM assessment_scores asc2
             JOIN academic_years ay   ON ay.id  = asc2.academic_year_id
             JOIN subjects sub        ON sub.id = asc2.subject_id
             JOIN assessment_configs ac ON ac.id = asc2.assessment_config_id
             LEFT JOIN grades g ON g.id=(
                 SELECT current_grade_id FROM students WHERE id=asc2.student_id LIMIT 1
             )
             WHERE asc2.student_id=? AND asc2.status IN ('approved','published')
               AND asc2.max_marks>0
             ORDER BY ay.start_date DESC, sub.name, ac.sequence"
        );
        $scores->execute([$sid]); $scores = $scores->fetchAll();

        // Group by academic year + subject
        $scoresByYear = [];
        foreach ($scores as $sc) {
            $scoresByYear[$sc['ay_id']]['ay_name']   = $sc['ay_name'];
            $scoresByYear[$sc['ay_id']]['subjects'][$sc['subject_name']][$sc['config_name']] = $sc;
        }

        // Promotion history
        try {
            $ph = $pdo->prepare(
                "SELECT pr.*, ay.name ay_name,
                        gf.name from_grade, gt.name to_grade,
                        u.name processed_by_name
                 FROM promotion_records pr
                 JOIN academic_years ay ON ay.id = pr.academic_year_id
                 LEFT JOIN grades gf ON gf.id = pr.from_grade_id
                 LEFT JOIN grades gt ON gt.id = pr.to_grade_id
                 LEFT JOIN users u ON u.id = pr.processed_by
                 WHERE pr.student_id=?
                 ORDER BY ay.start_date DESC"
            );
            $ph->execute([$sid]); $promotionHist = $ph->fetchAll();
        } catch (Throwable $e) { $promotionHist = []; }

        // Graduation record
        try {
            $gr = $pdo->prepare(
                "SELECT gr.*, ay.name ay_name, u.name approved_by_name
                 FROM graduation_records gr
                 JOIN academic_years ay ON ay.id=gr.academic_year_id
                 LEFT JOIN users u ON u.id=gr.approved_by
                 WHERE gr.student_id=? LIMIT 1"
            );
            $gr->execute([$sid]); $gradRecord = $gr->fetch() ?: null;
        } catch (Throwable $e) { $gradRecord = null; }

        // Movement history
        try {
            $mv = $pdo->prepare(
                "SELECT sm.*, ay.name ay_name, u.name requester_name
                 FROM student_movements sm
                 JOIN academic_years ay ON ay.id=sm.academic_year_id
                 LEFT JOIN users u ON u.id=sm.requested_by
                 WHERE sm.student_id=? ORDER BY sm.created_at DESC"
            );
            $mv->execute([$sid]); $movementHist = $mv->fetchAll();
        } catch (Throwable $e) { $movementHist = []; }

        // Guardian
        try {
            $gd = $pdo->prepare(
                "SELECT g.* FROM guardians g
                 JOIN student_guardians sg ON sg.guardian_id=g.id
                 WHERE sg.student_id=? LIMIT 1"
            );
            $gd->execute([$sid]); $guardian = $gd->fetch() ?: null;
        } catch (Throwable $e) { $guardian = null; }
    }
}

$grades    = $pdo->query("SELECT id,name FROM grades WHERE is_active=1 ORDER BY sequence")->fetchAll();
$allYears  = $pdo->query("SELECT id,name FROM academic_years ORDER BY start_date DESC")->fetchAll();
$schoolName= setting('school_name','KARN HIGH SCHOOL');
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">Registrar <span></span></div>
    <h1>Academic Records</h1>
    <p>Student academic history, transcripts and records</p>
  </div>
  <?php if ($student): ?>
  <div style="display:flex;gap:8px">
    <a href="<?= BASE_URL ?>/admin/registrar_records.php" class="button button-secondary">← Back to Search</a>
    <a href="<?= BASE_URL ?>/letters/transcript_pdf.php?student_id=<?= $student['id'] ?>"
       class="button button-primary" target="_blank">📄 Print Transcript</a>
  </div>
  <?php endif; ?>
</div>

<?php if ($student): ?>
<!-- ═══════════════ STUDENT ACADEMIC RECORD ═══════════════ -->

<!-- Student header card -->
<div class="panel" style="padding:22px;margin-bottom:20px;display:flex;align-items:center;gap:20px;flex-wrap:wrap">
  <?php $ini = strtoupper(substr($student['first_name'],0,1).substr($student['last_name'],0,1)); ?>
  <div class="avatar" style="width:56px;height:56px;font-size:20px;flex-shrink:0"><?= e($ini) ?></div>
  <div style="flex:1">
    <h2 style="font-size:18px;font-weight:800;margin-bottom:4px">
      <?= e(trim($student['first_name'].($student['middle_name']?' '.$student['middle_name']:'').' '.$student['last_name'])) ?>
    </h2>
    <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:12.5px;color:var(--ink-soft)">
      <span>🆔 <?= e($student['student_id']) ?></span>
      <span>📋 Adm: <?= e($student['admission_number'] ?? '—') ?></span>
      <span>🎓 <?= e($student['grade_name'] ?? '—') ?><?= $student['class_name'] ? ' / '.e($student['class_name']) : '' ?></span>
      <span>📅 Enrolled: <?= $student['admission_date'] ? date('M d, Y', strtotime($student['admission_date'])) : '—' ?></span>
    </div>
  </div>
  <span class="status <?= $student['status']==='Active'?'approved':($student['status']==='Graduated'?'approved':'warning') ?>">
    <?= e($student['status']) ?>
  </span>
</div>

<!-- Record tabs -->
<div class="tab-bar" style="margin-bottom:16px">
  <a href="?student_id=<?= $sid ?>&tab=history"    class="tab-btn <?= $tab==='history'   ?'active':'' ?>">📊 Academic History</a>
  <a href="?student_id=<?= $sid ?>&tab=promotion"  class="tab-btn <?= $tab==='promotion' ?'active':'' ?>">⬆️ Promotion Records</a>
  <a href="?student_id=<?= $sid ?>&tab=movements"  class="tab-btn <?= $tab==='movements' ?'active':'' ?>">🔄 Movements</a>
  <a href="?student_id=<?= $sid ?>&tab=documents"  class="tab-btn <?= $tab==='documents' ?'active':'' ?>">📄 Documents</a>
  <?php if ($gradRecord): ?>
  <a href="?student_id=<?= $sid ?>&tab=graduation" class="tab-btn <?= $tab==='graduation'?'active':'' ?>">🎓 Graduation</a>
  <?php endif; ?>
</div>

<?php if ($tab === 'history'): ?>
<!-- ── ACADEMIC HISTORY ─────────────────────────────────── -->
<?php if (empty($scoresByYear)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">📊</div>
  <p style="color:var(--ink-soft)">No approved academic records found for this student.</p>
</div>
<?php else: foreach ($scoresByYear as $ayId2 => $yearData): ?>
<div class="panel" style="padding:0;overflow:hidden;margin-bottom:16px">
  <div style="padding:12px 18px;background:var(--bg2);border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center">
    <strong style="font-size:14px"><?= e($yearData['ay_name']) ?></strong>
    <a href="<?= BASE_URL ?>/letters/transcript_pdf.php?student_id=<?= $sid ?>&ay_id=<?= $ayId2 ?>"
       class="filter-button button-sm" target="_blank">📄 Transcript</a>
  </div>
  <div class="table-wrap" style="border:none">
    <table>
      <thead><tr><th>Subject</th><th>Assessment</th><th>Marks</th><th>%</th><th>Grade</th></tr></thead>
      <tbody>
        <?php foreach ($yearData['subjects'] as $subj => $configs): ?>
        <?php $subVals = array_filter(array_map(fn($sc) => $sc['pct']??null, $configs), fn($v) => $v !== null);
              $subAvg  = count($subVals) ? round(array_sum($subVals)/count($subVals),1) : null;
              $gl = $subAvg !== null ? gradeLetter($subAvg, $ayId) : '—';
        ?>
        <tr style="background:var(--bg)">
          <td rowspan="<?= count($configs)+1 ?>"><strong><?= e($subj) ?></strong></td>
        </tr>
        <?php foreach ($configs as $cfg => $sc): ?>
        <tr>
          <td class="muted"><?= e($cfg) ?></td>
          <td><?= $sc['marks_obtained'] ?>/<?= $sc['max_marks'] ?></td>
          <td style="color:<?= ($sc['pct']??0)>=50?'var(--green)':'var(--error)' ?>"><?= $sc['pct']??'—' ?>%</td>
          <td>—</td>
        </tr>
        <?php endforeach; ?>
        <tr style="background:var(--bg2)">
          <td style="font-size:11px;color:var(--ink-soft)">Subject Average</td>
          <td>—</td>
          <td><strong><?= $subAvg !== null ? $subAvg.'%' : '—' ?></strong></td>
          <td><strong style="color:<?= in_array($gl,['A','B','C','D'])?'var(--green)':'var(--error)' ?>"><?= $gl ?></strong></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; endif; ?>

<?php elseif ($tab === 'promotion'): ?>
<!-- ── PROMOTION HISTORY ────────────────────────────────── -->
<?php if (empty($promotionHist)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">⬆️</div>
  <p style="color:var(--ink-soft)">No promotion records found.</p>
</div>
<?php else: ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Academic Year</th><th>From Grade</th><th>To Grade</th><th>Decision</th><th>Date</th><th>Processed By</th></tr></thead>
    <tbody>
      <?php foreach ($promotionHist as $pr): ?>
      <tr>
        <td><?= e($pr['ay_name']) ?></td>
        <td class="muted"><?= e($pr['from_grade'] ?? '—') ?></td>
        <td class="muted"><?= e($pr['to_grade'] ?? '—') ?></td>
        <td><span class="status <?= $pr['status']==='Promoted'?'approved':($pr['status']==='Repeating'?'pending':'warning') ?>"><?= e($pr['status']) ?></span></td>
        <td class="muted"><?= $pr['processed_at'] ? date('M d, Y', strtotime($pr['processed_at'])) : '—' ?></td>
        <td class="muted"><?= e($pr['processed_by_name'] ?? '—') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php elseif ($tab === 'movements'): ?>
<!-- ── STUDENT MOVEMENTS ────────────────────────────────── -->
<?php if (empty($movementHist)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">🔄</div>
  <p style="color:var(--ink-soft)">No transfer or withdrawal records.</p>
</div>
<?php else: ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Type</th><th>Effective Date</th><th>Destination/Reason</th><th>Status</th><th>Academic Year</th></tr></thead>
    <tbody>
      <?php
      $typeLabels = ['transfer_out'=>'➡️ Transfer Out','transfer_in'=>'⬅️ Transfer In','withdrawal'=>'❌ Withdrawal','re_enrollment'=>'🔄 Re-enrollment'];
      foreach ($movementHist as $mv): ?>
      <tr>
        <td><span class="badge badge-grey"><?= $typeLabels[$mv['movement_type']] ?? e($mv['movement_type']) ?></span></td>
        <td class="muted"><?= date('M d, Y', strtotime($mv['effective_date'])) ?></td>
        <td style="max-width:200px;white-space:normal;font-size:12.5px">
          <?php if ($mv['destination_school']): ?><strong><?= e($mv['destination_school']) ?></strong><br><?php endif; ?>
          <?= e(mb_substr($mv['reason'],0,80)) ?>
        </td>
        <td><span class="status <?= $mv['status']==='completed'?'approved':($mv['status']==='approved'?'new-s':($mv['status']==='cancelled'?'warning':'pending')) ?>"><?= ucfirst($mv['status']) ?></span></td>
        <td class="muted"><?= e($mv['ay_name'] ?? '—') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php elseif ($tab === 'documents'): ?>
<!-- ── DOCUMENTS ────────────────────────────────────────── -->
<?php
try {
    $docs = $pdo->prepare("SELECT * FROM application_documents WHERE application_id IN (SELECT id FROM applications WHERE user_id=(SELECT user_id FROM students WHERE id=?) OR CONCAT(first_name,' ',last_name)=?) ORDER BY created_at DESC");
    $docs->execute([$sid, $student['first_name'].' '.$student['last_name']]);
    $docs = $docs->fetchAll();
} catch (Throwable $e) { $docs = []; }

$docTypes = ['report_card'=>'📋 Report Card','birth_certificate'=>'📄 Birth Certificate','passport_photo'=>'🖼️ Passport Photo','other'=>'📎 Other'];
?>
<?php if (empty($docs)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">📄</div>
  <p style="color:var(--ink-soft)">No documents on file for this student.</p>
  <a href="<?= BASE_URL ?>/admin/documents.php?student_id=<?= $sid ?>" class="button button-secondary" style="margin-top:14px">Manage Documents</a>
</div>
<?php else: ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Document Type</th><th>File</th><th>Size</th><th>Uploaded</th><th>Status</th></tr></thead>
    <tbody>
      <?php foreach ($docs as $d): ?>
      <tr>
        <td><strong><?= $docTypes[$d['doc_type']] ?? e($d['doc_type']) ?></strong></td>
        <td class="muted" style="font-size:12px"><?= e($d['file_name']) ?></td>
        <td class="muted"><?= $d['file_size'] ? round($d['file_size']/1024,1).' KB' : '—' ?></td>
        <td class="muted"><?= date('M d, Y', strtotime($d['created_at'])) ?></td>
        <td><span class="status <?= ($d['is_verified']??0)?'approved':'pending' ?>"><?= ($d['is_verified']??0)?'Verified':'Pending' ?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php elseif ($tab === 'graduation' && $gradRecord): ?>
<!-- ── GRADUATION RECORD ─────────────────────────────────── -->
<div class="panel" style="padding:24px;max-width:560px">
  <h3 style="font-weight:700;margin-bottom:16px">🎓 Graduation Record</h3>
  <?php foreach ([
    'Academic Year'    => $gradRecord['ay_name'],
    'Graduation Date'  => $gradRecord['graduation_date'] ? date('F d, Y', strtotime($gradRecord['graduation_date'])) : '—',
    'Status'           => ucfirst($gradRecord['status']),
    'Overall Average'  => $gradRecord['overall_average'] ? $gradRecord['overall_average'].'%' : '—',
    'Certificate #'    => $gradRecord['certificate_number'] ?? '—',
    'Approved By'      => $gradRecord['approved_by_name'] ?? '—',
    'Approved At'      => $gradRecord['approved_at'] ? date('M d, Y H:i', strtotime($gradRecord['approved_at'])) : '—',
  ] as $label => $val): ?>
  <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--line-soft);font-size:13px">
    <span style="color:var(--ink-soft);font-weight:600;min-width:130px"><?= $label ?></span>
    <span style="text-align:right"><?= e($val) ?></span>
  </div>
  <?php endforeach; ?>
  <?php if (in_array($gradRecord['status'],['approved','graduated'])): ?>
  <a href="<?= BASE_URL ?>/letters/graduation_cert.php?student_id=<?= $sid ?>&ay_id=<?= $gradRecord['academic_year_id'] ?>"
     class="button button-secondary" style="margin-top:16px" target="_blank">📜 View Certificate</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ═══════════════ STUDENT SEARCH ═══════════════ -->
<form method="get" class="filter-row" style="margin-bottom:16px">
  <div class="table-search">🔍<input type="search" name="q" placeholder="Name, Student ID, Admission #…" value="<?= e($q) ?>"/></div>
  <select name="grade_id" class="filter-button" onchange="this.form.submit()">
    <option value="">All grades</option>
    <?php foreach ($grades as $g): ?><option value="<?= $g['id'] ?>" <?= $gradeF==$g['id']?'selected':'' ?>><?= e($g['name']) ?></option><?php endforeach; ?>
  </select>
  <select name="status" class="filter-button" onchange="this.form.submit()">
    <option value="">All statuses</option>
    <?php foreach (['Active','Graduated','Transferred','Withdrawn','Inactive','Suspended'] as $s): ?><option value="<?= $s ?>" <?= $statusF===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
  </select>
  <button type="submit" class="button button-secondary button-sm">Search</button>
  <?php if ($q||$gradeF||$statusF): ?><a href="<?= BASE_URL ?>/admin/registrar_records.php" class="filter-button">Clear</a><?php endif; ?>
</form>

<?php if (empty($students) && ($q||$gradeF||$statusF)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">🔍</div>
  <p style="color:var(--ink-soft)">No students found.</p>
</div>
<?php elseif (!empty($students)): ?>
<div class="table-wrap">
  <table>
    <thead><tr><th>Student</th><th>Student ID</th><th>Admission #</th><th>Grade</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($students as $s):
        $ini = strtoupper(substr($s['first_name'],0,1).substr($s['last_name'],0,1));
      ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <div class="avatar" style="width:32px;height:32px;font-size:11px;flex-shrink:0"><?= e($ini) ?></div>
            <strong><?= e($s['first_name'].' '.$s['last_name']) ?></strong>
          </div>
        </td>
        <td class="muted"><?= e($s['student_id']) ?></td>
        <td class="muted"><?= e($s['admission_number'] ?? '—') ?></td>
        <td class="muted"><?= e($s['grade_name'] ?? '—') ?></td>
        <td><span class="status <?= $s['status']==='Active'?'approved':($s['status']==='Graduated'?'approved':'warning') ?>"><?= e($s['status']) ?></span></td>
        <td>
          <a href="?student_id=<?= $s['id'] ?>&tab=history" class="filter-button button-sm">📊 Records</a>
          <a href="?student_id=<?= $s['id'] ?>&tab=documents" class="filter-button button-sm">📄 Docs</a>
          <a href="<?= BASE_URL ?>/letters/transcript_pdf.php?student_id=<?= $s['id'] ?>" class="filter-button button-sm" target="_blank">📜 Transcript</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php if ($pg['pages'] > 1): ?>
<div class="pagination">
  <?php if($pg['page']>1):?><a href="?q=<?=urlencode($q)?>&grade_id=<?=$gradeF?>&status=<?=urlencode($statusF)?>&page=<?=$pg['page']-1?>">&laquo;</a><?php endif; ?>
  <?php for($p=max(1,$pg['page']-2);$p<=min($pg['pages'],$pg['page']+2);$p++): ?>
    <?php if($p===$pg['page']):?><span class="current"><?=$p?></span><?php else:?><a href="?q=<?=urlencode($q)?>&grade_id=<?=$gradeF?>&status=<?=urlencode($statusF)?>&page=<?=$p?>"><?=$p?></a><?php endif; ?>
  <?php endfor; ?>
  <?php if($pg['page']<$pg['pages']):?><a href="?q=<?=urlencode($q)?>&grade_id=<?=$gradeF?>&status=<?=urlencode($statusF)?>&page=<?=$pg['page']+1?>">&raquo;</a><?php endif; ?>
</div>
<?php endif; ?>
<?php else: ?>
<div style="text-align:center;padding:56px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:40px;margin-bottom:14px">📊</div>
  <h3 style="margin-bottom:8px">Search for a Student</h3>
  <p style="color:var(--ink-soft)">Enter a name, student ID or admission number to view academic records, transcripts and history.</p>
</div>
<?php endif; endif; ?>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
