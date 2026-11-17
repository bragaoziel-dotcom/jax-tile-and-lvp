<?php
declare(strict_types=1);
// Lead email delivery health (no personal data): last verified send, last
// failure and consecutive failures, read from the private status file that
// api/lead.php writes outside the web root. Used by monitoring routines.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
$docRoot = realpath(__DIR__ . '/..');
$file = $docRoot === false ? '' : dirname($docRoot) . '/.jax-lead-log/status.json';
$st = ($file !== '' && is_file($file)) ? (json_decode((string)@file_get_contents($file), true) ?: []) : [];
$failures = (int)($st['consecutive_failures'] ?? 0);
echo json_encode([
    'ok' => $failures === 0,
    'last_ok_at' => $st['last_ok_at'] ?? null,
    'last_ok_transport' => $st['last_ok_transport'] ?? null,
    'last_fail_at' => $st['last_fail_at'] ?? null,
    'last_fail_status' => $st['last_fail_status'] ?? null,
    'last_fail_errors' => $st['last_fail_errors'] ?? [],
    'consecutive_failures' => $failures,
], JSON_UNESCAPED_SLASHES);
