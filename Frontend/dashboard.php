<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

require_login($pdo);

function count_items(PDO $pdo, string $column, string $value): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE $column = ?");
    $stmt->execute([$value]);
    return (int) $stmt->fetchColumn();
}

$totalLost  = count_items($pdo, 'report_type', 'lost');
$totalFound = count_items($pdo, 'report_type', 'found');
$reunited   = count_items($pdo, 'status', 'reunited');
$donated    = count_items($pdo, 'status', 'donated');

$open = (int) $pdo->query(
    "SELECT COUNT(*) FROM items WHERE status IN ('open','matched')"
)->fetchColumn();

$recoveryRate = $totalLost > 0 ? round(($reunited / $totalLost) * 100, 1) : 0;

/* Hotspots */
$hotspots = $pdo->query(
    "SELECT location_name AS location, COUNT(*) AS count
     FROM items GROUP BY location_name ORDER BY count DESC LIMIT 10"
)->fetchAll();

/* Categories */
$categories = $pdo->query(
    "SELECT category, COUNT(*) AS count
     FROM items GROUP BY category ORDER BY count DESC"
)->fetchAll();

json_response([
    'total_lost'    => $totalLost,
    'total_found'   => $totalFound,
    'reunited'      => $reunited,
    'donated'       => $donated,
    'open_items'    => $open,
    'recovery_rate' => $recoveryRate,
    'hotspots'      => $hotspots,
    'categories'    => $categories,
    'chart_url'     => null,
]);
