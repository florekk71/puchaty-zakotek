<?php
// Customer sessions never read or create Dolibarr login cookies or employee permissions.
define('NOLOGIN',1);define('NOSESSION',1);define('NOREQUIREUSER',1);define('NOREQUIREMENU',1);define('NOCSRFCHECK',1);define('NOTOKENRENEWAL',1);
$_SESSION=array();
unset($_GET['entity'],$_POST['entity'],$_GET['disablemodules'],$_POST['disablemodules']);
require dirname(__DIR__,3).'/main.inc.php';
require_once dirname(__DIR__).'/lib/pz.lib.php';
require_once dirname(__DIR__).'/lib/commerce.lib.php';
require_once dirname(__DIR__).'/lib/portal.lib.php';
header('Cache-Control: no-store, private');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'self' https://accounts.google.com");
function pz_portal_reply($data,$status=200){http_response_code($status);header('Content-Type: application/json; charset=utf-8');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
try{
    $portalConfig=pz_portal_config();
    if(empty($portalConfig['portal']['enabled']))pz_portal_reply(array('error'=>'Strefa klienta nie jest jeszcze uruchomiona.'),503);
    pz_portal_validate($portalConfig);
    if(empty($conf->puchatyzakatek->enabled)||!pz_ready()||pz_store('config','main')===null)throw new RuntimeException('Moduł salonu nie jest gotowy.');
    $portalUrl=rtrim($portalConfig['portal']['base_url'],'/');$portalPath=parse_url($portalUrl,PHP_URL_PATH);
    ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
    session_name('PZCUSTOMER_'.substr(hash('sha256',$portalUrl.'|'.pz_entity()),0,12));
    session_set_cookie_params(array('lifetime'=>0,'path'=>$portalPath.'/','secure'=>parse_url($portalUrl,PHP_URL_SCHEME)==='https','httponly'=>true,'samesite'=>'Lax'));
    session_start();
    if(empty($_SESSION['pz_csrf']))$_SESSION['pz_csrf']=bin2hex(random_bytes(32));
    if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
        $token=(string)($_SERVER['HTTP_X_PZ_CSRF']??$_POST['csrf']??'');
        if(!$token||!hash_equals($_SESSION['pz_csrf'],$token))pz_portal_reply(array('error'=>'Sesja wygasła. Odśwież stronę.'),403);
        if((int)($_SERVER['CONTENT_LENGTH']??0)>16384)pz_portal_reply(array('error'=>'Zbyt duże żądanie.'),413);
        // REMOTE_ADDR must be restored by a trusted reverse proxy, never trust client X-Forwarded-For here.
        pz_portal_rate('ip|'.($_SERVER['REMOTE_ADDR']??''),120,$portalConfig);
    }
}catch(Throwable $e){dol_syslog('PZ portal bootstrap: '.$e->getMessage(),LOG_ERR);pz_portal_reply(array('error'=>'Strefa klienta jest chwilowo niedostępna. Skontaktuj się z salonem.'),503);}
