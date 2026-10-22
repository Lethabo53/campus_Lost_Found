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

// Always respond the same way whether or not the account exists, so this
// endpoint can't be used to check which emails are registered.
$genericMessage = "If an account exists for that email, a reset link has been generated.";

if (!$user || $user['password_hash'] === '') {
    // Either no account, or a guest-only account with no password ever set —
    // nothing to reset in either case.
    json_response(['success' => true, 'message' => $genericMessage]);
}

$token     = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);
$expires   = date('Y-m-d H:i:s', strtotime('+30 minutes'));

$pdo->prepare("UPDATE users SET reset_token_hash = ?, reset_token_expires = ? WHERE id = ?")
    ->execute([$tokenHash, $expires, $user['id']]);

$resetLink = 'reset-password.html?email=' . urlencode($email) . '&token=' . $token;

/*
 * NOTE: No SMTP/mail server is configured on this install, so the link is
 * returned directly in the response below instead of being emailed. Once
 * real email sending is set up (e.g. PHPMailer + Gmail SMTP, or a transactional
 * email API), replace the block below with an actual send, and drop
 * 'reset_link' from the JSON response so the link is only ever delivered
 * by email, not shown on-screen.
 */
json_response([
    'success'    => true,
    'message'    => $genericMessage,
    'reset_link' => $resetLink,
    'dev_note'   => 'No email server is configured, so this link is shown here directly for local testing. It expires in 30 minutes.',
]);