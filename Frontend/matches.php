<?php

require_once __DIR__ . '/db.php';


/* =====================================================
   TEXT SIMILARITY
===================================================== */

function text_similarity(
    string $a,
    string $b
): float {

    $a = strtolower(trim($a));
    $b = strtolower(trim($b));

    if ($a === '' || $b === '') {
        return 0;
    }

    if ($a === $b) {
        return 100;
    }

    similar_text(
        $a,
        $b,
        $percent
    );

    $wordsA =
        preg_split(
            '/\W+/',
            $a,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

    $wordsB =
        preg_split(
            '/\W+/',
            $b,
            -1,
            PREG_SPLIT_NO_EMPTY
        );

    $intersection =
        count(
            array_intersect(
                $wordsA,
                $wordsB
            )
        );

    $union =
        count(
            array_unique(
                array_merge(
                    $wordsA,
                    $wordsB
                )
            )
        );

    $jaccard =
        $union > 0
            ? ($intersection / $union) * 100
            : 0;

    return max(
        (float)$percent,
        $jaccard
    );
}


/* =====================================================
   LOCATION
===================================================== */

function location_score(
    array $lost,
    array $found
): float {

    if (
        $lost['latitude'] !== null &&
        $lost['longitude'] !== null &&
        $found['latitude'] !== null &&
        $found['longitude'] !== null
    ) {

        $lat1 =
            deg2rad(
                (float)$lost['latitude']
            );

        $lat2 =
            deg2rad(
                (float)$found['latitude']
            );

        $dLat =
            $lat2 - $lat1;

        $dLon =
            deg2rad(
                (float)$found['longitude']
            ) -
            deg2rad(
                (float)$lost['longitude']
            );

        $a =
            sin($dLat / 2) ** 2 +
            cos($lat1) *
            cos($lat2) *
            sin($dLon / 2) ** 2;

        $distance =
            6371000 *
            2 *
            atan2(
                sqrt($a),
                sqrt(1 - $a)
            );

        if ($distance <= 100) {
            return 100;
        }

        if ($distance <= 250) {
            return 85;
        }

        if ($distance <= 500) {
            return 65;
        }

        if ($distance <= 1000) {
            return 40;
        }

        if ($distance <= 2500) {
            return 20;
        }

        return 0;
    }

    return text_similarity(
        $lost['location_name'],
        $found['location_name']
    );
}


/* =====================================================
   DATE
===================================================== */

function date_score(
    string $lostDate,
    string $foundDate
): float {

    $a =
        strtotime($lostDate);

    $b =
        strtotime($foundDate);

    if (!$a || !$b) {
        return 0;
    }

    $days =
        abs(
            $a - $b
        ) / 86400;

    if ($days <= 1) {
        return 100;
    }

    if ($days <= 3) {
        return 80;
    }

    if ($days <= 7) {
        return 60;
    }

    if ($days <= 14) {
        return 35;
    }

    return 10;
}


/* =====================================================
   PHOTO SIMILARITY
===================================================== */

function image_features(
    string $path
): ?array {

    if (!extension_loaded('gd')) {
        return null;
    }

    if (!file_exists($path)) {
        return null;
    }

    $info =
        @getimagesize($path);

    if (!$info) {
        return null;
    }

    $type =
        $info[2];

    switch ($type) {

        case IMAGETYPE_JPEG:
            $image =
                @imagecreatefromjpeg($path);
            break;

        case IMAGETYPE_PNG:
            $image =
                @imagecreatefrompng($path);
            break;

        case IMAGETYPE_WEBP:

            if (
                function_exists(
                    'imagecreatefromwebp'
                )
            ) {
                $image =
                    @imagecreatefromwebp($path);
            } else {
                return null;
            }

            break;

        default:
            return null;
    }

    if (!$image) {
        return null;
    }

    $width =
        imagesx($image);

    $height =
        imagesy($image);


    /* Average colour */

    $totalR = 0;
    $totalG = 0;
    $totalB = 0;

    $samples = 0;


    for (
        $x = 0;
        $x < $width;
        $x += max(1, (int)($width / 20))
    ) {

        for (
            $y = 0;
            $y < $height;
            $y += max(1, (int)($height / 20))
        ) {

            $rgb =
                imagecolorat(
                    $image,
                    $x,
                    $y
                );

            $totalR +=
                ($rgb >> 16) & 255;

            $totalG +=
                ($rgb >> 8) & 255;

            $totalB +=
                $rgb & 255;

            $samples++;
        }
    }


    $avgR =
        $samples
            ? $totalR / $samples
            : 0;

    $avgG =
        $samples
            ? $totalG / $samples
            : 0;

    $avgB =
        $samples
            ? $totalB / $samples
            : 0;


    /* Simple perceptual hash */

    $small =
        imagecreatetruecolor(
            16,
            16
        );

    imagecopyresampled(
        $small,
        $image,
        0,
        0,
        0,
        0,
        16,
        16,
        $width,
        $height
    );


    $values = [];

    $total = 0;

    for ($x = 0; $x < 16; $x++) {

        for ($y = 0; $y < 16; $y++) {

            $rgb =
                imagecolorat(
                    $small,
                    $x,
                    $y
                );

            $r =
                ($rgb >> 16) & 255;

            $g =
                ($rgb >> 8) & 255;

            $b =
                $rgb & 255;

            $gray =
                (
                    $r * .299 +
                    $g * .587 +
                    $b * .114
                );

            $values[] =
                $gray;

            $total +=
                $gray;
        }
    }

    $average =
        $total /
        count($values);

    $hash = '';

    foreach ($values as $value) {

        $hash .=
            $value >= $average
                ? '1'
                : '0';
    }


    imagedestroy($small);

    imagedestroy($image);


    return [
        'hash' => $hash,
        'r' => (int)$avgR,
        'g' => (int)$avgG,
        'b' => (int)$avgB
    ];
}


function hash_similarity(
    ?string $a,
    ?string $b
): float {

    if (!$a || !$b) {
        return 0;
    }

    $length =
        min(
            strlen($a),
            strlen($b)
        );

    if ($length === 0) {
        return 0;
    }

    $same = 0;

    for ($i = 0; $i < $length; $i++) {

        if ($a[$i] === $b[$i]) {
            $same++;
        }
    }

    return
        ($same / $length) * 100;
}


function colour_similarity(
    array $a,
    array $b
): float {

    if (
        !isset($a['average_r']) ||
        !isset($b['average_r'])
    ) {
        return 0;
    }

    $distance =
        sqrt(
            pow(
                $a['average_r'] -
                $b['average_r'],
                2
            ) +
            pow(
                $a['average_g'] -
                $b['average_g'],
                2
            ) +
            pow(
                $a['average_b'] -
                $b['average_b'],
                2
            )
        );

    $max =
        sqrt(
            255 ** 2 +
            255 ** 2 +
            255 ** 2
        );

    return max(
        0,
        100 -
        ($distance / $max * 100)
    );
}


/* =====================================================
   CALCULATE MATCH
===================================================== */

function calculate_match(
    array $lost,
    array $found,
    ?array $lostPhoto,
    ?array $foundPhoto
): array {


    $category =
        strtolower(
            $lost['category']
        ) ===
        strtolower(
            $found['category']
        )
            ? 100
            : 0;


    $colour =
        text_similarity(
            $lost['color'] ?? '',
            $found['color'] ?? ''
        );


    $brand =
        text_similarity(
            $lost['brand_model'] ?? '',
            $found['brand_model'] ?? ''
        );


    $description =
        text_similarity(
            $lost['description'] ?? '',
            $found['description'] ?? ''
        );


    $location =
        location_score(
            $lost,
            $found
        );


    $date =
        date_score(
            $lost['event_date'],
            $found['event_date']
        );


    $photo = 0;


    if (
        $lostPhoto &&
        $foundPhoto
    ) {

        $hash =
            hash_similarity(
                $lostPhoto['image_hash'],
                $foundPhoto['image_hash']
            );

        $colourImage =
            colour_similarity(
                $lostPhoto,
                $foundPhoto
            );

        $photo =
            ($hash * .65) +
            ($colourImage * .35);
    }


    /*
     * Total score
     *
     * Photo       35%
     * Category    20%
     * Colour      10%
     * Brand       15%
     * Description 10%
     * Location     5%
     * Date         5%
     */

    $total =
        ($photo * .35) +
        ($category * .20) +
        ($colour * .10) +
        ($brand * .15) +
        ($description * .10) +
        ($location * .05) +
        ($date * .05);


    return [

        'score' =>
            round(
                $total,
                2
            ),

        'photo_score' =>
            round(
                $photo,
                2
            ),

        'category_score' =>
            round(
                $category,
                2
            ),

        'colour_score' =>
            round(
                $colour,
                2
            ),

        'brand_score' =>
            round(
                $brand,
                2
            ),

        'description_score' =>
            round(
                $description,
                2
            ),

        'location_score' =>
            round(
                $location,
                2
            ),

        'date_score' =>
            round(
                $date,
                2
            )
    ];
}


/* =====================================================
   CREATE MATCHES
===================================================== */

function generate_matches(
    PDO $pdo,
    int $lostId
): void {

    $stmt =
        $pdo->prepare(
            "SELECT *
             FROM items
             WHERE id = ?
             LIMIT 1"
        );

    $stmt->execute([
        $lostId
    ]);

    $lost =
        $stmt->fetch();

    if (!$lost) {
        return;
    }


    $stmt =
        $pdo->prepare(
            "SELECT *
             FROM items
             WHERE report_type = 'found'
             AND status IN ('open','matched')
             ORDER BY created_at DESC
             LIMIT 300"
        );

    $stmt->execute();

    $foundItems =
        $stmt->fetchAll();


    $photoStmt =
        $pdo->prepare(
            "SELECT *
             FROM item_photos
             WHERE item_id = ?
             LIMIT 1"
        );


    foreach ($foundItems as $found) {

        $photoStmt->execute([
            $lostId
        ]);

        $lostPhoto =
            $photoStmt->fetch();


        $photoStmt->execute([
            $found['id']
        ]);

        $foundPhoto =
            $photoStmt->fetch();


        $scores =
            calculate_match(
                $lost,
                $found,
                $lostPhoto ?: null,
                $foundPhoto ?: null
            );


        /*
         * Don't show extremely weak matches.
         */

        if ($scores['score'] < 25) {
            continue;
        }


        $stmt =
            $pdo->prepare(
                "INSERT INTO matches (
                    lost_item_id,
                    found_item_id,
                    score,
                    photo_score,
                    category_score,
                    colour_score,
                    brand_score,
                    description_score,
                    location_score,
                    date_score
                 )
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    score = VALUES(score),
                    photo_score = VALUES(photo_score),
                    category_score = VALUES(category_score),
                    colour_score = VALUES(colour_score),
                    brand_score = VALUES(brand_score),
                    description_score = VALUES(description_score),
                    location_score = VALUES(location_score),
                    date_score = VALUES(date_score)"
            );


        $stmt->execute([

            $lostId,

            $found['id'],

            $scores['score'],

            $scores['photo_score'],

            $scores['category_score'],

            $scores['colour_score'],

            $scores['brand_score'],

            $scores['description_score'],

            $scores['location_score'],

            $scores['date_score']
        ]);
    }


    /*
     * Mark lost item matched if
     * a strong match exists.
     */

    $stmt =
        $pdo->prepare(
            "SELECT COUNT(*)
             FROM matches
             WHERE lost_item_id = ?
             AND score >= 60"
        );

    $stmt->execute([
        $lostId
    ]);

    $strong =
        (int)$stmt->fetchColumn();


    if ($strong > 0) {

        $stmt =
            $pdo->prepare(
                "UPDATE items
                 SET status = 'matched'
                 WHERE id = ?
                 AND status = 'open'"
            );

        $stmt->execute([
            $lostId
        ]);
    }
}
