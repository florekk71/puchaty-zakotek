<?php
require_once __DIR__.'/access.lib.php';
// Shared persistence and authorization for the Puchaty Zakatek module.
function pz_query($sql) {
    global $db;
    $r = $db->query($sql);
    if (!$r) {
        dol_syslog('PuchatyZakatek: '.$db->lasterror(), LOG_ERR);
        throw new RuntimeException('Błąd bazy danych. Sprawdź dziennik Dolibarra.');
    }
    return $r;
}
function pz_rows($sql) {
    global $db;
    $r = pz_query($sql); $rows = array();
    while ($o = $db->fetch_object($r)) $rows[] = (array) $o;
    return $rows;
}
function pz_q($v) { global $db; return "'".$db->escape((string) $v)."'"; }
function pz_entity() { global $conf; return (int) $conf->entity; }
function pz_scope($alias = 's') {
    global $user;
    $sql = $alias.'.entity = '.pz_entity();
    if (!pz_can_manage() && !$user->hasRight('societe', 'client', 'voir')) {
        $sql .= ' AND EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'societe_commerciaux sc WHERE sc.fk_soc = '.$alias.'.rowid AND sc.fk_user = '.(int) $user->id.')';
    }
    return $sql;
}
function pz_client($id) {
    $r = pz_rows('SELECT s.rowid FROM '.MAIN_DB_PREFIX.'societe s WHERE s.rowid = '.(int) $id.' AND s.client IN (1,3) AND '.pz_scope().pz_active_client_sql().pz_write_lock());
    if (!$r) throw new InvalidArgumentException('Klient nie istnieje lub nie masz dostępu.');
    return (int) $id;
}
function pz_text($data, $key, $max = 255, $required = false) {
    $v = trim((string) ($data[$key] ?? ''));
    if (($required && $v === '') || mb_strlen($v) > $max) throw new InvalidArgumentException('Nieprawidłowe pole: '.$key);
    return $v;
}
function pz_date($value, $time = false) {
    $format = $time ? 'Y-m-d H:i:s' : 'Y-m-d';
    $value = str_replace('T', ' ', (string) $value);
    if ($time && strlen($value) === 16) $value .= ':00';
    $d = DateTimeImmutable::createFromFormat('!'.$format, $value);
    if (!$d || $d->format($format) !== $value) throw new InvalidArgumentException('Nieprawidłowa data.');
    return $value;
}
function pz_cents($v) {
    $v = str_replace(',', '.', trim((string) $v));
    if (!preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D', $v)) throw new InvalidArgumentException('Nieprawidłowa kwota.');
    return (int) round((float) $v * 100);
}
function pz_defaults() {
    return array(
        'salon' => 'Puchaty Zakątek', 'address' => '', 'phone' => '',
        'quarterLimit' => '',
        'services' => array(
            array('id'=>'full_s','name'=>'Pełna pielęgnacja — Mały pies do 10 kg','price'=>12000),
            array('id'=>'full_m','name'=>'Pełna pielęgnacja — Średni pies 10–20 kg','price'=>15000),
            array('id'=>'full_l','name'=>'Pełna pielęgnacja — Duży pies 20–35 kg','price'=>19000),
            array('id'=>'full_xl','name'=>'Pełna pielęgnacja — Bardzo duży pies 35+ kg','price'=>23000),
            array('id'=>'bath_s','name'=>'Kąpiel + suszenie — Mały pies do 10 kg','price'=>8000),
            array('id'=>'bath_m','name'=>'Kąpiel + suszenie — Średni pies 10–20 kg','price'=>11000),
            array('id'=>'bath_l','name'=>'Kąpiel + suszenie — Duży pies 20–35 kg','price'=>15000),
            array('id'=>'comb','name'=>'Rozczesywanie','price'=>6000),
            array('id'=>'nails','name'=>'Pazurki','price'=>2000,'addon'=>true),
            array('id'=>'ears','name'=>'Uszy','price'=>2000,'addon'=>true),
            array('id'=>'eyes','name'=>'Okolice oczu','price'=>2000,'addon'=>true),
            array('id'=>'paws','name'=>'Łapki / higiena','price'=>3000,'addon'=>true),
            array('id'=>'bath_extra','name'=>'Kąpiel mały pies','price'=>8000,'addon'=>true),
            array('id'=>'comb_extra','name'=>'Rozczesywanie','price'=>6000,'addon'=>true)
        )
    );
}
function pz_ready() {
    global $db;
    $r = $db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'pz_store LIMIT 1');
    return (bool) $r;
}
function pz_install() {
    global $db;
    // Additive only: preserve all existing salon and Dolibarr records.
    foreach (array('dog','visit','visit_line','expense') as $name) {
        $sql = file_get_contents(dirname(__DIR__).'/sql/llx_pz_'.$name.'.sql');
        $sql = str_replace('CREATE TABLE llx_', 'CREATE TABLE IF NOT EXISTS '.MAIN_DB_PREFIX, $sql);
        pz_query($sql);
    }
    pz_query('CREATE TABLE IF NOT EXISTS '.MAIN_DB_PREFIX."pz_store (
        rowid INTEGER AUTO_INCREMENT PRIMARY KEY, entity INTEGER NOT NULL,
        kind VARCHAR(32) NOT NULL, object_key VARCHAR(64) NOT NULL,
        payload LONGTEXT NOT NULL, datec DATETIME NOT NULL,
        UNIQUE KEY uk_pz_store (entity, kind, object_key)
    ) ENGINE=innodb");
    pz_query('INSERT IGNORE INTO '.MAIN_DB_PREFIX.'pz_store (entity,kind,object_key,payload,datec) VALUES ('.pz_entity().",'config','main',".pz_q(json_encode(pz_defaults(), JSON_UNESCAPED_UNICODE)).',NOW())');
}
function pz_store($kind, $key) {
    $r = pz_rows('SELECT payload FROM '.MAIN_DB_PREFIX.'pz_store WHERE entity='.pz_entity().' AND kind='.pz_q($kind).' AND object_key='.pz_q($key));
    return $r ? json_decode($r[0]['payload'], true, 512, JSON_THROW_ON_ERROR) : null;
}
function pz_put($kind, $key, $value) {
    $json = pz_q(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    pz_query('INSERT INTO '.MAIN_DB_PREFIX.'pz_store (entity,kind,object_key,payload,datec) VALUES ('.pz_entity().','.pz_q($kind).','.pz_q($key).','.$json.',NOW()) ON DUPLICATE KEY UPDATE payload=VALUES(payload)');
}
function pz_config() {
    $c = pz_store('config', 'main');
    if (!$c) throw new RuntimeException('Administrator musi przygotować bazę dla tej firmy.');
    return $c;
}
function pz_dog($id) {
    $r = pz_rows('SELECT d.* FROM '.MAIN_DB_PREFIX.'pz_dog d JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=d.fk_soc WHERE d.rowid='.(int) $id.' AND d.active=1 AND '.pz_scope().pz_active_client_sql().pz_write_lock());
    if (!$r) throw new InvalidArgumentException('Pies nie istnieje lub nie masz dostępu.');
    return $r[0];
}
function pz_save_dog($data) {
    global $db;
    $client = pz_client($data['clientId'] ?? 0);
    $id = (int) ($data['id'] ?? 0);
    if ($id) pz_dog($id);
    $name = pz_text($data, 'name', 128, true);
    $weight = trim((string) ($data['weight'] ?? ''));
    if ($weight !== '') { $weight = pz_cents($weight)/100; if ($weight <= 0 || $weight > 150) throw new InvalidArgumentException('Waga musi być większa od 0 i nie większa niż 150 kg.'); }
    $born = pz_text($data, 'birthdate', 10);
    if ($born !== '') { pz_date($born); if ($born > date('Y-m-d')) throw new InvalidArgumentException('Data urodzenia jest w przyszłości.'); }
    $values = array('fk_soc'=>$client,'name'=>pz_q($name),'breed'=>pz_q(pz_text($data,'breed',128)),
        'weight'=>$weight === '' ? 'NULL' : $weight,'birthdate'=>$born === '' ? 'NULL' : pz_q($born),
        'health_notes'=>pz_q(pz_text($data,'health_notes',5000)), 'grooming_notes'=>pz_q(pz_text($data,'grooming_notes',5000)),
        'behavior_notes'=>pz_q(pz_text($data,'behavior_notes',5000)), 'allergies'=>pz_q(pz_text($data,'allergies',5000)));
    if ($id) {
        $sets=array(); foreach ($values as $k=>$v) $sets[]=$k.'='.$v;
        pz_query('UPDATE '.MAIN_DB_PREFIX.'pz_dog SET '.implode(',',$sets).' WHERE rowid='.$id);
    } else {
        $values['datec']='NOW()';
        pz_query('INSERT INTO '.MAIN_DB_PREFIX.'pz_dog ('.implode(',',array_keys($values)).') VALUES ('.implode(',',$values).')');
        $id=(int)$db->last_insert_id(MAIN_DB_PREFIX.'pz_dog');
    }
    return array('id'=>$id);
}
function pz_visit($id) {
    $r=pz_rows('SELECT v.* FROM '.MAIN_DB_PREFIX.'pz_visit v JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=v.fk_soc WHERE v.rowid='.(int)$id.' AND '.pz_scope());
    if (!$r) throw new InvalidArgumentException('Brak dostępu do wizyty.');
    return $r[0];
}
function pz_sale($data) {
    global $db;
    $key=pz_text($data,'requestKey',64,true);
    if (!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$key)) throw new InvalidArgumentException('Nieprawidłowy identyfikator zapisu.');
    $old=pz_store('sale',$key);
    if (!empty($old['aborted'])) throw new InvalidArgumentException('Ta próba zapisu została zamknięta. Rozpocznij rozliczenie od nowa.');
    if ($old) { pz_visit($old['id']); return $old; }
    $dog=pz_dog($data['dogId']??0); $client=pz_client($data['clientId']??0);
    if ((int)$dog['fk_soc']!==$client) throw new InvalidArgumentException('Pies nie należy do wybranego klienta.');
    $payment=pz_text($data,'payment',32,true);
    if (!in_array($payment,array('Gotówka','Karta','BLIK','Przelew'),true)) throw new InvalidArgumentException('Nieprawidłowa metoda płatności.');
    $items=$data['items']??array();
    if (!is_array($items)||!count($items)||count($items)>100) throw new InvalidArgumentException('Koszyk musi zawierać od 1 do 100 pozycji.');
    $catalog=array_column(array_filter(pz_config()['services'],function($s){return !isset($s['active'])||$s['active'];}),null,'id'); $lines=array(); $total=0;
    foreach ($items as $item) {
        if (!is_array($item) || !isset($catalog[$item['id']??''])) throw new InvalidArgumentException('Usługa nie istnieje. Odśwież cennik.');
        $s=$catalog[$item['id']];
        if (!isset($item['price']) || (int)$item['price']!==$s['price']) throw new InvalidArgumentException('Cena zmieniła się. Odśwież stronę i wybierz usługi ponownie.');
        $lines[]=$s; $total+=$s['price'];
    }
    if ($total<=0 || $total>999999999) throw new InvalidArgumentException('Nieprawidłowa suma.');
    $planned=(int)($data['visitId']??0);
    // The surrounding transaction covers the idempotency reservation, visit and all lines.
    pz_query('INSERT INTO '.MAIN_DB_PREFIX.'pz_store (entity,kind,object_key,payload,datec) VALUES ('.pz_entity().",'sale',".pz_q($key).",'{}',NOW())");
    if ($planned) {
        $v=pz_visit($planned);
        if ($v['status']!=='planned' || (int)$v['fk_dog']!==(int)$dog['rowid']) throw new InvalidArgumentException('Ta wizyta nie jest dostępna do rozliczenia.');
        $updateResult=pz_query('UPDATE '.MAIN_DB_PREFIX."pz_visit SET status='completed',payment_type=".pz_q($payment).',amount_total='.($total/100)." WHERE rowid=$planned AND status='planned'");
        if ((int)$db->affected_rows($updateResult)!==1) throw new RuntimeException('Wizyta została już rozliczona.');
        $id=$planned;
    } else {
        require_once __DIR__.'/booking.lib.php';
        $visitDate=(new DateTimeImmutable('now',new DateTimeZone('Europe/Warsaw')))->format('Y-m-d H:i:s');
        pz_booking_assert($visitDate,0,false,array());
        pz_query('INSERT INTO '.MAIN_DB_PREFIX.'pz_visit (fk_soc,fk_dog,visit_date,status,payment_type,amount_total,notes,datec) VALUES ('.$client.','.(int)$dog['rowid'].','.pz_q($visitDate).",'completed',".pz_q($payment).','.($total/100).','.pz_q(pz_text($data,'notes',5000)).',NOW())');
        $id=(int)$db->last_insert_id(MAIN_DB_PREFIX.'pz_visit');
    }
    foreach ($lines as $s) pz_query('INSERT INTO '.MAIN_DB_PREFIX.'pz_visit_line (fk_visit,service_name,qty,unit_price,total_price) VALUES ('.$id.','.pz_q($s['name']).',1,'.($s['price']/100).','.($s['price']/100).')');
    $units=array_column(pz_units(),null,'id');
    pz_put('visit_items',(string)$id,array_map(function($s)use($units){return array('productId'=>$s['id'],'name'=>$s['name'],'unit'=>$units[$s['unit']??'usl']['short']??'usł.');},$lines));
    $result=array('id'=>$id,'total'=>$total);
    pz_put('sale',$key,$result);
    pz_put('payment',(string)$id,array('received'=>!empty($data['received']),'date'=>!empty($data['received'])?date('Y-m-d'):null,'completedDate'=>date('Y-m-d')));
    return $result;
}
function pz_data() {
    $p=MAIN_DB_PREFIX;
    $clients=pz_rows('SELECT s.rowid id,s.nom name,s.phone,s.email,s.town,s.address,s.zip,s.siren nip,s.name_alias alias,s.url www,s.note_private notes FROM '.$p.'societe s WHERE s.client IN (1,3) AND '.pz_scope().' ORDER BY s.nom');
    $dogs=pz_rows('SELECT d.*,d.rowid id,d.fk_soc clientId FROM '.$p.'pz_dog d JOIN '.$p.'societe s ON s.rowid=d.fk_soc WHERE d.active=1 AND '.pz_scope().' ORDER BY d.name');
    foreach($clients as &$client){$meta=pz_store('client_meta',(string)$client['id'])??array();$client['contact']=$meta['contact']??'';$client['clientKind']=$meta['clientKind']??(!empty($client['nip'])?'company':'person');}unset($client);
    $archiveRows=pz_rows('SELECT object_key FROM '.$p."pz_store WHERE kind='archived_client' AND entity=".pz_entity());
    $archivedIds=array_column($archiveRows,'object_key');
    $archivedClients=array_values(array_filter($clients,function($c)use($archivedIds){return in_array((string)$c['id'],$archivedIds,true);}));
    $clients=array_values(array_filter($clients,function($c)use($archivedIds){return !in_array((string)$c['id'],$archivedIds,true);}));
    $archivedDogs=pz_rows('SELECT d.*,d.rowid id,d.fk_soc clientId,s.nom clientName FROM '.$p.'pz_dog d JOIN '.$p.'societe s ON s.rowid=d.fk_soc WHERE d.active=0 AND '.pz_scope().' ORDER BY d.name');
    $dogs=array_values(array_filter($dogs,function($d)use($archivedIds){return !in_array((string)$d['clientId'],$archivedIds,true);}));
    $visits=pz_rows('SELECT v.*,s.nom client,d.name dog FROM '.$p.'pz_visit v JOIN '.$p.'societe s ON s.rowid=v.fk_soc JOIN '.$p.'pz_dog d ON d.rowid=v.fk_dog WHERE '.pz_scope().' ORDER BY v.visit_date DESC');
    $expenses=pz_rows('SELECT e.* FROM '.$p.'pz_expense e JOIN '.$p."pz_store m ON m.kind='expense' AND m.object_key=CAST(e.rowid AS CHAR) AND m.entity=".pz_entity().' ORDER BY e.expense_date DESC');
    $removed=pz_records('expense_removed');$expenses=array_values(array_filter($expenses,function($e)use($removed){return !isset($removed[$e['rowid']]);}));
    foreach($expenses as &$expense)$expense['meta']=pz_store('expense_meta',(string)$expense['rowid'])??array();unset($expense);
    $payments=pz_rows('SELECT object_key,payload FROM '.$p."pz_store WHERE kind='payment' AND entity=".pz_entity());
    $map=array();foreach($payments as $m)$map[$m['object_key']]=json_decode($m['payload'],true);
    foreach($visits as &$v)$v['paymentState']=$map[$v['rowid']]??array('received'=>false,'date'=>null);
    unset($v);
    return array('blocks'=>pz_booking_blocks(),'archivedClients'=>$archivedClients,'archivedDogs'=>$archivedDogs,'clients'=>$clients,'dogs'=>$dogs,'visits'=>$visits,'expenses'=>$expenses,'config'=>pz_config());
}

function pz_write_lock() { return ($_SERVER['REQUEST_METHOD']??'GET')==='POST' ? ' FOR UPDATE' : ''; }
function pz_active_client_sql() {
    return " AND NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."pz_store ac WHERE ac.entity=".pz_entity()." AND ac.kind='archived_client' AND ac.object_key=CAST(s.rowid AS CHAR))";
}
function pz_archive_card($kind,$id,$restore=false) {
    global $user;
    $id=(int)$id;
    if ($id<=0 || !in_array($kind,array('client','dog'),true)) throw new InvalidArgumentException('Nieprawidłowa kartoteka.');
    if ($kind==='client') {
        $found=pz_rows('SELECT s.rowid FROM '.MAIN_DB_PREFIX.'societe s WHERE s.rowid='.$id.' AND s.client IN (1,3) AND '.pz_scope().' FOR UPDATE');
    } else {
        $found=pz_rows('SELECT d.rowid,d.fk_soc FROM '.MAIN_DB_PREFIX.'pz_dog d JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=d.fk_soc WHERE d.rowid='.$id.' AND '.pz_scope().' FOR UPDATE');
    }
    if (!$found) throw new InvalidArgumentException('Brak dostępu do kartoteki.');
    if (!$restore) {
        $planned=pz_rows('SELECT rowid FROM '.MAIN_DB_PREFIX.'pz_visit WHERE '.($kind==='client'?'fk_soc':'fk_dog').'='.$id." AND status='planned' LIMIT 1");
        if ($planned) throw new InvalidArgumentException('Najpierw rozlicz lub odwołaj zaplanowane wizyty tej kartoteki.');
    }
    if ($kind==='client') {
        if ($restore) pz_query('DELETE FROM '.MAIN_DB_PREFIX."pz_store WHERE entity=".pz_entity()." AND kind='archived_client' AND object_key=".pz_q((string)$id));
        else pz_put('archived_client',(string)$id,array('userId'=>(int)$user->id,'date'=>date('c')));
    } else {
        if ($restore && pz_store('archived_client',(string)$found[0]['fk_soc'])!==null) throw new InvalidArgumentException('Najpierw przywróć właściciela psa z archiwum klientów.');
        pz_query('UPDATE '.MAIN_DB_PREFIX.'pz_dog SET active='.($restore?1:0).' WHERE rowid='.$id);
    }
    return array('ok'=>true,'archived'=>!$restore);
}
