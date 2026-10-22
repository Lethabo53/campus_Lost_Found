<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

require_login($pdo);

$type     = trim($_GET['type'] ?? '');
$category = trim($_GET['category'] ?? '');
$q        = trim($_GET['q'] ?? '');
$sort     = $_GET['sort'] ?? 'newest';

$where  = [];
$params = [];

if ($type === 'lost' || $type === 'found') {
    $where[] = 'i.report_type = ?';
    $params[] = $type;
}

if ($category !== '') {
    $where[] = 'i.category = ?';
    $params[] = $category;
}

if ($q !== '') {
    $where[] = '(i.title LIKE ? OR i.description LIKE ? OR i.brand_model LIKE ? OR i.color LIKE ? OR i.location_name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

switch ($sort) {
    case 'oldest':
        $orderSql = 'i.created_at ASC';
        break;
    case 'urgent':
        $orderSql = 'i.urgent DESC, i.created_at DESC';
        break;
    case 'location':
        $orderSql = 'i.location_name ASC, i.created_at DESC';
        break;
    case 'newest':
    default:
        $orderSql = 'i.created_at DESC';
        break;
}

try {
    $sql = "
        SELECT
            i.*,
            p.file_path AS photo,
            DATE_ADD(i.event_date, INTERVAL 7 DAY) AS donate_after
        FROM items i
        LEFT JOIN (
            SELECT item_id, MIN(id) AS photo_id
            FROM item_photos
            GROUP BY item_id
        ) fp ON fp.item_id = i.id
        LEFT JOIN item_photos p ON p.id = fp.photo_id
        $whereSql
        ORDER BY $orderSql
        LIMIT 500
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$row) {
        unset($row['security_answer_1']);
        unset($row['security_answer_2']);

        if ($row['report_type'] === 'found') {
            unset($row['reporter_name']);
            unset($row['reporter_email']);
        }
    }
    unset($row);

    $counters = [
        'lost'     => (int) $pdo->query("SELECT COUNT(*) FROM items WHERE report_type = 'lost'")->fetchColumn(),
        'found'    => (int) $pdo->query("SELECT COUNT(*) FROM items WHERE report_type = 'found'")->fetchColumn(),
        'reunited' => (int) $pdo->query("SELECT COUNT(*) FROM items WHERE status = 'reunited'")->fetchColumn(),
    ];

    echo json_encode(['items' => $items, 'counters' => $counters]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}