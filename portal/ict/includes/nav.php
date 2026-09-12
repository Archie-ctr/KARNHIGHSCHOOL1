<?php
// ICT Officer Portal — Shared Sidebar Navigation
// Set $activePage and $officer before including.
if (!isset($activePage)) $activePage = '';
if (!isset($officer))    $officer    = null;
$ini = 'IT';
if ($officer) $ini = strtoupper(substr($officer['first_name']??'I',0,1).substr($officer['last_name']??'T',0,1));

$nav = [
    'dashboard' => ['🏠','Dashboard',       BASE_URL.'/portal/ict/'],
    'assets'    => ['💻','IT Assets',        BASE_URL.'/portal/ict/assets.php'],
    'network'   => ['🌐','Network',          BASE_URL.'/portal/ict/network.php'],
    'tickets'   => ['🎫','Support Tickets',  BASE_URL.'/portal/ict/tickets.php'],
    'users'     => ['👥','User Support',     BASE_URL.'/portal/ict/users.php'],
    'inventory' => ['📦','ICT Inventory',    BASE_URL.'/portal/ict/inventory.php'],
    'security'  => ['🔐','Security Monitor', BASE_URL.'/portal/ict/security.php'],
    'notifications'=> ['🔔','Notifications', BASE_URL.'/portal/notifications.php'],
];
?>
<aside class="portal-sidebar" style="background:#0c1a2e;border-right:none">
  <div style="padding:18px 16px 14px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:10px">
    <img src="<?=BASE_URL?>/assets/images/logo.jpg" alt="KHS" style="width:32px;height:32px;border-radius:8px;object-fit:cover;flex-shrink:0"/>
    <div><strong style="display:block;font-size:13px;font-weight:800;color:#fff">KHS</strong><small style="font-size:10px;color:rgba(255,255,255,.4)">ICT Department</small></div>
  </div>
  <?php if($officer):?>
  <div style="display:flex;align-items:center;gap:10px;padding:10px 14px 12px;border-bottom:1px solid rgba(255,255,255,.06)">
    <div class="avatar" style="width:34px;height:34px;font-size:12px;flex-shrink:0;background:#1d4ed8;color:#fff"><?=e($ini)?></div>
    <div style="min-width:0">
      <strong style="display:block;font-size:12.5px;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=e(($officer['first_name']??'').' '.($officer['last_name']??''))?></strong>
      <small style="font-size:10px;color:rgba(255,255,255,.4)">ICT Officer</small>
    </div>
  </div>
  <?php endif;?>
  <nav style="flex:1;padding:10px 8px;overflow-y:auto">
    <?php
    $_unreadI=0;
    try{$_unreadI=(int)db()->query("SELECT COUNT(*) FROM notifications WHERE user_id=".currentUserId()." AND is_read=0")->fetchColumn();}catch(Throwable $_e){}
    foreach($nav as $key=>[$icon,$label,$url]):?>
    <a href="<?=e($url)?>" <?=$activePage===$key?'class="active"':''?>
       style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;font-size:13px;font-weight:600;color:<?=$activePage===$key?'#fff':'rgba(255,255,255,.6)'?>;background:<?=$activePage===$key?'rgba(255,255,255,.12)':'none'?>;text-decoration:none;margin-bottom:1px;transition:all .15s">
      <span style="font-size:15px;width:20px;text-align:center;flex-shrink:0"><?=$icon?></span><?=$label?>
      <?php if($key==='notifications'&&$_unreadI>0):?>
      <span class="ts-badge notif-bell-badge" style="margin-left:auto"><?=$_unreadI>99?'99+':$_unreadI?></span>
      <?php endif;?>
    </a>
    <?php endforeach;?>
  </nav>
  <div style="padding:10px 8px;border-top:1px solid rgba(255,255,255,.06)">
    <a href="<?=BASE_URL?>/" target="_blank" style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;font-size:12px;color:rgba(255,255,255,.4);text-decoration:none">🌐 School Website</a>
    <a href="<?=BASE_URL?>/admin/logout.php" style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;font-size:13px;font-weight:600;color:rgba(255,100,100,.7);text-decoration:none">↩ Sign Out</a>
  </div>
</aside>
<script>window.__BASE_URL='<?=BASE_URL?>';</script>
<script src="<?=BASE_URL?>/assets/js/notifications.js" defer></script>
