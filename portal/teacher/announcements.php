<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole(['teacher','class_teacher']);
$pdo=db();
$anns=$pdo->query("SELECT * FROM announcements WHERE target IN ('all','teachers') AND published_at IS NOT NULL AND (expires_at IS NULL OR expires_at>NOW()) ORDER BY published_at DESC")->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Announcements — Teacher Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/></head><body>
<div class="portal-grid">
<aside class="portal-sidebar">
  <div class="portal-brand"><div class="brand"><img src="<?=BASE_URL?>/assets/images/logo.jpg" alt="KHS"/><span><strong>KHS</strong><small>Teacher Portal</small></span></div></div>
  <nav class="portal-nav">
    <a href="<?=BASE_URL?>/portal/teacher/">🏠 Dashboard</a>
    <a href="<?=BASE_URL?>/portal/teacher/my_classes.php">🏫 My Classes</a>
    <a href="<?=BASE_URL?>/portal/teacher/enter_marks.php">✏️ Enter Marks</a>
    <a href="<?=BASE_URL?>/portal/teacher/take_attendance.php">📆 Attendance</a>
    <a href="<?=BASE_URL?>/portal/teacher/students.php">🎓 Students</a>
    <a href="<?=BASE_URL?>/portal/teacher/timetable.php">📅 Timetable</a>
    <a href="<?=BASE_URL?>/portal/teacher/announcements.php" class="active">📢 Announcements</a>
  </nav>
  <div style="border-top:1px solid var(--line);padding:12px"><a href="<?=BASE_URL?>/admin/logout.php" style="color:var(--error);font-size:13px;font-weight:600">Sign Out</a></div>
</aside>
<div class="portal-content">
  <div class="page-heading"><div><h1>Announcements</h1><p>School notices for staff and teachers</p></div></div>

  <?php if (empty($anns)): ?>
  <div style="text-align:center;padding:48px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
    <div style="font-size:36px;margin-bottom:12px">📢</div>
    <p style="color:var(--ink-soft)">No announcements at this time. Check back later.</p>
  </div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:14px">
    <?php foreach ($anns as $ann): ?>
    <div style="background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);padding:20px 24px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:10px">
        <h3 style="font-size:16px;font-weight:700"><?=e($ann['title'])?></h3>
        <div style="display:flex;gap:8px;align-items:center;flex-shrink:0">
          <span class="status new-s" style="font-size:11px"><?=e(ucfirst($ann['target']))?></span>
          <span style="font-size:11.5px;color:var(--ink-faint)"><?=date('M d, Y',strtotime($ann['published_at']))?></span>
        </div>
      </div>
      <p style="font-size:14px;line-height:1.75;color:var(--ink-soft)"><?=nl2br(e($ann['message']))?></p>
      <?php if ($ann['expires_at']): ?>
      <div style="margin-top:10px;font-size:12px;color:var(--ink-faint)">Expires: <?=date('M d, Y',strtotime($ann['expires_at']))?></div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script></body></html>
