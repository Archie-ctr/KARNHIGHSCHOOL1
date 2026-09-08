<?php
$pageTitle   = 'Academic Overview';
$activeAdmin = 'vp_academic';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['sys_admin','super_admin','school_admin','principal','vice_principal','vice_principal_alt','academic_dean']);

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();
$tab  = $_GET['tab'] ?? 'performance';

// ── Class performance ─────────────────────────────────────────
$classPerf = $pdo->prepare(
    "SELECT c.id, c.name class_name, g.name grade_name, g.sequence,
            COUNT(DISTINCT s.id) students,
            ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1) avg_pct,
            SUM(CASE WHEN asc2.marks_obtained/asc2.max_marks*100 >= 50 THEN 1 ELSE 0 END) passing,
            COUNT(asc2.id) score_count
     FROM classes c
     JOIN grades g ON g.id=c.grade_id
     LEFT JOIN students s ON s.current_class_id=c.id AND s.status='Active'
     LEFT JOIN assessment_scores asc2 ON asc2.student_id=s.id
           AND asc2.academic_year_id=? AND asc2.max_marks>0
           AND asc2.status IN ('approved','published')
     WHERE c.academic_year_id=?
     GROUP BY c.id, c.name, g.name, g.sequence
     ORDER BY g.sequence, c.name"
);
$classPerf->execute([$ayId,$ayId]);
$classPerf = $classPerf->fetchAll();

// ── Subject performance ───────────────────────────────────────
$subjectPerf = $pdo->prepare(
    "SELECT sub.name subject_name, sub.category,
            COUNT(DISTINCT asc2.student_id) students,
            ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1) avg_pct,
            SUM(CASE WHEN asc2.marks_obtained/asc2.max_marks*100 >= 50 THEN 1 ELSE 0 END) passing,
            COUNT(asc2.id) scores
     FROM assessment_scores asc2
     JOIN subjects sub ON sub.id=asc2.subject_id
     WHERE asc2.academic_year_id=? AND asc2.max_marks>0
       AND asc2.status IN ('approved','published')
     GROUP BY sub.id, sub.name, sub.category
     ORDER BY avg_pct DESC"
);
$subjectPerf->execute([$ayId]);
$subjectPerf = $subjectPerf->fetchAll();

// ── Teacher workload & submission ────────────────────────────
$teacherWork = $pdo->prepare(
    "SELECT CONCAT(u.first_name,' ',u.last_name) teacher_name,
            u.email,
            COUNT(DISTINCT ta.class_id)   classes_assigned,
            COUNT(DISTINCT ta.subject_id) subjects_assigned,
            SUM(CASE WHEN asc2.status IN ('submitted','approved','published') THEN 1 ELSE 0 END) scores_submitted,
            SUM(CASE WHEN asc2.status='draft' THEN 1 ELSE 0 END) scores_draft,
            ROUND(AVG(CASE WHEN asc2.max_marks>0 AND asc2.status IN ('approved','published')
                           THEN asc2.marks_obtained/asc2.max_marks*100 END),1) class_avg
     FROM teachers t
     JOIN users u ON u.id=t.user_id
     LEFT JOIN teacher_assignments ta ON ta.teacher_id=t.id AND ta.academic_year_id=?
     LEFT JOIN assessment_scores asc2 ON asc2.entered_by=u.id AND asc2.academic_year_id=?
     WHERE t.status='Active'
     GROUP BY u.id, u.first_name, u.last_name, u.email
     ORDER BY classes_assigned DESC, scores_submitted DESC"
);
$teacherWork->execute([$ayId,$ayId]);
$teacherWork = $teacherWork->fetchAll();

// ── At-risk students (below 50% avg or >20% absent) ──────────
$atRisk = $pdo->prepare(
    "SELECT s.id, s.student_id student_code,
            CONCAT(s.first_name,' ',s.last_name) student_name,
            g.name grade_name, c.name class_name,
            ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1) avg_pct,
            ROUND(SUM(att.status='Present')/NULLIF(COUNT(att.id),0)*100,1) att_rate,
            COUNT(DISTINCT dr.id) discipline_count
     FROM students s
     LEFT JOIN grades g ON g.id=s.current_grade_id
     LEFT JOIN classes c ON c.id=s.current_class_id
     LEFT JOIN assessment_scores asc2 ON asc2.student_id=s.id
           AND asc2.academic_year_id=? AND asc2.max_marks>0
           AND asc2.status IN ('approved','published')
     LEFT JOIN attendance att ON att.student_id=s.id AND att.academic_year_id=?
     LEFT JOIN discipline_records dr ON dr.student_id=s.id AND dr.academic_year_id=?
     WHERE s.academic_year_id=? AND s.status='Active'
     GROUP BY s.id, s.student_id, s.first_name, s.last_name, g.name, c.name
     HAVING (avg_pct IS NOT NULL AND avg_pct < 50)
          OR (att_rate IS NOT NULL AND att_rate < 75)
          OR discipline_count >= 2
     ORDER BY avg_pct ASC LIMIT 40"
);
$atRisk->execute([$ayId,$ayId,$ayId,$ayId]);
$atRisk = $atRisk->fetchAll();

// ── Marks submission status ───────────────────────────────────
$marksStatus = $pdo->prepare(
    "SELECT status, COUNT(*) cnt
     FROM assessment_scores WHERE academic_year_id=? GROUP BY status ORDER BY cnt DESC"
);
$marksStatus->execute([$ayId]);
$marksStatus = $marksStatus->fetchAll(PDO::FETCH_KEY_PAIR);

// ── Attendance trend (last 10 school days) ────────────────────
try {
    $attTrend = $pdo->prepare(
        "SELECT date, ROUND(SUM(status='Present')/COUNT(*)*100,1) rate
         FROM attendance WHERE academic_year_id=?
         GROUP BY date ORDER BY date DESC LIMIT 10"
    );
    $attTrend->execute([$ayId]);
    $attTrend = array_reverse($attTrend->fetchAll());
} catch (Throwable $e) { $attTrend = []; }

// ── Summary stats ─────────────────────────────────────────────
$totalClasses    = count($classPerf);
$totalSubjects   = count($subjectPerf);
$totalTeachers   = count($teacherWork);
$overallAvg      = $totalSubjects > 0 ? round(array_sum(array_column($subjectPerf,'avg_pct')) / $totalSubjects, 1) : 0;
$atRiskCount     = count($atRisk);
$pendingMarks    = $marksStatus['submitted'] ?? 0 + ($marksStatus['resubmitted'] ?? 0);
$maxClassAvg     = max(array_column($classPerf,'avg_pct') ?: [1]);

function avgColor(float $v): string {
    return $v >= 70 ? 'var(--green)' : ($v >= 50 ? 'var(--warning)' : 'var(--error)');
}
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">Academic Supervision <span></span></div>
    <h1>Academic Overview</h1>
    <p><?= e($ay) ?></p>
  </div>
  <div style="display:flex;gap:8px">
    <a href="<?= BASE_URL ?>/admin/marks_approval.php" class="button button-secondary">
      ✏️ Marks Approval<?= $pendingMarks > 0 ? " ($pendingMarks)" : '' ?>
    </a>
    <a href="<?= BASE_URL ?>/admin/broadsheets.php" class="button button-secondary">📃 Broadsheets</a>
  </div>
</div>

<!-- Summary metrics -->
<div class="metric-grid" style="margin-bottom:24px">
  <div class="metric-card">
    <div class="metric-top"><span>Overall Average</span><div class="metric-icon">📊</div></div>
    <strong style="color:<?= avgColor($overallAvg) ?>"><?= $overallAvg ?>%</strong>
    <small><i></i>All approved marks</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Classes</span><div class="metric-icon">🏫</div></div>
    <strong><?= $totalClasses ?></strong><small><i></i><?= e($ay) ?></small>
  </div>
  <div class="metric-card <?= $pendingMarks > 0 ? 'finance-metrics' : '' ?>">
    <div class="metric-top"><span>Marks Awaiting Review</span><div class="metric-icon">✏️</div></div>
    <strong style="color:<?= $pendingMarks > 0 ? 'var(--error)' : 'inherit' ?>"><?= $pendingMarks ?></strong>
    <small><i></i>Submitted by teachers</small>
  </div>
  <div class="metric-card <?= $atRiskCount > 0 ? 'finance-metrics' : '' ?>">
    <div class="metric-top"><span>At-Risk Students</span><div class="metric-icon">⚠️</div></div>
    <strong style="color:<?= $atRiskCount > 0 ? 'var(--error)' : 'inherit' ?>"><?= $atRiskCount ?></strong>
    <small><i></i>Low marks / attendance</small>
  </div>
</div>

<!-- Tabs -->
<div class="tab-bar" style="margin-bottom:16px">
  <a href="?tab=performance"  class="tab-btn <?= $tab==='performance' ?'active':'' ?>">📊 Class Performance</a>
  <a href="?tab=subjects"     class="tab-btn <?= $tab==='subjects'    ?'active':'' ?>">📚 Subject Analysis</a>
  <a href="?tab=teachers"     class="tab-btn <?= $tab==='teachers'    ?'active':'' ?>">👩‍🏫 Teacher Supervision</a>
  <a href="?tab=atrisk"       class="tab-btn <?= $tab==='atrisk'      ?'active':'' ?>">⚠️ At-Risk Students (<?= $atRiskCount ?>)</a>
  <a href="?tab=marks_status" class="tab-btn <?= $tab==='marks_status'?'active':'' ?>">📋 Marks Pipeline</a>
</div>

<?php if ($tab === 'performance'): ?>
<!-- ── CLASS PERFORMANCE ──────────────────────────────────────── -->
<?php if (empty($classPerf)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">🏫</div>
  <p style="color:var(--ink-soft)">No class data for <?= e($ay) ?></p>
</div>
<?php else: ?>
<div class="table-wrap">
  <table>
    <thead>
      <tr><th>Class</th><th>Grade</th><th>Students</th><th>Average</th><th>Passing</th><th>Visual</th><th>Action</th></tr>
    </thead>
    <tbody>
      <?php foreach ($classPerf as $c):
        $pct = $c['avg_pct'] ?? 0;
        $w   = $maxClassAvg > 0 ? min(100, round($pct / $maxClassAvg * 100)) : 0;
        $col = avgColor($pct);
        $passPct = $c['students'] > 0 ? round(($c['passing'] / max(1,$c['students'])) * 100) : 0;
      ?>
      <tr>
        <td><strong><?= e($c['class_name']) ?></strong></td>
        <td class="muted"><?= e($c['grade_name']) ?></td>
        <td><?= $c['students'] ?></td>
        <td>
          <?php if ($c['score_count'] > 0): ?>
          <strong style="color:<?= $col ?>"><?= $pct ?>%</strong>
          <?php else: ?><span class="muted">No data</span><?php endif; ?>
        </td>
        <td>
          <?php if ($c['score_count'] > 0): ?>
          <span style="color:<?= $passPct >= 70 ? 'var(--green)' : 'var(--warning)' ?>"><?= $passPct ?>%</span>
          <?php else: ?><span class="muted">—</span><?php endif; ?>
        </td>
        <td style="min-width:100px">
          <div style="height:7px;background:var(--bg2);border-radius:4px;overflow:hidden">
            <div style="width:<?= $w ?>%;height:100%;background:<?= $col ?>;border-radius:4px"></div>
          </div>
        </td>
        <td>
          <a href="<?= BASE_URL ?>/admin/broadsheets.php?class_id=<?= $c['id'] ?>" class="filter-button button-sm">📃 Broadsheet</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Attendance trend -->
<?php if (!empty($attTrend)): ?>
<div class="panel" style="padding:22px;margin-top:20px">
  <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">📆 Attendance Trend — Last <?= count($attTrend) ?> School Days</h3>
  <div style="display:flex;align-items:flex-end;gap:6px;height:64px">
    <?php foreach ($attTrend as $d):
      $h = max(6, round($d['rate'] / 100 * 56));
      $c = $d['rate'] >= 80 ? 'var(--green)' : ($d['rate'] >= 70 ? 'var(--warning)' : 'var(--error)');
    ?>
    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px">
      <span style="font-size:9px;color:var(--ink-soft)"><?= $d['rate'] ?>%</span>
      <div style="width:100%;height:<?= $h ?>px;background:<?= $c ?>;border-radius:3px 3px 0 0"></div>
      <span style="font-size:8px;color:var(--ink-soft);white-space:nowrap"><?= date('M d', strtotime($d['date'])) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php elseif ($tab === 'subjects'): ?>
<!-- ── SUBJECT ANALYSIS ───────────────────────────────────────── -->
<?php if (empty($subjectPerf)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">📚</div>
  <p style="color:var(--ink-soft)">No subject performance data for <?= e($ay) ?></p>
</div>
<?php else: ?>
<div class="table-wrap">
  <table>
    <thead>
      <tr><th>Subject</th><th>Category</th><th>Students</th><th>Average</th><th>Passing %</th><th>Visual</th></tr>
    </thead>
    <tbody>
      <?php
      $maxSubjAvg = max(array_column($subjectPerf,'avg_pct') ?: [1]);
      foreach ($subjectPerf as $s):
        $pct = $s['avg_pct'] ?? 0;
        $w   = $maxSubjAvg > 0 ? min(100, round($pct / $maxSubjAvg * 100)) : 0;
        $col = avgColor($pct);
        $passPct = $s['students'] > 0 ? round($s['passing'] / $s['students'] * 100) : 0;
      ?>
      <tr>
        <td><strong><?= e($s['subject_name']) ?></strong></td>
        <td><span class="badge badge-grey"><?= e(ucfirst($s['category'] ?? 'general')) ?></span></td>
        <td><?= $s['students'] ?></td>
        <td><strong style="color:<?= $col ?>"><?= $pct ?>%</strong></td>
        <td>
          <span style="color:<?= $passPct >= 70 ? 'var(--green)' : ($passPct >= 50 ? 'var(--warning)' : 'var(--error)') ?>"><?= $passPct ?>%</span>
        </td>
        <td style="min-width:100px">
          <div style="height:7px;background:var(--bg2);border-radius:4px;overflow:hidden">
            <div style="width:<?= $w ?>%;height:100%;background:<?= $col ?>;border-radius:4px"></div>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Bottom performers -->
<?php
$bottomSubjects = array_filter($subjectPerf, fn($s) => ($s['avg_pct'] ?? 100) < 50);
if (!empty($bottomSubjects)): ?>
<div class="alert alert-warn" style="margin-top:16px">
  ⚠️ <strong><?= count($bottomSubjects) ?> subject<?= count($bottomSubjects)!==1?'s':'' ?></strong> with average below 50% — consider curriculum support or teacher intervention.
</div>
<?php endif; endif; ?>

<?php elseif ($tab === 'teachers'): ?>
<!-- ── TEACHER SUPERVISION ────────────────────────────────────── -->
<?php if (empty($teacherWork)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:36px;margin-bottom:12px">👩‍🏫</div>
  <p style="color:var(--ink-soft)">No teacher data available.</p>
</div>
<?php else: ?>
<div class="table-wrap">
  <table>
    <thead>
      <tr><th>Teacher</th><th>Classes</th><th>Subjects</th><th>Marks Submitted</th><th>Draft Remaining</th><th>Class Average</th><th>Status</th></tr>
    </thead>
    <tbody>
      <?php foreach ($teacherWork as $t):
        $total = $t['scores_submitted'] + $t['scores_draft'];
        $submittedPct = $total > 0 ? round($t['scores_submitted'] / $total * 100) : 0;
        $statusLabel = $t['scores_draft'] > 0 ? 'Has drafts' : ($t['scores_submitted'] > 0 ? 'Submitted' : 'No data');
        $statusClass = $t['scores_draft'] > 0 ? 'pending' : ($t['scores_submitted'] > 0 ? 'approved' : 'new-s');
      ?>
      <tr>
        <td>
          <strong><?= e($t['teacher_name']) ?></strong>
          <div style="font-size:11px;color:var(--ink-faint)"><?= e($t['email']) ?></div>
        </td>
        <td><strong><?= $t['classes_assigned'] ?></strong></td>
        <td><?= $t['subjects_assigned'] ?></td>
        <td>
          <strong style="color:var(--green)"><?= $t['scores_submitted'] ?></strong>
          <?php if ($total > 0): ?>
          <span style="font-size:11px;color:var(--ink-soft)"> (<?= $submittedPct ?>%)</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($t['scores_draft'] > 0): ?>
          <strong style="color:var(--warning)"><?= $t['scores_draft'] ?></strong>
          <?php else: ?>
          <span class="muted">—</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($t['class_avg']): ?>
          <strong style="color:<?= avgColor($t['class_avg']) ?>"><?= $t['class_avg'] ?>%</strong>
          <?php else: ?><span class="muted">—</span><?php endif; ?>
        </td>
        <td><span class="status <?= $statusClass ?>"><?= $statusLabel ?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Teachers with draft marks alert -->
<?php $draftTeachers = array_filter($teacherWork, fn($t) => $t['scores_draft'] > 0); ?>
<?php if (!empty($draftTeachers)): ?>
<div class="alert alert-warn" style="margin-top:14px">
  ⚠️ <strong><?= count($draftTeachers) ?> teacher<?= count($draftTeachers)!==1?'s':'' ?></strong> still have draft marks not yet submitted. Follow up required.
</div>
<?php endif; endif; ?>

<?php elseif ($tab === 'atrisk'): ?>
<!-- ── AT-RISK STUDENTS ───────────────────────────────────────── -->
<?php if (empty($atRisk)): ?>
<div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <div style="font-size:40px;margin-bottom:12px">✅</div>
  <h3 style="margin-bottom:8px">No at-risk students</h3>
  <p style="color:var(--ink-soft)">All students are performing above the 50% threshold with acceptable attendance.</p>
</div>
<?php else: ?>
<div class="alert alert-warn" style="margin-bottom:14px">
  <strong>⚠️ <?= count($atRisk) ?> at-risk student<?= count($atRisk)!==1?'s':'' ?></strong> identified — below 50% average, below 75% attendance, or 2+ discipline incidents.
</div>
<div class="table-wrap">
  <table>
    <thead>
      <tr><th>Student</th><th>Grade / Class</th><th>Average</th><th>Attendance</th><th>Discipline</th><th>Risk Flags</th></tr>
    </thead>
    <tbody>
      <?php foreach ($atRisk as $s):
        $flags = [];
        if ($s['avg_pct'] !== null && $s['avg_pct'] < 50)  $flags[] = '<span style="color:var(--error);font-size:11px">📊 Low marks</span>';
        if ($s['att_rate'] !== null && $s['att_rate'] < 75) $flags[] = '<span style="color:var(--error);font-size:11px">📆 Low attendance</span>';
        if ($s['discipline_count'] >= 2)                    $flags[] = '<span style="color:var(--warning);font-size:11px">⚖️ Discipline</span>';
      ?>
      <tr>
        <td>
          <strong><?= e($s['student_name']) ?></strong>
          <div style="font-size:11px;color:var(--ink-faint)"><?= e($s['student_code']) ?></div>
        </td>
        <td class="muted"><?= e($s['grade_name'] ?? '—') ?><?= $s['class_name'] ? ' / '.e($s['class_name']) : '' ?></td>
        <td>
          <?php if ($s['avg_pct'] !== null): ?>
          <strong style="color:<?= avgColor($s['avg_pct']) ?>"><?= $s['avg_pct'] ?>%</strong>
          <?php else: ?><span class="muted">No marks</span><?php endif; ?>
        </td>
        <td>
          <?php if ($s['att_rate'] !== null): ?>
          <strong style="color:<?= $s['att_rate'] >= 75 ? 'var(--green)' : 'var(--error)' ?>"><?= $s['att_rate'] ?>%</strong>
          <?php else: ?><span class="muted">—</span><?php endif; ?>
        </td>
        <td>
          <?php if ($s['discipline_count'] > 0): ?>
          <span style="color:var(--warning);font-weight:700"><?= $s['discipline_count'] ?> incident<?= $s['discipline_count']!==1?'s':'' ?></span>
          <?php else: ?><span class="muted">—</span><?php endif; ?>
        </td>
        <td><?= implode(' ', $flags) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php elseif ($tab === 'marks_status'): ?>
<!-- ── MARKS PIPELINE ─────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px" class="vp-grid">
  <!-- Marks by status -->
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">📋 Marks Status Pipeline — <?= e($ay) ?></h3>
    <?php
    $statusOrder = ['draft','submitted','resubmitted','approved','returned','rejected','published'];
    $statusIcons = ['draft'=>'✏️','submitted'=>'📤','resubmitted'=>'🔄','approved'=>'✅','returned'=>'↩️','rejected'=>'❌','published'=>'🌐'];
    $statusClrs  = ['draft'=>'var(--ink-faint)','submitted'=>'var(--primary)','resubmitted'=>'var(--warning)','approved'=>'var(--green)','returned'=>'var(--warning)','rejected'=>'var(--error)','published'=>'var(--green)'];
    $totalScores = array_sum($marksStatus);
    foreach ($statusOrder as $st):
      $cnt = $marksStatus[$st] ?? 0;
      if ($cnt === 0) continue;
      $w = $totalScores > 0 ? round($cnt / $totalScores * 100) : 0;
    ?>
    <div style="margin-bottom:10px">
      <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:3px">
        <span><?= $statusIcons[$st]??'•' ?> <?= ucfirst($st) ?></span>
        <strong style="color:<?= $statusClrs[$st]??'inherit' ?>"><?= number_format($cnt) ?> (<?= $w ?>%)</strong>
      </div>
      <div style="height:7px;background:var(--bg2);border-radius:4px;overflow:hidden">
        <div style="width:<?= $w ?>%;height:100%;background:<?= $statusClrs[$st]??'var(--primary)' ?>;border-radius:4px"></div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($marksStatus)): ?><p style="color:var(--ink-faint);font-size:13px">No marks data yet.</p><?php endif; ?>
  </div>

  <!-- Quick actions -->
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">🚀 Quick Actions</h3>
    <?php
    $submitted   = $marksStatus['submitted']    ?? 0;
    $resubmitted = $marksStatus['resubmitted']  ?? 0;
    $draft       = $marksStatus['draft']        ?? 0;
    $approved    = $marksStatus['approved']     ?? 0;
    $published   = $marksStatus['published']    ?? 0;
    $items = [
      [$submitted + $resubmitted, 'Marks awaiting your review',    BASE_URL.'/admin/marks_approval.php',   'var(--error)',   '✏️'],
      [$draft,                    'Draft marks not yet submitted',  BASE_URL.'/admin/results.php',          'var(--warning)', '📝'],
      [$approved,                 'Approved marks ready to publish',BASE_URL.'/admin/report_cards.php',    'var(--green)',   '📑'],
      [$published,                'Marks published this year',      BASE_URL.'/admin/broadsheets.php',     'var(--primary)', '🌐'],
    ];
    foreach ($items as [$cnt,$label,$href,$color,$ico]): ?>
    <a href="<?= e($href) ?>"
       style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--line-soft);text-decoration:none;color:var(--ink)">
      <span style="font-size:1.2rem"><?= $ico ?></span>
      <div style="flex:1">
        <strong style="font-size:13px"><?= $label ?></strong>
      </div>
      <strong style="color:<?= $color ?>;font-size:1.1rem"><?= number_format($cnt) ?></strong>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Pending batches -->
<?php
$pendingBatches = $pdo->prepare(
    "SELECT c.name cname, sub.name sname, ac.name cfg_name,
            COUNT(*) scores, ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1) avg_pct,
            u.name teacher_name, MAX(asc2.updated_at) last_updated
     FROM assessment_scores asc2
     JOIN classes c ON c.id=asc2.class_id
     JOIN subjects sub ON sub.id=asc2.subject_id
     JOIN assessment_configs ac ON ac.id=asc2.assessment_config_id
     LEFT JOIN users u ON u.id=asc2.entered_by
     WHERE asc2.status IN ('submitted','resubmitted') AND asc2.academic_year_id=?
     GROUP BY asc2.class_id, asc2.subject_id, asc2.assessment_config_id
     ORDER BY last_updated ASC LIMIT 20"
);
$pendingBatches->execute([$ayId]);
$pendingBatches = $pendingBatches->fetchAll();
if (!empty($pendingBatches)): ?>
<div class="panel" style="padding:0;overflow:hidden">
  <div style="padding:14px 18px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center">
    <h3 style="font-weight:700;font-size:14px">Pending Review — <?= count($pendingBatches) ?> batch<?= count($pendingBatches)!==1?'es':'' ?></h3>
    <a href="<?= BASE_URL ?>/admin/marks_approval.php" class="button button-primary button-sm">Review All →</a>
  </div>
  <div class="table-wrap" style="border:none">
    <table>
      <thead><tr><th>Class</th><th>Subject</th><th>Assessment</th><th>Students</th><th>Avg</th><th>Teacher</th><th>Waiting</th></tr></thead>
      <tbody>
        <?php foreach ($pendingBatches as $b):
          $waiting = round((time() - strtotime($b['last_updated'])) / 3600);
        ?>
        <tr>
          <td><strong><?= e($b['cname']) ?></strong></td>
          <td><?= e($b['sname']) ?></td>
          <td class="muted"><?= e($b['cfg_name']) ?></td>
          <td><?= $b['scores'] ?></td>
          <td><strong style="color:<?= avgColor($b['avg_pct']??0) ?>"><?= $b['avg_pct']??'—' ?>%</strong></td>
          <td class="muted"><?= e($b['teacher_name']??'—') ?></td>
          <td class="muted"><?= $waiting > 48 ? '<span style="color:var(--error)">' : '' ?><?= $waiting ?>h<?= $waiting > 48 ? '</span>' : '' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<style>@media(max-width:640px){.vp-grid{grid-template-columns:1fr !important}}</style>
<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
