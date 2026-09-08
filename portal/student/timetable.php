<?php
// ============================================================
// Student Portal — My Timetable
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('student');

$pdo        = db();
$user       = currentUser();
$activePage = 'timetable';
$ayId       = currentAcademicYearId();

$student = $pdo->prepare(
    "SELECT id, current_class_id FROM students WHERE user_id=? LIMIT 1"
);
$student->execute([$user['id']]);
$student = $student->fetch();

$timetable = [];
$days      = [1=>'Monday', 2=>'Tuesday', 3=>'Wednesday', 4=>'Thursday', 5=>'Friday'];

if ($student && $student['current_class_id']) {
    try {
        $tt = $pdo->prepare(
            "SELECT tt.*, s.name sname,
                    CONCAT(u.first_name,' ',u.last_name) tname,
                    tt.room
             FROM timetable tt
             JOIN subjects s ON s.id = tt.subject_id
             LEFT JOIN teachers t ON t.id = tt.teacher_id
             LEFT JOIN users    u ON u.id = t.user_id
             WHERE tt.class_id=? AND tt.academic_year_id=?
             ORDER BY tt.day_of_week, tt.period_slot"
        );
        $tt->execute([$student['current_class_id'], $ayId]);
        foreach ($tt->fetchAll() as $row) {
            $timetable[$row['day_of_week']][$row['period_slot']] = $row;
        }
    } catch (Throwable $e) { $timetable = []; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Timetable — Student Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <div class="page-heading">
    <div>
      <h1>My Timetable</h1>
      <p>Weekly class schedule</p>
    </div>
  </div>

  <?php if (empty($timetable)): ?>
  <div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
    <div style="font-size:40px;margin-bottom:12px">📅</div>
    <h3 style="margin-bottom:6px">Timetable not yet set up</h3>
    <p style="color:var(--ink-soft)">Your class timetable will appear here once it has been set up by the school.</p>
  </div>

  <?php else: ?>
  <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:600px">
      <thead>
        <tr>
          <th style="padding:11px 14px;background:var(--primary);color:#fff;border-right:1px solid rgba(255,255,255,.15);text-align:left;white-space:nowrap">Period</th>
          <?php foreach ($days as $d): ?>
          <th style="padding:11px 14px;background:var(--primary);color:#fff;border-right:1px solid rgba(255,255,255,.15);text-align:center"><?= $d ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php for ($p = 1; $p <= 8; $p++): ?>
        <tr style="border-bottom:1px solid var(--line-soft)">
          <td style="padding:10px 14px;font-weight:700;background:var(--bg);border-right:1px solid var(--line);white-space:nowrap;font-size:12px;color:var(--ink-soft)">
            Period <?= $p ?>
          </td>
          <?php foreach ([1,2,3,4,5] as $d):
            $cell = $timetable[$d][$p] ?? null;
          ?>
          <td style="padding:10px 12px;border-right:1px solid var(--line-soft);vertical-align:top">
            <?php if ($cell): ?>
            <div style="font-weight:700;font-size:13px;color:var(--ink)"><?= e($cell['sname']) ?></div>
            <?php if (!empty($cell['tname'])): ?>
            <div style="font-size:11px;color:var(--ink-soft);margin-top:2px">👩‍🏫 <?= e($cell['tname']) ?></div>
            <?php endif; ?>
            <?php if (!empty($cell['room'])): ?>
            <div style="font-size:11px;color:var(--primary);margin-top:1px">📍 <?= e($cell['room']) ?></div>
            <?php endif; ?>
            <?php else: ?>
            <span style="color:var(--line);font-size:12px">—</span>
            <?php endif; ?>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endfor; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
