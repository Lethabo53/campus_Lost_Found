<?php

require_once __DIR__ . '/db.php';

$viewer = require_login($pdo);

$id = (int) ($_GET['id'] ?? 0);

if (!$id) {
    json_response(['error' => 'Invalid item.'], 422);
}

$stmt = $pdo->prepare(
    "SELECT
        i.*,
        p.file_path AS photo,
        GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(i.created_at, INTERVAL 30 DAY))) AS seconds_left
     FROM items i
     LEFT JOIN item_photos p
        ON p.item_id = i.id
     WHERE i.id = ?
     LIMIT 1"
);
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    json_response(['error' => 'Item not found.'], 404);
}

$isAdmin = ($viewer['role'] ?? 'student') === 'admin';
$isOwner = strtolower($viewer['email']) === strtolower((string) ($item['reporter_email'] ?? ''));

// Items the admin has not approved yet are visible only to the admin and to the person who reported them.
if ($item['approval_status'] !== 'approved' && !$isAdmin && !$isOwner) {
    json_response(['error' => 'Item not found.'], 404);
}

$item['is_owner'] = $isOwner;
$item['is_admin'] = $isAdmin;

// 30-day claim window counted from the moment the item was inserted
$item['seconds_left'] = (int) $item['seconds_left'];
$item['hours_left']   = (int) ceil($item['seconds_left'] / 3600);

// Security answers and the finder's contact details are for the admin only.
if (!$isAdmin) {
    unset($item['security_answer_1']);
    unset($item['security_answer_2']);

    if ($item['report_type'] === 'found') {
        unset($item['reporter_name']);
        unset($item['reporter_email']);
    }
}

$matches = [];

if ($item['report_type'] === 'lost' && $item['approval_status'] === 'approved') {

    $stmt = $pdo->prepare(
        "SELECT
            m.*,
            i.ticket_code,
            i.title,
            i.category,
            i.color,
            i.description,
            i.location_name,
            i.status,
            p.file_path AS photo
         FROM matches m
         JOIN items i
            ON i.id = m.found_item_id
         LEFT JOIN item_photos p
            ON p.item_id = i.id
         WHERE m.lost_item_id = ?
         AND i.status IN ('open','matched')
         AND i.approval_status = 'approved'
         ORDER BY m.score DESC
         LIMIT 20"
    );
    $stmt->execute([$id]);
    $matches = $stmt->fetchAll();
}

json_response([
    'item'    => $item,
    'matches' => $matches,
]);