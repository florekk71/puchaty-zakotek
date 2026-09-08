<?php
function pz_portal_config() {
    $path=getenv('PZ_PORTAL_CONFIG') ?: '/run/secrets/pz-portal.ini';
    if(!is_file($path))return array('portal'=>array('enabled'=>false));
    $c=@parse_ini_file($path,true,INI_SCANNER_TYPED);
    if(!is_array($c))throw new RuntimeException('Nieprawidłowy plik portal-config.ini.');
    return $c;
}
function pz_portal_validate($c) {
    $p=$c['portal']??array();$a=$c['auth']??array();
    $url=rtrim((string)($p['base_url']??''),'/');$u=parse_url($url);
    $local=!empty($p['allow_local_http'])&&in_array($u['host']??'',array('localhost','127.0.0.1'),true);
    if(!filter_var($url,FILTER_VALIDATE_URL)||isset($u['user'])||isset($u['pass'])||isset($u['query'])||isset($u['fragment'])||(($u['scheme']??'')!=='https'&&!($local&&($u['scheme']??'')==='http'))||!str_ends_with($u['path']??'','/custom/puchatyzakatek/portal'))throw new RuntimeException('Uzupełnij base_url portalu (HTTPS).');
    if(strlen((string)($p['app_secret']??''))<64||empty($p['service_user_login']))throw new RuntimeException('Uzupełnij app_secret i service_user_login.');
    if(!empty($a['google_enabled'])&&(empty($a['google_client_id'])||empty($a['google_client_secret'])))throw new RuntimeException('Uzupełnij konfigurację Google.');
    if(empty($a['google_enabled'])&&empty($a['email_enabled']))throw new RuntimeException('Włącz przynajmniej jedną metodę logowania.');
    if(!filter_var($p['privacy_url']??'',FILTER_VALIDATE_URL)||parse_url($p['privacy_url'],PHP_URL_SCHEME)!=='https')throw new RuntimeException('Uzupełnij privacy_url (HTTPS).');
    if(empty($c['calendar']['enforce_hours']))throw new RuntimeException('Portal wymaga enforce_hours = true.');
    pz_booking_hours_validate($c);
}
function pz_booking_hours_validate($c) {
    $k=$c['calendar']??array();
    $days=(int)($k['days_ahead']??90);if($days<1||$days>365)throw new RuntimeException('days_ahead: od 1 do 365.');
    foreach(array('monday','tuesday','wednesday','thursday','friday','saturday','sunday') as $day){
        $v=(string)($k[$day]??'');if($v==='')continue;
        if(!preg_match('/^([01]\d|2[0-3]):([0-5]\d)-([01]\d|2[0-3]):([0-5]\d)$/D',$v,$m)||((int)$m[3]*60+(int)$m[4])-((int)$m[1]*60+(int)$m[2])<180)throw new RuntimeException('Nieprawidłowe godziny: '.$day.'. Wymagane minimum 3 godziny, w jednym dniu.');
    }
    foreach(array_filter(array_map('trim',explode(',',(string)($k['closed_dates']??'')))) as $d){$v=DateTimeImmutable::createFromFormat('!Y-m-d',$d);if(!$v||$v->format('Y-m-d')!==$d)throw new RuntimeException('Nieprawidłowe closed_dates.');}
}
