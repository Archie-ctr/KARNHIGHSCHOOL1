<?php
require_once __DIR__.'/config/db.php';
$pageTitle = 'Events';
$activeNav = 'events';
$events = db()->query(
    "SELECT * FROM events WHERE is_public=1 AND event_date >= CURDATE() ORDER BY event_date ASC LIMIT 30"
)->fetchAll();
$past = db()->query(
    "SELECT * FROM events WHERE is_public=1 AND event_date < CURDATE() ORDER BY event_date DESC LIMIT 6"
)->fetchAll();
include __DIR__.'/includes/header.php';
?>
<main class="inner-page">
  <div class="container inner-hero">
    <div class="eyebrow">School Calendar <span></span></div>
    <h1>Upcoming<br><em>events at KHS.</em></h1>
  </div>
  <div class="container" style="padding-bottom:80px">
    <?php if (empty($events) && empty($past)): ?>
      <div style="text-align:center;padding:60px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
        <div style="font-size:40px;margin-bottom:12px">📅</div>
        <p style="color:var(--ink-soft)">No upcoming events at this time. Check back soon.</p>
      </div>
    <?php else: ?>
      <?php if (!empty($events)): ?>
      <div class="eyebrow" style="margin-bottom:20px">Upcoming Events <span></span></div>
      <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:48px">
        <?php foreach ($events as $ev): ?>
        <div style="background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);padding:20px 24px;display:flex;gap:20px;align-items:flex-start">
          <div style="min-width:64px;background:var(--primary-soft);border-radius:var(--radius-sm);padding:10px;text-align:center;flex-shrink:0">
            <div style="font-size:22px;font-weight:800;color:var(--primary);line-height:1"><?= date('d', strtotime($ev['event_date'])) ?></div>
            <div style="font-size:11px;font-weight:700;color:var(--primary);text-transform:uppercase"><?= date('M', strtotime($ev['event_date'])) ?></div>
            <div style="font-size:10px;color:var(--ink-faint)"><?= date('Y', strtotime($ev['event_date'])) ?></div>
          </div>
          <div style="flex:1">
            <span class="status new-s" style="margin-bottom:6px;display:inline-flex"><?= e(ucfirst($ev['category'])) ?></span>
            <h3 style="font-size:16px;font-weight:700;margin-bottom:4px"><?= e($ev['title']) ?></h3>
            <?php if ($ev['description']): ?>
            <p style="font-size:13.5px;color:var(--ink-soft)"><?= e($ev['description']) ?></p>
            <?php endif; ?>
            <?php if ($ev['venue']): ?>
            <p style="font-size:12.5px;color:var(--ink-faint);margin-top:4px">📍 <?= e($ev['venue']) ?></p>
            <?php endif; ?>
            <?php if ($ev['start_time']): ?>
            <p style="font-size:12.5px;color:var(--ink-faint)">🕐 <?= date('g:i A', strtotime($ev['start_time'])) ?></p>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if (!empty($past)): ?>
      <div class="eyebrow" style="margin-bottom:20px">Past Events <span></span></div>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px">
        <?php foreach ($past as $ev): ?>
        <div style="background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);padding:18px;opacity:.7">
          <div style="font-size:12px;color:var(--ink-faint);margin-bottom:6px"><?= date('M d, Y', strtotime($ev['event_date'])) ?></div>
          <strong style="font-size:14px"><?= e($ev['title']) ?></strong>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__.'/includes/footer.php'; ?>
