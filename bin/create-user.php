<?php
if(PHP_SAPI!=='cli')exit('CLI only');
$path=dirname(__DIR__).'/config.php';if(!is_file($path))exit("Copy config.example.php to config.php first.\n");
$c=require $path;$d=$c['db'];
$db=new PDO("mysql:host={$d['host']};port={$d['port']};dbname={$d['name']};charset=utf8mb4",$d['user'],$d['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name=trim(readline('Display name: '));$user=trim(readline('Username: '));
echo "Password will be visible in this local terminal. Use a unique password.\n";
$password=readline('Password (12+ characters): ');
if(!$name||!$user||mb_strlen($name)>120||mb_strlen($user)>80||strlen($password)<12)exit("Invalid name, username or password.\n");
$s=$db->prepare('INSERT INTO users(name,username,password_hash) VALUES(?,?,?)');
try{$s->execute([$name,$user,password_hash($password,PASSWORD_DEFAULT)]);echo "User created.\n";}catch(Throwable $e){exit("Could not create user; check database or duplicate username.\n");}
