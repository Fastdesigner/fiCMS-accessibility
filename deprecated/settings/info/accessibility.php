<?php

if (!$site['onsite']) return;

if (isset($_POST['accessibility_result'])) {
	$accessibility = ['stored'=>false];
	if (\accessibility\Config::enabled() && \accessibility\License::allowed() && \accessibility\Repository::available()) $accessibility['stored'] = \accessibility\Collector::acceptPending(
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

$accessibility = [
	'output'=>['lists'=>[]],
	'entries'=>['overview'=>[],'statistics'=>[]],
	'attributes'=>['overview'=>['classes'=>['forms__wrapper']],'statistics'=>['classes'=>['forms__wrapper']]],
	'tablist'=>[],'info'=>[],'statistics_items'=>[],'page_scores'=>[]
];
if (!\accessibility\License::allowed()) {
	create__limit_output($settings,'accessibility',$user['language']);
	unset($accessibility);
	return;
}
if (!\accessibility\Repository::available()) {
	unset($accessibility);
	return;
}

require_once PLUGINPATH.'/fiCMS-accessibility/deprecated/src/Overview.php';
$accessibility['overview'] = \accessibility\DeprecatedOverview::current();
$accessibility['metrics'] = \accessibility\DeprecatedOverview::metrics($accessibility['overview'],$user['language']);
$accessibility['info'][] = [
	'type'=>'statistics','chart'=>'pie',
	'attributes'=>['data-span'=>'all','data-style'=>'progress','data-value'=>round($accessibility['overview']['score']),'data-label'=>language__get($user['language'],'_accessibility_score')],
	'values'=>$accessibility['metrics']
];
foreach ($accessibility['overview']['total'] as $key => $value) $accessibility['info'][] = ['id'=>$settings['key'].'_accessibility_'.$key,'type'=>'statistics','chart'=>'info','values'=>['value'=>(int) $value,'label'=>language__get($user['language'],'_accessibility_'.$key)]];
$accessibility['entries']['overview'][] = ['id'=>$settings['key'].'Info','classes'=>['statistics__wrapper'],'items'=>$accessibility['info']];
$accessibility['items'] = \accessibility\DeprecatedOverview::settingsList($accessibility['overview'],$user['language']);
if (!$accessibility['items']) $accessibility['items'][] = ['id'=>$settings['key'].'-noresult','tag'=>'font','description'=>language__get($user['language'],'_accessibility_no_results_yet')];
$accessibility['entries']['overview'][] = ['id'=>$settings['key'].'Result','classes'=>['forms__wrapper'],'items'=>$accessibility['items']];
$accessibility['entries']['overview'][] = [
	'id'=>$settings['key'].'Summary','tag'=>'font',
	'description'=>language__get_parsed($user['language'],'_accessibility_summary',[
		'pages'=>$accessibility['overview']['count'],
		'last'=>$accessibility['overview']['last'] ? format__date_relative($accessibility['overview']['last']) : '-'
	])
];

\accessibility\Statistics::sync();
$accessibility['statistics_data'] = \accessibility\Statistics::data();
if (!$accessibility['statistics_data']['days']) {
	$accessibility['entries']['statistics'][] = ['id'=>$settings['key'].'-statistics-empty','tag'=>'font','description'=>language__get($user['language'],'_accessibility_statistics_empty')];
} else {
	$accessibility['statistics_items'][] = [
		'type'=>'statistics','chart'=>'graph',
		'attributes'=>['data-span'=>'all','data-label'=>language__get($user['language'],'_accessibility_statistics_report_history')],
		'values'=>statistics__format_graph($user['language'],\accessibility\Statistics::reportGraph($user['language']),['reports'=>'_accessibility_statistics_reports'],['gridLines'=>4])
	];
	$accessibility['score_labels'] = [];
	foreach (\accessibility\Config::CATEGORIES as $accessibility['category']) $accessibility['score_labels'][$accessibility['category']] = '_accessibility_'.$accessibility['category'];
	$accessibility['statistics_items'][] = [
		'type'=>'statistics','chart'=>'graph',
		'attributes'=>['data-span'=>'all','data-label'=>language__get($user['language'],'_accessibility_statistics_category_history')],
		'values'=>statistics__format_graph($user['language'],\accessibility\Statistics::categoryGraph($user['language']),$accessibility['score_labels'],['max'=>100,'unit'=>'%','legend'=>true,'gridLines'=>4])
	];
	foreach (\accessibility\Statistics::pageScores() as $accessibility['page_score']) $accessibility['page_scores'][] = [
		'label'=>$accessibility['page_score']['label'],
		'value'=>$accessibility['page_score']['value'],
		'color'=>$accessibility['page_score']['value'] < 60 ? '#c62828' : ($accessibility['page_score']['value'] < 80 ? '#d99e3a' : '#6aa84f')
	];
	if ($accessibility['page_scores']) $accessibility['statistics_items'][] = [
		'type'=>'statistics','chart'=>'bars',
		'attributes'=>['data-span'=>'all','data-label'=>language__get($user['language'],'_accessibility_statistics_page_scores')],
		'values'=>['max'=>100,'rows'=>$accessibility['page_scores']]
	];
	$accessibility['entries']['statistics'][] = ['id'=>$settings['key'].'Statistics','classes'=>['statistics__wrapper'],'items'=>$accessibility['statistics_items']];
}

$accessibility['tablist'] = [
	'overview'=>language__get($user['language'],'_accessibility_tab_overview'),
	'statistics'=>language__get($user['language'],'_accessibility_tab_statistics')
];
$accessibility['tabs'] = create__tablist($settings['key'],$accessibility['tablist'],$accessibility['entries'],$accessibility['attributes']);
$accessibility['output']['lists'][$settings['key'].'Content'] = ['id'=>$settings['key'].'Content','items'=>[$accessibility['tabs']['tabs'],$accessibility['tabs']['panels']]];

foreach ($accessibility['output'] as $key => $value) {
	if (!$value) continue;
	if (!isset($settings['output'][$key])) $settings['output'][$key] = [];
	$settings['output'][$key] = array_merge($settings['output'][$key],$value);
}

unset($accessibility);
