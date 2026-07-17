<?php

namespace accessibility;

class Collector {
	public static function acceptPending(int $mid, int $tid, string $lid, string $payload): bool {
		$context = SessionContext::consume($mid,$tid,$lid);
		if ($context === false) return false;
		$result = Result::fromPayload($payload,$context);
		if ($result === false || !Repository::store($result)) return false;
		Statistics::record((int) ($_SERVER['now'] ?? time()));
		return true;
	}
}
