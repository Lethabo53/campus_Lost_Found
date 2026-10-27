<?php

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$data  = request_json();
$email = strtolower(clean($data['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['error' => 'Please enter a valid email address.'], 422);
}

$stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();


$genericMessage = "If an account exists for that email, a reset link has been generated.";

if (!$user || $user['password_hash'] === '') {
    
    json_response(['success' => true, 'message' => $genericMessage]);
}

$token     = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);
$expires   = date('Y-m-d H:i:s', strtotime('+30 minutes'));

$pdo->prepare("UPDATE users SET reset_token_hash = ?, reset_token_expires = ? WHERE id = ?")
    ->execute([$tokenHash, $expires, $user['id']]);

$resetLink = 'reset-password.html?email=' . urlencode($email) . '&token=' . $token;

json_response([
    'success'    => true,
    'message'    => $genericMessage,
    'reset_link' => $resetLink,
    'dev_note'   => 'No email server is configured, so this link is shown here directly for local testing. It expires in 30 minutes.',
]);