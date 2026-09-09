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

  <!-- ── PROFILE UPDATE REQUEST ────────────────────────────── -->
  <div class="panel" style="padding:22px;margin-top:20px">
    <h3 style="font-size:15px;font-weight:700;margin-bottom:4px">📝 Request Information Update</h3>
    <p style="font-size:13px;color:var(--ink-soft);margin-bottom:16px">
      Official information changes require approval from the Registrar. Submit a request below and the office will review it.
    </p>
    <?php
    // Show pending requests
    try {
        $pending=$pdo->query("SELECT * FROM approval_requests WHERE requested_by={$user['id']} AND module='student_profile' ORDER BY created_at DESC LIMIT 5")->fetchAll();
    } catch (Throwable $e) { $pending=[]; }

    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_request'])) {
        try {
            verifyCsrf();
            $field=trim($_POST['field_name']??'');
            $newVal=trim($_POST['new_value']??'');
            $reason=trim($_POST['reason']??'');
            if ($field && $newVal) {
                try {
                    $pdo->prepare("INSERT INTO approval_requests (module,record_type,record_id,requested_by,status,priority,title,description,new_value) VALUES ('student_profile','student',?,?,'pending','normal',?,?,?)")
                        ->execute([$student['id'],$user['id'],"Profile update: $field","Student requests change to $field. Reason: $reason",$newVal]);
                    flash('success','Update request submitted. The Registrar will review your request.');
                } catch (Throwable $e) { flash('error','Failed to submit: '.$e->getMessage()); }
                redirect(BASE_URL.'/portal/student/profile.php');
            }
        } catch (Throwable $e) {}
    }
    ?>
    <?php foreach(getFlash() as $f):?><div class="alert alert-<?=$f['type']?>"><?=e($f['message'])?></div><?php endforeach;?>

    <?php if (!empty($pending)): ?>
    <div style="margin-bottom:16px">
      <h4 style="font-size:13px;font-weight:700;margin-bottom:8px;color:var(--ink-soft)">YOUR PENDING REQUESTS</h4>
      <?php foreach ($pending as $pr): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:9px 12px;background:var(--bg2);border-radius:var(--radius-sm);margin-bottom:6px;font-size:13px">
        <span><?=e($pr['title'])?></span>
        <span class="status <?=$pr['status']==='approved'?'approved':($pr['status']==='rejected'?'warning':'pending')?>" style="font-size:11px"><?=ucfirst($pr['status'])?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="post">
      <?=csrfField()?>
      <input type="hidden" name="update_request" value="1"/>
      <div class="form-grid">
        <div class="form-group">
          <label>Field to Update <span style="color:var(--error)">*</span></label>
          <select name="field_name" required>
            <option value="">— Select field —</option>
            <?php foreach(['Phone Number','Email Address','Home Address','County','District','Community','Emergency Contact'] as $f):?>
            <option value="<?=$f?>"><?=$f?></option>
            <?php endforeach;?>
          </select>
        </div>
        <div class="form-group">
          <label>New Value <span style="color:var(--error)">*</span></label>
          <input type="text" name="new_value" required placeholder="Enter the correct information"/>
        </div>
        <div class="form-group" style="grid-column:1/-1">
          <label>Reason / Explanation</label>
          <input type="text" name="reason" placeholder="Why does this need to be updated?"/>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:12px;margin-top:8px">
        <button type="submit" class="button button-primary">📤 Submit Update Request</button>
        <span style="font-size:12px;color:var(--ink-faint)">The Registrar will verify and apply approved changes.</span>
      </div>
    </form>
  </div>

</div>
</div>
<style>
@media(max-width:640px){ .profile-grid{ grid-template-columns:1fr !important } }
</style>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
