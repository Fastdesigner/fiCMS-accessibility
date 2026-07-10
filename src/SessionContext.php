<?php

namespace accessibility;

class SessionContext {
	private const TTL = 300;

	public static function create(array $context): void {
		self::cleanup();
		$_SESSION['fiCMS_accessibility']['contexts'][bin2hex(random_bytes(24))] = [
			'context'=>$context,
			'expires'=>$_SERVER['now'] + self::TTL
		];
	}

	public static function consume(int $mid, int $tid, string $lid): array|false {
		self::cleanup();
		foreach ($_SESSION['fiCMS_accessibility']['contexts'] ?? [] as $key => $entry) {
			if ((int) ($entry['context']['mid'] ?? 0) !== $mid || (int) ($entry['context']['tid'] ?? 0) !== $tid || (string) ($entry['context']['lid'] ?? '') !== $lid) continue;
			unset($_SESSION['fiCMS_accessibility']['contexts'][$key]);
			return is_array($entry['context'] ?? null) ? $entry['context'] : false;
		}
		return false;
	}

	private static function cleanup(): void {
		if (!isset($_SESSION['fiCMS_accessibility']['contexts']) || !is_array($_SESSION['fiCMS_accessibility']['contexts'])) $_SESSION['fiCMS_accessibility']['contexts'] = [];
		foreach ($_SESSION['fiCMS_accessibility']['contexts'] as $key => $entry) if ((int) ($entry['expires'] ?? 0) <= $_SERVER['now']) unset($_SESSION['fiCMS_accessibility']['contexts'][$key]);
	}
}
