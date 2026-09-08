<?php
// Monetary values: integer grosze; quantities: integer thousandths.
function pz_today() { return (new DateTimeImmutable('now',new DateTimeZone('Europe/Warsaw')))->format('Y-m-d'); }
function pz_amount_words($cents) {
    $one=array('zero','jeden','dwa','trzy','cztery','pięć','sześć','siedem','osiem','dziewięć');
    $teen=array('dziesięć','jedenaście','dwanaście','trzynaście','czternaście','piętnaście','szesnaście','siedemnaście','osiemnaście','dziewiętnaście');
    $ten=array('','','dwadzieścia','trzydzieści','czterdzieści','pięćdziesiąt','sześćdziesiąt','siedemdziesiąt','osiemdziesiąt','dziewięćdziesiąt');
    $hundred=array('','sto','dwieście','trzysta','czterysta','pięćset','sześćset','siedemset','osiemset','dziewięćset');
    $triple=function($n)use($one,$teen,$ten,$hundred){$w=array();if($n>=100)$w[]=$hundred[intdiv($n,100)];$n%=100;if($n>=10&&$n<20)$w[]=$teen[$n-10];else{if($n>=20)$w[]=$ten[intdiv($n,10)];if($n%10)$w[]=$one[$n%10];}return implode(' ',$w);};
    $ending=function($n,$forms){return $n===1?$forms[0]:($n%10>=2&&$n%10<=4&&!($n%100>=12&&$n%100<=14)?$forms[1]:$forms[2]);};
    $n=intdiv((int)$cents,100);$words=array();foreach(array(1000000=>array('milion','miliony','milionów'),1000=>array('tysiąc','tysiące','tysięcy')) as $size=>$forms){$q=intdiv($n,$size);if($q){$words[]=($q===1&&$size===1000?'':$triple($q).' ').$ending($q,$forms);$n%=$size;}}
    if($n||!$words)$words[]=$n?$triple($n):'zero';return implode(' ',$words).' '.$ending(intdiv((int)$cents,100),array('złoty','złote','złotych')).' '.str_pad((string)($cents%100),2,'0',STR_PAD_LEFT).'/100';
}
function pz_commerce_lock() { pz_query('SELECT rowid FROM '.MAIN_DB_PREFIX."pz_store WHERE entity=".pz_entity()." AND kind='config' AND object_key='main' FOR UPDATE"); }
function pz_records($kind) {
    $rows=pz_rows('SELECT object_key,payload FROM '.MAIN_DB_PREFIX.'pz_store WHERE entity='.pz_entity().' AND kind='.pz_q($kind).' ORDER BY rowid');
    $out=array(); foreach($rows as $row) $out[$row['object_key']]=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR); return $out;
}
function pz_key($key) { if(!is_string($key)||!preg_match('/^[a-zA-Z0-9_-]{1,64}$/D',$key))throw new InvalidArgumentException('Nieprawidłowy identyfikator.');return $key; }
function pz_quantity($value,$positive=false) {
    $value=str_replace(',','.',trim((string)$value));
    if(!preg_match('/^\d{1,6}(?:\.\d{1,3})?$/D',$value))throw new InvalidArgumentException('Ilość musi być liczbą z najwyżej trzema miejscami po przecinku.');
    $n=(int)round((float)$value*1000);if($positive&&$n<=0)throw new InvalidArgumentException('Ilość musi być dodatnia.');return $n;
}
function pz_units() { return pz_config()['units']??array(array('id'=>'szt','name'=>'sztuka','short'=>'szt.'),array('id'=>'usl','name'=>'usługa','short'=>'usł.'),array('id'=>'kg','name'=>'kilogram','short'=>'kg'),array('id'=>'l','name'=>'litr','short'=>'l')); }
function pz_audit($kind,$id,$before,$after,$reason='') { global $user;pz_put('audit',bin2hex(random_bytes(16)),array('kind'=>$kind,'id'=>$id,'before'=>$before,'after'=>$after,'reason'=>$reason,'userId'=>(int)$user->id,'date'=>date('c'))); }
function pz_catalog_save($input) {
    if(!pz_can_manage())throw new InvalidArgumentException('Brak uprawnień do cennika.');
    $cfg=pz_config();$id=pz_key($input['id']??'');$existing=null;$index=null;
    foreach($cfg['services'] as $i=>$s)if($s['id']===$id){$existing=$s;$index=$i;}
    $kind=pz_text($input,'kind',16,true);if(!in_array($kind,array('service','product'),true))throw new InvalidArgumentException('Nieprawidłowy typ pozycji.');
    if($existing && ($existing['kind']??'service')!==$kind)throw new InvalidArgumentException('Typ istniejącej pozycji jest stały. Dodaj nową pozycję.');
    $unit=pz_key($input['unit']??'usl');if(!in_array($unit,array_column(pz_units(),'id'),true))throw new InvalidArgumentException('Wybierz jednostkę.');
    $price=pz_cents($input['price']??'');if($price<=0)throw new InvalidArgumentException('Cena musi być dodatnia.');
    $stock=$kind==='product'?pz_quantity($input['stock']??0):0;
    $value=array('id'=>$id,'name'=>pz_text($input,'name',255,true),'price'=>$price,'kind'=>$kind,'unit'=>$unit,'ean'=>pz_text($input,'ean',32),'stock'=>$stock,'addon'=>!empty($input['addon']),'active'=>!isset($input['active'])||!empty($input['active']));
    if($index===null)$cfg['services'][]=$value;else $cfg['services'][$index]=$value;
    if($kind==='product'&&$stock!==($existing['stock']??0))pz_audit('stock',$id,$existing['stock']??0,$stock,pz_text($input,'reason',500,true));
    pz_audit('catalog',$id,$existing,$value);pz_put('config','main',$cfg);return array('id'=>$id);
}
function pz_unit_save($input) {
    if(!pz_can_manage())throw new InvalidArgumentException('Brak uprawnień.');
    $cfg=pz_config();$units=pz_units();$id=pz_key($input['id']??'');$delete=!empty($input['delete']);
    if($delete)foreach($cfg['services'] as $s)if(($s['unit']??'usl')===$id)throw new InvalidArgumentException('Jednostka jest używana w katalogu.');
    $units=array_values(array_filter($units,function($u)use($id){return $u['id']!==$id;}));
    if(!$delete)$units[]=array('id'=>$id,'short'=>pz_text($input,'short',20,true),'name'=>pz_text($input,'name',128,true));
    $cfg['units']=$units;pz_put('config','main',$cfg);return array('ok'=>true);
}
function pz_stock_apply($key,$lines,$direction=-1) {
    if(pz_store('stock_apply',$key)!==null)return;
    $cfg=pz_config();$deltas=array();
    foreach($lines as $line){$id=$line['productId'];$deltas[$id]=($deltas[$id]??0)+$direction*$line['qty'];}
    foreach($cfg['services'] as &$s)if(isset($deltas[$s['id']])&&($s['kind']??'service')==='product'){
        $next=($s['stock']??0)+$deltas[$s['id']];if($next<0)throw new InvalidArgumentException('Brak wystarczającej ilości towaru: '.$s['name']);
        pz_audit('stock',$s['id'],$s['stock']??0,$next,$key);$s['stock']=$next;
    }unset($s);
    pz_put('config','main',$cfg);pz_put('stock_apply',$key,array('deltas'=>$deltas,'date'=>date('c')));
}
function pz_document_access($doc) {
    if(!$doc)throw new InvalidArgumentException('Dokument nie istnieje.');
    $r=pz_rows('SELECT s.rowid FROM '.MAIN_DB_PREFIX.'societe s WHERE s.rowid='.(int)$doc['clientId'].' AND '.pz_scope());
    if(!$r)throw new InvalidArgumentException('Brak dostępu do dokumentu.');return $doc;
}
function pz_document_totals($items) {
    if(!is_array($items)||count($items)<1||count($items)>100)throw new InvalidArgumentException('Dokument wymaga od 1 do 100 pozycji.');
    $cfg=pz_config();$catalog=array_column($cfg['services'],null,'id');$units=array_column(pz_units(),null,'id');$lines=array();$total=0;$net=0;
    foreach($items as $input){
        $id=pz_key($input['productId']??'');$product=$catalog[$id]??null;
        if(!$product||isset($product['active'])&&!$product['active'])throw new InvalidArgumentException('Pozycja katalogu jest niedostępna.');
        $qty=pz_quantity($input['quantity']??'',true);$price=pz_cents($input['price']??'');
        $rate=pz_text($input,'vat',4,true);if(!in_array($rate,array('zw','0','5','8','23'),true))throw new InvalidArgumentException('Nieprawidłowa stawka VAT.');
        $gross=(int)round($qty*$price/1000);$lineNet=$rate==='zw'?$gross:(int)round($gross*100/(100+(int)$rate));
        $total+=$gross;$net+=$lineNet;if($total>999999999)throw new InvalidArgumentException('Przekroczono maksymalną wartość dokumentu.');
        $lines[]=array('productId'=>$id,'name'=>$product['name'],'qty'=>$qty,'unit'=>$units[$product['unit']??'usl']['short']??'szt.','price'=>$price,'vat'=>$rate,'net'=>$lineNet,'tax'=>$gross-$lineNet,'total'=>$gross);
    }
    return array('lines'=>$lines,'total'=>$total,'net'=>$net,'tax'=>$total-$net);
}
function pz_document_save($input) {
    $id=pz_key($input['id']??'');$old=pz_store('document',$id);if($old)pz_document_access($old);
    if(strncmp($id,'visit-',6)===0)throw new InvalidArgumentException('Ten identyfikator jest zarezerwowany dla rachunku z wizyty.');
    if($old&&$old['state']!=='draft')throw new InvalidArgumentException('Wystawiony dokument jest zamknięty. Możesz go anulować z podaniem powodu.');
    if($old&&(int)($input['version']??0)!==$old['version'])throw new InvalidArgumentException('Dokument zmienił się w innej karcie. Odśwież dane.');
    $clientId=pz_client($input['clientId']??0);$type=pz_text($input,'type',16,true);
    if(!in_array($type,array('rachunek','faktura','potwierdzenie'),true))throw new InvalidArgumentException('Nieprawidłowy rodzaj dokumentu.');
    $issue=pz_date($input['issueDate']??'');$sale=pz_date($input['saleDate']??'');$due=pz_date($input['dueDate']??'');
    if($due<$issue)throw new InvalidArgumentException('Termin płatności nie może poprzedzać wystawienia.');
    $method=pz_text($input,'payment',32,true);if(!in_array($method,array('Gotówka','Karta','BLIK','Przelew'),true))throw new InvalidArgumentException('Nieprawidłowa forma płatności.');
    if(in_array($method,array('Gotówka','Karta'),true))$due=$issue;
    $totals=pz_document_totals($input['items']??array());
    $doc=array_merge($totals,array('id'=>$id,'state'=>'draft','number'=>'Szkic','clientId'=>$clientId,'type'=>$type,'issueDate'=>$issue,'saleDate'=>$sale,'dueDate'=>$due,'payment'=>$method,'notes'=>pz_text($input,'notes',3000),'version'=>($old['version']??0)+1,'visitId'=>0,'received'=>false));
    pz_put('document',$id,$doc);pz_audit('document',$id,$old,$doc);return array('id'=>$id);
}
function pz_document_issue($input) {
    $id=pz_key($input['id']??'');$doc=pz_document_access(pz_store('document',$id));
    if($doc['state']==='issued')return array('id'=>$id); // Retry is idempotent.
    if($doc['state']!=='draft')throw new InvalidArgumentException('Dokument jest zamknięty.');
    if($doc['version']!==(int)($input['version']??0))throw new InvalidArgumentException('Odśwież zmieniony dokument.');
    if($doc['issueDate']>pz_today()||$doc['saleDate']>pz_today())throw new InvalidArgumentException('Nie wystawiaj sprzedaży z przyszłą datą. Zapisz szkic.');
    $client=pz_rows('SELECT s.rowid,s.nom,s.address,s.zip,s.town,s.siren,s.email FROM '.MAIN_DB_PREFIX.'societe s WHERE s.rowid='.(int)$doc['clientId'].' AND '.pz_scope());
    if(!$client)throw new InvalidArgumentException('Brak nabywcy.');
    $cfg=pz_config();$seller=$cfg['seller']??array('name'=>$cfg['salon'],'address'=>$cfg['address'],'nip'=>'','bank'=>'','account'=>'','exemption'=>'');
    if(empty($seller['name'])||empty($seller['address']))throw new InvalidArgumentException('Uzupełnij dane sprzedawcy.');
    if(($seller['taxProfile']??'')!=='ndg_consumer_art113')throw new InvalidArgumentException('W NDG i raporty > Dane sprzedawcy potwierdź profil sprzedaży zwolnionej z VAT (art. 113) oraz wpisz swoje imię, nazwisko i pełny adres.');
    if($doc['type']!=='faktura')throw new InvalidArgumentException('W tym profilu wystaw fakturę. Zmień rodzaj dokumentu w szkicu; potwierdzenie 80 mm jest dodatkiem do faktury.');
    $meta=pz_store('client_meta',(string)$doc['clientId'])??array();
    if(($meta['clientKind']??'person')!=='person'||!empty($client[0]['siren']))throw new InvalidArgumentException('Ten profil obsługuje faktury dla konsumentów. Nie wystawiaj w nim faktury dla firmy.');
    if(empty(trim($client[0]['nom']??''))||empty(trim($client[0]['address']??''))||empty(trim($client[0]['town']??'')))throw new InvalidArgumentException('Uzupełnij imię i nazwisko oraz pełny adres nabywcy (adres i miejscowość) w edycji klienta, a następnie ponów zapis tej samej wizyty.');
    foreach($doc['lines'] as $line)if($line['vat']!=='zw')throw new InvalidArgumentException('Profil zwolnienia z VAT wymaga oznaczenia zw na każdej pozycji. Edytuj szkic.');
    if(empty($doc['visitId'])){ $catalog=array_column($cfg['services'],null,'id');foreach($doc['lines'] as $line){$p=$catalog[$line['productId']]??null;if(!$p||isset($p['active'])&&!$p['active'])throw new InvalidArgumentException('Pozycja szkicu jest już niedostępna. Edytuj szkic.');}}
    $year=substr($doc['issueDate'],0,4);$sequence=pz_store('doc_sequence',$year)??array('next'=>1);$n=$sequence['next'];pz_put('doc_sequence',$year,array('next'=>$n+1));
    if(empty($doc['visitId']))pz_stock_apply('document-'.$id,$doc['lines']);
    $before=$doc;$doc['number']='PZ/'.$year.'/'.str_pad((string)$n,5,'0',STR_PAD_LEFT);$doc['state']='issued';$doc['seller']=$seller;$doc['buyer']=$client[0];$doc['version']++;
    pz_put('document',$id,$doc);pz_audit('document',$id,$before,$doc);return array('id'=>$id);
}
function pz_document_action($input,$action) {
    $id=pz_key($input['id']??'');$doc=pz_document_access(pz_store('document',$id));$before=$doc;
    if($action==='paid'){
        if($doc['state']!=='issued')throw new InvalidArgumentException('Najpierw wystaw dokument.');
        $date=pz_date($input['date']??pz_today());if($date>pz_today()||$date<$doc['saleDate'])throw new InvalidArgumentException('Nieprawidłowa data wpłaty.');
        if($doc['received'])return array('ok'=>true);$doc['received']=true;$doc['paidDate']=$date;
        if(!empty($doc['visitId'])){pz_visit($doc['visitId']);$p=pz_store('payment',(string)$doc['visitId'])??array();if(empty($p['received']))pz_put('payment',(string)$doc['visitId'],array_merge($p,array('received'=>true,'date'=>$date)));else $doc['paidDate']=$p['date'];}
    }else{
        if(!empty($doc['visitId']))throw new InvalidArgumentException('Dokument jest powiązany z rozliczoną wizytą. Nie można anulować go niezależnie od sprzedaży.');
        if($doc['state']==='void')return array('ok'=>true);
        if(!empty($doc['received']))throw new InvalidArgumentException('Opłacony dokument wymaga korekty i rozliczenia zwrotu; nie można go anulować.');
        $doc['reason']=pz_text($input,'reason',1000,true);
        if($doc['state']==='issued'&&empty($doc['visitId']))pz_stock_apply('void-'.$id,$doc['lines'],1);
        $doc['state']='void';$doc['voidDate']=pz_today();
    }
    $doc['version']++;pz_put('document',$id,$doc);pz_audit('document',$id,$before,$doc,$doc['reason']??'');return array('ok'=>true);
}
function pz_document_from_visit($input) {
    $v=pz_visit((int)($input['id']??0));if($v['status']!=='completed')throw new InvalidArgumentException('Najpierw rozlicz wizytę.');
    $id='visit-'.(int)$v['rowid'];$old=pz_store('document',$id);if($old){pz_document_access($old);if((int)($old['visitId']??0)!==(int)$v['rowid'])throw new InvalidArgumentException('Konflikt powiązania dokumentu z wizytą.');return array('id'=>$id);}
    $items=pz_rows('SELECT service_name,qty,unit_price,total_price FROM '.MAIN_DB_PREFIX.'pz_visit_line WHERE fk_visit='.(int)$v['rowid'].' ORDER BY rowid');$lines=array();
    $snapshot=pz_store('visit_items',(string)$v['rowid'])??array();
    foreach($items as $i=>$line){$total=pz_cents($line['total_price']);$lines[]=array('productId'=>'visit','name'=>$line['service_name'],'qty'=>(int)round($line['qty']*1000),'unit'=>$snapshot[$i]['unit']??'usł.','price'=>pz_cents($line['unit_price']),'vat'=>'zw','net'=>$total,'tax'=>0,'total'=>$total);}
    $payment=pz_store('payment',(string)$v['rowid'])??array();$date=$payment['completedDate']??substr($v['visit_date'],0,10);$total=pz_cents($v['amount_total']);
    $doc=array('id'=>$id,'state'=>'draft','number'=>'Szkic','clientId'=>(int)$v['fk_soc'],'type'=>'faktura','issueDate'=>pz_today(),'saleDate'=>$date,'dueDate'=>pz_today(),'payment'=>$v['payment_type'],'notes'=>$v['notes'],'version'=>1,'visitId'=>(int)$v['rowid'],'received'=>!empty($payment['received']),'paidDate'=>$payment['date']??null,'lines'=>$lines,'total'=>$total,'net'=>$total,'tax'=>0);
    pz_put('document',$id,$doc);return pz_document_issue(array('id'=>$id,'version'=>1));
}
function pz_commerce_data() {
    $all=pz_data();$clientIds=array_map('intval',array_merge(array_column($all['clients'],'id'),array_column($all['archivedClients']??array(),'id')));
    $docs=array_values(array_filter(pz_records('document'),function($d)use($clientIds){return in_array((int)$d['clientId'],$clientIds,true);}));
    return array('documents'=>$docs,'units'=>pz_units(),'catalog'=>pz_config()['services'],'seller'=>pz_config()['seller']??array(),'limits'=>pz_config()['limits']??array());
}
function pz_business_settings($input) {
    if(!pz_can_manage())throw new InvalidArgumentException('Brak uprawnień.');$cfg=pz_config();
    $logo=$cfg['seller']['logo']??null;
    if(array_key_exists('logo',$input)){
        $logo=$input['logo'];
        if($logo!==null){
            if(!is_string($logo)||strlen($logo)>360000||!preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$#D',$logo,$match))throw new InvalidArgumentException('Logo musi być plikiem JPG do 256 KB.');
            $bytes=base64_decode($match[1],true);$info=$bytes!==false?@getimagesizefromstring($bytes):false;
            if(!$info||strlen($bytes)>262144||$info[2]!==IMAGETYPE_JPEG||$info[0]>2000||$info[1]>2000)throw new InvalidArgumentException('Logo JPG: maksymalnie 256 KB i 2000 × 2000 pikseli.');
        }
    }
    if(($input['taxProfile']??'')!=='ndg_consumer_art113')throw new InvalidArgumentException('Potwierdź, że korzystasz ze zwolnienia z VAT z art. 113 i wystawiasz faktury konsumentom.');
    $cfg['seller']=array('taxProfile'=>'ndg_consumer_art113');foreach(array('name','address','nip','bank','account','exemption') as $k)$cfg['seller'][$k]=pz_text($input,$k,500,$k==='name'||$k==='address');
    $cfg['seller']['logo']=$logo;
    if($cfg['seller']['nip']!==''){
        $nip=preg_replace('/[ -]/','',$cfg['seller']['nip']);
        if(!preg_match('/^[0-9]{10}$/D',$nip))throw new InvalidArgumentException('NIP powinien mieć 10 cyfr. Nie wpisuj PESEL; jeśli nie masz NIP, pozostaw pole puste.');
        $sum=0;foreach(array(6,5,7,2,3,4,5,6,7) as $i=>$weight)$sum+=(int)$nip[$i]*$weight;
        if($sum%11!==(int)$nip[9])throw new InvalidArgumentException('Nieprawidłowa suma kontrolna NIP.');
        $cfg['seller']['nip']=$nip;
    }
    pz_put('config','main',$cfg);return array('ok'=>true);
}
function pz_limits_save($input) {
    if(!pz_can_manage())throw new InvalidArgumentException('Brak uprawnień.');$year=(int)($input['year']??0);if($year<2020||$year>2100)throw new InvalidArgumentException('Nieprawidłowy rok.');
    $months=$input['months']??array();if(count($months)!==12)throw new InvalidArgumentException('Podaj 12 limitów miesięcznych.');
    $cfg=pz_config();$cfg['limits'][(string)$year]=array_map(function($value){if($value===null||trim((string)$value)==='')return null;$amount=pz_cents($value);return $amount>0?$amount:null;},$months);pz_put('config','main',$cfg);return array('ok'=>true);
}
function pz_expense_change($input,$remove=false) {
    $id=(int)($input['id']??0);$found=pz_rows('SELECT e.* FROM '.MAIN_DB_PREFIX.'pz_expense e JOIN '.MAIN_DB_PREFIX."pz_store m ON m.kind='expense' AND m.object_key=CAST(e.rowid AS CHAR) AND m.entity=".pz_entity().' WHERE e.rowid='.$id.' FOR UPDATE');
    if(!$found)throw new InvalidArgumentException('Nie znaleziono kosztu.');$old=$found[0];
    if($remove){$reason=pz_text($input,'reason',1000,true);pz_put('expense_removed',(string)$id,array('reason'=>$reason,'date'=>date('c')));pz_audit('expense',$id,$old,null,$reason);}
    else{
        if(pz_store('expense_removed',(string)$id)!==null)throw new InvalidArgumentException('Koszt usunięty.');
        $amount=pz_cents($input['amount']??'');if($amount<=0)throw new InvalidArgumentException('Kwota musi być dodatnia.');
        $supplier=pz_text($input,'supplier',255,true);$client=(int)($input['clientId']??0);if($client)pz_client($client);
        pz_query('UPDATE '.MAIN_DB_PREFIX.'pz_expense SET expense_date='.pz_q(pz_date($input['date']??'')).',document_no='.pz_q(pz_text($input,'document',128,true)).',supplier='.pz_q($supplier).',description='.pz_q(pz_text($input,'description',5000)).',category='.pz_q(pz_text($input,'category',128,true)).',amount_gross='.($amount/100).' WHERE rowid='.$id);
        pz_put('expense_meta',(string)$id,array('clientId'=>$client,'note'=>pz_text($input,'note',5000)));pz_audit('expense',$id,$old,$input);
    }return array('ok'=>true);
}
