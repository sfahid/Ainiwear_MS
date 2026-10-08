<?php
require dirname(__DIR__).'/app/bootstrap.php';
$page=(string)($_GET['page']??'dashboard');
if(empty($_SESSION['user']))$page='login';
require dirname(__DIR__).'/app/actions.php';
$allowed=['login','dashboard','customers','orders','order','item','settings','shipping','calendar','assistant'];
if(!in_array($page,$allowed,true)){http_response_code(404);$page='dashboard';$error='Page not found.';}
// Authenticated image delivery; uploaded files live outside the public directory.
if(isset($_GET['image'])&&!empty($_SESSION['user'])){
 $img=one('SELECT * FROM images WHERE id=?',[(int)$_GET['image']]);
 if(!$img||!preg_match('/^[a-f0-9]{40}\.(jpg|png|webp)$/',$img['filename'])){http_response_code(404);exit;}
 $path=dirname(__DIR__).'/storage/images/'.$img['filename'];if(!is_file($path)){http_response_code(404);exit;}
 header('Content-Type: '.$img['mime']);header('Cache-Control: private, max-age=3600');readfile($path);exit;
}
$titles=['login'=>'Welcome back','dashboard'=>'Production overview','customers'=>'Customers','orders'=>'Orders','order'=>'Order workspace','item'=>'Item workspace','settings'=>'Production settings','shipping'=>'Shipping rates','calendar'=>'Schedule','assistant'=>'AI draft assistant'];
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?=e($titles[$page])?> · Aini Wear</title><link rel="stylesheet" href="assets/style.css"><script src="assets/app.js" defer></script></head><body>
<?php if(!empty($_SESSION['user'])): ?><aside><a class="brand" href="index.php">AW<span>Aini Wear<small>Production studio</small></span></a><nav><?php foreach(['dashboard'=>'Overview','orders'=>'Orders','customers'=>'Customers','calendar'=>'Schedule','shipping'=>'Shipping','assistant'=>'AI assistant','settings'=>'Settings'] as $p=>$label): ?><a class="<?=$page===$p?'selected':''?>" href="index.php?page=<?=e($p)?>"><?=e($label)?></a><?php endforeach ?></nav><div class="user"><?=e($_SESSION['user']['name'])?><?php startform('logout'); ?><button class="quiet">Sign out</button></form></div></aside><?php endif ?>
<main class="<?=$page==='login'?'login':''?>"><header><div><p class="eyebrow">AINI WEAR / OPERATIONS</p><h1><?=e($titles[$page])?></h1></div><time><?=e(date('D, d M Y'))?></time></header>
<?php if(!empty($error)): ?><div class="alert error" role="alert"><?=e($error)?> Your submitted fields remain below where applicable.</div><?php endif ?>
<?php if(isset($_SESSION['flash'])): ?><div class="alert" role="status"><?=e($_SESSION['flash'])?></div><?php unset($_SESSION['flash']);endif ?>
<?php try{require dirname(__DIR__).'/app/pages/'.$page.'.php';}catch(Throwable $ex){error_log($ex->getMessage());http_response_code(400);echo '<div class="alert error">Could not load this record. Return to the orders list or check the database setup.</div>';} ?>
<footer>Aini Wear · Small steps. Clear progress.</footer></main></body></html>
