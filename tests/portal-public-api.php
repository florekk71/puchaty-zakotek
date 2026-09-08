<?php
// Exercise the actual endpoint dispatch with a guest session and isolated dependencies.
// Bootstrap/SMTP are not loaded; SQL projection and booking rules have their own portal.php tests.
if(($argv[1]??'')==='child'){
    function pz_portal_account(){return null;}
    function pz_portal_reply($data,$status=200){echo json_encode(array('status'=>$status,'data'=>$data));exit;}
    function pz_booking_calendar($from,$config){return array(array('date'=>$from,'slots'=>array(),'busy'=>array(array('start'=>'09:00','end'=>'12:00')),'closed'=>false,'full'=>false));}
    function pz_portal_data($account){throw new RuntimeException('Private data must not be called for guests');}
    function pz_portal_tx($callback){throw new RuntimeException('Writes must not be called for guests');}
    function dol_syslog(...$args){}
    $_GET=array('op'=>$argv[2],'from'=>'2026-09-09');$_SERVER['REQUEST_METHOD']=$argv[3];$_SESSION=array('pz_csrf'=>'fixture');
    $portalConfig=array('auth'=>array('email_enabled'=>true,'google_enabled'=>true),'portal'=>array('privacy_url'=>'https://example.test/privacy'));
    $source=file_get_contents(dirname(__DIR__).'/custom/puchatyzakatek/portal/api.php');
    $source=str_replace("require __DIR__.'/bootstrap.php';",'',substr($source,5));
    $source=str_replace("file_get_contents('php://input')","'{}'",$source);
    eval($source);exit;
}
foreach(array(array('calendar','GET',200),array('data','GET',401),array('book','POST',401),array('cancel','POST',401),array('profile','POST',401),array('dog','POST',401)) as [$op,$method,$expected]){
    $process=proc_open(array(PHP_BINARY,__FILE__,'child',$op,$method),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
    $output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    $result=json_decode($output,true);
    if($exit||!$result||$result['status']!==$expected)throw new RuntimeException($op.' failed: '.$errors.$output);
    if($op==='calendar'&&array_keys($result['data'])!==array('days'))throw new RuntimeException('Unexpected public fields');
}
echo "PASS: guest calendar 200; private data, booking, cancellation, profile and dog writes 401.\n";
