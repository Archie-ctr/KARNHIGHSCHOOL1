<?php
$pageTitle   = 'Student Statistics';
$activeAdmin = 'student_statistics';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['sys_admin','super_admin','school_admin','principal','registrar','vice_principal']);

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();

// ── All-years list for switcher ───────────────────────────────
$allYears = $pdo->query("SELECT id,name,is_current FROM academic_years ORDER BY start_date DESC")->fetchAll();

// ── Core stats for current year ───────────────────────────────
$totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE academic_year_id=$ayId")->fetchColumn();
$activeStudents= (int)$pdo->query("SELECT COUNT(*) FROM students WHERE academic_year_id=$ayId AND status='Active'")->fetchColumn();
$newThisYear   = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE academic_year_id=$ayId AND YEAR(admission_date)=YEAR(NOW())")->fetchColumn();

// Gender breakdown
$genderStats = $pdo->prepare(
    "SELECT gender, COUNT(*) cnt FROM students WHERE academic_year_id=? AND status='Active' GROUP BY gender ORDER BY cnt DESC"
);
$genderStats->execute([$ayId]); $genderStats = $genderStats->fetchAll();

// Status breakdown
$statusStats = $pdo->prepare(
    "SELECT status, COUNT(*) cnt FROM students WHERE academic_year_id=? GROUP BY status ORDER BY cnt DESC"
);
$statusStats->execute([$ayId]); $statusStats = $statusStats->fetchAll();

// By grade
$gradeStats = $pdo->prepare(
    "SELECT g.name grade_name, g.sequence,
            COUNT(s.id) total,
            SUM(s.gender='Male') males,
            SUM(s.gender='Female') females,
            SUM(s.status='Active') active
     FROM students s
     JOIN grades g ON g.id=s.current_grade_id
     WHERE s.academic_year_id=?
     GROUP BY g.id, g.name, g.sequence
     ORDER BY g.sequence"
);
$gradeStats->execute([$ayId]); $gradeStats = $gradeStats->fetchAll();

// By class
$classStats = $pdo->prepare(
    "SELECT c.name class_name, g.name grade_name,
            COUNT(s.id) total,
            SUM(s.status='Active') active
     FROM students s
     JOIN classes c ON c.id=s.current_class_id
     JOIN grades  g ON g.id=s.current_grade_id
     WHERE s.academic_year_id=?
     GROUP BY c.id, c.name, g.name
     ORDER BY g.sequence, c.name"
);
$classStats->execute([$ayId]); $classStats = $classStats->fetchAll();

// By county
$countyStats = $pdo->prepare(
    "SELECT COALESCE(NULLIF(county,''),'Unknown') county, COUNT(*) cnt
     FROM students WHERE academic_year_id=? AND status='Active'
     GROUP BY county ORDER BY cnt DESC LIMIT 15"
);
$countyStats->execute([$ayId]); $countyStats = $countyStats->fetchAll();

// Attendance summary
try {
    $attSummary = $pdo->prepare(
        "SELECT
            ROUND(SUM(status='Present')/COUNT(*)*100,1) present_rate,
            SUM(status='Present') present,
            SUM(status='Absent')  absent,
            COUNT(*) total
         FROM attendance WHERE academic_year_id=?"
    );
    $attSummary->execute([$ayId]); $attSummary = $attSummary->fetch();
} catch (Throwable $e) { $attSummary = null; }

// Year-on-year enrollment trend
$trend = $pdo->query(
    "SELECT ay.name, COUNT(s.id) cnt
     FROM academic_years ay
     LEFT JOIN students s ON s.academic_year_id=ay.id AND s.status='Active'
     GROUP BY ay.id, ay.name ORDER BY ay.start_date ASC"
)->fetchAll();

// Helper: simple inline bar chart
function barPct(int $val, int $max): int { return $max > 0 ? (int)round($val/$max*100) : 0; }
$maxGradeTotal = max(array_column($gradeStats, 'total') ?: [1]);
$maxTrend      = max(array_column($trend, 'cnt') ?: [1]);
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">Student Administration <span></span></div>
    <h1>Student Statistics</h1>
    <p><?= e($ay) ?></p>
  </div>
  <a href="<?= BASE_URL ?>/admin/reports.php" class="button button-secondary">📥 Export Data</a>
</div>

<!-- Top metrics -->
<div class="metric-grid" style="margin-bottom:24px">
  <div class="metric-card">
    <div class="metric-top"><span>Total Enrolled</span><div class="metric-icon">🎓</div></div>
    <strong><?= number_format($totalStudents) ?></strong><small><i></i><?= e($ay) ?></small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Active Students</span><div class="metric-icon" style="background:var(--green-soft);color:var(--green)">✓</div></div>
    <strong style="color:var(--green)"><?= number_format($activeStudents) ?></strong>
    <small><i></i><?= $totalStudents > 0 ? round($activeStudents/$totalStudents*100,1) : 0 ?>% of enrolled</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>New This Year</span><div class="metric-icon">🆕</div></div>
    <strong><?= number_format($newThisYear) ?></strong><small><i></i>Fresh admissions</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Attendance Rate</span><div class="metric-icon">📆</div></div>
    <strong><?= $attSummary && $attSummary['total']>0 ? $attSummary['present_rate'].'%' : '—' ?></strong>
    <small><i></i>Overall this year</small>
  </div>
</div>

<!-- Two-column layout -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px" class="stats-grid">

  <!-- Gender breakdown -->
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:16px">👫 Gender Breakdown</h3>
    <?php if (empty($genderStats)): ?>
    <p style="color:var(--ink-faint);font-size:13px">No data for <?= e($ay) ?>.</p>
    <?php else:
      $maxG = max(array_column($genderStats,'cnt'));
      foreach ($genderStats as $g): $pct = barPct($g['cnt'],$maxG); ?>
    <div style="margin-bottom:12px">
      <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
        <span><?= e($g['gender'] ?: 'Not specified') ?></span>
        <strong><?= number_format($g['cnt']) ?> (<?= $totalStudents>0?round($g['cnt']/$totalStudents*100,1):0 ?>%)</strong>
      </div>
      <div style="height:8px;background:var(--bg2);border-radius:4px;overflow:hidden">
        <div style="width:<?= $pct ?>%;height:100%;background:<?= $g['gender']==='Male'?'#3b5bdb':'#e64980' ?>;border-radius:4px"></div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Status breakdown -->
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:16px">📊 Status Breakdown</h3>
    <?php
    $statusIcons = ['Active'=>'✅','Inactive'=>'⏸️','Graduated'=>'🎓','Transferred'=>'➡️','Withdrawn'=>'❌','Suspended'=>'⚠️'];
    $statusClr   = ['Active'=>'var(--green)','Inactive'=>'var(--ink-soft)','Graduated'=>'var(--primary)','Transferred'=>'var(--warning)','Withdrawn'=>'var(--error)','Suspended'=>'var(--error)'];
    foreach ($statusStats as $st): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--line-soft);font-size:13px">
      <span><?= $statusIcons[$st['status']]??'•' ?> <?= e($st['status']) ?></span>
      <strong style="color:<?= $statusClr[$st['status']]??'inherit' ?>"><?= number_format($st['cnt']) ?></strong>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- By Grade -->
<div class="panel" style="padding:22px;margin-bottom:20px">
  <h3 style="font-weight:700;font-size:14px;margin-bottom:16px">📚 Enrollment by Grade — <?= e($ay) ?></h3>
  <?php if (empty($gradeStats)): ?>
  <p style="color:var(--ink-faint);font-size:13px">No grade data for this year.</p>
  <?php else: ?>
  <div class="table-wrap" style="border:none">
    <table>
      <thead><tr><th>Grade</th><th>Total</th><th>Active</th><th>Male</th><th>Female</th><th>Chart</th></tr></thead>
      <tbody>
        <?php foreach ($gradeStats as $g): $pct = barPct($g['total'], $maxGradeTotal); ?>
        <tr>
          <td><strong><?= e($g['grade_name']) ?></strong></td>
          <td><strong><?= number_format($g['total']) ?></strong></td>
          <td style="color:var(--green)"><?= number_format($g['active']) ?></td>
          <td class="muted"><?= number_format($g['males']??0) ?></td>
          <td class="muted"><?= number_format($g['females']??0) ?></td>
          <td style="width:120px">
            <div style="height:8px;background:var(--bg2);border-radius:4px;overflow:hidden">
              <div style="width:<?= $pct ?>%;height:100%;background:var(--primary);border-radius:4px"></div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- By Class + By County side by side -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px" class="stats-grid">

  <!-- Classes -->
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:16px">🏫 Enrollment by Class</h3>
    <?php if (empty($classStats)): ?>
    <p style="color:var(--ink-faint);font-size:13px">No class data.</p>
    <?php else: ?>
    <div class="table-wrap" style="border:none;max-height:280px;overflow-y:auto">
      <table>
        <thead><tr><th>Class</th><th>Grade</th><th>Total</th><th>Active</th></tr></thead>
        <tbody>
          <?php foreach ($classStats as $c): ?>
          <tr>
            <td><strong><?= e($c['class_name']) ?></strong></td>
            <td class="muted"><?= e($c['grade_name']) ?></td>
            <td><?= $c['total'] ?></td>
            <td style="color:var(--green)"><?= $c['active'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- By County -->
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:16px">📍 Students by County (Active)</h3>
    <?php if (empty($countyStats)): ?>
    <p style="color:var(--ink-faint);font-size:13px">No county data.</p>
    <?php else:
      $maxC = max(array_column($countyStats,'cnt'));
      foreach ($countyStats as $c): $pct = barPct($c['cnt'],$maxC); ?>
    <div style="margin-bottom:8px">
      <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:3px">
        <span><?= e($c['county']) ?></span>
        <strong><?= $c['cnt'] ?></strong>
      </div>
      <div style="height:6px;background:var(--bg2);border-radius:3px;overflow:hidden">
        <div style="width:<?= $pct ?>%;height:100%;background:var(--primary);border-radius:3px"></div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- Year-on-Year Trend -->
<?php if (!empty($trend)): ?>
<div class="panel" style="padding:22px;margin-bottom:20px">
  <h3 style="font-weight:700;font-size:14px;margin-bottom:16px">📈 Enrollment Trend — Year on Year</h3>
  <div style="display:flex;align-items:flex-end;gap:12px;height:100px;padding-bottom:4px">
    <?php foreach ($trend as $yr): $h = $maxTrend>0 ? max(8, round($yr['cnt']/$maxTrend*90)) : 8; ?>
    <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
      <span style="font-size:10px;font-weight:700;color:var(--primary)"><?= $yr['cnt'] ?: '' ?></span>
      <div style="width:100%;height:<?= $h ?>px;background:var(--primary);border-radius:3px 3px 0 0;opacity:<?= $yr['cnt']>0?.9:.2 ?>"></div>
      <span style="font-size:9px;color:var(--ink-soft);text-align:center;white-space:nowrap"><?= e($yr['name']) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<style>@media(max-width:680px){.stats-grid{grid-template-columns:1fr !important}}</style>
<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
