<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
define('NOLOGIN', 1);
define('NOREQUIREMENU', 1);
$_SERVER['PHP_SELF'] = '/custom/puchatyzakatek/scripts/install_db.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
require dirname(__DIR__, 3).'/main.inc.php';
require dirname(__DIR__).'/lib/pz.lib.php';
try {
    pz_install();
    foreach (array('dog','visit','visit_line','expense','store') as $name) pz_query('SELECT rowid FROM '.MAIN_DB_PREFIX.'pz_'.$name.' LIMIT 1');
    pz_config();
    echo "PZ_SCHEMA_OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, "PZ_SCHEMA_FAILED: ".$e->getMessage()."\n");
    exit(1);
}
