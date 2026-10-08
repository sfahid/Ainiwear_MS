<?php
if($_SERVER['REQUEST_METHOD']!=='POST')return;
if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))){if(str_contains($_SERVER['HTTP_ACCEPT']??'','application/json'))json_reply(['ok'=>false,'error'=>'Your session or form expired. Reload the page and sign in again.'],403);http_response_code(403);exit('Expired form. Reload and try again.');}
$action=text('action',40,true);
$aiJson=$action==='ai'&&str_contains($_SERVER['HTTP_ACCEPT']??'','application/json');
try {
 if($action==='login'){
  if(time()<($_SESSION['login_after']??0))throw new InvalidArgumentException('Please wait a few seconds before trying again.');
  $u=one('SELECT * FROM users WHERE username=?',[text('username',80,true)]);
  if(!$u||!password_verify(text('password',512,true),$u['password_hash'])){$_SESSION['login_after']=time()+3;throw new InvalidArgumentException('Incorrect username or password.');}
  session_regenerate_id(true);$_SESSION['user']=['id'=>$u['id'],'name'=>$u['name']];unset($_SESSION['login_after']);go();
 }
 if(empty($_SESSION['user'])){if($aiJson)json_reply(['ok'=>false,'error'=>'Sign in again before using AI.'],401);go('login');}
 if($action==='logout'){$_SESSION=[];session_destroy();go('login');}
 switch($action){
 case 'ai_settings':
  $key=text('api_key',512);$model=text('model',100,true);
  if(!preg_match('/^[a-zA-Z0-9_.:\/-]+$/',$model))throw new InvalidArgumentException('Enter a valid model ID from your API account.');
  if($key==='')$key=$config['ai']['key']??'';
  if($key===''||preg_match('/\s/',$key))throw new InvalidArgumentException('Enter your API key without spaces.');
  $path=dirname(__DIR__).'/storage/ai-settings.json';$temp=dirname(__DIR__).'/storage/ai-settings-'.bin2hex(random_bytes(6)).'.tmp';
  if(file_put_contents($temp,json_encode(['key'=>$key,'model'=>$model],JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RuntimeException('Could not save AI settings. Check storage folder permissions.');
  chmod($temp,0600);if(!rename($temp,$path)){unlink($temp);throw new RuntimeException('Could not save AI settings.');}
  flash('AI settings saved. Open the assistant to test your model with a small file.');go('assistant');
 case 'customer':
  $data=[text('name',160,true),text('email',160),text('phone',80),text('country',100),text('address')];
  if($data[1]!==''&&!filter_var($data[1],FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Invalid email address.');
  if(!empty($_POST['id'])){$id=integer('id');must('customers',$id);q('UPDATE customers SET name=?,email=?,phone=?,country=?,address=? WHERE id=?',[...$data,$id]);}else q('INSERT INTO customers(name,email,phone,country,address) VALUES(?,?,?,?,?)',$data);
  flash('Customer saved.');go('customers');
 case 'order':
  $document=null;if(isset($_FILES['sheet'])&&$_FILES['sheet']['error']!==UPLOAD_ERR_NO_FILE)$document=validate_document($_FILES['sheet']);
  $customer=integer('customer_id');must('customers',$customer);$orderdate=dateval('order_date');if(!$orderdate)throw new InvalidArgumentException('Order date is required.');
  $due=dateval('due_date');$ship=dateval('ship_date');if(($due&&$due<$orderdate)||($ship&&$due&&$ship<$due))throw new InvalidArgumentException('Completion date must follow order date; shipping date must follow completion date.');
  $data=[text('code',80,true),$customer,choice('status',['draft','confirmed','production','ready','shipped','cancelled']),$orderdate,$due,$ship,choice('priority',['normal','high','urgent']),text('destination',100),text('tracking',200),text('details')];
  $savedPath=null;$db->beginTransaction();try{
   if(!empty($_POST['id'])){$id=integer('id');must('orders',$id);q('UPDATE orders SET code=?,customer_id=?,status=?,order_date=?,due_date=?,ship_date=?,priority=?,destination=?,tracking=?,details=? WHERE id=?',[...$data,$id]);}else{q('INSERT INTO orders(code,customer_id,status,order_date,due_date,ship_date,priority,destination,tracking,details) VALUES(?,?,?,?,?,?,?,?,?,?)',$data);$id=(int)$db->lastInsertId();}
   if($document)$savedPath=order_file_save($id,$document);$db->commit();
  }catch(Throwable $e){$db->rollBack();if($savedPath&&is_file($savedPath))unlink($savedPath);throw $e;}
  flash('Order saved.');go('order',['id'=>$id]);
 case 'order_file':
  $id=integer('order_id');must('orders',$id);order_file_save($id,validate_document($_FILES['sheet']??[]));flash('Order / product sheet uploaded.');go('order',['id'=>$id]);
 case 'item':
  $order=integer('order_id');must('orders',$order);$data=[text('name',160,true),text('sku',100),integer('quantity'),text('fabric',200),text('colors',200),text('sizes'),text('requirements'),dateval('due_date')];
  $db->beginTransaction();try{
   if(!empty($_POST['id'])){$id=integer('id');$old=must('items',$id);if((int)$old['order_id']!==$order)throw new InvalidArgumentException('Wrong order.');q('UPDATE items SET name=?,sku=?,quantity=?,fabric=?,colors=?,sizes=?,requirements=?,due_date=? WHERE id=?',[...$data,$id]);}else{q('INSERT INTO items(name,sku,quantity,fabric,colors,sizes,requirements,due_date,order_id) VALUES(?,?,?,?,?,?,?,?,?)',[...$data,$order]);$id=(int)$db->lastInsertId();}
   image_upload($id);$db->commit();
  }catch(Throwable $e){$db->rollBack();throw $e;}
  flash('Item saved.');go('item',['id'=>$id]);
 case 'image': $id=integer('item_id');must('items',$id);image_upload($id);flash('Image saved.');go('item',['id'=>$id]);
 case 'component':
  $item=integer('item_id');must('items',$item);$type=integer('type_id');if(!one('SELECT id FROM component_types WHERE id=?',[$type]))throw new InvalidArgumentException('Invalid component type.');
  $data=[$type,text('name',160,true),integer('quantity'),text('details'),choice('status',statuses()),text('supplier',160),dateval('due_date')];
  if(!empty($_POST['id'])){$id=integer('id');$old=must('components',$id);if((int)$old['item_id']!==$item)throw new InvalidArgumentException('Wrong item.');q('UPDATE components SET type_id=?,name=?,quantity=?,details=?,status=?,supplier=?,due_date=? WHERE id=?',[...$data,$id]);}else q('INSERT INTO components(type_id,name,quantity,details,status,supplier,due_date,item_id) VALUES(?,?,?,?,?,?,?,?)',[...$data,$item]);
  flash('Component saved.');go('item',['id'=>$item]);
 case 'component_type':q('INSERT INTO component_types(name) VALUES(?)',[text('name',100,true)]);flash('Component type added.');go('settings');
 case 'template':
  $data=[text('name',100,true),integer('position',0,9999),isset($_POST['active'])?1:0];
  if(!empty($_POST['id'])){$id=integer('id');must('step_templates',$id);q('UPDATE step_templates SET name=?,position=?,active=? WHERE id=?',[...$data,$id]);}else q('INSERT INTO step_templates(name,position,active) VALUES(?,?,?)',$data);
  flash('Step template saved. Existing item steps are preserved.');go('settings');
 case 'step':
  $item=integer('item_id');must('items',$item);$status=choice('status',statuses());$start=dateval('start_date');$due=dateval('due_date');if($start&&$due&&$due<$start)throw new InvalidArgumentException('Step due date precedes start date.');
  $data=[text('name',100,true),integer('position',0,9999),$status,text('assignee',120),$start,$due,text('notes'),$status==='done'?date('Y-m-d H:i:s'):null];
  if(!empty($_POST['id'])){$id=integer('id');$old=must('item_steps',$id);if((int)$old['item_id']!==$item)throw new InvalidArgumentException('Wrong item.');if($status==='done'&&$old['completed_at'])$data[7]=$old['completed_at'];q('UPDATE item_steps SET name=?,position=?,status=?,assignee=?,start_date=?,due_date=?,notes=?,completed_at=? WHERE id=?',[...$data,$id]);}else q('INSERT INTO item_steps(name,position,status,assignee,start_date,due_date,notes,completed_at,item_id) VALUES(?,?,?,?,?,?,?,?,?)',[...$data,$item]);
  flash('Production step saved.');go('item',['id'=>$item]);
 case 'apply_steps':
  $item=integer('item_id');must('items',$item);$lines=explode("\n",text('steps',4000,true));$names=[];
  foreach($lines as $line){$line=trim($line);if($line==='')continue;if(mb_strlen($line)>100)throw new InvalidArgumentException('Step names must be under 100 characters.');$names[]=$line;}
  $names=array_values(array_unique($names));if(!$names||count($names)>30)throw new InvalidArgumentException('Enter 1–30 step names.');
  $db->beginTransaction();try{$position=(int)one('SELECT COALESCE(MAX(position),0) p FROM item_steps WHERE item_id=?',[$item])['p'];foreach($names as $name){$position+=10;q("INSERT INTO item_steps(item_id,name,position,notes) VALUES(?,?,?,'') ON DUPLICATE KEY UPDATE name=VALUES(name)",[$item,$name,$position]);}$db->commit();}catch(Throwable $e){$db->rollBack();throw $e;}
  flash('Steps added; existing steps and status preserved. Edit positions to reorder.');go('item',['id'=>$item]);
 case 'note':
  $order=integer('order_id');must('orders',$order);q('INSERT INTO notes(order_id,body,followup_date) VALUES(?,?,?)',[$order,text('body',10000,true),dateval('followup_date')]);flash('Note saved.');go('order',['id'=>$order]);
 case 'note_done':$id=integer('id');$note=must('notes',$id);q('UPDATE notes SET done=? WHERE id=?',[$note['done']?0:1,$id]);go('order',['id'=>$note['order_id']]);
 case 'rate':save_rate(rate_validate($_POST));flash('Rate saved. Matching slabs are updated.');go('shipping');
 case 'rate_toggle':$id=integer('id');$r=must('shipping_rates',$id);q('UPDATE shipping_rates SET active=? WHERE id=?',[$r['active']?0:1,$id]);go('shipping');
 case 'import_rates':$count=import_rates($_FILES['rates']??[]);flash("$count rates imported. Existing matching slabs updated.");go('shipping');
 case 'ai':
  ai_require_config();
  if(time()<($_SESSION['ai_after']??0))throw new InvalidArgumentException('Wait 10 seconds between AI requests.');
  $context=[];$itemid=(int)($_POST['item_id']??0);$orderid=(int)($_POST['order_id']??0);
  if($itemid){$context['item']=must('items',$itemid);$orderid=(int)$context['item']['order_id'];$context['components']=rows('SELECT c.*,t.name type_name FROM components c JOIN component_types t ON t.id=c.type_id WHERE item_id=?',[$itemid]);$context['steps']=rows('SELECT * FROM item_steps WHERE item_id=? ORDER BY position,id',[$itemid]);}
  if($orderid){$context['order']=must('orders',$orderid);$context['customer']=one('SELECT name,country FROM customers WHERE id=?',[$context['order']['customer_id']]);$context['notes']=rows('SELECT body,followup_date,done FROM notes WHERE order_id=? ORDER BY id DESC LIMIT 20',[$orderid]);if(!$itemid){$context['items']=rows('SELECT * FROM items WHERE order_id=?',[$orderid]);$context['steps']=rows('SELECT s.*,i.name item_name FROM item_steps s JOIN items i ON i.id=s.item_id WHERE i.order_id=? ORDER BY i.id,s.position',[$orderid]);$context['components']=rows('SELECT c.*,t.name type_name,i.name item_name FROM components c JOIN component_types t ON t.id=c.type_id JOIN items i ON i.id=c.item_id WHERE i.order_id=?',[$orderid]);}}
  $attachment=null;$hasUpload=isset($_FILES['attachment'])&&$_FILES['attachment']['error']!==UPLOAD_ERR_NO_FILE;
  $fileid=(int)($_POST['file_id']??0);
  if($hasUpload&&$fileid)throw new InvalidArgumentException('Choose either a new upload or a saved order sheet, not both.');
  if($hasUpload)$attachment=validate_document($_FILES['attachment']);
  elseif($fileid){if(!$orderid)throw new InvalidArgumentException('Select a linked order first.');$attachment=saved_document($fileid,$orderid);}
  $_SESSION['ai_after']=time()+10;
  $task=text('task',20,true);$input=text('input',12000);$userId=$_SESSION['user']['id'];
  // Release the session while waiting so other pages and cancellation remain usable.
  session_write_close();$draft=ai_draft($task,$input,$context,$attachment);session_start();
  if(($_SESSION['user']['id']??null)!==$userId){if($aiJson)json_reply(['ok'=>false,'error'=>'Your session changed. Sign in and try again.'],401);go('login');}
  $_SESSION['ai_draft']=['text'=>$draft,'task'=>$task,'item_id'=>$itemid,'order_id'=>$orderid];
  if($aiJson)json_reply(['ok'=>true,'redirect'=>'index.php?'.http_build_query(['page'=>'assistant','item_id'=>$itemid,'order_id'=>$orderid])]);
  go('assistant',['item_id'=>$itemid,'order_id'=>$orderid]);
 default:throw new InvalidArgumentException('Unknown action.');
 }
}catch(PDOException $e){error_log($e->getMessage());$error=$e->getCode()==='23000'?'A matching code or name already exists, or a referenced record is missing.':'Could not save. Check database connection and server logs.';}
catch(Throwable $e){error_log($e->getMessage());$error=$e instanceof InvalidArgumentException||$e instanceof RuntimeException?$e->getMessage():'Could not complete this request. Check server logs.';}
if($aiJson&&isset($error))json_reply(['ok'=>false,'error'=>$error],isset($e)&&$e instanceof InvalidArgumentException?422:502);
