<?php
require dirname(__DIR__).'/custom/puchatyzakatek/lib/mail.lib.php';
function pz_config(){return array('mailOwner'=>array('address'=>'biuro@topkomp.pl','enabled'=>true,'events'=>array()));}
function check_address($condition){if(!$condition)throw new RuntimeException('Salon email regression');}
$path=tempnam(sys_get_temp_dir(),'pz-mail-');
try{
 file_put_contents($path,"[smtp]\nusername=\"biuro@topkomp.pl\"\npassword=\"test-only\"\n[sender]\naddress=\"biuro@topkomp.pl\"\nreply_to=\"biuro@topkomp.pl\"\n[delivery]\ntest_recipient=\"biuro@topkomp.pl\"\n");
 putenv('PZ_MAIL_CONFIG='.$path);$c=pz_mail_config();
 check_address($c['sender']['address']==='biuro@puchaty-zakatek.pl');
 check_address($c['sender']['reply_to']==='biuro@puchaty-zakatek.pl');
 check_address($c['smtp']['username']==='biuro@topkomp.pl'&&$c['smtp']['password']==='test-only');
 check_address(pz_mail_owner()['address']==='biuro@puchaty-zakatek.pl');
 check_address(pz_mail_salon_address('client@example.test')==='client@example.test');
 $html=pz_mail_template('portal_login',array('Kod jednorazowy'=>'00000000'),$c);
 check_address(str_contains($html,'mailto:biuro@puchaty-zakatek.pl')&&!str_contains($html,'biuro@topkomp.pl'));
 echo "PASS: sender, reply-to, owner, template; SMTP credentials preserved\n";
}finally{unlink($path);putenv('PZ_MAIL_CONFIG');}
