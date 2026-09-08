<?php
// Build standards-compliant MIME without relying on SMTPs' raw 8-bit HTML wrapping.
function pz_mail_mime($html,$text,$file=null) {
    $token=bin2hex(random_bytes(12));$mixed='pz-mixed-'.$token;$alt='pz-alt-'.$token;$related='pz-related-'.$token;$images=array();
    $html=preg_replace_callback('~data:image/png;base64,([A-Za-z0-9+/=]+)~',function($m)use(&$images,$token){
        $bytes=base64_decode($m[1],true);if($bytes===false)throw new RuntimeException('Nieprawidlowe logo PNG.');
        $cid='pz-logo-'.count($images).'-'.$token.'@puchaty.local';$images[]=array('cid'=>$cid,'bytes'=>$bytes);return 'cid:'.$cid;
    },$html);
    if(str_contains($html,'data:image/'))throw new RuntimeException('Nieobslugiwany obraz osadzony w wiadomosci.');
    $part=function($type,$bytes,$headers=''){return 'Content-Type: '.$type."\r\nContent-Transfer-Encoding: base64\r\n".$headers."\r\n".chunk_split(base64_encode($bytes),76,"\r\n");};
    $body='Content-Type: multipart/mixed; boundary="'.$mixed."\"\r\n\r\n";
    $body.='--'.$mixed."\r\nContent-Type: multipart/alternative; boundary=\"".$alt."\"\r\n\r\n";
    $body.='--'.$alt."\r\n".$part('text/plain; charset=UTF-8',$text);
    $body.='--'.$alt."\r\nContent-Type: multipart/related; boundary=\"".$related."\"\r\n\r\n";
    $body.='--'.$related."\r\n".$part('text/html; charset=UTF-8',$html);
    foreach($images as $i=>$image)$body.='--'.$related."\r\n".$part('image/png',$image['bytes'],'Content-ID: <'.$image['cid'].">\r\nContent-Disposition: inline; filename=\"logo-".$i.".png\"\r\n");
    $body.='--'.$related."--\r\n--".$alt."--\r\n";
    if($file){$bytes=file_get_contents($file['path']);if($bytes===false)throw new RuntimeException('Nie mozna odczytac PDF.');$name=preg_replace('/[^a-zA-Z0-9._-]/','_',$file['name']);$body.='--'.$mixed."\r\n".$part('application/pdf',$bytes,'Content-Disposition: attachment; filename="'.$name."\"\r\n");}
    $body.='--'.$mixed."--\r\n";pz_mail_check_lines($body);return $body;
}
function pz_mail_check_lines($wire){foreach(explode("\r\n",$wire) as $line)if(strlen($line)>998)throw new RuntimeException('Wiadomosc ma zbyt dluga linie SMTP. Wysylka zatrzymana przed polaczeniem.');}
