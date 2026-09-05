<?php
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('parent');
$pdo=db();
$anns=$pdo->query("SELECT * FROM announcements WHERE target IN ('all','parents') AND (expires_at IS NULL OR expires_at>NOW()) AND published_at IS NOT NULL ORDER BY published_at DESC")->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/><title>Announcements — Parent Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/><link rel="stylesheet" href="<?=BASE_URL?>/assets/css/style.css"/></head><body>
<div class="portal-grid">
<aside class="portal-sidebar"><div class="portal-brand"><div class="brand"><img src="<?=BASE_URL?>/assets/images/logo.jpg" alt="KHS"/><span><strong>KHS</strong><small>Parent Portal</small></span></div></div>
<nav class="portal-nav"><a href="<?=BASE_URL?>/portal/parent/">🏠 Dashboard</a><a href="<?=BASE_URL?>/portal/parent/child_results.php">📊 Results</a><a href="<?=BASE_URL?>/portal/parent/fees.php">💰 Fees</a><a href="<?=BASE_URL?>/portal/parent/report_card.php">📑 Report Card</a><a href="<?=BASE_URL?>/portal/parent/announcements.php" class="active">📢 Announcements</a></nav>
<div style="border-top:1px solid var(--line);padding:12px"><a href="<?=BASE_URL?>/admin/logout.php" style="color:var(--error);font-size:13px;font-weight:600">Sign Out</a></div></aside>
<div class="portal-content">
  <div class="page-heading"><div><h1>Announcements</h1></div></div>
  <?php if(empty($anns)):?><div style="text-align:center;padding:40px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)"><div style="font-size:36px;margin-bottom:12px">📢</div><p style="color:var(--ink-soft)">No announcements at this time.</p></div>
  <?php else:?><div style="display:flex;flex-direction:column;gap:14px">
    <?php foreach($anns as $ann):?>
    <div style="background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);padding:20px 24px">
      <div style="font-size:11px;color:var(--ink-faint);margin-bottom:6px"><?=date('F d, Y',strtotime($ann['published_at']))?></div>
      <h3 style="font-size:16px;font-weight:700;margin-bottom:10px"><?=e($ann['title'])?></h3>
      <p style="font-size:14px;line-height:1.75;color:var(--ink-soft)"><?=nl2br(e($ann['message']))?></p>
    </div>
    <?php endforeach;?></div>
  <?php endif;?>
</div></div>
<script src="<?=BASE_URL?>/assets/js/main.js"></script></body></html>
