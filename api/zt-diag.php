<?php
// TEMP diagnostic: ZeptoMail send from WEB context. Delete after use.
if (($_GET['k'] ?? '') !== 'diag-zt-20261013') { http_response_code(404); exit; }
$cfg = include '/home/u897804896/domains/jaxtileandlvp.com/.zoho-jax-config.php';
$payload = json_encode([
    'from' => ['address' => $cfg['zeptomail_from'], 'name' => 'Jax Tile & LVP'],
    'to' => [['email_address' => ['address' => 'braga@bragaremodeling.com', 'name' => 'Braga Remodeling']]],
    'subject' => 'DIAG ZT web context',
    'textbody' => 'ZeptoMail send test from PHP web context on jaxtileandlvp.com host. Ignore.',
]);
$ch = curl_init('https://api.zeptomail.com/v1.1/email');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => ['Authorization: Zoho-enczapikey ' . $cfg['zeptomail_token'], 'Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_POSTFIELDS => $payload]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
header('Content-Type: text/plain');
echo "HTTP $code\n" . substr((string)$res, 0, 300) . "\n";
