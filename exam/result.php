<?php
require_once dirname(__DIR__).'/config/db.php';
$attemptId = (int)($_GET['attempt_id']??0);
if (!$attemptId) die('Invalid link.');
$att = db()->query("SELECT a.*,e.title exam_title,e.show_result,e.passing_score,app.first_name,app.last_name,app.application_number FROM entrance_exam_attempts a JOIN entrance_exams e ON e.id=a.exam_id JOIN applications app ON app.id=a.application_id WHERE a.id=$attemptId")->fetch();
if (!$att) die('Result not found.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Exam Result — KARN HIGH SCHOOL</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body style="background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px">
<div style="background:var(--surface);border-radius:var(--radius-lg);max-width:520px;width:100%;padding:40px;box-shadow:var(--shadow-lg);text-align:center">
  <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="KHS" style="width:56px;height:56px;border-radius:12px;margin:0 auto 16px;object-fit:cover"/>
  <div class="eyebrow" style="justify-content:center;margin-bottom:8px">KARN HIGH SCHOOL <span></span></div>
  <h2 style="margin-bottom:4px">Exam Submitted</h2>
  <p style="color:var(--ink-soft);margin-bottom:28px"><?= e($att['exam_title']) ?></p>
  <p style="color:var(--ink-soft);font-size:14px">Name: <strong><?= e($att['first_name'].' '.$att['last_name']) ?></strong></p>
  <p style="color:var(--ink-soft);font-size:14px;margin-bottom:24px">Application: <strong><?= e($att['application_number']) ?></strong></p>

  <?php if ($att['show_result']==='immediate' && $att['percentage'] !== null): ?>
  <div style="background:<?= $att['passed'] ? 'var(--green-soft)':'var(--error-soft)' ?>;border-radius:var(--radius);padding:24px;margin-bottom:24px">
    <div style="font-size:48px;font-weight:800;color:<?= $att['passed']?'var(--green)':'var(--error)' ?>"><?= round($att['percentage'],1) ?>%</div>
    <div style="font-size:16px;font-weight:700;margin-top:4px;color:<?= $att['passed']?'var(--green)':'var(--error)' ?>"><?= $att['passed']?'PASSED':'NOT PASSED' ?></div>
    <div style="font-size:13px;color:var(--ink-soft);margin-top:8px">Score: <?= fmtMark($att['score']) ?> / <?= fmtMark($att['max_score']) ?> &nbsp;&middot;&nbsp; Pass mark: <?= $att['passing_score'] ?>%</div>
  </div>
  <?php else: ?>
  <div style="background:var(--blue-soft);border-radius:var(--radius);padding:24px;margin-bottom:24px">
    <div style="font-size:36px;margin-bottom:8px">✓</div>
    <strong>Your exam has been submitted successfully.</strong>
    <p style="font-size:13.5px;color:var(--ink-soft);margin-top:6px">Results will be available after review by the admissions team.</p>
  </div>
  <?php endif; ?>

  <a href="<?= BASE_URL ?>/application-status.php" class="button button-primary">Track Application Status →</a>
  <div style="margin-top:16px"><a href="<?= BASE_URL ?>/" style="font-size:13px;color:var(--ink-faint)">Return to website</a></div>
</div>
</body>
</html>
