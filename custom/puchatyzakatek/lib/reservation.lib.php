<?php
// Appointment wall-clock values belong to the salon in Poland, regardless of server timezone.
function pz_reservation_date($value, $now = null) {
    $zone = new DateTimeZone('Europe/Warsaw');
    $raw = str_replace('T', ' ', (string)$value);
    if(strlen($raw)===16)$raw.=':00';
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $raw, $zone);
    if(!$date || $date->format('Y-m-d H:i:s')!==$raw)throw new InvalidArgumentException('Nieprawidlowa data rezerwacji.');
    $now = $now ?? new DateTimeImmutable('now', $zone);
    $minimum = $now->getTimestamp()+3600;
    if($date->getTimestamp()<$minimum){
        $display=(new DateTimeImmutable('@'.(int)(ceil($minimum/60)*60)))->setTimezone($zone)->format('d.m.Y H:i');
        throw new InvalidArgumentException('Rezerwacja musi byc co najmniej godzine do przodu. Najblizszy termin: '.$display.' (czas polski).');
    }
    return $date->format('Y-m-d H:i:s');
}
