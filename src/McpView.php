<?php

namespace accessibility;

final class McpView {
	public static function read(string $id, string $language): array {
		if ($id === 'summary') return self::summary($language);
		if ($id === 'pages') return self::pages($language);
		if (!preg_match('/^page:([0-9]+)-([0-9]+)-([a-z0-9_-]+)$/i',$id,$match)) return ['error'=>'accessibility id must be "summary", "pages", or "page:<mid-tid-lid>".'];
		return self::page((int) $match[1],(int) $match[2],$match[3],$language);
	}

	private static function summary(string $language): array {
		$rows = Repository::rows();
		$assessment = Overview::summarize($rows);
		return [
			'type'=>'accessibility',
			'id'=>'summary',
			'status'=>self::status($rows),
			'coverage'=>self::coverage($rows),
			'assessment'=>[
				'score_percent'=>$assessment['count'] > 0 ? (int) $assessment['score'] : false,
				'categories'=>self::categories($assessment['categories'] ?? [],$language),
				'checks'=>$assessment['total'],
				'last'=>(int) $assessment['last']
			],
			'finding_groups'=>self::findingGroups($rows,$language),
			'recommended_calls'=>['get("accessibility","pages")','get("accessibility","page:<mid-tid-lid>")','get("skill","accessibility")'],
			'limitations'=>self::limitations()
		];
	}

	private static function pages(string $language): array {
		$rows = Repository::rows();
		$pages = [];
		foreach (self::groupRows($rows) as $id => $pageRows) $pages[] = self::pageSummary($id,$pageRows,$language);
		usort($pages,fn($a,$b) => strnatcasecmp($a['path'].'-'.$a['locale'],$b['path'].'-'.$b['locale']));
		return [
			'type'=>'accessibility',
			'id'=>'pages',
			'status'=>self::status($rows),
			'coverage'=>self::coverage($rows),
			'pages'=>$pages,
			'limitations'=>self::limitations()
		];
	}

	private static function page(int $mid, int $tid, string $lid, string $language): array {
		$rows = Repository::pageRows($mid,$tid,$lid);
		if (!$rows) return ['error'=>'No stored accessibility audit found for page '.$mid.'-'.$tid.'-'.$lid.'.'];
		$id = $mid.'-'.$tid.'-'.$lid;
		$audits = [];
		foreach ($rows as $row) {
			$viewport = (int) ($row['mobile'] ?? 0) === 1 ? 'mobile' : 'desktop';
			$audit = is_array($row['result']['audit'] ?? null) ? $row['result']['audit'] : [];
			$aggregate = Score::aggregate(is_array($audit['scores'] ?? null) ? $audit['scores'] : []);
			if ($aggregate === false) continue;
			$audits[$viewport] = [
				'viewport'=>$viewport,
				'last'=>(int) ($row['audit_time'] ?? 0),
				'fresh'=>self::isFresh((int) ($row['audit_time'] ?? 0)),
				'age_days'=>self::ageDays((int) ($row['audit_time'] ?? 0)),
				'schema'=>(string) ($row['result']['schema'] ?? $row['schema_version'] ?? ''),
				'engine'=>$row['result']['engine'] ?? ['name'=>'ficms-accessibility','version'=>(string) ($row['engine_version'] ?? '')],
				'score_percent'=>(int) $aggregate['score'],
				'categories'=>self::categories($aggregate['categories'],$language),
				'checks'=>array_intersect_key($aggregate,array_flip(['total','success','warning','error'])),
				'stats'=>self::stats(is_array($audit['stats'] ?? null) ? $audit['stats'] : []),
				'findings'=>self::findings(is_array($audit['accessibility'] ?? null) ? $audit['accessibility'] : [],$language)
			];
		}
		if (!$audits) return ['error'=>'Stored accessibility audits for page '.$id.' are invalid.'];
		ksort($audits);
		return [
			'type'=>'accessibility',
			'id'=>'page:'.$id,
			'page'=>self::pageSummary($id,$rows,$language),
			'audits'=>$audits,
			'limitations'=>self::limitations()
		];
	}

	private static function pageSummary(string $id, array $rows, string $language): array {
		$assessment = Overview::summarize($rows);
		$viewports = [];
		$fresh = true;
		foreach ($rows as $row) {
			$viewports[] = (int) ($row['mobile'] ?? 0) === 1 ? 'mobile' : 'desktop';
			if (!self::isFresh((int) ($row['audit_time'] ?? 0))) $fresh = false;
		}
		$viewports = array_values(array_unique($viewports));
		sort($viewports);
		return [
			'midtidlid'=>$id,
			'path'=>(string) ($rows[0]['path'] ?? $rows[0]['result']['page']['path'] ?? ''),
			'locale'=>(string) ($rows[0]['lid'] ?? $rows[0]['result']['page']['locale'] ?? ''),
			'audited_viewports'=>$viewports,
			'missing_viewports'=>array_values(array_diff(['desktop','mobile'],$viewports)),
			'score_percent'=>$assessment['count'] > 0 ? (int) $assessment['score'] : false,
			'categories'=>self::categories($assessment['categories'] ?? [],$language),
			'checks'=>$assessment['total'],
			'last'=>(int) $assessment['last'],
			'fresh'=>$fresh,
			'full_version'=>['tool'=>'get','type'=>'accessibility','id'=>'page:'.$id]
		];
	}

	private static function groupRows(array $rows): array {
		$pages = [];
		foreach ($rows as $row) {
			$id = (int) ($row['mid'] ?? 0).'-'.(int) ($row['tid'] ?? 0).'-'.(string) ($row['lid'] ?? '');
			if (!isset($pages[$id])) $pages[$id] = [];
			$pages[$id][] = $row;
		}
		return $pages;
	}

	private static function status(array $rows): array {
		$fresh = 0;
		foreach ($rows as $row) if (self::isFresh((int) ($row['audit_time'] ?? 0))) $fresh++;
		return [
			'available'=>Repository::available() && License::allowed(),
			'enabled'=>Config::enabled(),
			'has_data'=>(bool) $rows,
			'freshness_days'=>Config::freshnessDays(),
			'fresh_contexts'=>$fresh,
			'stale_contexts'=>count($rows) - $fresh
		];
	}

	private static function coverage(array $rows): array {
		$pages = self::groupRows($rows);
		$viewports = ['desktop'=>0,'mobile'=>0];
		$complete = 0;
		foreach ($pages as $pageRows) {
			$present = [];
			foreach ($pageRows as $row) {
				$viewport = (int) ($row['mobile'] ?? 0) === 1 ? 'mobile' : 'desktop';
				$viewports[$viewport]++;
				$present[$viewport] = true;
			}
			if (count($present) === 2) $complete++;
		}
		return [
			'audited_pages'=>count($pages),
			'audited_contexts'=>count($rows),
			'viewport_contexts'=>$viewports,
			'pages_with_both_viewports'=>$complete,
			'note'=>'Counts only stored latest audit snapshots. It does not prove that every public page or state was tested.'
		];
	}

	private static function categories(array $categories, string $language): array {
		$result = [];
		foreach ($categories as $category => $score) $result[$category] = [
			'label'=>language__get($language,'_accessibility_'.$category),
			'score_percent'=>(int) round((float) $score * 100)
		];
		return $result;
	}

	private static function findingGroups(array $rows, string $language): array {
		$groups = ['error'=>[],'warning'=>[]];
		foreach ($rows as $row) foreach (['error','warning'] as $severity) foreach (($row['result']['audit']['accessibility'][$severity] ?? []) as $rule => $entries) {
			if (!is_array($entries)) continue;
			if (!isset($groups[$severity][$rule])) $groups[$severity][$rule] = ['rule'=>$rule,'occurrences'=>0,'pages'=>[],'viewports'=>[]];
			$groups[$severity][$rule]['occurrences'] += max(1,count($entries));
			$groups[$severity][$rule]['pages'][(int) $row['mid'].'-'.(int) $row['tid'].'-'.(string) $row['lid']] = true;
			$groups[$severity][$rule]['viewports'][(int) $row['mobile'] === 1 ? 'mobile' : 'desktop'] = true;
		}
		foreach ($groups as $severity => $entries) {
			$groups[$severity] = [];
			foreach ($entries as $entry) {
				$entry['title'] = language__get($language,$entry['rule'].'_headline');
				$entry['description'] = language__get($language,$entry['rule']);
				$entry['affected_pages'] = count($entry['pages']);
				$entry['viewports'] = array_keys($entry['viewports']);
				sort($entry['viewports']);
				unset($entry['pages']);
				$groups[$severity][] = $entry;
			}
			usort($groups[$severity],fn($a,$b) => $b['occurrences'] <=> $a['occurrences'] ?: strcmp($a['rule'],$b['rule']));
		}
		return $groups;
	}

	private static function findings(array $findings, string $language): array {
		$result = ['error'=>[],'warning'=>[]];
		foreach (['error','warning'] as $severity) foreach (($findings[$severity] ?? []) as $rule => $entries) {
			if (!is_array($entries)) continue;
			$items = [];
			foreach ($entries as $entry) {
				if (!is_array($entry)) continue;
				$items[] = self::findingItem($rule,$entry);
			}
			$result[$severity][] = [
				'rule'=>$rule,
				'title'=>language__get($language,$rule.'_headline'),
				'description'=>language__get($language,$rule),
				'occurrences'=>max(1,count($entries)),
				'items'=>$items
			];
		}
		return $result;
	}

	private static function findingItem(string $rule, array $entry): array {
		$name = trim((string) ($entry['name'] ?? ''));
		if (str_contains($name,'<')) $name = preg_match('/<\s*([a-z0-9-]+)/i',$name,$match) ? strtolower($match[1]) : 'element';
		$value = $entry['value'] ?? false;
		if (str_contains($rule,'_alt_')) $value = false;
		else if (is_string($value)) $value = substr($value,0,160);
		else if (!is_int($value) && !is_float($value) && !is_bool($value)) $value = false;
		return [
			'id'=>substr((string) ($entry['id'] ?? ''),0,64),
			'element'=>substr($name,0,160),
			'value'=>$value,
			'selector'=>substr((string) ($entry['unique'] ?? ''),0,500)
		];
	}

	private static function stats(array $stats): array {
		$result = [];
		foreach (['totalElements','collectedElements','checkRuns'] as $key) if (isset($stats[$key]) && is_int($stats[$key]) && $stats[$key] >= 0) $result[$key] = $stats[$key];
		return $result;
	}

	private static function isFresh(int $timestamp): bool {
		return $timestamp > 0 && $timestamp >= (int) ($_SERVER['now'] ?? time()) - (Config::freshnessDays() * 86400);
	}

	private static function ageDays(int $timestamp): int|false {
		if ($timestamp <= 0) return false;
		return max(0,(int) floor(((int) ($_SERVER['now'] ?? time()) - $timestamp) / 86400));
	}

	private static function limitations(): array {
		return [
			'automated_sampled_audit'=>true,
			'compliance_proof'=>false,
			'interpretation'=>'Report what the stored audit observed, including page, viewport, and timestamp. Do not claim WCAG, BFSG, or legal compliance from this audit alone.',
			'absence_of_finding'=>'No finding means only that this automated snapshot did not report the rule. Missing pages, viewports, interaction states, assistive-technology behavior, and manual checks remain unknown.'
		];
	}
}
