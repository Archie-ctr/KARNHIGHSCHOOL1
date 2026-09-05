<?php
require_once dirname(__DIR__).'/config/db.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD']!=='POST') json_out(['ok'=>false],405);
$data = json_decode(file_get_contents('php://input'),true) ?? [];
$attemptId   = (int)($data['attempt_id']??0);
$answers     = $data['answers'] ?? [];
$autoSubmit  = (bool)($data['auto_submitted']??false);
if (!$attemptId) json_out(['ok'=>false,'message'=>'Invalid attempt']);
$att = db()->prepare("SELECT a.*,e.passing_score,e.show_result,e.id exam_id FROM entrance_exam_attempts a JOIN entrance_exams e ON e.id=a.exam_id WHERE a.id=?")->execute([$attemptId]) ? db()->query("SELECT a.*,e.passing_score,e.show_result,e.id exam_id FROM entrance_exam_attempts a JOIN entrance_exams e ON e.id=a.exam_id WHERE a.id=$attemptId")->fetch() : null;
if (!$att || $att['status']==='submitted') json_out(['ok'=>false,'message'=>'Already submitted']);

// Save final answers
$pdo = db();
foreach ($answers as $qIdx => $val) {
    $qs = $pdo->prepare("SELECT q.id,q.correct_answer,q.marks FROM entrance_questions q WHERE q.exam_id=? AND q.is_active=1 ORDER BY q.id LIMIT 1 OFFSET $qIdx");
    $qs->execute([$att['exam_id']]); $q=$qs->fetch();
    if (!$q) continue;
    $isOptId = is_numeric($val) && (int)$val > 0;
    $isCorrect = null; $marksEarned = null;
    if ($isOptId) {
        $opt = $pdo->query("SELECT is_correct FROM entrance_question_options WHERE id=".(int)$val)->fetch();
        $isCorrect  = $opt ? (bool)$opt['is_correct'] : false;
        $marksEarned = $isCorrect ? $q['marks'] : 0;
    }
    $pdo->prepare("INSERT INTO entrance_answers (attempt_id,question_id,answer_text,option_id,is_correct,marks_earned) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE answer_text=VALUES(answer_text),option_id=VALUES(option_id),is_correct=VALUES(is_correct),marks_earned=VALUES(marks_earned),saved_at=NOW()")
       ->execute([$attemptId,$q['id'],$isOptId?null:(string)$val,$isOptId?(int)$val:null,$isCorrect,$marksEarned]);
}

// Calculate score
$score = (float)($pdo->query("SELECT COALESCE(SUM(marks_earned),0) FROM entrance_answers WHERE attempt_id=$attemptId")->fetchColumn()??0);
$maxScore = (float)($pdo->prepare("SELECT COALESCE(SUM(marks),0) FROM entrance_questions WHERE exam_id=? AND is_active=1")->execute([$att['exam_id']]) ? $pdo->query("SELECT COALESCE(SUM(marks),0) FROM entrance_questions WHERE exam_id={$att['exam_id']} AND is_active=1")->fetchColumn() : 0);
$pct    = $maxScore > 0 ? round(($score/$maxScore)*100,2) : 0;
$passed = $pct >= (float)$att['passing_score'] ? 1 : 0;

$pdo->prepare("UPDATE entrance_exam_attempts SET status='submitted',submitted_at=NOW(),score=?,max_score=?,percentage=?,passed=? WHERE id=?")
   ->execute([$score,$maxScore,$pct,$passed,$attemptId]);

// Update application
$pdo->prepare("UPDATE applications SET entrance_score=?,entrance_passed=?,entrance_status='Entrance completed',status=? WHERE id=?")->execute([$pct,$passed,$passed?'Entrance passed':'Entrance completed',$att['application_id']]);

$redirectUrl = BASE_URL.'/exam/result.php?attempt_id='.$attemptId;
json_out(['ok'=>true,'redirect'=>$redirectUrl,'score'=>$score,'percentage'=>$pct,'passed'=>(bool)$passed]);
