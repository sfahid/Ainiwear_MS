<?php
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/app/chatgpt.php';
$count=0;
function pass(bool $ok,string $label):void{global $count;if(!$ok)throw new RuntimeException($label);$count++;echo "PASS $label\n";}
function rejects(callable $fn,string $label):void{try{$fn();}catch(Throwable $e){pass(true,$label);return;}pass(false,$label);}
$key=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
if(!$key)throw new RuntimeException('Test RSA key generation failed; configure OpenSSL.');
$details=openssl_pkey_get_details($key);
$b64=fn($s)=>rtrim(strtr(base64_encode($s),'+/','-_'),'=');
$jwks=['keys'=>[['kty'=>'RSA','kid'=>'fixture','alg'=>'RS256','n'=>$b64($details['rsa']['n']),'e'=>$b64($details['rsa']['e'])]]];
$claims=['iss'=>'https://auth.openai.com','sub'=>'fixture-user','aud'=>'fixture-client','iat'=>time(),'exp'=>time()+120,'nonce'=>'fixture-nonce'];
$encode=fn($c)=>\Firebase\JWT\JWT::encode($c,$key,'RS256','fixture');
pass(chatgpt_identity($encode($claims),$jwks,'fixture-client','fixture-nonce')['sub']==='fixture-user','valid signed identity');
foreach(['iss'=>'https://attacker.invalid','aud'=>'other-client','nonce'=>'other-nonce','exp'=>time()-120,'sub'=>''] as $field=>$bad){$c=$claims;$c[$field]=$bad;rejects(fn()=>chatgpt_identity($encode($c),$jwks,'fixture-client','fixture-nonce'),'reject wrong '.$field);}
$token=$encode($claims);$parts=explode('.',$token);$parts[1]=$b64(json_encode(array_replace($claims,['sub'=>'attacker'])));
rejects(fn()=>chatgpt_identity(implode('.',$parts),$jwks,'fixture-client','fixture-nonce'),'reject forged signature');
rejects(fn()=>chatgpt_pending('../outside'),'reject invalid state path');
$completed=['type'=>'response.completed','response'=>['status'=>'completed','output'=>[]]];
pass(chatgpt_stream_result("event: response.completed\ndata: ".json_encode($completed)."\n\n")['status']==='completed','SSE completed response');
pass(chatgpt_stream_result("data: ".json_encode($completed)."\r\n\r\n")['status']==='completed','SSE Windows line endings');
foreach(['response.failed','response.incomplete','error'] as $type)rejects(fn()=>chatgpt_stream_result('data: '.json_encode(['type'=>$type])."\n\n"),'reject '.$type);
rejects(fn()=>chatgpt_stream_result("data: {\"type\":\"response.output_text.delta\",\"delta\":\"partial\"}\n\n"),'reject interrupted stream');
echo "$count subscription security and stream checks passed.\n";
