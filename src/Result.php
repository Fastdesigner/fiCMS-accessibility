<?php

namespace accessibility;

class Result {
	private array $envelope;
	private array $aggregate;

	private function __construct(array $envelope, array $aggregate) {
		$this->envelope = $envelope;
		$this->aggregate = $aggregate;
	}

	public static function fromPayload(string $payload, array $context): self|false {
		global $site;

		if ($payload === '' || strlen($payload) > Config::maxPayloadBytes()) return false;
		$audit = json_decode($payload,true);
		if (!is_array($audit) || !isset($audit['scores'],$audit['accessibility']) || !is_array($audit['scores']) || !is_array($audit['accessibility'])) return false;
		$aggregate = Score::aggregate($audit['scores']);
		if ($aggregate === false) return false;
		$audit = [
			'scores'=>$audit['scores'],
			'accessibility'=>self::sanitizeFindings($audit['accessibility']),
			'stats'=>self::sanitizeStats($audit['stats'] ?? [])
		];
		return new self([
			'schema'=>Config::SCHEMA,
			'engine'=>['name'=>'ficms-accessibility','version'=>Config::ENGINE_VERSION],
			'platform'=>['name'=>'ficms','version'=>(string) ($site['ficms_version'] ?? '')],
			'page'=>['path'=>$context['path'],'locale'=>$context['lid'],'viewport'=>((int) $context['mobile'] === 1 ? 'mobile' : 'desktop')],
			'context'=>['mid'=>$context['mid'],'tid'=>$context['tid'],'lid'=>$context['lid'],'mobile'=>$context['mobile']],
			'audit'=>$audit
		],$aggregate);
	}

	public function envelope(): array {
		return $this->envelope;
	}

	public function aggregate(): array {
		return $this->aggregate;
	}

	private static function sanitizeFindings(array $findings): array {
		$result = ['error'=>[],'warning'=>[]];
		foreach (['error','warning'] as $severity) foreach ((is_array($findings[$severity] ?? null) ? $findings[$severity] : []) as $rule => $entries) {
			if (!is_array($entries)) continue;
			$result[$severity][$rule] = [];
			foreach ($entries as $entry) {
				if (!is_array($entry)) continue;
				$name = trim((string) ($entry['name'] ?? ''));
				if (str_contains($name,'<')) $name = preg_match('/<\s*([a-z0-9-]+)/i',$name,$match) ? strtolower($match[1]) : 'element';
				$value = $entry['value'] ?? false;
				if (str_contains((string) $rule,'_alt_')) $value = false;
				else if (is_string($value)) $value = substr($value,0,160);
				else if (!is_int($value) && !is_float($value) && !is_bool($value)) $value = false;
				$result[$severity][$rule][] = [
					'id'=>substr((string) ($entry['id'] ?? ''),0,64),
					'name'=>substr($name,0,160),
					'value'=>$value,
					'image'=>false,
					'unique'=>substr((string) ($entry['unique'] ?? ''),0,500)
				];
			}
		}
		return $result;
	}

	private static function sanitizeStats(array $stats): array {
		$result = [];
		foreach (['totalElements','collectedElements','checkRuns'] as $key) if (isset($stats[$key]) && is_int($stats[$key]) && $stats[$key] >= 0) $result[$key] = $stats[$key];
		return $result;
	}
}
