<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(array $payload, int $status = 200): void { http_response_code($status); echo json_encode($payload); exit; }
function clean(string $key, int $max = 500): string { return mb_substr(trim(strip_tags((string)($_POST[$key] ?? ''))), 0, $max); }
function line(string $value): string { return str_replace(["\r", "\n"], ' ', trim($value)); }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') respond(['ok' => false, 'message' => 'Method not allowed'], 405);

// 1) Honeypot: bots fill the hidden "website" field. Pretend success, never count a conversion.
if (!empty($_POST['website'] ?? '')) respond(['ok' => true, 'trackConversion' => false]);

// 2) Time trap: real people take more than ~2.5 s to fill the form (set by /assets/site.js).
$elapsedRaw = clean('elapsed_ms', 12);
$elapsed = ctype_digit($elapsedRaw) ? (int)$elapsedRaw : null;
if ($elapsed !== null && $elapsed < 2500) respond(['ok' => true, 'trackConversion' => false]);

$name = line(clean('name', 100));
$email = line(clean('email', 150));
$service = clean('service', 80);
$details = clean('details', 1200);
$page = clean('page', 180);
$floor = clean('current_floor', 40);
$quote = clean('quote_summary', 700);

// 3) US phone: 10 digits (optional leading 1), valid NANP area code and exchange.
$phoneDigits = preg_replace('/\D/', '', clean('phone', 30));
if (strlen($phoneDigits) === 11 && $phoneDigits[0] === '1') $phoneDigits = substr($phoneDigits, 1);
$phoneOk = (bool)preg_match('/^[2-9](?!11)\d{2}[2-9]\d{6}$/', $phoneDigits);

// 4) ZIP: 5 digits (ZIP+4 accepted). Service area = 320xx / 322xx (Duval, St. Johns, Clay, Nassau).
$zipRaw = clean('zip', 10);
$zip = preg_match('/^(\d{5})(-\d{4})?$/', $zipRaw, $zm) ? $zm[1] : '';
$inArea = $zip !== '' && (bool)preg_match('/^32[02]\d{2}$/', $zip);

$sqftRaw = preg_replace('/[^\d]/', '', clean('sqft', 8));
$sqft = $sqftRaw === '' ? 0 : min((int)$sqftRaw, 50000);

$allowedServices = ['LVP Installed', 'Luxury Vinyl Plank / Vinyl'];
$allowedFloors = ['', 'Carpet', 'Tile', 'Wood / laminate', 'Vinyl / LVP', 'Bare concrete slab', 'Other / not sure'];
if (!in_array($floor, $allowedFloors, true)) $floor = 'Other / not sure';

if ($name === '') respond(['ok' => false, 'field' => 'name', 'message' => 'Please enter your name.'], 422);
if (!$phoneOk) respond(['ok' => false, 'field' => 'phone', 'message' => 'Please enter a valid 10-digit US phone number.'], 422);
if ($zip === '') respond(['ok' => false, 'field' => 'zip', 'message' => 'Please enter a 5-digit ZIP code.'], 422);
if (!in_array($service, $allowedServices, true) || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) respond(['ok' => false, 'message' => 'Please check the form and try again.'], 422);

// 5) Obvious spam content: links in the name, or many links in the details. Drop quietly.
if (preg_match('~https?://|www\.|@~i', $name) || preg_match_all('~https?://~i', $details) > 1) respond(['ok' => true, 'trackConversion' => false]);

// 6) Duplicate suppression (10 minutes).
$fingerprint = hash('sha256', strtolower($phoneDigits . '|' . $zip . '|' . $sqft . '|' . $details));
$cache = sys_get_temp_dir() . '/jax-lead-' . $fingerprint;
if (is_file($cache) && time() - filemtime($cache) < 600) respond(['ok' => true, 'duplicate' => true, 'trackConversion' => false]);

$tracking = [];
foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'gbraid', 'wbraid', 'fbclid'] as $key) { $value = clean($key, 180); if ($value !== '') $tracking[] = $key . ': ' . $value; }

$phonePretty = sprintf('(%s) %s-%s', substr($phoneDigits, 0, 3), substr($phoneDigits, 3, 3), substr($phoneDigits, 6));
$flags = [];
if (!$inArea) $flags[] = 'OUTSIDE SERVICE AREA (ZIP not 320xx/322xx)';
if ($elapsed === null) $flags[] = 'no JS timing (possible bot)';
$sizeTxt = $sqft > 0 ? $sqft . ' sq ft' : 'sq ft not given';
$smallJob = $sqft > 0 && $sqft < 500;
// Small-room flat prices shown on the site (under 500 sq ft).
$smallTier = null;
if ($smallJob) {
  foreach ([[150, 895, 'up to 150 sq ft'], [250, 1195, '151-250 sq ft'], [350, 1495, '251-350 sq ft'], [499, 1895, '351-499 sq ft']] as $t) {
    if ($sqft <= $t[0]) { $smallTier = $t; break; }
  }
}
$tierTxt = $smallTier ? '$' . number_format($smallTier[1]) . ' tier (' . $smallTier[2] . ')' : '';
$subject = ($inArea ? '' : '[CHECK AREA] ') . ($smallTier ? '[SMALL ROOM ' . $tierTxt . '] New LVP small-room lead: ' : 'New LVP $3.99 lead: ') . $zip . ' · ' . $sizeTxt;
$estimate = $smallTier ? 'SMALL-ROOM FLAT PRICE ' . $tierTxt . ': in-stock LVP, installation, carpet removal & haul-off, cleanup; add-ons extra; tier confirmed at free measure' : ($sqft > 0 ? '$' . number_format($sqft * 3.99, 0) . ' base at $3.99/sq ft (package, 500+ sq ft), before add-ons' : 'n/a');
$body = "Name: $name\nPhone: $phonePretty\nEmail: $email\nZIP: $zip\nApprox. sq ft: $sizeTxt\nCurrent floor: " . ($floor ?: 'not given') . "\nBase estimate: $estimate\nService: $service\nPage: $page"
  . ($quote !== '' ? "\n$quote" : '')
  . ($details !== '' ? "\nDetails: $details" : '')
  . "\nSource: jaxtileandlvp.com"
  . ($flags ? "\n\nFlags: " . implode('; ', $flags) : '')
  . ($tracking ? "\n\nAttribution:\n" . implode("\n", $tracking) : '');
$headers = "From: Jax Tile & LVP <wordpress@jaxtileandlvp.com>\r\n" . ($email !== '' ? 'Reply-To: ' . $email . "\r\n" : '') . "Content-Type: text/plain; charset=UTF-8\r\n";

$sent = mail('braga@bragaremodeling.com', '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
if (!$sent) respond(['ok' => false, 'message' => 'Unable to send right now.'], 503);
touch($cache);

// 7) Zoho CRM: create the lead so it shows up in the CRM + safety-net monitor.
// Best-effort: never blocks the user response. Credentials live OUTSIDE public_html.
sendToZoho([
    'name' => $name,
    'email' => $email,
    'phone' => $phonePretty,
    'zip' => $zip,
    'sqft' => $sqft > 0 ? (string)$sqft : '',
    'service' => $service,
    'floor' => $floor,
    'details' => $details,
    'page' => $page,
    'quote' => $quote,
    'estimate' => $estimate,
    'tracking' => $tracking,
    'flags' => $flags,
]);

// Only real, in-area leads with JS timing count as a Google Ads / GA4 / Meta conversion.
respond(['ok' => true, 'trackConversion' => $inArea && $elapsed !== null]);

/**
 * Create a Lead in Zoho CRM (best-effort, 8s total budget).
 * Config: /home/<user>/.zoho-jax-config.php  (NOT in public_html)
 * Format: <?php return ['client_id'=>'...','client_secret'=>'...','refresh_token'=>'...'];
 */
function sendToZoho(array $lead): void
{
    $home = getenv('HOME') ?: (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['dir'] : '');
    $configFile = rtrim($home, '/') . '/.zoho-jax-config.php';
    if ($home === '' || !is_file($configFile)) return;
    $cfg = @include $configFile;
    if (!is_array($cfg) || empty($cfg['client_id']) || empty($cfg['client_secret']) || empty($cfg['refresh_token'])) return;

    $deadline = microtime(true) + 8;

    // Refresh the access token.
    $tokenRes = zohoCurl('https://accounts.zoho.com/oauth/v2/token', [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'refresh_token' => $cfg['refresh_token'],
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'grant_type' => 'refresh_token',
        ]),
    ], $deadline);
    if ($tokenRes === null) return;
    $tokenData = json_decode($tokenRes, true);
    $accessToken = $tokenData['access_token'] ?? '';
    $apiDomain = rtrim($tokenData['api_domain'] ?? 'https://www.zohoapis.com', '/');
    if ($accessToken === '') return;

    // Split "First Last" for Zoho's required Last_Name.
    $parts = preg_split('/\s+/', trim($lead['name']), 2);
    $firstName = $parts[0] ?? $lead['name'];
    $lastName = $parts[1] ?? $parts[0] ?? $lead['name'];

    $description = implode("\n", array_filter([
        'Service Requested: ' . $lead['service'],
        $lead['details'] !== '' ? 'Message: ' . $lead['details'] : null,
        $lead['zip'] !== '' ? 'ZIP: ' . $lead['zip'] : null,
        $lead['sqft'] !== '' ? 'Approx. sq ft: ' . $lead['sqft'] : null,
        $lead['floor'] !== '' ? 'Current floor: ' . $lead['floor'] : null,
        $lead['estimate'] !== '' && $lead['estimate'] !== 'n/a' ? 'Estimate shown: ' . $lead['estimate'] : null,
        $lead['page'] !== '' ? 'Source Page: ' . $lead['page'] : null,
        $lead['quote'] !== '' ? $lead['quote'] : null,
        'Lead Source: jaxtileandlvp.com',
        !empty($lead['tracking']) ? 'Attribution: ' . implode('; ', $lead['tracking']) : null,
        !empty($lead['flags']) ? 'Flags: ' . implode('; ', $lead['flags']) : null,
        'Captured at: ' . gmdate('c'),
    ]));

    $payload = json_encode(['data' => [[
        'First_Name' => mb_substr($firstName, 0, 40),
        'Last_Name' => mb_substr($lastName, 0, 80),
        'Phone' => $lead['phone'] ?: null,
        'Email' => $lead['email'] !== '' ? $lead['email'] : null,
        'Zip_Code' => $lead['zip'] !== '' ? $lead['zip'] : null,
        'Company' => 'Jax Tile & LVP Website Lead',
        'Lead_Source' => 'Jax Tile LVP',
        'Description' => $description,
    ]]]);

    zohoCurl($apiDomain . '/crm/v2/Leads', [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Zoho-oauthtoken ' . $accessToken,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $payload,
    ], $deadline);
    // Response intentionally ignored: duplicate/outage must not break the form.
}

/** Minimal curl wrapper with a shared deadline. Returns body string or null. */
function zohoCurl(string $url, array $opts, float $deadline): ?string
{
    $remaining = $deadline - microtime(true);
    if ($remaining <= 0.5) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, $opts + [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT_MS => (int)($remaining * 1000),
        CURLOPT_CONNECTTIMEOUT_MS => 3000,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $httpCode < 200 || $httpCode >= 300) return null;
    return (string)$body;
}
