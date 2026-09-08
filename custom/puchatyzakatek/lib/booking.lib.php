<?php
require_once __DIR__.'/reservation.lib.php';
require_once __DIR__.'/portal-config.lib.php';
function pz_booking_next($from='', $c=null, $now=null) {
    $c=$c??pz_portal_config();
    $zone=new DateTimeZone('Europe/Warsaw');
    $now=($now??new DateTimeImmutable('now',$zone))->setTimezone($zone);
    pz_booking_hours_validate($c);
    $today=$now->setTime(0,0);
    $day=$from===''?$today:DateTimeImmutable::createFromFormat('!Y-m-d',$from,$zone);
    if (!$day || ($from!=='' && $day->format('Y-m-d')!==$from)) throw new InvalidArgumentException('Nieprawidłowa data.');
    if ($day<$today) $day=$today;
    $last=$today->modify('+'.(int)($c['calendar']['days_ahead']??90).' days');
    if ($day>$last) return array('date'=>null,'message'=>'Wybrany dzień jest poza zakresem rezerwacji.');
    $rows=pz_booking_rows($day->modify('-1 day')->format('Y-m-d H:i:s'),$last->modify('+1 day')->format('Y-m-d H:i:s'));
    for (;$day<=$last;$day=$day->modify('+1 day')) {
        $window=pz_booking_window($day->format('Y-m-d'),$c);
        if (!$window) continue;
        for ($slot=$window[0];$slot->modify('+180 minutes')<=$window[1];$slot=$slot->modify('+180 minutes')) {
            if ($slot->getTimestamp()<$now->getTimestamp()+3600) continue;
            $date=$slot->format('Y-m-d H:i:s');
            if (!pz_booking_conflict($date,$rows)) return array('date'=>$date,'message'=>'Wybrano najbliższy wolny termin. Dostępność zostanie ponownie sprawdzona przy zapisie.');
        }
    }
    return array('date'=>null,'message'=>'Brak wolnych terminów w godzinach pracy w zakresie rezerwacji. Sprawdź grafik i konfigurację godzin pracy salonu.');
}
function pz_booking_window($day,$c) {
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$day,new DateTimeZone('Europe/Warsaw'));
    if(!$d||$d->format('Y-m-d')!==$day)throw new InvalidArgumentException('Nieprawidłowy dzień.');
    $closed=array_map('trim',explode(',',(string)($c['calendar']['closed_dates']??'')));
    $range=$c['calendar'][strtolower($d->format('l'))]??'';
    if(!$range||in_array($day,$closed,true))return null;
    pz_booking_hours_validate($c);
    [$start,$end]=explode('-',$range);
    return array(new DateTimeImmutable($day.' '.$start,new DateTimeZone('Europe/Warsaw')),new DateTimeImmutable($day.' '.$end,new DateTimeZone('Europe/Warsaw')));
}
function pz_booking_conflict($date,$rows,$exclude=0) {
    $start=new DateTimeImmutable($date,new DateTimeZone('Europe/Warsaw'));$end=$start->modify('+180 minutes');$count=0;
    foreach($rows as $v){
        if((int)$v['rowid']===$exclude||!in_array($v['status'],array('planned','completed'),true))continue;
        $other=new DateTimeImmutable($v['visit_date'],new DateTimeZone('Europe/Warsaw'));
        if($other->format('Y-m-d')===$start->format('Y-m-d'))$count++;
        if($other<$end&&$other->modify('+180 minutes')>$start)return 'Ten termin jest już zajęty. Każda wizyta trwa 3 godziny.';
    }
    return $count>=3?'W tym dniu przyjmujemy już 3 psy. Wybierz inny dzień.':null;
}
function pz_booking_rows($from,$to) {
    // No staff salesperson scope: capacity belongs to the whole salon/entity.
    $rows=pz_rows('SELECT v.rowid,v.visit_date,v.status FROM '.MAIN_DB_PREFIX.'pz_visit v JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid=v.fk_soc WHERE s.entity='.pz_entity()." AND v.status IN ('planned','completed') AND v.visit_date>=".pz_q($from).' AND v.visit_date<'.pz_q($to));
    foreach(pz_booking_blocks() as $block) if($block['date']>=$from && $block['date']<$to) {
        $rows[]=array('rowid'=>-1,'visit_date'=>$block['date'],'status'=>'planned');
    }
    return $rows;
}
function pz_booking_assert($date,$exclude=0,$checkLead=true,$c=null,$now=null) {
    $c=$c??pz_portal_config();$zone=new DateTimeZone('Europe/Warsaw');$now=$now??new DateTimeImmutable('now',$zone);
    $date=$checkLead?pz_reservation_date($date,$now):pz_date($date,true);
    $start=new DateTimeImmutable($date,$zone);$end=$start->modify('+180 minutes');
    if(!empty($c['calendar']['enforce_hours'])){
        $window=pz_booking_window($start->format('Y-m-d'),$c);
        if(!$window||$start<$window[0]||$end>$window[1])throw new InvalidArgumentException('Wizyta musi mieścić się w godzinach pracy salonu (3 godziny).');
        if($checkLead&&$start->format('Y-m-d')>$now->modify('+'.(int)($c['calendar']['days_ahead']??90).' days')->format('Y-m-d'))throw new InvalidArgumentException('Termin wykracza poza dostępny okres rezerwacji.');
    }
    // Caller must hold the same config/main row lock as staff API for the entire transaction.
    $rows=pz_booking_rows($start->modify('-1 day')->format('Y-m-d').' 00:00:00',$start->modify('+1 day')->format('Y-m-d').' 00:00:00');
    $error=pz_booking_conflict($date,$rows,$exclude);if($error)throw new InvalidArgumentException($error);
    return $date;
}
function pz_booking_calendar($from,$c,$now=null) {
    $zone=new DateTimeZone('Europe/Warsaw');$now=$now??new DateTimeImmutable('now',$zone);
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$from,$zone);
    if(!$d||$d->format('Y-m-d')!==$from||$from<$now->format('Y-m-d')||$from>$now->modify('+'.(int)($c['calendar']['days_ahead']??90).' days')->format('Y-m-d'))throw new InvalidArgumentException('Wybierz dzień w dostępnym okresie.');
    $rows=pz_booking_rows($d->modify('-1 day')->format('Y-m-d').' 00:00:00',$d->modify('+7 days')->format('Y-m-d').' 00:00:00');$days=array();
    for($i=0;$i<7;$i++){
        $day=$d->modify('+'.$i.' days')->format('Y-m-d');$window=pz_booking_window($day,$c);$slots=array();$busy=array();
        foreach($rows as $v)if(substr($v['visit_date'],0,10)===$day)$busy[]=array('start'=>substr($v['visit_date'],11,5),'end'=>(new DateTimeImmutable($v['visit_date'],$zone))->modify('+180 minutes')->format('H:i'));
        if($window)for($s=$window[0];$s->modify('+180 minutes')<=$window[1];$s=$s->modify('+180 minutes')){
            $inRange=$day<=$now->modify('+'.(int)($c['calendar']['days_ahead']??90).' days')->format('Y-m-d');
            $available=$inRange&&$s->getTimestamp()>=$now->getTimestamp()+3600&&!pz_booking_conflict($s->format('Y-m-d H:i:s'),$rows);
            $slots[]=array('date'=>$s->format('Y-m-d H:i:s'),'start'=>$s->format('H:i'),'end'=>$s->modify('+180 minutes')->format('H:i'),'available'=>(bool)$available);
        }
        // Only times and availability; never identifiers, names, emails, notes or dog data.
        $days[]=array('date'=>$day,'slots'=>$slots,'busy'=>$busy,'closed'=>!$window,'full'=>count($busy)>=3);
    }
    return $days;
}

function pz_booking_blocks() {
    return array_values(array_filter(pz_records('calendar_block'),function($b){return !empty($b['active']);}));
}
// Called inside the shared salon transaction/lock, like staff and portal bookings.
function pz_booking_block_save($input,$c=null,$now=null) {
    global $user;
    $c=$c??pz_portal_config();$dates=$input['dates']??array();
    if(!is_array($dates)||count($dates)<1||count($dates)>3)throw new InvalidArgumentException('Wybierz od 1 do 3 okienek.');
    $dates=array_values(array_unique($dates));$day=null;
    foreach($dates as $date){
        $date=pz_date($date,true);$iso=substr($date,0,10);
        if($day!==null&&$day!==$iso)throw new InvalidArgumentException('Wybierz okienka z jednego dnia.');$day=$iso;
        $available=pz_booking_calendar($iso,$c,$now)[0]['slots'];
        $found=false;foreach($available as $slot)if($slot['date']===$date&&$slot['available'])$found=true;
        if(!$found)throw new InvalidArgumentException('Okienko jest już zajęte lub niedostępne. Odśwież terminarz.');
    }
    $group=pz_text($input,'requestKey',64,true);
    if(!preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$group))throw new InvalidArgumentException('Nieprawidłowy identyfikator.');
    foreach($dates as $date){
        pz_booking_assert($date,0,true,$c,$now);
        $key=$group.'-'.str_replace(array('-',' ',':','T'),'',$date);
        pz_put('calendar_block',$key,array('id'=>$key,'date'=>pz_date($date,true),'active'=>true,'userId'=>(int)$user->id,'by'=>(string)(trim(($user->firstname??'').' '.($user->lastname??''))?:$user->login)));
    }
    return array('ok'=>true);
}
function pz_booking_block_release($input){
    global $user;
    $id=pz_text($input,'id',128,true);$b=pz_store('calendar_block',$id);
    if(!$b)throw new InvalidArgumentException('Nie znaleziono blokady.');
    $b['active']=false;$b['releasedBy']=(int)$user->id;$b['releasedAt']=date('c');pz_put('calendar_block',$id,$b);
    return array('ok'=>true);
}
