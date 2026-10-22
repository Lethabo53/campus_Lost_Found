<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

require_login($pdo);

$data   = request_json();
$itemId = (int) ($data['item_id'] ?? 0);
$action = clean($data['action'] ?? '');

$allowedActions = ['mark_reunited', 'mark_donated'];

if (!$itemId || !in_array($action, $allowedActions, true)) {
    json_response(['error' => 'Invalid request.'], 422);
}

$stmt = $pdo->prepare("SELECT * FROM items WHERE id = ? LIMIT 1");
$stmt->execute([$itemId]);
$item = $stmt->fetch();

if (!$item) {
    json_response(['error' => 'Item not found.'], 404);
}

if ($action === 'mark_reunited') {

    $pdo->prepare("UPDATE items SET status = 'reunited' WHERE id = ?")
        ->execute([$itemId]);

    // Bonus points to the finder when a found item is confirmed reunited.
    if ($item['report_type'] === 'found') {
        $userId = find_or_create_user($pdo, $item['reporter_name'], $item['reporter_email']);
        $pdo->prepare("UPDATE users SET points = points + 15 WHERE id = ?")->execute([$userId]);
    }

    json_response(['success' => true, 'status' => 'reunited']);
}

if ($action === 'mark_donated') {

    $pdo->prepare("UPDATE items SET status = 'donated' WHERE id = ?")
        ->execute([$itemId]);

    $existing = $pdo->prepare("SELECT id FROM donations WHERE item_id = ? LIMIT 1");
    $existing->execute([$itemId]);

    if ($existing->fetch()) {
        $pdo->prepare("UPDATE donations SET donation_date = CURDATE(), status = 'donated' WHERE item_id = ?")
            ->execute([$itemId]);
    } else {
        $pdo->prepare("
            INSERT INTO donations (item_id, donation_date, status, charity_name)
            VALUES (?, CURDATE(), 'donated', 'Campus Charity Drive')
        ")->execute([$itemId]);
    }

    json_response(['success' => true, 'status' => 'donated']);
}
