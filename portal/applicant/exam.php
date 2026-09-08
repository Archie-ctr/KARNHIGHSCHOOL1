<?php
// ============================================================
// Applicant Portal — Entrance Examination
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('applicant');

$activePage = 'exam';
$user       = currentUser();

// Load application
try {
    $app = db()->prepare("SELECT * FROM applications WHERE user_id=? ORDER BY created_at DESC LIMIT 1");
    $app->execute([$user['id']]);
    $app = $app->fetch();
} catch (Throwable $e) { $app = null; }

// Statuses that unlock this page
$examStatuses = ['Approved for entrance','Entrance scheduled','Entrance completed','Entrance passed','Admitted'];
$hasExamAccess = $app && in_array($app['status'], $examStatuses);

// Load entrance exam details if scheduled
$examDate = $app['entrance_exam_date'] ?? null;
$examTime = $app['entrance_exam_time'] ?? null;
$examVenue = $app['entrance_exam_venue'] ?? null;
$letterRef = $app['entrance_letter_ref'] ?? null;
$today = date('Y-m-d');
$examPast = $examDate && $examDate < $today;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Entrance Exam — Applicant Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <div class="page-heading">
    <div><h1>Entrance Examination</h1><p>Invitation, schedule and preparation</p></div>
  </div>

  <?php if (!$app): ?>
  <div class="alert alert-warning">No application found.</div>

  <?php elseif (!$hasExamAccess): ?>
  <div style="text-align:center;padding:56px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
    <div style="font-size:40px;margin-bottom:14px">📝</div>
    <h3 style="margin-bottom:8px">Not yet available</h3>
    <p style="color:var(--ink-soft)">Entrance examination details will appear here once your application has been approved and an exam invitation issued.</p>
    <p style="color:var(--ink-soft);margin-top:8px">Current status: <strong><?= e($app['status']) ?></strong></p>
  </div>

  <?php else: ?>

  <?php if ($app['status'] === 'Entrance passed' || $app['status'] === 'Admitted'): ?>
  <div class="alert" style="background:var(--green-soft);border-color:var(--green-light);margin-bottom:20px">
    <strong>🎉 Entrance Passed!</strong> You have passed the entrance examination. Please check your admission decision.
    <a href="decision.php" class="button button-sm" style="margin-left:12px;background:var(--green);color:#fff">View Decision →</a>
  </div>
  <?php endif; ?>

  <!-- Invitation card -->
  <div class="panel" style="padding:24px;margin-bottom:20px">
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px">
      <div style="width:52px;height:52px;border-radius:var(--radius);background:var(--primary-soft);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:1.6rem;flex-shrink:0">📝</div>
      <div>
        <h2 style="font-size:17px;font-weight:800;margin-bottom:2px">Entrance Examination Invitation</h2>
        <p style="font-size:13px;color:var(--ink-soft)">Karn High School &mdash; <?= e($app['grade_applying_for'] ?? '') ?> Applicant</p>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-bottom:20px">
      <div style="padding:14px;background:var(--bg);border-radius:var(--radius-sm);text-align:center">
        <span style="font-size:1.4rem;display:block;margin-bottom:6px">📅</span>
        <strong style="display:block;font-size:15px;color:var(--ink)"><?= $examDate ? date('M d, Y', strtotime($examDate)) : '—' ?></strong>
        <span style="font-size:11px;color:var(--ink-faint)">Exam Date</span>
      </div>
      <div style="padding:14px;background:var(--bg);border-radius:var(--radius-sm);text-align:center">
        <span style="font-size:1.4rem;display:block;margin-bottom:6px">🕐</span>
        <strong style="display:block;font-size:15px;color:var(--ink)"><?= $examTime ? date('g:i A', strtotime($examTime)) : '—' ?></strong>
        <span style="font-size:11px;color:var(--ink-faint)">Time</span>
      </div>
      <div style="padding:14px;background:var(--bg);border-radius:var(--radius-sm);text-align:center">
        <span style="font-size:1.4rem;display:block;margin-bottom:6px">📍</span>
        <strong style="display:block;font-size:15px;color:var(--ink)"><?= e($examVenue ?: 'KHS Main Campus') ?></strong>
        <span style="font-size:11px;color:var(--ink-faint)">Venue</span>
      </div>
      <div style="padding:14px;background:var(--bg);border-radius:var(--radius-sm);text-align:center">
        <span style="font-size:1.4rem;display:block;margin-bottom:6px">📋</span>
        <strong style="display:block;font-size:15px;color:var(--ink)"><?= e($app['application_number']) ?></strong>
        <span style="font-size:11px;color:var(--ink-faint)">Application No.</span>
      </div>
    </div>

    <!-- Admission letter download -->
    <?php if ($letterRef): ?>
    <div style="background:var(--green-soft);border-radius:var(--radius-sm);padding:16px;display:flex;align-items:center;gap:14px;flex-wrap:wrap">
      <span style="font-size:1.4rem">📄</span>
      <div style="flex:1">
        <strong style="display:block;color:var(--green)">Entrance Eligibility Letter Ready</strong>
        <p style="font-size:13px;color:var(--ink-soft);margin-top:2px">Download and bring this letter on examination day along with a valid ID.</p>
      </div>
      <a href="<?= BASE_URL ?>/letters/entrance_letter.php?app=<?= urlencode($app['application_number']) ?>&phone=<?= urlencode($app['phone'] ?? '') ?>"
         class="button button-sm" style="background:var(--green);color:#fff" target="_blank">
        📥 Download Letter
      </a>
    </div>
    <?php endif; ?>
  </div>

  <!-- Preparation guide -->
  <div class="panel" style="padding:22px">
    <h3 style="font-size:14px;font-weight:700;margin-bottom:16px">📚 Preparation Guide</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px">
      <?php foreach ([
        ['📖','English Language','Reading comprehension, grammar, vocabulary and essay writing.'],
        ['🔢','Mathematics','Arithmetic, algebra, geometry and basic problem-solving.'],
        ['🔬','General Science','Basic science concepts appropriate for the grade applied for.'],
        ['⏰','Arrive Early','Please arrive at least 15 minutes before the scheduled time.'],
        ['🪪','Bring ID','Bring your admission letter and a valid ID or passport photo.'],
        ['🚫','No Assistance','The examination must be completed independently — no phones allowed.'],
      ] as [$ico,$title,$desc]): ?>
      <div style="padding:14px;border:1px solid var(--line);border-radius:var(--radius-sm)">
        <span style="font-size:1.3rem;display:block;margin-bottom:6px"><?= $ico ?></span>
        <strong style="display:block;font-size:13px;color:var(--ink);margin-bottom:4px"><?= $title ?></strong>
        <p style="font-size:12px;color:var(--ink-soft);line-height:1.6;margin:0"><?= $desc ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php endif; ?>

</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
