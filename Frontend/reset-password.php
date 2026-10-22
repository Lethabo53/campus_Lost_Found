<?php

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$data     = request_json();
$email    = strtolower(clean($data['email'] ?? ''));
$token    = clean($data['token'] ?? '');
$password = (string) ($data['password'] ?? '');

if ($email === '' || $token === '' || $password === '') {
    json_response(['error' => 'Missing email, token, or new password.'], 400);
}

if (strlen($password) < 8) {
    json_response(['error' => 'Password must be at least 8 characters.'], 422);
}

$stmt = $pdo->prepare("
    SELECT id, reset_token_hash, reset_token_expires
    FROM users
    WHERE email = ?
    LIMIT 1
");
$stmt->execute([$email]);
$user = $stmt->fetch();

$invalid = !$user
    || $user['reset_token_hash'] === null
    || $user['reset_token_expires'] === null
    || strtotime($user['reset_token_expires']) < time()
    || !hash_equals($user['reset_token_hash'], hash('sha256', $token));

if ($invalid) {
    json_response(['error' => 'This reset link is invalid or has expired. Please request a new one.'], 422);
}

$newHash = password_hash($password, PASSWORD_DEFAULT);

$pdo->prepare("
    UPDATE users
    SET password_hash = ?, reset_token_hash = NULL, reset_token_expires = NULL
    WHERE id = ?
")->execute([$newHash, $user['id']]);

json_response(['success' => true, 'message' => 'Your password has been reset. You can now sign in.']);