<?php
// Admin reports. One endpoint, several reports, JSON for the screen or CSV for Excel.
//   admin_reports.php?report=categories&from=2026-10-01&to=2026-10-31&format=csv
require_once __DIR__ . '/db.php';

require_admin($pdo);

$report = $_GET['report'] ?? 'categories';
$format = $_GET['format'] ?? 'json';
$from   = $_GET['from'] ?? '';
$to     = $_GET['to'] ?? '';

$validDate = fn(string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
if ($from !== '' && !$validDate($from)) $from = '';
if ($to !== '' && !$validDate($to)) $to = '';

// Optional date range on a column, e.g. range('i.created_at') => " AND DATE(i.created_at) >= ? ..."
$params = [];
function range_sql(string $col, string $from, string $to, array &$params): string {
    $sql = '';
    if ($from !== '') { $sql .= " AND DATE($col) >= ?"; $params[] = $from; }
    if ($to !== '')   { $sql .= " AND DATE($col) <= ?"; $params[] = $to; }
    return $sql;
}

function run(PDO $pdo, string $sql, array $params): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_NUM);
}

function pct(int $part, int $whole): string {
    return $whole > 0 ? round($part / $whole * 100, 1) . '%' : '0%';
}

$title = '';
$columns = [];
$rows = [];
$totals = null;

switch ($report) {

    /* ---- Items by category: reported / claimed / returned / donated ---- */
    case 'categories':
        $title = 'Items by category — reported, claimed, returned and donated';
        $columns = ['Category', 'Lost reports', 'Found reports', 'Claimed by owner', 'Returned to owner', 'Donated', 'Still unclaimed', 'Claim rate'];
        $r = range_sql('i.created_at', $from, $to, $params);
        $data = run($pdo, "
            SELECT i.category,
                   SUM(i.report_type = 'lost'),
                   SUM(i.report_type = 'found'),
                   SUM(i.report_type = 'found' AND i.status IN ('claimed','reunited')),
                   SUM(i.report_type = 'found' AND i.status = 'reunited'),
                   SUM(i.status = 'donated'),
                   SUM(i.report_type = 'found' AND i.status IN ('open','matched'))
            FROM items i
            WHERE i.approval_status = 'approved' $r
            GROUP BY i.category
            ORDER BY 3 DESC, 2 DESC", $params);
        $sum = array_fill(1, 6, 0);
        foreach ($data as $d) {
            $n = array_map('intval', array_slice($d, 1));
            foreach ($n as $k => $v) $sum[$k + 1] += $v;
            $rows[] = [$d[0], $n[0], $n[1], $n[2], $n[3], $n[4], $n[5], pct($n[2], $n[1])];
        }
        $totals = ['TOTAL', $sum[1], $sum[2], $sum[3], $sum[4], $sum[5], $sum[6], pct($sum[3], $sum[2])];
        break;

    /* ---- Campus hotspots ---- */
    case 'hotspots':
        $title = 'Campus hotspots — reports per location';
        $columns = ['Location', 'Lost reports', 'Found reports', 'Total', 'Returned to owner'];
        $r = range_sql('i.created_at', $from, $to, $params);
        $data = run($pdo, "
            SELECT i.location_name,
                   SUM(i.report_type = 'lost'),
                   SUM(i.report_type = 'found'),
                   COUNT(*),
                   SUM(i.status = 'reunited')
            FROM items i
            WHERE i.approval_status = 'approved' $r
            GROUP BY i.location_name", $params);
        $hotspots = ['PC Lab 1', 'PC Lab 2', 'PC Lab 3', 'Cafeteria', 'Library'];
        $acc = [];
        foreach ($hotspots as $h) $acc[$h] = [0, 0, 0, 0];
        $acc['Other locations'] = [0, 0, 0, 0];
        foreach ($data as $d) {
            $key = 'Other locations';
            foreach ($hotspots as $h) if (strcasecmp($d[0], $h) === 0) { $key = $h; break; }
            for ($k = 0; $k < 4; $k++) $acc[$key][$k] += (int) $d[$k + 1];
        }
        $sum = [0, 0, 0, 0];
        foreach ($acc as $name => $n) {
            $rows[] = [$name, $n[0], $n[1], $n[2], $n[3]];
            for ($k = 0; $k < 4; $k++) $sum[$k] += $n[$k];
        }
        usort($rows, fn($a, $b) => ($a[0] === 'Other locations') <=> ($b[0] === 'Other locations') ?: $b[3] <=> $a[3]);
        $totals = ['TOTAL', $sum[0], $sum[1], $sum[2], $sum[3]];
        break;

    /* ---- Monthly trend ---- */
    case 'trend':
        $title = 'Monthly trend — by month the item was reported';
        $columns = ['Month', 'Reports submitted', 'Approved', 'Rejected', 'Claimed by owner', 'Returned to owner', 'Donated'];
        $r = range_sql('i.created_at', $from, $to, $params);
        $data = run($pdo, "
            SELECT DATE_FORMAT(i.created_at, '%Y-%m'),
                   COUNT(*),
                   SUM(i.approval_status = 'approved'),
                   SUM(i.approval_status = 'rejected'),
                   SUM(i.status IN ('claimed','reunited')),
                   SUM(i.status = 'reunited'),
                   SUM(i.status = 'donated')
            FROM items i
            WHERE 1 = 1 $r
            GROUP BY 1
            ORDER BY 1 DESC", $params);
        $sum = array_fill(1, 6, 0);
        foreach ($data as $d) {
            $n = array_map('intval', array_slice($d, 1));
            foreach ($n as $k => $v) $sum[$k + 1] += $v;
            $rows[] = array_merge([$d[0]], $n);
        }
        $totals = array_merge(['TOTAL'], array_values($sum));
        break;

    /* ---- Reunited & donated items ---- */
    case 'outcomes':
        $title = 'Outcomes — items returned to owners and items donated';
        $columns = ['Ticket', 'Type', 'Item', 'Category', 'Location', 'Reported', 'Outcome', 'Outcome date'];
        $r = range_sql('i.created_at', $from, $to, $params);
        $rows = run($pdo, "
            SELECT i.ticket_code, i.report_type, i.title, i.category, i.location_name,
                   DATE(i.created_at), i.status, DATE(i.updated_at)
            FROM items i
            WHERE i.approval_status = 'approved' AND i.status IN ('reunited','donated') $r
            ORDER BY i.updated_at DESC", $params);
        foreach ($rows as &$row) $row[6] = $row[6] === 'reunited' ? 'Returned to owner' : 'Donated';
        unset($row);
        break;

    /* ---- Rewards ---- */
    case 'rewards':
        $title = 'Rewards — points, snack credits and redemptions per student';
        $columns = ['Student', 'Email', 'Points', 'Credits balance', 'Credits collected', 'Credits waiting to be collected'];
        $r = range_sql('r.created_at', $from, $to, $params);
        $data = run($pdo, "
            SELECT u.full_name, u.email, u.points, u.snack_credits,
                   COALESCE(SUM(CASE WHEN r.status = 'fulfilled' THEN r.credits END), 0),
                   COALESCE(SUM(CASE WHEN r.status = 'pending' THEN r.credits END), 0)
            FROM users u
            LEFT JOIN redemptions r ON r.user_id = u.id $r
            WHERE u.role = 'student'
            GROUP BY u.id
            HAVING u.points > 0 OR u.snack_credits > 0 OR SUM(r.id IS NOT NULL) > 0
            ORDER BY u.points DESC", $params);
        $sum = array_fill(2, 4, 0);
        foreach ($data as $d) {
            $n = array_map('intval', array_slice($d, 2));
            foreach ($n as $k => $v) $sum[$k + 2] += $v;
            $rows[] = array_merge([$d[0], $d[1]], $n);
        }
        $totals = array_merge(['TOTAL', ''], array_values($sum));
        break;

    /* ---- Summary ---- */
    case 'summary':
        $title = 'Summary — overall performance';
        $columns = ['Measure', 'Value'];
        $r = range_sql('i.created_at', $from, $to, $params);
        $one = function (string $sql) use ($pdo, $params) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn();
        };
        $submitted = (int) $one("SELECT COUNT(*) FROM items i WHERE 1 = 1 $r");
        $pending   = (int) $one("SELECT COUNT(*) FROM items i WHERE i.approval_status = 'pending' $r");
        $rejected  = (int) $one("SELECT COUNT(*) FROM items i WHERE i.approval_status = 'rejected' $r");
        $lost      = (int) $one("SELECT COUNT(*) FROM items i WHERE i.approval_status = 'approved' AND i.report_type = 'lost' $r");
        $found     = (int) $one("SELECT COUNT(*) FROM items i WHERE i.approval_status = 'approved' AND i.report_type = 'found' $r");
        $claimed   = (int) $one("SELECT COUNT(*) FROM items i WHERE i.approval_status = 'approved' AND i.report_type = 'found' AND i.status IN ('claimed','reunited') $r");
        $returned  = (int) $one("SELECT COUNT(*) FROM items i WHERE i.approval_status = 'approved' AND i.status = 'reunited' $r");
        $donated   = (int) $one("SELECT COUNT(*) FROM items i WHERE i.approval_status = 'approved' AND i.status = 'donated' $r");
        $unclaimed = (int) $one("SELECT COUNT(*) FROM items i WHERE i.approval_status = 'approved' AND i.report_type = 'found' AND i.status IN ('open','matched') $r");
        $waiting   = (int) $pdo->query("SELECT COUNT(*) FROM claims c JOIN items i ON i.id = c.item_id WHERE c.status = 'pending' AND i.status = 'claimed'")->fetchColumn();
        $avgApprove = $one("SELECT AVG(TIMESTAMPDIFF(MINUTE, i.created_at, i.reviewed_at)) FROM items i WHERE i.reviewed_at IS NOT NULL $r");
        $avgReturn  = $one("SELECT AVG(TIMESTAMPDIFF(HOUR, i.created_at, i.updated_at)) FROM items i WHERE i.status = 'reunited' $r");
        $rows = [
            ['Reports submitted (all)', $submitted],
            ['Awaiting admin approval', $pending],
            ['Rejected by admin', $rejected],
            ['Approved lost reports', $lost],
            ['Approved found reports', $found],
            ['Found items claimed by their owner', $claimed],
            ['Items returned to owners', $returned],
            ['Items donated to charity', $donated],
            ['Found items still unclaimed', $unclaimed],
            ['Verified claims waiting for hand-over', $waiting],
            ['Claim rate (claimed ÷ found)', pct($claimed, $found)],
            ['Average time to admin approval', $avgApprove === null ? '—' : round($avgApprove / 60, 1) . ' hours'],
            ['Average time from report to return', $avgReturn === null ? '—' : round($avgReturn / 24, 1) . ' days'],
        ];
        break;

    default:
        json_response(['error' => 'Unknown report.'], 422);
}

$rangeText = ($from !== '' || $to !== '')
    ? 'Period: ' . ($from !== '' ? $from : 'start') . ' to ' . ($to !== '' ? $to : 'today')
    : 'Period: all time';

$out = ['report' => $report, 'title' => $title, 'period' => $rangeText, 'generated' => date('Y-m-d H:i'),
        'columns' => $columns, 'rows' => $rows, 'totals' => $totals];

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . $report . '_' . date('Ymd') . '.csv"');
    $fh = fopen('php://output', 'w');
    fwrite($fh, "\xEF\xBB\xBF");               // so Excel shows special characters correctly
    fputcsv($fh, [$title]);
    fputcsv($fh, [$rangeText, 'Generated ' . $out['generated']]);
    fputcsv($fh, []);
    fputcsv($fh, $columns);
    foreach ($rows as $row) fputcsv($fh, $row);
    if ($totals) fputcsv($fh, $totals);
    fclose($fh);
    exit;
}

header('Content-Type: application/json');
echo json_encode($out, JSON_UNESCAPED_UNICODE);