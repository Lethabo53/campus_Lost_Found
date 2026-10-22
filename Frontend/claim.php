<?php

require_once __DIR__ . '/db.php';

require_login($pdo);

function count_items(
    PDO $pdo,
    string $type
): int {

    $stmt =
        $pdo->prepare(
            "SELECT COUNT(*)
             FROM items
             WHERE report_type = ?"
        );

    $stmt->execute([
        $type
    ]);

    return (int)$stmt->fetchColumn();
}


$totalLost =
    count_items(
        $pdo,
        'lost'
    );


$totalFound =
    count_items(
        $pdo,
        'found'
    );


$stmt =
    $pdo->query(
        "SELECT COUNT(*)
         FROM items
         WHERE status = 'reunited'"
    );

$reunited =
    (int)$stmt->fetchColumn();


$stmt =
    $pdo->query(
        "SELECT COUNT(*)
         FROM items
         WHERE status = 'donated'"
    );

$donated =
    (int)$stmt->fetchColumn();


$stmt =
    $pdo->query(
        "SELECT COUNT(*)
         FROM items
         WHERE status IN ('open','matched')"
    );

$open =
    (int)$stmt->fetchColumn();


$recoveryRate =
    $totalLost > 0
        ? round(
            ($reunited / $totalLost) * 100,
            1
        )
        : 0;


/* Hotspots */

$stmt =
    $pdo->query(
        "SELECT
            location_name AS location,
            COUNT(*) AS count
         FROM items
         GROUP BY location_name
         ORDER BY count DESC
         LIMIT 10"
    );


$hotspots =
    $stmt->fetchAll();


/* Categories */

$stmt =
    $pdo->query(
        "SELECT
            category,
            COUNT(*) AS count
         FROM items
         GROUP BY category
         ORDER BY count DESC"
    );


$categories =
    $stmt->fetchAll();


json_response([

    'total_lost' =>
        $totalLost,

    'total_found' =>
        $totalFound,

    'reunited' =>
        $reunited,

    'donated' =>
        $donated,

    'open_items' =>
        $open,

    'recovery_rate' =>
        $recoveryRate,

    'hotspots' =>
        $hotspots,

    'categories' =>
        $categories,

    'chart_url' =>
        null
]);
