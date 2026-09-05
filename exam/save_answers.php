<?php
require_once dirname(__DIR__).'/config/db.php';
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD']!=='POST') json_out(['ok'=>false],405);
$data = json_decode(file_get_contents('php://input'),true) ?? [];
$attemptId = (int)($data['attempt_id']??0);
$answers   = $data['answers'] ?? [];
if (!$attemptId) json_out(['ok'=>false,'message'=>'Invalid attempt']);
// Verify attempt exists and is in progress
$att = db()->prepare("SELECT id,status FROM entrance_exam_attempts WHERE id=?")->execute([$attemptId]) ? db()->query("SELECT id,status FROM entrance_exam_attempts WHERE id=$attemptId")->fetch() : null;
if (!$att || $att['status']==='submitted') json_out(['ok'=>false,'message'=>'Attempt not found or already submitted']);
foreach ($answers as $qIndex => $val) {
    // val can be option_id (int) or answer text (string)
    $qId = (int)(db()->query("SELECT id FROM entrance_questions LIMIT 1 OFFSET $qIndex")->fetchColumn()??0);
    if (!$qId) continue;
    $isOptId = is_numeric($val);
    db()->prepare("INSERT INTO entrance_answers (attempt_id,question_id,answer_text,option_id) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE answer_text=VALUES(answer_text),option_id=VALUES(option_id),saved_at=NOW()")
       ->execute([$attemptId,$qId,$isOptId?null:(string)$val,$isOptId?(int)$val:null]);
}
json_out(['ok'=>true]);
