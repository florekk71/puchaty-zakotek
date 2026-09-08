<?php
// NDG rules verified 2026-09-08. Amounts are integer grosze.
function pz_ndg_admin() { global $user; if (!pz_can_manage()) throw new InvalidArgumentException('Ewidencja NDG wymaga uprawnień do pełnej obsługi salonu.'); }
function pz_ndg_objects($kind) {
    $rows=pz_rows('SELECT object_key,payload FROM '.MAIN_DB_PREFIX.'pz_store WHERE entity='.pz_entity().' AND kind='.pz_q($kind).' ORDER BY rowid');
    $out=array(); foreach($rows as $r)$out[$r['object_key']]=json_decode($r['payload'],true,512,JSON_THROW_ON_ERROR);return $out;
}
function pz_ndg_date($value) { $d=pz_date($value);if($d>pz_today())throw new InvalidArgumentException('Wpis ewidencji nie może być w przyszłości. Do planowania służy terminarz.');return $d; }
function pz_ndg_entry($data) {
    global $user;
    pz_ndg_admin();$key=pz_text($data,'requestKey',64,true);
    if(!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$key))throw new InvalidArgumentException('Nieprawidłowy identyfikator wpisu.');
    $kind=pz_text($data,'kind',16,true);if(!in_array($kind,array('due','cash','cost'),true))throw new InvalidArgumentException('Nieprawidłowy rodzaj wpisu.');
    $amount=pz_cents($data['amount']??'');if($amount<=0)throw new InvalidArgumentException('Kwota musi być dodatnia.');
    $direction=pz_text($data,'direction',8,true);if(!in_array($direction,array('plus','minus'),true))throw new InvalidArgumentException('Nieprawidłowy znak wpisu.');
    $entry=array('kind'=>$kind,'date'=>pz_ndg_date($data['date']??''),'amount'=>$direction==='minus'?-$amount:$amount,'reference'=>pz_text($data,'reference',255,true),'description'=>pz_text($data,'description',1000,true));
    $old=pz_store('ndg_entry',$key);
    if($old){foreach($entry as $k=>$v)if(($old[$k]??null)!==$v)throw new InvalidArgumentException('Identyfikator należy do innego wpisu. Odśwież formularz.');return array('ok'=>true);}
    $entry['userId']=(int)$user->id;$entry['createdAt']=date('c');
    pz_query('INSERT INTO '.MAIN_DB_PREFIX.'pz_store (entity,kind,object_key,payload,datec) VALUES ('.pz_entity().",'ndg_entry',".pz_q($key).','.pz_q(json_encode($entry,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)).',NOW())');
    return array('ok'=>true);
}
function pz_ndg_cost($data) {
    global $user;
    pz_ndg_admin();$id=(int)($data['id']??0);
    $rows=pz_rows('SELECT e.* FROM '.MAIN_DB_PREFIX.'pz_expense e JOIN '.MAIN_DB_PREFIX."pz_store m ON m.kind='expense' AND m.object_key=CAST(e.rowid AS CHAR) AND m.entity=".pz_entity().' WHERE e.rowid='.$id);
    if(!$rows)throw new InvalidArgumentException('Brak dostępu do kosztu.');
    $status=pz_text($data,'status',16,true);if(!in_array($status,array('eligible','excluded'),true))throw new InvalidArgumentException('Nieprawidłowa kwalifikacja.');
    $value=array('status'=>$status,'paidDate'=>$status==='eligible'?pz_ndg_date($data['paidDate']??''):null,'reason'=>pz_text($data,'reason',1000,true),'userId'=>(int)$user->id,'updatedAt'=>date('c'));
    pz_put('ndg_audit',bin2hex(random_bytes(16)),array('expenseId'=>$id,'before'=>pz_store('ndg_cost',(string)$id),'after'=>$value));pz_put('ndg_cost',(string)$id,$value);return array('ok'=>true);
}
function pz_ndg_calculate($entries,$year) {
    // No automatic carry-forward of a statutory limit to unverified years.
    $limit=$year===2026?1081350:null;$quarters=array();$daily=array();$cash=0;$cost=0;
    for($q=1;$q<=4;$q++)$quarters[$q]=array('quarter'=>$q,'due'=>0,'limit'=>$limit,'remaining'=>$limit,'percent'=>null,'breachDate'=>null);
    usort($entries,function($a,$b){return strcmp($a['date'],$b['date'])?:strcmp($a['id'],$b['id']);});
    foreach($entries as $e){if((int)substr($e['date'],0,4)!==$year)continue;
        if($e['kind']==='cash')$cash+=$e['amount'];if($e['kind']==='cost')$cost+=$e['amount'];
        if($e['kind']==='due'){$daily[$e['date']]=($daily[$e['date']]??0)+$e['amount'];}
    }
    ksort($daily);$ledger=array();$annual=0;
    foreach($daily as $date=>$amount){$q=(int)floor(((int)substr($date,5,2)-1)/3)+1;$quarters[$q]['due']+=$amount;$annual+=$amount;$ledger[]=array('date'=>$date,'amount'=>$amount,'quarterTotal'=>$quarters[$q]['due'],'yearTotal'=>$annual);if($limit!==null&&$quarters[$q]['due']>$limit&&!$quarters[$q]['breachDate'])$quarters[$q]['breachDate']=$date;}
    foreach($quarters as &$q){if($limit!==null){$q['remaining']=$limit-$q['due'];$q['percent']=round($q['due']*100/$limit,2);}}unset($q);
    return array('year'=>$year,'quarters'=>array_values($quarters),'daily'=>$ledger,'cash'=>$cash,'cost'=>$cost,'income'=>$cash-$cost,'due'=>$annual,'ruleVerified'=>$limit!==null);
}
function pz_ndg_data($year) {
    pz_ndg_admin();if($year<2020||$year>2100)throw new InvalidArgumentException('Nieprawidłowy rok.');
    $all=pz_data();$entries=array();$issues=array();$classification=pz_ndg_objects('ndg_cost');$costs=array();
    foreach($all['visits'] as $v){if($v['status']!=='completed')continue;$id=(int)$v['rowid'];$state=$v['paymentState'];$date=$state['completedDate']??null;
        if(!$date){$date=substr($v['visit_date'],0,10);$issues[]='Wizyta #'.$id.': brak potwierdzonej daty wykonania, użyto daty wizyty.';}
        $base=array('id'=>'visit-'.$id,'date'=>$date,'amount'=>pz_cents((string)$v['amount_total']),'reference'=>'Wizyta #'.$id,'description'=>$v['client'].' / '.$v['dog']);
        $entries[]=array_merge($base,array('kind'=>'due'));
        if(!empty($state['received'])){if(empty($state['date']))$issues[]='Wizyta #'.$id.': wpłata bez daty — pominięta w PIT.';else $entries[]=array_merge($base,array('id'=>'payment-'.$id,'kind'=>'cash','date'=>$state['date']));}
    }
    foreach($all['expenses'] as $e){$id=(int)$e['rowid'];$c=$classification[$id]??null;$e['qualification']=$c;$costs[]=$e;
        if(!$c){$issues[]='Koszt #'.$id.': wymaga kwalifikacji i daty zapłaty; nie został odliczony.';continue;}
        if($c['status']==='eligible')$entries[]=array('id'=>'expense-'.$id,'kind'=>'cost','date'=>$c['paidDate'],'amount'=>pz_cents((string)$e['amount_gross']),'reference'=>$e['document_no'],'description'=>$e['supplier'].' / '.$e['description']);
    }
    foreach(pz_records('document') as $doc){
        if(!empty($doc['visitId']) || $doc['number']==='Szkic')continue;
        $base=array('id'=>'document-'.$doc['id'],'date'=>$doc['saleDate'],'amount'=>$doc['total'],'reference'=>$doc['number'],'description'=>$doc['buyer']['nom']??'');
        $entries[]=array_merge($base,array('kind'=>'due'));
        if($doc['state']==='void')$entries[]=array_merge($base,array('id'=>'void-'.$doc['id'],'kind'=>'due','amount'=>-$doc['total'],'date'=>$doc['voidDate']));
        if(!empty($doc['received']))$entries[]=array_merge($base,array('kind'=>'cash','date'=>$doc['paidDate']));
    }
    foreach(pz_ndg_objects('ndg_entry') as $key=>$entry)$entries[]=array_merge($entry,array('id'=>'entry-'.$key));
    $summary=pz_ndg_calculate($entries,$year);$breaches=array();
    foreach(array(2026) as $knownYear){$s=pz_ndg_calculate($entries,$knownYear);foreach($s['quarters'] as $q)if($q['breachDate']&&$q['breachDate']<=pz_today())$breaches[]=$q['breachDate'];}
    sort($breaches);$summary['firstBreach']=$breaches[0]??null;
    $summary['issues']=$issues;$summary['entries']=array_values(array_filter($entries,function($e)use($year){return (int)substr($e['date'],0,4)===$year;}));
    usort($summary['entries'],function($a,$b){return strcmp($a['date'],$b['date'])?:strcmp($a['id'],$b['id']);});
    $summary['costs']=$costs;$summary['verifiedOn']='2026-09-08';$summary['current']=pz_ndg_calculate($entries,(int)substr(pz_today(),0,4));
    return $summary;
}
