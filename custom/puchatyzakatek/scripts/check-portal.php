<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('NOLOGIN',1);define('NOSESSION',1);define('NOREQUIREMENU',1);define('NOREQUIREUSER',1);
$_SERVER['REQUEST_METHOD']='GET';$_SESSION=array();
require dirname(__DIR__,3).'/main.inc.php';
require_once dirname(__DIR__).'/lib/pz.lib.php';
require_once dirname(__DIR__).'/lib/commerce.lib.php';
require_once dirname(__DIR__).'/lib/portal.lib.php';
try{
    $c=pz_portal_config();pz_portal_validate($c);
    if(empty($conf->puchatyzakatek->enabled)||!pz_ready()||pz_store('config','main')===null)throw new RuntimeException('Najpierw uruchom moduł Puchaty Zakątek.');
    pz_portal_actor($c);
    if(!empty($c['auth']['google_enabled'])&&!function_exists('curl_init'))throw new RuntimeException('Brak cURL dla Google.');
    if(!empty($c['auth']['email_enabled'])){if(!function_exists('openssl_open'))throw new RuntimeException('Brak OpenSSL.');$m=pz_mail_config();pz_mail_validate($m);if(empty($m['delivery']['enabled']))throw new RuntimeException('Włącz delivery.enabled w mail-config.ini.');}
    $days=0;foreach(array('monday','tuesday','wednesday','thursday','friday','saturday','sunday') as $d)if(!empty($c['calendar'][$d]))$days++;
    if(!$days)throw new RuntimeException('Wpisz godziny pracy co najmniej jednego dnia.');
    echo "PORTAL_CONFIG_OK\n";
    echo 'Portal: '.(!empty($c['portal']['enabled'])?'włączony':'wyłączony')."\n";
    echo 'Google redirect URI: '.rtrim($c['portal']['base_url'],'/')."/google.php\n";
    echo "3 godziny/pies, 3 psy/dzień, minimum 1 godzina wyprzedzenia.\nNie wysłano wiadomości ani nie utworzono kont.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
