<?php
require_once __DIR__.'/mail-template.lib.php';
require_once __DIR__.'/mail-mime.lib.php';
// SMTP secrets are mounted outside the web root. No secret values are returned by the API.
function pz_mail_config() {
    $path=getenv('PZ_MAIL_CONFIG') ?: '/run/secrets/pz-mail.ini';
    if(!is_file($path))return array('delivery'=>array('enabled'=>false));
    $c=@parse_ini_file($path,true,INI_SCANNER_TYPED);
    if(!is_array($c))throw new RuntimeException('Nieprawidlowy plik konfiguracji poczty.');
    return $c;
}
function pz_mail_enabled($c,$event){return !empty($c['delivery']['enabled'])&&!empty($c['messages'][$event]);}
function pz_mail_owner(){return pz_config()['mailOwner']??array('address'=>'','enabled'=>false,'events'=>array());}
function pz_mail_owner_enabled($c,$event){$o=pz_mail_owner();return !empty($c['delivery']['enabled'])&&!empty($o['enabled'])&&!empty($o['events'][$event])&&filter_var($o['address']??'',FILTER_VALIDATE_EMAIL);}
function pz_mail_owner_save($input){
    if(!pz_can_manage())throw new InvalidArgumentException('Brak uprawnień do ustawień poczty.');
    $address=trim((string)($input['address']??''));$enabled=($input['enabled']??'0')==='1';
    if(($enabled||$address!=='')&&!filter_var($address,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Wpisz poprawny adres e-mail właściciela salonu.');
    $events=array();foreach(array('booking_confirmation','appointment_changed','appointment_cancelled') as $e)$events[$e]=($input[$e]??'0')==='1';
    $cfg=pz_config();$cfg['mailOwner']=array('address'=>$address,'enabled'=>$enabled,'events'=>$events);pz_put('config','main',$cfg);return array('ok'=>true);
}
function pz_mail_validate($c) {
    $s=$c['smtp']??array();$from=$c['sender']??array();
    if(empty($s['host'])||preg_match('/[\s\/\\\\]/',(string)$s['host'])||!in_array($s['encryption']??'',array('tls','starttls'),true))throw new RuntimeException('Uzupelnij host SMTP i szyfrowanie tls/starttls.');
    if((int)($s['port']??0)<1||(int)$s['port']>65535||empty($s['username'])||empty($s['password']))throw new RuntimeException('Uzupelnij port, login i haslo SMTP.');
    if(empty($s['verify_certificate']))throw new RuntimeException('Weryfikacja certyfikatu SMTP musi byc wlaczona.');
    foreach(array('address','reply_to') as $key)if(($key==='address'||!empty($from[$key]))&&!filter_var($from[$key]??'',FILTER_VALIDATE_EMAIL))throw new RuntimeException('Nieprawidlowy adres nadawcy lub odpowiedzi.');
    if(preg_match('/[\r\n<>]/',(string)($from['name']??'')))throw new RuntimeException('Nieprawidlowa nazwa nadawcy.');
}
function pz_mail_enqueue($event,$id,$stamp='',$revision='') {
    try{$c=pz_mail_config();}catch(Throwable $e){dol_syslog('PZ mail: invalid configuration, notification not queued',LOG_ERR);return;}
    foreach(array('client','owner') as $audience){
        if($audience==='owner'?!pz_mail_owner_enabled($c,$event):!pz_mail_enabled($c,$event))continue;
        $base=$event.'|'.$id.'|'.$stamp.'|'.$revision;
        // Keep existing customer keys stable during upgrades.
        $key=hash('sha256',$base.($audience==='owner'?'|owner':''));
        if(pz_store('mail',$key)!==null)continue;
        $m=array('id'=>$key,'event'=>$event,'audience'=>$audience,'objectId'=>(string)$id,'stamp'=>$stamp,'state'=>'queued','createdAt'=>date('c'),'attempts'=>0);
        if($audience==='owner')$m['ownerAddress']=pz_mail_owner()['address'];
        pz_put('mail',$key,$m);
    }
}
function pz_mail_capture($action,$input,$result) {
    if($action==='plan')pz_mail_enqueue('booking_confirmation',$result['id'],str_replace('T',' ',(string)($input['date']??'')));
    if($action==='reschedule'){
        $key=(string)(int)$input['id'];$stamp=substr(str_replace('T',' ',(string)$input['date']),0,16);
        $prior=pz_store('mail_revision',$key);
        if(!$prior||$prior['date']!==$stamp){
            $revision=(int)($prior['revision']??0)+1;
            pz_put('mail_revision',$key,array('date'=>$stamp,'revision'=>$revision));
            pz_mail_enqueue('appointment_changed',$key,$stamp,(string)$revision);
        }
    }
    if($action==='cancel')pz_mail_enqueue('appointment_cancelled',(int)$input['id']);
    $id=null;
    if($action==='sale')$id=$result['documentId']??null;
    if($action==='paid')$id='visit-'.(int)$input['id'];
    if(in_array($action,array('paiddocument','issuedocument','visitdocument'),true))$id=$result['id']??($input['id']??null);
    if($id){$d=pz_store('document',(string)$id);if($d&&$d['state']==='issued'&&!empty($d['received']))pz_mail_enqueue('invoice_after_payment',$id);}
}
function pz_mail_records(){return array_map(function($r){return json_decode($r['payload'],true,512,JSON_THROW_ON_ERROR);},pz_rows('SELECT payload FROM '.MAIN_DB_PREFIX.'pz_store WHERE entity='.pz_entity()." AND kind='mail' ORDER BY rowid DESC"));}
function pz_mail_status(){
    if(!pz_can_manage())throw new InvalidArgumentException('Brak uprawnien do poczty.');
    try{$c=pz_mail_config();$enabled=!empty($c['delivery']['enabled']);$error='';}catch(Throwable $e){$enabled=false;$error='Blad pliku konfiguracji poczty.';}
    return array('enabled'=>$enabled,'error'=>$error,'owner'=>pz_mail_owner(),'messages'=>array_slice(pz_mail_records(),0,100));
}
function pz_mail_retry($id){
    if(!pz_can_manage())throw new InvalidArgumentException('Brak uprawnien do poczty.');
    $m=pz_store('mail',pz_key($id));if(!$m||!in_array($m['state'],array('failed','unknown','skipped'),true))throw new InvalidArgumentException('Ta wiadomosc nie wymaga ponowienia.');
    $m['state']='queued';unset($m['error']);pz_put('mail',$id,$m);return array('ok'=>true);
}
function pz_mail_visit($id){
    $rows=pz_rows('SELECT v.*,s.email,s.nom client,d.name dog FROM '.MAIN_DB_PREFIX.'pz_visit v JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=v.fk_soc JOIN '.MAIN_DB_PREFIX.'pz_dog d ON d.rowid=v.fk_dog WHERE v.rowid='.(int)$id.' AND s.entity='.pz_entity());
    return $rows[0]??null;
}
function pz_mail_reminders($c,$now=null){
    if(!pz_mail_enabled($c,'appointment_reminder'))return;
    $now=$now??time();$hours=(int)($c['reminders']['hours_before']??24);
    if($hours<1||$hours>168)throw new RuntimeException('Przypomnienie: wybierz od 1 do 168 godzin.');
    $zone=new DateTimeZone('Europe/Warsaw');
    $dates=function($ts)use($zone){return (new DateTimeImmutable('@'.$ts))->setTimezone($zone)->format('Y-m-d H:i:s');};
    $visits=pz_rows('SELECT v.rowid,v.visit_date FROM '.MAIN_DB_PREFIX.'pz_visit v JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=v.fk_soc WHERE s.entity='.pz_entity()." AND v.status='planned' AND v.visit_date>".pz_q($dates($now)).' AND v.visit_date<='.pz_q($dates($now+$hours*3600)));
    foreach($visits as $v)pz_mail_enqueue('appointment_reminder',$v['rowid'],$v['visit_date']);
}
function pz_mail_prepare($m,$c){
    $file=null;$body='';$details=array();$event=$m['event'];
    $owner=($m['audience']??'client')==='owner';
    if($owner&&(!pz_mail_owner_enabled($c,$event)||($m['ownerAddress']??'')!==(pz_mail_owner()['address']??'')))return null;
    if($event==='invoice_after_payment'){
        $d=pz_store('document',$m['objectId']);if(!$d||$d['state']!=='issued'||empty($d['received']))return null;
        $to=$d['buyer']['email']??'';
        if(!filter_var($to,FILTER_VALIDATE_EMAIL))return array('skip'=>'Brak poprawnego e-mail nabywcy w dokumencie.');
        require_once __DIR__.'/document-pdf.lib.php';$file=pz_document_pdf($d);
        $details=array('Dokument'=>$d['number'],'Data sprzedaży'=>$d['saleDate'],'Kwota'=>number_format($d['total']/100,2,',',' ').' zł','Płatność'=>'Opłacono');
        $subject='Faktura '.$d['number'].' - Puchaty Zakątek';$body="Dzień dobry,\n\nw załączniku przesyłamy opłaconą fakturę ".$d['number'].".\nDziękujemy za wizytę!";
    }else{
        $v=pz_mail_visit($m['objectId']);if(!$v)return null;
        if($event==='appointment_cancelled'&&$v['status']!=='cancelled')return null;
        if($event!=='appointment_cancelled'&&$v['status']!=='planned')return null;
        if($m['stamp']!==''&&substr($m['stamp'],0,16)!==substr($v['visit_date'],0,16))return null;
        if($event!=='appointment_cancelled'&&(new DateTimeImmutable($v['visit_date'],new DateTimeZone('Europe/Warsaw')))->getTimestamp()<=time())return null;
        $to=$owner?$m['ownerAddress']:$v['email'];if(!filter_var($to,FILTER_VALIDATE_EMAIL))return array('skip'=>'Brak poprawnego adresu e-mail klienta.');
        $titles=array('booking_confirmation'=>'Potwierdzenie rezerwacji','appointment_changed'=>'Zmiana terminu wizyty','appointment_cancelled'=>'Odwołanie wizyty','appointment_reminder'=>'Przypomnienie o wizycie');
        $details=array('Pupil'=>$v['dog'],'Termin'=>(new DateTimeImmutable($v['visit_date']))->format('d.m.Y H:i').' (czas polski)');
        $subject=($owner?'Salon: ':'').($titles[$event]??'Wizyta').' - Puchaty Zakątek';
        if($owner)$details=array('Klient'=>$v['client'])+$details;
        $body="Dzień dobry,\n\n".$titles[$event]."\nPies: ".$v['dog']."\nTermin: ".(new DateTimeImmutable($v['visit_date']))->format('d.m.Y H:i')." (czas polski).";
    }
    $body.="\n\n".($c['salon']['address']??'')."\nTelefon: ".($c['salon']['phone']??'')."\n".($c['salon']['footer']??'Puchaty Zakątek');
    return array('to'=>$to,'subject'=>$subject,'body'=>$body,'html'=>pz_mail_template($owner?'owner_'.$event:$event,$details,$c),'file'=>$file);
}
function pz_mail_send($c,$payload){
    global $conf;
    pz_mail_validate($c);
    if(!function_exists('openssl_open'))throw new RuntimeException('Brak obslugi OpenSSL. Wysylka SMTP zostala zatrzymana.');
    require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
    $saved=clone $conf->global;
    try{
        // Isolate this transport from global forced recipients and automatic copies.
        foreach(array('MAIN_MAIL_AUTOCOPY_TO','MAIN_MAIL_FORCE_FROM','MAIN_MAIL_FORCE_SENDTO','MAIN_MAIL_FORCE_NOT_SENDING_TO','MAIN_MAIL_DEBUG') as $k)$conf->global->$k='';
        $values=array('MAIN_MAIL_SENDMODE'=>'smtps','MAIN_MAIL_SMTP_SERVER'=>$c['smtp']['host'],'MAIN_MAIL_SMTP_PORT'=>(int)$c['smtp']['port'],'MAIN_MAIL_SMTPS_ID'=>$c['smtp']['username'],'MAIN_MAIL_SMTPS_PW'=>$c['smtp']['password'],'MAIN_MAIL_SMTPS_AUTH_TYPE'=>'LOGIN','MAIN_MAIL_EMAIL_TLS'=>$c['smtp']['encryption']==='tls'?1:0,'MAIN_MAIL_EMAIL_STARTTLS'=>$c['smtp']['encryption']==='starttls'?1:0,'MAIN_MAIL_EMAIL_SMTP_ALLOW_SELF_SIGNED'=>0);
        foreach($values as $k=>$v)$conf->global->{$k.'_PZ'}=$v;
        $file=$payload['file']??null;
        $html=$payload['html']??pz_mail_template('test',array(),$c);
        $conf->global->MAIN_MAIL_ADD_INLINE_IMAGES_IF_DATA=0;
        $conf->global->MAIN_MAIL_ADD_INLINE_IMAGES_IF_IN_MEDIAS=0;
        $conf->global->MAIN_MAIL_FORCE_CONTENT_TYPE_TO_HTML=0;
        $conf->global->MAIN_MAIL_USE_MULTI_PART=1;
        $mime=pz_mail_mime($html,$payload['body']??'', $file);
        // CMailFile still configures the verified SMTP transport and authentication.
        $mail=new CMailFile($payload['subject'],$payload['to'],($c['sender']['name']??'Puchaty Zakątek').' <'.$c['sender']['address'].'>',$payload['body']??'',array(),array(),array(),'','',0,0,'','','','','pz',($c['sender']['reply_to']??'')?:$c['sender']['address']);
        if(!is_object($mail->smtps))throw new RuntimeException('Nie przygotowano transportu SMTP.');
        require_once __DIR__.'/mail-smtp.class.php';
        $original=$mail->smtps;$smtp=new PzMimeSMTP();
        $smtp->setCharSet('UTF-8');$smtp->setSubject($original->getSubject());
        $smtp->setTO($original->getTO());$smtp->setFrom($original->getFrom('org'));
        $smtp->setReplyTo(($c['sender']['reply_to']??'')?:$c['sender']['address']);
        $smtp->setOptions(array('ssl'=>array('verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false)));
        $smtp->pzMime=$mime;pz_mail_check_lines($smtp->getHeader().$mime);$mail->smtps=$smtp;
        if(is_object($mail->smtps))$mail->smtps->setSMTPTimeout(max(5,min(60,(int)($c['smtp']['timeout_seconds']??20)))); if(!$mail->sendfile())throw new RuntimeException('SMTP nie potwierdzil wyslania. Sprawdz ustawienia i skrzynke odbiorcy przed ponowieniem.');
    }finally{$conf->global=$saved;}
}
function pz_mail_run(){
    global $db;
    $c=pz_mail_config();if(empty($c['delivery']['enabled']))return array('enabled'=>false,'sent'=>0);
    pz_mail_validate($c);$lock='pz-mail-'.pz_entity();
    $got=pz_rows('SELECT GET_LOCK('.pz_q($lock).',0) acquired');if(empty($got[0]['acquired']))return array('busy'=>true);
    $sent=0;
    try{
        $db->begin();pz_commerce_lock();pz_mail_reminders($c);if($db->commit()<=0)throw new RuntimeException('Blad zapisu kolejki.');
        foreach(array_reverse(pz_mail_records()) as $m){
            if($m['state']==='sending'){$m['state']='unknown';$m['error']='Poprzednia proba zostala przerwana. Sprawdz odbior przed ponowieniem.';pz_put('mail',$m['id'],$m);continue;}
            if($m['state']!=='queued')continue;
            if((($m['audience']??'client')==='owner')?!pz_mail_owner_enabled($c,$m['event']):!pz_mail_enabled($c,$m['event'])){$m['state']='skipped';$m['error']='Ten rodzaj wiadomosci jest wylaczony.';pz_put('mail',$m['id'],$m);continue;}
            try{
                $p=pz_mail_prepare($m,$c);
                if(!$p||isset($p['skip'])){$m['state']='skipped';$m['error']=$p['skip']??'Wiadomosc nieaktualna.';pz_put('mail',$m['id'],$m);continue;}
                $m['state']='sending';$m['attempts']++;$m['attemptedAt']=date('c');pz_put('mail',$m['id'],$m);
                pz_mail_send($c,$p);$m['state']='sent';$m['sentAt']=date('c');unset($m['error']);$sent++;
            }catch(Throwable $e){$m['state']=($m['state']==='sending'?'unknown':'failed');$m['error']=$m['state']==='unknown'?'Brak potwierdzenia SMTP. Sprawdz odbior przed ponowieniem.':'Nie przygotowano wiadomosci. Sprawdz adres i dokument.';}
            pz_put('mail',$m['id'],$m);
            if($sent>=20)break;
        }
    }finally{pz_query('SELECT RELEASE_LOCK('.pz_q($lock).')');}
    return array('enabled'=>true,'sent'=>$sent);
}

