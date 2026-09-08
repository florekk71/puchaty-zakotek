<?php
// Offline SQL/authorization regression tests. Requires pdo_sqlite; no Dolibarr or SMTP connection.
error_reporting(E_ALL);set_error_handler(function($n,$s){if(error_reporting()&$n)throw new Exception($s);return true;});
define('MAIN_DB_PREFIX','llx_');
class PortalTestDB {
    public $pdo;
    function __construct(){$this->pdo=new PDO('sqlite::memory:');$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);}
    function query($s){$s=str_replace(array(' FOR UPDATE','NOW()','ON DUPLICATE KEY UPDATE payload=VALUES(payload)'),array('','CURRENT_TIMESTAMP','ON CONFLICT(entity,kind,object_key) DO UPDATE SET payload=excluded.payload'),$s);$s=str_replace('DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 2 DAY)',"datetime('now','-2 days')",$s);return $this->pdo->query($s);}
    function fetch_object($s){return $s->fetch(PDO::FETCH_OBJ);}
    function escape($s){return str_replace("'","''",$s);}
    function begin(){return $this->pdo->beginTransaction()?1:0;}
    function commit(){return $this->pdo->commit()?1:0;}
    function rollback(){if($this->pdo->inTransaction())$this->pdo->rollBack();}
    function last_insert_id($t){return $this->pdo->lastInsertId();}
}
function dol_syslog(...$v){}
$db=new PortalTestDB();$conf=(object)array('entity'=>1);$user=null;
require dirname(__DIR__).'/custom/puchatyzakatek/lib/pz.lib.php';
require dirname(__DIR__).'/custom/puchatyzakatek/lib/commerce.lib.php';
require dirname(__DIR__).'/custom/puchatyzakatek/lib/portal.lib.php';
function ok($value,$message){if(!$value)throw new Exception($message);}
function rejects($fn,$message){try{$fn();}catch(InvalidArgumentException $e){return;}throw new Exception($message);}
$db->query('CREATE TABLE llx_pz_store(rowid INTEGER PRIMARY KEY,entity INTEGER,kind TEXT,object_key TEXT,payload TEXT,datec TEXT,UNIQUE(entity,kind,object_key))');
$db->query('CREATE TABLE llx_societe(rowid INTEGER PRIMARY KEY,entity INTEGER,client INTEGER,nom TEXT,phone TEXT)');
$db->query('CREATE TABLE llx_pz_dog(rowid INTEGER PRIMARY KEY,fk_soc INTEGER,name TEXT,breed TEXT,active INTEGER,datec TEXT)');
$db->query('CREATE TABLE llx_pz_visit(rowid INTEGER PRIMARY KEY,fk_soc INTEGER,fk_dog INTEGER,visit_date TEXT,status TEXT,notes TEXT,datec TEXT)');
pz_put('config','main',pz_defaults());
putenv('PZ_MAIL_CONFIG='.__DIR__.'/nonexistent-test-mail.ini');
$db->query("INSERT INTO llx_societe VALUES(1,1,1,'Owner One',''),(2,1,1,'Private Owner Two',''),(3,2,1,'Other Entity','')");
$db->query("INSERT INTO llx_pz_dog VALUES(1,1,'Luna','',1,''),(2,2,'Private Dog','',1,''),(3,3,'Other Entity Dog','',1,'')");
$c=parse_ini_file(dirname(__DIR__).'/deploy/portal-config.example.ini',true,INI_SCANNER_TYPED);
$c['portal']=array_merge($c['portal'],array('app_secret'=>str_repeat('a',64),'base_url'=>'https://salon.example/custom/puchatyzakatek/portal','privacy_url'=>'https://salon.example/privacy','service_user_login'=>'fixture','enabled'=>true));
$c['calendar']['enforce_hours']=true;
foreach(array('monday','tuesday','wednesday','thursday','friday','saturday','sunday') as $day)$c['calendar'][$day]='09:00-18:00';
pz_portal_validate($c);
$now=new DateTimeImmutable('2026-09-08 08:00:00',new DateTimeZone('Europe/Warsaw'));
ok(pz_reservation_date('2026-09-08 09:00',$now)==='2026-09-08 09:00:00','Exact lead time');
rejects(fn()=>pz_reservation_date('2026-09-08 08:59',$now),'Lead time bypass');
$visits=array(array('rowid'=>1,'status'=>'planned','visit_date'=>'2026-09-08 09:00:00'));
ok(pz_booking_conflict('2026-09-08 12:00:00',$visits)===null,'Adjacent appointments blocked');
ok(pz_booking_conflict('2026-09-08 11:59:00',$visits)!==null,'Overlap accepted');
ok(pz_booking_conflict('2026-09-08 10:00:00',$visits,1)===null,'Reschedule conflicts with itself');
for($i=2;$i<=3;$i++)$visits[]=array('rowid'=>$i,'status'=>'completed','visit_date'=>'2026-09-08 '.($i===2?'12':'15').':00:00');
ok(pz_booking_conflict('2026-09-08 20:00:00',$visits)!==null,'Daily cap bypass');
$visits[0]['status']='cancelled';ok(pz_booking_conflict('2026-09-08 09:00:00',$visits)===null,'Cancelled visit consumes capacity');
$cross=array(array('rowid'=>9,'status'=>'planned','visit_date'=>'2026-09-07 23:00:00'));
ok(pz_booking_conflict('2026-09-08 01:00:00',$cross)!==null,'Cross-midnight overlap');
$date=(new DateTimeImmutable('+7 days',new DateTimeZone('Europe/Warsaw')))->format('Y-m-d');
$a=pz_portal_tx(fn()=>pz_portal_new_account('one@example.test'));$a['clientId']=1;pz_put('portal_account',$a['id'],$a);
rejects(fn()=>pz_portal_dog($a,2),'Foreign dog access');rejects(fn()=>pz_portal_dog($a,3),'Foreign entity dog access');
$request=array('requestKey'=>str_repeat('b',32),'date'=>$date.' 09:00:00','dogId'=>1);
$saved=pz_portal_tx(fn()=>pz_portal_book($a,$request,$c));
ok(pz_portal_tx(fn()=>pz_portal_book($a,$request,$c))===$saved,'Idempotency failed');
ok((int)pz_rows('SELECT COUNT(*) n FROM llx_pz_visit')[0]['n']===1,'Duplicate booking inserted');
rejects(fn()=>pz_portal_tx(fn()=>pz_portal_book($a,array_merge($request,array('date'=>$date.' 12:00:00')),$c)),'Idempotency key changed payload accepted');
rejects(fn()=>pz_portal_tx(fn()=>pz_portal_book($a,array_merge($request,array('requestKey'=>str_repeat('c',32),'dogId'=>2)),$c)),'Foreign dog booking accepted');
rejects(fn()=>pz_portal_tx(fn()=>pz_booking_assert($date.' 10:00:00',0,true,$c)),'Staff overlap accepted');
ok(pz_portal_tx(fn()=>pz_booking_assert($date.' 10:00:00',$saved['id'],true,$c))===$date.' 10:00:00','Own reschedule exclusion');
rejects(fn()=>pz_portal_tx(fn()=>pz_booking_assert($date.' 17:00:00',0,true,$c)),'Closing time bypass');
$closed=$c;$closed['calendar']['closed_dates']=$date;rejects(fn()=>pz_portal_tx(fn()=>pz_booking_assert($date.' 12:00:00',0,true,$closed)),'Closed holiday accepted');
foreach(array('12','15') as $h)pz_portal_tx(fn()=>pz_portal_book($a,array('requestKey'=>str_repeat($h,16),'dogId'=>1,'date'=>$date.' '.$h.':00:00'),$c));
rejects(fn()=>pz_portal_tx(fn()=>pz_booking_assert($date.' 20:00:00',0,true,array())),'Staff daily limit bypass');
$db->query("INSERT INTO llx_pz_visit(fk_soc,fk_dog,visit_date,status,notes) VALUES(2,2,'".$date." 09:00:00','planned','TOP SECRET')");
$other=(int)$db->last_insert_id('');rejects(fn()=>pz_portal_tx(fn()=>pz_portal_cancel($a,$other)),'Foreign cancellation accepted');
$private=pz_portal_data($a);ok(count($private['visits'])===3&&!str_contains(json_encode($private),'TOP SECRET')&&!str_contains(json_encode($private),'Private'),'Other customer data leaked');
$calendar=pz_booking_calendar($date,$c);ok($calendar[0]['full'],'Full day missing');ok(!str_contains(json_encode($calendar),'Private')&&!str_contains(json_encode($calendar),'rowid')&&!str_contains(json_encode($calendar),'dog'),'Anonymous calendar leaked details');
pz_portal_tx(fn()=>pz_portal_cancel($a,$saved['id']));ok(pz_rows('SELECT status FROM llx_pz_visit WHERE rowid='.$saved['id'])[0]['status']==='cancelled','Cancel failed');
// E-mail verification: wrong attempts persist; expiry, replay and disabled accounts are rejected.
$email='new@example.test';$key=pz_portal_identity_key('email',$email);$code='12345678';
$challenge=fn($until)=>pz_put('portal_code',$key,array('hash'=>pz_portal_hash($email.'|'.$code,$c),'until'=>$until,'attempts'=>0));
$challenge(time()+600);
for($i=0;$i<5;$i++)rejects(fn()=>pz_portal_check_code($email,'00000000',$c),'Wrong OTP accepted');
ok(pz_store('portal_code',$key)['attempts']===5,'Failed attempts rolled back');rejects(fn()=>pz_portal_check_code($email,$code,$c),'OTP attempts limit bypass');
$challenge(time()-1);rejects(fn()=>pz_portal_check_code($email,$code,$c),'Expired OTP accepted');
$challenge(time()+600);$new=pz_portal_check_code($email,$code,$c);ok($new['email']===$email,'Verified registration failed');
rejects(fn()=>pz_portal_check_code($email,$code,$c),'OTP replay');
$new['active']=false;pz_put('portal_account',$new['id'],$new);$challenge(time()+600);rejects(fn()=>pz_portal_check_code($email,$code,$c),'Disabled account logged in');
rejects(fn()=>pz_portal_google_account(array('sub'=>'123','email'=>'third@example.test','email_verified'=>true)),'Untrusted Google third party domain accepted');
$google=pz_portal_google_account(array('sub'=>'123','email'=>'fixture@gmail.com','email_verified'=>true));
ok(pz_portal_google_account(array('sub'=>'123','email'=>'fixture@gmail.com','email_verified'=>true))['id']===$google['id'],'Google subject identity unstable');
rejects(fn()=>pz_portal_google_account(array('sub'=>'456','email'=>'fixture@gmail.com','email_verified'=>true)),'Silent account merging');
pz_portal_rate('fixture',1,$c);rejects(fn()=>pz_portal_rate('fixture',1,$c),'Rate limiting failed');
echo "PASS: booking overlap, 3/day, hours, lead time, holidays, retry, ownership, privacy, OTP, Google identities, rate limits.\n";
