<?php

if (!file_exists(DESIGNSYSTEM.'/assets/js/admin/sys.js')) {
	require PLUGINPATH.'/fiCMS-accessibility/deprecated/settings/info/accessibility.php';
	return;
}
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

$accessibility = [];
if (!\accessibility\License::allowed()) {
	\ficms\Ui::emitLimit($settings,'accessibility',$user['language']);
	unset($accessibility);
	return;
}
if (!\accessibility\Repository::available()) return;

$accessibility['assessment'] = \accessibility\Overview::current();
$accessibility['ui'] = new \ficms\Ui($settings['key'],'accessibility',$user['language']);
$accessibility['overview_tab'] = $accessibility['ui']->tab('overview',['label'=>language__get($user['language'],'_accessibility_tab_overview')]);
$accessibility['metrics'] = $accessibility['overview_tab']->listing('metrics',['kind'=>'statistics']);
$accessibility['metrics']->statistics('score','pie',\accessibility\Overview::metrics($accessibility['assessment'],$user['language']),['attrs'=>[
	'data-span'=>'all',
	'data-style'=>'progress',
	'data-value'=>round($accessibility['assessment']['score']),
	'data-label'=>language__get($user['language'],'_accessibility_score')
]]);
foreach ($accessibility['assessment']['total'] as $key => $value) $accessibility['metrics']->statistics($key,'info',['value'=>(int) $value,'label'=>language__get($user['language'],'_accessibility_'.$key)],['id'=>$settings['key'].'_accessibility_'.$key]);
$accessibility['findings'] = $accessibility['overview_tab']->listing('findings',['kind'=>'wrapper']);
if ($accessibility['ui']->assessmentFindings($accessibility['findings'],$accessibility['assessment']) == 0) $accessibility['findings']->text('empty',language__get($user['language'],'_accessibility_no_results_yet'));
$accessibility['overview_tab']->text('summary',language__get_parsed($user['language'],'_accessibility_summary',[
	'pages'=>$accessibility['assessment']['count'],
	'last'=>$accessibility['assessment']['last'] ? format__date_relative($accessibility['assessment']['last']) : '-'
]),['id'=>$settings['key'].'Summary','html'=>true]);

$accessibility['statistics_tab'] = $accessibility['ui']->tab('statistics',['label'=>language__get($user['language'],'_accessibility_tab_statistics')]);
\accessibility\Statistics::sync();
$accessibility['statistics_data'] = \accessibility\Statistics::data();
if (!$accessibility['statistics_data']['days']) {
	$accessibility['statistics_tab']->text('statistics-empty',language__get($user['language'],'_accessibility_statistics_empty'));
} else {
	$accessibility['statistics'] = $accessibility['statistics_tab']->listing('statistics',['kind'=>'statistics']);
	$accessibility['statistics']->statistics('report-history','graph',statistics__format_graph(
		$user['language'],
		\accessibility\Statistics::reportGraph($user['language']),
		['reports'=>'_accessibility_statistics_reports'],
		['gridLines'=>4]
	),['attrs'=>['data-span'=>'all','data-label'=>language__get($user['language'],'_accessibility_statistics_report_history')]]);
	$accessibility['score_labels'] = [];
	foreach (\accessibility\Config::CATEGORIES as $accessibility['category']) $accessibility['score_labels'][$accessibility['category']] = '_accessibility_'.$accessibility['category'];
	$accessibility['statistics']->statistics('category-history','graph',statistics__format_graph(
		$user['language'],
		\accessibility\Statistics::categoryGraph($user['language']),
		$accessibility['score_labels'],
		['max'=>100,'unit'=>'%','legend'=>true,'gridLines'=>4]
	),['attrs'=>['data-span'=>'all','data-label'=>language__get($user['language'],'_accessibility_statistics_category_history')]]);
	$accessibility['page_scores'] = [];
	foreach (\accessibility\Statistics::pageScores() as $accessibility['page_score']) $accessibility['page_scores'][] = [
		'label'=>$accessibility['page_score']['label'],
		'value'=>$accessibility['page_score']['value'],
		'color'=>$accessibility['page_score']['value'] < 60 ? '#c62828' : ($accessibility['page_score']['value'] < 80 ? '#d99e3a' : '#6aa84f')
	];
	if ($accessibility['page_scores']) $accessibility['statistics']->statistics('page-scores','bars',['max'=>100,'rows'=>$accessibility['page_scores']],['attrs'=>[
		'data-span'=>'all',
		'data-label'=>language__get($user['language'],'_accessibility_statistics_page_scores')
	]]);
}
$accessibility['ui']->emit($settings);

unset($accessibility);
