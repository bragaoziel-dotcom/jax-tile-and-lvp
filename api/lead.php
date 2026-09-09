<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'message'=>'Method not allowed']); exit; }
if (!empty($_POST['website'] ?? '')) { echo json_encode(['ok'=>true,'trackConversion'=>false]); exit; }
function clean(string $key, int $max=500): string { return mb_substr(trim(strip_tags((string)($_POST[$key] ?? ''))),0,$max); }
$name=clean('name',100); $phone=clean('phone',30); $email=clean('email',150); $zip=clean('zip',10); $service=clean('service',80); $details=clean('details',1200);
$allowed=['Luxury Vinyl Plank / Vinyl','Tile Installation'];
if ($name==='' || !in_array($service,$allowed,true) || preg_match_all('/\d/',$phone)<10 || ($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))) { http_response_code(422); echo json_encode(['ok'=>false,'message'=>'Please check your name, phone, email and service.']); exit; }
$fingerprint=hash('sha256',strtolower($phone.'|'.$service.'|'.$details)); $cache=sys_get_temp_dir().'/jax-lead-'.$fingerprint;
if (is_file($cache) && time()-filemtime($cache)<600) { echo json_encode(['ok'=>true,'duplicate'=>true,'trackConversion'=>false]); exit; }
$subject='New Jax Tile & LVP lead: '.$service;
$body="Name: $name\nPhone: $phone\nEmail: $email\nZIP: $zip\nService: $service\nDetails: $details\nSource: jaxtileandlvp.com";
$headers="From: Jax Tile & LVP <wordpress@jaxtileandlvp.com>\r\n".($email!==''?'Reply-To: '.$email."\r\n":'');
$sent=mail('braga@bragaremodeling.com',$subject,$body,$headers);
if (!$sent) { http_response_code(503); echo json_encode(['ok'=>false,'message'=>'Unable to send right now.']); exit; }
touch($cache); echo json_encode(['ok'=>true,'trackConversion'=>true]);
