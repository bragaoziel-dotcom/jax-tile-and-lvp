<?php
declare(strict_types=1);

/**
 * Live Braga Remodeling Google reviews for jaxtileandlvp.com.
 *
 * Prefers Places API (New) when GOOGLE_PLACES_API_KEY or GOOGLE_MAPS_API_KEY
 * is set in the environment. Otherwise proxies the cached feed on
 * bragaremodeling.com. Never invents a rating, count or review text.
 */

const GOOGLE_PLACE_ID = 'ChIJ-bI5F1N2jYcRepVs4CTp9dc';
const GOOGLE_REVIEWS_URL = 'https://search.google.com/local/reviews?placeid=' . GOOGLE_PLACE_ID;
const GOOGLE_MAPS_PLACE_URL = 'https://www.google.com/maps/place/?q=place_id:' . GOOGLE_PLACE_ID;
const BRAGA_REVIEWS_API = 'https://bragaremodeling.com/api/google-reviews';
const CACHE_TTL = 8 * 60 * 60;
const ALLOWED_ORIGINS = [
    'https://jaxtileandlvp.com',
    'https://www.jaxtileandlvp.com',
    'https://bragaremodeling.com',
    'https://www.bragaremodeling.com',
];

function reviews_headers(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=300, stale-while-revalidate=3600');
    header('X-Content-Type-Options: nosniff');
    header('Vary: Origin');
    if ($origin !== '' && in_array($origin, ALLOWED_ORIGINS, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Accept, Content-Type');
    }
}

function reviews_respond(array $payload, int $status = 200): void
{
    reviews_headers();
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function reviews_empty(string $source = 'unavailable'): array
{
    return [
        'ok' => false,
        'source' => $source,
        'rating' => null,
        'count' => null,
        'mapsUrl' => GOOGLE_MAPS_PLACE_URL,
        'reviewsUrl' => GOOGLE_REVIEWS_URL,
        'fetchedAt' => null,
        'reviews' => [],
    ];
}

function reviews_cache_paths(): array
{
    return [
        dirname(__DIR__, 2) . '/reviews-cache/google-reviews.json',
        __DIR__ . '/cache/google-reviews.json',
        sys_get_temp_dir() . '/braga-google-reviews.json',
    ];
}

function reviews_read_cache(): ?array
{
    foreach (reviews_cache_paths() as $path) {
        if (!is_readable($path)) {
            continue;
        }
        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            continue;
        }
        $parsed = json_decode($raw, true);
        if (!is_array($parsed)) {
            continue;
        }
        $storedAt = isset($parsed['storedAt']) ? (int) $parsed['storedAt'] : 0;
        $snapshot = isset($parsed['snapshot']) && is_array($parsed['snapshot']) ? $parsed['snapshot'] : $parsed;
        if ($storedAt > 0 && (time() - $storedAt) < CACHE_TTL && reviews_has_live($snapshot)) {
            $snapshot['source'] = 'cache';
            return $snapshot;
        }
    }
    return null;
}

function reviews_write_cache(array $snapshot): void
{
    $payload = json_encode(['snapshot' => $snapshot, 'storedAt' => time()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        return;
    }
    foreach (reviews_cache_paths() as $path) {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (is_dir($dir) && is_writable($dir) && @file_put_contents($path, $payload) !== false) {
            return;
        }
    }
}

function reviews_has_live(array $snapshot): bool
{
    return isset($snapshot['rating']) || isset($snapshot['count']) || (!empty($snapshot['reviews']) && is_array($snapshot['reviews']));
}

function reviews_places_key(): ?string
{
    foreach (['GOOGLE_PLACES_API_KEY', 'GOOGLE_MAPS_API_KEY'] as $name) {
        $value = getenv($name);
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }
    return null;
}

function reviews_http_get(string $url, array $headers = []): ?array
{
    $headerLines = array_merge(['Accept: application/json'], $headers);
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        if ($curl === false) {
            return null;
        }
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_USERAGENT => 'JaxTileLVP-Reviews/1.0',
        ]);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if (!is_string($body) || $status < 200 || $status >= 300) {
            return null;
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headerLines) . "\r\n",
            'timeout' => 8,
            'ignore_errors' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    if (!is_string($body) || $body === '') {
        return null;
    }
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : null;
}

function reviews_from_places(): ?array
{
    $key = reviews_places_key();
    if ($key === null) {
        return null;
    }
    $url = 'https://places.googleapis.com/v1/places/' . rawurlencode(GOOGLE_PLACE_ID);
    $body = reviews_http_get($url, [
        'X-Goog-Api-Key: ' . $key,
        'X-Goog-FieldMask: rating,userRatingCount,googleMapsUri,reviews',
    ]);
    if ($body === null) {
        return null;
    }
    $reviews = [];
    if (!empty($body['reviews']) && is_array($body['reviews'])) {
        foreach ($body['reviews'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $text = '';
            if (isset($row['text']['text']) && is_string($row['text']['text'])) {
                $text = trim($row['text']['text']);
            } elseif (isset($row['text']) && is_string($row['text'])) {
                $text = trim($row['text']);
            }
            if ($text === '') {
                continue;
            }
            $attr = isset($row['authorAttribution']) && is_array($row['authorAttribution']) ? $row['authorAttribution'] : [];
            $reviews[] = [
                'author' => isset($attr['displayName']) && is_string($attr['displayName']) && $attr['displayName'] !== '' ? $attr['displayName'] : 'Google user',
                'text' => $text,
                'rating' => isset($row['rating']) && is_numeric($row['rating']) ? (float) $row['rating'] : null,
                'publishedAt' => isset($row['publishTime']) && is_string($row['publishTime']) ? $row['publishTime'] : null,
                'relativeTime' => isset($row['relativePublishTimeDescription']) && is_string($row['relativePublishTimeDescription']) ? $row['relativePublishTimeDescription'] : null,
                'authorUrl' => isset($attr['uri']) && is_string($attr['uri']) ? $attr['uri'] : null,
                'reviewUrl' => isset($row['googleMapsUri']) && is_string($row['googleMapsUri']) ? $row['googleMapsUri'] : null,
            ];
        }
        usort($reviews, static function ($a, $b) {
            return strcmp((string) ($b['publishedAt'] ?? ''), (string) ($a['publishedAt'] ?? ''));
        });
        $reviews = array_slice($reviews, 0, 5);
    }
    $rating = isset($body['rating']) && is_numeric($body['rating']) ? (float) $body['rating'] : null;
    $count = isset($body['userRatingCount']) && is_numeric($body['userRatingCount']) ? (int) $body['userRatingCount'] : null;
    $snapshot = [
        'ok' => true,
        'source' => 'google_places_api',
        'rating' => $rating,
        'count' => $count,
        'mapsUrl' => isset($body['googleMapsUri']) && is_string($body['googleMapsUri']) ? $body['googleMapsUri'] : GOOGLE_MAPS_PLACE_URL,
        'reviewsUrl' => GOOGLE_REVIEWS_URL,
        'fetchedAt' => gmdate('c'),
        'reviews' => $reviews,
    ];
    return reviews_has_live($snapshot) ? $snapshot : null;
}

function reviews_from_braga(): ?array
{
    $body = reviews_http_get(BRAGA_REVIEWS_API);
    if ($body === null || !reviews_has_live($body)) {
        return null;
    }
    $body['ok'] = true;
    if (empty($body['source'])) {
        $body['source'] = 'cache';
    }
    return $body;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
    reviews_headers();
    http_response_code(204);
    exit;
}
if ($method !== 'GET') {
    reviews_respond(['ok' => false, 'message' => 'Method not allowed'], 405);
}

$cached = reviews_read_cache();
if ($cached !== null) {
    reviews_respond($cached);
}

$live = reviews_from_places() ?? reviews_from_braga();
if ($live !== null) {
    reviews_write_cache($live);
    reviews_respond($live);
}

reviews_respond(reviews_empty(), 200);
