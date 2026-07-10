<?php

namespace accessibility;

class Repository {
	public static function isFresh(array $context, int $days): bool {
		global $tables;
		if (!isset($tables[Config::TABLE])) return false;
		return (bool) mysqlFetchAssoc('SELECT `id` FROM '.$tables[Config::TABLE].' WHERE `mid` = '.(int) $context['mid'].' AND `tid` = '.(int) $context['tid'].' AND `lid` = \''.mysqlEscape($context['lid']).'\' AND `mobile` = '.(int) $context['mobile'].' AND `created_at` >= FROM_UNIXTIME('.($_SERVER['now'] - ($days * 86400)).') LIMIT 1');
	}

	public static function store(Result $result): bool {
		$envelope = $result->envelope();
		$aggregate = $result->aggregate();
		return mysqlSet(Config::TABLE,[
			'result'=>$envelope,
			'scores'=>$aggregate['categories'],
			'mid'=>$envelope['context']['mid'],
			'tid'=>$envelope['context']['tid'],
			'lid'=>$envelope['context']['lid'],
			'mobile'=>$envelope['context']['mobile'],
			'path'=>$envelope['page']['path'],
			'schema_version'=>$envelope['schema'],
			'engine_version'=>$envelope['engine']['version'],
			'score'=>$aggregate['score'],
			'total'=>$aggregate['total'],
			'success'=>$aggregate['success'],
			'warning'=>$aggregate['warning'],
			'error'=>$aggregate['error']
		]) !== false;
	}

	public static function healthScore(): int|false {
		global $tables;
		if (!isset($tables[Config::TABLE])) return false;
		$rows = $handled = [];
		$fetch = mysqlQuery('SELECT `mid`,`tid`,`lid`,`mobile`,`scores` FROM '.$tables[Config::TABLE].' ORDER BY `created_at` DESC');
		while ($row = mysqlFetchAssoc($fetch)) {
			$key = (int) $row['mid'].'-'.(int) $row['tid'].'-'.(string) $row['lid'].'-'.(int) $row['mobile'];
			if (isset($handled[$key])) continue;
			$row['scores'] = helper__json_convert($row['scores'] ?? '');
			if (!is_array($row['scores'])) continue;
			$handled[$key] = true;
			$rows[] = $row['scores'];
		}
		return Overview::score($rows);
	}

	public static function rows(int $before = 0): array {
		global $tables;
		if (!isset($tables[Config::TABLE])) return [];
		$rows = $handled = [];
		$fetch = mysqlQuery('SELECT *,UNIX_TIMESTAMP(`created_at`) AS `audit_time` FROM '.$tables[Config::TABLE].(($before > 0) ? ' WHERE `created_at` < FROM_UNIXTIME('.$before.')' : '').' ORDER BY `created_at` DESC');
		while ($row = mysqlFetchAssoc($fetch)) {
			$key = (int) $row['mid'].'-'.(int) $row['tid'].'-'.(string) $row['lid'].'-'.(int) $row['mobile'];
			if (isset($handled[$key])) continue;
			$row['result'] = helper__json_convert($row['result'] ?? '');
			if (!is_array($row['result'])) continue;
			$handled[$key] = true;
			$rows[] = $row;
		}
		return $rows;
	}

	public static function latestDate(): int|false {
		global $tables;
		if (!isset($tables[Config::TABLE])) return false;
		$result = mysqlFetchAssoc('SELECT UNIX_TIMESTAMP(MAX(`created_at`)) AS `audit_time` FROM '.$tables[Config::TABLE]);
		return !empty($result['audit_time']) ? (int) $result['audit_time'] : false;
	}

	public static function cleanup(): int {
		global $tables;
		if (!isset($tables[Config::TABLE])) return 0;
		mysqlQuery('DELETE FROM '.$tables[Config::TABLE].' WHERE `created_at` < FROM_UNIXTIME('.($_SERVER['now'] - (Config::retentionDays() * 86400)).')');
		return (int) ($_SERVER['database']['active']['con']->affected_rows ?? 0);
	}
}
