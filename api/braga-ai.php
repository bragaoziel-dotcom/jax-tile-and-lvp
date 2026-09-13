<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false]); exit; }

$input = json_decode((string)file_get_contents('php://input'), true);
$message = trim((string)($input['message'] ?? ''));
if ($message === '' || mb_strlen($message) > 1000) {
    http_response_code(422);
    echo json_encode(['ok'=>false]);
    exit;
}

/*
 * Prefer the Jax-specific key, but also accept the existing Braga/host-level
 * GEMINI_API_KEY. This keeps the browser key-free while avoiding a production
 * outage when Hostinger already exposes the shared Gemini key under the
 * standard name.
 */
$key = '';
if (defined('JAX_GEMINI_API_KEY') && JAX_GEMINI_API_KEY) {
    $key = (string)JAX_GEMINI_API_KEY;
} elseif (($env = getenv('JAX_GEMINI_API_KEY')) !== false && $env !== '') {
    $key = (string)$env;
} elseif (defined('GEMINI_API_KEY') && GEMINI_API_KEY) {
    $key = (string)GEMINI_API_KEY;
} elseif (($env = getenv('GEMINI_API_KEY')) !== false && $env !== '') {
    $key = (string)$env;
}

function localFallbackReply(string $message): string {
    $m = mb_strtolower($message);

    if (preg_match('/area|serve|service area|location|city|where|zip|jacksonville|ponte vedra|nocatee|st\.? johns|st\.? augustine|orange park|fleming|mandarin/u', $m)) {
        return 'We serve Jacksonville, Jacksonville Beach, Ponte Vedra, Nocatee, St. Johns, St. Augustine, Orange Park, Fleming Island and Mandarin. What city or ZIP code is your project in?';
    }
    if (preg_match('/lvp|vinyl|luxury vinyl|spc/u', $m)) {
        return 'Yes. We install LVP, luxury vinyl plank, vinyl plank and SPC flooring, including removal and floor preparation when needed. About how many square feet is the project?';
    }
    if (preg_match('/tile|porcelain|ceramic|backsplash|shower/u', $m)) {
        return 'Yes. We install porcelain and ceramic tile for floors, walls, backsplashes and showers, including preparation and waterproofing when part of the tile project. What type of tile project do you have?';
    }
    if (preg_match('/price|cost|how much|estimate|quote/u', $m)) {
        return 'Estimates are free. Final pricing depends on the material, square footage, existing floor and preparation required, so firm quotes need an on-site estimate. Is your project tile or LVP/vinyl?';
    }
    if (preg_match('/phone|call|contact|number/u', $m)) {
        return 'You can call or text Jax Tile & LVP at (904) 520-1994. Is your project tile or LVP/vinyl?';
    }
    if (preg_match('/portugu[eê]s|fala portugu[eê]s|pt-br/u', $m)) {
        return 'Sim. Atendemos em português para projetos de piso vinílico/LVP e instalação de tile. Seu projeto é de LVP/vinil ou tile?';
    }

    return 'I can help with LVP/vinyl flooring, tile installation, service areas and free estimates. Is your project tile or LVP/vinyl?';
}

$system = <<<PROMPT
You are Braga AI Assistant for Jax Tile & LVP by Braga Remodeling in Jacksonville, Florida. You only discuss and qualify two categories: (1) LVP, luxury vinyl plank, vinyl plank and SPC flooring; (2) porcelain or ceramic tile installation, including tile floors, walls, backsplashes and shower tile. Supporting work such as removing existing flooring, subfloor preparation, leveling, transitions, trim and wet-area waterproofing may be discussed only as part of a tile or vinyl project. Politely decline unrelated remodeling, pavers, painting, roofing, plumbing, jobs, wholesale inquiries and DIY-only requests, and bring the visitor back to tile or vinyl. Service areas: Jacksonville, Jacksonville Beach, Ponte Vedra, Nocatee, St. Johns, St. Augustine, Orange Park, Fleming Island and Mandarin. Phone: (904) 520-1994. Estimates are free. Never invent prices, availability, licenses, insurance, warranties, ratings or completion dates. Ask exactly one short qualifying question per reply. Collect service, approximate square footage, existing flooring, removal/preparation needs, ZIP or city, first name and phone. Email is optional. Do not claim information was submitted; direct the visitor to the estimate form or phone once ready. Natural American English, friendly and concise. State clearly that you are an AI if asked.
PROMPT;

$history = [];
foreach (array_slice((array)($input['history'] ?? []), -12) as $m) {
    $role = ($m['role'] ?? '') === 'assistant' ? 'model' : 'user';
    $text = mb_substr(trim((string)($m['content'] ?? '')), 0, 1000);
    if ($text !== '') $history[] = ['role'=>$role, 'parts'=>[['text'=>$text]]];
}

/* Keep the chat useful even if the host-level Gemini secret is temporarily absent. */
if ($key === '') {
    error_log('[jax-braga-ai] Gemini key not available; serving safe local fallback');
    echo json_encode(['ok'=>true, 'degraded'=>true, 'reply'=>localFallbackReply($message)], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = [
    'system_instruction'=>['parts'=>[['text'=>$system]]],
    'contents'=>$history ?: [['role'=>'user','parts'=>[['text'=>$message]]]],
    'generationConfig'=>['temperature'=>0.35,'maxOutputTokens'=>220]
];

$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . rawurlencode($key);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST=>true,
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_TIMEOUT=>20,
    CURLOPT_CONNECTTIMEOUT=>8,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
    CURLOPT_POSTFIELDS=>json_encode($payload),
]);
$raw = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

$data = json_decode((string)$raw, true);
$reply = trim((string)($data['candidates'][0]['content']['parts'][0]['text'] ?? ''));

if ($status < 200 || $status >= 300 || $reply === '') {
    error_log('[jax-braga-ai] Gemini request failed status=' . $status . ($curlError ? ' curl=' . $curlError : ''));
    echo json_encode(['ok'=>true, 'degraded'=>true, 'reply'=>localFallbackReply($message)], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok'=>true,'reply'=>$reply], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
