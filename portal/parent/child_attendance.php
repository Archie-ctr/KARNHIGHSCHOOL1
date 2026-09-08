<?php
// ============================================================
// Parent Portal — Child Attendance
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('parent');

$activePage = 'attendance';
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();

include __DIR__.'/includes/resolve_child.php';

$records = [];
$stats   = ['p'=>0,'a'=>0,'l'=>0,'e'=>0,'t'=>0];
$rate    = 0;

if ($child) {
    try {
        $r = db()->prepare(
            "SELECT date, status, remarks FROM attendance
             WHERE student_id=? AND academic_year_id=? ORDER BY date DESC"
        );
        $r->execute([$child['id'], $ayId]);
        $records = $r->fetchAll();

        $s = db()->prepare(
            "SELECT SUM(status='Present') p, SUM(status='Absent') a,
                    SUM(status='Late') l, SUM(status='Excused') e, COUNT(*) t
             FROM attendance WHERE student_id=? AND academic_year_id=?"
        );
        $s->execute([$child['id'], $ayId]);
        $stats = $s->fetch();
        $rate  = ($stats['t'] > 0) ? round($stats['p'] / $stats['t'] * 100, 1) : 0;
    } catch (Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Attendance — Parent Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <div class="page-heading">
    <div>
      <h1><?= $child ? e($child['first_name'])."'s" : "Child's" ?> Attendance</h1>
      <p><?= e($ay) ?></p>
    </div>
  </div>

  <?php if (empty($children)): ?>
  <div class="alert alert-warning">No children linked. Please contact the school registrar.</div>

  <?php elseif (!$child): ?>
  <div class="alert alert-warning">Child not found. <a href="<?= BASE_URL ?>/portal/parent/">Go back</a>.</div>

  <?php else: ?>

  <!-- Stats -->
  <div class="metric-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px">
    <div class="metric-card">
      <div class="metric-top"><span>Present</span><div class="metric-icon" style="background:var(--green-soft);color:var(--green)">✓</div></div>
      <strong style="color:var(--green)"><?= $stats['p'] ?? 0 ?></strong>
      <small><i></i>Days present</small>
    </div>
    <div class="metric-card">
      <div class="metric-top"><span>Absent</span><div class="metric-icon" style="background:var(--error-soft);color:var(--error)">✗</div></div>
      <strong style="color:var(--error)"><?= $stats['a'] ?? 0 ?></strong>
      <small><i></i>Days absent</small>
    </div>
    <div class="metric-card">
      <div class="metric-top"><span>Late</span><div class="metric-icon" style="background:var(--warning-soft);color:var(--warning)">⏰</div></div>
      <strong style="color:var(--warning)"><?= $stats['l'] ?? 0 ?></strong>
      <small><i></i>Times late</small>
    </div>
    <div class="metric-card">
      <div class="metric-top"><span>Attendance Rate</span><div class="metric-icon">📊</div></div>
      <strong style="color:<?= $rate >= 80 ? 'var(--green)' : 'var(--error)' ?>"><?= $rate ?>%</strong>
      <small><i></i><?= $rate >= 80 ? 'Good' : 'Below 80%' ?></small>
    </div>
  </div>

  <!-- Progress bar -->
  <div class="panel" style="padding:20px 22px;margin-bottom:20px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <strong style="font-size:13px">Attendance Rate &mdash; <?= e($ay) ?></strong>
      <span class="status <?= $rate >= 80 ? 'approved' : 'warning' ?>"><?= $rate ?>%</span>
    </div>
    <div class="progress-bar">
      <div class="progress-fill <?= $rate >= 80 ? 'green' : '' ?>" style="width:<?= $rate ?>%"></div>
    </div>
    <?php if ($rate < 80): ?>
    <p style="font-size:12px;color:var(--error);margin-top:8px">
      ⚠️ <?= e($child['first_name']) ?>'s attendance is below 80%. Please contact the class teacher.
    </p>
    <?php endif; ?>
  </div>

  <!-- Records -->
  <?php if (empty($records)): ?>
  <div style="text-align:center;padding:40px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
    <div style="font-size:36px;margin-bottom:10px">📆</div>
    <p style="color:var(--ink-soft)">No attendance records yet for <?= e($ay) ?>.</p>
  </div>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Date</th><th>Day</th><th>Status</th><th>Remarks</th></tr></thead>
      <tbody>
        <?php foreach ($records as $r):
          $cls = match($r['status']) {
              'Present' => 'approved', 'Absent' => 'warning',
              'Late'    => 'pending',  default  => 'new-s',
          };
        ?>
        <tr>
          <td><?= date('M d, Y', strtotime($r['date'])) ?></td>
          <td class="muted"><?= date('l', strtotime($r['date'])) ?></td>
          <td><span class="status <?= $cls ?>"><?= e($r['status']) ?></span></td>
          <td class="muted"><?= e($r['remarks'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
  <?php endif; ?>

</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
