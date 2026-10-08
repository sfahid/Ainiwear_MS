<?php
// Only explicitly selected files are ever sent to AI.
function validate_document(array $file):array {
 if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new InvalidArgumentException('File upload failed. Choose a PDF, JPG or PNG under 5 MB.');
 $path=$file['tmp_name']??'';
 if(!is_uploaded_file($path))throw new InvalidArgumentException('Invalid uploaded file.');
 if(filesize($path)>5*1024*1024||filesize($path)<1)throw new InvalidArgumentException('Choose a non-empty file under 5 MB.');
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
 $ext=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'][$mime]??null;
 if(!$ext)throw new InvalidArgumentException('Only PDF, JPG and PNG files are supported.');
 if($mime==='application/pdf'){
  $h=fopen($path,'rb');$signature=fread($h,5);fclose($h);
  if($signature!=='%PDF-')throw new InvalidArgumentException('Invalid PDF file.');
 }else{
  $size=getimagesize($path);if(!$size||$size[0]>8000||$size[1]>8000)throw new InvalidArgumentException('Image dimensions must be at most 8000 pixels per side.');
 }
 $name=mb_substr(str_replace(["\r","\n","\0"],'',basename(str_replace('\\','/',(string)$file['name']))),0,255);
 return ['path'=>$path,'mime'=>$mime,'ext'=>$ext,'original_name'=>$name?:'sheet.'.$ext,'bytes'=>filesize($path)];
}
function order_file_save(int $order,array $doc):string {
 $directory=dirname(__DIR__).'/storage/documents';
 if(!is_dir($directory)&&!mkdir($directory,0770,true))throw new RuntimeException('Cannot create document storage.');
 $filename=bin2hex(random_bytes(20)).'.'.$doc['ext'];$path=$directory.'/'.$filename;
 if(!move_uploaded_file($doc['path'],$path))throw new RuntimeException('Cannot save document. Check storage permissions.');
 try{q('INSERT INTO order_files(order_id,filename,original_name,mime,bytes) VALUES(?,?,?,?,?)',[$order,$filename,$doc['original_name'],$doc['mime'],$doc['bytes']]);}catch(Throwable $e){unlink($path);throw $e;}
 return $path;
}
function saved_document(int $id,int $order):array {
 $file=one('SELECT * FROM order_files WHERE id=? AND order_id=?',[$id,$order]);
 if(!$file||!preg_match('/^[a-f0-9]{40}\.(pdf|jpg|png)$/',$file['filename']))throw new InvalidArgumentException('Order file not found.');
 $file['path']=dirname(__DIR__).'/storage/documents/'.$file['filename'];
 if(!is_file($file['path']))throw new InvalidArgumentException('Stored file is missing.');
 return $file;
}
function ai_attachment_part(array $doc):array {
 $data='data:'.$doc['mime'].';base64,'.base64_encode(file_get_contents($doc['path']));
 return $doc['mime']==='application/pdf'
  ? ['type'=>'input_file','filename'=>$doc['original_name'],'file_data'=>$data]
  : ['type'=>'input_image','image_url'=>$data,'detail'=>'auto'];
}
