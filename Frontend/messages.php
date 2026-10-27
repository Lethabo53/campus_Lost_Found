<?php
require_once __DIR__ . '/db.php';

$me     = require_login($pdo);
$admin  = ($me['role'] ?? '') === 'admin';
$action = $_GET['action'] ?? '';

$ITEM_SQL = "SELECT i.id, i.title, i.ticket_code, i.report_type, i.reporter_email, i.approval_status
             FROM items i WHERE i.id = ?";

function fetch_item(PDO $pdo, int $id): ?array {
    if ($id <= 0) return null;
    $s = $pdo->prepare("SELECT id, title, ticket_code, report_type, reporter_email, approval_status FROM items WHERE id = ?");
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d    = request_json();
    $body = clean($d['body'] ?? '');
    $item = (int) ($d['item_id'] ?? 0);

    if ($body === '')                 json_response(['error' => 'Please type a message.'], 422);
    if (mb_strlen($body) > 1500)      json_response(['error' => 'Message is too long (max 1500 characters).'], 422);

    if ($admin) {
        $student = (int) ($d['student_id'] ?? 0);
        $chk = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'student'");
        $chk->execute([$student]);
        if (!$chk->fetch()) json_response(['error' => 'Student not found.'], 404);
        $sender = 'admin';
    } else {
        $student = (int) $me['id'];
        $sender  = 'student';
        $row = fetch_item($pdo, $item);
        if ($item !== 0) {
            
            $own = $row && strtolower($row['reporter_email']) === strtolower($me['email']);
            if (!$row || (!$own && $row['approval_status'] !== 'approved')) {
                json_response(['error' => 'That item was not found.'], 404);
            }
        }
        
        $c = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE student_id = ? AND sender = 'student' AND created_at > (NOW() - INTERVAL 1 HOUR)");
        $c->execute([$student]);
        if ((int) $c->fetchColumn() >= 20) json_response(['error' => 'You are sending messages too quickly. Please wait a little.'], 429);
    }

    $pdo->prepare("INSERT INTO messages (student_id, item_id, sender, body, read_by_student, read_by_admin)
                   VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$student, $item ?: null, $sender, $body, $sender === 'student' ? 1 : 0, $sender === 'admin' ? 1 : 0]);

    json_response(['success' => true]);
}

if ($action === 'unread') {
    if ($admin) {
        $n = (int) $pdo->query("SELECT COUNT(*) FROM messages WHERE sender = 'student' AND read_by_admin = 0")->fetchColumn();
    } else {
        $s = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE student_id = ? AND sender = 'admin' AND read_by_student = 0");
        $s->execute([$me['id']]);
        $n = (int) $s->fetchColumn();
    }
    json_response(['unread' => $n]);
}

if ($action === 'threads' || $action === 'inbox') {
    $where  = $admin ? '' : 'WHERE m.student_id = ' . (int) $me['id'];
    $unread = $admin ? "m.sender = 'student' AND m.read_by_admin = 0" : "m.sender = 'admin' AND m.read_by_student = 0";
    $rows = $pdo->query(
        "SELECT m.student_id, COALESCE(m.item_id, 0) AS item_id,
                u.full_name AS student_name, u.email AS student_email,
                i.title AS item_title, i.ticket_code,
                MAX(m.id) AS last_id,
                SUM($unread) AS unread
         FROM messages m
         JOIN users u ON u.id = m.student_id
         LEFT JOIN items i ON i.id = m.item_id
         $where
         GROUP BY m.student_id, COALESCE(m.item_id, 0), u.full_name, u.email, i.title, i.ticket_code
         ORDER BY last_id DESC
         LIMIT 200"
    )->fetchAll();

    foreach ($rows as &$r) {
        $l = $pdo->prepare("SELECT body, sender, created_at FROM messages WHERE id = ?");
        $l->execute([$r['last_id']]);
        $last = $l->fetch();
        $r['last_body']   = mb_strimwidth($last['body'], 0, 90, '…');
        $r['last_sender'] = $last['sender'];
        $r['last_at']     = $last['created_at'];
        $r['unread']      = (int) $r['unread'];
        unset($r['last_id']);
    }
    json_response(['threads' => $rows]);
}

if ($action === 'thread') {
    $item    = (int) ($_GET['item_id'] ?? 0);
    $student = $admin ? (int) ($_GET['student_id'] ?? 0) : (int) $me['id'];

    $s = $pdo->prepare("SELECT id, sender, body, created_at FROM messages
                        WHERE student_id = ? AND COALESCE(item_id, 0) = ? ORDER BY id ASC");
    $s->execute([$student, $item]);
    $messages = $s->fetchAll();

    if ($admin) {
        $pdo->prepare("UPDATE messages SET read_by_admin = 1 WHERE student_id = ? AND COALESCE(item_id,0) = ? AND sender = 'student'")
            ->execute([$student, $item]);
    } else {
        $pdo->prepare("UPDATE messages SET read_by_student = 1 WHERE student_id = ? AND COALESCE(item_id,0) = ? AND sender = 'admin'")
            ->execute([$student, $item]);
    }

    $itemRow = fetch_item($pdo, $item);
    $stu = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stu->execute([$student]);

    json_response([
        'messages' => $messages,
        'item'     => $itemRow ? ['id' => (int) $itemRow['id'], 'title' => $itemRow['title'], 'ticket_code' => $itemRow['ticket_code']] : null,
        'student'  => $stu->fetch() ?: null,
    ]);
}

json_response(['error' => 'Unknown action.'], 400);