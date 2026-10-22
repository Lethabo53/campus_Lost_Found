<?php

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

json_response(['user' => current_user($pdo)]);
