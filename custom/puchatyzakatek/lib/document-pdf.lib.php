<?php
function pz_pdf_escape($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function pz_pdf_money($v){return number_format($v/100,2,',',' ').' zł';}
function pz_document_pdf($doc) {
 $id=pz_key($doc['id']);
 require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
 $pdf=pdf_getInstance(array(210,297));$pdf->setPrintHeader(false);$pdf->setPrintFooter(true);$pdf->setFooterMargin(10);$pdf->setFooterFont(array('dejavusans','',8));$pdf->SetMargins(15,15,15);$pdf->SetAutoPageBreak(true,18);$pdf->SetFont('dejavusans','',9);$pdf->AddPage();
 $seller=$doc['seller']??(pz_config()['seller']??array('name'=>pz_config()['salon'],'address'=>pz_config()['address']));
 $logo=!empty($seller['logo'])?'@'.base64_decode(substr($seller['logo'],strlen('data:image/jpeg;base64,')),true):dirname(__DIR__).'/img/logo.jpg';
 if($logo[0]==='@'||is_file($logo)){$info=$logo[0]==='@'?getimagesizefromstring(substr($logo,1)):getimagesize($logo);$scale=min(25/$info[0],25/$info[1]);$pdf->Image($logo,15,12,$info[0]*$scale,$info[1]*$scale,'JPG');$pdf->SetY(42);}
 $buyer=$doc['buyer']??(pz_rows('SELECT nom,address,zip,town,siren FROM '.MAIN_DB_PREFIX.'societe WHERE rowid='.(int)$doc['clientId'])[0]??array());
 $title=array('rachunek'=>'Rachunek','faktura'=>'Faktura','potwierdzenie'=>'Potwierdzenie niefiskalne')[$doc['type']];
 $html='<h1 style="color:#873e55">Puchaty Zakątek</h1><h2>'.$title.' '.pz_pdf_escape($doc['number']).'</h2>';
 if($doc['state']!=='issued')$html.='<h2>'.($doc['state']==='draft'?'SZKIC — dokument niewystawiony':'ANULOWANY').'</h2>';
 if(($seller['taxProfile']??'')==='ndg_consumer_art113')$html.='<p>Sprzedaż zwolniona z VAT</p>';
 $html.='<p>Data wystawienia: '.pz_pdf_escape($doc['issueDate']).'<br>Data sprzedaży: '.pz_pdf_escape($doc['saleDate']).'</p><table cellpadding="8"><tr><td><b>Sprzedawca</b><br>'.pz_pdf_escape($seller['name']??'').'<br>'.pz_pdf_escape($seller['address']??'').(!empty($seller['nip'])?'<br>NIP: '.pz_pdf_escape($seller['nip']):'').'</td><td><b>Nabywca</b><br>'.pz_pdf_escape($buyer['nom']??'').'<br>'.pz_pdf_escape($buyer['address']??'').'<br>'.pz_pdf_escape(($buyer['zip']??'').' '.($buyer['town']??'')).(!empty($buyer['siren'])?'<br>NIP: '.pz_pdf_escape($buyer['siren']):'').'</td></tr></table>';
 $html.='<table border="1" cellpadding="5"><thead><tr style="background-color:#f8dce3"><th width="32%">Pozycja</th><th width="10%">Ilość</th><th width="8%">JM</th><th width="17%">Cena brutto</th><th width="10%">VAT</th><th width="23%">Wartość brutto</th></tr></thead><tbody>';
 foreach($doc['lines'] as $l)$html.='<tr><td width="32%">'.pz_pdf_escape($l['name']).'</td><td width="10%">'.pz_pdf_escape($l['qty']/1000).'</td><td width="8%">'.pz_pdf_escape($l['unit']).'</td><td width="17%">'.pz_pdf_money($l['price']).'</td><td width="10%">'.pz_pdf_escape($l['vat']).'</td><td width="23%">'.pz_pdf_money($l['total']).'</td></tr>';
 $html.='</tbody></table><p align="right">Netto: '.pz_pdf_money($doc['net']).'<br>VAT: '.pz_pdf_money($doc['tax']).'<br><b>Razem: '.pz_pdf_money($doc['total']).'</b></p><p>Forma płatności: '.pz_pdf_escape($doc['payment']).'<br>Termin płatności: '.pz_pdf_escape($doc['dueDate']).'<br>'.($doc['state']==='void'?'Anulowano: '.pz_pdf_escape($doc['voidDate']??'').' — '.pz_pdf_escape($doc['reason']??''):(!empty($doc['received'])?'Opłacono: '.pz_pdf_escape($doc['paidDate']??''):'Do zapłaty: '.pz_pdf_money($doc['total']))).'</p><p>Bank: '.pz_pdf_escape($seller['bank']??'').'<br>Rachunek: '.pz_pdf_escape($seller['account']??'').'</p><p>'.pz_pdf_escape($seller['exemption']??'').'</p><p>'.nl2br(pz_pdf_escape($doc['notes'])).'</p>';
 if($doc['type']==='potwierdzenie')$html.='<p>Dokument niefiskalny.</p>';
 $html.='<p>Słownie: '.pz_pdf_escape(pz_amount_words($doc['total'])).'</p>';
 $rates=array();foreach($doc['lines'] as $l){$r=$l['vat'];if(!isset($rates[$r]))$rates[$r]=array(0,0,0);$rates[$r][0]+=$l['net'];$rates[$r][1]+=$l['tax'];$rates[$r][2]+=$l['total'];}
 $html.='<table border="1" cellpadding="4"><tr><th>Stawka VAT</th><th>Netto</th><th>VAT</th><th>Brutto</th></tr>';foreach($rates as $r=>$v)$html.='<tr><td>'.pz_pdf_escape($r).'</td><td>'.pz_pdf_money($v[0]).'</td><td>'.pz_pdf_money($v[1]).'</td><td>'.pz_pdf_money($v[2]).'</td></tr>';$html.='</table>';
 $pdf->writeHTML($html,true,false,true,false,'');$bytes=$pdf->Output('','S');
 $dir=DOL_DATA_ROOT.'/puchatyzakatek/'.pz_entity().'/documents';if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new RuntimeException('Nie można utworzyć katalogu PDF.');
 $filename='PZ-'.$id.'-v'.(int)$doc['version'].'.pdf';if(file_put_contents($dir.'/'.$filename,$bytes,LOCK_EX)===false)throw new RuntimeException('Nie można zapisać PDF.');
 return array('path'=>$dir.'/'.$filename,'name'=>$filename,'bytes'=>$bytes);
}
