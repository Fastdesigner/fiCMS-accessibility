<?php

if (!defined('PLUGINPATH')) define('PLUGINPATH','system/plugins');
if (!defined('DESIGNSYSTEM')) define('DESIGNSYSTEM',dirname(__DIR__,2).'/fiCMS-ui/designs/all');

$test = ['passed'=>0,'dropped'=>[]];

class TestFiles {
	public static array $json = [];
	public static array $directories = [];

	public static function readJson(string $path): array {
		return self::$json[$path] ?? [];
	}

	public static function writeJson(string $path, array $content, bool $secure = false, bool $lock = false): bool {
		self::$json[$path] = $content;
		return true;
	}

	public static function updateJson(string $path, callable $update, bool $secure = false): array|false {
		self::$json[$path] = $update(self::$json[$path] ?? []);
		return is_array(self::$json[$path]) ? self::$json[$path] : false;
	}

	public static function ensureDirectory(string $path, bool $secure = false): bool {
		self::$directories[$path] = true;
		return true;
	}

	public static function delete(string $path, bool $cleanupEmptyParents = true): bool {
		if (!isset(self::$json[$path])) return false;
		unset(self::$json[$path]);
		return true;
	}
}

class_alias(TestFiles::class,'ficms\\Files');

function mysqlDrop($table) {
	global $tables, $test;
	if (!isset($tables[$table])) return false;
	unset($tables[$table]);
	$test['dropped'][] = $table;
	return true;
}

function helper__json_convert($value) {
	if (is_array($value)) return $value;
	$result = json_decode((string) $value,true);
	return is_array($result) ? $result : $value;
}

function language__get(string $language, string $key): string {
	return $language.':'.$key;
}

function language__get_parsed(string $language, string $key, array $values = [], bool $fallback = false, bool $plain = false): string {
	$result = language__get($language,$key);
	foreach ($values as $name => $value) $result = str_replace('%'.$name.'%',(string) $value,$result);
	return $result;
}

function format__date_relative(int $timestamp): string {
	return 'date:'.$timestamp;
}

function mcp__task_language(array $task): string {
	return (string) ($task['instruction_vars']['language'] ?? '');
}

function statistics__step_label(string $language, int $timestamp): string {
	return $language.':'.$timestamp;
}

function statistics__format_graph(string $language, array $data, array $labels = [], array $options = []): array {
	return array_merge(['series'=>$data['series'] ?? [],'points'=>$data['points'] ?? []],$options);
}

function expect($condition, string $message): void {
	global $test;
	if (!$condition) throw new RuntimeException($message);
	$test['passed']++;
}

require_once(dirname(__DIR__,2).'/fiCMS-ui/include/classes/fiCMS/src/Assessment.php');
require_once(dirname(__DIR__,2).'/fiCMS-ui/include/classes/fiCMS/src/Ui/Node.php');
require_once(dirname(__DIR__,2).'/fiCMS-ui/include/classes/fiCMS/src/Ui.php');
foreach (['Config','License','Score','Result','SessionContext','Repository','Overview','Installer','Statistics','McpView'] as $test['class']) require_once(dirname(__DIR__).'/src/'.$test['class'].'.php');

$site = ['ficms_version'=>'test','onsite'=>1,'default_language'=>'de'];
$tables = ['accessibility_audits'=>'accessibility_audits','rewrites_accessibility'=>'rewrites_accessibility'];
$user = ['language'=>'de'];
$_SERVER['now'] = 1_700_000_000;
$_SERVER['today'] = strtotime('today',$_SERVER['now']);
$_SESSION = [];

expect(\accessibility\Installer::run() === true,'Plugin file storage installation failed');
expect($test['dropped'] === ['accessibility_audits','rewrites_accessibility'],'Former audit tables were not removed');
expect($tables === [],'Former audit tables remained registered');
expect(isset(TestFiles::$directories[\accessibility\Config::dataPath()]),'Plugin data directory was not initialized');

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
$test['context'] = ['mid'=>10,'tid'=>0,'lid'=>'de','mobile'=>0,'path'=>'/test'];
$test['result'] = \accessibility\Result::fromPayload($test['payload'],$test['context']);
expect($test['result'] instanceof \accessibility\Result,'Valid audit payload was rejected');
expect($test['result']->aggregate()['total'] === 6,'Audit totals were not aggregated');
$test['finding'] = $test['result']->envelope()['audit']['accessibility']['error']['_accessibility_media_alt_missing'][0];
expect($test['finding']['name'] === 'img','Element markup was not reduced to a tag name');
expect($test['finding']['value'] === false && $test['finding']['image'] === false,'Sensitive media data was retained');
expect(!isset($test['result']->envelope()['audit']['private'],$test['result']->envelope()['audit']['stats']['private']),'Unknown payload data was retained');

$test['invalid'] = json_decode($test['payload'],true);
$test['invalid']['scores']['semantic']['total'] = 2;
expect(\accessibility\Result::fromPayload(json_encode($test['invalid']),$test['context']) === false,'Invalid category totals were accepted');
$test['invalid'] = json_decode($test['payload'],true);
unset($test['invalid']['scores']['semantic']);
expect(\accessibility\Result::fromPayload(json_encode($test['invalid']),$test['context']) === false,'Incomplete category set was accepted');

\accessibility\SessionContext::create($test['context']);
expect(\accessibility\SessionContext::consume(11,0,'de') === false,'Audit context was consumed for a different page');
expect(\accessibility\SessionContext::consume(10,0,'de') === $test['context'],'Audit context changed');
expect(\accessibility\SessionContext::consume(10,0,'de') === false,'Audit context was consumed twice');
\accessibility\SessionContext::create($test['context']);
$_SERVER['now'] += 300;
expect(\accessibility\SessionContext::consume(10,0,'de') === false,'Expired audit context was accepted');
$_SERVER['now'] -= 300;

expect(\accessibility\Repository::isFresh($test['context'],10) === false,'Missing audit was treated as fresh');
expect(\accessibility\Repository::store($test['result']) === true,'Desktop audit was not stored');
expect(\accessibility\Statistics::record() === true,'Desktop audit statistics were not stored');
expect(\accessibility\Repository::isFresh($test['context'],10) === true,'Stored audit was not treated as fresh');

$test['mobile_payload'] = json_decode($test['payload'],true);
$test['mobile_payload']['scores']['media_alt'] = ['total'=>1,'success'=>1,'warning'=>0,'error'=>0];
$test['mobile_payload']['accessibility']['error'] = [];
$test['mobile_context'] = array_merge($test['context'],['mobile'=>1]);
$test['mobile_result'] = \accessibility\Result::fromPayload(json_encode($test['mobile_payload']),$test['mobile_context']);
$_SERVER['now']++;
expect($test['mobile_result'] instanceof \accessibility\Result && \accessibility\Repository::store($test['mobile_result']) === true,'Mobile audit was not stored');
expect(\accessibility\Statistics::record() === true,'Mobile audit statistics were not stored');

$test['rows'] = \accessibility\Repository::pageRows(10,0,'de');
expect(count($test['rows']) === 2,'Page audit repository did not return both viewports');
$test['overview'] = \accessibility\Overview::summarize($test['rows']);
expect($test['overview']['count'] === 2 && $test['overview']['total']['total'] === 12,'Overview did not aggregate file-backed audit rows');
expect(abs($test['overview']['categories']['media_alt'] - 0.5) < 0.000001,'Overview category average is invalid');
expect($test['overview']['score'] === 89,'Overview score is invalid');
expect($test['overview']['findings']['error']['_accessibility_media_alt_missing']['main img']['device'] === 'desktop','Overview finding viewport is invalid');

$test['statistics'] = \accessibility\Statistics::data();
$test['today'] = $test['statistics']['days'][(string) $_SERVER['today']];
expect($test['today']['score'] === 89 && $test['today']['media_alt'] === 50,'Daily score statistics are invalid');
expect($test['today']['audits'] === 2 && $test['today']['checks'] === 12 && $test['today']['check_runs'] === 80,'Daily audit activity is invalid');
expect($test['statistics']['totals'] === ['audits'=>2,'check_runs'=>80,'checks'=>12],'Statistics totals are invalid');
$test['pages'] = \accessibility\Statistics::pageScores();
expect(count($test['pages']) === 1 && $test['pages'][0]['value'] === 89,'Per-page score statistics are invalid');
expect(count(\accessibility\Statistics::scoreGraph('de')['points']) === 1 && count(\accessibility\Statistics::activityGraph('de')['points']) === 1,'Statistics graphs are invalid');
unset(TestFiles::$json[\accessibility\Config::dataPath('statistics.json')]);
expect(\accessibility\Statistics::sync() === true && \accessibility\Statistics::data()['totals']['audits'] === 2,'Daily statistics were not rebuilt from audit indexes');

$html = ['is_superviser'=>1];
$settings = ['key'=>'accessibility','output'=>[]];
$_POST = [];
$_GET = [];
include dirname(__DIR__).'/settings/info/accessibility.php';
$test['ui'] = $settings['output']['lists']['accessibilityContent'] ?? [];
expect(($test['ui']['items'][0]['type'] ?? '') === 'tabs' && count($test['ui']['items'][0]['tabs'] ?? []) === 2,'Accessibility admin tabs are invalid');
$test['statistics_ui'] = $test['ui']['items'][0]['tabs'][1]['items'][0]['items'] ?? [];
expect(array_column($test['statistics_ui'],'chart') === ['graph','graph','info','info','info','bars'],'Accessibility statistics charts are incomplete');
expect(($test['statistics_ui'][5]['values']['max'] ?? 0) === 100 && count($test['statistics_ui'][5]['values']['rows'] ?? []) === 1,'Per-page score chart is invalid');

foreach (['summary','pages','page:10-0-de'] as $test['mcp_id']) $test['mcp'][$test['mcp_id']] = \accessibility\McpView::read($test['mcp_id'],'de');
expect($test['mcp']['summary']['coverage']['audited_pages'] === 1 && $test['mcp']['summary']['coverage']['audited_contexts'] === 2,'MCP summary coverage is invalid');
expect($test['mcp']['summary']['assessment']['score_percent'] === 89,'MCP summary score is invalid');
expect($test['mcp']['summary']['finding_groups']['error'][0]['occurrences'] === 1 && $test['mcp']['summary']['finding_groups']['error'][0]['affected_pages'] === 1,'MCP summary finding counts are invalid');
expect($test['mcp']['pages']['pages'][0]['audited_viewports'] === ['desktop','mobile'] && $test['mcp']['pages']['pages'][0]['missing_viewports'] === [],'MCP page coverage did not merge viewports');
expect(isset($test['mcp']['page:10-0-de']['audits']['desktop'],$test['mcp']['page:10-0-de']['audits']['mobile']),'MCP page detail did not expose both audits');
expect($test['mcp']['page:10-0-de']['audits']['desktop']['findings']['error'][0]['items'][0]['selector'] === 'main img','MCP page detail lost the sanitized selector');
expect($test['mcp']['page:10-0-de']['audits']['desktop']['findings']['error'][0]['items'][0]['element'] === 'img' && $test['mcp']['page:10-0-de']['audits']['desktop']['findings']['error'][0]['items'][0]['value'] === false,'MCP page detail exposed media data');
expect(!isset($test['mcp']['page:10-0-de']['audits']['desktop']['stats']['private']),'MCP page detail exposed unknown statistics');

$mcp = ['mode'=>'capabilities','scope'=>'admin','task'=>[]];
$test['capability'] = include dirname(__DIR__).'/mcp/get/accessibility.php';
expect($test['capability']['tool'] === 'get' && $test['capability']['type'] === 'accessibility','Admin MCP capability is missing');
$mcp['scope'] = 'user';
$test['user_capability'] = include dirname(__DIR__).'/mcp/get/accessibility.php';
expect($test['user_capability'] === false,'Accessibility MCP capability leaked into user scope');
$mcp = ['mode'=>'call','scope'=>'admin','task'=>['instruction_vars'=>['language'=>'de']]];
$get = ['id'=>'summary'];
$test['handler'] = include dirname(__DIR__).'/mcp/get/accessibility.php';
expect($test['handler']['type'] === 'accessibility' && $test['handler']['id'] === 'summary','Accessibility MCP handler did not return the summary view');

$_SERVER['now'] += (\accessibility\Config::retentionDays() + 1) * 86400;
expect(\accessibility\Repository::cleanup() === 2,'Expired audit files were not removed');
expect(\accessibility\Repository::rows() === [],'Expired audit indexes were retained');
expect(\accessibility\Statistics::data()['totals']['audits'] === 2,'Daily history was removed with raw audits');

echo 'OK '.$test['passed']." assertions\n";
