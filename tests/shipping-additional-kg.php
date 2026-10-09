<?php
require dirname(__DIR__).'/app/shipping.php';
$r=rate_validate(['courier'=>'SH','service'=>'Duty paid','destination'=>'UAE','currency'=>'PKR','min_kg'=>1,'max_kg'=>20,'rate'=>1340,'transit_days'=>1,'rate_basis'=>'additional_per_kg','base_kg'=>1,'base_rate'=>5180]);
foreach([1=>5180,5=>10540,10=>17240] as $weight=>$expected)if(shipping_quote($r,$weight)['rate']!==(float)$expected)throw new RuntimeException('Incorrect base-plus charge');
if(shipping_quote($r,1.5)['rate']!==5850.0)throw new RuntimeException('Fractional weight unexpectedly rounded');
$old=$r+['active'=>1];if(!shipping_rate_equal($old,$r))throw new RuntimeException('Unchanged rule not recognized');
$changed=$r;$changed['base_rate']=5200;if(shipping_rate_equal($old,$changed))throw new RuntimeException('Changed base charge ignored');
$disabled=$old;$disabled['active']=0;if(shipping_rate_equal($disabled,$r))throw new RuntimeException('Disabled rule incorrectly skipped');
try {$r['base_kg']=2;rate_validate($r);throw new RuntimeException('Invalid base weight accepted');}catch(InvalidArgumentException $e){}
echo "PASS: base-plus totals, fractional weights and duplicate/update detection\n";
