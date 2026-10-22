<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$db   = 'campus_lost_found';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn =
    "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE =>
        PDO::ERRMODE_EXCEPTION,

    PDO::ATTR_DEFAULT_FETCH_MODE =>
        PDO::FETCH_ASSOC,

    PDO::ATTR_EMULATE_PREPARES =>
        false,
];

try {

    $pdo = new PDO(
        $dsn,
        $user,
        $pass,
        $options
    );

} catch (PDOException $e) {

    http_response_code(500);

    header(
        'Content-Type: application/json'
    );

    echo json_encode([
        'error' =>
            'Database connection failed.'
    ]);

    exit;
}


function json_response(
    array $data,
    int $status = 200
): never {

    http_response_code($status);

    header(
        'Content-Type: application/json'
    );

    echo json_encode(
        $data,
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


function request_json(): array {

    $raw =
        file_get_contents('php://input');

    if (!$raw) {
        return [];
    }

    $data =
        json_decode(
            $raw,
            true
        );

    return is_array($data)
        ? $data
        : [];
}


function clean(
    mixed $value
): string {

    return trim(
        (string)($value ?? '')
    );
}


function make_ticket(): string {

    return 'LF-' .
        strtoupper(
            substr(
                bin2hex(
                    random_bytes(5)
                ),
                0,
                8
            )
        );
}


function ensure_upload_directories(): void {

    foreach ([
        __DIR__ . '/uploads',
        __DIR__ . '/uploads/lost',
        __DIR__ . '/uploads/found'
    ] as $dir) {

        if (!is_dir($dir)) {

            mkdir(
                $dir,
                0755,
                true
            );
        }
    }
}


function current_user(
    PDO $pdo
): ?array {

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt =
        $pdo->prepare(
            "SELECT id, full_name, email, points
             FROM users
             WHERE id = ?
             LIMIT 1"
        );

    $stmt->execute([
        $_SESSION['user_id']
    ]);

    $user =
        $stmt->fetch();

    return $user
        ? $user
        : null;
}


function find_or_create_user(
    PDO $pdo,
    string $name,
    string $email
): int {

    $stmt =
        $pdo->prepare(
            "SELECT id
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

    $stmt->execute([
        $email
    ]);

    $user =
        $stmt->fetch();

    if ($user) {
        return (int)$user['id'];
    }

    $stmt =
        $pdo->prepare(
            "INSERT INTO users
             (full_name, email, password_hash)
             VALUES (?, ?, '')"
        );

    $stmt->execute([
        $name,
        $email
    ]);

    return (int)$pdo->lastInsertId();
}

