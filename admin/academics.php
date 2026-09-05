<?php
// ============================================================
// KARN HIGH SCHOOL — Admin: Academic Setup
// ============================================================
$pageTitle   = 'Academics';
$activeAdmin = 'academics';
require_once dirname(__DIR__) . '/includes/admin_header.php';

$pdo = db();

// Grade-level summary from students table
$gradeBreakdown = $pdo->query(
    "SELECT grade, COUNT(*) AS cnt FROM students WHERE status='Active' GROUP BY grade ORDER BY FIELD(grade,'ABC/KG','Grade 1','Grade 2','Grade 3','Grade 4','Grade 5','Grade 6','Grade 7','Grade 8','Grade 9','Grade 10','Grade 11','Grade 12')"
)->fetchAll();

$totalActive = array_sum(array_column($gradeBreakdown, 'cnt'));

$metrics = [
    ['14', 'Grade levels',  '&#128218;'],
    ['48', 'Teachers',      '&#128101;'],
    ['32', 'Subjects',      '&#128203;'],
    ['2',  'Semesters',     '&#128197;'],
];
?>

<div class="page-heading">
  <div>
    <div class="eyebrow">Academics <span></span></div>
    <h1>Academic setup</h1>
    <p>Manage KARN HIGH SCHOOL classes, grades and academic year.</p>
  </div>
</div>

<!-- Metric cards -->
<div class="academic-cards">
  <?php foreach ($metrics as [$val, $label, $icon]): ?>
  <div class="academic-card">
    <span class="ac-icon"><?= $icon ?></span>
    <strong><?= $val ?></strong>
    <span><?= $label ?></span>
  </div>
  <?php endforeach; ?>
</div>

<!-- Current academic year -->
<div class="panel-inner-heading">
  <h3>Current academic year</h3>
  <span class="status approved">Active</span>
</div>
<div class="year-row" style="margin-bottom:28px">
  <div><strong>2026/2027</strong><span>Aug 18, 2026 – Jun 26, 2027</span></div>
  <div><span>Current term</span><strong>Semester 1 &middot; Period 1</strong></div>
  <div><span>Enrolled</span><strong><?= number_format((int)$totalActive) ?> students</strong></div>
</div>

<!-- Grade breakdown -->
<div class="panel-inner-heading" style="margin-top:8px">
  <h3>Enrolment by grade</h3>
  <span class="muted" style="font-size:13px"><?= number_format((int)$totalActive) ?> active students</span>
</div>
<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Grade level</th>
        <th>Active students</th>
        <th>Proportion</th>
        <th>Progress</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($gradeBreakdown)): ?>
        <tr><td colspan="4" class="muted" style="text-align:center;padding:32px">No student data yet.</td></tr>
      <?php else: ?>
        <?php foreach ($gradeBreakdown as $row):
          $pct = $totalActive > 0 ? round(($row['cnt'] / $totalActive) * 100) : 0;
        ?>
        <tr>
          <td><strong><?= e($row['grade']) ?></strong></td>
          <td><?= number_format((int)$row['cnt']) ?></td>
          <td class="muted"><?= $pct ?>%</td>
          <td>
            <div style="background:var(--line);border-radius:4px;height:8px;width:180px;overflow:hidden">
              <div style="background:var(--primary);height:100%;border-radius:4px;width:<?= $pct ?>%"></div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div style="margin-top:24px;padding:24px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
  <p style="font-size:14.5px;color:var(--ink-soft);line-height:1.7">
    <strong>&#128218; Coming soon:</strong> Full class management, subject assignments, timetable builder, and teacher allocation are planned for the next release. Contact your system administrator to add custom academic year and semester configurations.
  </p>
</div>

<?php include dirname(__DIR__) . '/includes/admin_footer.php'; ?>
