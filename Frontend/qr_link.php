<?php
// Builds the web address that goes inside an item's QR code.
//  * If the site was opened through a public address (for example a Cloudflare tunnel,
//    https://something.trycloudflare.com) that same address is used, so the QR works on ANY network.
//  * If the site was opened through localhost, a phone cannot use that address, so this computer's
//    Wi-Fi address is used instead (works only for phones on the same Wi-Fi).
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

require_login($pdo);

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    json_response(['error' => 'Invalid item.'], 422);
}

/* Only used when the site is opened through localhost. Put this computer's Wi-Fi address here
   (run "ipconfig" and use the IPv4 Address), or leave '' to detect it automatically. */
$MANUAL_HOST = '192.168.0.118';

function lan_ip(): string {
    // "Connecting" a UDP socket sends nothing; it just lets us read which local address would be used.
    $s = @stream_socket_client('udp://8.8.8.8:53', $errno, $errstr, 1);
    if ($s) {
        $name = stream_socket_get_name($s, false);   // e.g. "192.168.0.105:51234"
        fclose($s);
        if ($name) {
            $ip = explode(':', $name)[0];
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $ip;
            }
        }
    }
    $ip = gethostbyname(gethostname());
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : '127.0.0.1';
}

$hostHeader = $_SERVER['HTTP_HOST'] ?? 'localhost';
$parts = explode(':', $hostHeader);
$host  = $parts[0];
$port  = $parts[1] ?? '';

// Tunnels and proxies tell us the real scheme in this header.
$forwardedProto = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwardedProto === 'https';
$scheme  = $isHttps ? 'https' : 'http';

if (in_array($host, ['localhost', '127.0.0.1', '[::1]', '::1'], true)) {
    // Opened on this computer only: use the Wi-Fi address so a phone on the same network can reach it.
    $host = $MANUAL_HOST !== '' ? $MANUAL_HOST : lan_ip();
}
// Otherwise the site was opened through a real address (tunnel / Wi-Fi IP / domain): keep it as it is.

$dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$url = $scheme . '://' . $host . ($port !== '' ? ':' . $port : '') . $dir . '/item-detail.html?id=' . $id;

json_response(['url' => $url]);