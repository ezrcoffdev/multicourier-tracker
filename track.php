<?php
/**
 * track.php
 * ----------------------------------------------------------------------
 * Основна логика за:
 *  - приемане на вход (номер на пратка, избор на куриер);
 *  - автоматично разпознаване на куриера по шаблон;
 *  - кеширане на резултатите (10 мин) в системната temp директория;
 *  - извикване на API на Speedy / Econt / BOX NOW (server-side cURL);
 *  - безопасно рендиране на статуса/историята в семпъл HTML.
 *
 * Архитектура (минимална, без фреймуърк):
 *  - Всички специфики за достъп и endpoints са в access.inc.php
 *  - Креденшъли/настройки са в config.inc.php
 *  - Тук има само glue-code и мапинг на отговорите към унифициран формат.
 *
 * Сигурност:
 *  - sanitize на входа (допускаме латиница/цифри/тире, и то с лимит);
 *  - без ключове във фронтенда;
 *  - graceful fallback при грешки (човешки съобщения, официални линкове).
 */

/* Clean tracker for Speedy & Econt only (server-side fetch + simple rendering) */
require_once($_SERVER['DOCUMENT_ROOT'].'/access.inc.php');
require_once($_SERVER['DOCUMENT_ROOT'].'/config.inc.php');

/* --- language --- */
$lang = (isset($_GET['lang']) && $_GET['lang'] === 'en') ? 'en' : (defined('LANGUAGE_DEFAULT') ? LANGUAGE_DEFAULT : 'bg');

/* --- input (GET p=trackingNo or form POST) --- */
$tn = '';
if (isset($_GET['p'])) { $tn = substr(preg_replace('/[^A-Za-z0-9]/','', $_GET['p']), 0, 30); }
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['tn'])) { $tn = substr(preg_replace('/[^A-Za-z0-9]/','', $_POST['tn']), 0, 30); }

/* --- detect courier --- */
$courier = '';
if (preg_match(PATTERN_SPEEDY, $tn)) $courier = 'speedy';
if (preg_match(PATTERN_ECONT,  $tn)) $courier = 'econt';
if (preg_match(PATTERN_BOXNOW, $tn)) $courier = $courier ?: 'boxnow';

/* fallback explicit courier param */
if (isset($_GET['c']) && in_array($_GET['c'], ['speedy','econt'], true)) $courier = $_GET['c'];

/* form if no tn */
if (!$tn) {
  echo '<form method="post" class="ezar-form"><label>Номер на пратка</label><input name="tn" required placeholder="напр. 1234567890"><label>Куриер</label><select name="c"><option value="speedy">Speedy</option><option value="econt">Econt</option></select><button type="submit">Провери</button></form>';
  exit;
}
if (!$courier) {
  echo '<div class="ezar-card error">Не можем да разпознаем куриера по този номер. <a href="'.htmlspecialchars(SITE_CONTACT_URL).'">Свържете се с нас</a>.</div>';
  exit;
}

/* --- caching (10 min) --- */
$cacheDir = sys_get_temp_dir();
$cacheKey = 'ezartrk_'.md5($courier.'|'.$tn.'|'.$lang);
$cacheFile = $cacheDir.'/'.$cacheKey.'.json';
if (is_file($cacheFile) && (time()-filemtime($cacheFile) < 600)) {
  $data = json_decode(file_get_contents($cacheFile), true);
} else {
  if ($courier==='speedy') $data = ezar_fetch_speedy($tn, $lang);
  if ($courier==='econt')  $data = ezar_fetch_econt($tn,  $lang);
  if ($courier==='boxnow') $data = ezar_fetch_boxnow($tn, $lang);
  if (!$data || !isset($data['ok'])) $data = ['ok'=>false,'error'=>'Възникна грешка. Опитайте отново по-късно.'];
  @file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE));
}

/* --- render --- */
echo '<div class="ezar-card">';
echo '<div class="head"><strong>'.strtoupper($courier).'</strong> • '.htmlspecialchars($tn).'</div>';
if (!$data['ok']) {
  echo '<div class="status">'.htmlspecialchars($data['error']).'</div>';
  echo '</div>'; exit;
}
echo '<div class="status">'.htmlspecialchars($data['status']).'</div>';
if (!empty($data['last'])) echo '<div class="last">'.htmlspecialchars($data['last']).'</div>';
if (!empty($data['history']) && is_array($data['history'])) {
  echo '<ul class="hist">';
  foreach ($data['history'] as $row) {
    $t = isset($row['time']) ? $row['time'] : '';
    $ev= isset($row['event'])? $row['event']: '';
    $loc=isset($row['place'])? $row['place']: '';
    echo '<li><span class="t">'.htmlspecialchars($t).'</span> — '.htmlspecialchars($ev).($loc? ' · '.htmlspecialchars($loc):'').'</li>';
  }
  echo '</ul>';
}
if (!empty($data['official'])) {
  echo '<div class="actions"><a target="_blank" rel="nofollow" href="'.htmlspecialchars($data['official']).'">Виж в сайта на куриера</a></div>';
}
echo '</div>';

/* ================= helpers ================ */
/**
 * Взема статус от Speedy API и нормализира резултата.
 * @param string $tn   номер на пратка
 * @param string $lang 'bg' или 'en'
 * @return array {ok:bool, status:string, last:string, history:array, official:string|empty}
 */
function ezar_fetch_speedy($tn, $lang){
  if (!defined('SPEEDY_USER') || !defined('SPEEDY_PASS') || SPEEDY_USER==="" || SPEEDY_PASS==="") {
    return ['ok'=>false,'error'=>'Липсват Speedy API креденшъли в config.inc.php'];
  }
  $url = rtrim(SPEEDY_API_BASE,'/').'/'.SPEEDY_API_CMD_TRACK;
  $payload = json_encode(['username'=>SPEEDY_USER,'password'=>SPEEDY_PASS,'parcelIds'=>[$tn],'language'=>$lang==='en'?'en':'bg'], JSON_UNESCAPED_UNICODE);
  $resp = ezar_curl_json($url, $payload);
  if ($resp['status']!==200) return ['ok'=>false,'error'=>'Speedy API грешка (HTTP '.$resp['status'].')'];
  $body = json_decode($resp['body'], true);
  if (!$body || empty($body['parcels'])) return ['ok'=>false,'error'=>'Няма данни за тази пратка в Speedy.'];
  $p = $body['parcels'][0];
  $hist = [];
  // Map operations
  if (!empty($p['operations'])) {
    foreach ($p['operations'] as $op) {
      $hist[] = [
        'time'  => isset($op['operationTime']) ? date('d.m.Y H:i', strtotime($op['operationTime'])) : '',
        'event' => isset($op['operationComment']) ? $op['operationComment'] : (isset($op['operationCode'])?$op['operationCode']:''),
        'place' => isset($op['operationSiteName']) ? $op['operationSiteName'] : '',
      ];
    }
  }
  $status = isset($p['status']) ? $p['status'] : (isset($hist[0]['event'])?$hist[0]['event']:'В процес');
  $last   = !empty($hist) ? ($hist[0]['event'].' — '.$hist[0]['time']) : '';
  return [
    'ok'=>true,
    'status'=>$status,
    'last'=>$last,
    'history'=>$hist,
    'official'=>'https://www.speedy.bg/bg/track?shipmentNumber='.rawurlencode($tn),
  ];
}

/**
 * Взема статус от Econt (JSON-RPC) и нормализира резултата.
 * Бележка: Структурата леко варира; защитени проверки.
 */
function ezar_fetch_econt($tn, $lang){
  $url = rtrim(ECONT_API_BASE,'/').'/'.ECONT_API_CMD_TRACK;
  $payloadArr = [
    "id"=>1,
    "jsonrpc"=>"2.0",
    "method"=>"getStatuses",
    "params"=>["shipmentNumbers"=>[$tn], "language"=>$lang]
  ];
  $payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);
  // Because Econt uses JSON-RPC 2.0 endpoints; some setups require POST with specific headers.
  $resp = ezar_curl_json($url, $payload, ['Content-Type: application/json']);
  if ($resp['status']!==200) return ['ok'=>false,'error'=>'Econt API грешка (HTTP '.$resp['status'].')'];
  $body = json_decode($resp['body'], true);
  if (!$body) return ['ok'=>false,'error'=>'Неуспешен отговор от Econt.'];
  // Simplify mapping (structure varies). Try common layout:
  $hist = [];
  $status = 'В процес';
  $res = isset($body['result']) ? $body['result'] : $body;
  if (isset($res['shipments'][0])) {
    $s = $res['shipments'][0];
    if (!empty($s['events'])) {
      foreach ($s['events'] as $ev) {
        $hist[] = [
          'time'  => isset($ev['time']) ? date('d.m.Y H:i', strtotime($ev['time'])) : '',
          'event' => isset($ev['description']) ? $ev['description'] : '',
          'place' => isset($ev['location']) ? $ev['location'] : '',
        ];
      }
      $status = $hist[0]['event'] ?? $status;
    }
  }
  $last = !empty($hist) ? ($hist[0]['event'].' — '.$hist[0]['time']) : '';
  return [
    'ok'=>true,
    'status'=>$status,
    'last'=>$last,
    'history'=>$hist,
    'official'=>'https://www.econt.com/services/track-shipment?num='.rawurlencode($tn),
  ];
}

/**
 * Унифицирана POST заявка с JSON payload.
 * @return array ['status'=>int,'body'=>string,'error'?:string]
 */
function ezar_curl_json($url, $payload, $headers=['Content-Type: application/json']){
  $ch = curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>$payload,
    CURLOPT_HTTPHEADER=>$headers,
    CURLOPT_TIMEOUT=>15,
  ]);
  $body = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  if ($body===false) { $err = curl_error($ch); curl_close($ch); return ['status'=>0,'body'=>'','error'=>$err]; }
  curl_close($ch);
  return ['status'=>$status,'body'=>$body];
}


function ezar_fetch_boxnow($tn, $lang){
  if (!defined('BOXNOW_CLIENT_ID') || BOXNOW_CLIENT_ID==="" || !defined('BOXNOW_CLIENT_SECRET') || BOXNOW_CLIENT_SECRET==="") {
    return ['ok'=>false,'error'=>'Липсват BOX NOW OAuth данни (client_id/client_secret) в config.inc.php'];
  }
  if (!defined('BOXNOW_API_BASE') || BOXNOW_API_BASE==="") {
    return ['ok'=>false,'error'=>'Липсва BOX NOW API базов URL (BOXNOW_API_BASE)'];
  }
  $token = ezar_boxnow_token();
  if (!$token) return ['ok'=>false,'error'=>'Неуспешна автентикация към BOX NOW'];
  $url = rtrim(BOXNOW_API_BASE, '/').BOXNOW_PARCELS_PATH.'?q='.rawurlencode($tn);
  $ch = curl_init($url);
  curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token, 'Accept: application/json'],
    CURLOPT_TIMEOUT=>15,
  ]);
  $body = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  if ($body===false){ $err=curl_error($ch); curl_close($ch); return ['ok'=>false,'error'=>'BOX NOW грешка: '.$err]; }
  curl_close($ch);
  if ($status!==200){ return ['ok'=>false,'error'=>'BOX NOW API грешка (HTTP '.$status.')']; }
  $js = json_decode($body, true);
  if (!$js){ return ['ok'=>false,'error'=>'Неуспешен JSON отговор от BOX NOW']; }
  $data = isset($js['data']) ? $js['data'] : $js;
  $parcel = is_array($data) ? (isset($data[0]) ? $data[0] : $data) : $data;
  $hist = [];
  if (!empty($parcel['events']) && is_array($parcel['events'])){
    foreach ($parcel['events'] as $ev){
      $hist[] = [
        'time'  => isset($ev['time']) ? date('d.m.Y H:i', strtotime($ev['time'])) : '',
        'event' => isset($ev['type']) ? $ev['type'] : (isset($ev['displayName'])?$ev['displayName']:''),
        'place' => isset($ev['locationId']) ? $ev['locationId'] : '',
      ];
    }
    usort($hist, function($a,$b){ return strcmp($b['time'],$a['time']); });
  }
  $statusText = $hist ? $hist[0]['event'] : (isset($parcel['state'])?$parcel['state']:'В процес');
  $last = $hist ? ($hist[0]['event'].' — '.$hist[0]['time']) : '';
  return [
    'ok'=>true,
    'status'=>$statusText,
    'last'=>$last,
    'history'=>$hist,
    'official'=>BOXNOW_TRACK_PUBLIC
  ];
}

function ezar_boxnow_token(){
  $cacheFile = sys_get_temp_dir().'/ezar_boxnow_token.json';
  if (is_file($cacheFile)){
    $c = json_decode(file_get_contents($cacheFile), true);
    if ($c && !empty($c['access_token']) && !empty($c['expires_at']) && $c['expires_at'] > time()+60){
      return $c['access_token'];
    }
  }
  $url = rtrim(BOXNOW_API_BASE, '/').BOXNOW_OAUTH_PATH;
  $payload = json_encode([
    'grant_type'=>'client_credentials',
    'client_id'=>BOXNOW_CLIENT_ID,
    'client_secret'=>BOXNOW_CLIENT_SECRET
  ], JSON_UNESCAPED_UNICODE);
  $resp = ezar_curl_json($url, $payload, ['Content-Type: application/json','Accept: application/json']);
  if ($resp['status']!==200){ return null; }
  $body = json_decode($resp['body'], true);
  $token = $body['accessToken'] ?? null;
  if (!$token){ return null; }
  $ttl = isset($body['expiresIn']) ? intval($body['expiresIn']) : 3300;
  file_put_contents($cacheFile, json_encode(['access_token'=>$token, 'expires_at'=>time()+$ttl]));
  return $token;
}
