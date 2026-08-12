<?php
header('Content-Type: application/json');
$u='aromas'; $p='Fuhrfuhr300';
$auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
$isAdmin = ($auth === 'Basic '.base64_encode("$u:$p"));
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
$action = isset($_GET['action']) ? $_GET['action'] : (isset($data['action']) ? $data['action'] : 'publish');
function jload($f,$d){ return is_file($f) ? json_decode(file_get_contents($f), true) : $d; }
function jsave($f,$v){ file_put_contents($f, json_encode($v, JSON_UNESCAPED_UNICODE)); }
function need($b){ if(!$b){ http_response_code(401); exit('{"e":401}'); } }

switch($action){
case 'hit': case 'wa':
  $f='visitas.json'; $v=jload($f, array('v'=>0,'wa'=>0,'days'=>array()));
  $d=date('Ymd'); if(!isset($v['days'][$d])) $v['days'][$d]=array('v'=>0,'wa'=>0);
  if($action=='hit'){ $v['v']++; $v['days'][$d]['v']++; } else { $v['wa']++; $v['days'][$d]['wa']++; }
  jsave($f,$v); echo '{"ok":1}'; break;

case 'contact':
  $n = isset($data['name']) ? strip_tags($data['name']) : '';
  $m = isset($data['mail']) ? strip_tags($data['mail']) : '';
  $t = isset($data['msg'])  ? strip_tags($data['msg'])  : '';
  $body = "Nombre: $n\nEmail: $m\nTel: ".(isset($data['phone'])?$data['phone']:'')."\nTipo: ".(isset($data['type'])?$data['type']:'')."\nFechas: ".(isset($data['from'])?$data['from']:'')." a ".(isset($data['to'])?$data['to']:'')."\n\n$t";
  @mail('info@complejoaromas.com.ar', 'Consulta web de '.$n, $body, "From: web@".$_SERVER['SERVER_NAME']."\r\nContent-Type: text/plain; charset=utf-8");
  $c = jload('consultas.json', array());
  array_unshift($c, array('id'=>uniqid(), 'date'=>date('Y-m-d H:i'), 'name'=>$n, 'mail'=>$m, 'phone'=>isset($data['phone'])?$data['phone']:'', 'type'=>isset($data['type'])?$data['type']:'', 'from'=>isset($data['from'])?$data['from']:'', 'to'=>isset($data['to'])?$data['to']:'', 'msg'=>$t, 'status'=>'pendiente'));
  jsave('consultas.json', array_slice($c,0,200));
  echo '{"ok":1}'; break;

case 'mark': need($isAdmin);
  $c = jload('consultas.json', array());
  foreach($c as &$it){ if($it['id']===$data['id']) $it['status'] = ($it['status']==='pendiente')?'respondida':'pendiente'; }
  jsave('consultas.json',$c); echo '{"ok":1}'; break;

case 'consultas': need($isAdmin); echo json_encode(jload('consultas.json', array()), JSON_UNESCAPED_UNICODE); break;
case 'stats':     need($isAdmin); echo json_encode(jload('visitas.json', array('v'=>0,'wa'=>0,'days'=>array())), JSON_UNESCAPED_UNICODE); break;
case 'backups':   need($isAdmin); $b=glob('content.bak.*.json'); rsort($b); echo json_encode(array_slice($b,0,10)); break;
case 'restore':   need($isAdmin);
  $f = basename($data['file']); if(is_file($f) && is_file('content.json')){ copy('content.json','content.bak.'.date('Ymd-His').'.json'); copy($f,'content.json'); }
  echo '{"ok":1}'; break;

default: /* publish */
  need($isAdmin);
  file_put_contents('content.json', $raw);
  file_put_contents('content.bak.'.date('Ymd-His').'.json', $raw);
  $b = glob('content.bak.*.json'); rsort($b); while(count($b) > 10) unlink(array_shift($b));
  $seo = isset($data['seo']) ? $data['seo'] : null;
  if($seo && is_file('index.html')){
    $h = file_get_contents('index.html');
    if(!empty($seo['title'])) $h = preg_replace('/<title[^>]*>.*?<\/title>/s', '<title>'.htmlspecialchars($seo['title'], ENT_QUOTES).'</title>', $h, 1);
    if(!empty($seo['desc']))  $h = preg_replace('/<meta name="description" content="[^"]*"\s*\/?>/', '<meta name="description" content="'.htmlspecialchars($seo['desc'], ENT_QUOTES).'">', $h, 1);
    if(!empty($seo['og']))    $h = preg_replace('/<meta property="og:image" content="[^"]*"\s*\/?>/', '<meta property="og:image" content="'.htmlspecialchars($seo['og'], ENT_QUOTES).'">', $h, 1);
    file_put_contents('index.html', $h);
  }
  echo '{"ok":1}';
}