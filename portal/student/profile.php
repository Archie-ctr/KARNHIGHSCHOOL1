<?php
// ============================================================
// Student Portal — My Profile
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('student');

$pdo    = db();
$user   = currentUser();
$activePage = 'profile';

$student = $pdo->prepare(
    "SELECT s.*,g.name grade_name,c.name class_name
     FROM students s
     LEFT JOIN grades  g ON g.id = s.current_grade_id
     LEFT JOIN classes c ON c.id = s.current_class_id
     WHERE s.user_id = ? LIMIT 1"
);
$student->execute([$user['id']]);
$student = $student->fetch();
if (!$student) { redirect(BASE_URL.'/portal/student/'); }

// Guardian
try {
    $guardian = $pdo->prepare(
        "SELECT g.* FROM guardians g
         JOIN student_guardians sg ON sg.guardian_id = g.id
         WHERE sg.student_id = ? LIMIT 1"
    );
    $guardian->execute([$student['id']]);
    $guardian = $guardian->fetch();
} catch (Throwable $e) { $guardian = null; }

$ini = strtoupper(substr($student['first_name'],0,1).substr($student['last_name'],0,1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>My Profile — Student Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <div class="page-heading">
    <div>
      <h1>My Profile</h1>
      <p>Your personal and academic information on record</p>
    </div>
  </div>

  <!-- Avatar + name banner -->
  <div class="panel" style="display:flex;align-items:center;gap:20px;padding:24px;margin-bottom:20px;flex-wrap:wrap">
    <div class="avatar" style="width:64px;height:64px;font-size:22px;font-weight:800;flex-shrink:0"><?= e($ini) ?></div>
    <div>
      <h2 style="font-size:20px;font-weight:800;margin-bottom:2px">
        <?= e(trim($student['first_name'].($student['middle_name']?' '.$student['middle_name']:'').' '.$student['last_name'])) ?>
      </h2>
      <p style="font-size:13px;color:var(--ink-soft)">
        Student ID: <strong><?= e($student['student_id']) ?></strong>
        &nbsp;·&nbsp;
        <?= e($student['grade_name'] ?? '—') ?>
        <?= $student['class_name'] ? ' / '.e($student['class_name']) : '' ?>
      </p>
    </div>
    <?php if ($student['status']): ?>
    <span class="status <?= $student['status']==='Active'?'approved':'pending' ?>" style="margin-left:auto">
      <?= e($student['status']) ?>
    </span>
    <?php endif; ?>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="profile-grid">

    <!-- Personal details -->
    <div class="panel" style="padding:22px">
      <h3 style="font-size:14px;font-weight:700;color:var(--ink);margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--line)">
        👤 Personal Information
      </h3>
      <?php
      $fields = [
        'Date of Birth'  => $student['date_of_birth'] ? date('F d, Y', strtotime($student['date_of_birth'])) : null,
        'Gender'         => $student['gender'] ?? null,
        'Nationality'    => $student['nationality'] ?? null,
        'County'         => $student['county'] ?? null,
        'District'       => $student['district'] ?? null,
        'Community'      => $student['community'] ?? null,
        'Address'        => $student['address'] ?? null,
        'Phone'          => $student['phone'] ?? null,
        'Email'          => $student['email'] ?? null,
      ];
      foreach ($fields as $label => $val): ?>
      <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:7px 0;border-bottom:1px solid var(--line-soft);font-size:13px">
        <span style="color:var(--ink-soft);font-weight:600;min-width:120px"><?= $label ?></span>
        <span style="text-align:right;color:var(--ink)"><?= $val ? e($val) : '<span style="color:var(--ink-faint)">—</span>' ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Academic details -->
    <div>
      <div class="panel" style="padding:22px;margin-bottom:20px">
        <h3 style="font-size:14px;font-weight:700;color:var(--ink);margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--line)">
          🎓 Academic Information
        </h3>
        <?php
        $acFields = [
          'Student ID'       => $student['student_id'] ?? null,
          'Grade'            => $student['grade_name'] ?? null,
          'Class'            => $student['class_name'] ?? null,
          'Enrollment Date'  => $student['enrollment_date'] ? date('F d, Y', strtotime($student['enrollment_date'])) : null,
          'Previous School'  => $student['previous_school'] ?? null,
          'Last Grade'       => $student['last_grade_completed'] ?? null,
        ];
        foreach ($acFields as $label => $val): ?>
        <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:7px 0;border-bottom:1px solid var(--line-soft);font-size:13px">
          <span style="color:var(--ink-soft);font-weight:600;min-width:120px"><?= $label ?></span>
          <span style="text-align:right;color:var(--ink)"><?= $val ? e($val) : '<span style="color:var(--ink-faint)">—</span>' ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Guardian -->
      <?php if ($guardian): ?>
      <div class="panel" style="padding:22px">
        <h3 style="font-size:14px;font-weight:700;color:var(--ink);margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--line)">
          👨‍👩‍👧 Parent / Guardian
        </h3>
        <?php
        $gFields = [
          'Name'         => $guardian['full_name'] ?? ($guardian['name'] ?? null),
          'Relationship' => $guardian['relationship'] ?? null,
          'Phone'        => $guardian['phone'] ?? null,
          'Email'        => $guardian['email'] ?? null,
        ];
        foreach ($gFields as $label => $val): ?>
        <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:7px 0;border-bottom:1px solid var(--line-soft);font-size:13px">
          <span style="color:var(--ink-soft);font-weight:600;min-width:120px"><?= $label ?></span>
          <span style="text-align:right;color:var(--ink)"><?= $val ? e($val) : '<span style="color:var(--ink-faint)">—</span>' ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

  </div>

  <p style="margin-top:16px;font-size:12px;color:var(--ink-faint);text-align:center">
    To update any information, please contact the school registrar.
  </p>

</div>
</div>
<style>
@media(max-width:640px){ .profile-grid{ grid-template-columns:1fr !important } }
</style>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
