<?php
// TEMP diagnostic 7: token byte hygiene + sanitized probe. Delete after use.
if (($_GET['k'] ?? '') !== 'diag-zt7-20261013') { http_response_code(404); exit; }
header('Content-Type: text/plain');

$raw = '';
foreach ((array)@file('/home/u897804896/domains/bragaremodeling.com/hbuilds/config/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
    if (strpos($ln, 'ZEPTOMAIL_TOKEN=') === 0 || preg_match('/^\s*ZEPTOMAIL_TOKEN\s*=/', $ln)) {
        $raw = trim(substr($ln, strpos($ln, '=') + 1));
        break;
    }
}
echo 'raw value len=' . strlen($raw) . ' first byte=' . ord($raw[0]) . ' last byte=' . ord($raw[strlen($raw) - 1]) . "\n";
$unquoted = trim($raw, '"\'');
echo 'unquoted len=' . strlen($unquoted) . "\n";
// positions of any byte outside the safe token charset
$bad = [];
for ($i = 0; $i < strlen($unquoted); $i++) {
    $c = $unquoted[$i];
    if (!preg_match('/[A-Za-z0-9+\/=._-]/', $c)) $bad[] = $i . ':0x' . strtoupper(bin2hex($c));
}
echo 'unsafe bytes: ' . ($bad ? implode(' ', $bad) : 'none') . "\n";
$clean = preg_replace('/[^A-Za-z0-9+\/=._-]/', '', $unquoted);
echo 'clean len=' . strlen($clean) . "\n";

function probe(string $label, string $token): void {
    $ch = curl_init('https://api.zeptomail.com/v1.1/email');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_POST => true, CURLOPT_POSTFIELDS => '{}',
        CURLOPT_HTTPHEADER => ['Authorization: Zoho-enczapikey ' . $token, 'Content-Type: application/json', 'Accept: application/json']]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "[$label] HTTP $code body=" . substr((string)$res, 0, 200) . "\n";
}
probe('{} unquoted token', $unquoted);
probe('{} sanitized token', $clean);
