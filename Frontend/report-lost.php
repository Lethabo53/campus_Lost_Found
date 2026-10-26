<?php
require_once __DIR__ . '/db.php';
date_default_timezone_set('Africa/Johannesburg');
require_once __DIR__ . '/matches.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$user = require_login($pdo);

$title        = clean($_POST['title'] ?? '');
$category     = clean($_POST['category'] ?? '');
$color        = clean($_POST['color'] ?? '');
$description  = clean($_POST['description'] ?? '');
$brandModel   = clean($_POST['brand_model'] ?? '');
$eventDate    = clean($_POST['event_date'] ?? '');
$locationName = clean($_POST['location_name'] ?? '');
$reporterName  = clean($_POST['reporter_name'] ?? '');
$reporterEmail = clean($_POST['reporter_email'] ?? '');
$urgent       = ($_POST['urgent'] ?? '0') === '1' ? 1 : 0;
$latitude     = ($_POST['latitude'] ?? '') !== '' && $_POST['latitude'] !== 'null' ? (float) $_POST['latitude'] : null;
$longitude    = ($_POST['longitude'] ?? '') !== '' && $_POST['longitude'] !== 'null' ? (float) $_POST['longitude'] : null;

// Always use the signed-in account's identity (a person cannot report as someone else)
$reporterName  = $user['full_name'];
$reporterEmail = strtolower($user['email']);

if ($title === '' || $category === '' || $description === '' || $eventDate === ''
    || $reporterName === '' || $reporterEmail === '') {
    http_response_code(400);
    echo json_encode(['error' => 'All required fields must be filled']);
    exit;
}

// Any campus location is allowed. The five hotspots below are tracked on the dashboard,
// so we tidy up the spelling/capitalisation when someone types one of them.
$hotspots = ['PC Lab 1', 'PC Lab 2', 'PC Lab 3', 'Cafeteria', 'Library'];
foreach ($hotspots as $spot) {
    if (strcasecmp($locationName, $spot) === 0) {
        $locationName = $spot;
        break;
    }
}
if (mb_strlen($locationName) > 255) {
    http_response_code(422);
    echo json_encode(['error' => 'Location is too long (maximum 255 characters).']);
    exit;
}

$parsedDate = DateTime::createFromFormat('Y-m-d', $eventDate);
if (!$parsedDate || $parsedDate->format('Y-m-d') !== $eventDate) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter a valid date.']);
    exit;
}
if ($eventDate > date('Y-m-d')) {
    http_response_code(422);
    echo json_encode(['error' => 'You cannot choose a future date. Please select today or an earlier date.']);
    exit;
}

try {
    $ticketCode = make_ticket();

    $stmt = $pdo->prepare("
        INSERT INTO items (
            ticket_code, report_type, title, category, color, description,
            brand_model, event_date, location_name,
            latitude, longitude, reporter_name, reporter_email,
            urgent, status
        ) VALUES (
            :ticket_code, 'lost', :title, :category, :color, :description,
            :brand_model, :event_date, :location_name,
            :latitude, :longitude, :reporter_name, :reporter_email,
            :urgent, 'open'
        )
    ");

    $stmt->execute([
        ':ticket_code'    => $ticketCode,
        ':title'          => $title,
        ':category'       => $category,
        ':color'          => $color !== '' ? $color : null,
        ':description'    => $description,
        ':brand_model'    => $brandModel !== '' ? $brandModel : null,
        ':event_date'     => $eventDate,
        ':location_name'  => $locationName,
        ':latitude'       => $latitude,
        ':longitude'      => $longitude,
        ':reporter_name'  => $reporterName,
        ':reporter_email' => $reporterEmail,
        ':urgent'         => $urgent,
    ]);

    $itemId = (int) $pdo->lastInsertId();

    // --- Photo uploads (multiple files, field name "photos[]") ---
    ensure_upload_directories();
    $uploadDir = __DIR__ . '/uploads/lost/';

    if (isset($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
        $photoCount = count($_FILES['photos']['name']);
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        for ($i = 0; $i < $photoCount && $i < 5; $i++) {
            if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmpName  = $_FILES['photos']['tmp_name'][$i];
            $fileName = $_FILES['photos']['name'][$i];
            $fileType = $_FILES['photos']['type'][$i];

            if (!in_array($fileType, $allowedTypes, true)) {
                continue;
            }

            $extension    = pathinfo($fileName, PATHINFO_EXTENSION);
            $newFileName  = 'item_' . $itemId . '_' . time() . '_' . $i . '.' . $extension;
            $filePath     = $uploadDir . $newFileName;
            $relativePath = 'uploads/lost/' . $newFileName;

            if (move_uploaded_file($tmpName, $filePath)) {
                $features = image_features($filePath);

                $photoStmt = $pdo->prepare("
                    INSERT INTO item_photos (item_id, file_path, image_hash, average_r, average_g, average_b)
                    VALUES (:item_id, :file_path, :hash, :r, :g, :b)
                ");
                $photoStmt->execute([
                    ':item_id'   => $itemId,
                    ':file_path' => $relativePath,
                    ':hash'      => $features['hash'] ?? null,
                    ':r'         => $features['r'] ?? null,
                    ':g'         => $features['g'] ?? null,
                    ':b'         => $features['b'] ?? null,
                ]);
            }
        }
    }

    // Make sure the reporter exists as a user (no points for reporting lost items).
    find_or_create_user($pdo, $reporterName, $reporterEmail);

    // The report is saved as 'pending'. Matching only starts once the admin approves it.
    echo json_encode([
        'success'     => true,
        'item_id'     => $itemId,
        'ticket_code' => $ticketCode,
        'approval'    => 'pending',
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}