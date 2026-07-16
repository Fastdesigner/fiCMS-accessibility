<?php

if (!file_exists(DESIGNSYSTEM.'/assets/js/admin/sys.js')) {
	require PLUGINPATH.'/fiCMS-accessibility/deprecated/settings/info/accessibility.php';
	return;
}
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

$accessibility = [];
if (!\accessibility\License::allowed()) {
	\ficms\Ui::emitLimit($settings,'accessibility',$user['language']);
	unset($accessibility);
	return;
}
if (!isset($tables[\accessibility\Config::TABLE])) return;

$accessibility['assessment'] = \accessibility\Overview::current();
$accessibility['ui'] = new \ficms\Ui($settings['key'],'accessibility',$user['language']);
$accessibility['metrics'] = $accessibility['ui']->listing('metrics',['kind'=>'statistics']);
$accessibility['metrics']->statistics('score','pie',\accessibility\Overview::metrics($accessibility['assessment'],$user['language']),['attrs'=>[
	'data-span'=>'all',
	'data-style'=>'progress',
	'data-value'=>round($accessibility['assessment']['score']),
	'data-label'=>language__get($user['language'],'_accessibility_score')
]]);
foreach ($accessibility['assessment']['total'] as $key => $value) $accessibility['metrics']->statistics($key,'info',['value'=>(int) $value,'label'=>language__get($user['language'],'_accessibility_'.$key)],['id'=>$settings['key'].'_accessibility_'.$key]);
$accessibility['findings'] = $accessibility['ui']->listing('findings',['kind'=>'wrapper']);
if ($accessibility['ui']->assessmentFindings($accessibility['findings'],$accessibility['assessment']) == 0) $accessibility['findings']->text('empty',language__get($user['language'],'_accessibility_no_results_yet'));
$accessibility['ui']->text('summary',language__get_parsed($user['language'],'_accessibility_summary',[
	'pages'=>$accessibility['assessment']['count'],
	'last'=>$accessibility['assessment']['last'] ? format__date_relative($accessibility['assessment']['last']) : '-'
]),['id'=>$settings['key'].'Summary']);
$accessibility['ui']->emit($settings);

unset($accessibility);
