<?php
// TEMP diagnostic: which ZeptoMail token does the web context resolve, and does a send work? Delete after use.
if (($_GET['k'] ?? '') !== 'diag-zt2-20261013') { http_response_code(404); exit; }
header('Content-Type: text/plain');

function fp(string $t): string { return $t === '' ? '(empty)' : substr($t, 0, 4) . '...' . substr($t, -4) . ' len=' . strlen($t); }

// 1) env
$envTok = (string)(getenv('JAX_ZEPTOMAIL_TOKEN') ?: '');
echo 'env JAX_ZEPTOMAIL_TOKEN: ' . fp($envTok) . "\n";

// 2) sibling Braga .env
$sibTok = '';
$paths = [];
$home = (string)(getenv('HOME') ?: '');
if ($home !== '') $paths[] = rtrim($home, '/') . '/domains/bragaremodeling.com/hbuilds/config/.env';
$docRoot = realpath(__DIR__ . '/..');
if ($docRoot !== false) $paths[] = dirname(dirname($docRoot)) . '/bragaremodeling.com/hbuilds/config/.env';
foreach ($paths as $p) {
    echo 'sibling path ' . $p . ': exists=' . (is_file($p) ? 'yes' : 'no') . ' readable=' . (is_readable($p) ? 'yes' : 'no') . "\n";
    if ($sibTok === '' && is_file($p) && is_readable($p)) {
        foreach ((array)@file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
            $ln = trim($ln);
            if ($ln === '' || $ln[0] === '#' || strpos($ln, '=') === false) continue;
            [$k, $v] = explode('=', $ln, 2);
            if (trim($k) === 'ZEPTOMAIL_TOKEN') { $sibTok = trim(trim($v), '"\''); break; }
        }
    }
}
echo 'sibling ZEPTOMAIL_TOKEN: ' . fp($sibTok) . "\n";

// 3) config file token + from
$cfg = @include '/home/u897804896/domains/jaxtileandlvp.com/.zoho-jax-config.php';
$cfgTok = is_array($cfg) ? (string)($cfg['zeptomail_token'] ?? '') : '';
$from = is_array($cfg) ? (string)($cfg['zeptomail_from'] ?? '') : '';
echo 'config zeptomail_token: ' . fp($cfgTok) . "\n";
echo 'config zeptomail_from: ' . $from . "\n";

// resolved token = same order as lead.php zeptoConfig()
$token = $envTok !== '' ? $envTok : ($sibTok !== '' ? $sibTok : $cfgTok);
echo 'resolved: ' . fp($token) . "\n";

// 4) send test
$payload = json_encode([
    'from' => ['address' => $from, 'name' => 'Jax Tile & LVP'],
    'to' => [['email_address' => ['address' => 'braga@bragaremodeling.com', 'name' => 'Braga Remodeling']]],
    'subject' => 'DIAG ZT2 resolved-token send',
    'textbody' => 'ZeptoMail send test using the token resolved by the Jax web context (env -> sibling .env -> config). Ignore.',
]);
$ch = curl_init('https://api.zeptomail.com/v1.1/email');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
    CURLOPT_HTTPHEADER => ['Authorization: Zoho-enczapikey ' . $token, 'Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_POSTFIELDS => $payload]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "send HTTP $code\n" . substr((string)$res, 0, 300) . "\n";
