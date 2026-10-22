<?php

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$data = request_json();
if (!$data) {
    $data = $_POST;
}

$email    = strtolower(clean($data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');

if ($email === '' || $password === '') {
    json_response(['error' => 'Email and password are required.'], 400);
}

$stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || $user['password_hash'] === '' || !password_verify($password, $user['password_hash'])) {
    json_response(['error' => 'Incorrect email or password.'], 401);
}

$_SESSION['user_id'] = (int) $user['id'];

json_response(['success' => true, 'user' => current_user($pdo)]);
