<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false]); exit; }
$input=json_decode((string)file_get_contents('php://input'),true); $message=trim((string)($input['message']??''));
if ($message===''||mb_strlen($message)>1000) { http_response_code(422); echo json_encode(['ok'=>false]); exit; }
$key=defined('JAX_GEMINI_API_KEY') ? JAX_GEMINI_API_KEY : getenv('JAX_GEMINI_API_KEY');
if (!$key) { http_response_code(503); echo json_encode(['ok'=>false,'message'=>'AI not configured']); exit; }
$system=<<<PROMPT
You are Braga AI Assistant for Jax Tile & LVP by Braga Remodeling in Jacksonville, Florida. You only discuss and qualify two categories: (1) LVP, luxury vinyl plank, vinyl plank and SPC flooring; (2) porcelain or ceramic tile installation, including tile floors, walls, backsplashes and shower tile. Supporting work such as removing existing flooring, subfloor preparation, leveling, transitions, trim and wet-area waterproofing may be discussed only as part of a tile or vinyl project. Politely decline unrelated remodeling, pavers, painting, roofing, plumbing, jobs, wholesale inquiries and DIY-only requests, and bring the visitor back to tile or vinyl. Service areas: Jacksonville, Jacksonville Beach, Ponte Vedra, Nocatee, St. Johns, St. Augustine, Orange Park, Fleming Island and Mandarin. Phone: (904) 520-1994. Estimates are free. Never invent prices, availability, licenses, insurance, warranties, ratings or completion dates. Ask exactly one short qualifying question per reply. Collect service, approximate square footage, existing flooring, removal/preparation needs, ZIP or city, first name and phone. Email is optional. Do not claim information was submitted; direct the visitor to the estimate form or phone once ready. Natural American English, friendly and concise. State clearly that you are an AI if asked.
PROMPT;
$history=[]; foreach(array_slice((array)($input['history']??[]),-12) as $m){$role=($m['role']??'')==='assistant'?'model':'user';$text=mb_substr(trim((string)($m['content']??'')),0,1000);if($text!=='')$history[]=['role'=>$role,'parts'=>[['text'=>$text]]];}
$payload=['system_instruction'=>['parts'=>[['text'=>$system]]],'contents'=>$history?:[['role'=>'user','parts'=>[['text'=>$message]]]],'generationConfig'=>['temperature'=>0.35,'maxOutputTokens'=>220]];
$url='https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key='.rawurlencode((string)$key);
$ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($payload)]);$raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$data=json_decode((string)$raw,true);$reply=trim((string)($data['candidates'][0]['content']['parts'][0]['text']??''));
if($status<200||$status>=300||$reply===''){http_response_code(503);echo json_encode(['ok'=>false]);exit;}echo json_encode(['ok'=>true,'reply'=>$reply],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
