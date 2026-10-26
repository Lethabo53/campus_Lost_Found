<?php

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$user = require_login($pdo);

$data = request_json();
if (!$data) {
    $data = $_POST;
}

$itemId = (int) ($data['item_id'] ?? 0);
if (!$itemId) {
    json_response(['error' => 'Invalid item.'], 422);
}

$stmt = $pdo->prepare("SELECT id, report_type, status, reporter_email FROM items WHERE id = ? LIMIT 1");
$stmt->execute([$itemId]);
$item = $stmt->fetch();

if (!$item) {
    json_response(['error' => 'Item not found.'], 404);
}

if ($item['report_type'] !== 'lost') {
    json_response(['error' => 'Only a lost report can be marked as found by its owner.'], 422);
}

// Only the person who reported the item lost may close it.
if (strtolower($item['reporter_email']) !== strtolower($user['email'])) {
    json_response(['error' => 'Only the person who reported this item lost can do this.'], 403);
}

if (!in_array($item['status'], ['open', 'matched'], true)) {
    json_response(['error' => 'This report is already closed.'], 422);
}

// Close the report. No reward points are given for your own item.
$pdo->prepare("UPDATE items SET status = 'closed' WHERE id = ?")->execute([$itemId]);

json_response(['success' => true, 'status' => 'closed']);