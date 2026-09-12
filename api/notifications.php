<?php
// ============================================================
// API — Notifications
// GET  ?action=count         → {count: N}
// GET  ?action=list&limit=N  → {items:[...], unread:N}
// POST action=mark_read &id=N (or all)
// POST action=delete    &id=N
// ============================================================
require_once dirname(__DIR__).'/config/db.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error'=>'Unauthenticated']);
    exit;
}

$uid    = currentUserId();
$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'GET' ? ($_GET['action']??'count') : ($_POST['action']??'');

// ── GET ───────────────────────────────────────────────────────
if ($method === 'GET') {
    if ($action === 'count') {
        try {
            $n = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id=$uid AND is_read=0")->fetchColumn();
            echo json_encode(['count'=>$n, 'has_unread'=>$n>0]);
        } catch (Throwable $e) { echo json_encode(['count'=>0,'has_unread'=>false]); }
        exit;
    }

    if ($action === 'list') {
        $limit = min(50, max(1, (int)($_GET['limit']??10)));
        $onlyUnread = ($_GET['unread']??'') === '1';
        $where = $onlyUnread ? "user_id=$uid AND is_read=0" : "user_id=$uid";
        try {
            $items = $pdo->query(
                "SELECT id, type, title, message, link, is_read, created_at
                 FROM notifications WHERE $where
                 ORDER BY created_at DESC LIMIT $limit"
            )->fetchAll(PDO::FETCH_ASSOC);
            $unread = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id=$uid AND is_read=0")->fetchColumn();
            echo json_encode(['items'=>$items, 'unread'=>$unread]);
        } catch (Throwable $e) { echo json_encode(['items'=>[],'unread'=>0]); }
        exit;
    }
}

// ── POST ──────────────────────────────────────────────────────
if ($method === 'POST') {
    // CSRF not required for API calls (bearer-less, session-based)
    // But we check the session is valid (already done by isLoggedIn())

    if ($action === 'mark_read') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            if ($id > 0) {
                $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([$id,$uid]);
            } else {
                $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);
            }
            $remaining = (int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id=$uid AND is_read=0")->fetchColumn();
            echo json_encode(['ok'=>true, 'remaining_unread'=>$remaining]);
        } catch (Throwable $e) { echo json_encode(['ok'=>false]); }
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM notifications WHERE id=? AND user_id=?")->execute([$id,$uid]);
                echo json_encode(['ok'=>true]);
            } catch (Throwable $e) { echo json_encode(['ok'=>false]); }
        } else {
            echo json_encode(['ok'=>false,'error'=>'Missing id']);
        }
        exit;
    }
}

http_response_code(400);
echo json_encode(['error'=>'Invalid request']);
