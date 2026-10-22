<?php

require_once __DIR__ . '/db.php';

require_login($pdo);

$stmt =
    $pdo->query(
        "SELECT
            full_name,
            points
         FROM users
         WHERE points > 0
         ORDER BY points DESC
         LIMIT 20"
    );


$rows =
    $stmt->fetchAll();


$leaderboard =
    array_map(
        function ($user) {

            $points =
                (int)$user['points'];


            if ($points >= 100) {
                $badge = 'gold';
            } elseif ($points >= 50) {
                $badge = 'silver';
            } else {
                $badge = 'bronze';
            }


            return [

                'full_name' =>
                    $user['full_name'],

                'points' =>
                    $points,

                'badge' =>
                    $badge
            ];
        },
        $rows
    );


json_response([
    'leaderboard' =>
        $leaderboard
]);
