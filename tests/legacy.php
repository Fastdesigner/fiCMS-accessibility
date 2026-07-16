<?php

if (!defined('PLUGINPATH')) define('PLUGINPATH',dirname(__DIR__,2));

$legacy = ['passed'=>0,'scores'=>[]];

function scores__calc($scores,$weights) {
	$product = 1;
	$total = array_sum($weights);
	foreach ($scores as $key => $score) $product *= pow(max(0.01,$score),$weights[$key]);
	return min(100,max(0,(int) round(pow($product,1 / $total) * 100)));
}

function legacy_expect($condition,string $message): void {
	global $legacy;
	if (!$condition) throw new RuntimeException($message);
	$legacy['passed']++;
}

foreach (['Config','Score'] as $legacy['class']) require_once dirname(__DIR__).'/src/'.$legacy['class'].'.php';
require_once dirname(__DIR__).'/deprecated/src/Overview.php';

foreach (\accessibility\Config::CATEGORIES as $legacy['category']) $legacy['scores'][$legacy['category']] = ['total'=>1,'success'=>1,'warning'=>0,'error'=>0];
$legacy['scores']['media_alt'] = ['total'=>1,'success'=>0,'warning'=>0,'error'=>1];
$legacy['overview'] = \accessibility\DeprecatedOverview::summarize([[
	'result'=>['audit'=>['scores'=>$legacy['scores'],'accessibility'=>['error'=>['_accessibility_media_alt_missing'=>[['name'=>'img','unique'=>'main img']]],'warning'=>[]]]],
	'mobile'=>0,
	'audit_time'=>1000
]]);

legacy_expect($legacy['overview']['count'] === 1,'Legacy overview did not aggregate the audit');
legacy_expect($legacy['overview']['total']['total'] === 6,'Legacy overview totals are invalid');
legacy_expect($legacy['overview']['score'] === 46,'Legacy overview score is invalid');

echo 'OK '.$legacy['passed']." legacy assertions\n";
