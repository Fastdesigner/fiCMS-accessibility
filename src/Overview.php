<?php

namespace accessibility;

class Overview {
	public static function current(int $before = 0): array {
		return self::summarize(Repository::rows($before));
	}

	public static function summarize(array $rows): array {
		$overview = [
			'score'=>100,
			'categories'=>[],
			'total'=>['total'=>0,'success'=>0,'warning'=>0,'error'=>0],
			'findings'=>['error'=>[],'warning'=>[]],
			'count'=>0,
			'last'=>0
		];
		$categoryRows = [];
		foreach ($rows as $row) {
			$audit = $row['result']['audit'] ?? null;
			if (!is_array($audit) || !is_array($audit['scores'] ?? null) || !is_array($audit['accessibility'] ?? null)) continue;
			$aggregate = Score::aggregate($audit['scores']);
			if ($aggregate === false) continue;
			$overview['count']++;
			$overview['last'] = max($overview['last'],(int) ($row['audit_time'] ?? 0));
			$categoryRows[] = $aggregate['categories'];
			foreach ($overview['total'] as $type => $value) $overview['total'][$type] += $aggregate[$type];
			foreach (['error','warning'] as $severity) foreach ($audit['accessibility'][$severity] ?? [] as $rule => $entries) {
				if (!is_array($entries)) continue;
				if (!isset($overview['findings'][$severity][$rule])) $overview['findings'][$severity][$rule] = [];
				foreach ($entries as $entry) {
					if (!is_array($entry)) continue;
					$key = (string) ($entry['unique'] ?? $entry['name'] ?? '');
					if ($key === '') $key = (string) ($entry['id'] ?? count($overview['findings'][$severity][$rule]));
					if (!isset($overview['findings'][$severity][$rule][$key])) {
						$entry['device'] = ((int) ($row['mobile'] ?? 0) === 1) ? 'mobile' : 'desktop';
						$overview['findings'][$severity][$rule][$key] = $entry;
					} else if ($overview['findings'][$severity][$rule][$key]['device'] !== (((int) ($row['mobile'] ?? 0) === 1) ? 'mobile' : 'desktop')) {
						$overview['findings'][$severity][$rule][$key]['device'] = 'both';
					}
				}
			}
		}

		$overview['categories'] = self::categories($categoryRows);
		if ($overview['categories']) $overview['score'] = self::score($categoryRows);
		return $overview;
	}

	public static function score(array $rows): int|false {
		$categories = self::categories($rows);
		return $categories ? (int) scores__calc(array_values($categories),array_fill(0,count($categories),1)) : false;
	}

	public static function metrics(array $overview, string $language): array {
		$metrics = [];
		$count = count($overview['categories']);
		foreach ($overview['categories'] as $category => $score) $metrics[] = [
			'name'=>$category,
			'key'=>$category,
			'value'=>(int) round($score * 100),
			'segment'=>($count > 0) ? 100 / $count : 0,
			'label'=>language__get($language,'_accessibility_'.$category)
		];
		return $metrics;
	}

	public static function settingsList(array $overview, string $language): array {
		$result = [];
		foreach (['error','warning'] as $severity) foreach ($overview['findings'][$severity] as $rule => $entries) {
			$items = [['id'=>$rule.'-info','tag'=>'li','items'=>[['id'=>$rule.'-info-font','tag'=>'font','description'=>htmlspecialchars(language__get($language,$rule))]]]];
			foreach ($entries as $entry) {
				$id = 'id'.md5((string) ($entry['unique'] ?? $entry['id'] ?? $entry['name'] ?? ''));
				$item = [
					'id'=>$id,
					'description'=>str_starts_with((string) ($entry['name'] ?? ''),'_') ? language__get($language,$entry['name']) : (string) ($entry['name'] ?? ''),
					'attributes'=>['data-selector'=>(string) ($entry['unique'] ?? '')],
					'items'=>[],
					'icons'=>[]
				];
				foreach ([((string) ($entry['name'] ?? '')).'_subtitle',$rule.'_subtitle'] as $subtitleKey) {
					if ($subtitleKey === '_subtitle') continue;
					$values = $entry;
					if (isset($values['value']) && is_numeric($values['value'])) $values['value'] = round($values['value'],2);
					if (isset($values['value']) && str_contains($rule,'contrast')) $values['value'] = '1 : '.$values['value'];
					$subtitle = language__get_parsed($language,$subtitleKey,$values,true,true);
					if ($subtitle === $subtitleKey) continue;
					$item['subtitle'] = $subtitle;
					if (isset($values['value']) && $values['value'] !== false) $item['icons'][] = ['id'=>$id.'-icons-value','description'=>$values['value']];
					break;
				}
				if (($entry['device'] ?? 'both') !== 'both') $item['icons'][] = ['id'=>$id.'-icons-device','attributes'=>['data-systemicon'=>$entry['device']]];
				if ($item['icons']) $item['items'][] = ['id'=>$id.'-icons','classes'=>['system-icons'],'items'=>$item['icons']];
				unset($item['icons']);
				if (!$item['items']) unset($item['items']);
				$items[] = $item;
			}
			$result[] = create__dropdown($rule,htmlspecialchars(language__get($language,$rule.'_headline')),create__list($rule.'-list',$items),[
				'icons'=>[['id'=>$rule.'-bubble','value'=>max(1,count($items) - 1),'attributes'=>['data-systemicon'=>$severity]]],
				'classes'=>['system-bg'],
				'attributes'=>['data-notify'=>$severity],
				'mainattributes'=>['data-notify'=>$severity]
			]);
		}
		return $result;
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
