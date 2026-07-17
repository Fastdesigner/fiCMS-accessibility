<?php

namespace accessibility;

class Installer {
	public static function run(): bool {
		global $tables;

		if (!Repository::available()) return false;
		foreach ([Config::TABLE,'rewrites_accessibility'] as $table) if (isset($tables[$table]) && !mysqlDrop($table)) return false;
		return Statistics::sync();
	}
}
