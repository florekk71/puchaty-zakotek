<?php
require __DIR__.'/portal.php';
$db->query('DELETE FROM llx_pz_visit');$now=new DateTimeImmutable('2026-09-08 08:00:00',new DateTimeZone('Europe/Warsaw'));
foreach(['monday','tuesday','wednesday','thursday','friday'] as $d)$c['calendar'][$d]='09:00-15:00';
$c['calendar']['saturday']='';$c['calendar']['sunday']='';
ok(pz_booking_staff_assert('2026-09-09 18:00:00',0,$c,$now)==='2026-09-09 18:00:00','Staff evening denied');
rejects(fn()=>pz_booking_assert('2026-09-09 18:00:00',0,true,$c,$now),'Portal allows evening');
rejects(fn()=>pz_booking_staff_assert('2026-09-08 08:30:00',0,$c,$now),'Staff bypasses lead');
$db->query("INSERT INTO llx_pz_visit(fk_soc,visit_date,status) VALUES(1,'2026-09-09 18:00:00','planned')");
rejects(fn()=>pz_booking_staff_assert('2026-09-09 19:00:00',0,$c,$now),'Staff overlap');
$day=pz_booking_calendar('2026-09-09',$c,$now)[0];ok(count($day['slots'])===2&&count($day['busy'])===0&&!str_contains(json_encode($day),'18:00'),'Private hours exposed');
$db->query("INSERT INTO llx_pz_visit(fk_soc,visit_date,status) VALUES(1,'2026-09-09 14:00:00','planned')");
$day=pz_booking_calendar('2026-09-09',$c,$now)[0];ok(!$day['slots'][1]['available']&&$day['busy'][0]['start']==='12:00'&&!str_contains(json_encode($day),'14:00'),'Overlap projection leaks staff time');
$user=(object)['id'=>7,'login'=>'Malwina'];pz_portal_tx(fn()=>pz_booking_block_save(['requestKey'=>'manual-block-test-001','manual'=>true,'dates'=>['2026-09-10 19:00:00']],$c,$now));
rejects(fn()=>pz_booking_staff_assert('2026-09-10 20:00:00',0,$c,$now),'Manual block ignored');
echo "PASS: staff evenings, portal two windows, lead time, overlap, private hours, manual block.\n";
