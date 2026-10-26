<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

require_login($pdo);

function count_items(PDO $pdo, string $column, string $value): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE approval_status = 'approved' AND $column = ?");
    $stmt->execute([$value]);
    return (int) $stmt->fetchColumn();
}

$totalLost  = count_items($pdo, 'report_type', 'lost');
$totalFound = count_items($pdo, 'report_type', 'found');
$reunited   = count_items($pdo, 'status', 'reunited');
$donated    = count_items($pdo, 'status', 'donated');

$open = (int) $pdo->query(
    "SELECT COUNT(*) FROM items WHERE approval_status = 'approved' AND status IN ('open','matched')"
)->fetchColumn();

$recoveryRate = $totalLost > 0 ? round(($reunited / $totalLost) * 100, 1) : 0;

/* Hotspots: the five listed campus hotspots (always shown), plus everything else as "Other locations" */
$hotspotNames = ['PC Lab 1', 'PC Lab 2', 'PC Lab 3', 'Cafeteria', 'Library'];
$counts = array_fill_keys($hotspotNames, 0);
$otherCount = 0;

foreach ($pdo->query("SELECT location_name, COUNT(*) AS c FROM items WHERE approval_status = 'approved' GROUP BY location_name")->fetchAll() as $row) {
    $matched = false;
    foreach ($hotspotNames as $name) {
        if (strcasecmp($row['location_name'], $name) === 0) {
            $counts[$name] += (int) $row['c'];
            $matched = true;
            break;
        }
    }
    if (!$matched) {
        $otherCount += (int) $row['c'];
    }
}

arsort($counts);
$hotspots = [];
foreach ($counts as $name => $c) {
    $hotspots[] = ['location' => $name, 'count' => $c];
}
$hotspots[] = ['location' => 'Other locations', 'count' => $otherCount];

/* Categories */
$categories = $pdo->query(
    "SELECT category, COUNT(*) AS count
     FROM items WHERE approval_status = 'approved' GROUP BY category ORDER BY count DESC"
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