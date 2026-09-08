<?php
// ============================================================
// Student Portal — My Subjects
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('student');

$pdo    = db();
$user   = currentUser();
$activePage = 'subjects';
$ayId   = currentAcademicYearId();
$ay     = currentAcademicYearName();

$student = $pdo->prepare(
    "SELECT s.id, s.first_name, s.current_class_id, s.current_grade_id,
            g.name grade_name, c.name class_name
     FROM students s
     LEFT JOIN grades  g ON g.id = s.current_grade_id
     LEFT JOIN classes c ON c.id = s.current_class_id
     WHERE s.user_id = ? LIMIT 1"
);
$student->execute([$user['id']]);
$student = $student->fetch();
if (!$student) { redirect(BASE_URL.'/portal/student/'); }

// Subjects for this class/grade
$subjects = [];
if ($student['current_class_id']) {
    try {
        $stmt = $pdo->prepare(
            "SELECT s.id, s.name, s.code, s.description, s.category,
                    CONCAT(u.first_name,' ',u.last_name) teacher_name,
                    t.specialization
             FROM subjects s
             LEFT JOIN teacher_assignments ta ON ta.subject_id = s.id
                   AND ta.class_id = ? AND ta.academic_year_id = ?
             LEFT JOIN teachers t  ON t.id   = ta.teacher_id
             LEFT JOIN users    u  ON u.id   = t.user_id
             WHERE s.is_active = 1
               AND (s.grade_id = ? OR s.grade_id IS NULL)
             ORDER BY s.category, s.name"
        );
        $stmt->execute([$student['current_class_id'], $ayId, $student['current_grade_id']]);
        $subjects = $stmt->fetchAll();
    } catch (Throwable $e) {
        // Fallback: just get all subjects for grade
        try {
            $stmt = $pdo->prepare(
                "SELECT id, name, code, description, category
                 FROM subjects WHERE is_active=1
                 AND (grade_id=? OR grade_id IS NULL) ORDER BY category, name"
            );
            $stmt->execute([$student['current_grade_id']]);
            $subjects = $stmt->fetchAll();
        } catch (Throwable $e2) { $subjects = []; }
    }
}

// Group by category
$byCategory = [];
foreach ($subjects as $s) {
    $byCategory[$s['category'] ?? 'General'][] = $s;
}

$catLabels = [
    'core'           => '📖 Core Subjects',
    'elective'       => '🎨 Elective Subjects',
    'extracurricular'=> '⚽ Extracurricular',
    'General'        => '📚 Subjects',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>My Subjects — Student Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <div class="page-heading">
    <div>
      <h1>My Subjects</h1>
      <p><?= e($student['grade_name']??'') ?><?= $student['class_name'] ? ' / '.e($student['class_name']) : '' ?> &mdash; <?= e($ay) ?></p>
    </div>
    <div style="background:var(--primary-soft);color:var(--primary);font-weight:700;font-size:14px;padding:10px 18px;border-radius:var(--radius-sm)">
      <?= count($subjects) ?> Subject<?= count($subjects)!==1?'s':'' ?>
    </div>
  </div>

  <?php if (empty($subjects)): ?>
  <div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
    <div style="font-size:40px;margin-bottom:12px">📚</div>
    <h3 style="margin-bottom:6px">No subjects found</h3>
    <p style="color:var(--ink-soft)">Your subject list will appear here once the school sets up your class for <?= e($ay) ?>.</p>
  </div>

  <?php else: ?>
  <?php foreach ($byCategory as $cat => $subs):
    $catLabel = $catLabels[$cat] ?? ('📚 '.ucfirst($cat));
  ?>
  <div style="margin-bottom:28px">
    <h3 style="font-size:13px;font-weight:700;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.08em;margin-bottom:12px"><?= $catLabel ?></h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px">
      <?php foreach ($subs as $sub): ?>
      <div class="panel" style="padding:18px 20px;display:flex;flex-direction:column;gap:4px">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px">
          <strong style="font-size:15px;color:var(--ink)"><?= e($sub['name']) ?></strong>
          <?php if (!empty($sub['code'])): ?>
          <span style="font-size:11px;font-weight:700;color:var(--ink-faint);background:var(--bg);padding:2px 8px;border-radius:20px;white-space:nowrap"><?= e($sub['code']) ?></span>
          <?php endif; ?>
        </div>
        <?php if (!empty($sub['teacher_name'])): ?>
        <p style="font-size:12px;color:var(--primary);font-weight:600">👩‍🏫 <?= e($sub['teacher_name']) ?></p>
        <?php endif; ?>
        <?php if (!empty($sub['description'])): ?>
        <p style="font-size:12px;color:var(--ink-soft);line-height:1.55;margin-top:2px"><?= e(mb_substr($sub['description'],0,100)).(mb_strlen($sub['description'])>100?'…':'') ?></p>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
