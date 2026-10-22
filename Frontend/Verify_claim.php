<?php

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$claimant = require_login($pdo);

$data     = request_json();
$itemId   = (int) ($data['item_id'] ?? 0);
$answer1  = clean($data['answer_1'] ?? '');
$answer2  = clean($data['answer_2'] ?? '');

if (!$itemId || $answer1 === '' || $answer2 === '') {
    json_response(['error' => 'Please answer both questions.'], 422);
}

$stmt = $pdo->prepare("SELECT * FROM items WHERE id = ? LIMIT 1");
$stmt->execute([$itemId]);
$item = $stmt->fetch();

if (!$item) {
    json_response(['error' => 'Item not found.'], 404);
}

if ($item['report_type'] !== 'found') {
    json_response(['error' => 'Only found items can be claimed.'], 422);
}

if (!in_array($item['status'], ['open', 'matched'], true)) {
    json_response(['error' => 'This item is no longer available to claim.'], 422);
}

$correct1 = $item['security_answer_1'] !== null
    && strcasecmp(trim($answer1), trim($item['security_answer_1'])) === 0;

$correct2 = $item['security_answer_2'] !== null
    && strcasecmp(trim($answer2), trim($item['security_answer_2'])) === 0;

$score  = ($correct1 ? 50 : 0) + ($correct2 ? 50 : 0);
$passed = $score === 100;

$claimStmt = $pdo->prepare("
    INSERT INTO claims (item_id, claimant_name, claimant_email, answer_1, answer_2, verification_score, status)
    VALUES (:item_id, :claimant_name, :claimant_email, :answer_1, :answer_2, :score, :status)
");
$claimStmt->execute([
    ':item_id'        => $itemId,
    ':claimant_name'  => $claimant['full_name'],
    ':claimant_email' => $claimant['email'],
    ':answer_1'       => $answer1,
    ':answer_2'       => $answer2,
    ':score'          => $score,
    ':status'         => $passed ? 'approved' : 'rejected',
]);

if (!$passed) {
    json_response([
        'success' => false,
        'error'   => "Those answers don't match our records. Double-check them, or contact the Lost & Found office if you're sure this is your item.",
    ], 422);
}

// Mark claimed so no one else can claim it while this is sorted out.
$pdo->prepare("UPDATE items SET status = 'claimed' WHERE id = ? AND status IN ('open','matched')")
    ->execute([$itemId]);

json_response([
    'success' => true,
    'contact' => [
        'name'  => $item['reporter_name'],
        'email' => $item['reporter_email'],
    ],
]);