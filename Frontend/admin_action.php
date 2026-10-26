<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

require_admin($pdo);   // admin only: reunite, donate, fulfil snack codes

$data   = request_json();
$action = clean($data['action'] ?? '');

$allowedActions = ['mark_reunited', 'mark_donated', 'fulfill_redemption'];

if (!in_array($action, $allowedActions, true)) {
    json_response(['error' => 'Invalid request.'], 422);
}

if ($action === 'fulfill_redemption') {

    $code = clean($data['code'] ?? '');

    if ($code === '') {
        json_response(['error' => 'Redemption code is required.'], 422);
    }

    $stmt = $pdo->prepare("SELECT * FROM redemptions WHERE code = ? LIMIT 1");
    $stmt->execute([$code]);
    $redemption = $stmt->fetch();

    if (!$redemption) {
        json_response(['error' => 'No redemption found with that code.'], 404);
    }

    if ($redemption['status'] !== 'pending') {
        json_response(['error' => 'This code has already been ' . $redemption['status'] . '.'], 422);
    }

    $pdo->prepare("UPDATE redemptions SET status = 'fulfilled', fulfilled_at = NOW() WHERE id = ?")
        ->execute([$redemption['id']]);

    json_response(['success' => true, 'status' => 'fulfilled', 'credits' => (int) $redemption['credits']]);
}

$itemId = (int) ($data['item_id'] ?? 0);

if (!$itemId) {
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

    // The admin has handed the item over: close any verified claim for it.
    $pdo->prepare("UPDATE claims SET status = 'approved' WHERE item_id = ? AND status = 'pending'")
        ->execute([$itemId]);

    // Bonus points to the finder when a found item is confirmed reunited.
    if ($item['report_type'] === 'found') {
        $userId = find_or_create_user($pdo, $item['reporter_name'], $item['reporter_email']);
        $pdo->prepare("UPDATE users SET points = points + 15, snack_credits = snack_credits + 10 WHERE id = ?")->execute([$userId]);
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