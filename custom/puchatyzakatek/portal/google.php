<?php
require __DIR__.'/bootstrap.php';
function pz_google_http($url,$post=null,$bearer=null){
    if(!function_exists('curl_init'))throw new RuntimeException('Brak rozszerzenia cURL.');
    $ch=curl_init($url);$headers=array('Accept: application/json');if($bearer)$headers[]='Authorization: Bearer '.$bearer;
    curl_setopt_array($ch,array(CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>$headers,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS));
    if($post!==null)curl_setopt_array($ch,array(CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)));
    $body=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($status!==200||!is_string($body))throw new RuntimeException('Google nie potwierdziło logowania.');
    return json_decode($body,true,16,JSON_THROW_ON_ERROR);
}
try{
    if(empty($portalConfig['auth']['google_enabled']))throw new InvalidArgumentException('Logowanie Google jest wyłączone.');
    $a=$portalConfig['auth'];$redirect=$portalUrl.'/google.php';
    if($_SERVER['REQUEST_METHOD']==='POST'){
        $state=bin2hex(random_bytes(32));$verifier=bin2hex(random_bytes(32));$account=pz_portal_account();
        $_SESSION['pz_google']=array('state'=>$state,'verifier'=>$verifier,'until'=>time()+600,'link'=>$account['id']??null);
        $query=array('client_id'=>$a['google_client_id'],'redirect_uri'=>$redirect,'response_type'=>'code','scope'=>'openid email profile','state'=>$state,'code_challenge'=>rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'='),'code_challenge_method'=>'S256','prompt'=>'select_account');
        header('Location: https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query($query),true,303);exit;
    }
    $flow=$_SESSION['pz_google']??null;unset($_SESSION['pz_google']);
    if(!$flow||$flow['until']<time()||!hash_equals($flow['state'],(string)($_GET['state']??''))||empty($_GET['code']))throw new InvalidArgumentException('Logowanie Google wygasło lub zostało anulowane. Spróbuj ponownie.');
    if($flow['link']&&((pz_portal_account()['id']??null)!==$flow['link']))throw new InvalidArgumentException('Sesja wygasła. Zaloguj się ponownie.');
    $token=pz_google_http('https://oauth2.googleapis.com/token',array('code'=>(string)$_GET['code'],'client_id'=>$a['google_client_id'],'client_secret'=>$a['google_client_secret'],'redirect_uri'=>$redirect,'grant_type'=>'authorization_code','code_verifier'=>$flow['verifier']));
    if(empty($token['access_token'])||!is_string($token['access_token'])||preg_match('/[\r\n]/',$token['access_token']))throw new RuntimeException('Nie otrzymano tokenu Google.');
    // Obtain identity from Google's authenticated HTTPS endpoint; never trust a browser-decoded JWT.
    $identity=pz_google_http('https://openidconnect.googleapis.com/v1/userinfo',null,$token['access_token']);
    $account=pz_portal_google_account($identity,$flow['link']);pz_portal_login($account);
}catch(Throwable $e){
    // OAuth tokens, authorization codes and secrets must not be included in logs or redirects.
    $_SESSION['pz_notice']=$e instanceof InvalidArgumentException?$e->getMessage():'Nie udało się zalogować przez Google. Spróbuj ponownie lub użyj e-maila.';
}
header('Location: '.$portalUrl.'/',true,303);exit;
