<?php
// ============================================================
// Student Portal — Quiz Answer Saver & Final Submission
// Handles both:
//   action=save_answer  → AJAX save single answer, return JSON
//   action=submit_final → Grade quiz, save score, redirect
// ============================================================
require_once dirname(__DIR__,2).'/config/db.php';
requireAuth(); requireRole('student');

$pdo    = db();
$user   = currentUser();
$action = $_POST['action'] ?? '';

if (!in_array($action, ['save_answer','submit_final'], true)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Invalid action']);
    exit;
}

// ── Auth: load student ─────────────────────────────────────────
$student = $pdo->prepare("SELECT id FROM students WHERE user_id=? LIMIT 1");
$student->execute([$user['id']]); $student = $student->fetch();
if (!$student) {
    if ($action==='save_answer') { echo json_encode(['ok'=>false,'error'=>'No student record']); exit; }
    redirect(BASE_URL.'/portal/student/');
}
$studentId = (int)$student['id'];

// CSRF
try { verifyCsrf(); } catch (Throwable $e) {
    if ($action==='save_answer') { echo json_encode(['ok'=>false,'error'=>'CSRF']); exit; }
    redirect(BASE_URL.'/portal/student/assessments.php?tab=quizzes');
}

$quizId    = (int)($_POST['quiz_id']    ?? 0);
$attemptId = (int)($_POST['attempt_id'] ?? 0);

if (!$quizId || !$attemptId) {
    if ($action==='save_answer') { echo json_encode(['ok'=>false,'error'=>'Missing IDs']); exit; }
    redirect(BASE_URL.'/portal/student/assessments.php?tab=quizzes');
}

// Verify attempt belongs to this student
$attempt = $pdo->query(
    "SELECT * FROM quiz_attempts WHERE id=$attemptId AND student_id=$studentId AND quiz_id=$quizId AND in_progress=1 LIMIT 1"
)->fetch();

if (!$attempt) {
    if ($action==='save_answer') { echo json_encode(['ok'=>false,'error'=>'Attempt not found or already submitted']); exit; }
    redirect(BASE_URL.'/portal/student/take_quiz.php?quiz_id='.$quizId);
}

// Load current responses
$responses = json_decode($attempt['responses'] ?? '{}', true) ?: [];

// ── SAVE ANSWER ───────────────────────────────────────────────
if ($action === 'save_answer') {
    header('Content-Type: application/json');
    $questionId = (int)($_POST['question_id'] ?? 0);
    $answer     = trim($_POST['answer'] ?? '');
    if (!$questionId) { echo json_encode(['ok'=>false,'error'=>'No question_id']); exit; }

    // Verify question belongs to this quiz
    $qRow = $pdo->query("SELECT id FROM quiz_questions WHERE id=$questionId AND quiz_id=$quizId LIMIT 1")->fetch();
    if (!$qRow) { echo json_encode(['ok'=>false,'error'=>'Invalid question']); exit; }

    // Update responses
    $responses[$questionId] = $answer !== '' ? $answer : null;
    $responsesJson = json_encode($responses);
    $pdo->prepare("UPDATE quiz_attempts SET responses=? WHERE id=?")->execute([$responsesJson, $attemptId]);

    $answeredCount = count(array_filter($responses, fn($v)=>$v!==null && $v!==''));
    $totalQ = (int)$pdo->query("SELECT COUNT(*) FROM quiz_questions WHERE quiz_id=$quizId")->fetchColumn();

    echo json_encode(['ok'=>true, 'answered'=>$answeredCount, 'total'=>$totalQ]);
    exit;
}

// ── SUBMIT FINAL ──────────────────────────────────────────────
// Load all questions
$questions = $pdo->query(
    "SELECT id, type, answer, marks FROM quiz_questions WHERE quiz_id=$quizId ORDER BY sequence"
)->fetchAll();

// Grade: auto-grade MCQ and true/false; short answer = 0 for now (manual grading)
$score      = 0.0;
$totalMarks = 0.0;
$autoGraded = true;
$breakdown  = [];

foreach ($questions as $q) {
    $totalMarks += (float)$q['marks'];
    $studentAns  = trim($responses[$q['id']] ?? '');
    $correctAns  = trim($q['answer'] ?? '');
    $earnedMarks = 0.0;
    $correct     = false;

    if ($q['type'] === 'mcq' || $q['type'] === 'true_false') {
        // Case-insensitive comparison
        if (strtolower($studentAns) === strtolower($correctAns)) {
            $earnedMarks = (float)$q['marks'];
            $correct     = true;
        }
    } elseif ($q['type'] === 'short_answer') {
        // Partial: check if correct answer keyword appears in student answer
        if ($correctAns !== '' && $studentAns !== '') {
            if (stripos($studentAns, $correctAns) !== false || stripos($correctAns, $studentAns) !== false) {
                $earnedMarks = (float)$q['marks'];
                $correct     = true;
            }
        }
        // If short answers need manual grading, mark autoGraded=false for those
        if (!$correct && $studentAns !== '') $autoGraded = false;
    }

    $score += $earnedMarks;
    $breakdown[$q['id']] = [
        'student_answer' => $studentAns,
        'correct_answer' => $correctAns,
        'marks_earned'   => $earnedMarks,
        'max_marks'      => (float)$q['marks'],
        'correct'        => $correct,
    ];
}

$score      = round($score, 2);
$totalMarks = round($totalMarks, 2);
$pct        = $totalMarks > 0 ? round($score/$totalMarks*100, 1) : 0;

// Update quiz total_marks if not set
$pdo->prepare("UPDATE teacher_quizzes SET total_marks=? WHERE id=? AND (total_marks IS NULL OR total_marks=0)")
    ->execute([$totalMarks, $quizId]);

// Save attempt results
$pdo->prepare(
    "UPDATE quiz_attempts SET
        responses=?, score=?, total_marks=?, submitted_at=NOW(),
        in_progress=0, auto_graded=?, time_spent=TIMESTAMPDIFF(SECOND,started_at,NOW())
     WHERE id=?"
)->execute([
    json_encode($responses),
    $score, $totalMarks,
    $autoGraded ? 1 : 0,
    $attemptId
]);

// Flash result
flash('success', "Quiz submitted! Your score: $score/$totalMarks ($pct%).");

// ── Redirect to results page ──────────────────────────────────
redirect(BASE_URL.'/portal/student/quiz_result.php?attempt_id='.$attemptId.'&quiz_id='.$quizId);
