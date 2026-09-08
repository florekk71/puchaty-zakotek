<?php
require __DIR__.'/bootstrap.php';
$op=(string)($_GET['op']??'');$post=$_SERVER['REQUEST_METHOD']==='POST';
try{
    $account=pz_portal_account();
    if(!$post&&$op==='status')pz_portal_reply(array('csrf'=>$_SESSION['pz_csrf'],'authenticated'=>(bool)$account,'email'=>!empty($portalConfig['auth']['email_enabled']),'google'=>!empty($portalConfig['auth']['google_enabled']),'privacyUrl'=>$portalConfig['portal']['privacy_url'],'today'=>(new DateTimeImmutable('now',new DateTimeZone('Europe/Warsaw')))->format('Y-m-d'),'daysAhead'=>(int)($portalConfig['calendar']['days_ahead']??90)));
    if($post){
        $data=json_decode(file_get_contents('php://input'),true,16,JSON_THROW_ON_ERROR);if(!is_array($data))throw new InvalidArgumentException('Nieprawidłowe dane.');
        if($op==='email'){
            pz_portal_rate('mail-ip|'.($_SERVER['REMOTE_ADDR']??''),20,$portalConfig);
            pz_portal_reply(pz_portal_request_code($data['email']??'',$portalConfig));
        }
        if($op==='verify'){
            $a=pz_portal_check_code($_SESSION['pz_email']??'',$data['code']??'',$portalConfig);pz_portal_login($a);pz_portal_reply(array('ok'=>true));
        }
        if($op==='logout'){$_SESSION=array();session_regenerate_id(true);pz_portal_reply(array('ok'=>true));}
    }
    // Public calendar exposes only the explicit anonymous projection, never customer records.
    if(!$post&&$op==='calendar')pz_portal_reply(array('days'=>pz_booking_calendar((string)($_GET['from']??''),$portalConfig)));
    if(!$account)pz_portal_reply(array('error'=>'Zaloguj się, aby zarezerwować wizytę lub zobaczyć swoje dane.'),401);
    if(!$post&&$op==='data')pz_portal_reply(pz_portal_data($account));
    if(!$post)throw new InvalidArgumentException('Nieznana operacja.');
    $result=pz_portal_tx(function()use($op,$data,$portalConfig){
        // Re-read ownership under the transaction lock, including disabled accounts.
        $a=pz_portal_account();if(!$a)throw new InvalidArgumentException('Zaloguj się ponownie.');
        switch($op){
            case 'profile':return pz_portal_profile($a,$data,$portalConfig);
            case 'dog':return pz_portal_add_dog($a,$data);
            case 'book':return pz_portal_book($a,$data,$portalConfig);
            case 'cancel':return pz_portal_cancel($a,(int)($data['id']??0));
            default:throw new InvalidArgumentException('Nieznana operacja.');
        }
    });pz_portal_reply($result);
}catch(Throwable $e){
    dol_syslog('PZ portal API: '.$e->getMessage(),LOG_ERR);
    pz_portal_reply(array('error'=>$e instanceof InvalidArgumentException?$e->getMessage():'Operacja nie została potwierdzona. Sprawdź Moje wizyty przed ponowieniem. Jeśli problem dotyczy kodu e-mail, spróbuj ponownie za chwilę.'),$e instanceof InvalidArgumentException?400:500);
}
