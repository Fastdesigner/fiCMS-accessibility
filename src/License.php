<?php

namespace accessibility;

class License {
	public static function allowed(string $feature = 'audit'): bool {
		return $feature === 'audit';
	}
}
