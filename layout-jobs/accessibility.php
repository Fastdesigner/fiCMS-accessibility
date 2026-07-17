<?php

if (!class_exists('\accessibility\Repository') || !class_exists('\ficms\Jobs')) return;

$accessibilityLayoutJob = [
	'key'=>preg_replace('/^layout_job_|\.php$/','',basename(__FILE__)),
	'job'=>[],
	'rows'=>[]
];
if (!str_starts_with($accessibilityLayoutJob['key'],'accessibility-')) { unset($accessibilityLayoutJob); return; }
$accessibilityLayoutJob['job'] = \ficms\Jobs::openLayoutJobs(true)[$accessibilityLayoutJob['key']] ?? [];
if (!$accessibilityLayoutJob['job'] || ($accessibilityLayoutJob['job']['state'] ?? '') === 'resolved') { unset($accessibilityLayoutJob); return; }
$accessibilityLayoutJob['rows'] = \accessibility\Repository::rows();
if (!$accessibilityLayoutJob['rows']) { unset($accessibilityLayoutJob); return; }
foreach ($accessibilityLayoutJob['rows'] as $accessibilityLayoutJob['row']) if ((int) ($accessibilityLayoutJob['row']['audit_time'] ?? 0) <= (int) ($accessibilityLayoutJob['job']['created'] ?? 0)) { unset($accessibilityLayoutJob); return; }
$accessibilityLayoutJob['assessment'] = \accessibility\Overview::summarize($accessibilityLayoutJob['rows']);
if (\accessibility\LayoutJob::hasFindings($accessibilityLayoutJob['assessment'])) { unset($accessibilityLayoutJob); return; }
\ficms\Jobs::resolveLayoutJob($accessibilityLayoutJob['key'],[
	'score'=>(int) ($accessibilityLayoutJob['assessment']['score'] ?? 0),
	'pages'=>(int) ($accessibilityLayoutJob['assessment']['count'] ?? 0),
	'validated'=>(int) ($_SERVER['now'] ?? time())
]);

unset($accessibilityLayoutJob);
