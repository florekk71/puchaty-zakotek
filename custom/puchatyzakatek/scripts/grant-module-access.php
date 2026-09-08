<?php
// Run by the server operator only; never expose account changes over HTTP.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
if (empty($argv[1])) { fwrite(STDERR, "Usage: php grant-module-access.php LOGIN\n"); exit(1); }
define('NOLOGIN',1);
define('NOREQUIREMENU',1);
$_SERVER['PHP_SELF']='/custom/puchatyzakatek/scripts/grant-module-access.php';
$_SERVER['REQUEST_METHOD']='GET';
require dirname(__DIR__,3).'/main.inc.php';
function pz_grant_query($sql) { global $db; $r=$db->query($sql); if(!$r) throw new RuntimeException($db->lasterror()); return $r; }
try {
    $db->begin();
    $entity=(int)$conf->entity;
    $q=pz_grant_query("SELECT rowid,login,admin,fk_soc,statut FROM ".MAIN_DB_PREFIX."user WHERE login='".$db->escape($argv[1])."' AND entity IN (0,".$entity.")");
    if($db->num_rows($q)!==1) throw new RuntimeException('Nie znaleziono jednoznacznie konta w tej jednostce.');
    $account=$db->fetch_object($q);
    if(!empty($account->fk_soc)||!(int)$account->statut) throw new RuntimeException('Konto musi byc aktywnym uzytkownikiem wewnetrznym.');
    $id=(int)$account->rowid;
    foreach(array(500001=>'read',500002=>'write',500003=>'manage') as $rid=>$perm) {
        $r=$db->fetch_object(pz_grant_query('SELECT module,perms FROM '.MAIN_DB_PREFIX.'rights_def WHERE id='.$rid));
        if(!$r||$r->module!=='puchatyzakatek'||$r->perms!==$perm) throw new RuntimeException('Brak poprawnej definicji praw modulu. Aktywuj modul Puchaty Zakatek w konfiguracji.');
        $exists=$db->fetch_object(pz_grant_query('SELECT fk_id FROM '.MAIN_DB_PREFIX.'user_rights WHERE entity='.$entity.' AND fk_user='.$id.' AND fk_id='.$rid));
        if(!$exists) pz_grant_query('INSERT INTO '.MAIN_DB_PREFIX.'user_rights (entity,fk_user,fk_id) VALUES ('.$entity.','.$id.','.$rid.')');
    }
    if($db->commit()<=0) throw new RuntimeException('Nie zatwierdzono uprawnien.');
    echo 'OK: '.$account->login." — odczyt, zapis i pelna obsluga modulu. Wyloguj konto i zaloguj ponownie.\n";
} catch(Throwable $e) { $db->rollback(); fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
