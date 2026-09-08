<?php
// Fail rather than silently omit the restricted-account route guard.
$path = '/var/www/html/main.inc.php';
$source = file_get_contents($path);
if ($source === false) throw new RuntimeException('Cannot read Dolibarr main.inc.php');
$marker = '// PZ_ACCOUNT_ROUTE_GUARD_V1';
if (str_contains($source, $marker)) exit(0);
$anchor = '// Case forcing style from url';
if (substr_count($source, $anchor) !== 1) throw new RuntimeException('Unsupported Dolibarr bootstrap; review route guard integration');
$guard = <<<'PHP'
// PZ_ACCOUNT_ROUTE_GUARD_V1: after authentication, before page actions.
require_once DOL_DOCUMENT_ROOT.'/custom/puchatyzakatek/lib/access.lib.php';
pz_enforce_route();

PHP;
if (file_put_contents($path, str_replace($anchor, $guard.$anchor, $source)) === false) throw new RuntimeException('Cannot install route guard');
