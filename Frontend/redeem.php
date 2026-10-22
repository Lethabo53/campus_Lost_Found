<?php

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$user = require_login($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Return this user's current balance and redemption history.
    $stmt = $pdo->prepare("SELECT id, code, credits, status, created_at, fulfilled_at FROM redemptions WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);

    $balanceStmt = $pdo->prepare("SELECT snack_credits FROM users WHERE id = ?");
    $balanceStmt->execute([$user['id']]);

    json_response([
        'balance'     => (int) $balanceStmt->fetchColumn(),
        'redemptions' => $stmt->fetchAll(),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data    = request_json();
    $credits = (int) ($data['credits'] ?? 0);

    if ($credits < 5) {
        json_response(['error' => 'You must redeem at least 5 snack credits at a time.'], 422);
    }

    // Re-check the live balance under the current user, not a stale client-supplied value.
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT snack_credits FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$user['id']]);
    $balance = (int) $stmt->fetchColumn();

    if ($credits > $balance) {
        $pdo->rollBack();
        json_response(['error' => "You only have $balance snack credits available."], 422);
    }

    $code = make_redemption_code();

    $pdo->prepare("UPDATE users SET snack_credits = snack_credits - ? WHERE id = ?")
        ->execute([$credits, $user['id']]);

    $pdo->prepare("INSERT INTO redemptions (user_id, code, credits, status) VALUES (?, ?, ?, 'pending')")
        ->execute([$user['id'], $code, $credits]);

    $pdo->commit();

    json_response([
        'success'         => true,
        'code'            => $code,
        'credits'         => $credits,
        'remaining_balance' => $balance - $credits,
        'message'         => 'Show this code at the campus snack venue to claim your snack.',
    ]);
}

json_response(['error' => 'Unsupported request.'], 405);