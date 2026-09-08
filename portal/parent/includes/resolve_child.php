<?php
// ============================================================
// Parent Portal — Shared child-resolution helper
// Include AFTER requireRole('parent').
// Sets: $guardian, $children, $selChild, $child
// ============================================================
$guardian = null;
$children = [];

try {
    $g = db()->prepare("SELECT id FROM guardians WHERE user_id=? LIMIT 1");
    $g->execute([currentUserId()]);
    $guardian = $g->fetch() ?: null;
} catch (Throwable $e) {}

if ($guardian) {
    try {
        $c = db()->prepare(
            "SELECT s.*, g.name grade_name, c.name class_name
             FROM student_guardians sg
             JOIN students  s ON s.id  = sg.student_id
             LEFT JOIN grades  g ON g.id  = s.current_grade_id
             LEFT JOIN classes c ON c.id  = s.current_class_id
             WHERE sg.guardian_id = ?
             ORDER BY s.first_name"
        );
        $c->execute([$guardian['id']]);
        $children = $c->fetchAll();
    } catch (Throwable $e) {}
}

// Fallback: match by phone/email stored on student
if (empty($children)) {
    try {
        $u = currentUser();
        $c2 = db()->prepare(
            "SELECT s.*, g.name grade_name, c.name class_name
             FROM students s
             LEFT JOIN grades  g ON g.id = s.current_grade_id
             LEFT JOIN classes c ON c.id = s.current_class_id
             WHERE s.status='Active' AND (s.phone=? OR s.email=?)
             ORDER BY s.first_name"
        );
        $c2->execute([$u['phone'] ?? '', $u['email'] ?? '']);
        $children = $c2->fetchAll();
    } catch (Throwable $e) {}
}

$selChild = (int)($_GET['child_id'] ?? ($children[0]['id'] ?? 0));
$child    = null;
foreach ($children as $ch) {
    if ($ch['id'] == $selChild) { $child = $ch; break; }
}
