<?php

if (!$site['onsite'] || !$_SERVER['database']['active']) return;

$accessibility = ['installed'=>\accessibility\Installer::run()];

unset($accessibility);
