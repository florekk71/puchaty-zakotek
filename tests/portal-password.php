<?php
require __DIR__.'/portal.php';
$c['auth']['email_enabled']=true;
$a=pz_portal_tx(fn()=>pz_portal_new_account('password@example.test'));
pz_put('portal_identity',pz_portal_identity_key('email',$a['email']),array('accountId'=>$a['id']));
$_SESSION=array('pz_customer'=>$a['id'],'pz_until'=>time()+3600);
$input=array('password'=>'Correct horse 123!','confirm'=>'Correct horse 123!');
rejects(fn()=>pz_portal_tx(fn()=>pz_portal_set_password($a,$input)),'Setup without verification');
$_SESSION['pz_code_verified_at']=time();
pz_portal_tx(fn()=>pz_portal_set_password($a,$input));
$hash=pz_store('portal_password',$a['id'])['hash'];ok($hash!==$input['password']&&password_verify($input['password'],$hash),'Password storage');
ok(pz_portal_password_login('PASSWORD@example.test',$input['password'],$c)['id']===$a['id'],'Email login');
rejects(fn()=>pz_portal_password_login($a['email'],'incorrect',$c),'Wrong password');
rejects(fn()=>pz_portal_password_login('unknown@example.test',$input['password'],$c),'Unknown account');
$a=pz_store('portal_account',$a['id']);
rejects(fn()=>pz_portal_tx(fn()=>pz_portal_set_password($a,$input)),'Change without current password');
$_SESSION['pz_code_verified_at']=time();$new=array('password'=>'Replacement password 123!','confirm'=>'Replacement password 123!');
pz_portal_tx(fn()=>pz_portal_set_password($a,$new));
rejects(fn()=>pz_portal_password_login($a['email'],$input['password'],$c),'Old password accepted');
ok(pz_portal_password_login($a['email'],$new['password'],$c)['id']===$a['id'],'Reset failed');
$_SESSION['pz_auth_version']=0;ok(pz_portal_account()===null,'Old session survived reset');
$a=pz_store('portal_account',$a['id']);$a['active']=false;pz_put('portal_account',$a['id'],$a);
rejects(fn()=>pz_portal_password_login($a['email'],$new['password'],$c),'Disabled account');
for($i=0;$i<10;$i++){try{pz_portal_password_login('limited@example.test','wrong',$c);}catch(InvalidArgumentException $e){}}
try{pz_portal_password_login('limited@example.test','wrong',$c);throw new Exception('Rate limit missing');}catch(InvalidArgumentException $e){ok(str_contains($e->getMessage(),'Za dużo'),'Failed attempts not persisted');}
echo "PASS: password setup, hash, login, recovery, old sessions, inactive accounts and rate limits\n";
