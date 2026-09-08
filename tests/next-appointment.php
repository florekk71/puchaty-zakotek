<?php
require __DIR__.'/portal.php';
$db->begin();
try {
$db->query('DELETE FROM llx_pz_visit');
$now=new DateTimeImmutable('2026-09-08 08:00:00',new DateTimeZone('Europe/Warsaw'));
$next=fn($from='',$config=null,$time=null)=>pz_booking_next($from,$config??$c,$time??$now)['date'];
ok($next()==='2026-09-08 09:00:00','First available slot');
ok($next('',null,$now->modify('+1 second'))==='2026-09-08 12:00:00','Strict one-hour lead');
$db->query("INSERT INTO llx_pz_visit(fk_soc,visit_date,status) VALUES(2,'2026-09-08 10:00:00','planned'),(3,'2026-09-08 15:00:00','planned')");
ok($next()==='2026-09-08 15:00:00','Whole salon overlap and entity isolation');
$db->query("UPDATE llx_pz_visit SET status='cancelled' WHERE fk_soc=2");
ok($next()==='2026-09-08 09:00:00','Cancelled releases slot');
$db->query("INSERT INTO llx_pz_visit(fk_soc,visit_date,status) VALUES(1,'2026-09-08 00:00:00','completed'),(1,'2026-09-08 03:00:00','completed'),(2,'2026-09-08 06:00:00','planned')");
ok($next()==='2026-09-09 09:00:00','Three dogs daily cap');
$closed=$c;$closed['calendar']['closed_dates']='2026-09-09';
ok($next('',$closed)==='2026-09-10 09:00:00','Skip holiday');
ok($next('2026-09-11')==='2026-09-11 09:00:00','Selected day starts search');
ok($next('2026-09-01')==='2026-09-09 09:00:00','Past date clamped');
$short=$closed;$short['calendar']['days_ahead']=1;
ok($next('',$short)===null,'No available slot within horizon');
ok($next('2026-09-10',$short)===null,'Beyond horizon');
ok($next('',array())===null,'Unconfigured hours never invent a time');
rejects(fn()=>$next('2026-02-30'),'Invalid date');
$before=count(pz_rows('SELECT rowid FROM llx_pz_visit'));$next();
ok(count(pz_rows('SELECT rowid FROM llx_pz_visit'))===$before,'Search must not reserve');
echo "NEXT APPOINTMENT PASS\n";
} finally {$db->rollback();}
