<?php

namespace accessibility;

class Overview {
	public static function current(int $before = 0): array {
		if (!class_exists('\\ficms\\Assessment')) return self::deprecated()::current($before);
		return self::summarize(Repository::rows($before));
	}

	public static function summarize(array $rows): array {
		if (!class_exists('\\ficms\\Assessment')) return self::deprecated()::summarize($rows);
		$snapshots = [];
		foreach ($rows as $row) {
			$audit = $row['result']['audit'] ?? null;
			if (!is_array($audit) || !is_array($audit['scores'] ?? null) || !is_array($audit['accessibility'] ?? null)) continue;
			$aggregate = Score::aggregate($audit['scores']);
			if ($aggregate === false) continue;
			$snapshots[] = [
				'categories'=>$aggregate['categories'],
				'total'=>array_intersect_key($aggregate,array_flip(['total','success','warning','error'])),
				'findings'=>$audit['accessibility'],
				'device'=>((int) ($row['mobile'] ?? 0) === 1) ? 'mobile' : 'desktop',
				'date'=>(int) ($row['audit_time'] ?? 0)
			];
		}
		return \ficms\Assessment::summarize($snapshots);
	}

	public static function score(array $rows): int|false {
		if (!class_exists('\\ficms\\Assessment')) return self::deprecated()::score($rows);
		$categories = self::categories($rows);
		return $categories ? \ficms\Assessment::score(array_values($categories)) : false;
	}

	public static function metrics(array $overview, string $language): array {
		if (!class_exists('\\ficms\\Assessment')) return self::deprecated()::metrics($overview,$language);
		return \ficms\Assessment::metrics($overview,$language,'accessibility');
	}

	private static function deprecated(): string {
		require_once PLUGINPATH.'/fiCMS-accessibility/deprecated/src/Overview.php';
		return DeprecatedOverview::class;
	}

	private static function categories(array $rows): array {
		$totals = [];
		foreach ($rows as $scores) {
			if (!is_array($scores) || array_diff(Config::CATEGORIES,array_keys($scores)) || array_diff(array_keys($scores),Config::CATEGORIES)) continue;
			foreach ($scores as $score) if (!is_numeric($score) || $score < 0 || $score > 1) continue 2;
			foreach ($scores as $category => $score) {
				if (!isset($totals[$category])) $totals[$category] = ['total'=>0,'count'=>0];
				$totals[$category]['total'] += $score;
				$totals[$category]['count']++;
			}
		}
		$result = [];
		foreach ($totals as $category => $values) $result[$category] = $values['total'] / $values['count'];
		return $result;
	}
}
