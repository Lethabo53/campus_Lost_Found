<?php
// Admin review desk: approve / reject new reports, and handle verified claims.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/matches.php';

header('Content-Type: application/json');

$admin = require_admin($pdo);

/* ---------------------------------------------------------------
   GET  ?view=pending  -> reports waiting for approval
   GET  ?view=claims   -> verified claims waiting for the admin to arrange the hand-over
---------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $view = $_GET['view'] ?? 'pending';

    if ($view === 'claims') {
        $stmt = $pdo->query("
            SELECT
                c.id AS claim_id,
                c.claimant_name,
                c.claimant_email,
                c.created_at AS claimed_at,
                i.id AS item_id,
                i.ticket_code,
                i.title,
                i.category,
                i.location_name,
                i.reporter_name AS finder_name,
                i.reporter_email AS finder_email,
                (SELECT file_path FROM item_photos WHERE item_id = i.id ORDER BY id LIMIT 1) AS photo
            FROM claims c
            JOIN items i ON i.id = c.item_id
            WHERE c.status = 'pending' AND i.status = 'claimed'
            ORDER BY c.created_at ASC
        ");
        json_response(['claims' => $stmt->fetchAll()]);
    }

    $stmt = $pdo->query("
        SELECT
            i.*,
            (SELECT file_path FROM item_photos WHERE item_id = i.id ORDER BY id LIMIT 1) AS photo
        FROM items i
        WHERE i.approval_status = 'pending'
        ORDER BY i.created_at ASC
    ");
    json_response(['items' => $stmt->fetchAll()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$data   = request_json();
$action = clean($data['action'] ?? '');

/* ---------------------------------------------------------------
   POST approve  { item_id }
---------------------------------------------------------------- */
if ($action === 'approve') {

    $itemId = (int) ($data['item_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ? LIMIT 1");
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();

    if (!$item) {
        json_response(['error' => 'Item not found.'], 404);
    }

    $upd = $pdo->prepare("
        UPDATE items
        SET approval_status = 'approved', reviewed_at = NOW(), reviewed_by = ?, rejection_reason = NULL
        WHERE id = ? AND approval_status = 'pending'
    ");
    $upd->execute([$admin['id'], $itemId]);

    if ($upd->rowCount() === 0) {
        json_response(['error' => 'This report has already been reviewed.'], 422);
    }

    if ($item['report_type'] === 'found') {
        // Good Samaritan reward is given once the admin approves the found report.
        $userId = find_or_create_user($pdo, $item['reporter_name'], $item['reporter_email']);
        $pdo->prepare("UPDATE users SET points = points + 10, snack_credits = snack_credits + 5 WHERE id = ?")
            ->execute([$userId]);

        // Check the new found item against every open, approved lost report.
        $lostIds = $pdo->query("SELECT id FROM items WHERE report_type = 'lost' AND approval_status = 'approved' AND status IN ('open','matched')")
            ->fetchAll(PDO::FETCH_COLUMN);
        foreach ($lostIds as $lostId) {
            generate_matches($pdo, (int) $lostId);
        }
    } else {
        generate_matches($pdo, $itemId);
    }

    json_response(['success' => true, 'approval_status' => 'approved']);
}

/* ---------------------------------------------------------------
   POST reject  { item_id, reason }
---------------------------------------------------------------- */
if ($action === 'reject') {

    $itemId = (int) ($data['item_id'] ?? 0);
    $reason = mb_substr(clean($data['reason'] ?? ''), 0, 255);
    if ($reason === '') {
        $reason = 'This report did not meet the reporting guidelines.';
    }

    $upd = $pdo->prepare("
        UPDATE items
        SET approval_status = 'rejected', rejection_reason = ?, reviewed_at = NOW(), reviewed_by = ?
        WHERE id = ? AND approval_status = 'pending'
    ");
    $upd->execute([$reason, $admin['id'], $itemId]);

    if ($upd->rowCount() === 0) {
        json_response(['error' => 'This report has already been reviewed.'], 422);
    }

    json_response(['success' => true, 'approval_status' => 'rejected']);
}

/* ---------------------------------------------------------------
   POST reject_claim  { claim_id }  -> item goes back to "open"
---------------------------------------------------------------- */
if ($action === 'reject_claim') {

    $claimId = (int) ($data['claim_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT item_id FROM claims WHERE id = ? AND status = 'pending' LIMIT 1");
    $stmt->execute([$claimId]);
    $claim = $stmt->fetch();

    if (!$claim) {
        json_response(['error' => 'Claim not found or already handled.'], 404);
    }

    $pdo->prepare("UPDATE claims SET status = 'rejected' WHERE id = ?")->execute([$claimId]);
    $pdo->prepare("UPDATE items SET status = 'open' WHERE id = ? AND status = 'claimed'")->execute([$claim['item_id']]);

    json_response(['success' => true]);
}

json_response(['error' => 'Invalid request.'], 422);