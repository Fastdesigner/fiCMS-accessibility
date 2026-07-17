<?php

namespace accessibility;

class Config {
	public const TABLE = 'accessibility_audits';
	public const SCHEMA = 'accessibility-audit/1';
	public const ENGINE_VERSION = '0.1.1';
	public const CATEGORIES = ['media_alt','navigatability','form_labels','semantic','readability','user_preferences'];

	public static function enabled(): bool {
		global $site;
		return (int) ($site['accessibility_audit_active'] ?? 1) === 1;
	}

	public static function freshnessDays(): int {
		global $site;
		return max(1,(int) ($site['accessibility_audit_freshness'] ?? 10));
	}

	public static function retentionDays(): int {
		global $site;
		return max(1,(int) ($site['accessibility_audit_retention'] ?? 180));
	}

	public static function maxPayloadBytes(): int {
		return 2_000_000;
	}
}
