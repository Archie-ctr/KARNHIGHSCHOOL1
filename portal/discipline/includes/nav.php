<?php
if (!isset($activePage)) $activePage = '';
if (!isset($officer))    $officer    = null;
$ini = 'DO';
if ($officer) $ini = strtoupper(substr($officer['first_name']??'D',0,1).substr($officer['last_name']??'O',0,1));

$nav = [
    'dashboard'       => ['🏠', 'Dashboard',            BASE_URL.'/portal/discipline/'],
    'incidents'       => ['⚠️',  'Cases / Incidents',    BASE_URL.'/portal/discipline/incidents.php'],
    'investigations'  => ['🔍', 'Investigations',        BASE_URL.'/portal/discipline/investigations.php'],
    'actions'         => ['📋', 'Disciplinary Actions',  BASE_URL.'/portal/discipline/warnings.php'],
    'notifications'   => ['📞', 'Parent Communications', BASE_URL.'/portal/discipline/notifications.php'],
    'recommendations' => ['📨', 'Escalate to VP/Principal', BASE_URL.'/portal/discipline/recommendations.php'],
    'students'        => ['🎓', 'Student Profiles',      BASE_URL.'/portal/discipline/students.php'],
    'reports'         => ['📊', 'Reports',               BASE_URL.'/portal/discipline/reports.php'],
    'notifications'   => ['🔔', 'Notifications',         BASE_URL.'/portal/notifications.php'],
];
?>
<aside class="portal-sidebar" style="background:#1a1a2e;border-right:none">
  <div style="padding:18px 16px 14px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:10px">
    <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="KHS" style="width:32px;height:32px;border-radius:8px;object-fit:cover;flex-shrink:0"/>
    <div><strong style="display:block;font-size:13px;font-weight:800;color:#fff">KHS</strong><small style="font-size:10px;color:rgba(255,255,255,.4)">Discipline Office</small></div>
  </div>
  <?php if ($officer): ?>
  <div style="display:flex;align-items:center;gap:10px;padding:10px 14px 12px;border-bottom:1px solid rgba(255,255,255,.06)">
    <div class="avatar" style="width:34px;height:34px;font-size:12px;flex-shrink:0;background:var(--warning);color:#fff"><?= e($ini) ?></div>
    <div style="min-width:0">
      <strong style="display:block;font-size:12.5px;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e(($officer['first_name']??'').' '.($officer['last_name']??'')) ?></strong>
      <small style="font-size:10px;color:rgba(255,255,255,.4)">Discipline Officer</small>
    </div>
  </div>
  <?php endif; ?>
  <nav style="flex:1;padding:10px 8px;overflow-y:auto">
    <?php
    $_unreadD=0; try{$_unreadD=(int)db()->query("SELECT COUNT(*) FROM notifications WHERE user_id=".currentUserId()." AND is_read=0")->fetchColumn();}catch(Throwable $_e){}
    foreach ($nav as $key => [$icon,$label,$url]): ?>
    <a href="<?= e($url) ?>" <?= $activePage===$key?'class="active"':'' ?>
       style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;font-size:13px;font-weight:600;color:<?= $activePage===$key?'#fff':'rgba(255,255,255,.6)' ?>;background:<?= $activePage===$key?'rgba(255,255,255,.12)':'none' ?>;text-decoration:none;margin-bottom:1px;transition:all .15s">
      <span style="font-size:15px;width:20px;text-align:center;flex-shrink:0"><?= $icon ?></span><?= $label ?>
      <?php if($key==='notifications'&&$_unreadD>0):?><span class="ts-badge notif-bell-badge" style="margin-left:auto"><?=$_unreadD>99?'99+':$_unreadD?></span><?php endif;?>
    </a>
    <?php endforeach; ?>
  </nav>
  <div style="padding:10px 8px;border-top:1px solid rgba(255,255,255,.06)">
    <a href="<?= BASE_URL ?>/" target="_blank" style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;font-size:12.5px;font-weight:600;color:rgba(255,255,255,.4);text-decoration:none;margin-bottom:1px">🌐 School Website</a>
    <a href="<?= BASE_URL ?>/admin/logout.php" style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;font-size:13px;font-weight:600;color:rgba(255,100,100,.7);text-decoration:none">↩ Sign Out</a>
  </div>
</aside>
<script>window.__BASE_URL='<?= BASE_URL ?>';</script>
<script src="<?= BASE_URL ?>/assets/js/notifications.js" defer></script>
