<?php
// TEMP diagnostic 3: why does the ZeptoMail send fail from the Jax web context? Delete after use.
if (($_GET['k'] ?? '') !== 'diag-zt3-20261013') { http_response_code(404); exit; }
header('Content-Type: text/plain');

$cfg = @include '/home/u897804896/domains/jaxtileandlvp.com/.zoho-jax-config.php';
$from = is_array($cfg) ? (string)($cfg['zeptomail_from'] ?? '') : '';

// token from sibling Braga .env (same resolution as lead.php)
$token = '';
foreach ((array)@file('/home/u897804896/domains/bragaremodeling.com/hbuilds/config/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
    $ln = trim($ln);
    if ($ln === '' || $ln[0] === '#' || strpos($ln, '=') === false) continue;
    [$k, $v] = explode('=', $ln, 2);
    if (trim($k) === 'ZEPTOMAIL_TOKEN') { $token = trim(trim($v), '"\''); break; }
}
echo 'token len=' . strlen($token) . "\n";

function ztSend(string $label, string $url, array $payload, string $token): void {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Zoho-enczapikey ' . $token, 'Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload)]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    echo "[$label] HTTP $code err=$err body=" . substr((string)$res, 0, 400) . "\n";
}

$base = [
    'from' => ['address' => $from, 'name' => 'Jax Tile & LVP'],
    'to' => [['email_address' => ['address' => 'braga@bragaremodeling.com', 'name' => 'Braga Remodeling']]],
    'subject' => 'DIAG ZT3 variant',
    'textbody' => 'variant test',
];

// A: exact repeat of the failing send
ztSend('A repeat', 'https://api.zeptomail.com/v1.1/email', $base, $token);
// B: from name without ampersand
$b = $base; $b['from']['name'] = 'Jax Tile and LVP'; $b['subject'] = 'DIAG ZT3 variant B';
ztSend('B no-amp', 'https://api.zeptomail.com/v1.1/email', $b, $token);
// C: minimal payload (no names at all)
$c = ['from' => ['address' => $from], 'to' => [['email_address' => ['address' => 'braga@bragaremodeling.com']]], 'subject' => 'DIAG ZT3 variant C', 'textbody' => 'minimal'];
ztSend('C minimal', 'https://api.zeptomail.com/v1.1/email', $c, $token);
