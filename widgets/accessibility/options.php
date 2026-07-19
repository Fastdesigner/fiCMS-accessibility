<?php

if (!isset($block)) return [];

$accessibility_options = [
	'language'=>$GLOBALS['user']['language'],
	'options'=>[],
	'values'=>[],
	'datalists'=>[],
	'dependencies'=>['widget'=>['enable'=>['accessibility'],'disable'=>[]]],
	'layouts'=>[],
	'layout_dirs'=>[
		__DIR__.'/blocks',
		DESIGNPATH.'/widgets/accessibility/blocks'
	]
];

foreach ($accessibility_options['layout_dirs'] as $accessibility_options['layout_dir']) {
	foreach (glob($accessibility_options['layout_dir'].'/*.html', GLOB_NOSORT) ?: [] as $accessibility_options['layout_file']) {
		$accessibility_options['layout_key'] = basename($accessibility_options['layout_file'],'.html');
		if ($accessibility_options['layout_key'] == '') continue;
		$accessibility_options['layouts'][$accessibility_options['layout_key']] = ['name'=>language__get($accessibility_options['language'],'_a11y_widget_layout_'.$accessibility_options['layout_key']),'value'=>$accessibility_options['layout_key']];
	}
}
if (!isset($accessibility_options['layouts']['statement'])) $accessibility_options['layouts']['statement'] = ['name'=>language__get($accessibility_options['language'],'_a11y_widget_layout_statement'),'value'=>'statement'];
$accessibility_options['datalists']['accessibility-layouts'] = $accessibility_options['layouts'];

$accessibility_options['fields'] = [
	'widgetvalue'=>['type'=>'datalist','default'=>'statement','dynamic_name'=>'_a11y_widget_layout','include'=>true,'attributes'=>['data-list'=>'accessibility-layouts','data-exact'=>'true']]
];

foreach ($accessibility_options['fields'] as $accessibility_options['key'] => $accessibility_options['field']) {
	$accessibility_options['options'][$accessibility_options['key']] = [
		'type'=>$accessibility_options['field']['type'],
		'default'=>$accessibility_options['field']['default'],
		'dynamic_name'=>$accessibility_options['field']['dynamic_name'],
		'name'=>language__get($accessibility_options['language'],$accessibility_options['field']['dynamic_name']),
		'option'=>$accessibility_options['key'],
		'include'=>$accessibility_options['field']['include'] ?? true,
		'dependencies'=>$accessibility_options['dependencies']
	];
	if (isset($accessibility_options['field']['attributes'])) $accessibility_options['options'][$accessibility_options['key']]['attributes'] = $accessibility_options['field']['attributes'];
}

return ['options'=>$accessibility_options['options'],'values'=>$accessibility_options['values'],'datalists'=>$accessibility_options['datalists']];
