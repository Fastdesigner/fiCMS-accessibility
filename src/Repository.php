<?php

namespace accessibility;

class Repository {
	public static function available(): bool {
		return \ficms\Files::ensureDirectory(Config::dataPath(),true);
	}

	public static function isFresh(array $context, int $days): bool {
		foreach (self::index($context)['audits'] ?? [] as $audit) return (int) ($audit['audit_time'] ?? 0) >= self::now() - ($days * 86400);
		return false;
	}

	public static function store(Result $result): bool {
		if (!self::available()) return false;
		$envelope = $result->envelope();
		$aggregate = $result->aggregate();
		$timestamp = self::now();
		$key = self::contextKey($envelope['context']);
		$indexFile = Config::dataPath('indexes/'.hash('sha256',$key).'.json');
		$resultFile = Config::dataPath('audits/'.date('Y-m-d',$timestamp).'/'.hash('sha256',$key).'-'.$timestamp.'-'.bin2hex(random_bytes(4)).'.json');
		$row = [
			'mid'=>(int) $envelope['context']['mid'],
			'tid'=>(int) $envelope['context']['tid'],
			'lid'=>(string) $envelope['context']['lid'],
			'mobile'=>(int) $envelope['context']['mobile'],
			'path'=>(string) $envelope['page']['path'],
			'schema_version'=>(string) $envelope['schema'],
			'engine_version'=>(string) $envelope['engine']['version'],
			'score'=>(int) $aggregate['score'],
			'total'=>(int) $aggregate['total'],
			'success'=>(int) $aggregate['success'],
			'warning'=>(int) $aggregate['warning'],
			'error'=>(int) $aggregate['error'],
			'audit_time'=>$timestamp,
			'result_file'=>$resultFile
		];
		if (!\ficms\Files::writeJson($resultFile,$envelope,false,true)) return false;
		if (\ficms\Files::updateJson($indexFile,function($index) use ($envelope,$row) {
			$index['context'] = $envelope['context'];
			$index['audits'] = is_array($index['audits'] ?? null) ? $index['audits'] : [];
			array_unshift($index['audits'],$row);
			usort($index['audits'],fn($a,$b) => (int) ($b['audit_time'] ?? 0) <=> (int) ($a['audit_time'] ?? 0));
			return $index;
		}) === false) {
			\ficms\Files::delete($resultFile);
			return false;
		}
		if (\ficms\Files::updateJson(Config::dataPath('contexts.json'),function($contexts) use ($key,$indexFile,$envelope) {
			$contexts['contexts'] = is_array($contexts['contexts'] ?? null) ? $contexts['contexts'] : [];
			$contexts['contexts'][$key] = array_merge($envelope['context'],['index_file'=>$indexFile]);
			ksort($contexts['contexts']);
			return $contexts;
		}) !== false) return true;
		self::removeAudit($indexFile,$resultFile);
		return false;
	}

	public static function healthScore(): int|false {
		$rows = [];
		foreach (self::rows() as $row) {
			if (!is_array($row['result']['audit']['scores'] ?? null)) continue;
			$aggregate = Score::aggregate($row['result']['audit']['scores']);
			if ($aggregate !== false) $rows[] = $aggregate['categories'];
		}
		return Overview::score($rows);
	}

	public static function rows(int $before = 0): array {
		return self::currentRows($before);
	}

	public static function pageRows(int $mid, int $tid, string $lid, int $before = 0): array {
		if ($mid <= 0 || $tid < 0 || $lid === '') return [];
		return self::currentRows($before,['mid'=>$mid,'tid'=>$tid,'lid'=>$lid]);
	}

	public static function history(int $start = 0, int $end = 0): array {
		$rows = [];
		foreach (self::contexts() as $context) foreach (self::index($context)['audits'] ?? [] as $row) {
			if ($start > 0 && (int) ($row['audit_time'] ?? 0) < $start) continue;
			if ($end > 0 && (int) ($row['audit_time'] ?? 0) >= $end) continue;
			$rows[] = $row;
		}
		return $rows;
	}

	public static function latestDate(): int|false {
		$latest = 0;
		foreach (self::contexts() as $context) foreach (self::index($context)['audits'] ?? [] as $audit) {
			$latest = max($latest,(int) ($audit['audit_time'] ?? 0));
			break;
		}
		return $latest > 0 ? $latest : false;
	}

	public static function cleanup(): int {
		$deleted = 0;
		$empty = [];
		$threshold = self::now() - (Config::retentionDays() * 86400);
		foreach (self::contexts() as $key => $context) {
			$files = [];
			$index = \ficms\Files::updateJson((string) $context['index_file'],function($index) use ($threshold,&$files) {
				$audits = [];
				foreach (($index['audits'] ?? []) as $audit) {
					if ((int) ($audit['audit_time'] ?? 0) >= $threshold) $audits[] = $audit;
					else if (!empty($audit['result_file'])) $files[] = (string) $audit['result_file'];
				}
				$index['audits'] = $audits;
				return $index;
			});
			if ($index === false) continue;
			foreach ($files as $file) if (\ficms\Files::delete($file)) $deleted++;
			if (empty($index['audits'])) $empty[$key] = (string) $context['index_file'];
		}
		if (!$empty) return $deleted;
		if (\ficms\Files::updateJson(Config::dataPath('contexts.json'),function($contexts) use ($empty) {
			foreach (array_keys($empty) as $key) unset($contexts['contexts'][$key]);
			return $contexts;
		}) === false) return $deleted;
		foreach ($empty as $file) \ficms\Files::delete($file);
		return $deleted;
	}

	private static function currentRows(int $before = 0, array $filter = []): array {
		$rows = [];
		foreach (self::contexts() as $context) {
			foreach ($filter as $key => $value) if (($context[$key] ?? null) != $value) continue 2;
			foreach (self::index($context)['audits'] ?? [] as $row) {
				if ($before > 0 && (int) ($row['audit_time'] ?? 0) >= $before) continue;
				$row['result'] = \ficms\Files::readJson((string) ($row['result_file'] ?? ''));
				if (!$row['result']) continue;
				$rows[] = $row;
				break;
			}
		}
		usort($rows,fn($a,$b) => (int) $b['audit_time'] <=> (int) $a['audit_time']);
		return $rows;
	}

	private static function contexts(): array {
		return \ficms\Files::readJson(Config::dataPath('contexts.json'))['contexts'] ?? [];
	}

	private static function index(array $context): array {
		if (isset($context['index_file'])) return \ficms\Files::readJson((string) $context['index_file']);
		return \ficms\Files::readJson(Config::dataPath('indexes/'.hash('sha256',self::contextKey($context)).'.json'));
	}

	private static function contextKey(array $context): string {
		return (int) $context['mid'].'-'.(int) $context['tid'].'-'.(string) $context['lid'].'-'.(int) $context['mobile'];
	}

	private static function removeAudit(string $indexFile, string $resultFile): void {
		\ficms\Files::updateJson($indexFile,function($index) use ($resultFile) {
			$index['audits'] = array_values(array_filter($index['audits'] ?? [],fn($audit) => ($audit['result_file'] ?? '') !== $resultFile));
			return $index;
		});
		\ficms\Files::delete($resultFile);
	}

	private static function now(): int {
		return (int) ($_SERVER['now'] ?? time());
	}
}
