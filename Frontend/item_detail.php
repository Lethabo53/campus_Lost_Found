<?php

require_once __DIR__ . '/db.php';

require_login($pdo);

$id =
    (int)($_GET['id'] ?? 0);


if (!$id) {

    json_response([
        'error' =>
            'Invalid item.'
    ], 422);
}


$stmt =
    $pdo->prepare(
        "SELECT
            i.*,
            p.file_path AS photo
         FROM items i
         LEFT JOIN item_photos p
            ON p.item_id = i.id
         WHERE i.id = ?
         LIMIT 1"
    );


$stmt->execute([
    $id
]);


$item =
    $stmt->fetch();


if (!$item) {

    json_response([
        'error' =>
            'Item not found.'
    ], 404);
}


unset($item['security_answer_1']);
unset($item['security_answer_2']);

// The finder's contact details are never exposed here, under any status.
// The only way to see them is a successful verify_claim.php call.
if ($item['report_type'] === 'found') {
    unset($item['reporter_name']);
    unset($item['reporter_email']);
}

$matches = [];


if (
    $item['report_type'] === 'lost'
) {

    $stmt =
        $pdo->prepare(
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
             ORDER BY m.score DESC
             LIMIT 20"
        );


    $stmt->execute([
        $id
    ]);


    $matches =
        $stmt->fetchAll();
}


json_response([
    'item' =>
        $item,

    'matches' =>
        $matches
]);