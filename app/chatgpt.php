<?php
// Official local open-source ChatGPT plan flow. Never use browser session tokens.
function chatgpt_dir():string {
 $dir=dirname(__DIR__).'/storage/chatgpt';
 if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Cannot create private ChatGPT storage.');
 return $dir;
}
function chatgpt_write(string $path,array $value):void {
 $tmp=$path.'.'.bin2hex(random_bytes(6)).'.tmp';
 if(file_put_contents($tmp,json_encode($value,JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Cannot save ChatGPT connection.');
 chmod($tmp,0600);if(!rename($tmp,$path)){unlink($tmp);throw new RuntimeException('Cannot save ChatGPT connection.');}
}
function chatgpt_record(int $user):array {
 $path=chatgpt_dir().'/user-'.$user.'.json';
 return is_file($path)?json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR):[];
}
function chatgpt_http(string $url,?array $form=null,?string $token=null):array {
 $ch=curl_init($url);$headers=['Accept: application/json'];if($token)$headers[]='Authorization: Bearer '.$token;
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>25,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_HTTPHEADER=>$headers]);
 $ca=dirname(__DIR__).'/storage/certs/cacert.pem';if(is_file($ca))curl_setopt($ch,CURLOPT_CAINFO,$ca);
 if($form!==null)curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($form)]);
 $raw=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 if($raw===false)throw new RuntimeException('Could not connect to ChatGPT. Check Internet and certificate settings.');
 if($status<200||$status>=300)throw new RuntimeException('ChatGPT connection was rejected (HTTP '.$status.'). Reconnect in Settings; check plan eligibility and app permissions.');
 return json_decode($raw,true,512,JSON_THROW_ON_ERROR);
}
function chatgpt_identity(string $jwt,array $jwks,string $client,string $nonce):array {
 require_once dirname(__DIR__).'/vendor/autoload.php';
 $keys=\Firebase\JWT\JWK::parseKeySet($jwks,'RS256');
 // Restrict algorithms even if the provider publishes additional key types.
 $keys=array_filter($keys,fn($k)=>$k->getAlgorithm()==='RS256');
 \Firebase\JWT\JWT::$leeway=5;
 $claims=(array)\Firebase\JWT\JWT::decode($jwt,$keys);
 if(($claims['iss']??'')!=='https://auth.openai.com'||!in_array($client,(array)($claims['aud']??[]),true)||empty($claims['sub'])||empty($claims['exp'])||empty($claims['iat'])||!hash_equals($nonce,(string)($claims['nonce']??'')))throw new RuntimeException('ChatGPT identity validation failed. Start sign-in again.');
 return $claims;
}
function chatgpt_start():never {
 if(!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true))throw new RuntimeException('ChatGPT plan sign-in must run on this WAMP PC.');
 $user=(int)$_SESSION['user']['id'];$old=chatgpt_record($user);
 $hostPath=chatgpt_dir().'/host.json';
 if(!is_file($hostPath)){
  $bytes=random_bytes(16);$bytes[6]=chr((ord($bytes[6])&15)|64);$bytes[8]=chr((ord($bytes[8])&63)|128);$hex=bin2hex($bytes);
  chatgpt_write($hostPath,['id'=>'urn:uuid:'.substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20)]);
 }
 $host=json_decode(file_get_contents($hostPath),true)['id'];
 $state=bin2hex(random_bytes(32));$verifier=rtrim(strtr(base64_encode(random_bytes(48)),'+/','-_'),'=');
 $base=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'])),'/');
 $callback='http://127.0.0.1:'.(int)($_SERVER['SERVER_PORT']??80).$base.'/chatgpt.php';
 $pending=['user'=>$user,'expires'=>time()+600,'state'=>$state,'nonce'=>bin2hex(random_bytes(32)),'verifier'=>$verifier,'callback'=>$callback,'host'=>$host,'client'=>$old['client_id']??'dynamic_agent_client','subject'=>$old['subject']??null,'cookie'=>bin2hex(random_bytes(32))];
 chatgpt_write(chatgpt_dir().'/pending-'.$state.'.json',$pending);
 header('Location: '.$callback.'?begin='.$state);exit;
}
function chatgpt_pending(string $state):array {
 if(!preg_match('/^[a-f0-9]{64}$/',$state))throw new RuntimeException('Invalid sign-in state.');
 $path=chatgpt_dir().'/pending-'.$state.'.json';
 if(!is_file($path))throw new RuntimeException('Sign-in expired or already used. Start again in Settings.');
 $p=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
 if($p['expires']<time())throw new RuntimeException('Sign-in expired. Start again in Settings.');
 return $p;
}
function chatgpt_callback():never {
 header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');
 if(!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)){http_response_code(403);exit;}
 try{
  if(isset($_GET['begin'])){
   $p=chatgpt_pending((string)$_GET['begin']);
   // Bind loopback callback to the browser that began the sign-in.
   setcookie('aw_chatgpt',$p['cookie'],['expires'=>time()+600,'path'=>parse_url($p['callback'],PHP_URL_PATH),'httponly'=>true,'samesite'=>'Lax']);
   $args=['client_id'=>$p['client'],'ext_agent_host_id'=>$p['host'],'response_type'=>'code','redirect_uri'=>$p['callback'],'scope'=>'openid profile email offline_access resource.invoke chatgpt.tokens.use.direct','resource'=>'https://api.openai.com/v1','state'=>$p['state'],'nonce'=>$p['nonce'],'code_challenge_method'=>'S256','code_challenge'=>rtrim(strtr(base64_encode(hash('sha256',$p['verifier'],true)),'+/','-_'),'=')];
   if($p['client']==='dynamic_agent_client')$args['agent_name_hint']='Aini Wear Production Manager';
   header('Location: https://auth.openai.com/api/accounts/authorize?'.http_build_query($args));exit;
  }
  $p=chatgpt_pending((string)($_GET['state']??''));
  if(!hash_equals($p['cookie'],(string)($_COOKIE['aw_chatgpt']??'')))throw new RuntimeException('Sign-in browser did not match. Start again in Settings.');
  // Atomic one-time consumption prevents callback replay.
  $path=chatgpt_dir().'/pending-'.$p['state'].'.json';$used=$path.'.used';
  if(!rename($path,$used))throw new RuntimeException('Sign-in was already used.');unlink($used);
  setcookie('aw_chatgpt','',['expires'=>1,'path'=>parse_url($p['callback'],PHP_URL_PATH),'httponly'=>true,'samesite'=>'Lax']);
  if(isset($_GET['error']))throw new RuntimeException('ChatGPT sign-in or plan permission was declined. Return to Settings to try again.');
  $client=(string)($_GET['client_id']??$p['client']);
  if($client==='dynamic_agent_client'||$client===''||($p['client']!=='dynamic_agent_client'&&$client!==$p['client']))throw new RuntimeException('ChatGPT registration did not return the expected client ID.');
  $t=chatgpt_http('https://auth.openai.com/api/accounts/oauth/token',['grant_type'=>'authorization_code','client_id'=>$client,'code'=>(string)($_GET['code']??''),'code_verifier'=>$p['verifier'],'redirect_uri'=>$p['callback'],'resource'=>'https://api.openai.com/v1']);
  $identity=chatgpt_identity($t['id_token']??'',chatgpt_http('https://auth.openai.com/.well-known/jwks.json'),$client,$p['nonce']);
  if($p['subject']!==null&&$p['subject']!==$identity['sub'])throw new RuntimeException('The signed-in ChatGPT account differs from your saved account.');
  $scopes=explode(' ',$t['scope']??'');
  if(!in_array('chatgpt.tokens.use.direct',$scopes,true)||empty($t['access_token'])||empty($t['refresh_token']))throw new RuntimeException('ChatGPT plan usage was not authorized. Enable plan access during consent.');
  $record=['client_id'=>$client,'subject'=>$identity['sub'],'email'=>$identity['email']??'ChatGPT account','host'=>$p['host'],'access_token'=>$t['access_token'],'refresh_token'=>$t['refresh_token'],'id_token'=>$t['id_token'],'scopes'=>$scopes,'expires_at'=>time()+(int)($t['expires_in']??3600)];
  chatgpt_write(chatgpt_dir().'/user-'.$p['user'].'.json',$record);
  $message='ChatGPT connected. Return to the Aini Wear tab, select ChatGPT subscription in Settings, and save. No API key is needed.';
 }catch(Throwable $e){http_response_code(400);$message=$e instanceof \Firebase\JWT\SignatureInvalidException?'ChatGPT identity could not be verified. Start again in Settings.':$e->getMessage();}
 header('Content-Type: text/html; charset=utf-8');echo '<!doctype html><title>Aini Wear · ChatGPT connection</title><h1>Aini Wear</h1><p>'.htmlspecialchars($message,ENT_QUOTES,'UTF-8').'</p>';exit;
}
function chatgpt_access(int $user):string {
 $lock=fopen(chatgpt_dir().'/user-'.$user.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Could not lock ChatGPT connection.');
 try{
  $r=chatgpt_record($user);if(empty($r['access_token']))throw new RuntimeException('Open Settings and Continue with ChatGPT first.');
  if($r['expires_at']<time()+60){
   $t=chatgpt_http('https://auth.openai.com/api/accounts/oauth/token',['grant_type'=>'refresh_token','client_id'=>$r['client_id'],'refresh_token'=>$r['refresh_token'],'resource'=>'https://api.openai.com/v1']);
   if(empty($t['access_token'])||empty($t['refresh_token']))throw new RuntimeException('ChatGPT renewal failed. Reconnect in Settings.');
   $r=array_replace($r,['access_token'=>$t['access_token'],'refresh_token'=>$t['refresh_token'],'expires_at'=>time()+(int)($t['expires_in']??3600)]);
   if(isset($t['scope']))$r['scopes']=explode(' ',$t['scope']);
   if(!in_array('chatgpt.tokens.use.direct',$r['scopes'],true))throw new RuntimeException('ChatGPT plan access was removed. Reconnect in Settings.');
   chatgpt_write(chatgpt_dir().'/user-'.$user.'.json',$r);
  }
  return $r['access_token'];
 }finally{flock($lock,LOCK_UN);fclose($lock);}
}
function chatgpt_stream_result(string $raw):array {
 foreach(preg_split('/\r?\n\r?\n/',$raw) as $event){
  $lines=[];foreach(explode("\n",$event) as $line)if(str_starts_with($line,'data:'))$lines[]=trim(substr($line,5));
  $data=json_decode(implode("\n",$lines),true);if(!is_array($data))continue;
  if(($data['type']??'')==='response.completed')return $data['response'];
  if(in_array($data['type']??'',['response.failed','error'],true))throw new RuntimeException('ChatGPT could not complete the draft. Check your plan usage and app permissions at chatgpt.com/settings/usage. No paid API fallback was attempted.');
  if(($data['type']??'')==='response.incomplete')throw new RuntimeException('ChatGPT returned an incomplete draft. Try shorter input.');
 }
 throw new RuntimeException('ChatGPT connection ended before the draft completed. Try again with shorter input.');
}
