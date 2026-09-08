<?php
// Offline diagnostic: no database, credentials or network connections.
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only');}
require dirname(__DIR__).'/lib/mail-template.lib.php';
require dirname(__DIR__).'/lib/mail-mime.lib.php';
$c=array('sender'=>array('address'=>'salon@example.test'),'salon'=>array());
try{
 foreach(array('booking_confirmation','appointment_changed','appointment_cancelled','appointment_reminder','invoice_after_payment','owner_booking_confirmation') as $event){
  $html=pz_mail_template($event,array('Pupil'=>'Przykład '.str_repeat('x',1500)),$c);
  $mime=pz_mail_mime($html,'Zażółć gęślą jaźń');
  $longest=max(array_map('strlen',explode("\r\n",$mime)));
  if($longest>998||str_contains($mime,'data:image/png'))throw new RuntimeException('Nieprawidlowy format MIME.');
  echo 'OK '.$event.' max_line='.$longest."\n";
 }
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
