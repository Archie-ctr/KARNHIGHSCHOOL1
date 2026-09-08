<?php
// ============================================================
// Parent Portal — Announcements
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('parent');

$activePage = 'announcements';
include __DIR__.'/includes/resolve_child.php';

try {
    $anns = db()->query(
        "SELECT a.*, u.name posted_by_name
         FROM announcements a
         LEFT JOIN users u ON u.id = a.created_by
         WHERE a.target IN ('all','parents')
           AND (a.expires_at IS NULL OR a.expires_at > NOW())
           AND a.published_at IS NOT NULL
         ORDER BY a.published_at DESC"
    )->fetchAll();
} catch (Throwable $e) { $anns = []; }

$typeColors = [
    'general'     => ['💬', '#f0f4ff', '#3b5bdb'],
    'academic'    => ['📚', '#f0fdf4', '#15803d'],
    'examination' => ['📝', '#fff7ed', '#c2410c'],
    'event'       => ['🎉', '#fdf4ff', '#7c3aed'],
    'urgent'      => ['🔴', '#fff1f2', '#be123c'],
    'fee'         => ['💰', '#fefce8', '#a16207'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Announcements — Parent Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include __DIR__.'/includes/nav.php'; ?>
<div class="portal-content">

  <div class="page-heading">
    <div><h1>Announcements</h1><p>School notices and important information</p></div>
    <?php if (!empty($anns)): ?>
    <span style="background:var(--primary-soft);color:var(--primary);font-weight:700;font-size:13px;padding:8px 16px;border-radius:20px">
      <?= count($anns) ?> notice<?= count($anns)!==1?'s':'' ?>
    </span>
    <?php endif; ?>
  </div>

  <?php if (empty($anns)): ?>
  <div style="text-align:center;padding:56px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
    <div style="font-size:40px;margin-bottom:12px">📢</div>
    <h3 style="margin-bottom:6px">No announcements</h3>
    <p style="color:var(--ink-soft)">There are no school announcements at this time. Check back soon.</p>
  </div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:12px">
    <?php foreach ($anns as $ann):
      $type  = strtolower($ann['type'] ?? 'general');
      [$ico, $bg, $col] = $typeColors[$type] ?? $typeColors['general'];
      $isNew = strtotime($ann['published_at']) > strtotime('-3 days');
    ?>
    <article style="background:#fff;border:1px solid var(--line);border-radius:var(--radius);overflow:hidden">
      <div style="padding:14px 20px 12px;background:<?= $bg ?>;border-bottom:1px solid <?= $col ?>22;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <span style="font-size:1.2rem"><?= $ico ?></span>
        <h3 style="font-size:15px;font-weight:700;color:var(--ink);flex:1"><?= e($ann['title']) ?></h3>
        <?php if ($isNew): ?>
        <span style="font-size:10px;font-weight:800;background:<?= $col ?>;color:#fff;padding:2px 9px;border-radius:20px;letter-spacing:.05em;text-transform:uppercase">New</span>
        <?php endif; ?>
        <span style="font-size:11px;font-weight:600;color:<?= $col ?>;background:<?= $bg ?>;padding:3px 10px;border-radius:20px;border:1px solid <?= $col ?>33"><?= ucfirst($type) ?></span>
      </div>
      <div style="padding:14px 20px">
        <p style="font-size:14px;color:var(--ink);line-height:1.75;white-space:pre-line"><?= e($ann['message']) ?></p>
        <div style="display:flex;align-items:center;gap:16px;margin-top:12px;font-size:11.5px;color:var(--ink-faint)">
          <span>📅 <?= date('F d, Y', strtotime($ann['published_at'])) ?></span>
          <?php if (!empty($ann['posted_by_name'])): ?><span>👤 <?= e($ann['posted_by_name']) ?></span><?php endif; ?>
          <?php if (!empty($ann['expires_at'])): ?><span>⏳ Expires <?= date('M d, Y', strtotime($ann['expires_at'])) ?></span><?php endif; ?>
        </div>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
