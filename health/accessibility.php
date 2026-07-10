<?php

if (!$site['onsite'] || !\accessibility\Config::enabled() || !\accessibility\License::allowed()) return;

$accessibility = ['score'=>\accessibility\Repository::healthScore()];
if ($accessibility['score'] === false) {
	unset($accessibility);
	return;
}
$accessibility['difference'] = 100 - $accessibility['score'];
$accessibility['scores'] = ['total'=>100,'success'=>$accessibility['score'],'warning'=>0,'error'=>0];
$accessibility['health'] = ['error'=>[],'warning'=>[]];
if ($accessibility['difference'] > 20) {
	$accessibility['scores']['error'] = $accessibility['difference'];
	$accessibility['health']['error']['_health_legal_accessibility'] = [['name'=>'_health_legal_accessibility_item','value'=>$accessibility['score']]];
} else if ($accessibility['difference'] > 0) {
	$accessibility['scores']['warning'] = $accessibility['difference'];
	$accessibility['health']['warning']['_health_legal_accessibility'] = [['name'=>'_health_legal_accessibility_item','value'=>$accessibility['score']]];
}
$healthContribution = ['category'=>'legal','key'=>'accessibility','weight'=>1,'score'=>$accessibility['score'] / 100,'scores'=>$accessibility['scores'],'health'=>$accessibility['health']];

unset($accessibility);
return $healthContribution;
