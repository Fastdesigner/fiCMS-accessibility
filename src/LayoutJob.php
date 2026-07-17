<?php

namespace accessibility;

final class LayoutJob {
	private const PREFIX = 'accessibility-';

	public static function active(): array|false {
		if (!class_exists('\ficms\Jobs')) return false;
		foreach (\ficms\Jobs::openLayoutJobs(true) as $key => $job) {
			if (!str_starts_with((string) $key,self::PREFIX) || ($job['state'] ?? '') === 'resolved') continue;
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
		$snapshot = self::snapshot($rows,$language);
		$key = self::PREFIX.substr(hash('sha256',json_encode(self::identity($rows),JSON_UNESCAPED_SLASHES)),0,12);
		try {
			$job = \ficms\Jobs::announceLayoutJob([
				'key'=>$key,
				'title'=>language__get($language,'_accessibility_job_title'),
				'description'=>language__get($language,'_accessibility_job_description'),
				'source'=>'accessibility',
				'instruction_key'=>'accessibility',
				'todo'=>'migrations/'.$key.'.md',
				'todo_content'=>self::markdown($snapshot),
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

	private static function identity(array $rows): array {
		$identity = [];
		foreach ($rows as $row) $identity[] = array_intersect_key($row,array_flip(['mid','tid','lid','mobile','audit_time','schema_version','engine_version']));
		return $identity;
	}

	private static function snapshot(array $rows, string $language): array {
		$pages = McpView::read('pages',$language);
		$details = [];
		foreach ($pages['pages'] ?? [] as $page) {
			$id = (string) ($page['midtidlid'] ?? '');
			if ($id !== '') $details[] = McpView::read('page:'.$id,$language);
		}
		return [
			'type'=>'accessibility-layout-job',
			'created'=>(int) ($_SERVER['now'] ?? time()),
			'schema'=>Config::SCHEMA,
			'engine'=>Config::ENGINE_VERSION,
			'summary'=>McpView::read('summary',$language),
			'pages'=>$details
		];
	}

	private static function markdown(array $snapshot): string {
		return '# Barrierefreiheitsprobleme beheben'.PHP_EOL.PHP_EOL.
			'Dieser Auftrag wurde vom Webseitenbetreiber aus dem Accessibility-Adminbereich erteilt.'.PHP_EOL.PHP_EOL.
			'## Auftrag'.PHP_EOL.PHP_EOL.
			'1. Prüfe die gemeldeten Fundstellen und ordne sie Layout oder redaktionellem Inhalt zu.'.PHP_EOL.
			'2. Behebe alle layoutbedingten Probleme in den Layout-Dateien.'.PHP_EOL.
			'3. Halte verbleibende Inhaltsprobleme als Notiz am Layout-Job fest.'.PHP_EOL.
			'4. Rufe anschließend alle aufgeführten Seiten in Desktop- und Mobile-Ansicht auf, damit vollständige neue Prüfberichte entstehen.'.PHP_EOL.PHP_EOL.
			'Der Checker schließt den Auftrag erst, wenn alle zuvor erfassten Kontexte nach Auftragserstellung neu geprüft wurden und keine Warnungen oder Fehler mehr enthalten.'.PHP_EOL.PHP_EOL.
			'## Prüfsnapshot'.PHP_EOL.PHP_EOL.'````json'.PHP_EOL.
			json_encode($snapshot,JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL.'````'.PHP_EOL;
	}
}
