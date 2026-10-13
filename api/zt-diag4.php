<?php
// TEMP diagnostic 4: is api.zeptomail.com reachable from the Jax web context at all? Delete after use.
if (($_GET['k'] ?? '') !== 'diag-zt4-20261013') { http_response_code(404); exit; }
header('Content-Type: text/plain');

function probe(string $label, string $url, array $opts = []): void {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15] + $opts);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    $ip = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
    curl_close($ch);
    echo "[$label] HTTP $code ip=$ip err=$err body=" . substr((string)$res, 0, 200) . "\n";
}

probe('GET google', 'https://www.google.com/');
probe('GET zeptomail root', 'https://api.zeptomail.com/');
probe('GET zeptomail v1.1', 'https://api.zeptomail.com/v1.1/email');

// token from sibling Braga .env
$token = '';
foreach ((array)@file('/home/u897804896/domains/bragaremodeling.com/hbuilds/config/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
    $ln = trim($ln);
    if ($ln === '' || $ln[0] === '#' || strpos($ln, '=') === false) continue;
    [$k, $v] = explode('=', $ln, 2);
    if (trim($k) === 'ZEPTOMAIL_TOKEN') { $token = trim(trim($v), '"\''); break; }
}
$cfg = @include '/home/u897804896/domains/jaxtileandlvp.com/.zoho-jax-config.php';
$from = is_array($cfg) ? (string)($cfg['zeptomail_from'] ?? '') : '';
$payload = json_encode(['from' => ['address' => $from, 'name' => 'Jax Tile & LVP'],
    'to' => [['email_address' => ['address' => 'braga@bragaremodeling.com', 'name' => 'Braga Remodeling']]],
    'subject' => 'DIAG ZT4 http11', 'textbody' => 'http1.1 + UA test']);
probe('POST send HTTP/1.1+UA', 'https://api.zeptomail.com/v1.1/email', [
    CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36',
    CURLOPT_HTTPHEADER => ['Authorization: Zoho-enczapikey ' . $token, 'Content-Type: application/json', 'Accept: application/json'],
]);
