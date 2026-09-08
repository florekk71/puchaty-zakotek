<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
define('NOLOGIN',1);define('NOREQUIREMENU',1);
$_SERVER['PHP_SELF']='/custom/puchatyzakatek/scripts/mail-worker.php';$_SERVER['REQUEST_METHOD']='GET';
require dirname(__DIR__,3).'/main.inc.php';
require dirname(__DIR__).'/lib/pz.lib.php';
require dirname(__DIR__).'/lib/commerce.lib.php';
require dirname(__DIR__).'/lib/mail.lib.php';
try {
    $command=$argv[1]??'run';
    if($command==='check'){$c=pz_mail_config();pz_mail_validate($c);echo 'SMTP_CONFIG_OK; enabled='.(empty($c['delivery']['enabled'])?'false':'true')."\n";}
    elseif($command==='test'){
        $c=pz_mail_config();pz_mail_validate($c);$to=$c['delivery']['test_recipient']??'';
        if(!filter_var($to,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Uzupelnij test_recipient.');
        pz_mail_send($c,array('to'=>$to,'subject'=>'Test poczty - Puchaty Zakątek','body'=>'Konfiguracja SMTP działa. To wiadomość testowa.'));echo "SMTP_TEST_SENT\n";
    }elseif($command==='run'){echo json_encode(pz_mail_run(),JSON_UNESCAPED_UNICODE)."\n";}
    else throw new RuntimeException('Uzyj: check, test lub run.');
}catch(Throwable $e){fwrite(STDERR,"PZ_MAIL_ERROR: ".$e->getMessage()."\n");exit(1);}
