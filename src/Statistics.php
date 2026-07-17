<?php

namespace accessibility;

class Statistics {
	public static function record(int $timestamp = 0): bool {
		if ($timestamp <= 0) $timestamp = (int) (Repository::latestDate() ?: 0);
		if ($timestamp <= 0) return true;
		$day = strtotime(date('Y-m-d',$timestamp));
		$end = strtotime('+1 day',$day);
		$assessment = Overview::summarize(Repository::rows($end));
		$point = [
			'last'=>(int) ($assessment['last'] ?? 0),
			'reports'=>count(Repository::history($day,$end))
		];
		foreach (Config::CATEGORIES as $category) $point[$category] = (int) round(((float) ($assessment['categories'][$category] ?? 0)) * 100);
		return \ficms\Files::updateJson(Config::dataPath('statistics.json'),function($statistics) use ($day,$point) {
			$statistics['days'] = is_array($statistics['days'] ?? null) ? $statistics['days'] : [];
			$current = $statistics['days'][(string) $day] ?? [];
			if ((int) ($current['last'] ?? 0) > $point['last'] || ((int) ($current['last'] ?? 0) === $point['last'] && (int) ($current['reports'] ?? $current['audits'] ?? 0) > $point['reports'])) return $statistics;
			$statistics['days'][(string) $day] = $point;
			ksort($statistics['days'],SORT_NUMERIC);
			unset($statistics['totals']);
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
		return array_merge(['days'=>[]],\ficms\Files::readJson(Config::dataPath('statistics.json')));
	}

	public static function reportGraph(string $language): array {
		$graph = ['series'=>['reports'=>[]],'points'=>[]];
		foreach (self::data()['days'] as $day => $values) $graph['points'][] = [
			'label'=>statistics__step_label($language,(int) $day),
			'data'=>['reports'=>(int) ($values['reports'] ?? $values['audits'] ?? 0)]
		];
		return $graph;
	}

	public static function categoryGraph(string $language): array {
		$graph = ['series'=>[],'points'=>[]];
		foreach (Config::CATEGORIES as $category) $graph['series'][$category] = [];
		foreach (self::data()['days'] as $day => $values) {
			$point = ['label'=>statistics__step_label($language,(int) $day),'data'=>[]];
			foreach (array_keys($graph['series']) as $key) $point['data'][$key] = (int) ($values[$key] ?? 0);
			$graph['points'][] = $point;
		}
		return $graph;
	}

	public static function pageScores(): array {
		$pages = [];
		foreach (Repository::rows() as $row) {
			$key = (int) $row['mid'].'-'.(int) $row['tid'].'-'.(string) $row['lid'];
			if (!isset($pages[$key])) $pages[$key] = [];
			$pages[$key][] = $row;
		}
		$result = [];
		foreach ($pages as $rows) {
			$result[] = [
				'label'=>((string) ($rows[0]['path'] ?? '') ?: (int) $rows[0]['mid'].'-'.(int) $rows[0]['tid']).' ('.strtoupper((string) $rows[0]['lid']).')',
				'value'=>(int) round(Overview::summarize($rows)['score'] ?? 0)
			];
		}
		usort($result,fn($a,$b) => $a['value'] <=> $b['value'] ?: strnatcasecmp($a['label'],$b['label']));
		return $result;
	}
}
