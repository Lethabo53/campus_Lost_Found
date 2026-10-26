<?php

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

require_admin($pdo);

$stmt = $pdo->query("
    SELECT r.id, r.code, r.credits, r.status, r.created_at, u.full_name, u.email
    FROM redemptions r
    JOIN users u ON u.id = r.user_id
    WHERE r.status = 'pending'
    ORDER BY r.created_at ASC
");

json_response(['redemptions' => $stmt->fetchAll()]);