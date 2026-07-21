<?php

if (!$site['onsite']) return;

foreach (['Config','Score','Repository','Overview'] as $accessibility_class) require_once dirname(__DIR__,2).'/src/'.$accessibility_class.'.php';

$accessibility = [
	'structure_file'=>'',
	'layout_paths'=>[
		DESIGNPATH.'/widgets/accessibility',
		__DIR__
	],
	'block'=>isset($service['temp']['data']['block']) && is_array($service['temp']['data']['block']) ? $service['temp']['data']['block'] : [],
	'layout'=>'statement',
	'sections'=>[],
	'block_files'=>[],
	'structure'=>[],
	'threshold'=>100,
	'categories'=>[],
	'items'=>[],
	'limited'=>0,
	'content'=>''
];

foreach ($accessibility['layout_paths'] as $accessibility['layout_path']) if (is_file($accessibility['layout_path'].'/frame.html')) { $accessibility['structure_file'] = $accessibility['layout_path'].'/frame.html'; break; }
if ($accessibility['structure_file'] == '') {
	$service['content'] = '';
	unset($accessibility);
	return;
}

$_SERVER['load_services']['accessibility'] = true;

$accessibility['layout'] = trim((string) ($accessibility['block']['option_widgetvalue'] ?? 'statement'));
if ($accessibility['layout'] == '') $accessibility['layout'] = 'statement';

foreach ($accessibility['layout_paths'] as $accessibility['layout_path']) {
	foreach (glob($accessibility['layout_path'].'/blocks/*.html', GLOB_NOSORT) ?: [] as $accessibility['section_file']) {
		$accessibility['section'] = basename($accessibility['section_file'],'.html');
		if ($accessibility['section'] != '') $accessibility['sections'][$accessibility['section']] = $accessibility['section'];
	}
}
foreach ($accessibility['sections'] as $accessibility['section']) {
	$accessibility['block_files'][$accessibility['section']] = '';
	foreach ($accessibility['layout_paths'] as $accessibility['layout_path']) if (is_file($accessibility['layout_path'].'/blocks/'.$accessibility['section'].'.html')) { $accessibility['block_files'][$accessibility['section']] = $accessibility['layout_path'].'/blocks/'.$accessibility['section'].'.html'; break; }
}
if (($accessibility['block_files'][$accessibility['layout']] ?? '') == '') $accessibility['layout'] = 'statement';
if (($accessibility['block_files'][$accessibility['layout']] ?? '') == '') {
	$service['content'] = '';
	unset($accessibility);
	return;
}

// Fragment-Cache: Output hängt an Block-Optionen (block:<id>), Sprache, den Templates und dem
// Daten-Version-Key accessibility:data — den bumpt der Audit-Store (Repository::invalidateCaches)
// bei jedem neuen/gelöschten Ergebnis. Kein mtime-Polling der Datenordner mehr. Der Page-HTML-Cache
// braucht keine Daten-Dep: er speichert den Prä-Widget-Frame, widgets.php läuft auf jedem Request danach.
$accessibility['block_id'] = (int) ($service['cache']['context']['block_id'] ?? ($accessibility['block']['id'] ?? 0));
$accessibility['definition'] = [
	'type'=>'fragment',
	'scope'=>[
		'widget'=>$service['cache']['context']['widget'] ?? ($service['temp']['data']['wert'] ?? 'accessibility'),
		'block_id'=>$accessibility['block_id'],
		'lid'=>$_SESSION['language']
	],
	'watch'=>[
		'versions'=>array_merge($accessibility['block_id'] > 0 ? ['block:'.$accessibility['block_id']] : [],['accessibility:data']),
		'values'=>[],
		'files'=>array_values(array_filter(array_merge(
			[__FILE__,$accessibility['structure_file']],
			$accessibility['block_files']
		)))
	],
	'policy'=>['cacheable'=>($accessibility['block_id'] > 0 && (!isset($service['cache']['policy']['cacheable']) || (int) $service['cache']['policy']['cacheable'] === 1)) ? 1 : 0],
	'meta'=>[]
];
$accessibility['cache_entry'] = false;
if ($accessibility['definition']['policy']['cacheable'] == 1) {
	$accessibility['cache_entry'] = $_SERVER['CacheDirector']->entry($accessibility['definition']);
	$accessibility['cache_entry']->setDefinition($accessibility['definition']);
	$accessibility['cached'] = $accessibility['cache_entry']->get();
	if ($accessibility['cached'] !== false) {
		$service['content'] = $accessibility['cached'];
		unset($accessibility);
		return;
	}
}

$accessibility['rows'] = \accessibility\Repository::rows();
$accessibility['assessment'] = \accessibility\Overview::summarize($accessibility['rows']);
$accessibility['scores'] = is_array($accessibility['assessment']['categories'] ?? null) ? $accessibility['assessment']['categories'] : [];

if ($accessibility['scores']) {
	$accessibility['structure'] = parser__file($accessibility['structure_file']);
	$accessibility['row_structure'] = parser__file($accessibility['block_files'][$accessibility['layout']]);
	$accessibility['row_template'] = $accessibility['row_structure']['frame'] ?? '';
	$accessibility['pages'] = [];
	foreach ($accessibility['rows'] as $accessibility['row']) $accessibility['pages'][(int) $accessibility['row']['mid'].'-'.(int) $accessibility['row']['tid'].'-'.(string) $accessibility['row']['lid']] = true;

	foreach (\accessibility\Config::CATEGORIES as $accessibility['category']) {
		if (!isset($accessibility['scores'][$accessibility['category']])) continue;
		$accessibility['percent'] = (int) round((float) $accessibility['scores'][$accessibility['category']] * 100);
		$accessibility['conform'] = $accessibility['percent'] >= $accessibility['threshold'];
		if (!$accessibility['conform']) $accessibility['limited']++;
		$accessibility['line'] = $accessibility['row_template'];
		$accessibility['items'][] = parser__replace($accessibility['line'],[
			'category'=>$accessibility['category'],
			'label'=>htmlspecialchars(language__get($_SESSION['language'],'_a11y_statement_cat_'.$accessibility['category']),ENT_QUOTES,'UTF-8'),
			'status'=>$accessibility['conform'] ? 'conform' : 'limited',
			'status_label'=>htmlspecialchars(language__get($_SESSION['language'],$accessibility['conform'] ? '_a11y_statement_conform' : '_a11y_statement_limited'),ENT_QUOTES,'UTF-8')
		]);
	}

	if ($accessibility['items']) {
		$accessibility['last'] = (int) ($accessibility['assessment']['last'] ?? 0);
		$accessibility['note'] = $accessibility['last'] > 0 ? htmlspecialchars(language__get_parsed($_SESSION['language'],'_a11y_statement_note',['date'=>date('d.m.Y',$accessibility['last']),'pages'=>count($accessibility['pages'])]),ENT_QUOTES,'UTF-8') : '';
		$accessibility['frame'] = $accessibility['structure']['frame'];
		$accessibility['content'] = parser__replace($accessibility['frame'],[
			'items'=>implode('',$accessibility['items']),
			'count'=>count($accessibility['items']),
			'limited_count'=>$accessibility['limited'],
			'conform_all'=>$accessibility['limited'] == 0 ? 1 : 0,
			'has_note'=>$accessibility['note'] != '' ? 1 : 0,
			'note'=>$accessibility['note']
		]);
	}
}

$service['content'] = $accessibility['content'];
// Leeren Zustand (noch keine Audits) nie cachen — er soll mit dem ersten Ergebnis sofort verschwinden
if ($accessibility['cache_entry'] && $service['content'] !== '') $accessibility['cache_entry']->set($service['content'],$accessibility['definition']['meta']);

unset($accessibility);
