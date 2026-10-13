<?php
// TEMP diagnostic 5: list KEY NAMES (never values) in the Braga app .env + probe POST patterns. Delete after use.
if (($_GET['k'] ?? '') !== 'diag-zt5-20261013') { http_response_code(404); exit; }
header('Content-Type: text/plain');

$envFile = '/home/u897804896/domains/bragaremodeling.com/hbuilds/config/.env';
$vals = [];
foreach ((array)@file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
    $ln = trim($ln);
    if ($ln === '' || $ln[0] === '#' || strpos($ln, '=') === false) continue;
    [$k, $v] = explode('=', $ln, 2);
    $vals[trim($k)] = trim(trim($v), '"\'');
}
echo 'env keys: ' . implode(', ', array_keys($vals)) . "\n";
if (isset($vals['ZEPTOMAIL_API_URL'])) echo 'ZEPTOMAIL_API_URL=' . $vals['ZEPTOMAIL_API_URL'] . "\n";
if (isset($vals['ZEPTOMAIL_FROM_ADDRESS'])) echo 'ZEPTOMAIL_FROM_ADDRESS=' . $vals['ZEPTOMAIL_FROM_ADDRESS'] . "\n";
$token = $vals['ZEPTOMAIL_TOKEN'] ?? '';
$apiUrl = $vals['ZEPTOMAIL_API_URL'] ?? 'https://api.zeptomail.com/v1.1/email';

function probe(string $label, string $url, ?string $body, ?string $token): void {
    $ch = curl_init($url);
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15];
    if ($body !== null) { $opts[CURLOPT_POST] = true; $opts[CURLOPT_POSTFIELDS] = $body; }
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token !== null) $headers[] = 'Authorization: Zoho-enczapikey ' . $token;
    $opts[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    echo "[$label] HTTP $code err=$err body=" . substr((string)$res, 0, 220) . "\n";
}

// probe pattern: POST {} WITH token (Node probe gets 400 when token is live)
probe('POST {} with token @ ' . $apiUrl, $apiUrl, '{}', $token);
// POST full payload WITHOUT token (expect structured 401 if POST mechanics are fine)
$cfg = @include '/home/u897804896/domains/jaxtileandlvp.com/.zoho-jax-config.php';
$from = is_array($cfg) ? (string)($cfg['zeptomail_from'] ?? '') : '';
$payload = json_encode(['from' => ['address' => $from, 'name' => 'Jax Tile & LVP'],
    'to' => [['email_address' => ['address' => 'braga@bragaremodeling.com', 'name' => 'Braga Remodeling']]],
    'subject' => 'DIAG ZT5 noauth', 'textbody' => 'noauth probe']);
probe('POST payload NO token', $apiUrl, $payload, null);
// POST full payload WITH token at the resolved API URL
$payload2 = json_encode(['from' => ['address' => ($vals['ZEPTOMAIL_FROM_ADDRESS'] ?? $from), 'name' => 'Jax Tile & LVP'],
    'to' => [['email_address' => ['address' => 'braga@bragaremodeling.com', 'name' => 'Braga Remodeling']]],
    'subject' => 'DIAG ZT5 resolved-url send', 'textbody' => 'resolved url test', 'htmlbody' => '<p>resolved url test</p>']);
probe('POST payload with token', $apiUrl, $payload2, $token);
