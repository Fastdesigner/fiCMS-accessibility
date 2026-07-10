<?php

$test = ['mysql_result'=>false,'mysql_check'=>[],'last_query'=>'','passed'=>0];

function scores__calc(array $scores, array $weights): float {
	$product = 1;
	$total = array_sum($weights);
	foreach (array_values($scores) as $key => $score) $product *= pow(max(0.01,$score),$weights[$key]);
	return ($total > 0) ? round(pow($product,1 / $total) * 100) : 100;
}

function mysqlFetchAssoc($query) {
	global $test;
	$test['last_query'] = is_string($query) ? $query : '';
	return $test['mysql_result'];
}

function mysqlCheck($table, $fields = [], $keys = [], $delete = [], $force = []) {
	global $test;
	$test['mysql_check'] = compact('table','fields','keys','delete','force');
	return [];
}

function mysqlEscape(string $value): string {
	return addslashes($value);
}

function expect($condition, string $message): void {
	global $test;
	if (!$condition) throw new RuntimeException($message);
	$test['passed']++;
}

foreach (['Config','Score','Result','SessionContext','Repository','Overview','Installer'] as $test['class']) require_once(dirname(__DIR__).'/src/'.$test['class'].'.php');

$site = ['ficms_version'=>'test'];
$tables = ['accessibility_audits'=>'accessibility_audits'];
$_SERVER['now'] = 1_000;
$_SESSION = [];

expect(\accessibility\Installer::schema() === true,'Plugin schema failed');
expect($test['mysql_check']['delete'] === ['date'],'Plugin schema retained the legacy date column');
expect(!isset($test['mysql_check']['force']['result'],$test['mysql_check']['force']['scores']),'Plugin schema bypassed automatic text sizing');

$test['scores'] = [];
foreach (\accessibility\Config::CATEGORIES as $test['category']) $test['scores'][$test['category']] = ['total'=>1,'success'=>1,'warning'=>0,'error'=>0];
$test['scores']['media_alt'] = ['total'=>1,'success'=>0,'warning'=>0,'error'=>1];
$test['payload'] = json_encode([
	'scores'=>$test['scores'],
	'accessibility'=>[
		'error'=>['_accessibility_media_alt_missing'=>[['_id'=>'ignored','id'=>'finding','name'=>'<img src="private.jpg">','value'=>'private alt','image'=>'data:image/png;base64,secret','unique'=>'main img']]],
		'warning'=>[]
	],
	'stats'=>['totalElements'=>20,'collectedElements'=>10,'checkRuns'=>40,'private'=>'discard'],
	'private'=>'discard'
]);
$test['result'] = \accessibility\Result::fromPayload($test['payload'],['mid'=>10,'tid'=>0,'lid'=>'de','mobile'=>0,'path'=>'/test']);
expect($test['result'] instanceof \accessibility\Result,'Valid audit payload was rejected');
expect($test['result']->aggregate()['total'] === 6,'Audit totals were not aggregated');
$test['finding'] = $test['result']->envelope()['audit']['accessibility']['error']['_accessibility_media_alt_missing'][0];
expect($test['finding']['name'] === 'img','Element markup was not reduced to a tag name');
expect($test['finding']['value'] === false && $test['finding']['image'] === false,'Sensitive media data was retained');
expect(!isset($test['result']->envelope()['audit']['private'],$test['result']->envelope()['audit']['stats']['private']),'Unknown payload data was retained');
$test['second'] = $test['result']->envelope();
$test['second']['audit']['scores']['media_alt'] = ['total'=>1,'success'=>1,'warning'=>0,'error'=>0];
$test['overview'] = \accessibility\Overview::summarize([
	['result'=>$test['result']->envelope(),'mid'=>10,'tid'=>0,'lid'=>'de','mobile'=>0,'audit_time'=>1_000],
	['result'=>$test['second'],'mid'=>10,'tid'=>0,'lid'=>'de','mobile'=>1,'audit_time'=>1_001]
]);
expect($test['overview']['count'] === 2 && $test['overview']['total']['total'] === 12,'Overview did not aggregate audit rows');
expect(abs($test['overview']['categories']['media_alt'] - 0.5) < 0.000001,'Overview category average is invalid');
expect($test['overview']['score'] === 89,'Overview score is invalid');
expect($test['overview']['findings']['error']['_accessibility_media_alt_missing']['main img']['device'] === 'both','Overview did not merge viewport findings');

$test['invalid'] = json_decode($test['payload'],true);
$test['invalid']['scores']['semantic']['total'] = 2;
expect(\accessibility\Result::fromPayload(json_encode($test['invalid']),['mid'=>10,'tid'=>0,'lid'=>'de','mobile'=>0,'path'=>'/test']) === false,'Invalid category totals were accepted');
$test['invalid'] = json_decode($test['payload'],true);
unset($test['invalid']['scores']['semantic']);
expect(\accessibility\Result::fromPayload(json_encode($test['invalid']),['mid'=>10,'tid'=>0,'lid'=>'de','mobile'=>0,'path'=>'/test']) === false,'Incomplete category set was accepted');

$test['context'] = ['mid'=>10,'tid'=>0,'lid'=>'de','mobile'=>0,'path'=>'/test'];
\accessibility\SessionContext::create($test['context']);
expect(\accessibility\SessionContext::consume(11,0,'de') === false,'Audit context was consumed for a different page');
expect(\accessibility\SessionContext::consume(10,0,'de') === $test['context'],'Audit context changed');
expect(\accessibility\SessionContext::consume(10,0,'de') === false,'Audit context was consumed twice');
\accessibility\SessionContext::create($test['context']);
$_SERVER['now'] += 300;
expect(\accessibility\SessionContext::consume(10,0,'de') === false,'Expired audit context was accepted');

$test['mysql_result'] = null;
expect(\accessibility\Repository::isFresh($test['context'],10) === false,'Missing audit was treated as fresh');
expect(str_contains($test['last_query'],'`created_at` >= FROM_UNIXTIME(') && !str_contains($test['last_query'],'`date`'),'Freshness does not use native timestamps');
$test['mysql_result'] = ['id'=>1];
expect(\accessibility\Repository::isFresh($test['context'],10) === true,'Existing audit was not treated as fresh');

echo 'OK '.$test['passed']." assertions\n";
