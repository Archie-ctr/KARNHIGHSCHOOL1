<?php
// ============================================================
// Unified Notification Center — all authenticated roles
// URL: /portal/notifications.php
// ============================================================
require_once dirname(__DIR__).'/config/db.php';
requireAuth();

$pdo    = db();
$user   = currentUser();
$role   = currentRole();
$uid    = currentUserId();

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_read') {
        $id = (int)($_POST['notif_id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$id, $uid]);
        } else {
            $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);
        }
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header('Content-Type: application/json');
            echo json_encode(['ok'=>true]);
            exit;
        }
    }
    if ($action === 'delete') {
        $id = (int)($_POST['notif_id'] ?? 0);
        if ($id) $pdo->prepare("DELETE FROM notifications WHERE id=? AND user_id=?")->execute([$id, $uid]);
    }
    if ($action === 'clear_all') {
        $pdo->prepare("DELETE FROM notifications WHERE user_id=?")->execute([$uid]);
    }
    redirect(BASE_URL.'/portal/notifications.php');
}

// Filter
$filter  = $_GET['filter'] ?? 'all'; // all|unread|read
$page    = max(1,(int)($_GET['p'] ?? 1));
$perPage = 20;

$where = ["n.user_id=$uid"];
if ($filter === 'unread') $where[] = "n.is_read=0";
if ($filter === 'read')   $where[] = "n.is_read=1";
$ws = implode(' AND ', $where);

$total   = (int)$pdo->query("SELECT COUNT(*) FROM notifications n WHERE $ws")->fetchColumn();
$unread  = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id=$uid AND is_read=0")->fetchColumn();
$offset  = ($page - 1) * $perPage;
$pages   = max(1, (int)ceil($total / $perPage));

$notifs  = $pdo->query(
    "SELECT * FROM notifications n WHERE $ws
     ORDER BY n.created_at DESC LIMIT $perPage OFFSET $offset"
)->fetchAll();

// Mark fetched unread as read (auto mark-read on view)
if (!empty($notifs)) {
    $ids = implode(',', array_column($notifs, 'id'));
    $pdo->query("UPDATE notifications SET is_read=1 WHERE id IN ($ids) AND user_id=$uid");
}

// Icon map by notification type
$typeIcon = [
    'marks_submitted'    => ['✏️',  'var(--blue)'],
    'marks_approved'     => ['✅',  'var(--green)'],
    'marks_returned'     => ['↩️',  'var(--warning)'],
    'marks_rejected'     => ['❌',  'var(--error)'],
    'attendance_absent'  => ['📆', 'var(--error)'],
    'attendance_open'    => ['📆', 'var(--primary)'],
    'discipline_notice'  => ['⚠️',  'var(--warning)'],
    'parent_notified'    => ['📞', 'var(--blue)'],
    'report_card_published'=>['📑','var(--green)'],
    'announcement'       => ['📢', 'var(--primary)'],
    'fee_reminder'       => ['💰', 'var(--warning)'],
    'system'             => ['⚙️',  'var(--ink-soft)'],
    'info'               => ['ℹ️',  'var(--blue)'],
    'success'            => ['✅',  'var(--green)'],
    'warning'            => ['⚠️',  'var(--warning)'],
    'error'              => ['❌',  'var(--error)'],
];

// Back link based on role
$backLink = match(true) {
    in_array($role,['teacher','class_teacher']) => BASE_URL.'/portal/teacher/',
    $role === 'student'            => BASE_URL.'/portal/student/',
    $role === 'parent'             => BASE_URL.'/portal/parent/',
    $role === 'librarian'          => BASE_URL.'/portal/librarian/',
    $role === 'discipline_officer' => BASE_URL.'/portal/discipline/',
    $role === 'ict_officer'        => BASE_URL.'/portal/ict/',
    default                        => BASE_URL.'/admin/index.php',
};

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff/60).'m ago';
    if ($diff < 86400)  return floor($diff/3600).'h ago';
    if ($diff < 604800) return floor($diff/86400).'d ago';
    return date('d M Y', strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Notifications — KHS</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body style="background:var(--bg);font-family:'Plus Jakarta Sans',sans-serif">

<div style="max-width:700px;margin:0 auto;padding:24px 16px">

  <!-- Header -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px">
    <div>
      <a href="<?= e($backLink) ?>" style="font-size:13px;color:var(--primary);text-decoration:none;margin-bottom:8px;display:block">← Back to Portal</a>
      <h1 style="font-size:22px;font-weight:800;margin:0">
        🔔 Notifications
        <?php if ($unread > 0): ?>
        <span style="font-size:13px;background:var(--error);color:#fff;padding:3px 9px;border-radius:12px;vertical-align:middle;font-weight:700"><?= $unread ?> new</span>
        <?php endif; ?>
      </h1>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php if ($total > 0): ?>
      <form method="post" style="display:inline">
        <?= csrfField() ?><input type="hidden" name="action" value="mark_read"/>
        <button class="button button-secondary button-sm">✓ Mark all read</button>
      </form>
      <form method="post" style="display:inline" onsubmit="return confirm('Delete all notifications?')">
        <?= csrfField() ?><input type="hidden" name="action" value="clear_all"/>
        <button class="button button-danger button-sm">🗑 Clear all</button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- Filter tabs -->
  <div style="display:flex;gap:6px;margin-bottom:16px;border-bottom:1.5px solid var(--line);padding-bottom:0">
    <?php foreach(['all'=>'All','unread'=>'Unread','read'=>'Read'] as $f=>$label): ?>
    <a href="?filter=<?=$f?>"
       style="padding:8px 16px;font-size:13px;font-weight:700;text-decoration:none;border-bottom:2.5px solid <?=$filter===$f?'var(--primary)':'transparent'?>;color:<?=$filter===$f?'var(--primary)':'var(--ink-soft)'?>;margin-bottom:-1.5px">
      <?=$label?>
      <?php if ($f==='unread' && $unread>0): ?><span style="background:var(--error);color:#fff;font-size:10px;padding:1px 5px;border-radius:8px;margin-left:4px"><?=$unread?></span><?php endif;?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- Notifications list -->
  <?php if (empty($notifs)): ?>
  <div style="text-align:center;padding:60px 20px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
    <div style="font-size:48px;margin-bottom:14px">🔔</div>
    <h3 style="font-weight:700;margin-bottom:6px">
      <?= $filter==='unread' ? 'All caught up!' : 'No notifications' ?>
    </h3>
    <p style="color:var(--ink-soft)">
      <?= $filter==='unread' ? 'You have no unread notifications.' : 'Notifications will appear here when there is activity.' ?>
    </p>
  </div>

  <?php else: ?>
  <div style="background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);overflow:hidden">
    <?php foreach ($notifs as $n):
      [$icon, $color] = $typeIcon[$n['type']] ?? ['🔔','var(--primary)'];
      $isUnread = !$n['is_read']; // won't show as unread since we auto-marked, but keep for style
    ?>
    <div class="notif-item" style="<?= $isUnread?'background:var(--bg)':'' ?>">
      <!-- Icon -->
      <div class="notif-icon" style="background:<?= $color ?>22;color:<?= $color ?>"><?= $icon ?></div>

      <!-- Body -->
      <div class="notif-body">
        <div class="notif-title"><?= e($n['title']) ?></div>
        <div class="notif-msg"><?= e($n['message']) ?></div>
        <div class="notif-time">
          <?= timeAgo($n['created_at']) ?>
          &middot; <?= date('d M Y H:i', strtotime($n['created_at'])) ?>
        </div>
      </div>

      <!-- Actions -->
      <div style="display:flex;flex-direction:column;gap:5px;flex-shrink:0;align-items:flex-end">
        <?php if ($n['link']): ?>
        <a href="<?= e($n['link']) ?>" class="button button-secondary button-sm" style="font-size:11px;white-space:nowrap">View →</a>
        <?php endif; ?>
        <form method="post" style="display:inline">
          <?= csrfField() ?>
          <input type="hidden" name="action"   value="delete"/>
          <input type="hidden" name="notif_id" value="<?= $n['id'] ?>"/>
          <button class="button button-secondary button-sm" style="font-size:11px;color:var(--ink-faint)">✕</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
  <div style="display:flex;justify-content:center;gap:6px;margin-top:16px">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
    <a href="?filter=<?= $filter ?>&p=<?= $p ?>"
       style="padding:6px 12px;border-radius:6px;font-size:13px;font-weight:700;text-decoration:none;
              background:<?= $p===$page?'var(--primary)':'var(--surface)' ?>;
              color:<?= $p===$page?'#fff':'var(--ink-soft)' ?>;
              border:1px solid <?= $p===$page?'var(--primary)':'var(--line)' ?>">
      <?= $p ?>
    </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>

</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
