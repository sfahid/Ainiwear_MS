<?php
declare(strict_types=1);
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
$configFile=dirname(__DIR__).'/config.php';
if(!is_file($configFile)){ http_response_code(503); exit('Setup needed: copy config.example.php to config.php, enter database details, and run bin/create-user.php. See README.md.'); }
$config=require $configFile;
$aiSettings=dirname(__DIR__).'/storage/ai-settings.json';
if(is_file($aiSettings)){
 $saved=json_decode(file_get_contents($aiSettings),true);
 if(is_array($saved)&&isset($saved['key'],$saved['model']))$config['ai']=array_replace($config['ai']??[],['key'=>$saved['key'],'model'=>$saved['model']]);
}
date_default_timezone_set($config['timezone'] ?? 'Asia/Karachi');
try {
 $d=$config['db'];
 $db=new PDO("mysql:host={$d['host']};port={$d['port']};dbname={$d['name']};charset=utf8mb4",$d['user'],$d['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 $db->exec("SET time_zone = '+05:00'");
} catch(Throwable $e){error_log($e->getMessage()); http_response_code(503); exit('Database unavailable. Check config.php and import database/schema.sql.');}
require __DIR__.'/functions.php';
require __DIR__.'/documents.php';
require __DIR__.'/shipping.php';
require __DIR__.'/ai.php';
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
