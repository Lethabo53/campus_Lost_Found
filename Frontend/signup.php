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

$fullName = clean($data['full_name'] ?? '');
$email    = strtolower(clean($data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');

if ($fullName === '' || $email === '' || $password === '') {
    json_response(['error' => 'Full name, email, and password are all required.'], 400);
}

// Full name must be a first name AND a surname (at least two words, each at least 2 letters).
// Letters, hyphens and apostrophes only, e.g. "Lethabo Mokoena", "Anna-Marie van der Merwe", "Sipho O'Neil".
$fullName = preg_replace('/\s+/u', ' ', trim($fullName));
if (mb_strlen($fullName) > 100 || !preg_match("/^\p{L}[\p{L}'’\-]+(?: \p{L}[\p{L}'’\-]+)+$/u", $fullName)) {
    json_response(['error' => 'Please enter your full name: first name and surname (e.g. Lethabo Mokoena).'], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['error' => 'Please enter a valid email address.'], 422);
}

// Only allow campus student emails: "40" + 7 more digits, then @my.richfield.ac.za
// Example: 402311567@my.richfield.ac.za
if (!preg_match('/^40[0-9]{7}@my\.richfield\.ac\.za$/', $email)) {
    json_response(['error' => 'Please sign up with your student email, e.g. 402311567@my.richfield.ac.za'], 422);
}

if (strlen($password) < 8) {
    json_response(['error' => 'Password must be at least 8 characters.'], 422);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$existing = $stmt->fetch();

if ($existing) {

    if ($existing['password_hash'] !== '') {
        // A real account already owns this email.
        json_response(['error' => 'An account with that email already exists. Try signing in instead.'], 409);
    }

    // This email only exists as a "guest" record created when reporting an item.
    // Claim it: attach the new password and name, keep the accumulated points.
    $pdo->prepare("UPDATE users SET full_name = ?, password_hash = ? WHERE id = ?")
        ->execute([$fullName, $hash, $existing['id']]);

    $userId = (int) $existing['id'];

} else {

    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)");
    $stmt->execute([$fullName, $email, $hash]);
    $userId = (int) $pdo->lastInsertId();
}

$_SESSION['user_id'] = $userId;

$user = current_user($pdo);

json_response(['success' => true, 'user' => $user]);