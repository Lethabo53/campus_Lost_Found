<?php

require_once __DIR__ . '/db.php';

// 1. Empty the session data and SAVE it, so even if the session file can't be
//    deleted afterwards (common on Windows/XAMPP) it no longer contains the login.
$_SESSION = [];
session_write_close();

// 2. Re-open the same session and destroy it properly.
session_start();
$_SESSION = [];
session_destroy();

// 3. Remove the session cookie (on the root path and on this folder's path).
$name   = session_name();
$params = session_get_cookie_params();
setcookie($name, '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
$folder = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
if ($folder !== $params['path']) {
    setcookie($name, '', time() - 42000, $folder, $params['domain'], $params['secure'], $params['httponly']);
}

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Plain link click (Sign out in the nav): go to the public landing page.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Location: index.html?signedout=1');
    exit;
}

header('Content-Type: application/json');
json_response(['success' => true]);