<?php

namespace accessibility;

final class LayoutJob {
	private const KEY = 'accessibility';

	public static function active(): array|false {
		if (!class_exists('\ficms\Jobs')) return false;
		foreach (\ficms\Jobs::openLayoutJobs(true) as $key => $job) {
			if (($key !== self::KEY && !str_starts_with((string) $key,self::KEY.'-')) || ($job['state'] ?? '') === 'resolved') continue;
			return $job;
		}
		return false;
	}

	public static function request(string $language): array {
		if (!class_exists('\ficms\Jobs')) return ['result'=>false,'error'=>'jobs_unavailable'];
		if (self::active()) return ['result'=>false,'error'=>'job_exists'];
		$rows = Repository::rows();
		$assessment = Overview::summarize($rows);
		if (!$rows || !self::hasFindings($assessment)) return ['result'=>false,'error'=>'findings_missing'];
		try {
			$job = \ficms\Jobs::announceLayoutJob([
				'key'=>self::KEY,
				'title'=>language__get($language,'_accessibility_job_title'),
				'description'=>language__get($language,'_accessibility_job_description'),
				'source'=>'accessibility',
				'created'=>$_SERVER['now'],
				'checker'=>PLUGINPATH.'/fiCMS-accessibility/layout-jobs/accessibility.php'
			]);
		} catch (\Throwable $e) {
			return ['result'=>false,'error'=>$e->getMessage()];
		}
		return ['result'=>true,'job'=>$job];
	}

	public static function hasFindings(array $assessment): bool {
		return (int) ($assessment['total']['warning'] ?? 0) > 0 || (int) ($assessment['total']['error'] ?? 0) > 0;
	}
}
