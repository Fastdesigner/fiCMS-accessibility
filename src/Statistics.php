<?php

namespace accessibility;

class Statistics {
	public static function record(int $timestamp = 0): bool {
		if ($timestamp <= 0) $timestamp = (int) (Repository::latestDate() ?: 0);
		if ($timestamp <= 0) return true;
		$day = strtotime(date('Y-m-d',$timestamp));
		$end = strtotime('+1 day',$day);
		$rows = Repository::rows($end);
		$assessment = Overview::summarize($rows);
		$history = Repository::history($day,$end);
		$point = [
			'score'=>(int) round($assessment['score'] ?? 0),
			'last'=>(int) ($assessment['last'] ?? 0),
			'audits'=>count($history),
			'check_runs'=>0,
			'checks'=>0,
			'contexts'=>(int) ($assessment['count'] ?? 0),
			'pages'=>count(self::pageScores($rows)),
			'success'=>(int) ($assessment['total']['success'] ?? 0),
			'warning'=>(int) ($assessment['total']['warning'] ?? 0),
			'error'=>(int) ($assessment['total']['error'] ?? 0)
		];
		foreach (Config::CATEGORIES as $category) $point[$category] = (int) round(((float) ($assessment['categories'][$category] ?? 0)) * 100);
		foreach ($history as $audit) {
			$point['check_runs'] += (int) ($audit['check_runs'] ?? 0);
			$point['checks'] += (int) ($audit['total'] ?? 0);
		}
		return \ficms\Files::updateJson(Config::dataPath('statistics.json'),function($statistics) use ($day,$point) {
			$statistics['days'] = is_array($statistics['days'] ?? null) ? $statistics['days'] : [];
			$current = $statistics['days'][(string) $day] ?? [];
			if ((int) ($current['last'] ?? 0) > $point['last'] || ((int) ($current['last'] ?? 0) === $point['last'] && (int) ($current['audits'] ?? 0) > $point['audits'])) return $statistics;
			$statistics['days'][(string) $day] = $point;
			ksort($statistics['days'],SORT_NUMERIC);
			$statistics['totals'] = ['audits'=>0,'check_runs'=>0,'checks'=>0];
			foreach ($statistics['days'] as $values) foreach (array_keys($statistics['totals']) as $key) $statistics['totals'][$key] += (int) ($values[$key] ?? 0);
			return $statistics;
		}) !== false;
	}

	public static function sync(): bool {
		$latest = Repository::latestDate();
		if ($latest === false) return true;
		if ((int) (self::data()['days'][(string) strtotime(date('Y-m-d',$latest))]['last'] ?? 0) >= $latest) return true;
		return self::record($latest);
	}

	public static function data(): array {
		return array_merge(['days'=>[],'totals'=>['audits'=>0,'check_runs'=>0,'checks'=>0]],\ficms\Files::readJson(Config::dataPath('statistics.json')));
	}

	public static function scoreGraph(string $language): array {
		$graph = ['series'=>['score'=>[]],'points'=>[]];
		foreach (Config::CATEGORIES as $category) $graph['series'][$category] = [];
		foreach (self::data()['days'] as $day => $values) {
			$point = ['label'=>statistics__step_label($language,(int) $day),'data'=>[]];
			foreach (array_keys($graph['series']) as $key) $point['data'][$key] = (int) ($values[$key] ?? 0);
			$graph['points'][] = $point;
		}
		return $graph;
	}

	public static function activityGraph(string $language): array {
		$graph = ['series'=>['audits'=>[],'checks'=>[],'check_runs'=>[]],'points'=>[]];
		foreach (self::data()['days'] as $day => $values) $graph['points'][] = [
			'label'=>statistics__step_label($language,(int) $day),
			'data'=>[
				'audits'=>(int) ($values['audits'] ?? 0),
				'checks'=>(int) ($values['checks'] ?? 0),
				'check_runs'=>(int) ($values['check_runs'] ?? 0)
			]
		];
		return $graph;
	}

	public static function pageScores(?array $rows = null): array {
		$pages = [];
		foreach ($rows ?? Repository::rows() as $row) {
			$key = (int) $row['mid'].'-'.(int) $row['tid'].'-'.(string) $row['lid'];
			if (!isset($pages[$key])) $pages[$key] = [];
			$pages[$key][] = $row;
		}
		$result = [];
		foreach ($pages as $rows) {
			$assessment = Overview::summarize($rows);
			$result[] = [
				'label'=>((string) ($rows[0]['path'] ?? '') ?: (int) $rows[0]['mid'].'-'.(int) $rows[0]['tid']).' ('.strtoupper((string) $rows[0]['lid']).')',
				'value'=>(int) round($assessment['score'] ?? 0),
				'last'=>(int) ($assessment['last'] ?? 0)
			];
		}
		usort($result,fn($a,$b) => $a['value'] <=> $b['value'] ?: strnatcasecmp($a['label'],$b['label']));
		return $result;
	}
}
