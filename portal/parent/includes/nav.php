<?php
// Parent Portal — Shared Sidebar Navigation
if (!isset($activePage)) $activePage = '';
if (!isset($selChild))   $selChild   = 0;
if (!isset($children))   $children   = [];

$cq = $selChild ? '?child_id='.$selChild : '';

$nav = [
    'dashboard'    => ['🏠', 'Dashboard',          BASE_URL.'/portal/parent/'],
    'profile'      => ['👤', 'Child Profile',        BASE_URL.'/portal/parent/child_profile.php'.$cq],
    'timetable'    => ['📅', 'Timetable',            BASE_URL.'/portal/parent/timetable.php'.$cq],
    'attendance'   => ['📆', 'Attendance',           BASE_URL.'/portal/parent/child_attendance.php'.$cq],
    'results'      => ['📊', 'Grades & Results',     BASE_URL.'/portal/parent/child_results.php'.$cq],
    'report_card'  => ['📑', 'Report Card',          BASE_URL.'/portal/parent/report_card.php'.$cq],
    'assignments'  => ['📝', 'Assignments',          BASE_URL.'/portal/parent/assignments.php'.$cq],
    'quizzes'      => ['🧠', 'Quiz Performance',     BASE_URL.'/portal/parent/quizzes.php'.$cq],
    'fees'         => ['💰', 'Fees & Payments',      BASE_URL.'/portal/parent/fees.php'.$cq],
    'discipline'   => ['⚠️',  'Discipline',           BASE_URL.'/portal/parent/discipline.php'.$cq],
    'announcements'=> ['📢', 'Announcements',        BASE_URL.'/portal/parent/announcements.php'],
    'alerts'       => ['🔔', 'Alerts',               BASE_URL.'/portal/parent/alerts.php'.$cq],
];
?>
<aside class="portal-sidebar" style="background:#1e1b4b;border-right:none">
  <!-- Brand -->
  <div style="padding:18px 16px 14px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:10px">
    <img src="<?=BASE_URL?>/assets/images/logo.jpg" alt="KHS"
         style="width:32px;height:32px;border-radius:8px;object-fit:cover;flex-shrink:0"/>
    <div>
      <strong style="display:block;font-size:13px;font-weight:800;color:#fff">KHS</strong>
      <small style="font-size:10px;color:rgba(255,255,255,.4)">Parent Portal</small>
    </div>
  </div>

  <!-- Child switcher -->
  <?php if (!empty($children)): ?>
  <div style="padding:10px 10px 8px;border-bottom:1px solid rgba(255,255,255,.07)">
    <div style="font-size:10px;font-weight:700;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;padding:0 4px">
      <?= count($children) > 1 ? 'Children' : 'Child' ?>
    </div>
    <div style="display:flex;flex-direction:column;gap:3px">
      <?php foreach ($children as $ch):
        $ini = strtoupper(substr($ch['first_name'],0,1).substr($ch['last_name'],0,1));
        $sel = ($ch['id'] == $selChild);
      ?>
      <a href="?child_id=<?=$ch['id']?>"
         style="display:flex;align-items:center;gap:9px;padding:7px 10px;border-radius:8px;
                font-size:12.5px;font-weight:600;text-decoration:none;transition:all .15s;
                color:<?=$sel?'#fff':'rgba(255,255,255,.58)'?>;
                background:<?=$sel?'rgba(255,255,255,.13)':'transparent'?>">
        <span style="width:28px;height:28px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;
                     background:<?=$sel?'var(--primary)':'rgba(255,255,255,.14)'?>;color:#fff"><?=$ini?></span>
        <div style="min-width:0">
          <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=e($ch['first_name'].' '.$ch['last_name'])?></div>
          <div style="font-size:10px;color:rgba(255,255,255,.4)"><?=e($ch['grade_name']??$ch['class_name']??'Student')?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Nav links -->
  <nav style="flex:1;padding:10px 8px;overflow-y:auto" aria-label="Parent portal navigation">
    <?php foreach ($nav as $key => [$icon, $label, $url]): ?>
    <a href="<?=e($url)?>" <?=$activePage===$key?'class="active" aria-current="page"':''?>
       style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:8px;
              font-size:13px;font-weight:600;text-decoration:none;margin-bottom:1px;transition:all .15s;
              color:<?=$activePage===$key?'#fff':'rgba(255,255,255,.62)'?>;
              background:<?=$activePage===$key?'rgba(255,255,255,.13)':'none'?>">
      <span style="font-size:15px;width:20px;text-align:center;flex-shrink:0"><?=$icon?></span>
      <?=$label?>
    </a>
    <?php endforeach; ?>
  </nav>

  <!-- Bottom -->
  <div style="padding:10px 8px;border-top:1px solid rgba(255,255,255,.06)">
    <a href="<?=BASE_URL?>/" target="_blank"
       style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;font-size:12px;color:rgba(255,255,255,.4);text-decoration:none">
      🌐 School Website
    </a>
    <a href="<?=BASE_URL?>/admin/logout.php"
       style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;font-size:13px;font-weight:600;color:rgba(255,100,100,.75);text-decoration:none">
      ↩ Sign Out
    </a>
  </div>
</aside>
