<?php
require dirname(__DIR__).'/app/shipping.php';
$base=['courier'=>'SH','service'=>'Test','destination'=>'Canada','currency'=>'PKR','min_kg'=>11,'max_kg'=>249,'rate'=>2550,'transit_days'=>0];
if(rate_validate($base)['rate_basis']!=='flat')throw new RuntimeException('Legacy basis changed');
$r=rate_validate($base+['rate_basis'=>'per_kg']);
if(shipping_quote($r,12)['rate']!==30600.0)throw new RuntimeException('Per-kg total incorrect');
if(shipping_quote($r,12.5)['rate']!==31875.0)throw new RuntimeException('Weight was unexpectedly rounded');
if(shipping_quote($base,12)['rate']!==2550)throw new RuntimeException('Flat total changed');
try {rate_validate($base+['rate_basis'=>'invalid']);throw new RuntimeException('Invalid basis accepted');}catch(InvalidArgumentException $e){}
$rank=rank_rates([shipping_quote(['rate'=>2550,'rate_basis'=>'per_kg','transit_days'=>0],12),shipping_quote(['rate'=>20000,'rate_basis'=>'flat','transit_days'=>0],12)],0);
if($rank[0]['rate']!==20000)throw new RuntimeException('Rank compared unit rate instead of shipment total');
echo "PASS: legacy prices, per-kg totals, fractional weights and total-price ranking\n";
