<?php
require '../../main.inc.php';
require_once __DIR__.'/lib/pz.lib.php';
require_once __DIR__.'/lib/commerce.lib.php';
if(empty($conf->puchatyzakatek->enabled)||empty($user->id)||!empty($user->socid)||!pz_can_read())accessforbidden();
header('Cache-Control: no-store');
require_once __DIR__.'/lib/document-pdf.lib.php';
try {
 $id=pz_key((string)GETPOST('id','alphanohtml'));$doc=pz_document_access(pz_store('document',$id));
 $file=pz_document_pdf($doc);
 header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="'.$file['name'].'"');echo $file['bytes'];
}catch(Throwable $e){dol_syslog('PuchatyZakatek PDF: '.$e->getMessage(),LOG_ERR);http_response_code(400);header('Content-Type: text/plain; charset=utf-8');echo 'Nie udało się wygenerować PDF. Sprawdź dane dokumentu i dziennik serwera.';}
