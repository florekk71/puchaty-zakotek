<?php
require_once __DIR__.'/booking.lib.php';
require_once __DIR__.'/mail.lib.php';
function pz_portal_tx($fn) {
    global $db;
    if($db->begin()<=0)throw new RuntimeException('Nie rozpoczęto transakcji.');
    try{pz_commerce_lock();$result=$fn();if($db->commit()<=0)throw new RuntimeException('Nie zatwierdzono zapisu.');return $result;}catch(Throwable $e){$db->rollback();throw $e;}
}
function pz_portal_hash($value,$c){return hash_hmac('sha256',$value,$c['portal']['app_secret']);}
function pz_portal_email($value){$s=strtolower(trim((string)$value));if(strlen($s)>128||!filter_var($s,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Wpisz poprawny adres e-mail.');return $s;}
function pz_portal_identity_key($provider,$value){return hash('sha256',$provider.'|'.$value);}
function pz_portal_identity($provider,$value){return pz_store('portal_identity',pz_portal_identity_key($provider,$value));}
function pz_portal_rate($key,$limit,$c) {
    $allowed=pz_portal_tx(function()use($key,$limit,$c){
        $key=pz_portal_hash('rate|'.$key,$c);$r=pz_store('portal_rate',$key)??array('until'=>0,'count'=>0);
        if($r['until']<time())$r=array('until'=>time()+3600,'count'=>0);
        if($r['count']>=$limit)return false;
        $r['count']++;pz_put('portal_rate',$key,$r);
        pz_query('UPDATE '.MAIN_DB_PREFIX."pz_store SET datec=NOW() WHERE entity=".pz_entity()." AND kind='portal_rate' AND object_key=".pz_q($key));
        // Bound storage growth; no clear-text addresses or IPs are persisted in rate records.
        pz_query('DELETE FROM '.MAIN_DB_PREFIX."pz_store WHERE entity=".pz_entity()." AND kind='portal_rate' AND datec < DATE_SUB(NOW(),INTERVAL 2 DAY)");
        return true;
    });
    if(!$allowed)throw new InvalidArgumentException('Za dużo prób. Spróbuj ponownie za godzinę.');
}
function pz_portal_account() {
    $id=$_SESSION['pz_customer']??'';
    if(!$id||($_SESSION['pz_until']??0)<time())return null;
    $a=pz_store('portal_account',$id);
    return $a&&!empty($a['active'])?$a:null;
}
function pz_portal_login($a){
    session_regenerate_id(true);$_SESSION=array('pz_customer'=>$a['id'],'pz_until'=>time()+43200,'pz_csrf'=>bin2hex(random_bytes(32)));
}
function pz_portal_new_account($email) {
    $a=array('id'=>bin2hex(random_bytes(16)),'email'=>$email,'active'=>true,'clientId'=>0,'createdAt'=>date('c'));
    pz_put('portal_account',$a['id'],$a);return $a;
}
function pz_portal_request_code($email,$c){
    if(empty($c['auth']['email_enabled']))throw new InvalidArgumentException('Logowanie przez e-mail jest wyłączone.');
    $email=pz_portal_email($email);
    pz_portal_rate('email|'.$email,5,$c);
    $mail=pz_mail_config();if(empty($mail['delivery']['enabled']))throw new RuntimeException('Poczta nie jest włączona.');pz_mail_validate($mail);
    $code=(string)random_int(10000000,99999999);$key=pz_portal_identity_key('email',$email);
    pz_portal_tx(function()use($key,$code,$email,$c){pz_put('portal_code',$key,array('hash'=>pz_portal_hash($email.'|'.$code,$c),'until'=>time()+600,'attempts'=>0));});
    $_SESSION['pz_email']=$email;
    $body='Twój kod do Strefy klienta Puchatego Zakątka: '.$code.'. Kod jest ważny 10 minut. Jeśli nie prosisz o logowanie, pomiń wiadomość.';
    pz_mail_send($mail,array('to'=>$email,'subject'=>'Kod logowania — Puchaty Zakątek','body'=>$body,'html'=>pz_mail_template('portal_login',array('Kod jednorazowy'=>$code,'Ważność'=>'10 minut'),$mail)));
    return array('ok'=>true,'message'=>'Kod wysłano. Wpisz go poniżej, aby zalogować się lub utworzyć konto.');
}
function pz_portal_check_code($email,$code,$c){
    if(empty($c['auth']['email_enabled']))throw new InvalidArgumentException('Logowanie przez e-mail jest wyłączone.');
    $email=pz_portal_email($email);$code=(string)$code;
    // Persist failed attempts even when authentication fails. Codes are single-use across sessions.
    $account=pz_portal_tx(function()use($email,$code,$c){
        $key=pz_portal_identity_key('email',$email);$r=pz_store('portal_code',$key);
        if(!$r||$r['until']<time()||$r['attempts']>=5)return null;
        $r['attempts']++;
        $ok=preg_match('/^\d{8}$/D',$code)&&hash_equals($r['hash'],pz_portal_hash($email.'|'.$code,$c));
        if($ok)$r['until']=0;
        pz_put('portal_code',$key,$r);
        if(!$ok)return null;
        $identity=pz_portal_identity('email',$email);
        if($identity){$a=pz_store('portal_account',$identity['accountId']);return $a&&!empty($a['active'])?$a:null;}
        $a=pz_portal_new_account($email);pz_put('portal_identity',$key,array('accountId'=>$a['id']));return $a;
    });
    if(!$account)throw new InvalidArgumentException('Kod jest nieprawidłowy, wykorzystany lub wygasł. Zamów nowy kod.');
    return $account;
}
function pz_portal_google_account($identity,$linkId=null){
    $sub=(string)($identity['sub']??'');$email=pz_portal_email($identity['email']??'');
    // Google is authoritative for Gmail/Workspace addresses. Other domains verify by e-mail.
    if(!preg_match('/^[0-9]{1,255}$/D',$sub)||($identity['email_verified']??false)!==true||(!str_ends_with($email,'@gmail.com')&&empty($identity['hd'])))throw new InvalidArgumentException('Dla tego konta Google użyj potwierdzenia przez e-mail.');
    return pz_portal_tx(function()use($sub,$email,$linkId){
        $old=pz_portal_identity('google',$sub);
        if($old){
            if($linkId&&$old['accountId']!==$linkId)throw new InvalidArgumentException('To konto Google jest już połączone z innym kontem.');
            $a=pz_store('portal_account',$old['accountId']);if(!$a||empty($a['active']))throw new InvalidArgumentException('Konto jest niedostępne.');return $a;
        }
        if($linkId){$a=pz_store('portal_account',$linkId);if(!$a||empty($a['active'])||$a['email']!==$email)throw new InvalidArgumentException('Połącz Google z tym samym adresem e-mail.');}
        else{
            if(pz_portal_identity('email',$email))throw new InvalidArgumentException('Masz już konto: zaloguj się kodem e-mail i wybierz „Połącz Google”.');
            $a=pz_portal_new_account($email);pz_put('portal_identity',pz_portal_identity_key('email',$email),array('accountId'=>$a['id']));
        }
        pz_put('portal_identity',pz_portal_identity_key('google',$sub),array('accountId'=>$a['id']));return $a;
    });
}
function pz_portal_actor($c){
    global $db;
    require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
    $actor=new User($db);
    if($actor->fetch(0,(string)$c['portal']['service_user_login'],'',1,pz_entity())<=0||empty($actor->statut)||!empty($actor->socid))throw new RuntimeException('Nieprawidłowy pracownik techniczny portalu.');
    $actor->loadRights();
    if(empty($actor->admin)&&!$actor->hasRight('puchatyzakatek','write')&&!$actor->hasRight('puchatyzakatek','manage'))throw new RuntimeException('Pracownik portalu nie ma prawa zapisu PZ.');
    return $actor;
}
function pz_portal_profile($a,$data,$c){
    global $db;
    if(!empty($a['clientId']))throw new InvalidArgumentException('Dane są już zapisane. W sprawie zmiany skontaktuj się z salonem.');
    $name=pz_text($data,'name',128,true);$phone=pz_text($data,'phone',20,true);
    require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
    $actor=pz_portal_actor($c);$s=new Societe($db);$s->name=$name;$s->phone=$phone;$s->email=$a['email'];$s->entity=pz_entity();$s->client=1;$s->fournisseur=0;$s->code_client='-1';
    $id=$s->create($actor);if($id<=0)throw new RuntimeException('Nie udało się utworzyć kartoteki klienta.');
    // Dolibarr may already assign the creator; avoid a duplicate-key failure.
    pz_query('INSERT INTO '.MAIN_DB_PREFIX.'societe_commerciaux (fk_soc,fk_user,fk_c_type_contact_code)'
        .' SELECT '.(int)$id.','.(int)$actor->id.", 'SALESREPTHIRD' WHERE NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX.'societe_commerciaux'
        .' WHERE fk_soc='.(int)$id.' AND fk_user='.(int)$actor->id." AND fk_c_type_contact_code='SALESREPTHIRD')");
    $a['clientId']=(int)$id;pz_put('portal_account',$a['id'],$a);pz_put('client_meta',(string)$id,array('clientKind'=>'person','source'=>'portal'));
    return array('ok'=>true);
}
function pz_portal_client($a){
    $id=(int)($a['clientId']??0);
    $r=pz_rows('SELECT s.rowid,s.nom,s.phone FROM '.MAIN_DB_PREFIX.'societe s WHERE s.rowid='.$id.' AND s.entity='.pz_entity().' AND s.client IN (1,3)'.pz_active_client_sql());
    if(!$r)throw new InvalidArgumentException('Uzupełnij dane klienta lub skontaktuj się z salonem.');return $r[0];
}
function pz_portal_dog($a,$id){
    $client=pz_portal_client($a);
    $rows=pz_rows('SELECT rowid,name FROM '.MAIN_DB_PREFIX.'pz_dog WHERE rowid='.(int)$id.' AND active=1 AND fk_soc='.(int)$client['rowid']);
    if(!$rows)throw new InvalidArgumentException('Nie znaleziono Twojego psa.');return $rows[0];
}
function pz_portal_add_dog($a,$data){
    global $db;$client=pz_portal_client($a);
    $name=pz_text($data,'name',128,true);$breed=pz_text($data,'breed',128);
    $count=pz_rows('SELECT COUNT(*) n FROM '.MAIN_DB_PREFIX.'pz_dog WHERE fk_soc='.(int)$client['rowid'].' AND active=1');
    if((int)$count[0]['n']>=10)throw new InvalidArgumentException('W sprawie dodania kolejnego psa skontaktuj się z salonem.');
    pz_query('INSERT INTO '.MAIN_DB_PREFIX.'pz_dog (fk_soc,name,breed,active,datec) VALUES ('.(int)$client['rowid'].','.pz_q($name).','.pz_q($breed).',1,NOW())');
    return array('id'=>(int)$db->last_insert_id(MAIN_DB_PREFIX.'pz_dog'));
}
function pz_portal_data($a){
    $result=array('email'=>$a['email'],'profile'=>null,'dogs'=>array(),'visits'=>array());
    if(empty($a['clientId']))return $result;
    $client=pz_portal_client($a);$result['profile']=array('name'=>$client['nom'],'phone'=>$client['phone']);
    $result['dogs']=pz_rows('SELECT rowid id,name,breed FROM '.MAIN_DB_PREFIX.'pz_dog WHERE active=1 AND fk_soc='.(int)$client['rowid'].' ORDER BY name');
    // Internal staff notes, medical notes, invoices and unrelated clients are never returned.
    $result['visits']=pz_rows('SELECT v.rowid id,v.visit_date date,v.status,d.name dog FROM '.MAIN_DB_PREFIX.'pz_visit v JOIN '.MAIN_DB_PREFIX.'pz_dog d ON d.rowid=v.fk_dog WHERE v.fk_soc='.(int)$client['rowid'].' AND d.fk_soc='.(int)$client['rowid'].' ORDER BY v.visit_date DESC LIMIT 100');return $result;
}
function pz_portal_book($a,$data,$c){
    global $db;
    $request=pz_text($data,'requestKey',64,true);if(!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$request))throw new InvalidArgumentException('Nieprawidłowa operacja.');
    $key=hash('sha256',$a['id'].'|'.$request);$fingerprint=hash('sha256',json_encode(array((int)($data['dogId']??0),(string)($data['date']??''))));
    $old=pz_store('portal_booking',$key);if($old){if(!hash_equals($old['fingerprint'],$fingerprint))throw new InvalidArgumentException('Najpierw sprawdź poprzednią rezerwację.');return array('id'=>$old['id']);}
    $dog=pz_portal_dog($a,$data['dogId']??0);$date=pz_booking_assert($data['date']??'',0,true,$c);
    $calendar=pz_booking_calendar(substr($date,0,10),$c);$allowed=array_column(array_filter($calendar[0]['slots'],function($s){return $s['available'];}),'date');
    if(!in_array($date,$allowed,true))throw new InvalidArgumentException('Wybierz jeden z dostępnych terminów.');
    pz_query('INSERT INTO '.MAIN_DB_PREFIX.'pz_visit (fk_soc,fk_dog,visit_date,status,notes,datec) VALUES ('.(int)$a['clientId'].','.(int)$dog['rowid'].','.pz_q($date).",'planned','Rezerwacja ze strefy klienta',NOW())");
    $id=(int)$db->last_insert_id(MAIN_DB_PREFIX.'pz_visit');pz_put('portal_booking',$key,array('id'=>$id,'fingerprint'=>$fingerprint));
    pz_mail_capture('plan',array('date'=>$date),array('id'=>$id));return array('id'=>$id);
}
function pz_portal_cancel($a,$id){
    $client=pz_portal_client($a);
    $rows=pz_rows('SELECT rowid,visit_date,status FROM '.MAIN_DB_PREFIX.'pz_visit WHERE rowid='.(int)$id.' AND fk_soc='.(int)$client['rowid']);
    if(!$rows)throw new InvalidArgumentException('Nie znaleziono Twojej wizyty.');$v=$rows[0];
    if($v['status']==='cancelled')return array('ok'=>true);
    if($v['status']!=='planned'||(new DateTimeImmutable($v['visit_date'],new DateTimeZone('Europe/Warsaw')))->getTimestamp()<=time())throw new InvalidArgumentException('Tej wizyty nie można odwołać online. Skontaktuj się z salonem.');
    pz_query('UPDATE '.MAIN_DB_PREFIX."pz_visit SET status='cancelled' WHERE rowid=".(int)$id." AND status='planned'");pz_mail_capture('cancel',array('id'=>(int)$id),array('ok'=>true));return array('ok'=>true);
}
