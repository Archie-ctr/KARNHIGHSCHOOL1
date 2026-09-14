<?php
require_once dirname(__DIR__).'/config/db.php';
requireAuth(); requireRole(['sys_admin','super_admin','school_admin','principal','vice_principal','academic_dean','registrar']);

$pageTitle   = 'Promotion';
$activeAdmin = 'promotion';

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();
$tab  = $_GET['tab'] ?? 'process';

// ── POST: bulk promotion ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'promote') {
    verifyCsrf();
    $gradeId     = (int)($_POST['grade_id']     ?? 0);
    $nextGradeId = (int)($_POST['next_grade_id'] ?? 0);
    $decisions   = $_POST['decision'] ?? [];

    $promoted = $repeated = $graduated = 0;
    foreach ($decisions as $stdId => $dec) {
        $stdId   = (int)$stdId;
        $avg     = (float)($pdo->query("SELECT ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),2) FROM assessment_scores asc2 WHERE asc2.student_id=$stdId AND asc2.academic_year_id=$ayId AND asc2.status='approved'")->fetchColumn() ?: 0);
        $toGrade = ($dec === 'Promoted' && $nextGradeId) ? $nextGradeId : null;

        // Upsert promotion record
        $pdo->prepare(
            "INSERT INTO promotion_records
                (student_id,academic_year_id,from_grade_id,to_grade_id,status,yearly_average,processed_by,processed_at)
             VALUES (?,?,?,?,?,?,?,NOW())
             ON DUPLICATE KEY UPDATE
                to_grade_id=VALUES(to_grade_id), status=VALUES(status),
                yearly_average=VALUES(yearly_average),
                processed_by=VALUES(processed_by), processed_at=NOW()"
        )->execute([$stdId,$ayId,$gradeId,$toGrade,$dec,$avg,currentUserId()]);

        if ($dec === 'Promoted' && $toGrade) {
            $pdo->prepare("UPDATE students SET current_grade_id=?,status='Active',updated_at=NOW() WHERE id=?")->execute([$toGrade,$stdId]);
            $promoted++;
        } elseif ($dec === 'Repeating') {
            $pdo->prepare("UPDATE students SET status='Active',updated_at=NOW() WHERE id=?")->execute([$stdId]);
            $repeated++;
        } elseif ($dec === 'Graduated') {
            $pdo->prepare("UPDATE students SET status='Graduated',graduation_date=CURDATE(),updated_at=NOW() WHERE id=?")->execute([$stdId]);
            $graduated++;
        } elseif (in_array($dec, ['Transferred','Withdrawn'], true)) {
            $pdo->prepare("UPDATE students SET status=?,updated_at=NOW() WHERE id=?")->execute([$dec,$stdId]);
        }
    }
    auditLog('bulk_promotion','promotion','grade',$gradeId,'','Promoted:'.$promoted.' Repeated:'.$repeated.' Graduated:'.$graduated);
    flash('success', "Promotion processed — Promoted: $promoted · Repeating: $repeated · Graduated: $graduated");
    redirect(BASE_URL.'/admin/promotion.php?tab=process&grade_id='.$gradeId);
}

$grades     = $pdo->query("SELECT id,name,sequence FROM grades WHERE is_active=1 ORDER BY sequence")->fetchAll();
$allYears   = $pdo->query("SELECT id,name FROM academic_years ORDER BY id DESC")->fetchAll();
$selGrade   = (int)($_GET['grade_id']   ?? 0);
$selYear    = (int)($_GET['report_ay']  ?? $ayId);
$students   = []; $existing = [];

if ($tab === 'process' && $selGrade) {
    $sts = $pdo->prepare(
        "SELECT s.*, g.name grade_name,
                ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1) avg_pct
         FROM students s
         LEFT JOIN grades g ON g.id=s.current_grade_id
         LEFT JOIN assessment_scores asc2
               ON asc2.student_id=s.id
              AND asc2.academic_year_id=?
              AND asc2.status='approved'
         WHERE s.current_grade_id=? AND s.status='Active'
         GROUP BY s.id
         ORDER BY s.last_name, s.first_name"
    );
    $sts->execute([$ayId, $selGrade]); $students = $sts->fetchAll();

    $ex = $pdo->prepare(
        "SELECT student_id, status, yearly_average FROM promotion_records
         WHERE academic_year_id=? AND from_grade_id=?"
    );
    $ex->execute([$ayId, $selGrade]); $existing = array_column($ex->fetchAll(), null, 'student_id');

    // Next grade
    $curSeq    = (int)$pdo->query("SELECT sequence FROM grades WHERE id=$selGrade")->fetchColumn();
    $nextGrade = $pdo->query("SELECT id,name FROM grades WHERE sequence=".($curSeq+1)." AND is_active=1 LIMIT 1")->fetch() ?: null;
    $isGrade12 = ($curSeq >= 13);
}

// ── Report tab data ───────────────────────────────────────────
$reportStats = []; $reportByGrade = []; $reportDetails = [];
if ($tab === 'report') {
    // Summary counts per grade for selected year
    $reportByGrade = $pdo->prepare(
        "SELECT g.name grade_name, g.sequence,
                pr.status,
                COUNT(*) cnt,
                ROUND(AVG(pr.yearly_average),1) avg_pct
         FROM promotion_records pr
         JOIN grades g ON g.id = pr.from_grade_id
         WHERE pr.academic_year_id=?
         GROUP BY g.id, g.name, g.sequence, pr.status
         ORDER BY g.sequence, pr.status"
    );
    $reportByGrade->execute([$selYear]); $reportByGrade = $reportByGrade->fetchAll();

    // Overall totals for selected year
    $reportStats = $pdo->prepare(
        "SELECT
            COUNT(*) total,
            SUM(status='Promoted')  promoted,
            SUM(status='Repeating') repeating,
            SUM(status='Graduated') graduated,
            SUM(status='Transferred') transferred,
            SUM(status='Withdrawn') withdrawn,
            SUM(status='Not Promoted') not_promoted,
            ROUND(AVG(yearly_average),1) overall_avg
         FROM promotion_records WHERE academic_year_id=?"
    );
    $reportStats->execute([$selYear]); $reportStats = $reportStats->fetch();

    // Detail list — filterable by grade
    $reportDetails = $pdo->prepare(
        "SELECT pr.*,
                CONCAT(s.first_name,' ',s.last_name) sname, s.student_id sid,
                s.gender,
                fg.name from_grade, tg.name to_grade,
                u.name processed_by_name
         FROM promotion_records pr
         JOIN students s ON s.id=pr.student_id
         JOIN grades fg ON fg.id=pr.from_grade_id
         LEFT JOIN grades tg ON tg.id=pr.to_grade_id
         LEFT JOIN users u ON u.id=pr.processed_by
         WHERE pr.academic_year_id=?
         ".($selGrade ? "AND pr.from_grade_id=$selGrade" : "")."
         ORDER BY fg.sequence, s.last_name, s.first_name"
    );
    $reportDetails->execute([$selYear]); $reportDetails = $reportDetails->fetchAll();

    // Pivot by grade for chart-like display
    $gradeMatrix = [];
    foreach ($reportByGrade as $row) {
        $gradeMatrix[$row['grade_name']][$row['status']] = $row['cnt'];
        $gradeMatrix[$row['grade_name']]['avg'] = $row['avg_pct'];
    }
}

require_once dirname(__DIR__).'/includes/admin_header.php';

$statusColors = [
    'Promoted'     => ['bg'=>'#d1fae5','color'=>'#065f46','border'=>'#a7f3d0'],
    'Graduated'    => ['bg'=>'#dbeafe','color'=>'#1e40af','border'=>'#93c5fd'],
    'Repeating'    => ['bg'=>'#fef3c7','color'=>'#92400e','border'=>'#fde68a'],
    'Not Promoted' => ['bg'=>'#fef2f2','color'=>'#991b1b','border'=>'#fecaca'],
    'Transferred'  => ['bg'=>'#f3f4f6','color'=>'#374151','border'=>'#e5e7eb'],
    'Withdrawn'    => ['bg'=>'#f3f4f6','color'=>'#6b7280','border'=>'#e5e7eb'],
];
function promoBadge(string $status, array $colors): string {
    $c = $colors[$status] ?? ['bg'=>'#f3f4f6','color'=>'#374151','border'=>'#e5e7eb'];
    return '<span style="background:'.$c['bg'].';color:'.$c['color'].';border:1px solid '.$c['border'].';padding:2px 9px;border-radius:12px;font-size:11.5px;font-weight:700">'.htmlspecialchars($status,ENT_QUOTES).'</span>';
}
?>

<!-- ══ PAGE HEADING ════════════════════════════════════════════ -->
<div class="page-heading">
  <div>
    <div class="eyebrow">End of Year <span></span></div>
    <h1>Promotion Management</h1>
    <p><?=e($ay)?> — Review and process student promotion decisions</p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <?php if ($tab==='process' && $selGrade): ?>
    <a href="<?=BASE_URL?>/api/export.php?type=promotion_list&format=pdf&grade_id=<?=$selGrade?>"
       class="button button-secondary" style="background:#c00200;color:#fff" target="_blank">🖨 PDF</a>
    <a href="<?=BASE_URL?>/api/export.php?type=promotion_list&format=excel&grade_id=<?=$selGrade?>"
       class="button button-secondary" style="background:#1d6f42;color:#fff" target="_blank">📊 Excel</a>
    <?php endif; ?>
    <?php if ($tab==='report'): ?>
    <a href="<?=BASE_URL?>/api/export.php?type=promotion_report&format=excel&ay_id=<?=$selYear?><?=$selGrade?"&grade_id=$selGrade":''?>"
       class="button button-secondary" style="background:#1d6f42;color:#fff" target="_blank">📊 Export Excel</a>
    <a href="<?=BASE_URL?>/api/export.php?type=promotion_report&format=pdf&ay_id=<?=$selYear?><?=$selGrade?"&grade_id=$selGrade":''?>"
       class="button button-secondary" style="background:#c00200;color:#fff" target="_blank">🖨 Export PDF</a>
    <?php endif; ?>
  </div>
</div>

<!-- ══ TABS ════════════════════════════════════════════════════ -->
<div class="tab-bar" style="margin-bottom:22px">
  <a href="?tab=process<?=$selGrade?"&grade_id=$selGrade":''?>" class="tab-btn <?=$tab==='process'?'active':''?>">
    ⬆️ Process Promotion
  </a>
  <a href="?tab=report&report_ay=<?=$ayId?>" class="tab-btn <?=$tab==='report'?'active':''?>">
    📊 Promotion Report
  </a>
</div>

<?php if ($tab === 'process'): ?>
<!-- ══════════════════════════════════════════════════════════ -->
<!--  PROCESS PROMOTION TAB                                     -->
<!-- ══════════════════════════════════════════════════════════ -->

<div class="alert alert-warning" style="margin-bottom:18px">
  ⚠️ Promotion decisions are permanent once processed. Review student averages carefully before submitting.
  Always take a database backup before bulk operations.
</div>

<form method="get" class="filter-row" style="margin-bottom:22px">
  <input type="hidden" name="tab" value="process"/>
  <select name="grade_id" class="filter-button" onchange="this.form.submit()" style="min-width:200px">
    <option value="">Select Grade to promote…</option>
    <?php foreach($grades as $g): ?>
    <option value="<?=$g['id']?>" <?=$selGrade==$g['id']?'selected':''?>><?=e($g['name'])?></option>
    <?php endforeach; ?>
  </select>
</form>

<?php if ($selGrade && !empty($students)):
  $alreadyDone = !empty($existing);
  $passMark    = (float)setting('passing_grade','70');
?>
<div class="form-section">
  <div class="form-section-title" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
    <span style="display:flex;align-items:center;gap:10px">
      <?=e($pdo->query("SELECT name FROM grades WHERE id=$selGrade")->fetchColumn())?>
      <span class="status new-s" style="font-size:12px"><?=count($students)?> students</span>
      <?php if ($nextGrade??null): ?>
        <span style="font-size:13px;color:var(--ink-soft)">→ <?=e($nextGrade['name'])?></span>
      <?php endif; ?>
    </span>
    <?php if ($alreadyDone): ?>
      <span class="status approved" style="font-size:12px">✅ Already processed for <?=e($ay)?></span>
    <?php endif; ?>
  </div>

  <form method="post">
    <?=csrfField()?>
    <input type="hidden" name="action"        value="promote"/>
    <input type="hidden" name="grade_id"      value="<?=$selGrade?>"/>
    <input type="hidden" name="next_grade_id" value="<?=$nextGrade['id']??0?>"/>

    <!-- Quick-set bar -->
    <?php if (!$alreadyDone): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;padding:10px 14px;background:var(--bg);border-radius:var(--radius);border:1px solid var(--line)">
      <span style="font-size:12.5px;font-weight:700;color:var(--ink-soft);align-self:center">Quick set all:</span>
      <?php $quickOpts = $isGrade12 ? ['Graduated','Repeating'] : ['Promoted','Repeating']; ?>
      <?php foreach($quickOpts as $qo): ?>
      <button type="button" class="filter-button button-sm"
              onclick="document.querySelectorAll('select[name^=\'decision\']').forEach(s=>s.value='<?=$qo?>')">
        Set all → <?=$qo?>
      </button>
      <?php endforeach; ?>
      <button type="button" class="filter-button button-sm"
              onclick="document.querySelectorAll('select[name^=\'decision\']').forEach(s=>{
                const avg=parseFloat(s.closest('tr').querySelector('.avg-val')?.dataset.avg||'0');
                s.value=avg>=<?=$passMark?>?'<?=($isGrade12?'Graduated':'Promoted')?>':'Repeating';
              })">
        🎯 Auto by average (<?=$passMark?>% pass)
      </button>
    </div>
    <?php endif; ?>

    <div class="table-wrap" style="margin-bottom:16px">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th style="text-align:left">Student</th>
            <th>Student ID</th>
            <th>Avg %</th>
            <th>Status</th>
            <th style="text-align:center">Decision</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($students as $i => $st):
            $prev       = $existing[$st['id']] ?? null;
            $locked     = $prev !== null;
            $avg        = (float)($st['avg_pct'] ?? 0);
            $autoDec    = $isGrade12 ? 'Graduated' : ($avg >= $passMark ? 'Promoted' : 'Repeating');
            $defaultDec = $prev['status'] ?? $autoDec;
            $avgColor   = $avg >= $passMark ? 'var(--green)' : ($avg > 0 ? 'var(--error)' : 'var(--ink-faint)');
          ?>
          <tr>
            <td class="muted"><?=$i+1?></td>
            <td>
              <strong><?=e($st['first_name'].' '.$st['last_name'])?></strong>
              <div style="font-size:11px;color:var(--ink-faint)"><?=e($st['grade_name']??'')?></div>
            </td>
            <td class="muted" style="font-size:12px"><?=e($st['student_id'])?></td>
            <td>
              <span class="avg-val" data-avg="<?=$avg?>"
                    style="font-weight:700;color:<?=$avgColor?>;font-size:13.5px">
                <?=$avg > 0 ? $avg.'%' : '—'?>
              </span>
            </td>
            <td>
              <?php if ($locked): ?>
                <?=promoBadge($prev['status'], $statusColors)?>
              <?php else: ?>
                <span class="status <?=$avg>=$passMark?'approved':'warning'?>" style="font-size:11px">
                  <?=$avg>=$passMark?'Pass':'Below pass'?>
                </span>
              <?php endif; ?>
            </td>
            <td style="text-align:center">
              <?php if ($locked): ?>
                <?=promoBadge($prev['status'], $statusColors)?>
                <input type="hidden" name="decision[<?=$st['id']?>]" value="<?=e($prev['status'])?>"/>
              <?php else: ?>
                <?php $opts = $isGrade12 ? ['Graduated','Repeating','Transferred','Withdrawn']
                                         : ['Promoted','Repeating','Not Promoted','Transferred','Withdrawn']; ?>
                <select name="decision[<?=$st['id']?>]"
                        style="padding:6px 10px;border:1.5px solid var(--line);border-radius:6px;
                               font-size:13px;background:var(--bg);min-width:130px">
                  <?php foreach($opts as $opt): ?>
                  <option value="<?=$opt?>" <?=$defaultDec===$opt?'selected':''?>><?=$opt?></option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if (!$alreadyDone): ?>
    <div style="display:flex;justify-content:flex-end;gap:10px">
      <button type="submit" class="button"
              style="background:#dc2626;color:#fff;border:none;padding:10px 24px;font-size:14px;font-weight:700"
              onclick="return confirm('Process promotion for all listed students?\n\nThis will update their grade records. This cannot be easily undone.\n\nContinue?')">
        ⬆️ Process Promotion
      </button>
    </div>
    <?php else: ?>
    <div class="alert alert-info" style="margin-top:0">
      ✅ Promotion has already been processed for this grade in <?=e($ay)?>.
      <a href="?tab=report&report_ay=<?=$ayId?>&grade_id=<?=$selGrade?>" style="font-weight:700">View Report →</a>
    </div>
    <?php endif; ?>
  </form>
</div>

<?php elseif ($selGrade): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">📭</div>
  <p style="color:var(--ink-soft)">No active students found in the selected grade for <?=e($ay)?>.</p>
</div>
<?php else: ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:40px;margin-bottom:12px">⬆️</div>
  <h3 style="margin-bottom:8px">Select a Grade</h3>
  <p style="color:var(--ink-soft)">Choose a grade from the dropdown above to view students and process promotion.</p>
</div>
<?php endif; ?>


<?php elseif ($tab === 'report'): ?>
<!-- ══════════════════════════════════════════════════════════ -->
<!--  PROMOTION REPORT TAB                                      -->
<!-- ══════════════════════════════════════════════════════════ -->

<!-- Year + grade filter -->
<div class="filter-row" style="margin-bottom:22px">
  <form method="get" style="display:contents">
    <input type="hidden" name="tab" value="report"/>
    <select name="report_ay" class="filter-button" onchange="this.form.submit()" style="min-width:160px">
      <?php foreach($allYears as $yr): ?>
      <option value="<?=$yr['id']?>" <?=$selYear==$yr['id']?'selected':''?>><?=e($yr['name'])?></option>
      <?php endforeach; ?>
    </select>
    <select name="grade_id" class="filter-button" onchange="this.form.submit()" style="min-width:180px">
      <option value="">All Grades</option>
      <?php foreach($grades as $g): ?>
      <option value="<?=$g['id']?>" <?=$selGrade==$g['id']?'selected':''?>><?=e($g['name'])?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php
  $selYearName = '';
  foreach($allYears as $yr) { if($yr['id']==$selYear) { $selYearName=$yr['name']; break; } }
?>

<?php if (empty($reportDetails)): ?>
<div style="text-align:center;padding:56px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:40px;margin-bottom:12px">📊</div>
  <h3 style="margin-bottom:8px">No promotion records found</h3>
  <p style="color:var(--ink-soft)">No promotion data for <strong><?=e($selYearName)?></strong><?=$selGrade?' in the selected grade':'.'?></p>
</div>
<?php else: ?>

<!-- ── Summary cards ─────────────────────────────────────────── -->
<div class="metric-grid" style="margin-bottom:24px">
  <div class="metric-card">
    <div class="metric-top"><span>Total Students</span><div class="metric-icon">👥</div></div>
    <strong style="font-size:2rem"><?=number_format($reportStats['total']??0)?></strong>
    <small><i></i><?=e($selYearName)?></small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Promoted</span><div class="metric-icon" style="background:#d1fae5">⬆️</div></div>
    <strong style="font-size:2rem;color:#065f46"><?=number_format($reportStats['promoted']??0)?></strong>
    <small style="color:#065f46">
      <?= $reportStats['total'] > 0 ? round(($reportStats['promoted']/$reportStats['total'])*100,1).'%' : '—' ?>
    </small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Graduated</span><div class="metric-icon" style="background:#dbeafe">🎓</div></div>
    <strong style="font-size:2rem;color:#1e40af"><?=number_format($reportStats['graduated']??0)?></strong>
    <small style="color:#1e40af">Grade 12</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Repeating</span><div class="metric-icon" style="background:#fef3c7">🔄</div></div>
    <strong style="font-size:2rem;color:#92400e"><?=number_format($reportStats['repeating']??0)?></strong>
    <small style="color:#92400e">
      <?= $reportStats['total'] > 0 ? round(($reportStats['repeating']/$reportStats['total'])*100,1).'%' : '—' ?>
    </small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Not Promoted / Other</span><div class="metric-icon" style="background:#fef2f2">❌</div></div>
    <strong style="font-size:2rem;color:#991b1b"><?=number_format(($reportStats['not_promoted']??0)+($reportStats['transferred']??0)+($reportStats['withdrawn']??0))?></strong>
    <small>Not promoted · Transferred · Withdrawn</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Overall Average</span><div class="metric-icon">📊</div></div>
    <strong style="font-size:2rem;color:var(--primary)"><?=$reportStats['overall_avg']??'—'?>%</strong>
    <small><i></i>All processed students</small>
  </div>
</div>

<!-- ── Grade breakdown matrix ─────────────────────────────────── -->
<?php if (!$selGrade && !empty($gradeMatrix)): ?>
<div class="form-section" style="margin-bottom:24px">
  <div class="form-section-title">📊 Promotion Breakdown by Grade — <?=e($selYearName)?></div>
  <div class="table-wrap" style="border:none">
    <table>
      <thead>
        <tr>
          <th style="text-align:left">Grade</th>
          <th style="text-align:center;color:#065f46">Promoted</th>
          <th style="text-align:center;color:#1e40af">Graduated</th>
          <th style="text-align:center;color:#92400e">Repeating</th>
          <th style="text-align:center;color:#991b1b">Not Promoted</th>
          <th style="text-align:center;color:#6b7280">Transferred</th>
          <th style="text-align:center;color:#6b7280">Withdrawn</th>
          <th style="text-align:center">Avg %</th>
          <th style="text-align:center">Total</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($gradeMatrix as $gname => $data):
          $gtotal = ($data['Promoted']??0)+($data['Graduated']??0)+($data['Repeating']??0)+($data['Not Promoted']??0)+($data['Transferred']??0)+($data['Withdrawn']??0);
          $gid    = 0;
          foreach($grades as $gg) { if($gg['name']===$gname){$gid=$gg['id'];break;} }
          $promoPct = $gtotal > 0 ? round((($data['Promoted']??0)+($data['Graduated']??0))/$gtotal*100,1) : 0;
        ?>
        <tr>
          <td><strong><?=e($gname)?></strong></td>
          <td style="text-align:center;font-weight:700;color:#065f46"><?=$data['Promoted']??'—'?></td>
          <td style="text-align:center;font-weight:700;color:#1e40af"><?=$data['Graduated']??'—'?></td>
          <td style="text-align:center;font-weight:700;color:#92400e"><?=$data['Repeating']??'—'?></td>
          <td style="text-align:center;font-weight:700;color:#991b1b"><?=$data['Not Promoted']??'—'?></td>
          <td style="text-align:center;color:#6b7280"><?=$data['Transferred']??'—'?></td>
          <td style="text-align:center;color:#6b7280"><?=$data['Withdrawn']??'—'?></td>
          <td style="text-align:center">
            <strong style="color:<?=$data['avg']>=(float)setting('passing_grade','70')?'var(--green)':'var(--error)'?>">
              <?=$data['avg']??'—'?>%
            </strong>
          </td>
          <td style="text-align:center;font-weight:700"><?=$gtotal?></td>
          <td>
            <!-- Mini progress bar: promoted vs total -->
            <div style="width:80px;height:6px;background:#e5e7eb;border-radius:3px;overflow:hidden">
              <div style="width:<?=$promoPct?>%;height:100%;background:#059669;border-radius:3px"></div>
            </div>
            <div style="font-size:10px;color:var(--ink-faint);margin-top:1px"><?=$promoPct?>% pass rate</div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ── Detailed student list ────────────────────────────────── -->
<div class="form-section">
  <div class="form-section-title" style="display:flex;justify-content:space-between;align-items:center">
    <span>
      📋 Student Details
      <span class="status new-s" style="margin-left:8px;font-size:11px"><?=count($reportDetails)?> records</span>
    </span>
    <span style="font-size:12px;color:var(--ink-soft)"><?=e($selYearName)?><?=$selGrade?' · '.$pdo->query("SELECT name FROM grades WHERE id=$selGrade")->fetchColumn():''?></span>
  </div>
  <div class="table-wrap" style="border:none">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th style="text-align:left">Student</th>
          <th>ID</th>
          <th>Gender</th>
          <th>From Grade</th>
          <th>To Grade</th>
          <th style="text-align:center">Avg %</th>
          <th style="text-align:center">Decision</th>
          <th>Processed</th>
          <th>By</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($reportDetails as $i => $rd): ?>
        <tr>
          <td class="muted"><?=$i+1?></td>
          <td><strong><?=e($rd['sname'])?></strong></td>
          <td class="muted" style="font-size:12px"><?=e($rd['sid'])?></td>
          <td style="font-size:12px"><?=e($rd['gender']??'—')?></td>
          <td style="font-size:12.5px"><?=e($rd['from_grade'])?></td>
          <td style="font-size:12.5px;color:var(--ink-soft)"><?=e($rd['to_grade']??'—')?></td>
          <td style="text-align:center;font-weight:700;color:<?=($rd['yearly_average']>=(float)setting('passing_grade','70')&&$rd['yearly_average']>0)?'var(--green)':'var(--ink-faint)'?>">
            <?=$rd['yearly_average']>0?$rd['yearly_average'].'%':'—'?>
          </td>
          <td style="text-align:center"><?=promoBadge($rd['status'],$statusColors)?></td>
          <td style="font-size:11.5px;color:var(--ink-soft)"><?=date('d M Y',strtotime($rd['processed_at']))?></td>
          <td style="font-size:12px;color:var(--ink-soft)"><?=e($rd['processed_by_name']??'—')?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; // reportDetails not empty ?>

<?php endif; // tab ?>

<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
