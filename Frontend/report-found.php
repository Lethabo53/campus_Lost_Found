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

$title            = clean($_POST['title'] ?? '');
$category         = clean($_POST['category'] ?? '');
$color            = clean($_POST['color'] ?? '');
$description      = clean($_POST['description'] ?? '');
$brandModel       = clean($_POST['brand_model'] ?? '');
$conditionNotes   = clean($_POST['condition_notes'] ?? '');
$eventDate        = clean($_POST['event_date'] ?? '');
$locationName     = clean($_POST['location_name'] ?? '');
$securityQuestion1 = clean($_POST['security_question_1'] ?? '');
$securityAnswer1   = clean($_POST['security_answer_1'] ?? '');
$securityQuestion2 = clean($_POST['security_question_2'] ?? '');
$securityAnswer2   = clean($_POST['security_answer_2'] ?? '');
$reporterName     = clean($_POST['reporter_name'] ?? '');
$reporterEmail    = clean($_POST['reporter_email'] ?? '');
$latitude         = ($_POST['latitude'] ?? '') !== '' ? (float) $_POST['latitude'] : null;
$longitude        = ($_POST['longitude'] ?? '') !== '' ? (float) $_POST['longitude'] : null;

// Always use the signed-in account's identity (a person cannot report as someone else)
$reporterName  = $user['full_name'];
$reporterEmail = strtolower($user['email']);

if ($title === '' || $category === '' || $description === '' || $eventDate === ''
    || $reporterName === '' || $reporterEmail === ''
    || $securityQuestion1 === '' || $securityAnswer1 === ''
    || $securityQuestion2 === '' || $securityAnswer2 === '') {
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

// A person cannot report their OWN lost item as "found" (this would farm reward points).
// If they got their item back, they open their lost report and press "I found my item" instead.
$ownLost = $pdo->prepare(
    "SELECT ticket_code, title, description
     FROM items
     WHERE report_type = 'lost'
       AND LOWER(reporter_email) = ?
       AND category = ?
       AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)"
);
$ownLost->execute([$reporterEmail, $category]);
foreach ($ownLost->fetchAll() as $lost) {
    if (text_similarity($title, $lost['title']) >= 45
        || text_similarity($description, $lost['description']) >= 45) {
        http_response_code(422);
        echo json_encode(['error' => 'This looks like the item you reported lost (ticket ' . $lost['ticket_code']
            . '). If you got it back, open your lost report and press "I found my item" instead.']);
        exit;
    }
}

try {
    $ticketCode = make_ticket();

    $stmt = $pdo->prepare("
        INSERT INTO items (
            ticket_code, report_type, title, category, color, description,
            brand_model, condition_notes, event_date, location_name,
            latitude, longitude, reporter_name, reporter_email,
            security_question_1, security_answer_1,
            security_question_2, security_answer_2,
            status
        ) VALUES (
            :ticket_code, 'found', :title, :category, :color, :description,
            :brand_model, :condition_notes, :event_date, :location_name,
            :latitude, :longitude, :reporter_name, :reporter_email,
            :security_question_1, :security_answer_1,
            :security_question_2, :security_answer_2,
            'open'
        )
    ");

    $stmt->execute([
        ':ticket_code'          => $ticketCode,
        ':title'                => $title,
        ':category'             => $category,
        ':color'                => $color !== '' ? $color : null,
        ':description'          => $description,
        ':brand_model'          => $brandModel !== '' ? $brandModel : null,
        ':condition_notes'      => $conditionNotes !== '' ? $conditionNotes : null,
        ':event_date'           => $eventDate,
        ':location_name'        => $locationName,
        ':latitude'             => $latitude,
        ':longitude'            => $longitude,
        ':reporter_name'        => $reporterName,
        ':reporter_email'       => $reporterEmail,
        ':security_question_1'  => $securityQuestion1,
        ':security_answer_1'    => $securityAnswer1,
        ':security_question_2'  => $securityQuestion2,
        ':security_answer_2'    => $securityAnswer2,
    ]);

    $itemId = (int) $pdo->lastInsertId();

    // --- Photo upload (single file input named "photo") ---
    ensure_upload_directories();
    $uploadDir = __DIR__ . '/uploads/found/';

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $tmpName  = $_FILES['photo']['tmp_name'];
        $fileName = $_FILES['photo']['name'];
        $fileType = $_FILES['photo']['type'];

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (in_array($fileType, $allowedTypes, true)) {
            $extension   = pathinfo($fileName, PATHINFO_EXTENSION);
            $newFileName = 'item_' . $itemId . '_' . time() . '.' . $extension;
            $filePath    = $uploadDir . $newFileName;
            $relativePath = 'uploads/found/' . $newFileName;

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

    // Make sure the reporter exists as a user.
    // Points and snack credits are awarded when the admin APPROVES this report.
    find_or_create_user($pdo, $reporterName, $reporterEmail);

    // The report is saved as 'pending'. Matching only starts once the admin approves it.

    $donateAfter = date('Y-m-d', strtotime('+30 days'));

    echo json_encode([
        'success'      => true,
        'item_id'      => $itemId,
        'ticket_code'  => $ticketCode,
        'donate_after' => $donateAfter,
        'window_hours' => 720,
        'approval'     => 'pending',
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}