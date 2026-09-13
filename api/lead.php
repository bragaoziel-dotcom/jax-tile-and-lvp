<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'message'=>'Method not allowed']); exit; }
if (!empty($_POST['website'] ?? '')) { echo json_encode(['ok'=>true,'trackConversion'=>false]); exit; }
function clean(string $key, int $max=500): string { return mb_substr(trim(strip_tags((string)($_POST[$key] ?? ''))),0,$max); }
function line(string $value): string { return str_replace(["\r","\n"],' ',trim($value)); }
$name=clean('name',100); $phone=clean('phone',30); $email=line(clean('email',150)); $zip=clean('zip',10); $service=clean('service',80); $details=clean('details',1200); $page=clean('page',180);
$allowed=['Luxury Vinyl Plank / Vinyl','Tile Installation'];
if ($name==='' || !in_array($service,$allowed,true) || preg_match_all('/\d/',$phone)<10 || ($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))) { http_response_code(422); echo json_encode(['ok'=>false,'message'=>'Please check your name, phone, email and service.']); exit; }
$fingerprint=hash('sha256',strtolower($phone.'|'.$service.'|'.$details)); $cache=sys_get_temp_dir().'/jax-lead-'.$fingerprint;
if (is_file($cache) && time()-filemtime($cache)<600) { echo json_encode(['ok'=>true,'duplicate'=>true,'trackConversion'=>false]); exit; }
$tracking=[]; foreach(['utm_source','utm_medium','utm_campaign','utm_term','utm_content','gclid','gbraid','wbraid'] as $key){$value=clean($key,180);if($value!=='')$tracking[]=$key.': '.$value;}
$subject='New Jax Tile & LVP lead: '.$service;
$body="Name: $name\nPhone: $phone\nEmail: $email\nZIP: $zip\nService: $service\nPage: $page\nDetails: $details\nSource: jaxtileandlvp.com".($tracking?"\n\nAttribution:\n".implode("\n",$tracking):'');
$headers="From: Jax Tile & LVP <wordpress@jaxtileandlvp.com>\r\n".($email!==''?'Reply-To: '.$email."\r\n":'');
$sent=mail('braga@bragaremodeling.com',$subject,$body,$headers);
if (!$sent) { http_response_code(503); echo json_encode(['ok'=>false,'message'=>'Unable to send right now.']); exit; }
touch($cache); echo json_encode(['ok'=>true,'trackConversion'=>true]);
