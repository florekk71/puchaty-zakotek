<?php
// Staff-only CLI: linking requires the operator to verify the customer's identity.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('NOLOGIN',1);define('NOSESSION',1);define('NOREQUIREMENU',1);define('NOREQUIREUSER',1);
$_SERVER['REQUEST_METHOD']='GET';$_SESSION=array();
require dirname(__DIR__,3).'/main.inc.php';
require_once dirname(__DIR__).'/lib/pz.lib.php';require_once dirname(__DIR__).'/lib/commerce.lib.php';require_once dirname(__DIR__).'/lib/portal.lib.php';
try{
    $command=$argv[1]??'';$email=pz_portal_email($argv[2]??'');
    if(!in_array($command,array('link','disable','enable'),true))throw new InvalidArgumentException('Użycie: portal-account.php link EMAIL ID_KLIENTA | disable EMAIL | enable EMAIL');
    pz_portal_tx(function()use($command,$email,$argv){
        $identity=pz_portal_identity('email',$email);if(!$identity)throw new InvalidArgumentException('Klient musi najpierw potwierdzić e-mail / zalogować się.');
        $a=pz_store('portal_account',$identity['accountId']);if(!$a)throw new RuntimeException('Brak konta.');
        if($command==='link'){
            $id=(int)($argv[3]??0);
            if(!empty($a['clientId'])&&(int)$a['clientId']!==$id)throw new InvalidArgumentException('Konto ma już kartotekę. Scalanie wymaga osobnej weryfikacji historii.');
            $rows=pz_rows('SELECT s.rowid FROM '.MAIN_DB_PREFIX.'societe s WHERE s.rowid='.$id.' AND s.entity='.pz_entity().' AND s.client IN (1,3)'.pz_active_client_sql());
            if(!$rows)throw new InvalidArgumentException('Nie znaleziono aktywnego klienta w tej firmie.');
            foreach(pz_rows('SELECT payload FROM '.MAIN_DB_PREFIX."pz_store WHERE entity=".pz_entity()." AND kind='portal_account'") as $row){$other=json_decode($row['payload'],true);if((int)($other['clientId']??0)===$id&&$other['id']!==$a['id'])throw new InvalidArgumentException('Kartoteka jest przypisana do innego konta.');}
            $a['clientId']=$id;
        }else{$a['active']=$command==='enable';}
        pz_put('portal_account',$a['id'],$a);
    });echo "OK\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
