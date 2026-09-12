<?php
// ============================================================
// Teacher Portal — Shared teacher record resolver
// Include AFTER requireRole(['teacher','class_teacher']).
// Sets:
//   $teacher          — teachers table row
//   $teacherId        — teachers.id (int)
//   $isClassSponsor   — true if this teacher sponsors any class
//                       (via classes.teacher_id OR role=class_teacher)
//   $sponsoredClassId — the class ID this teacher sponsors (or 0)
//   $sponsoredClass   — the full class row (or null)
// ============================================================
$pdo  = $pdo  ?? db();
$user = $user ?? currentUser();
$ayId = $ayId ?? currentAcademicYearId();

// ── 1. Resolve teacher record ────────────────────────────────
$_tr = $pdo->prepare("SELECT * FROM teachers WHERE user_id=? LIMIT 1");
$_tr->execute([$user['id']]);
$teacher = $_tr->fetch();

if (!$teacher) {
    // Auto-create a minimal teacher record so portal never hard-blocks
    try {
        $np        = explode(' ', trim($user['name'] ?? 'Teacher User'));
        $firstName = $np[0];
        $lastName  = count($np) > 1 ? implode(' ', array_slice($np, 1)) : 'Staff';
        $autoId    = 'TCH-AUTO-' . str_pad($user['id'], 4, '0', STR_PAD_LEFT);
        $pdo->prepare(
            "INSERT IGNORE INTO teachers
             (user_id,teacher_id,first_name,last_name,gender,email,qualification,specialization,employment_date,status)
             VALUES (?,?,?,?,'Male',?,'B.Ed.','General',CURDATE(),'Active')"
        )->execute([$user['id'],$autoId,$firstName,$lastName,$user['email']??'']);
        $_tr2 = $pdo->prepare("SELECT * FROM teachers WHERE user_id=? LIMIT 1");
        $_tr2->execute([$user['id']]);
        $teacher = $_tr2->fetch();
    } catch (Throwable $_e) {}
}

// Synthetic fallback — portal renders even if DB insert failed
if (!$teacher) {
    $np = explode(' ', trim($user['name'] ?? 'Teacher User'));
    $teacher = [
        'id'             => 0,
        'teacher_id'     => 'TEMP-'.$user['id'],
        'first_name'     => $np[0],
        'last_name'      => implode(' ', array_slice($np, 1)),
        'specialization' => 'Teacher',
        'email'          => $user['email'] ?? '',
        'user_id'        => $user['id'],
    ];
}

$teacherId = (int)$teacher['id'];

// ── 2. Detect class sponsorship ──────────────────────────────
// A teacher is a class sponsor if:
//   (a) their role is 'class_teacher', OR
//   (b) they appear as classes.teacher_id for any active class
//       in the current academic year
//
// This means a subject teacher can ALSO be a sponsor.
$isClassSponsor   = false;
$sponsoredClassId = 0;
$sponsoredClass   = null;

// Always true for class_teacher role
if (hasRole('class_teacher')) {
    $isClassSponsor = true;
}

// Also check via classes.teacher_id (covers subject teachers who sponsor a class)
if ($teacherId > 0) {
    try {
        $_sc = $pdo->query(
            "SELECT c.*, g.name grade_name
             FROM classes c
             JOIN grades g ON g.id=c.grade_id
             WHERE c.teacher_id=$teacherId
               AND c.academic_year_id=$ayId
             LIMIT 1"
        )->fetch();
        if ($_sc) {
            $isClassSponsor   = true;
            $sponsoredClass   = $_sc;
            $sponsoredClassId = (int)$_sc['id'];
        }
    } catch (Throwable $_e) {}
}

// If role is class_teacher but no class is assigned yet via classes.teacher_id,
// fall back to their first teacher_assignment class
if ($isClassSponsor && !$sponsoredClassId && $teacherId > 0) {
    try {
        $_fc = $pdo->query(
            "SELECT c.*, g.name grade_name
             FROM teacher_assignments ta
             JOIN classes c ON c.id=ta.class_id
             JOIN grades  g ON g.id=c.grade_id
             WHERE ta.teacher_id=$teacherId
               AND ta.academic_year_id=$ayId
             ORDER BY g.sequence, c.name
             LIMIT 1"
        )->fetch();
        if ($_fc) {
            $sponsoredClass   = $_fc;
            $sponsoredClassId = (int)$_fc['id'];
        }
    } catch (Throwable $_e) {}
}

// ── 3. Load sponsored class subjects (all subjects, not just teacher's own) ──
// Class sponsor can see ALL subjects taught in their class
$sponsoredClassSubjects = [];
if ($isClassSponsor && $sponsoredClassId) {
    try {
        $sponsoredClassSubjects = $pdo->query(
            "SELECT DISTINCT sub.id, sub.name, sub.code,
                    t.first_name t_first, t.last_name t_last,
                    ta.teacher_id sub_teacher_id
             FROM teacher_assignments ta
             JOIN subjects sub ON sub.id=ta.subject_id
             LEFT JOIN teachers t ON t.id=ta.teacher_id
             WHERE ta.class_id=$sponsoredClassId
               AND ta.academic_year_id=$ayId
             ORDER BY sub.name"
        )->fetchAll();
    } catch (Throwable $_e) {}
}

// ── 4. My own assigned subjects (what I personally teach) ───
$myAssignedSubjects = [];
if ($teacherId > 0) {
    try {
        $myAssignedSubjects = $pdo->query(
            "SELECT DISTINCT sub.id, sub.name, sub.code, ta.class_id, c.name class_name
             FROM teacher_assignments ta
             JOIN subjects sub ON sub.id=ta.subject_id
             JOIN classes  c   ON c.id=ta.class_id
             WHERE ta.teacher_id=$teacherId
               AND ta.academic_year_id=$ayId
             ORDER BY sub.name, c.name"
        )->fetchAll();
    } catch (Throwable $_e) {}
}
