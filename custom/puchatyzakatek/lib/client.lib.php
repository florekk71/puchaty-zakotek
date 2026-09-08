<?php
function pz_save_client($input) {
    global $db, $user;
    $id = (int) ($input['id'] ?? 0);
    if(isset($input['clientKind'])){if(!in_array($input['clientKind'],array('person','company'),true))throw new InvalidArgumentException('Nieprawidłowy rodzaj klienta.');if($input['clientKind']==='person')$input['nip']='';}
    $name = pz_text($input, 'name', 128, true);
    $phone = pz_text($input, 'phone', 20);
    $email = pz_text($input, 'email', 128);
    $town = pz_text($input, 'town', 128);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Nieprawidłowy adres e-mail.');
    if ($id) pz_client($id);
    require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
    $client = new Societe($db);
    if ($id && $client->fetch($id) <= 0) throw new InvalidArgumentException('Nie znaleziono klienta.');
    // Update supplied contact fields; preserve unrelated Dolibarr data.
    $client->name = $name;
    $client->phone = $phone;
    $client->email = $email;
    $client->town = $town;
    foreach(array('address'=>'address','zip'=>'zip','nip'=>'idprof1','alias'=>'name_alias','www'=>'url','notes'=>'note_private') as $key=>$property) {
        if(array_key_exists($key,$input))$client->$property=pz_text($input,$key,$key==='notes'?5000:255);
    }
    if(array_key_exists('nip',$input)&&$input['nip']!==''){
        $nip=preg_replace('/[ -]/','',$input['nip']);
        if(!preg_match('/^\d{10}$/D',$nip))throw new InvalidArgumentException('NIP musi mieć 10 cyfr.');
        $sum=0;foreach(array(6,5,7,2,3,4,5,6,7) as $i=>$w)$sum+=(int)$nip[$i]*$w;
        if($sum%11!==(int)$nip[9])throw new InvalidArgumentException('NIP ma nieprawidłową sumę kontrolną.');$client->idprof1=$nip;
    }
    if (!$id) {
        $client->entity = pz_entity();
        $client->client = 1;
        $client->fournisseur = 0;
        $client->code_client = '-1'; // Use Dolibarr's configured numbering module.
        $result = $client->create($user);
    } else {
        $result = $client->update($id, $user);
    }
    if ($result < 0 || (!$id && !$result)) {
        dol_syslog('PuchatyZakatek client save: '.$client->error, LOG_ERR);
        throw new RuntimeException('Nie udało się zapisać klienta. Sprawdź dziennik Dolibarra.');
    }
    if (!$id) {
        $id = (int) $result;
        pz_query('INSERT INTO '.MAIN_DB_PREFIX.'societe_commerciaux (fk_soc,fk_user) VALUES ('.$id.','.(int)$user->id.')');
    }
    $meta=pz_store('client_meta',(string)$id)??array();if(array_key_exists('contact',$input))$meta['contact']=pz_text($input,'contact',255);if(isset($input['clientKind']))$meta['clientKind']=$input['clientKind'];pz_put('client_meta',(string)$id,$meta);
    return array('id'=>$id);
}
