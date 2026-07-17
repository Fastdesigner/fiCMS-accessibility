<?php

$test = ['mysql_result'=>false,'mysql_rows'=>[],'mysql_check'=>[],'last_query'=>'','passed'=>0];

class TestMysqlResult {
	public int $index = 0;

	public function __construct(public array $rows) {}
}

function mysqlFetchAssoc($query) {
	global $test;
	if ($query instanceof TestMysqlResult) return $query->rows[$query->index++] ?? null;
	$test['last_query'] = is_string($query) ? $query : '';
	return $test['mysql_result'];
}

function mysqlQuery($query) {
	global $test;
	$test['last_query'] = (string) $query;
	return new TestMysqlResult(array_shift($test['mysql_rows']) ?? []);
}

function mysqlCheck($table, $fields = [], $keys = [], $delete = [], $force = []) {
	global $test;
	$test['mysql_check'] = compact('table','fields','keys','delete','force');
	return [];
}

function mysqlEscape(string $value): string {
	return addslashes($value);
}

function helper__json_convert($value) {
	if (is_array($value)) return $value;
	$result = json_decode((string) $value,true);
	return is_array($result) ? $result : $value;
}

function language__get(string $language, string $key): string {
	return $language.':'.$key;
}

function mcp__task_language(array $task): string {
	return (string) ($task['instruction_vars']['language'] ?? '');
}

function expect($condition, string $message): void {
	global $test;
	if (!$condition) throw new RuntimeException($message);
	$test['passed']++;
}

require_once(dirname(__DIR__,2).'/fiCMS-ui/include/classes/fiCMS/src/Assessment.php');
foreach (['Config','License','Score','Result','SessionContext','Repository','Overview','Installer','McpView'] as $test['class']) require_once(dirname(__DIR__).'/src/'.$test['class'].'.php');

$site = ['ficms_version'=>'test','onsite'=>1,'default_language'=>'de'];
$tables = ['accessibility_audits'=>'accessibility_audits'];
$user = ['language'=>'de'];
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

$test['legacy_envelope'] = $test['result']->envelope();
$test['legacy_envelope']['engine']['version'] = 'legacy-core';
$test['legacy_envelope']['audit']['accessibility']['error']['_accessibility_media_alt_missing'][0]['name'] = '<img src="private.jpg">';
$test['legacy_envelope']['audit']['accessibility']['error']['_accessibility_media_alt_missing'][0]['value'] = 'private alt';
$test['legacy_envelope']['audit']['stats']['private'] = 'discard';
$test['rows'] = [
	[
		'id'=>1,'mid'=>10,'tid'=>0,'lid'=>'de','mobile'=>0,'path'=>'/test','schema_version'=>\accessibility\Config::SCHEMA,'engine_version'=>'legacy-core',
		'score'=>$test['result']->aggregate()['score'],'total'=>6,'success'=>5,'warning'=>0,'error'=>1,'audit_time'=>1_200,'result'=>json_encode($test['legacy_envelope'])
	],
	[
		'id'=>2,'mid'=>10,'tid'=>0,'lid'=>'de','mobile'=>1,'path'=>'/test','schema_version'=>\accessibility\Config::SCHEMA,'engine_version'=>\accessibility\Config::ENGINE_VERSION,
		'score'=>100,'total'=>6,'success'=>6,'warning'=>0,'error'=>0,'audit_time'=>1_201,'result'=>json_encode($test['second'])
	]
];
$test['mysql_rows'][] = $test['rows'];
$test['page_rows'] = \accessibility\Repository::pageRows(10,0,'de');
expect(count($test['page_rows']) === 2,'Page audit repository did not return both viewports');
expect(str_contains($test['last_query'],'`mid` = 10') && str_contains($test['last_query'],'`tid` = 0') && str_contains($test['last_query'],"`lid` = 'de'"),'Page audit repository did not filter by page context');

foreach (['summary','pages','page:10-0-de'] as $test['mcp_id']) {
	$test['mysql_rows'][] = $test['rows'];
	$test['mcp'][$test['mcp_id']] = \accessibility\McpView::read($test['mcp_id'],'de');
}
expect($test['mcp']['summary']['coverage']['audited_pages'] === 1 && $test['mcp']['summary']['coverage']['audited_contexts'] === 2,'MCP summary coverage is invalid');
expect($test['mcp']['summary']['assessment']['score_percent'] === 89,'MCP summary score is invalid');
expect($test['mcp']['summary']['finding_groups']['error'][0]['occurrences'] === 2 && $test['mcp']['summary']['finding_groups']['error'][0]['affected_pages'] === 1,'MCP summary finding counts are invalid');
expect($test['mcp']['pages']['pages'][0]['audited_viewports'] === ['desktop','mobile'] && $test['mcp']['pages']['pages'][0]['missing_viewports'] === [],'MCP page coverage did not merge viewports');
expect(isset($test['mcp']['page:10-0-de']['audits']['desktop'],$test['mcp']['page:10-0-de']['audits']['mobile']),'MCP page detail did not expose both audits');
expect($test['mcp']['page:10-0-de']['audits']['desktop']['findings']['error'][0]['items'][0]['selector'] === 'main img','MCP page detail lost the sanitized selector');
expect($test['mcp']['page:10-0-de']['audits']['desktop']['findings']['error'][0]['items'][0]['element'] === 'img' && $test['mcp']['page:10-0-de']['audits']['desktop']['findings']['error'][0]['items'][0]['value'] === false,'MCP page detail exposed legacy media data');
expect(!isset($test['mcp']['page:10-0-de']['audits']['desktop']['stats']['private']),'MCP page detail exposed unknown legacy statistics');

$mcp = ['mode'=>'capabilities','scope'=>'admin','task'=>[]];
$test['capability'] = include dirname(__DIR__).'/mcp/get/accessibility.php';
expect($test['capability']['tool'] === 'get' && $test['capability']['type'] === 'accessibility','Admin MCP capability is missing');
$mcp['scope'] = 'user';
$test['user_capability'] = include dirname(__DIR__).'/mcp/get/accessibility.php';
expect($test['user_capability'] === false,'Accessibility MCP capability leaked into user scope');
$mcp = ['mode'=>'call','scope'=>'admin','task'=>['instruction_vars'=>['language'=>'de']]];
$get = ['id'=>'summary'];
$test['mysql_rows'][] = $test['rows'];
$test['handler'] = include dirname(__DIR__).'/mcp/get/accessibility.php';
expect($test['handler']['type'] === 'accessibility' && $test['handler']['id'] === 'summary','Accessibility MCP handler did not return the summary view');

echo 'OK '.$test['passed']." assertions\n";
