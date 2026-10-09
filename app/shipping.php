<?php
const SHIPPING_IMPORT_MAX_ROWS=50000;
const SHIPPING_IMPORT_MAX_BYTES=20*1024*1024;
function rate_validate(array $r,int $line=0):array {
 $prefix=$line?"Row $line: ":'';
 $required=['courier','service','destination','currency','min_kg','max_kg','rate','transit_days'];
 foreach($required as $k)if(!isset($r[$k])||trim((string)$r[$k])==='')throw new InvalidArgumentException($prefix."Missing $k.");
 foreach(['courier','service','destination'] as $k){$r[$k]=trim((string)$r[$k]);if(mb_strlen($r[$k])>100)throw new InvalidArgumentException($prefix."$k is too long.");}
 $r['currency']=strtoupper(trim((string)$r['currency']));if(!preg_match('/^[A-Z]{3}$/',$r['currency']))throw new InvalidArgumentException($prefix.'Use a 3-letter currency.');
 foreach(['min_kg','max_kg','rate','transit_days'] as $k){if(!is_numeric($r[$k])||!is_finite((float)$r[$k])||(float)$r[$k]<0)throw new InvalidArgumentException($prefix."Invalid $k.");$r[$k]=(float)$r[$k];}
 if($r['max_kg']<=$r['min_kg']||$r['max_kg']>1000000||$r['rate']>100000000||$r['transit_days']>365||floor($r['transit_days'])!==$r['transit_days'])throw new InvalidArgumentException($prefix.'Check weight slab, rate and transit days.');
 $r['rate_basis']=trim((string)($r['rate_basis']??'flat'))?:'flat';
 if(!in_array($r['rate_basis'],['flat','per_kg','additional_per_kg'],true))throw new InvalidArgumentException($prefix.'rate_basis must be flat, per_kg or additional_per_kg.');
 foreach(['base_kg','base_rate'] as $k){$v=$r[$k]??0;if($v==='')$v=0;if(!is_numeric($v)||!is_finite((float)$v)||(float)$v<0)throw new InvalidArgumentException($prefix."Invalid $k.");$r[$k]=(float)$v;}
 if($r['rate_basis']==='additional_per_kg' && ($r['base_kg']>$r['min_kg']||$r['base_kg']>50||$r['base_rate']>100000000))throw new InvalidArgumentException($prefix.'Base weight must not exceed the starting weight.');
 $r['valid_until']=trim((string)($r['valid_until']??''))?:null;
 if($r['valid_until']){$d=DateTimeImmutable::createFromFormat('!Y-m-d',$r['valid_until']);if(!$d||$d->format('Y-m-d')!==$r['valid_until'])throw new InvalidArgumentException($prefix.'valid_until must be YYYY-MM-DD. Format Excel date cells as text.');}
 return $r;
}
function save_rate(array $r):void{
 q('INSERT INTO shipping_rates(courier,service,destination,currency,min_kg,max_kg,rate,transit_days,valid_until,rate_basis,base_kg,base_rate) VALUES(?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE rate=VALUES(rate),transit_days=VALUES(transit_days),valid_until=VALUES(valid_until),rate_basis=VALUES(rate_basis),base_kg=VALUES(base_kg),base_rate=VALUES(base_rate),active=1',array_map(fn($k)=>$r[$k],['courier','service','destination','currency','min_kg','max_kg','rate','transit_days','valid_until','rate_basis','base_kg','base_rate']));
}
function import_rates(array $f):int{
 global $db;
 set_time_limit(180);
 if(($f['error']??1)!==UPLOAD_ERR_OK||($f['size']??0)>SHIPPING_IMPORT_MAX_BYTES)throw new InvalidArgumentException('Upload a rate file up to 20 MB.');
 $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));$data=[];
 if($ext==='zip')throw new InvalidArgumentException('Extract the ZIP package first, then upload the single CSV rate file inside it.');
 if(in_array($ext,['csv','xls','xlsx'],true)){
  $probe=fopen($f['tmp_name'],'rb');if(!$probe)throw new InvalidArgumentException('Cannot read the uploaded rate file.');
  try{$first=fgets($probe,4096);}finally{fclose($probe);}
  if($first!==false && preg_match('/^(?:\xEF\xBB\xBF)?courier,service,destination,currency,/i',trim($first)))$ext='csv';
 }

 if($ext==='csv'){
  $h=fopen($f['tmp_name'],'r');if(!$h)throw new RuntimeException('Cannot read file.');
  try{while(($r=fgetcsv($h,0,',','"',''))!==false){$data[]=$r;if(count($data)>SHIPPING_IMPORT_MAX_ROWS+1)throw new InvalidArgumentException('Maximum 50,000 data rows.');}}finally{fclose($h);}
 }elseif(in_array($ext,['xls','xlsx'],true)){
  $autoload=dirname(__DIR__).'/vendor/autoload.php';if(!is_file($autoload))throw new InvalidArgumentException('Excel support requires Composer install (see README). CSV works without it.');require_once $autoload;
  if($ext==='xlsx'){
   $zip=new ZipArchive();$opened=$zip->open($f['tmp_name']);
   if($opened!==true)throw new InvalidArgumentException('This file is not a valid XLSX workbook. Upload the original CSV rate file, or save a genuine Excel workbook as XLSX.');
   try{foreach(['[Content_Types].xml','_rels/.rels','xl/workbook.xml'] as $member)if($zip->locateName($member)===false)throw new InvalidArgumentException('This ZIP is not a complete Excel workbook. Extract the rate package and upload its CSV file, or save the workbook again in Excel.');}finally{$zip->close();}
  }
  try{
  // Select the reader explicitly: never treat an uploaded spreadsheet as HTML.
  $reader=\PhpOffice\PhpSpreadsheet\IOFactory::createReader($ext==='xls'?'Xls':'Xlsx');
  if(!$reader->canRead($f['tmp_name']))throw new InvalidArgumentException('The file contents do not match its Excel format. Use the original CSV or save the workbook again in Excel.');
  $reader->setReadDataOnly(true);
  $reader->setReadFilter(new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
   public function readCell(string $columnAddress,int $row,string $worksheetName=''):bool{return $row<=SHIPPING_IMPORT_MAX_ROWS+2 && in_array($columnAddress,['A','B','C','D','E','F','G','H','I','J','K','L'],true);}
  });
  $info=$reader->listWorksheetInfo($f['tmp_name']);
  if(($info[0]['totalRows']??0)>SHIPPING_IMPORT_MAX_ROWS+1||($info[0]['totalColumns']??0)>12)throw new InvalidArgumentException('First sheet must have at most 50,000 data rows and 9, 10 or 12 columns.');
  $reader->setLoadSheetsOnly($info[0]['worksheetName']);
  $book=$reader->load($f['tmp_name']);$sheet=$book->getSheet(0);
  if($sheet->getHighestDataRow()>SHIPPING_IMPORT_MAX_ROWS+1)throw new InvalidArgumentException('Maximum 50,000 data rows.');
  $data=$sheet->rangeToArray('A1:'.\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($info[0]['totalColumns']??9).$sheet->getHighestDataRow(),null,false,false,false);$book->disconnectWorksheets();
  }catch(InvalidArgumentException $e){throw $e;}catch(Throwable $e){error_log('Shipping workbook read failed: '.$e->getMessage());throw new InvalidArgumentException('The Excel workbook could not be read. Save it again in Excel, or upload the original CSV rate file.');}
 }else throw new InvalidArgumentException('Use CSV, XLS or XLSX.');
 if(!$data)throw new InvalidArgumentException('Empty file.');
 $headers=array_map(fn($s)=>strtolower(trim((string)$s)),array_shift($data));$headers[0]=preg_replace('/^\xEF\xBB\xBF/','',$headers[0]??'');
 $expected=['courier','service','destination','currency','min_kg','max_kg','rate','transit_days','valid_until'];
 // Excel pads optional empty columns; the header still has to contain all nine names.
 if($headers===array_merge($expected,['rate_basis','base_kg','base_rate']))$expected=array_merge($expected,['rate_basis','base_kg','base_rate']);
 elseif($headers===array_merge($expected,['rate_basis']))$expected[]='rate_basis';
 if($headers!==$expected)throw new InvalidArgumentException('Headers must match samples/shipping-rates.csv exactly, including valid_until.');
 $valid=[];$keys=[];foreach($data as $i=>$row){if(!array_filter($row,fn($v)=>trim((string)$v)!==''))continue;
  if(count($row)>count($expected))throw new InvalidArgumentException('Row '.($i+2).': extra columns.');
  $r=rate_validate(array_combine($expected,array_pad($row,count($expected),'')),$i+2);
  $key=mb_strtolower(implode('|',array_map(fn($k)=>(string)$r[$k],array_slice($expected,0,6))));
  if(isset($keys[$key])){if($keys[$key]!==$r)throw new InvalidArgumentException('Row '.($i+2).': conflicting duplicate slab.');continue;}$keys[$key]=$r;$valid[]=$r;
  unset($data[$i]);
 }
 if(!$valid)throw new InvalidArgumentException('No rates found.');
 $statement=$db->prepare('INSERT INTO shipping_rates(courier,service,destination,currency,min_kg,max_kg,rate,transit_days,valid_until,rate_basis,base_kg,base_rate) VALUES(?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE rate=VALUES(rate),transit_days=VALUES(transit_days),valid_until=VALUES(valid_until),rate_basis=VALUES(rate_basis),base_kg=VALUES(base_kg),base_rate=VALUES(base_rate),active=1');
 $existing=[];foreach(rows('SELECT * FROM shipping_rates') as $old)$existing[shipping_rate_key($old)]=$old;
 $stats=['new'=>0,'updated'=>0,'unchanged'=>0];
 $db->beginTransaction();try{foreach($valid as $r){$old=$existing[shipping_rate_key($r)]??null;if($old && shipping_rate_equal($old,$r)){$stats['unchanged']++;continue;}$statement->execute(array_map(fn($k)=>$r[$k],array_merge(array_slice($expected,0,9),['rate_basis','base_kg','base_rate'])));$stats[$old?'updated':'new']++;}$db->commit();$_SESSION['shipping_import_stats']=$stats;}catch(Throwable $e){$db->rollBack();throw $e;}return count($valid);
}
function rank_rates(array $rates,float $speedWeight):array{
 if(!$rates)return [];
 $lo=min(array_column($rates,'rate'));$hi=max(array_column($rates,'rate'));$known=array_filter(array_column($rates,'transit_days'),fn($days)=>$days>0);$fast=$known?min($known):0;$slow=$known?max($known):0;
 foreach($rates as &$r){$cost=$hi==$lo?0:((float)$r['rate']-$lo)/($hi-$lo);$speed=(int)$r['transit_days']===0||$slow==$fast?0:((int)$r['transit_days']-$fast)/($slow-$fast);$r['score']=(1-$speedWeight)*$cost+$speedWeight*$speed;}
 unset($r);usort($rates,fn($a,$b)=>($speedWeight>0?((int)((int)$a['transit_days']===0)<=>(int)((int)$b['transit_days']===0)):0)?:($a['score']<=>$b['score'])?:($a['rate']<=>$b['rate'])?:($a['transit_days']<=>$b['transit_days']));return $rates;
}

function shipping_quote(array $rate,float $weight):array {
 $rate['unit_rate']=(float)$rate['rate'];
 if(($rate['rate_basis']??'flat')==='additional_per_kg')$rate['rate']=round((float)$rate['base_rate']+max(0,$weight-(float)$rate['base_kg'])*$rate['unit_rate'],2);
 elseif(($rate['rate_basis']??'flat')==='per_kg')$rate['rate']=round($rate['unit_rate']*$weight,2);
 return $rate;
}

function shipping_rate_key(array $r):string {
 return mb_strtolower(implode('|',[$r['courier'],$r['service'],$r['destination'],$r['currency'],number_format((float)$r['min_kg'],3,'.',''),number_format((float)$r['max_kg'],3,'.','')]));
}
function shipping_rate_equal(array $a,array $b):bool {
 foreach(['rate','transit_days','base_kg','base_rate'] as $k)if((float)($a[$k]??0)!==(float)($b[$k]??0))return false;
 return ($a['rate_basis']??'flat')===($b['rate_basis']??'flat') && ($a['valid_until']??null)===($b['valid_until']??null) && (int)($a['active']??1)===1;
}
