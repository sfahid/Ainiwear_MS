<?php
function rate_validate(array $r,int $line=0):array {
 $prefix=$line?"Row $line: ":'';
 $required=['courier','service','destination','currency','min_kg','max_kg','rate','transit_days'];
 foreach($required as $k)if(!isset($r[$k])||trim((string)$r[$k])==='')throw new InvalidArgumentException($prefix."Missing $k.");
 foreach(['courier','service','destination'] as $k){$r[$k]=trim((string)$r[$k]);if(mb_strlen($r[$k])>100)throw new InvalidArgumentException($prefix."$k is too long.");}
 $r['currency']=strtoupper(trim((string)$r['currency']));if(!preg_match('/^[A-Z]{3}$/',$r['currency']))throw new InvalidArgumentException($prefix.'Use a 3-letter currency.');
 foreach(['min_kg','max_kg','rate','transit_days'] as $k){if(!is_numeric($r[$k])||!is_finite((float)$r[$k])||(float)$r[$k]<0)throw new InvalidArgumentException($prefix."Invalid $k.");$r[$k]=(float)$r[$k];}
 if($r['max_kg']<=$r['min_kg']||$r['max_kg']>1000000||$r['rate']>100000000||$r['transit_days']<1||$r['transit_days']>365||floor($r['transit_days'])!==$r['transit_days'])throw new InvalidArgumentException($prefix.'Check weight slab, rate and transit days.');
 $r['valid_until']=trim((string)($r['valid_until']??''))?:null;
 if($r['valid_until']){$d=DateTimeImmutable::createFromFormat('!Y-m-d',$r['valid_until']);if(!$d||$d->format('Y-m-d')!==$r['valid_until'])throw new InvalidArgumentException($prefix.'valid_until must be YYYY-MM-DD. Format Excel date cells as text.');}
 return $r;
}
function save_rate(array $r):void{
 q('INSERT INTO shipping_rates(courier,service,destination,currency,min_kg,max_kg,rate,transit_days,valid_until) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE rate=VALUES(rate),transit_days=VALUES(transit_days),valid_until=VALUES(valid_until),active=1',array_map(fn($k)=>$r[$k],['courier','service','destination','currency','min_kg','max_kg','rate','transit_days','valid_until']));
}
function import_rates(array $f):int{
 global $db;
 if(($f['error']??1)!==UPLOAD_ERR_OK||($f['size']??0)>5*1024*1024)throw new InvalidArgumentException('Upload a rate file under 5 MB.');
 $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));$data=[];
 if($ext==='csv'){
  $h=fopen($f['tmp_name'],'r');if(!$h)throw new RuntimeException('Cannot read file.');
  try{while(($r=fgetcsv($h,0,',','"',''))!==false){$data[]=$r;if(count($data)>2001)throw new InvalidArgumentException('Maximum 2000 data rows.');}}finally{fclose($h);}
 }elseif(in_array($ext,['xls','xlsx'],true)){
  $autoload=dirname(__DIR__).'/vendor/autoload.php';if(!is_file($autoload))throw new InvalidArgumentException('Excel support requires Composer install (see README). CSV works without it.');require_once $autoload;
  // Select the reader explicitly: never treat an uploaded spreadsheet as HTML.
  $reader=\PhpOffice\PhpSpreadsheet\IOFactory::createReader($ext==='xls'?'Xls':'Xlsx');
  $reader->setReadDataOnly(true);
  $reader->setReadFilter(new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
   public function readCell(string $columnAddress,int $row,string $worksheetName=''):bool{return $row<=2002 && in_array($columnAddress,['A','B','C','D','E','F','G','H','I'],true);}
  });
  $info=$reader->listWorksheetInfo($f['tmp_name']);
  if(($info[0]['totalRows']??0)>2001||($info[0]['totalColumns']??0)>9)throw new InvalidArgumentException('First sheet must have at most 2000 data rows and 9 columns.');
  $reader->setLoadSheetsOnly($info[0]['worksheetName']);
  $book=$reader->load($f['tmp_name']);$sheet=$book->getSheet(0);
  if($sheet->getHighestDataRow()>2001)throw new InvalidArgumentException('Maximum 2000 data rows.');
  $data=$sheet->rangeToArray('A1:I'.$sheet->getHighestDataRow(),null,false,false,false);$book->disconnectWorksheets();
 }else throw new InvalidArgumentException('Use CSV, XLS or XLSX.');
 if(!$data)throw new InvalidArgumentException('Empty file.');
 $headers=array_map(fn($s)=>strtolower(trim((string)$s)),array_shift($data));$headers[0]=preg_replace('/^\xEF\xBB\xBF/','',$headers[0]??'');
 $expected=['courier','service','destination','currency','min_kg','max_kg','rate','transit_days','valid_until'];
 // Excel pads optional empty columns; the header still has to contain all nine names.
 if($headers!==$expected)throw new InvalidArgumentException('Headers must match samples/shipping-rates.csv exactly, including valid_until.');
 $valid=[];$keys=[];foreach($data as $i=>$row){if(!array_filter($row,fn($v)=>trim((string)$v)!==''))continue;
  if(count($row)>9)throw new InvalidArgumentException('Row '.($i+2).': extra columns.');
  $r=rate_validate(array_combine($expected,array_pad($row,9,'')),$i+2);
  $key=mb_strtolower(implode('|',array_map(fn($k)=>(string)$r[$k],array_slice($expected,0,6))));
  if(isset($keys[$key]))throw new InvalidArgumentException('Row '.($i+2).': duplicate slab in this file.');$keys[$key]=true;$valid[]=$r;
 }
 if(!$valid)throw new InvalidArgumentException('No rates found.');
 $db->beginTransaction();try{foreach($valid as $r)save_rate($r);$db->commit();}catch(Throwable $e){$db->rollBack();throw $e;}return count($valid);
}
function rank_rates(array $rates,float $speedWeight):array{
 if(!$rates)return [];
 $lo=min(array_column($rates,'rate'));$hi=max(array_column($rates,'rate'));$fast=min(array_column($rates,'transit_days'));$slow=max(array_column($rates,'transit_days'));
 foreach($rates as &$r){$cost=$hi==$lo?0:((float)$r['rate']-$lo)/($hi-$lo);$speed=$slow==$fast?0:((int)$r['transit_days']-$fast)/($slow-$fast);$r['score']=(1-$speedWeight)*$cost+$speedWeight*$speed;}
 unset($r);usort($rates,fn($a,$b)=>($a['score']<=>$b['score'])?:($a['rate']<=>$b['rate'])?:($a['transit_days']<=>$b['transit_days']));return $rates;
}
