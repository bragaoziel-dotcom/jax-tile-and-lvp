<?php
// TEMP diagnostic 6: stale-token probe vs new-token probe patterns. Delete after use.
if (($_GET['k'] ?? '') !== 'diag-zt6-20261013') { http_response_code(404); exit; }
header('Content-Type: text/plain');

$vals = [];
foreach ((array)@file('/home/u897804896/domains/bragaremodeling.com/hbuilds/config/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
    $ln = trim($ln);
    if ($ln === '' || $ln[0] === '#' || strpos($ln, '=') === false) continue;
    [$k, $v] = explode('=', $ln, 2);
    $vals[trim($k)] = trim(trim($v), '"\'');
}
$newTok = $vals['ZEPTOMAIL_TOKEN'] ?? '';
$cfg = @include '/home/u897804896/domains/jaxtileandlvp.com/.zoho-jax-config.php';
$staleTok = is_array($cfg) ? (string)($cfg['zeptomail_token'] ?? '') : '';
$url = 'https://api.zeptomail.com/v1.1/email';

function probe(string $label, string $url, string $token, array $extra = []): void {
    $ch = curl_init($url);
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_POST => true, CURLOPT_POSTFIELDS => '{}',
        CURLOPT_HTTPHEADER => ['Authorization: Zoho-enczapikey ' . $token, 'Content-Type: application/json', 'Accept: application/json']];
    foreach ($extra as $k => $v) $opts[$k] = $v;
    curl_setopt_array($ch, $opts);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    echo "[$label] HTTP $code err=$err body=" . substr((string)$res, 0, 220) . "\n";
}

probe('{} stale token', $url, $staleTok);
probe('{} new token + UA + HTTP1.1', $url, $newTok, [CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36', CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1]);
probe('{} new token + no Accept header test', $url, $newTok);
