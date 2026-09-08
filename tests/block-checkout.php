<?php
require __DIR__.'/portal.php';
$user=new class {public $id=7;public $admin=1;public $socid=0;function hasRight(...$args){return true;}};
$db->query('DELETE FROM llx_pz_visit');
$day=(new DateTimeImmutable('+8 days',new DateTimeZone('Europe/Warsaw')))->format('Y-m-d');
$block=['id'=>'checkout-block-01','date'=>$day.' 18:00:00','active'=>true,'by'=>'Malwina'];pz_put('calendar_block',$block['id'],$block);
$r=pz_portal_tx(fn()=>pz_booking_block_visit(['id'=>$block['id'],'dogId'=>1]));
$v=pz_visit($r['id']);ok($v['visit_date']===$block['date']&&$v['status']==='planned'&&(int)$v['fk_dog']===1,'Wrong converted visit');
ok(!pz_store('calendar_block',$block['id'])['active'],'Block not removed');
ok(pz_portal_tx(fn()=>pz_booking_block_visit(['id'=>$block['id'],'dogId'=>1]))===$r,'Retry not idempotent');
ok(count(pz_rows('SELECT rowid FROM llx_pz_visit'))===1,'Duplicate visit');
rejects(fn()=>pz_portal_tx(fn()=>pz_booking_block_visit(['id'=>$block['id'],'dogId'=>2])),'Changed dog accepted on retry');
rejects(fn()=>pz_booking_staff_assert($day.' 19:00:00'),'Converted slot became free');
$bad=['id'=>'checkout-block-02','date'=>$day.' 19:00:00','active'=>true];pz_put('calendar_block',$bad['id'],$bad);
rejects(fn()=>pz_portal_tx(fn()=>pz_booking_block_visit(['id'=>$bad['id'],'dogId'=>1])),'Conflict accepted');ok(pz_store('calendar_block',$bad['id'])['active'],'Rollback lost block');
$conf->entity=2;rejects(fn()=>pz_portal_tx(fn()=>pz_booking_block_visit(['id'=>$block['id'],'dogId'=>3])),'Cross entity accepted');
echo "PASS: block conversion, original date, idempotent retry, dog and entity checks, rollback, slot stays occupied.\n";
