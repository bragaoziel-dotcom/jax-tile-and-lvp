<?php
declare(strict_types=1);
// Every lead notification goes to both inboxes (Oziel, 2026-10-17).
const LEAD_RECIPIENTS = [['braga@bragaremodeling.com', 'Braga Remodeling'], ['bragaoziel@gmail.com', 'Oziel Braga']];
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
$estSummary = mb_substr(trim(strip_tags((string)($_POST['estimate_summary'] ?? ''))), 0, 3000);
$allowedColors = ['Aspen (Promo)', 'Pecan (Promo)', 'Sandalwood (Promo)', 'Honey Oak', 'Driftwood Gray', 'Silver Ash', 'Weathered Charcoal', 'Golden Chestnut', 'Smoke Taupe', 'Natural Maple', 'Sandy Birch'];
$color = clean('color', 40);
if (!in_array($color, $allowedColors, true)) $color = '';

// 3) US phone: 10 digits (optional leading 1), valid NANP area code and exchange.
$phoneDigits = preg_replace('/\D/', '', clean('phone', 30));
if (strlen($phoneDigits) === 11 && $phoneDigits[0] === '1') $phoneDigits = substr($phoneDigits, 1);
$phoneOk = (bool)preg_match('/^[2-9](?!11)\d{2}[2-9]\d{6}$/', $phoneDigits);

// 4) ZIP: 5 digits (ZIP+4 accepted). Service area = 320xx / 322xx (Duval, St. Johns, Clay, Nassau).
$zipRaw = clean('zip', 10);
$zip = preg_match('/^(\d{5})(-\d{4})?$/', $zipRaw, $zm) ? $zm[1] : '';
$inArea = $zip !== '' && (bool)preg_match('/^32[02]\d{2}$/', $zip);

// Qualified-lead rule (online signal for Google Ads, 2026-10-17): LVP lead, in the
// service area, 1,000+ sq ft (the $3.99 package minimum). ZIPs the campaign excludes
// (Callahan, Hilliard, Middleburg) never count. Leads without sq ft are NOT counted
// online; they can still be imported later as offline "qualified" conversions.
const QUALIFIED_MIN_SQFT = 1000;
const QUALIFIED_EXCLUDED_ZIPS = ['32011', '32046', '32068'];

$sqftRaw = preg_replace('/[^\d]/', '', clean('sqft', 8));
$sqft = $sqftRaw === '' ? 0 : min((int)$sqftRaw, 50000);

$allowedServices = ['LVP Installed', 'Luxury Vinyl Plank / Vinyl', 'Tile Installation'];
$allowedFloors = ['', 'Carpet', 'Tile', 'Wood / laminate', 'Vinyl / LVP', 'Bare concrete slab', 'Bare wall or concrete', 'Other / not sure'];
if (!in_array($floor, $allowedFloors, true)) $floor = 'Other / not sure';

if ($name === '') respond(['ok' => false, 'field' => 'name', 'message' => 'Please enter your name.'], 422);
if (!$phoneOk) respond(['ok' => false, 'field' => 'phone', 'message' => 'Please enter a valid 10-digit US phone number.'], 422);
if ($zip === '') respond(['ok' => false, 'field' => 'zip', 'message' => 'Please enter a 5-digit ZIP code.'], 422);
if (!in_array($service, $allowedServices, true) || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) respond(['ok' => false, 'message' => 'Please check the form and try again.'], 422);

// 5) Obvious spam content: links in the name, or many links in the details. Drop quietly.
if (preg_match('~https?://|www\.|@~i', $name) || preg_match_all('~https?://~i', $details) > 1) respond(['ok' => true, 'trackConversion' => false]);

// 6) Duplicate suppression (10 minutes).
$fingerprint = hash('sha256', strtolower($phoneDigits . '|' . $zip . '|' . $sqft . '|' . $details . '|' . $estSummary));
$cache = sys_get_temp_dir() . '/jax-lead-' . $fingerprint;
if (is_file($cache) && time() - filemtime($cache) < 600) respond(['ok' => true, 'duplicate' => true, 'trackConversion' => false]);

$tracking = [];
foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'gbraid', 'wbraid', 'fbclid'] as $key) { $value = clean($key, 180); if ($value !== '') $tracking[] = $key . ': ' . $value; }

$phonePretty = sprintf('(%s) %s-%s', substr($phoneDigits, 0, 3), substr($phoneDigits, 3, 3), substr($phoneDigits, 6));
$flags = [];
if (!$inArea) $flags[] = 'OUTSIDE SERVICE AREA (ZIP not 320xx/322xx)';
if ($elapsed === null) $flags[] = 'no JS timing (possible bot)';
$sizeTxt = $sqft > 0 ? $sqft . ' sq ft' : 'sq ft not given';
$smallJob = $sqft > 0 && $sqft < 1000;
// The $3.99 package is for 1,000 sq ft and up; smaller areas are not advertised (custom quote, Oziel decides).
$isTile = $service === 'Tile Installation';
$qualified = $inArea && !$isTile && $sqft >= QUALIFIED_MIN_SQFT && !in_array($zip, QUALIFIED_EXCLUDED_ZIPS, true);
$leadId = 'JTL-' . date('Ymd') . '-' . substr($fingerprint, 0, 8);
$subject = ($inArea ? '' : '[CHECK AREA] ') . ($isTile ? 'New tile installation lead: ' : ($smallJob ? '[UNDER 1,000 SQ FT] New LVP lead: ' : 'New LVP $3.99 lead: ')) . $zip . ' · ' . $sizeTxt;
$estimate = $isTile
    ? 'Tile installation: custom quote after free measure (no published package price)'
    : ($smallJob ? 'UNDER 1,000 SQ FT: package price does not apply; no price was shown to the customer (custom quote)' : ($sqft > 0 ? '$' . number_format($sqft * 3.99, 0) . ' base at $3.99/sq ft (package, 1,000+ sq ft), before add-ons' : 'n/a'));
$body = "Name: $name\nPhone: $phonePretty\nEmail: $email\nZIP: $zip\nApprox. sq ft: $sizeTxt\nCurrent floor: " . ($floor ?: 'not given') . "\nBase estimate: $estimate\nService: $service\nPage: $page"
  . ($color !== '' ? "\nLVP color: $color" : '')
  . ($quote !== '' ? "\n$quote" : '')
  . ($estSummary !== '' ? "\n\n$estSummary" : '')
  . ($details !== '' ? "\nDetails: $details" : '')
  . "\nSource: jaxtileandlvp.com"
  . "\nLead ID: $leadId"
  . "\nPre-qualified (online rule: 1,000+ sq ft, in area, not tile): " . ($qualified ? 'YES' : 'NO')
  . "\nMaterial+install confirmed: PENDING (confirm with client)"
  . ($flags ? "\n\nFlags: " . implode('; ', $flags) : '')
  . ($tracking ? "\n\nAttribution:\n" . implode("\n", $tracking) : '');
$headers = "From: Jax Tile & LVP <wordpress@jaxtileandlvp.com>\r\n" . ($email !== '' ? 'Reply-To: ' . $email . "\r\n" : '') . "Content-Type: text/plain; charset=UTF-8\r\n";

// Notification transport (root-caused 2026-10-13): Hostinger's shared sendmail
// (hsendmail) silently drops lead-shaped notifications from this domain.
// Verified by bisection: trivial test mail via mail() reaches
// braga@bragaremodeling.com instantly, but the lead notification — with the
// real subject and/or the real body — never arrives, even though mail()
// returns true (in web AND CLI contexts, with or without an aligned -f
// envelope sender; SPF/DMARC alignment was NOT the cause). Deliveries stopped
// after 2026-10-11 12:37 EDT. Primary transport is therefore the ZeptoMail
// HTTPS API (same transport as bragaremodeling.com): authenticated API send
// from noreply@bragaremodeling.com, DKIM-signed, bypassing hsendmail entirely.
// mail() with the aligned envelope sender stays as a fallback only.
// Photo estimator uploads (resized to ~1600px JPEG in the browser).
$photos = collectPhotos();
if ($photos) {
    $subject = '[' . count($photos) . ' PHOTOS] ' . $subject;
    $body .= "\n\nPhotos attached: " . count($photos);
    $saved = savePhotos($photos, $phoneDigits);
    if ($saved !== '') $body .= "\nServer copy: " . $saved;
}
$sent = sendViaZeptoMail($subject, $body, $email, $photos);
if (!$sent && $photos) {
    error_log('[jax-lead] ZeptoMail with attachments failed; retrying without photos');
    $sent = sendViaZeptoMail($subject, $body . "\n(Photos could not be attached to this email; see server copy above.)", $email);
}
if (!$sent) {
    error_log('[jax-lead] ZeptoMail transport failed, falling back to mail() for lead from page ' . $page . ' ZIP ' . $zip);
    $sent = mail(implode(', ', array_column(LEAD_RECIPIENTS, 0)), '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers, '-fwordpress@jaxtileandlvp.com');
}
if (!$sent) {
    error_log('[jax-lead] all notification transports failed for lead from page ' . $page . ' ZIP ' . $zip);
    respond(['ok' => false, 'message' => 'Unable to send right now.'], 503);
}
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
    'quote' => trim($quote . ($color !== '' ? ' | Color: ' . $color : '') . ($estSummary !== '' ? "\n" . $estSummary : '')),
    'estimate' => $estimate,
    'tracking' => $tracking,
    'flags' => $flags,
    'lead_id' => $leadId,
    'qualified' => $qualified,
]);

// Only real, in-area leads with JS timing count as a Google Ads / GA4 / Meta conversion.
$countable = $inArea && $elapsed !== null;
respond(['ok' => true, 'trackConversion' => $countable, 'qualified' => $countable && $qualified, 'leadId' => $leadId]);

/**
 * Server config file shared by the Zoho CRM and ZeptoMail transports.
 * Same candidate locations as zohoConfig() (outside the web root).
 */
function jaxConfigFile(): ?array
{
    $candidates = [];
    $home = getenv('HOME') ?: (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['dir'] : '');
    if ($home !== '') $candidates[] = rtrim($home, '/') . '/.zoho-jax-config.php';
    $docRoot = realpath(__DIR__ . '/..');
    if ($docRoot !== false) {
        $candidates[] = dirname($docRoot) . '/.zoho-jax-config.php'; // domains/<domain>/
        $candidates[] = dirname(dirname($docRoot)) . '/.zoho-jax-config.php'; // account home
    }
    foreach ($candidates as $configFile) {
        if (is_file($configFile)) {
            $cfg = @include $configFile;
            if (is_array($cfg)) return $cfg;
        }
    }
    return null;
}

/**
 * Read one KEY=value from the BRAGA app's .env (same hosting account).
 * The hPanel env editor is the single, working rotation point for the
 * ZeptoMail token (the File Manager editor cannot be written by our
 * automation), so the Jax site resolves the token from there first.
 * The value is never logged.
 */
function bragaEnvValue(string $key): string
{
    $candidates = [];
    $home = (string)(getenv('HOME') ?: '');
    if ($home !== '') $candidates[] = rtrim($home, '/') . '/domains/bragaremodeling.com/hbuilds/config/.env';
    $docRoot = realpath(__DIR__ . '/..');
    if ($docRoot !== false) {
        // domains/<this-domain>/ -> domains/bragaremodeling.com/...
        $candidates[] = dirname(dirname($docRoot)) . '/bragaremodeling.com/hbuilds/config/.env';
    }
    foreach ($candidates as $file) {
        if (!is_file($file) || !is_readable($file)) continue;
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) continue;
        foreach ($lines as $ln) {
            $ln = trim($ln);
            if ($ln === '' || $ln[0] === '#' || strpos($ln, '=') === false) continue;
            [$k, $v] = explode('=', $ln, 2);
            if (trim($k) !== $key) continue;
            $v = trim($v);
            if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'")) $v = trim($v, '"\'');
            return $v;
        }
    }
    return '';
}

/**
 * ZeptoMail credentials: env JAX_ZEPTOMAIL_TOKEN first, then the BRAGA
 * app's .env (ZEPTOMAIL_TOKEN, the live rotation point), then the
 * zeptomail_token key in the server config file (last resort).
 * From: env JAX_ZEPTOMAIL_FROM, else zeptomail_from in the config file.
 */
function zeptoConfig(): ?array
{
    $token = (string)(getenv('JAX_ZEPTOMAIL_TOKEN') ?: '');
    if ($token === '') $token = bragaEnvValue('ZEPTOMAIL_TOKEN');
    $from = (string)(getenv('JAX_ZEPTOMAIL_FROM') ?: '');
    if ($token === '' || $from === '') {
        $cfg = jaxConfigFile();
        if (is_array($cfg)) {
            if ($token === '') $token = (string)($cfg['zeptomail_token'] ?? '');
            if ($from === '') $from = (string)($cfg['zeptomail_from'] ?? '');
        }
    }
    if ($token === '' || $from === '') return null;
    // The stored value may carry the full "Zoho-enczapikey " scheme prefix
    // (that is how the ZeptoMail console presents the key, and how it ended
    // up in the BRAGA app's .env). Sending the prefix twice makes the
    // ZeptoMail backend answer a bare HTTP 500 — strip it, like the Braga
    // app's own client does.
    $token = (string)preg_replace('/^Zoho-enczapikey\s+/i', '', trim($token));
    if ($token === '') return null;
    return ['token' => $token, 'from' => $from];
}

/**
 * Send the lead notification through the ZeptoMail HTTPS API.
 * Returns true only on a 2xx API response. Never throws.
 */
function sendViaZeptoMail(string $subject, string $body, string $replyToEmail, array $attachments = []): bool
{
    $cfg = zeptoConfig();
    if ($cfg === null) {
        error_log('[jax-lead] ZeptoMail not configured (no token/from)');
        return false;
    }
    $payload = [
        // From name matches the sending domain (bragaremodeling.com); a different brand
        // name on this domain looked like impersonation and landed in Spam (2026-10-17).
        'from' => ['address' => $cfg['from'], 'name' => 'Braga Website Leads'],
        'to' => array_map(fn($a) => ['email_address' => ['address' => $a[0], 'name' => $a[1]]], LEAD_RECIPIENTS),
        'subject' => $subject,
        'textbody' => $body,
    ];
    if ($attachments) {
        $payload['attachments'] = array_map(fn($a) => ['content' => base64_encode($a['data']), 'mime_type' => $a['mime'], 'name' => $a['name']], $attachments);
    }
    if ($replyToEmail !== '' && filter_var($replyToEmail, FILTER_VALIDATE_EMAIL)) {
        $payload['reply_to'] = [['address' => $replyToEmail]];
    }
    $ch = curl_init('https://api.zeptomail.com/v1.1/email');
    if ($ch === false) return false;
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_HTTPHEADER => [
            'Authorization: Zoho-enczapikey ' . $cfg['token'],
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 200 && $code < 300) return true;
    error_log('[jax-lead] ZeptoMail send failed HTTP ' . $code . ': ' . substr((string)$res, 0, 200));
    return false;
}

/**
 * Create a Lead in Zoho CRM (best-effort, 8s total budget).
 * Credentials are never stored in this file.
 * Prefer environment variables (Hostinger PHP env or the server environment):
 *   JAX_ZOHO_CLIENT_ID, JAX_ZOHO_CLIENT_SECRET, JAX_ZOHO_REFRESH_TOKEN
 * Fallback, still outside the web root: /home/<user>/.zoho-jax-config.php
 * Format: <?php return ['client_id'=>'...','client_secret'=>'...','refresh_token'=>'...'];
 */
function zohoConfig(): ?array
{
    $fromEnv = [
        'client_id' => (string)(getenv('JAX_ZOHO_CLIENT_ID') ?: ''),
        'client_secret' => (string)(getenv('JAX_ZOHO_CLIENT_SECRET') ?: ''),
        'refresh_token' => (string)(getenv('JAX_ZOHO_REFRESH_TOKEN') ?: ''),
    ];
    if ($fromEnv['client_id'] !== '' && $fromEnv['client_secret'] !== '' && $fromEnv['refresh_token'] !== '') {
        return $fromEnv;
    }

    $candidates = [];
    $home = getenv('HOME') ?: (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['dir'] : '');
    if ($home !== '') $candidates[] = rtrim($home, '/') . '/.zoho-jax-config.php';
    // Hostinger: domain home is <account-home>/domains/<domain>
    $docRoot = realpath(__DIR__ . '/..');
    if ($docRoot !== false) {
        $candidates[] = dirname($docRoot) . '/.zoho-jax-config.php'; // domains/<domain>/
        $candidates[] = dirname(dirname($docRoot)) . '/.zoho-jax-config.php'; // account home
    }
    foreach ($candidates as $configFile) {
        if (is_file($configFile)) {
            $cfg = @include $configFile;
            if (is_array($cfg)) return $cfg;
        }
    }
    return null;
}

function sendToZoho(array $lead): void
{
    $cfg = zohoConfig();
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
        !empty($lead['lead_id']) ? 'Lead ID: ' . $lead['lead_id'] . ' | Pre-qualified (online rule: 1,000+ sq ft, in area, not tile): ' . (!empty($lead['qualified']) ? 'YES' : 'NO') . ' | Material+install confirmed: PENDING (confirm with client)' : null,
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

/**
 * Validate uploaded photos[]: real images only, max 12 files, 6 MB each, 14 MB total.
 * Returns [['name','mime','data'], ...]. Never throws.
 */
function collectPhotos(): array
{
    $f = $_FILES['photos'] ?? null;
    if (!is_array($f) || !is_array($f['tmp_name'] ?? null)) return [];
    $out = []; $total = 0;
    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
    foreach ($f['tmp_name'] as $i => $tmp) {
        if (count($out) >= 12) break;
        if (($f['error'][$i] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) continue;
        $size = (int)($f['size'][$i] ?? 0);
        if ($size <= 0 || $size > 6 * 1024 * 1024 || $total + $size > 14 * 1024 * 1024) continue;
        $mime = $finfo ? (string)finfo_file($finfo, $tmp) : (string)(@getimagesize($tmp)['mime'] ?? '');
        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/heic' => 'heic', 'image/heif' => 'heif'][$mime] ?? null;
        if ($ext === null) continue;
        $data = (string)file_get_contents($tmp);
        $base = preg_replace('/[^a-z0-9-]+/', '-', strtolower(pathinfo((string)($f['name'][$i] ?? 'photo'), PATHINFO_FILENAME))) ?: 'photo';
        $out[] = ['name' => substr($base, 0, 40) . '.' . $ext, 'mime' => $mime, 'data' => $data];
        $total += $size;
    }
    return $out;
}

/** Best-effort backup copy OUTSIDE the web root (domains/<domain>/lead-photos/). Returns the folder or ''. */
function savePhotos(array $photos, string $phone): string
{
    $docRoot = realpath(__DIR__ . '/..');
    if ($docRoot === false) return '';
    $dir = dirname($docRoot) . '/lead-photos/' . date('Y-m-d_His') . '_' . substr($phone, -4) . '_' . bin2hex(random_bytes(3));
    if (!@mkdir($dir, 0700, true)) return '';
    foreach ($photos as $i => $p) @file_put_contents($dir . '/' . ($i + 1) . '-' . $p['name'], $p['data']);
    return str_replace(dirname($docRoot), '~/domains/jaxtileandlvp.com', $dir);
}
