<?php

if (!$site['onsite'] || !\accessibility\Repository::available() || !\accessibility\License::allowed()) return;

if ($reports['mode'] == 'meta') {
	$reports['meta'] = ['active'=>0,'opt_label'=>'accessibility','selfsend'=>1,'schedule'=>['every'=>'month','offset'=>1]];
	return;
}
if ($reports['mode'] != 'send') return;

$accessibility = [
	'latest'=>\accessibility\Repository::latestDate(),
	'overview'=>\accessibility\Overview::current(),
	'notify_types'=>['error','warning']
];
if ($accessibility['latest'] === false || $accessibility['overview']['count'] === 0) {
	unset($accessibility);
	return;
}
$accessibility['previous'] = \accessibility\Overview::current($accessibility['latest']);

foreach ($reports['recipients'] as $accessibility['email'] => $accessibility['value']) {
	$accessibility['last'] = is_numeric($accessibility['value']['report']['last'] ?? null) ? (int) $accessibility['value']['report']['last'] : 0;
	if ($accessibility['last'] >= $accessibility['latest']) continue;
	$reports['has_values'] = 1;
	$accessibility['lang'] = !empty($accessibility['value']['user']['language']) ? $accessibility['value']['user']['language'] : $site['default_language'];
	$accessibility['value_score'] = (int) round($accessibility['overview']['score']);
	$accessibility['previous_score'] = ($accessibility['previous']['count'] > 0) ? (int) round($accessibility['previous']['score']) : false;
	$accessibility['list'] = [
		['feature'=>'lead','data'=>['titlekey'=>'_reports_accessibility','title'=>'','desckey'=>'_reports_accessibility_description','text'=>'','hasassess'=>'','assess'=>[]]],
		['feature'=>'score','data'=>[
			'titlekey'=>'','title'=>'','desckey'=>'',
			'value'=>(string) $accessibility['value_score'],'color'=>reports__score_color($accessibility['value_score']),'labelkey'=>'_accessibility_score',
			'delta'=>($accessibility['previous_score'] !== false) ? reports__delta($accessibility['value_score'],$accessibility['previous_score']) : '',
			'metric'=>reports__metrics(\accessibility\Overview::metrics($accessibility['overview'],$accessibility['lang']),($accessibility['previous_score'] !== false) ? \accessibility\Overview::metrics($accessibility['previous'],$accessibility['lang']) : [])
		]]
	];

	$accessibility['checks'] = $accessibility['overview']['total']['success'] + $accessibility['overview']['total']['warning'] + $accessibility['overview']['total']['error'];
	if ($accessibility['checks'] > 0) {
		$accessibility['segments'] = $accessibility['legend'] = [];
		foreach (['success'=>['#6aa84f','_reports_passed'],'warning'=>['#d99e3a','_reports_warnings'],'error'=>['#c62828','_reports_errors']] as $accessibility['type'] => $accessibility['definition']) {
			$accessibility['count'] = $accessibility['overview']['total'][$accessibility['type']];
			if ($accessibility['count'] > 0) {
				$accessibility['width'] = round($accessibility['count'] / $accessibility['checks'] * 100,1);
				$accessibility['segments'][] = ['width'=>$accessibility['width'],'color'=>$accessibility['definition'][0],'inlabel'=>($accessibility['width'] >= 12) ? (string) $accessibility['count'] : ''];
			}
			$accessibility['legend'][] = ['color'=>$accessibility['definition'][0],'labelkey'=>$accessibility['definition'][1],'label'=>'','count'=>(string) $accessibility['count']];
		}
		$accessibility['list'][] = ['feature'=>'split','data'=>['titlekey'=>'_reports_checks','title'=>'','value'=>(string) $accessibility['checks'],'valuecolor'=>'','delta'=>'','seg'=>$accessibility['segments'],'haslegend'=>'1','legend'=>$accessibility['legend']]];
	}

	$accessibility['counts'] = [];
	foreach ($accessibility['notify_types'] as $accessibility['severity']) foreach ($accessibility['overview']['findings'][$accessibility['severity']] as $accessibility['rule'] => $accessibility['entries']) $accessibility['counts'][$accessibility['rule']] = ($accessibility['counts'][$accessibility['rule']] ?? 0) + count($accessibility['entries']);
	if ($accessibility['counts']) {
		arsort($accessibility['counts']);
		$accessibility['counts'] = array_slice($accessibility['counts'],0,5,true);
		$accessibility['maximum'] = max(1,reset($accessibility['counts']));
		$accessibility['rows'] = [];
		foreach ($accessibility['counts'] as $accessibility['rule'] => $accessibility['count']) {
			$accessibility['width'] = (int) round($accessibility['count'] / $accessibility['maximum'] * 100);
			$accessibility['rows'][] = ['name'=>language__get($accessibility['lang'],$accessibility['rule'].'_headline'),'width'=>$accessibility['width'],'rest'=>100 - $accessibility['width'],'color'=>'#d99e3a','num'=>(string) $accessibility['count'],'sub'=>''];
		}
		$accessibility['list'][] = ['feature'=>'bars','data'=>['titlekey'=>'_reports_issues_by_category','title'=>'','value'=>'','valuecolor'=>'','delta'=>'','row'=>$accessibility['rows']]];
	}

	foreach ($accessibility['notify_types'] as $accessibility['severity']) foreach ($accessibility['overview']['findings'][$accessibility['severity']] as $accessibility['rule'] => $accessibility['entries']) {
		$accessibility['items'] = [];
		foreach ($accessibility['entries'] as $accessibility['entry']) $accessibility['items'][] = reports__issue_item($accessibility['lang'],$accessibility['rule'],$accessibility['entry']);
		$accessibility['list'][] = ['feature'=>'issue','data'=>[
			'border'=>($accessibility['severity'] == 'error') ? '#c62828' : '#f57c00',
			'titlekey'=>$accessibility['rule'].'_headline','desckey'=>$accessibility['rule'],'item'=>$accessibility['items']
		]];
	}

	$reports['items'][$accessibility['email']] = ['list'=>$accessibility['list']];
}

unset($accessibility);
