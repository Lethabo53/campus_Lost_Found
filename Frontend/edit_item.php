<?php
/*
 * Edit a report.
 *  - The student who made the report may edit it ONLY while it is still waiting for approval
 *    (pending) or after the admin rejected it (the edit then sends it back to the admin as pending).
 *  - After approval the details are locked for students. Only the admin can edit them.
 *  - Only the admin can change the security questions and answers of a found item.
 */
require_once __DIR__ . '/db.php';

$user = require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed.'], 405);
}

$d       = request_json();
$itemId  = (int) ($d['item_id'] ?? 0);
$isAdmin = ($user['role'] ?? 'student') === 'admin';

$s = $pdo->prepare("SELECT id, report_type, reporter_email, approval_status FROM items WHERE id = ?");
$s->execute([$itemId]);
$item = $s->fetch();
if (!$item) json_response(['error' => 'Item not found.'], 404);

$isOwner = strtolower($user['email']) === strtolower((string) $item['reporter_email']);

if (!$isAdmin) {
    if (!$isOwner) {
        json_response(['error' => 'You can only edit your own reports.'], 403);
    }
    if (!in_array($item['approval_status'], ['pending', 'rejected'], true)) {
        json_response(['error' => 'This report has already been approved, so it is locked. Message the admin if something needs to change.'], 403);
    }
}

$title       = clean($d['title'] ?? '');
$category    = clean($d['category'] ?? '');
$color       = clean($d['color'] ?? '');
$description = clean($d['description'] ?? '');
$brand       = clean($d['brand_model'] ?? '');
$condition   = clean($d['condition_notes'] ?? '');
$eventDate   = clean($d['event_date'] ?? '');

if ($title === '' || $category === '' || $description === '' || $eventDate === '') {
    json_response(['error' => 'Title, category, description and date are required.'], 422);
}
if (mb_strlen($title) > 200 || mb_strlen($category) > 80 || mb_strlen($color) > 100 || mb_strlen($brand) > 150 || mb_strlen($condition) > 255) {
    json_response(['error' => 'One of the fields is too long.'], 422);
}
$dt = DateTime::createFromFormat('Y-m-d', $eventDate);
if (!$dt || $dt->format('Y-m-d') !== $eventDate) {
    json_response(['error' => 'Please enter a valid date.'], 422);
}
if ($eventDate > date('Y-m-d')) {
    json_response(['error' => 'The date cannot be in the future.'], 422);
}

$sql = "UPDATE items SET title = ?, category = ?, color = ?, description = ?, brand_model = ?, condition_notes = ?, event_date = ?";
$args = [$title, $category, $color ?: null, $description, $brand ?: null, $condition ?: null, $eventDate];

// Security questions/answers (found items): admin only
if ($isAdmin && $item['report_type'] === 'found') {
    $q1 = clean($d['security_question_1'] ?? ''); $a1 = clean($d['security_answer_1'] ?? '');
    $q2 = clean($d['security_question_2'] ?? ''); $a2 = clean($d['security_answer_2'] ?? '');
    if ($q1 === '' || $a1 === '' || $q2 === '' || $a2 === '') {
        json_response(['error' => 'Both security questions and both answers are required.'], 422);
    }
    $sql .= ", security_question_1 = ?, security_answer_1 = ?, security_question_2 = ?, security_answer_2 = ?";
    array_push($args, $q1, $a1, $q2, $a2);
}

// A student fixing a rejected report sends it back to the admin for a new review.
$resubmitted = false;
if (!$isAdmin && $item['approval_status'] === 'rejected') {
    $sql .= ", approval_status = 'pending', rejection_reason = NULL, reviewed_at = NULL, reviewed_by = NULL";
    $resubmitted = true;
}

$sql .= " WHERE id = ?";
$args[] = $itemId;
$pdo->prepare($sql)->execute($args);

json_response([
    'success'     => true,
    'resubmitted' => $resubmitted,
    'message'     => $resubmitted ? 'Report updated and sent back to the admin for approval.' : 'Report updated.',
]);