<?php
// ============================================================
// Parent Portal — Child Profile
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('parent');

$activePage = 'profile';
$ayId = currentAcademicYearId();
$ay   = currentAcademicYearName();

include __DIR__.'/includes/resolve_child.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Child Profile — Parent Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <div class="page-heading">
    <div><h1>Child Profile</h1><p>Personal and academic information on record</p></div>
  </div>

  <?php if (empty($children)): ?>
  <div class="alert alert-warning">No children linked to your account. Please contact the school registrar.</div>

  <?php elseif (!$child): ?>
  <div class="alert alert-warning">Child not found. <a href="<?= BASE_URL ?>/portal/parent/">Go back</a>.</div>

  <?php else:
    $ini = strtoupper(substr($child['first_name'],0,1).substr($child['last_name'],0,1));

    // Guardian info
    try {
        $gInfo = db()->prepare(
            "SELECT g.* FROM guardians g
             JOIN student_guardians sg ON sg.guardian_id = g.id
             WHERE sg.student_id = ? LIMIT 1"
        );
        $gInfo->execute([$child['id']]);
        $gInfo = $gInfo->fetch();
    } catch (Throwable $e) { $gInfo = null; }
  ?>

  <!-- Avatar banner -->
  <div class="panel" style="display:flex;align-items:center;gap:20px;padding:24px;margin-bottom:20px;flex-wrap:wrap">
    <div class="avatar" style="width:60px;height:60px;font-size:20px;font-weight:800;flex-shrink:0"><?= e($ini) ?></div>
    <div>
      <h2 style="font-size:20px;font-weight:800;margin-bottom:2px">
        <?= e(trim($child['first_name'].($child['middle_name']?' '.$child['middle_name']:'').' '.$child['last_name'])) ?>
      </h2>
      <p style="font-size:13px;color:var(--ink-soft)">
        <?= e($child['grade_name'] ?? '—') ?>
        <?= $child['class_name'] ? ' / '.e($child['class_name']) : '' ?>
        &nbsp;·&nbsp; ID: <strong><?= e($child['student_id']) ?></strong>
      </p>
    </div>
    <span class="status <?= ($child['status']??'')=='Active'?'approved':'pending' ?>" style="margin-left:auto">
      <?= e($child['status'] ?? 'Unknown') ?>
    </span>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="profile-grid">

    <!-- Personal -->
    <div class="panel" style="padding:22px">
      <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--line)">👤 Personal Information</h3>
      <?php foreach ([
        'Date of Birth'  => isset($child['date_of_birth']) ? date('F d, Y', strtotime($child['date_of_birth'])) : null,
        'Gender'         => $child['gender']      ?? null,
        'Nationality'    => $child['nationality'] ?? null,
        'County'         => $child['county']      ?? null,
        'Community'      => $child['community']   ?? null,
        'Phone'          => $child['phone']        ?? null,
        'Email'          => $child['email']        ?? null,
      ] as $label => $val): ?>
      <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--line-soft);font-size:13px">
        <span style="color:var(--ink-soft);font-weight:600;min-width:120px"><?= $label ?></span>
        <span style="text-align:right"><?= $val ? e($val) : '<span style="color:var(--ink-faint)">—</span>' ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <div>
      <!-- Academic -->
      <div class="panel" style="padding:22px;margin-bottom:20px">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--line)">🎓 Academic Information</h3>
        <?php foreach ([
          'Student ID'      => $child['student_id']     ?? null,
          'Grade'           => $child['grade_name']     ?? null,
          'Class'           => $child['class_name']     ?? null,
          'Enrolled'        => isset($child['enrollment_date']) ? date('M d, Y', strtotime($child['enrollment_date'])) : null,
          'Previous School' => $child['previous_school'] ?? null,
          'Academic Year'   => $ay,
        ] as $label => $val): ?>
        <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--line-soft);font-size:13px">
          <span style="color:var(--ink-soft);font-weight:600;min-width:120px"><?= $label ?></span>
          <span style="text-align:right"><?= $val ? e($val) : '<span style="color:var(--ink-faint)">—</span>' ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if ($gInfo): ?>
      <!-- Guardian -->
      <div class="panel" style="padding:22px">
        <h3 style="font-size:14px;font-weight:700;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--line)">👨‍👩‍👧 Your Guardian Record</h3>
        <?php foreach ([
          'Name'         => $gInfo['full_name'] ?? ($gInfo['name'] ?? null),
          'Relationship' => $gInfo['relationship'] ?? null,
          'Phone'        => $gInfo['phone']        ?? null,
          'Email'        => $gInfo['email']        ?? null,
        ] as $label => $val): ?>
        <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--line-soft);font-size:13px">
          <span style="color:var(--ink-soft);font-weight:600;min-width:120px"><?= $label ?></span>
          <span style="text-align:right"><?= $val ? e($val) : '<span style="color:var(--ink-faint)">—</span>' ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

  </div>

  <p style="margin-top:14px;font-size:12px;color:var(--ink-faint);text-align:center">
    To update any information, please contact the school registrar.
  </p>
  <?php endif; ?>

</div>
</div>
<style>@media(max-width:640px){.profile-grid{grid-template-columns:1fr !important}}</style>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
