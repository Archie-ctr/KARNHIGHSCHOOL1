<?php
// ============================================================
// Parent Portal — Shared Sidebar Navigation
// Include this in every parent portal page.
// Expects:
//   $activePage  = 'dashboard' | 'profile' | 'results' | etc.
//   $selChild    = (int) currently selected child ID (or 0)
//   $children    = array of child records (for child switcher)
// ============================================================
if (!isset($activePage)) $activePage = '';
if (!isset($selChild))   $selChild   = 0;
if (!isset($children))   $children   = [];

// Build child_id query suffix
$cq = $selChild ? '?child_id='.$selChild : '';

$nav = [
    'dashboard'  => ['🏠', 'Dashboard',           BASE_URL.'/portal/parent/'],
    'profile'    => ['👤', "Child Profile",        BASE_URL.'/portal/parent/child_profile.php'.$cq],
    'timetable'  => ['📅', 'Timetable',            BASE_URL.'/portal/parent/timetable.php'.$cq],
    'attendance' => ['📆', 'Attendance',           BASE_URL.'/portal/parent/child_attendance.php'.$cq],
    'results'    => ['📊', 'Grades & Results',     BASE_URL.'/portal/parent/child_results.php'.$cq],
    'report_card'=> ['📑', 'Report Card',          BASE_URL.'/portal/parent/report_card.php'.$cq],
    'fees'       => ['💰', 'Fees & Payments',      BASE_URL.'/portal/parent/fees.php'.$cq],
    'announcements'=> ['📢', 'Announcements',      BASE_URL.'/portal/parent/announcements.php'],
    'contact'    => ['✉️',  'Contact School',      BASE_URL.'/contact.php'],
];
?>
<aside class="portal-sidebar">
  <div class="portal-brand">
    <div class="brand">
      <img src="<?= BASE_URL ?>/assets/images/logo.jpg" alt="KHS"/>
      <span><strong>KHS</strong><small>Parent Portal</small></span>
    </div>
  </div>

  <!-- Child switcher (shown only when parent has multiple children) -->
  <?php if (count($children) > 1): ?>
  <div style="padding:10px 14px;border-bottom:1px solid rgba(255,255,255,.08)">
    <div style="font-size:10px;font-weight:700;color:rgba(255,255,255,.38);text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px">Viewing</div>
    <div style="display:flex;flex-direction:column;gap:4px">
      <?php foreach ($children as $ch):
        $ini = strtoupper(substr($ch['first_name'],0,1).substr($ch['last_name'],0,1));
        $isSelected = ($ch['id'] == $selChild);
      ?>
      <a href="?child_id=<?= $ch['id'] ?>"
         style="display:flex;align-items:center;gap:8px;padding:6px 8px;border-radius:6px;font-size:12px;font-weight:600;color:<?= $isSelected?'#fff':'rgba(255,255,255,.55)' ?>;background:<?= $isSelected?'rgba(255,255,255,.12)':'transparent' ?>;text-decoration:none;transition:all .15s">
        <span style="width:26px;height:26px;border-radius:50%;background:<?= $isSelected?'var(--primary)':'rgba(255,255,255,.15)' ?>;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;flex-shrink:0"><?= $ini ?></span>
        <span><?= e($ch['first_name']) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <nav class="portal-nav" aria-label="Parent portal navigation">
    <?php foreach ($nav as $key => [$icon, $label, $url]): ?>
    <a href="<?= e($url) ?>" <?= $activePage === $key ? 'class="active" aria-current="page"' : '' ?>>
      <?= $icon ?> <?= $label ?>
    </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-bottom" style="border-top:1px solid var(--line);padding:12px">
    <a href="<?= BASE_URL ?>/" target="_blank" style="display:block;font-size:12px;color:var(--ink-faint);margin-bottom:8px">🌐 School Website</a>
    <a href="<?= BASE_URL ?>/admin/logout.php" style="color:var(--error);font-size:13px;font-weight:600">⬡ Sign Out</a>
  </div>
</aside>
