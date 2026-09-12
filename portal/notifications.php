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
$ayId   = currentAcademicYearId();

// ── Load the portal-specific record so the sidebar renders correctly ──
$activePage = 'notifications';
$teacher = $student = $librarian = $officer = $parent = null;

switch (true) {
    case in_array($role, ['teacher','class_teacher']):
        include __DIR__.'/teacher/includes/resolve_teacher.php';
        break;

    case $role === 'student':
        $student = $pdo->prepare(
            "SELECT s.*, g.name grade_name, c.name class_name
             FROM students s
             LEFT JOIN grades  g ON g.id=s.current_grade_id
             LEFT JOIN classes c ON c.id=s.current_class_id
             WHERE s.user_id=? LIMIT 1"
        );
        $student->execute([$uid]);
        $student = $student->fetch() ?: null;
        break;

    case $role === 'parent':
        $parent = $pdo->prepare("SELECT * FROM parents WHERE user_id=? LIMIT 1");
        $parent->execute([$uid]);
        $parent = $parent->fetch() ?: null;
        break;

    case $role === 'librarian':
        $librarian = $pdo->prepare("SELECT * FROM staff WHERE user_id=? LIMIT 1");
        $librarian->execute([$uid]);
        $librarian = $librarian->fetch() ?: ['first_name'=>$user['name'],'last_name'=>''];
        break;

    case in_array($role, ['discipline_officer','ict_officer']):
        $officer = $pdo->prepare("SELECT * FROM staff WHERE user_id=? LIMIT 1");
        $officer->execute([$uid]);
        $officer = $officer->fetch() ?: ['first_name'=>$user['name'],'last_name'=>''];
        break;
}

// ── POST actions ──────────────────────────────────────────────
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

// ── Query ─────────────────────────────────────────────────────
$filter  = $_GET['filter'] ?? 'all';
$page    = max(1,(int)($_GET['p'] ?? 1));
$perPage = 20;

$where = ["n.user_id=$uid"];
if ($filter === 'unread') $where[] = "n.is_read=0";
if ($filter === 'read')   $where[] = "n.is_read=1";
$ws = implode(' AND ', $where);

$total  = (int)$pdo->query("SELECT COUNT(*) FROM notifications n WHERE $ws")->fetchColumn();
$unread = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id=$uid AND is_read=0")->fetchColumn();
$offset = ($page - 1) * $perPage;
$pages  = max(1, (int)ceil($total / $perPage));

$notifs = $pdo->query(
    "SELECT * FROM notifications n WHERE $ws ORDER BY n.created_at DESC LIMIT $perPage OFFSET $offset"
)->fetchAll();

// Auto mark-read on view
if (!empty($notifs)) {
    $ids = implode(',', array_column($notifs, 'id'));
    $pdo->query("UPDATE notifications SET is_read=1 WHERE id IN ($ids) AND user_id=$uid");
}

// ── Icon / colour map ─────────────────────────────────────────
$typeIcon = [
    'marks_submitted'      => ['✏️', 'var(--blue)'],
    'marks_approved'       => ['✅', 'var(--green)'],
    'marks_returned'       => ['↩️', 'var(--warning)'],
    'marks_rejected'       => ['❌', 'var(--error)'],
    'attendance_absent'    => ['📆', 'var(--error)'],
    'attendance_open'      => ['📆', 'var(--primary)'],
    'discipline_notice'    => ['⚠️', 'var(--warning)'],
    'parent_notified'      => ['📞', 'var(--blue)'],
    'report_card_published'=> ['📑', 'var(--green)'],
    'announcement'         => ['📢', 'var(--primary)'],
    'fee_reminder'         => ['💰', 'var(--warning)'],
    'system'               => ['⚙️', 'var(--ink-soft)'],
    'info'                 => ['ℹ️', 'var(--blue)'],
    'success'              => ['✅', 'var(--green)'],
    'warning'              => ['⚠️', 'var(--warning)'],
    'error'                => ['❌', 'var(--error)'],
];

function notif_timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return floor($diff/60).'m ago';
    if ($diff < 86400)  return floor($diff/3600).'h ago';
    if ($diff < 604800) return floor($diff/86400).'d ago';
    return date('d M Y', strtotime($datetime));
}

// ── Which nav file to include ─────────────────────────────────
$navFile = match(true) {
    in_array($role,['teacher','class_teacher']) => __DIR__.'/teacher/includes/nav.php',
    $role === 'student'            => __DIR__.'/student/includes/nav.php',
    $role === 'parent'             => __DIR__.'/parent/includes/nav.php',
    $role === 'librarian'          => __DIR__.'/librarian/includes/nav.php',
    $role === 'discipline_officer' => __DIR__.'/discipline/includes/nav.php',
    $role === 'ict_officer'        => __DIR__.'/ict/includes/nav.php',
    default                        => null,
};

// ── For admin/staff roles without a portal sidebar, redirect to admin ─
if (!$navFile) {
    // Show within admin layout
    $pageTitle   = 'Notifications';
    $activeAdmin = '';
    include dirname(__DIR__).'/includes/admin_header.php';
    $showAdminLayout = true;
} else {
    $showAdminLayout = false;
}
?>
<?php if (!$showAdminLayout): ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Notifications — KHS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css"/>
</head>
<body>
<div class="portal-grid">
<?php include $navFile; ?>
<div class="portal-content">
<?php endif; ?>

  <!-- ── Page header ── -->
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px">
    <div>
      <h1 style="font-size:22px;font-weight:800;margin:0;display:flex;align-items:center;gap:10px">
        🔔 Notifications
        <?php if ($unread > 0): ?>
        <span style="font-size:13px;background:var(--error);color:#fff;padding:3px 10px;border-radius:12px;font-weight:700"><?= $unread ?> new</span>
        <?php endif; ?>
      </h1>
      <p style="margin:4px 0 0;font-size:13px;color:var(--ink-soft)">Your activity feed across all school modules.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <?php if ($total > 0): ?>
      <form method="post" style="display:inline">
        <?= csrfField() ?><input type="hidden" name="action" value="mark_read"/>
        <button class="button button-secondary button-sm">✓ Mark all read</button>
      </form>
      <form method="post" style="display:inline" onsubmit="return confirm('Delete all notifications? This cannot be undone.')">
        <?= csrfField() ?><input type="hidden" name="action" value="clear_all"/>
        <button class="button button-danger button-sm">🗑 Clear all</button>
      </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Filter tabs ── -->
  <div style="display:flex;gap:4px;margin-bottom:18px;border-bottom:2px solid var(--line)">
    <?php foreach(['all'=>'All','unread'=>'Unread','read'=>'Read'] as $f=>$flabel): ?>
    <a href="?filter=<?= $f ?>"
       style="padding:9px 18px;font-size:13px;font-weight:700;text-decoration:none;
              border-bottom:3px solid <?= $filter===$f?'var(--primary)':'transparent' ?>;
              color:<?= $filter===$f?'var(--primary)':'var(--ink-soft)' ?>;
              margin-bottom:-2px;transition:color .15s">
      <?= $flabel ?>
      <?php if ($f==='unread' && $unread>0): ?>
      <span style="background:var(--error);color:#fff;font-size:10px;padding:2px 6px;border-radius:8px;margin-left:3px;font-weight:700"><?= $unread ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>

  <!-- ── List ── -->
  <?php if (empty($notifs)): ?>
  <div style="text-align:center;padding:64px 20px;background:var(--surface);border:1px solid var(--line);border-radius:var(--radius)">
    <div style="font-size:52px;margin-bottom:16px">🔔</div>
    <h3 style="font-weight:800;margin:0 0 8px"><?= $filter==='unread'?'All caught up!':'No notifications' ?></h3>
    <p style="color:var(--ink-soft);margin:0"><?= $filter==='unread'?'No unread notifications right now.':'Notifications will appear here as activity happens.' ?></p>
  </div>
  <?php else: ?>

  <div style="background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);overflow:hidden">
    <?php foreach ($notifs as $n):
      [$icon, $color] = $typeIcon[$n['type']] ?? ['🔔', 'var(--primary)'];
    ?>
    <div class="notif-item">
      <div class="notif-icon" style="background:<?= $color ?>22;color:<?= $color ?>"><?= $icon ?></div>
      <div class="notif-body">
        <div class="notif-title"><?= e($n['title']) ?></div>
        <div class="notif-msg"><?= e($n['message']) ?></div>
        <div class="notif-time">
          <?= notif_timeAgo($n['created_at']) ?>
          &middot; <?= date('d M Y, H:i', strtotime($n['created_at'])) ?>
        </div>
      </div>
      <div style="display:flex;flex-direction:column;gap:5px;flex-shrink:0;align-items:flex-end">
        <?php if ($n['link']): ?>
        <a href="<?= e($n['link']) ?>" class="button button-secondary button-sm" style="font-size:11px;white-space:nowrap">View →</a>
        <?php endif; ?>
        <form method="post">
          <?= csrfField() ?>
          <input type="hidden" name="action"   value="delete"/>
          <input type="hidden" name="notif_id" value="<?= $n['id'] ?>"/>
          <button class="button button-secondary button-sm" style="font-size:11px;color:var(--ink-faint)" title="Delete">✕</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
  <div style="display:flex;justify-content:center;gap:6px;margin-top:18px">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
    <a href="?filter=<?= $filter ?>&p=<?= $p ?>"
       style="padding:6px 13px;border-radius:6px;font-size:13px;font-weight:700;text-decoration:none;
              background:<?= $p===$page?'var(--primary)':'var(--surface)' ?>;
              color:<?= $p===$page?'#fff':'var(--ink-soft)' ?>;
              border:1px solid <?= $p===$page?'var(--primary)':'var(--line)' ?>">
      <?= $p ?>
    </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

  <?php endif; ?>

<?php if (!$showAdminLayout): ?>
</div><!-- .portal-content -->
</div><!-- .portal-grid -->
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
<?php else: ?>
<?php include dirname(__DIR__).'/includes/admin_footer.php'; ?>
<?php endif; ?>
