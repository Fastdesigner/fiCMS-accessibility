<?php

namespace accessibility;

class Installer {
	public static function run(): bool {
		return self::schema() && self::migrate();
	}

	public static function schema(): bool {
		return mysqlCheck(Config::TABLE,[
			'result'=>'TEXT',
			'scores'=>'TEXT',
			'mid'=>0,
			'tid'=>0,
			'lid'=>'de',
			'mobile'=>0,
			'path'=>'TEXT',
			'schema_version'=>'',
			'engine_version'=>'',
			'score'=>100,
			'total'=>0,
			'success'=>0,
			'warning'=>0,
			'error'=>0
		],['mid','tid','lid','mobile','score','created_at'],['date'],[
			'mid'=>"INT NOT NULL DEFAULT '0'",
			'tid'=>"INT NOT NULL DEFAULT '0'",
			'mobile'=>"INT NOT NULL DEFAULT '0'",
			'score'=>"INT NOT NULL DEFAULT '0'",
			'total'=>"INT NOT NULL DEFAULT '0'",
			'success'=>"INT NOT NULL DEFAULT '0'",
			'warning'=>"INT NOT NULL DEFAULT '0'",
			'error'=>"INT NOT NULL DEFAULT '0'"
		]) !== false;
	}

	public static function migrate(): bool {
		global $site, $tables;

		$key = 'system/plugins/fiCMS-accessibility/migrate-core-accessibility-v1';
		if (!isset($tables['rewrites_accessibility']) || \ficms\Journal::isMigrated($key)) return true;
		$journal = \ficms\Journal::start($key,'Native Accessibility-Audits ins Plugin uebernehmen','system/plugins/fiCMS-accessibility/cron/accessibility.php');
		if (!$journal->ready()) return false;

		$migrated = 0;
		$fetch = mysqlQuery('SELECT * FROM '.$tables['rewrites_accessibility'].' ORDER BY `id` ASC');
		while ($row = mysqlFetchAssoc($fetch)) {
			$exists = mysqlFetchAssoc('SELECT `id` FROM '.$tables[Config::TABLE].' WHERE `mid` = '.(int) $row['mid'].' AND `tid` = '.(int) $row['tid'].' AND `lid` = \''.mysqlEscape((string) $row['lid']).'\' AND `mobile` = '.(int) $row['mobile'].' AND `created_at` = FROM_UNIXTIME('.(int) $row['date'].') AND `engine_version` = \'legacy-core\' LIMIT 1');
			if ($exists) continue;
			$audit = helper__json_convert($row['result'] ?? '');
			if (!is_array($audit)) return false;
			$aggregate = Score::aggregate(is_array($audit['scores'] ?? null) ? $audit['scores'] : []);
			if ($aggregate === false) return false;
			$path = '';
			if (isset($_SERVER['Router'])) $path = (string) ($_SERVER['Router']->getParsedUrl((int) $row['mid'].'-'.(int) $row['tid'],(string) $row['lid']) ?: '');
			$success = isset($row['success']) ? (int) $row['success'] : max(0,(int) $row['total'] - (int) $row['warning'] - (int) $row['error']);
			$envelope = [
				'schema'=>Config::SCHEMA,
				'engine'=>['name'=>'ficms-accessibility','version'=>'legacy-core'],
				'platform'=>['name'=>'ficms','version'=>(string) ($site['ficms_version'] ?? '')],
				'page'=>['path'=>$path,'locale'=>(string) $row['lid'],'viewport'=>((int) $row['mobile'] === 1 ? 'mobile' : 'desktop')],
				'context'=>['mid'=>(int) $row['mid'],'tid'=>(int) $row['tid'],'lid'=>(string) $row['lid'],'mobile'=>(int) $row['mobile']],
				'audit'=>$audit
			];
			$id = mysqlSet(Config::TABLE,[
				'result'=>$envelope,
				'scores'=>$aggregate['categories'],
				'mid'=>$envelope['context']['mid'],
				'tid'=>$envelope['context']['tid'],
				'lid'=>$envelope['context']['lid'],
				'mobile'=>$envelope['context']['mobile'],
				'path'=>$path,
				'schema_version'=>Config::SCHEMA,
				'engine_version'=>'legacy-core',
				'score'=>(int) ($row['score'] ?? 0),
				'total'=>(int) ($row['total'] ?? 0),
				'success'=>$success,
				'warning'=>(int) ($row['warning'] ?? 0),
				'error'=>(int) ($row['error'] ?? 0)
			]);
			if ($id === false) return false;
			$journal->rememberAddedSql(Config::TABLE,(int) $id);
			if (!mysqlQuery('UPDATE '.$tables[Config::TABLE].' SET `created_at` = FROM_UNIXTIME('.(int) $row['date'].'), `updated_at` = FROM_UNIXTIME('.(int) $row['date'].') WHERE `id` = '.(int) $id)) return false;
			$migrated++;
		}
		$journal->rememberSchema('rewrites_accessibility');
		if (!mysqlDrop('rewrites_accessibility')) return false;
		return $journal->finish(['rows'=>$migrated]);
	}
}
