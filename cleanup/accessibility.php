<?php

if (!$site['onsite']) return;

$accessibility = [
	'deleted'=>\accessibility\Repository::cleanup(),
	'legacy_cache'=>is_dir(CACHEPATH.'/accessibility') ? \ficms\Files::removeDirectory(CACHEPATH.'/accessibility',true,true) : 0
];

unset($accessibility);
