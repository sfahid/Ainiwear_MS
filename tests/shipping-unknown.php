<?php
require dirname(__DIR__).'/app/shipping.php';
function expect_shipping(bool $ok):void {if(!$ok)throw new RuntimeException('Shipping assertion failed');}
$base=['courier'=>'Test','service'=>'Standard','destination'=>'Canada','currency'=>'PKR','min_kg'=>0,'max_kg'=>1,'rate'=>100,'transit_days'=>0];
expect_shipping(rate_validate($base)['transit_days']===0.0);
try {rate_validate(array_replace($base,['transit_days'=>-1]));throw new RuntimeException('Negative days accepted');}catch(InvalidArgumentException $e){}
$rates=[['rate'=>100,'transit_days'=>0.0],['rate'=>200,'transit_days'=>5],['rate'=>300,'transit_days'=>2]];
expect_shipping(rank_rates($rates,0)[0]['transit_days']===0.0);
expect_shipping(rank_rates($rates,1)[0]['transit_days']===2);
expect_shipping(rank_rates($rates,.5)[2]['transit_days']===0.0);
expect_shipping(rank_rates([['rate'=>200,'transit_days'=>0],['rate'=>100,'transit_days'=>0]],1)[0]['rate']===100);
echo "PASS: unknown transit validation and recommendation ordering\n";
