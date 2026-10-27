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

if (!$item || $item['approval_status'] !== 'approved') {
    json_response(['error' => 'Item not found.'], 404);
}

if ($item['report_type'] !== 'found') {
    json_response(['error' => 'Only found items can be claimed.'], 422);
}

if (!in_array($item['status'], ['open', 'matched'], true)) {
    json_response(['error' => 'This item is no longer available to claim.'], 422);
}

if (strtolower($item['reporter_email']) === strtolower($claimant['email'])) {
    json_response(['error' => "You reported this item yourself, so you can't claim it."], 422);
}

/*
 * Flexible answer check: the answers do not have to be identical. An answer is accepted when it
 * shares at least one meaningful word with the finder's expected answer (ignoring capital letters,
 * punctuation, small filler words like "the" / "a" / "in", and a plural "s").
 *   expected "Black Samsung phone"  ->  "samsung"  OK,  "my black phone"  OK,  "iphone"  not OK
 */
function answer_words(string $text): array {
    static $filler = ['the','a','an','is','it','its','of','in','on','at','and','or','to','my','was','with','for',
                      'i','me','be','are','by','as','this','that','there','near','inside','it\'s'];
    $text  = mb_strtolower($text, 'UTF-8');
    $text  = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text);
    $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    $out = [];
    foreach ($words as $w) {
        if (in_array($w, $filler, true)) continue;
        if (mb_strlen($w, 'UTF-8') > 3 && mb_substr($w, -1) === 's') $w = mb_substr($w, 0, -1);
        $out[$w] = true;
    }
    return array_keys($out);
}

function answers_match(?string $given, ?string $expected): bool {
    if ($expected === null || trim($expected) === '') return false;
    if (strcasecmp(trim((string) $given), trim($expected)) === 0) return true;   // identical
    return count(array_intersect(answer_words((string) $given), answer_words($expected))) >= 1;
}

$correct1 = answers_match($answer1, $item['security_answer_1']);
$correct2 = answers_match($answer2, $item['security_answer_2']);

$score  = ($correct1 ? 50 : 0) + ($correct2 ? 50 : 0);
$passed = $score === 100;

// 'pending' = answers verified, waiting for the admin to arrange the hand-over.
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
    ':status'         => $passed ? 'pending' : 'rejected',
]);

if (!$passed) {
    json_response([
        'success' => false,
        'error'   => "Those answers don't match our records. Double-check them, or contact the Lost & Found admin if you're sure this is your item.",
    ], 422);
}

// Mark claimed so no one else can claim it while the admin sorts out the hand-over.
$pdo->prepare("UPDATE items SET status = 'claimed' WHERE id = ? AND status IN ('open','matched')")
    ->execute([$itemId]);

// The finder's contact details are NOT shown. Communication starts with the admin.
json_response([
    'success' => true,
    'message' => 'Verified! The admin has been notified and will contact you to arrange the hand-over.',
]);