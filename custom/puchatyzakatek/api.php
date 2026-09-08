<?php
require '../../main.inc.php';
require_once __DIR__.'/lib/pz.lib.php';
require_once __DIR__.'/lib/reservation.lib.php';
require_once __DIR__.'/lib/booking.lib.php';
require_once __DIR__.'/lib/client.lib.php';
require_once __DIR__.'/lib/commerce.lib.php';
require_once __DIR__.'/lib/ndg.lib.php';
require_once __DIR__.'/lib/backup.lib.php';
require_once __DIR__.'/lib/mail.lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function pz_reply($data,$code=200) { $data['_token']=(string)($_SESSION['newtoken']??''); http_response_code($code); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_INVALID_UTF8_SUBSTITUTE); exit; }
if (empty($user->id) || !empty($user->socid) || empty($conf->puchatyzakatek->enabled) || !pz_can_read()) pz_reply(array('error'=>'Brak uprawnień do modułu.'),403);
if ((int)($_SERVER['HTTP_X_PZ_USER']??0)!==(int)$user->id) pz_reply(array('error'=>'Zmieniono zalogowaną osobę. Odśwież stronę przed dalszą pracą.'),409);
$action=GETPOST('op','aZ09');
$write=$_SERVER['REQUEST_METHOD']==='POST';
try {
    if ($write) {
        if (!pz_can_write()) pz_reply(array('error'=>'Brak uprawnień do zapisu.'),403);
        $token=(string)GETPOST('token','alphanohtml');
        if (!$token || !hash_equals((string)($_SESSION['token']??''),$token)) pz_reply(array('error'=>'Sesja wygasła. Odśwież stronę.','code'=>'csrf'),403);
    }
    if ($action==='status' && !$write) pz_reply(array('ready'=>pz_ready() && pz_store('config','main')!==null));
    if ($action==='install' && $write) { if (!$user->admin) pz_reply(array('error'=>'Wymagany administrator.'),403); pz_install(); pz_reply(array('ok'=>true)); }
    if (!pz_ready()) pz_reply(array('error'=>'Najpierw przygotuj bazę modułu.'),409);
    if ($action==='salestatus' && !$write) {
        $key=(string)GETPOST('requestKey','alphanohtml');
        if (!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$key)) throw new InvalidArgumentException('Nieprawidłowy identyfikator zapisu.');
        $saved=pz_store('sale',$key);
        if (!empty($saved['aborted'])) pz_reply(array('state'=>'aborted'));
        if ($saved && !empty($saved['id'])) { pz_visit($saved['id']); pz_reply(array('state'=>'saved','id'=>(int)$saved['id'],'total'=>(int)$saved['total'],'documentId'=>$saved['documentId']??null,'documentNumber'=>$saved['documentNumber']??null)); }
        pz_reply(array('state'=>$saved===null?'absent':'unknown'));
    }
    if ($action==='backupscript' && !$write) pz_reply(pz_backup_script(GETPOSTINT('restore')===1));
    if ($action==='mailstatus' && !$write) pz_reply(pz_mail_status());
    if ($action==='commerce' && !$write) pz_reply(pz_commerce_data());
    if ($action==='ndg' && !$write) pz_reply(pz_ndg_data(GETPOSTINT('year') ?: (int)date('Y')));
    if ($action==='nextappointment' && !$write) {
        if (!pz_can_write()) pz_reply(array('error'=>'Brak uprawnień do dodawania wizyt.'),403);
        pz_reply(pz_booking_next((string)GETPOST('from','alphanohtml')));
    }
    if ($action==='blockslots' && !$write) {
        $day=pz_booking_calendar((string)GETPOST('from','alphanohtml'),pz_portal_config())[0];
        $start=new DateTimeImmutable($day['date'],new DateTimeZone('Europe/Warsaw'));
        $rows=pz_booking_rows($start->format('Y-m-d H:i:s'),$start->modify('+1 day')->format('Y-m-d H:i:s'));
        $day['visitCount']=count(array_filter($rows,function($r){return (int)$r['rowid']>0;}));
        $day['blockCount']=count($rows)-$day['visitCount'];
        pz_reply(array('day'=>$day));
    }
    if ($action==='data' && !$write) pz_reply(pz_data());
    if ($action==='receipt' && !$write) {
        $v=pz_visit(GETPOSTINT('id'));
        if ($v['status']!=='completed') throw new InvalidArgumentException('Wizyta nie jest rozliczona.');
        $lines=pz_rows('SELECT service_name,qty,unit_price,total_price FROM '.MAIN_DB_PREFIX.'pz_visit_line WHERE fk_visit='.(int)$v['rowid'].' ORDER BY rowid');
        $document=pz_store('document','visit-'.(int)$v['rowid']);if($document){pz_document_access($document);if((int)($document['visitId']??0)!==(int)$v['rowid'])throw new RuntimeException('Nieprawidłowe powiązanie dokumentu.');}
        pz_reply(array('visit'=>$v,'lines'=>$lines,'config'=>pz_config(),'document'=>$document,'payment'=>pz_store('payment',(string)$v['rowid'])??array()));
    }
    if (!$write) pz_reply(array('error'=>'Nieznana operacja.'),400);
    $data=json_decode((string)GETPOST('data','none'),true,512,JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new InvalidArgumentException('Nieprawidłowe dane.');
    if ($db->begin()<=0) throw new RuntimeException('Nie udało się rozpocząć transakcji.');
    pz_commerce_lock();
    switch ($action) {
    case 'mailownersettings': $result=pz_mail_owner_save($data);break;
    case 'mailretry': $result=pz_mail_retry((string)($data['id']??''));break;
    case 'catalog': $result=pz_catalog_save($data);break;
    case 'unit': $result=pz_unit_save($data);break;
    case 'document': $result=pz_document_save($data);break;
    case 'issuedocument': $result=pz_document_issue($data);break;
    case 'paiddocument': $result=pz_document_action($data,'paid');break;
    case 'voiddocument': $result=pz_document_action($data,'void');break;
    case 'visitdocument': $result=pz_document_from_visit($data);break;
    case 'businesssettings': $result=pz_business_settings($data);break;
    case 'limits': $result=pz_limits_save($data);break;
    case 'editexpense': $result=pz_expense_change($data);break;
    case 'removeexpense': $result=pz_expense_change($data,true);break;
    case 'ndgentry': $result=pz_ndg_entry($data);break;
    case 'ndgcost': $result=pz_ndg_cost($data);break;
    case 'archive': $result=pz_archive_card($data['kind']??'', $data['id']??0); break;
    case 'restore': $result=pz_archive_card($data['kind']??'', $data['id']??0, true); break;
    case 'client': $result=pz_save_client($data); break;
    case 'dog': $result=pz_save_dog($data); break;
    case 'abortsale':
        $key=pz_text($data,'requestKey',64,true);
        if (!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$key)) throw new InvalidArgumentException('Nieprawidłowy identyfikator zapisu.');
        $old=pz_store('sale',$key);
        if (!empty($old['id'])) { pz_visit($old['id']); $result=array_merge($old,array('state'=>'saved')); break; }
        if ($old===null) pz_query('INSERT INTO '.MAIN_DB_PREFIX.'pz_store (entity,kind,object_key,payload,datec) VALUES ('.pz_entity().",'sale',".pz_q($key).",'{\"aborted\":true}',NOW())");
        elseif (empty($old['aborted'])) throw new InvalidArgumentException('Operacja w toku. Sprawdź wynik ponownie.');
        $result=array('state'=>'aborted');break;
    case 'sale':
        // A retry returns the original result, including its invoice choice.
        $previous=pz_store('sale',pz_text($data,'requestKey',64,true));
        if (!empty($previous['id'])) { pz_visit($previous['id']); $result=$previous; break; }
        $result=pz_sale($data);
        pz_stock_apply('sale-'.$data['requestKey'],array_map(function($s){return array('productId'=>$s['id'],'qty'=>1000);},$data['items']));
        if (($data['issueInvoice']??false)===true) {
            $document=pz_document_from_visit(array('id'=>$result['id']));$savedDocument=pz_store('document',$document['id']);
            $result['documentId']=$document['id'];$result['documentNumber']=$savedDocument['number'];
        }
        pz_put('sale',$data['requestKey'],$result);break;
    case 'block':
        $key=pz_text($data,'requestKey',64,true);$saved=pz_store('block_request',$key);
        if($saved){$result=$saved;break;}
        $result=pz_booking_block_save($data);pz_put('block_request',$key,$result);break;
    case 'blockvisit': $result=pz_booking_block_visit($data);break;
    case 'unblock': $result=pz_booking_block_release($data);break;
    case 'plan':
        $dog=pz_dog($data['dogId']??0); $date=pz_booking_staff_assert($data['date']??'');
        pz_query('INSERT INTO '.MAIN_DB_PREFIX.'pz_visit (fk_soc,fk_dog,visit_date,status,notes,datec) VALUES ('.(int)$dog['fk_soc'].','.(int)$dog['rowid'].','.pz_q($date).",'planned',".pz_q(pz_text($data,'notes',5000)).',NOW())');
        $result=array('id'=>(int)$db->last_insert_id(MAIN_DB_PREFIX.'pz_visit')); break;
    case 'reschedule':
        $v=pz_visit($data['id']??0);
        if ($v['status']!=='planned') throw new InvalidArgumentException('Można edytować tylko planowaną wizytę.');
        $date=pz_booking_staff_assert($data['date']??'',(int)$v['rowid']);
        pz_query('UPDATE '.MAIN_DB_PREFIX.'pz_visit SET visit_date='.pz_q($date).',notes='.pz_q(pz_text($data,'notes',5000)).' WHERE rowid='.(int)$v['rowid']." AND status='planned'");
        $result=array('ok'=>true); break;
    case 'cancel':
        $v=pz_visit($data['id']??0);
        if ($v['status']!=='planned') throw new InvalidArgumentException('Można anulować tylko planowaną wizytę.');
        pz_query('UPDATE '.MAIN_DB_PREFIX."pz_visit SET status='cancelled' WHERE rowid=".(int)$v['rowid']." AND status='planned'"); $result=array('ok'=>true); break;
    case 'paid':
        $v=pz_visit($data['id']??0);
        if ($v['status']!=='completed') throw new InvalidArgumentException('Wizyta nie jest rozliczona.');
        $previous=pz_store('payment',(string)$v['rowid']);
        $paidDate=pz_date($data['date']??date('Y-m-d'));
        if ($paidDate>date('Y-m-d')) throw new InvalidArgumentException('Data otrzymania wpłaty nie może być w przyszłości.');
        if (empty($previous['received'])) pz_put('payment',(string)$v['rowid'],array_merge($previous??array(),array('received'=>true,'date'=>$paidDate)));
        $linked=pz_store('document','visit-'.(int)$v['rowid']);if($linked){$p=pz_store('payment',(string)$v['rowid']);$linked['received']=!empty($p['received']);$linked['paidDate']=$p['date']??null;$linked['version']++;pz_put('document',$linked['id'],$linked);}
        $result=array('ok'=>true); break;
    case 'expense':
        $amount=pz_cents($data['amount']??''); if ($amount<=0) throw new InvalidArgumentException('Kwota musi być dodatnia.');
        pz_query('INSERT INTO '.MAIN_DB_PREFIX.'pz_expense (expense_date,document_no,supplier,category,description,amount_gross,datec) VALUES ('.pz_q(pz_date($data['date']??'')).','.pz_q(pz_text($data,'document',128,true)).','.pz_q(pz_text($data,'supplier',255,true)).','.pz_q(pz_text($data,'category',128,true)).','.pz_q(pz_text($data,'description',5000)).','.($amount/100).',NOW())');
        $id=(int)$db->last_insert_id(MAIN_DB_PREFIX.'pz_expense');pz_put('expense',(string)$id,array('id'=>$id));$supplierId=(int)($data['clientId']??0);if($supplierId)pz_client($supplierId);pz_put('expense_meta',(string)$id,array('clientId'=>$supplierId,'note'=>pz_text($data,'note',5000)));$result=array('id'=>$id);break;
    case 'settings':
        if (!pz_can_manage()) throw new InvalidArgumentException('Brak uprawnień do ustawień salonu.');
        $cfg=pz_config(); foreach(array('salon','address','phone') as $k) $cfg[$k]=pz_text($data,$k,255,$k==='salon');
        $cfg['quarterLimit']=trim((string)($data['quarterLimit']??''));
        if ($cfg['quarterLimit']!=='') {$cfg['quarterLimit']=pz_cents($cfg['quarterLimit']);if($cfg['quarterLimit']===0)$cfg['quarterLimit']='';}
        $prices=$data['prices']??array();
        foreach($cfg['services'] as &$s) { $s['price']=pz_cents($prices[$s['id']]??'');if($s['price']<=0)throw new InvalidArgumentException('Ceny muszą być dodatnie.'); } unset($s);
        pz_put('config','main',$cfg);$result=array('ok'=>true);break;
    default: throw new InvalidArgumentException('Nieznana operacja.');
    }
    pz_mail_capture($action,$data,$result);
    if ($db->commit()<=0) throw new RuntimeException('Nie udało się zatwierdzić zapisu.');
    pz_reply($result);
} catch (Throwable $e) {
    if ($write) $db->rollback();
    dol_syslog('PuchatyZakatek API: '.$e->getMessage(),LOG_ERR);
    pz_reply(array('error'=>$e instanceof InvalidArgumentException ? $e->getMessage() : 'Operacja nie powiodła się. Dane nie zostały zatwierdzone. Odśwież stronę lub sprawdź dziennik Dolibarra.'),$e instanceof InvalidArgumentException?400:500);
}
