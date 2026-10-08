<?php
function e($v):string {return htmlspecialchars((string)($v ?? ''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function q(string $sql,array $args=[]):PDOStatement {global $db; $s=$db->prepare($sql);$s->execute($args);return $s;}
function rows(string $sql,array $args=[]):array{return q($sql,$args)->fetchAll();}
function one(string $sql,array $args=[]):array {return q($sql,$args)->fetch() ?: [];}
function go(string $page='dashboard',array $args=[]):never{header('Location: index.php?'.http_build_query(['page'=>$page]+$args));exit;}
function flash(string $msg):void{$_SESSION['flash']=$msg;}
function json_reply(array $data,int $status=200):never {http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;}
function csrf():void{echo '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">';}
function text(string $key,int $max=10000,bool $required=false):string{
 $v=trim((string)($_POST[$key] ?? '')); if(mb_strlen($v)>$max || ($required && $v===''))throw new InvalidArgumentException("Please check $key (maximum $max characters)."); return $v;
}
function number(string $key,float $min=0,float $max=100000000):float{
 $v=$_POST[$key]??''; if(!is_numeric($v)||!is_finite((float)$v)||(float)$v<$min||(float)$v>$max)throw new InvalidArgumentException("Invalid $key."); return (float)$v;
}
function integer(string $key,int $min=1,int $max=1000000):int { $n=number($key,$min,$max);if(floor($n)!==$n)throw new InvalidArgumentException("$key must be a whole number.");return (int)$n;}
function dateval(string $key):?string{
 $v=text($key,10);if($v==='')return null;$d=DateTimeImmutable::createFromFormat('!Y-m-d',$v);if(!$d||$d->format('Y-m-d')!==$v)throw new InvalidArgumentException("Invalid $key date.");return $v;
}
function choice(string $key,array $choices):string {$v=text($key,40);if(!in_array($v,$choices,true))throw new InvalidArgumentException("Invalid $key.");return $v;}
function must(string $table,int $id):array {
 if(!in_array($table,['orders','items','components','item_steps','notes','customers','shipping_rates','step_templates'],true))throw new LogicException('Invalid table');
 $r=one("SELECT * FROM $table WHERE id=?",[$id]);if(!$r)throw new InvalidArgumentException('Record not found.');return $r;
}
function statuses():array{return ['pending','in_progress','blocked','done'];}
function selectbox(string $name,array $values,$selected='',bool $required=true):void{
 echo '<select name="'.e($name).'"'.($required?' required':'').'>';foreach($values as $key=>$label){$value=is_int($key)?$label:$key;echo '<option value="'.e($value).'"'.((string)$selected===(string)$value?' selected':'').'>'.e(ucwords(str_replace('_',' ',$label))).'</option>';}echo '</select>';
}
function field(string $label,string $name,$value='',string $type='text',bool $required=false,string $extra=''):void{
 echo '<label>'.e($label).'<input type="'.e($type).'" name="'.e($name).'" value="'.e($value).'"'.($required?' required':'').' '.$extra.'></label>';
}
function area(string $label,string $name,$value='',int $rows=3):void {echo '<label>'.e($label).'<textarea name="'.e($name).'" rows="'.$rows.'">'.e($value).'</textarea></label>';}
function startform(string $action,array $hidden=[],bool $upload=false):void{
 echo '<form method="post"'.($upload?' enctype="multipart/form-data"':'').'>';csrf();echo '<input type="hidden" name="action" value="'.e($action).'">';foreach($hidden as $k=>$v)echo '<input type="hidden" name="'.e($k).'" value="'.e($v).'">';
}
function badge(string $status):void {echo '<span class="badge '.e($status).'">'.e(str_replace('_',' ',$status)).'</span>';}
function progress(int $item):array{return one("SELECT COUNT(*) total, SUM(status='done') done FROM item_steps WHERE item_id=?",[$item]);}
function suggestions(array $item,array $components,array $templates):array{
 $text=mb_strtolower(implode(' ',[$item['name'],$item['fabric'],$item['requirements'],implode(' ',array_column($components,'name')),implode(' ',array_column($components,'type_name'))]));
 $names=['Color finalization','Client review','Client confirmation'];
 foreach(['Knitting'=>['knit'],'Logo preparation'=>['logo','embroider','rubber','woven','silicone'],'Sublimation'=>['sublimat'],'Laser work'=>['laser'],'Washing'=>['wash']] as $name=>$words){foreach($words as $word)if(str_contains($text,$word)){$names[]=$name;break;}}
 $names=array_merge($names,['Cutting','Sewing','Quality check','Packing']);
 $result=[];foreach($templates as $t)if(in_array($t['name'],$names,true))$result[]=$t['name'];return $result;
}
function image_upload(int $item):void{
 if(empty($_FILES['image']) || $_FILES['image']['error']===UPLOAD_ERR_NO_FILE)return;
 $f=$_FILES['image'];if($f['error']!==UPLOAD_ERR_OK || $f['size']>5*1024*1024)throw new InvalidArgumentException('Image must be under 5 MB.');
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;
 $size=getimagesize($f['tmp_name']);if(!$ext||!$size||$size[0]>8000||$size[1]>8000)throw new InvalidArgumentException('Use a JPG, PNG or WebP image up to 8000 pixels per side.');
 $name=bin2hex(random_bytes(20)).'.'.$ext;$path=dirname(__DIR__).'/storage/images/'.$name;
 if(!move_uploaded_file($f['tmp_name'],$path))throw new RuntimeException('Cannot save image. Check storage/images permissions.');
 try {q('INSERT INTO images(item_id,filename,original_name,mime) VALUES(?,?,?,?)',[$item,$name,mb_substr(basename($f['name']),0,255),$mime]);}catch(Throwable $e){unlink($path);throw $e;}
}
