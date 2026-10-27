<?php

require_once __DIR__ . '/db.php';

$user = require_login($pdo);

$method =
    $_SERVER['REQUEST_METHOD'];


if ($method === 'GET') {

    $stmt =
        $pdo->query(
            "SELECT
                i.*,
                d.status AS donation_status,
                d.donation_date,
                d.charity_name
             FROM items i
             LEFT JOIN donations d
                ON d.item_id = i.id
             WHERE
                i.report_type = 'found'
                AND i.approval_status = 'approved'
                AND i.status IN (
                    'open',
                    'matched'
                )
                AND i.created_at <= DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY
                i.event_date ASC"
        );


    $items = $stmt->fetchAll();

    foreach ($items as &$row) {
        unset($row['security_answer_1']);
        unset($row['security_answer_2']);
    }
    unset($row);

    json_response([
        'items' => $items
    ]);
}


if ($method === 'POST') {

    // Only the admin can send an item to charity.
    require_admin($pdo);

    $data =
        request_json();


    $itemId =
        (int)(
            $data['item_id'] ?? 0
        );


    $stmt =
        $pdo->prepare(
            "INSERT INTO donations (
                item_id,
                donation_date,
                status,
                charity_name
            )
            VALUES (?, CURDATE(), 'donated', ?)"
        );


    $stmt->execute([

        $itemId,

        clean(
            $data['charity_name']
                ?? 'Campus Charity Drive'
        )
    ]);


    $stmt =
        $pdo->prepare(
            "UPDATE items
             SET status = 'donated'
             WHERE id = ?"
        );


    $stmt->execute([
        $itemId
    ]);


    json_response([
        'success' => true
    ]);
}


json_response([
    'error' =>
        'Unsupported request.'
], 405);
