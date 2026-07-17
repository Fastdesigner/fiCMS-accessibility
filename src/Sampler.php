<?php

namespace accessibility;

class Sampler {
	public static function select(array $modul, array $html, array $user): bool {
		global $site;

		if (!$site['onsite'] || !Config::enabled() || !License::allowed()) return false;
		if ((int) ($user['id'] ?? 0) !== 0 || (int) ($html['is_main'] ?? 0) !== 1 || (int) ($html['is_bot'] ?? 1) === 1 || !empty($_SERVER['halt']['status'])) return false;
		$context = [
			'mid'=>(int) ($modul['mid'] ?? $modul['id'] ?? 0),
			'tid'=>(int) ($modul['tid'] ?? 0),
			'lid'=>(string) ($_SESSION['language'] ?? $modul['lid'] ?? ''),
			'mobile'=>(int) ($html['is_mobile'] ?? 0),
			'path'=>(string) ($modul['mpath'] ?? '')
		];
		$forced = (int) ($html['is_developer'] ?? 0) === 1 && isset($_GET['fiCMSaccessibility']);
		if ($context['mid'] <= 0 || $context['lid'] === '' || (!$forced && Repository::isFresh($context,Config::freshnessDays()))) return false;
		if (!$forced && !helper__system_runtime('accessibility_audit',60,false,'seconds')) return false;

		SessionContext::create($context);
		$_SERVER['load_services']['accessibility'] = true;
		return true;
	}
}
