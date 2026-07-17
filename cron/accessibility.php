<?php

if (!$site['onsite']) return;

$accessibility = ['installed'=>\accessibility\Installer::run()];

unset($accessibility);
