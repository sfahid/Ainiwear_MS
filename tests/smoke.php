<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit('CLI only');
$root=dirname(__DIR__);$cfg=$root.'/config.php';if(is_file($cfg)||is_file($root.'/storage/ai-settings.json'))exit("Use a disposable copy with no config.php or saved AI settings.\n");
require $root.'/vendor/autoload.php';
require $root.'/app/functions.php';require $root.'/app/shipping.php';
require $root.'/app/documents.php';
$port=(int)(getenv('TEST_DB_PORT')?:3306);$user=getenv('TEST_DB_USER')?:'root';$password=getenv('TEST_DB_PASSWORD')?:'';
$db=new PDO("mysql:host=127.0.0.1;port=$port;charset=utf8mb4",$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$name='aini_test_'.bin2hex(random_bytes(6));$temp=sys_get_temp_dir().'/'.$name;mkdir($temp);$proc=null;$count=0;
function check(bool $ok,string $label):void{global $count;if(!$ok)throw new RuntimeException('FAIL: '.$label);$count++;echo "PASS: $label\n";}
function request(string $page,array $post=[],array $headers=[]):array{
 global $base,$temp;
 $ch=curl_init($base.'/index.php'.($page?'?'.$page:''));
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$temp.'/cookies',CURLOPT_COOKIEJAR=>$temp.'/cookies',CURLOPT_TIMEOUT=>20]);
 if($headers)curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
 if($post)curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post]);
 $body=curl_exec($ch);if($body===false)throw new RuntimeException(curl_error($ch));$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);$headers=curl_getinfo($ch);curl_close($ch);return [$code,$body,$headers];
}
function csrf_from(string $body):string{if(!preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m))throw new RuntimeException('No CSRF token');return $m[1];}
try{
 $schema=str_replace('aini_wear',$name,file_get_contents($root.'/database/schema.sql'));
 $db->exec($schema);$db->exec(str_replace('aini_wear',$name,file_get_contents($root.'/database/seed.sql')));
 $db->prepare('INSERT INTO users(name,username,password_hash) VALUES(?,?,?)')->execute(['Smoke tester','smoke',password_hash('test-password-123',PASSWORD_DEFAULT)]);
 file_put_contents($cfg,'<?php return '.var_export(['db'=>['host'=>'127.0.0.1','port'=>$port,'name'=>$name,'user'=>$user,'password'=>$password],'timezone'=>'Asia/Karachi','ai'=>['key'=>'','model'=>'']],true).';');
 $httpPort=random_int(19000,29000);$base='http://127.0.0.1:'.$httpPort;
 $proc=proc_open([PHP_BINARY,'-d','display_errors=0','-d','log_errors=1','-d','session.save_path='.$temp,'-d','upload_tmp_dir='.$temp,'-d','upload_max_filesize=6M','-d','post_max_size=12M','-S','127.0.0.1:'.$httpPort,'-t',$root.'/public'],[0=>['pipe','r'],1=>['file',$temp.'/server.log','a'],2=>['file',$temp.'/server.log','a']],$pipes,$root);
 if(!is_resource($proc))throw new RuntimeException('Cannot start PHP test server');fclose($pipes[0]);
 for($j=0;$j<30;$j++){usleep(100000);$socket=@fsockopen('127.0.0.1',$httpPort);if($socket){fclose($socket);break;}}
 [$code,$body]=request('page=orders');check($code===200&&str_contains($body,'Sign in to your workspace'),'Unauthenticated requests show login');$csrf=csrf_from($body);
 [$code]=request('', ['action'=>'login','csrf'=>'invalid','username'=>'smoke','password'=>'test-password-123']);check($code===403,'CSRF rejection');
 [$code]=request('', ['action'=>'login','csrf'=>$csrf,'username'=>'smoke','password'=>'test-password-123']);check($code===302,'Login succeeds');
 [$code,$body]=request('');$csrf=csrf_from($body);check(str_contains($body,'Production focus'),'Dashboard renders');
 foreach(['customers','orders','order&id=1','item&id=1','settings','shipping','calendar','assistant&item_id=1'] as $p){[$code,$body]=request('page='.$p);check($code===200&&!str_contains($body,'Could not load this record'),'Page '.$p.' renders');}
 [$code]=request('page=customers',['action'=>'customer','csrf'=>$csrf,'name'=>'Test <script>alert(1)</script>','email'=>'test@example.com','phone'=>'123','country'=>'Testland','address'=>'Test address']);check($code===302,'Customer creation');
 $customer=(int)$db->query('SELECT MAX(id) FROM customers')->fetchColumn();[$code,$body]=request('page=customers');check(str_contains($body,'&lt;script&gt;')&&!str_contains($body,'<script>alert(1)'),'Customer text is escaped');
 $orderData=['action'=>'order','csrf'=>$csrf,'code'=>'AW-TEST','customer_id'=>$customer,'status'=>'production','order_date'=>date('Y-m-d'),'due_date'=>date('Y-m-d',strtotime('+4 days')),'ship_date'=>date('Y-m-d',strtotime('+5 days')),'priority'=>'high','destination'=>'United Kingdom','tracking'=>'','details'=>'Bulk order'];
 [$code]=request('page=order',$orderData);check($code===302,'Order creation');$order=(int)$db->query('SELECT MAX(id) FROM orders')->fetchColumn();
 [$code]=request('page=order&id='.$order,$orderData+['id'=>$order]);check($code===302,'Order update');
 $itemData=['action'=>'item','csrf'=>$csrf,'order_id'=>$order,'name'=>'Test jersey','sku'=>'T-1','quantity'=>20,'fabric'=>'Polyester knit','colors'=>'Navy','sizes'=>'M:20','requirements'=>'Sublimation with embroidered logo','due_date'=>date('Y-m-d')];
 [$code]=request('page=item&order_id='.$order,$itemData);check($code===302,'Item creation');$item=(int)$db->query('SELECT MAX(id) FROM items')->fetchColumn();
 $invalid=$itemData;$invalid['quantity']='1.5';[$code,$body]=request('page=item&order_id='.$order,$invalid);check(str_contains($body,'must be a whole number')&&(int)$db->query('SELECT COUNT(*) FROM items')->fetchColumn()===2,'Fractional quantity rejected');
 [$code]=request('page=item&id='.$item,['action'=>'component','csrf'=>$csrf,'item_id'=>$item,'type_id'=>1,'name'=>'Chest logo','quantity'=>20,'details'=>'8 cm','status'=>'blocked','supplier'=>'Maker','due_date'=>date('Y-m-d')]);check($code===302,'Component creation');
 $component=(int)$db->query('SELECT MAX(id) FROM components')->fetchColumn();
 [$code]=request('page=item&id='.$item,['action'=>'component','csrf'=>$csrf,'item_id'=>$item,'id'=>$component,'type_id'=>1,'name'=>'Chest logo','quantity'=>20,'details'=>'8 cm','status'=>'done','supplier'=>'Maker','due_date'=>date('Y-m-d')]);check($code===302&&$db->query('SELECT status FROM components WHERE id='.$component)->fetchColumn()==='done','Independent component status update');
 [$code]=request('page=item&id='.$item,['action'=>'apply_steps','csrf'=>$csrf,'item_id'=>$item,'steps'=>"Client confirmation\nCutting\nSewing"]);check($code===302,'Reviewed sequence applied');
 $step=(int)$db->query('SELECT MIN(id) FROM item_steps WHERE item_id='.$item)->fetchColumn();
 [$code]=request('page=item&id='.$item,['action'=>'step','csrf'=>$csrf,'item_id'=>$item,'id'=>$step,'name'=>'Client confirmation','position'=>10,'status'=>'done','assignee'=>'Lead','start_date'=>date('Y-m-d'),'due_date'=>date('Y-m-d'),'notes'=>'Approved']);check($code===302&&$db->query('SELECT completed_at FROM item_steps WHERE id='.$step)->fetchColumn()!==null,'Step completion timestamp');
 request('page=item&id='.$item,['action'=>'apply_steps','csrf'=>$csrf,'item_id'=>$item,'steps'=>"Client confirmation\nCutting\nSewing"]);check((int)$db->query('SELECT COUNT(*) FROM item_steps WHERE item_id='.$item)->fetchColumn()===3&&$db->query('SELECT status FROM item_steps WHERE id='.$step)->fetchColumn()==='done','Repeated suggestions preserve progress');
 [$code]=request('page=order&id='.$order,['action'=>'note','csrf'=>$csrf,'order_id'=>$order,'body'=>'Buyer follow-up','followup_date'=>date('Y-m-d')]);check($code===302,'Follow-up creation');
 $note=(int)$db->query('SELECT MAX(id) FROM notes')->fetchColumn();request('page=order&id='.$order,['action'=>'note_done','csrf'=>$csrf,'id'=>$note]);check((int)$db->query('SELECT done FROM notes WHERE id='.$note)->fetchColumn()===1,'Follow-up completion');
 [$code,$body]=request('page=calendar');check($code===200&&str_contains($body,'Test jersey')&&str_contains($body,'AW-TEST'),'Calendar includes saved schedule');
 $png=$temp.'/product.png';$im=imagecreatetruecolor(30,30);imagepng($im,$png);imagedestroy($im);
 [$code]=request('page=item&id='.$item,['action'=>'image','csrf'=>$csrf,'item_id'=>$item,'image'=>new CURLFile($png,'image/png','product.png')]);check($code===302,'Product image upload');
 $image=(int)$db->query('SELECT MAX(id) FROM images')->fetchColumn();[$code,$body,$info]=request('image='.$image);check($code===200&&$info['content_type']==='image/png'&&substr($body,0,4)==="\x89PNG",'Authenticated image delivery');
 $jpg=$temp.'/sheet.jpg';$im=imagecreatetruecolor(30,30);imagejpeg($im,$jpg);imagedestroy($im);
 $pdf=$temp.'/sheet.pdf';file_put_contents($pdf,"%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF");
 foreach([[$png,'image/png','sheet.png'],[$jpg,'image/jpeg','sheet.jpg'],[$pdf,'application/pdf','sheet.pdf']] as [$path,$mime,$filename]){
  [$code]=request('page=order&id='.$order,['action'=>'order_file','csrf'=>$csrf,'order_id'=>$order,'sheet'=>new CURLFile($path,$mime,$filename)]);check($code===302,'Order sheet upload '.$filename);
  $fileid=(int)$db->query('SELECT MAX(id) FROM order_files')->fetchColumn();[$code,$download,$meta]=request('file='.$fileid);check($code===200&&$meta['content_type']===$mime&&$download===file_get_contents($path),'Private sheet download '.$filename);
  $doc=saved_document($fileid,$order);$part=ai_attachment_part($doc);check($part['type']===($mime==='application/pdf'?'input_file':'input_image'),'AI attachment payload '.$filename);
  try{saved_document($fileid,1);$blocked=false;}catch(InvalidArgumentException $e){$blocked=true;}check($blocked,'Wrong-order sheet access rejected '.$filename);
 }
 $withSheet=$orderData;$withSheet['id']=$order;$withSheet['sheet']=new CURLFile($png,'image/png','entry.png');[$code]=request('page=order&id='.$order,$withSheet);check($code===302&&(int)$db->query('SELECT COUNT(*) FROM order_files WHERE order_id='.$order)->fetchColumn()===4,'Order entry saves attachment with order');
 [$code,$body]=request('page=assistant&order_id='.$order.'&task=extract&file_id='.$fileid);check(str_contains($body,'multipart/form-data')&&str_contains($body,'sheet.pdf')&&str_contains($body,'name="attachment"'),'AI assistant file upload and saved sheet selector');
 $bad=$temp.'/bad.php';file_put_contents($bad,'<?php echo "bad";');[$code,$body]=request('page=item&id='.$item,['action'=>'image','csrf'=>$csrf,'item_id'=>$item,'image'=>new CURLFile($bad,'image/png','fake.png')]);check(str_contains($body,'Use a JPG, PNG or WebP'),'Executable upload rejected');
 [$code,$body]=request('page=order&id='.$order,['action'=>'order_file','csrf'=>$csrf,'order_id'=>$order,'sheet'=>new CURLFile($bad,'application/pdf','fake.pdf')]);check(str_contains($body,'Only PDF, JPG and PNG'),'Fake PDF sheet rejected');
 $invalid=$orderData;$invalid['code']='AW-INVALID-FILE';$invalid['sheet']=new CURLFile($bad,'application/pdf','fake.pdf');[$code,$body]=request('page=order',$invalid);check(str_contains($body,'Only PDF, JPG and PNG')&&$db->query("SELECT COUNT(*) FROM orders WHERE code='AW-INVALID-FILE'")->fetchColumn()==0,'Invalid order attachment prevents partial order save');
 $csv=file_get_contents($root.'/public/samples/shipping-rates.csv');
 file_put_contents($temp.'/rates.csv',$csv);[$code]=request('page=shipping',['action'=>'import_rates','csrf'=>$csrf,'rates'=>new CURLFile($temp.'/rates.csv','text/csv','rates.csv')]);check($code===302&&(int)$db->query('SELECT COUNT(*) FROM shipping_rates')->fetchColumn()===3,'CSV import updates matching slabs');
 $sheetData=array_map('str_getcsv',array_filter(explode("\n",trim($csv))));
 foreach(['Xlsx'=>'xlsx','Xls'=>'xls'] as $format=>$ext){$book=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$book->getActiveSheet()->fromArray($sheetData);$writer=\PhpOffice\PhpSpreadsheet\IOFactory::createWriter($book,$format);$file=$temp.'/rates.'.$ext;$writer->save($file);$book->disconnectWorksheets();[$code,$body]=request('page=shipping',['action'=>'import_rates','csrf'=>$csrf,'rates'=>new CURLFile($file,'application/octet-stream','rates.'.$ext)]);check($code===302,'Import '.$ext);}
 $before=(int)$db->query('SELECT COUNT(*) FROM shipping_rates')->fetchColumn();file_put_contents($temp.'/invalid.csv',str_replace('65,3','not-a-rate,3',$csv));[$code,$body]=request('page=shipping',['action'=>'import_rates','csrf'=>$csrf,'rates'=>new CURLFile($temp.'/invalid.csv','text/csv','invalid.csv')]);check(str_contains($body,'Invalid rate')&&(int)$db->query('SELECT COUNT(*) FROM shipping_rates')->fetchColumn()===$before,'Invalid import saves no rows');
 $ruleCsv=file_get_contents($root.'/public/samples/shipping-rate-rules.csv');
 $ruleData=array_map('str_getcsv',array_filter(explode("\n",trim($ruleCsv))));
 foreach(['Xlsx'=>'xlsx','Xls'=>'xls'] as $format=>$ext){$book=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$book->getActiveSheet()->fromArray($ruleData);$file=$temp.'/rules.'.$ext;\PhpOffice\PhpSpreadsheet\IOFactory::createWriter($book,$format)->save($file);$book->disconnectWorksheets();[$code]=request('page=shipping',['action'=>'import_rates','csrf'=>$csrf,'rates'=>new CURLFile($file,'application/octet-stream','rules.'.$ext)]);check($code===302,'All three pricing methods import as '.$ext);}
 [$code,$body]=request('page=shipping&destination=United+Kingdom&currency=PKR&weight=15&mode=cheapest');check(str_contains($body,'17,500.00')&&str_contains($body,'18,000.00'),'Compare base-plus and per-kg shipment totals');
 [$code,$body]=request('page=shipping&destination=United+Kingdom&currency=PKR&weight=10');check(str_contains($body,'15,000.00')&&!str_contains($body,'Best match</td><td>Demo<small>Product via'),'Base slab boundary');
 [$code,$body]=request('page=shipping&destination=United+Kingdom&currency=PKR&weight=51');check(!str_contains($body,'Best match</td>'),'50 kg comparison ceiling');
 $fake=$temp.'/package.xlsx';$archive=new ZipArchive();$archive->open($fake,ZipArchive::CREATE);$archive->addFromString('README.txt','Rate package, not a workbook');$archive->close();[$code,$body]=request('page=shipping',['action'=>'import_rates','csrf'=>$csrf,'rates'=>new CURLFile($fake,'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','package.xlsx')]);check(str_contains($body,'not a complete Excel workbook')&&!str_contains($body,'Could not find zip member'),'ZIP package renamed XLSX has clear error');
 [$code]=request('page=shipping',['action'=>'import_rates','csrf'=>$csrf,'rates'=>new CURLFile($temp.'/rates.csv','text/csv','rates.xlsx')]);check($code===302,'CSV contents recognized despite Excel extension');
 $r=rank_rates([['rate'=>42,'transit_days'=>8],['rate'=>65,'transit_days'=>3]],0);check($r[0]['rate']===42,'Cheapest ranking');$r=rank_rates([['rate'=>42,'transit_days'=>8],['rate'=>65,'transit_days'=>3]],1);check($r[0]['rate']===65,'Fastest ranking');
 [$code,$body]=request('page=shipping&destination=United+Kingdom&currency=USD&weight=5&mode=fastest');check(str_contains($body,'Best match')&&strpos($body,'Express')<strpos($body,'Economy'),'Shipping recommendation and weight boundary');
 [$code,$body]=request('page=shipping&destination=United+Kingdom&currency=EUR&weight=5');check(str_contains($body,'No active, unexpired rates match'),'Cross-currency rates excluded');
 [$code,$body]=request('page=assistant&item_id='.$item,['action'=>'ai','csrf'=>$csrf,'item_id'=>$item,'order_id'=>$order,'task'=>'steps','input'=>'Suggest steps']);check(str_contains($body,'AI is not configured'),'AI disabled state');
 $begin=microtime(true);[$code,$body,$meta]=request('page=assistant',['action'=>'ai','csrf'=>$csrf,'task'=>'instructions','input'=>'Read the image','attachment'=>new CURLFile($png,'image/png','instructions.png')],['Accept: application/json']);$json=json_decode($body,true);check($code===422&&str_contains($json['error']??'','AI setup')&&str_contains($meta['content_type'],'application/json')&&microtime(true)-$begin<3,'Missing AI settings returns immediate JSON error for image upload');
 [$code,$body]=request('page=assistant',['action'=>'ai','csrf'=>'expired','task'=>'instructions'],['Accept: application/json']);check($code===403&&str_contains(json_decode($body,true)['error']??'','expired'),'Expired AI form returns JSON error');
 [$code,$body]=request('page=settings');check(str_contains($body,'name="api_key"')&&str_contains($body,'type="password"'),'Private AI setup form is available');
 [$code]=request('page=settings',['action'=>'ai_settings','csrf'=>$csrf,'api_key'=>'test-key-not-real','model'=>'test-vision-model']);check($code===302&&is_file($root.'/storage/ai-settings.json'),'AI settings saved privately');
 [$code,$body]=request('page=settings');check(!str_contains($body,'test-key-not-real')&&str_contains($body,'test-vision-model'),'Saved API key never rendered in settings');
 $built=suggestions(['name'=>'Jersey','fabric'=>'knit','requirements'=>'sublimation embroidered logo'],[],rows('SELECT * FROM step_templates ORDER BY position'));check(in_array('Knitting',$built)&&in_array('Sublimation',$built)&&in_array('Logo preparation',$built),'Offline production suggestions');
 [$code]=request('', ['action'=>'logout','csrf'=>$csrf]);check($code===302,'Logout');[$code,$body]=request('image='.$image);check(!str_starts_with($body,"\x89PNG"),'Images require login');
 [$code,$body]=request('file='.$fileid);check(!str_starts_with($body,'%PDF-')&&str_contains($body,'Sign in to your workspace'),'Order files require login');
 $log=file_get_contents($temp.'/server.log');check(!preg_match('/PHP (Fatal|Warning|Parse)|Uncaught|SQLSTATE/',$log),'No PHP or SQL runtime errors in server log');
 echo "\n$count checks passed.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");$failed=true;if(is_file($temp.'/server.log'))fwrite(STDERR,file_get_contents($temp.'/server.log'));}
finally{
 if(is_resource($proc)){proc_terminate($proc);proc_close($proc);}
 if(is_file($cfg))unlink($cfg);
 if(is_file($root.'/storage/ai-settings.json'))unlink($root.'/storage/ai-settings.json');
 foreach(rows('SELECT filename FROM images') as $img)if(preg_match('/^[a-f0-9]{40}\.(png|jpg|webp)$/',$img['filename']))@unlink($root.'/storage/images/'.$img['filename']);
 foreach(rows('SELECT filename FROM order_files') as $file)if(preg_match('/^[a-f0-9]{40}\.(pdf|jpg|png)$/',$file['filename']))@unlink($root.'/storage/documents/'.$file['filename']);
 $db->exec('DROP DATABASE IF EXISTS `'.$name.'`');foreach(glob($temp.'/*') as $file)if(is_file($file))unlink($file);rmdir($temp);
}
exit(!empty($failed)?1:0);
