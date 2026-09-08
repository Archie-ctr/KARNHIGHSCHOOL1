<?php
$pageTitle   = 'Executive Reports';
$activeAdmin = 'executive_reports';
require_once dirname(__DIR__).'/includes/admin_header.php';
requireRole(['sys_admin','super_admin','school_admin','principal','vice_principal']);

$pdo  = db();
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();

// ── Core metrics ──────────────────────────────────────────────
$totalStudents    = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status='Active' AND academic_year_id=$ayId")->fetchColumn();
$totalTeachers    = (int)$pdo->query("SELECT COUNT(*) FROM teachers WHERE status='Active'")->fetchColumn();
$totalClasses     = (int)$pdo->query("SELECT COUNT(*) FROM classes WHERE academic_year_id=$ayId")->fetchColumn();
$totalSubjects    = (int)$pdo->query("SELECT COUNT(*) FROM subjects WHERE is_active=1")->fetchColumn();
$totalStaff       = (int)$pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE u.is_active=1 AND r.name NOT IN ('student','parent','applicant')")->fetchColumn();

// Academic performance
try {
    $overallAvg = (float)$pdo->prepare(
        "SELECT ROUND(AVG(marks_obtained/max_marks*100),1) FROM assessment_scores
         WHERE academic_year_id=? AND max_marks>0 AND status IN ('approved','published')"
    )->execute([$ayId]) ? $pdo->query(
        "SELECT ROUND(AVG(marks_obtained/max_marks*100),1) FROM assessment_scores
         WHERE academic_year_id=$ayId AND max_marks>0 AND status IN ('approved','published')"
    )->fetchColumn() : 0;
} catch (Throwable $e) { $overallAvg = 0; }

// Performance by grade
$gradePerformance = $pdo->prepare(
    "SELECT g.name grade_name, g.sequence,
            COUNT(DISTINCT s.id) students,
            ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1) avg_pct,
            SUM(asc2.marks_obtained/asc2.max_marks*100 >= 50) passing,
            COUNT(asc2.id) scores
     FROM students s
     LEFT JOIN assessment_scores asc2 ON asc2.student_id=s.id
       AND asc2.academic_year_id=? AND asc2.max_marks>0 AND asc2.status IN ('approved','published')
     JOIN grades g ON g.id=s.current_grade_id
     WHERE s.academic_year_id=? AND s.status='Active'
     GROUP BY g.id, g.name, g.sequence ORDER BY g.sequence"
);
$gradePerformance->execute([$ayId,$ayId]);
$gradePerformance = $gradePerformance->fetchAll();

// Performance by subject
$subjectPerformance = $pdo->prepare(
    "SELECT sub.name subject_name,
            COUNT(DISTINCT asc2.student_id) students,
            ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1) avg_pct
     FROM assessment_scores asc2
     JOIN subjects sub ON sub.id=asc2.subject_id
     WHERE asc2.academic_year_id=? AND asc2.max_marks>0 AND asc2.status IN ('approved','published')
     GROUP BY sub.id, sub.name ORDER BY avg_pct DESC LIMIT 12"
);
$subjectPerformance->execute([$ayId]);
$subjectPerformance = $subjectPerformance->fetchAll();

// Attendance overview
try {
    $attData = $pdo->prepare(
        "SELECT ROUND(SUM(status='Present')/COUNT(*)*100,1) rate,
                SUM(status='Present') present,
                SUM(status='Absent')  absent,
                SUM(status='Late')    late,
                COUNT(*) total
         FROM attendance WHERE academic_year_id=?"
    );
    $attData->execute([$ayId]); $attData = $attData->fetch();
} catch (Throwable $e) { $attData = null; }

// Attendance by grade
try {
    $attByGrade = $pdo->prepare(
        "SELECT g.name grade_name,
                ROUND(SUM(a.status='Present')/COUNT(*)*100,1) rate,
                COUNT(*) total
         FROM attendance a
         JOIN students s ON s.id=a.student_id
         JOIN grades g ON g.id=s.current_grade_id
         WHERE a.academic_year_id=?
         GROUP BY g.id, g.name, g.sequence ORDER BY g.sequence"
    );
    $attByGrade->execute([$ayId]);
    $attByGrade = $attByGrade->fetchAll();
} catch (Throwable $e) { $attByGrade = []; }

// Discipline summary
$discSummary = $pdo->prepare(
    "SELECT COUNT(*) total,
            SUM(resolved=0) open,
            SUM(resolved=1) resolved
     FROM discipline_records WHERE academic_year_id=?"
);
$discSummary->execute([$ayId]);
$discSummary = $discSummary->fetch();

// Discipline by category
$discByCategory = $pdo->prepare(
    "SELECT category, COUNT(*) cnt FROM discipline_records WHERE academic_year_id=?
     GROUP BY category ORDER BY cnt DESC LIMIT 8"
);
$discByCategory->execute([$ayId]);
$discByCategory = $discByCategory->fetchAll();

// Financial overview
$finLRD = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='LRD' AND academic_year_id=$ayId")->fetchColumn();
$finUSD = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE currency='USD' AND academic_year_id=$ayId")->fetchColumn();
$payCount  = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE academic_year_id=$ayId")->fetchColumn();
$paidStudents = (int)$pdo->query("SELECT COUNT(DISTINCT student_id) FROM payments WHERE academic_year_id=$ayId")->fetchColumn();

// Monthly collections
$monthlyFinance = $pdo->prepare(
    "SELECT DATE_FORMAT(payment_date,'%b %Y') mon, SUM(amount) total
     FROM payments WHERE academic_year_id=? AND currency='LRD'
     GROUP BY DATE_FORMAT(payment_date,'%Y-%m') ORDER BY payment_date ASC LIMIT 12"
);
$monthlyFinance->execute([$ayId]);
$monthlyFinance = $monthlyFinance->fetchAll();

// Staff performance proxies
$teacherMarksStats = $pdo->prepare(
    "SELECT CONCAT(u.first_name,' ',u.last_name) teacher_name,
            COUNT(DISTINCT asc2.class_id, asc2.subject_id) assignments_covered,
            ROUND(AVG(asc2.marks_obtained/asc2.max_marks*100),1) avg_class_score,
            COUNT(asc2.id) scores_entered
     FROM assessment_scores asc2
     JOIN users u ON u.id=asc2.entered_by
     WHERE asc2.academic_year_id=? AND asc2.max_marks>0 AND asc2.status IN ('submitted','approved','published')
     GROUP BY u.id ORDER BY assignments_covered DESC, scores_entered DESC LIMIT 10"
);
$teacherMarksStats->execute([$ayId]);
$teacherMarksStats = $teacherMarksStats->fetchAll();

// Admission pipeline
$admPipeline = $pdo->prepare(
    "SELECT status, COUNT(*) cnt FROM applications WHERE academic_year_id=? GROUP BY status ORDER BY cnt DESC"
);
$admPipeline->execute([$ayId]);
$admPipeline = $admPipeline->fetchAll();

// Report cards published
$rcPublished  = (int)$pdo->query("SELECT COUNT(*) FROM report_cards WHERE status='published' AND academic_year_id=$ayId")->fetchColumn();
$rcGenerated  = (int)$pdo->query("SELECT COUNT(*) FROM report_cards WHERE academic_year_id=$ayId")->fetchColumn();

// Promotion summary
try {
    $promotionStats = $pdo->prepare(
        "SELECT status, COUNT(*) cnt FROM promotion_records WHERE academic_year_id=? GROUP BY status"
    );
    $promotionStats->execute([$ayId]);
    $promotionStats = $promotionStats->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Throwable $e) { $promotionStats = []; }

// Helpers
function sparkBar(float $val, float $max, string $color='var(--primary)'): string {
    $w = $max > 0 ? min(100, round($val/$max*100)) : 0;
    return "<div style='height:6px;background:var(--bg2);border-radius:3px;overflow:hidden;margin-top:3px'><div style='width:{$w}%;height:100%;background:{$color};border-radius:3px'></div></div>";
}

$maxGradeAvg  = max(array_column($gradePerformance,'avg_pct') ?: [1]);
$maxSubjAvg   = max(array_column($subjectPerformance,'avg_pct') ?: [1]);
$maxDisc      = max(array_column($discByCategory,'cnt') ?: [1]);
$maxMonthly   = max(array_column($monthlyFinance,'total') ?: [1]);
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">Principal <span></span></div>
    <h1>Executive Reports</h1>
    <p>School-wide performance overview — <?= e($ay) ?></p>
  </div>
  <a href="<?= BASE_URL ?>/admin/reports.php" class="button button-secondary">📥 Data Exports</a>
</div>

<!-- Top KPIs -->
<div class="metric-grid" style="margin-bottom:24px">
  <div class="metric-card">
    <div class="metric-top"><span>Overall Academic Avg</span><div class="metric-icon">📊</div></div>
    <strong style="color:<?= $overallAvg>=70?'var(--green)':($overallAvg>=50?'var(--warning)':'var(--error)') ?>"><?= $overallAvg ?>%</strong>
    <small><i></i>Approved marks</small>
  </div>
  <div class="metric-card <?= $attData&&$attData['rate']<80?'finance-metrics':'' ?>">
    <div class="metric-top"><span>Attendance Rate</span><div class="metric-icon">📆</div></div>
    <strong style="color:<?= $attData?($attData['rate']>=80?'var(--green)':'var(--error)'):'inherit' ?>"><?= $attData?$attData['rate'].'%':'—' ?></strong>
    <small><i></i>Whole year</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>LRD Collected</span><div class="metric-icon">💰</div></div>
    <strong>LRD <?= number_format($finLRD/1000,1) ?>K</strong>
    <small><i></i><?= $paidStudents ?> students paid</small>
  </div>
  <div class="metric-card <?= ($discSummary['open']??0)>5?'finance-metrics':'' ?>">
    <div class="metric-top"><span>Open Discipline Cases</span><div class="metric-icon">⚖️</div></div>
    <strong style="color:<?= ($discSummary['open']??0)>5?'var(--error)':'inherit' ?>"><?= $discSummary['open'] ?? 0 ?></strong>
    <small><i></i><?= $discSummary['total']??0 ?> total this year</small>
  </div>
  <div class="metric-top" style="display:none"></div>
  <div class="metric-card">
    <div class="metric-top"><span>Report Cards Published</span><div class="metric-icon">📑</div></div>
    <strong><?= $rcPublished ?></strong>
    <small><i></i><?= $rcGenerated ?> generated total</small>
  </div>
  <div class="metric-card">
    <div class="metric-top"><span>Total Enrollment</span><div class="metric-icon">🎓</div></div>
    <strong><?= number_format($totalStudents) ?></strong>
    <small><i></i><?= $totalClasses ?> classes · <?= $totalTeachers ?> teachers</small>
  </div>
</div>

<!-- Main dashboard grid -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px" class="exec-grid">

  <!-- Academic: Grade performance -->
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:4px">📚 Academic Performance by Grade</h3>
    <p style="font-size:12px;color:var(--ink-soft);margin-bottom:14px"><?= e($ay) ?> · Approved/published marks</p>
    <?php if (empty($gradePerformance)): ?>
    <p style="color:var(--ink-faint);font-size:13px">No marks data yet.</p>
    <?php else: foreach ($gradePerformance as $g):
      $avgColor = !$g['avg_pct']?'var(--ink-faint)':($g['avg_pct']>=70?'var(--green)':($g['avg_pct']>=50?'var(--warning)':'var(--error)'));
    ?>
    <div style="margin-bottom:10px">
      <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:2px">
        <span><?= e($g['grade_name']) ?> <span style="color:var(--ink-soft)">(<?= $g['students'] ?> students)</span></span>
        <strong style="color:<?= $avgColor ?>"><?= $g['avg_pct'] ? $g['avg_pct'].'%' : 'No data' ?></strong>
      </div>
      <?= sparkBar((float)($g['avg_pct']??0), 100, $avgColor) ?>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Attendance by Grade -->
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:4px">📆 Attendance by Grade</h3>
    <p style="font-size:12px;color:var(--ink-soft);margin-bottom:14px">Attendance rate — <?= e($ay) ?></p>
    <?php if ($attData && $attData['total'] > 0): ?>
    <div style="display:flex;gap:16px;margin-bottom:14px;padding:10px;background:var(--bg2);border-radius:var(--radius-sm)">
      <div><strong style="font-size:1.3rem;color:var(--green)"><?= $attData['rate'] ?>%</strong><br><span style="font-size:11px;color:var(--ink-soft)">Overall</span></div>
      <div><strong style="font-size:1.3rem"><?= number_format($attData['present']) ?></strong><br><span style="font-size:11px;color:var(--ink-soft)">Present</span></div>
      <div><strong style="font-size:1.3rem;color:var(--error)"><?= number_format($attData['absent']) ?></strong><br><span style="font-size:11px;color:var(--ink-soft)">Absent</span></div>
    </div>
    <?php endif; ?>
    <?php foreach ($attByGrade as $a):
      $rateColor = $a['rate']>=90?'var(--green)':($a['rate']>=75?'var(--warning)':'var(--error)');
    ?>
    <div style="margin-bottom:8px">
      <div style="display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:2px">
        <span><?= e($a['grade_name']) ?></span>
        <strong style="color:<?= $rateColor ?>"><?= $a['rate'] ?>%</strong>
      </div>
      <?= sparkBar((float)$a['rate'], 100, $rateColor) ?>
    </div>
    <?php endforeach; ?>
    <?php if(empty($attByGrade)): ?><p style="color:var(--ink-faint);font-size:13px">No attendance data.</p><?php endif; ?>
  </div>

  <!-- Subject performance -->
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:4px">📖 Subject Performance</h3>
    <p style="font-size:12px;color:var(--ink-soft);margin-bottom:14px">Average score per subject (approved marks)</p>
    <?php if (empty($subjectPerformance)): ?>
    <p style="color:var(--ink-faint);font-size:13px">No subject data.</p>
    <?php else: foreach ($subjectPerformance as $s):
      $c = $s['avg_pct']>=70?'var(--green)':($s['avg_pct']>=50?'var(--warning)':'var(--error)');
    ?>
    <div style="margin-bottom:7px">
      <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:2px">
        <span><?= e($s['subject_name']) ?> <span style="color:var(--ink-soft)">(<?= $s['students'] ?> students)</span></span>
        <strong style="color:<?= $c ?>"><?= $s['avg_pct'] ?>%</strong>
      </div>
      <?= sparkBar((float)$s['avg_pct'], 100, $c) ?>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- Finance: Monthly collections -->
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:4px">💰 Monthly Fee Collections (LRD)</h3>
    <p style="font-size:12px;color:var(--ink-soft);margin-bottom:14px">Total: LRD <?= number_format($finLRD) ?> · <?= $payCount ?> transactions</p>
    <?php if (empty($monthlyFinance)): ?>
    <p style="color:var(--ink-faint);font-size:13px">No financial data.</p>
    <?php else:
      $monthMax = max(array_column($monthlyFinance,'total'));
      ?>
    <div style="display:flex;align-items:flex-end;gap:8px;height:80px;padding-bottom:4px">
      <?php foreach ($monthlyFinance as $m):
        $h = $monthMax > 0 ? max(8, round($m['total']/$monthMax*70)) : 8;
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:2px">
        <span style="font-size:9px;font-weight:700;color:var(--green)"><?= number_format($m['total']/1000,0) ?>K</span>
        <div style="width:100%;height:<?= $h ?>px;background:var(--green);border-radius:3px 3px 0 0;opacity:.85"></div>
        <span style="font-size:9px;color:var(--ink-soft);white-space:nowrap;transform:rotate(-30deg);transform-origin:top left;margin-top:2px"><?= $m['mon'] ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Discipline overview -->
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">⚖️ Discipline Overview</h3>
    <div style="display:flex;gap:16px;margin-bottom:16px;padding:10px;background:var(--bg2);border-radius:var(--radius-sm)">
      <div><strong style="font-size:1.3rem"><?= $discSummary['total']??0 ?></strong><br><span style="font-size:11px;color:var(--ink-soft)">Total</span></div>
      <div><strong style="font-size:1.3rem;color:var(--error)"><?= $discSummary['open']??0 ?></strong><br><span style="font-size:11px;color:var(--ink-soft)">Open</span></div>
      <div><strong style="font-size:1.3rem;color:var(--green)"><?= $discSummary['resolved']??0 ?></strong><br><span style="font-size:11px;color:var(--ink-soft)">Resolved</span></div>
    </div>
    <?php foreach ($discByCategory as $d): $w = round($d['cnt']/$maxDisc*100); ?>
    <div style="margin-bottom:7px">
      <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:2px">
        <span><?= e($d['category']) ?></span><strong><?= $d['cnt'] ?></strong>
      </div>
      <div style="height:6px;background:var(--bg2);border-radius:3px;overflow:hidden">
        <div style="width:<?= $w ?>%;height:100%;background:var(--error);border-radius:3px"></div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($discByCategory)): ?><p style="color:var(--ink-faint);font-size:13px">No discipline data.</p><?php endif; ?>
  </div>

  <!-- Staff/Teacher performance -->
  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:4px">👩‍🏫 Teacher Activity</h3>
    <p style="font-size:12px;color:var(--ink-soft);margin-bottom:14px">Marks submissions — <?= e($ay) ?></p>
    <?php if (empty($teacherMarksStats)): ?>
    <p style="color:var(--ink-faint);font-size:13px">No submission data.</p>
    <?php else: foreach ($teacherMarksStats as $t): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--line-soft);font-size:12.5px">
      <div>
        <strong><?= e($t['teacher_name']) ?></strong>
        <span style="color:var(--ink-soft);margin-left:6px"><?= $t['assignments_covered'] ?> assignments</span>
      </div>
      <span style="color:<?= $t['avg_class_score']>=70?'var(--green)':($t['avg_class_score']>=50?'var(--warning)':'var(--error)') ?>;font-weight:700">
        <?= $t['avg_class_score'] ? $t['avg_class_score'].'%' : '—' ?>
      </span>
    </div>
    <?php endforeach; endif; ?>
  </div>

</div>

<!-- Admission pipeline + Promotion summary side by side -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px" class="exec-grid">

  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">📋 Admissions Pipeline</h3>
    <?php foreach ($admPipeline as $a): ?>
    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--line-soft);font-size:12.5px">
      <span><?= e($a['status']) ?></span><strong><?= $a['cnt'] ?></strong>
    </div>
    <?php endforeach; ?>
    <?php if(empty($admPipeline)): ?><p style="color:var(--ink-faint);font-size:13px">No applications.</p><?php endif; ?>
    <a href="<?= BASE_URL ?>/admin/applications.php" class="lnk" style="font-size:12px;margin-top:10px">View applications →</a>
  </div>

  <div class="panel" style="padding:22px">
    <h3 style="font-weight:700;font-size:14px;margin-bottom:14px">⬆️ Promotion Summary</h3>
    <?php if (empty($promotionStats)): ?>
    <p style="color:var(--ink-faint);font-size:13px">No promotion decisions yet.</p>
    <?php else: foreach ($promotionStats as $status => $cnt): ?>
    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--line-soft);font-size:12.5px">
      <span><?= ucfirst($status) ?></span><strong><?= $cnt ?></strong>
    </div>
    <?php endforeach; endif; ?>
    <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
      <a href="<?= BASE_URL ?>/admin/promotion.php" class="button button-secondary button-sm">Manage Promotion</a>
      <a href="<?= BASE_URL ?>/admin/graduation.php" class="button button-secondary button-sm">Graduation</a>
    </div>
  </div>

</div>

<style>@media(max-width:680px){.exec-grid{grid-template-columns:1fr !important}}</style>
<?php require_once dirname(__DIR__).'/includes/admin_footer.php'; ?>
