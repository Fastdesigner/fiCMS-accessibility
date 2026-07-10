<?php

if (!$site['onsite']) return;

if (isset($_POST['accessibility_result'])) {
	$accessibility = ['stored'=>false];
	if (\accessibility\Config::enabled() && \accessibility\License::allowed() && isset($tables[\accessibility\Config::TABLE])) $accessibility['stored'] = \accessibility\Collector::acceptPending(
		(int) ($_GET['vmid'] ?? 0),
		(int) ($_GET['vtid'] ?? 0),
		(string) ($_GET['vlid'] ?? ''),
		(string) $_POST['accessibility_result']
	);
	$settings['output']['result'] = ['result'=>$accessibility['stored']];
	unset($accessibility);
	return;
}

if ($html['is_superviser'] != 1 || isset($_GET[$settings['key'].'action'])) return;

$accessibility = ['output'=>['lists'=>[]],'info'=>[]];
if (!\accessibility\License::allowed()) {
	create__limit_output($settings,'accessibility',$user['language']);
	unset($accessibility);
	return;
}
if (!isset($tables[\accessibility\Config::TABLE])) {
	unset($accessibility);
	return;
}

$accessibility['overview'] = \accessibility\Overview::current();
$accessibility['metrics'] = \accessibility\Overview::metrics($accessibility['overview'],$user['language']);
$accessibility['output']['lists'][$settings['key'].'Content'] = ['id'=>$settings['key'].'Content','items'=>[]];
$accessibility['info'][] = [
	'type'=>'statistics','chart'=>'pie',
	'attributes'=>['data-span'=>'all','data-style'=>'progress','data-value'=>round($accessibility['overview']['score']),'data-label'=>language__get($user['language'],'_accessibility_score')],
	'values'=>$accessibility['metrics']
];
foreach ($accessibility['overview']['total'] as $key => $value) $accessibility['info'][] = ['id'=>$settings['key'].'_accessibility_'.$key,'type'=>'statistics','chart'=>'info','values'=>['value'=>(int) $value,'label'=>language__get($user['language'],'_accessibility_'.$key)]];
$accessibility['output']['lists'][$settings['key'].'Content']['items'][] = ['id'=>$settings['key'].'Info','classes'=>['statistics__wrapper'],'items'=>$accessibility['info']];
$accessibility['items'] = \accessibility\Overview::settingsList($accessibility['overview'],$user['language']);
if (!$accessibility['items']) $accessibility['items'][] = ['id'=>$settings['key'].'-noresult','tag'=>'font','description'=>language__get($user['language'],'_accessibility_no_results_yet')];
$accessibility['output']['lists'][$settings['key'].'Content']['items'][] = ['id'=>$settings['key'].'Result','classes'=>['forms__wrapper'],'items'=>$accessibility['items']];
$accessibility['output']['lists'][$settings['key'].'Content']['items'][] = [
	'id'=>$settings['key'].'Summary','tag'=>'font',
	'description'=>language__get_parsed($user['language'],'_accessibility_summary',[
		'pages'=>$accessibility['overview']['count'],
		'last'=>$accessibility['overview']['last'] ? format__date_relative($accessibility['overview']['last']) : '-'
	])
];

foreach ($accessibility['output'] as $key => $value) {
	if (!$value) continue;
	if (!isset($settings['output'][$key])) $settings['output'][$key] = [];
	$settings['output'][$key] = array_merge($settings['output'][$key],$value);
}

unset($accessibility);
