<?php

// Presentation only: leave authentication, session tokens and form fields intact.

$sourcePath=$argv[1]??'/var/www/html/core/tpl/login.tpl.php';

$targetPath=$argv[2]??$sourcePath;

$source=file_get_contents($sourcePath);

if($source===false)throw new RuntimeException('Cannot read login template');

if(!str_contains($source,'PZ_LOGIN_BRAND_V1')){

    $pattern='~<!-- Title with version -->.*?(?=<div class="login_table">)~s';

    $source=preg_replace($pattern,"<!-- PZ_LOGIN_BRAND_V1 -->\n",$source,-1,$count);

    if($count!==1)throw new RuntimeException('Unsupported login title structure');

    $old='<img alt="" src="<?php echo $urllogo; ?>" id="img_logo" />';

    $new='<img alt="Puchaty Zakątek" src="<?php echo DOL_URL_ROOT; ?>/custom/puchatyzakatek/img/logo.jpg" id="img_logo" style="width:180px !important;height:180px !important;max-height:180px !important;max-width:80vw;object-fit:contain" />';

    if(substr_count($source,$old)!==1)throw new RuntimeException('Unsupported login logo structure');

    $source=str_replace($old,$new,$source);

}

if(!str_contains($source,'PZ_LOGIN_STYLE_V2')){

    $brand=<<<'HTML'

<!-- PZ_LOGIN_STYLE_V2 -->

<style>

body.bodylogin, body.bodylogin .login_center {background:radial-gradient(ellipse at top left,#dcefe7 0,transparent 55%),radial-gradient(ellipse at bottom right,#f4d4df 0,transparent 58%),#fffaf7 !important;background-attachment:fixed !important;}

body.bodylogin .login_center {min-height:100vh;box-sizing:border-box;padding:24px 16px;}

body.bodylogin .login_vertical_align {background:transparent !important;}

body.bodylogin form#login {box-sizing:border-box;width:575px;max-width:100%;margin:0 auto;padding:40px 44px;background:#fffdfb !important;border:1px solid #ecd2db;border-radius:30px;box-shadow:0 24px 60px #89405c20;color:#443d43;}

body.bodylogin .pz-login-heading {margin:0 0 48px;color:#913b5b;font:700 34px/1.2 Arial,sans-serif;text-align:center;}

body.bodylogin .login_table {background:transparent !important;border:0 !important;box-shadow:none !important;width:100%;margin:0;padding:0;}

body.bodylogin #img_logo {width:222px !important;height:225px !important;max-height:225px !important;max-width:100% !important;object-fit:contain;}

body.bodylogin #login_right {margin-top:26px;}

body.bodylogin #username, body.bodylogin #password {box-sizing:border-box;width:320px;max-width:calc(100% - 36px);height:56px;border:1px solid #e5b5c5 !important;border-radius:14px;background:#fff !important;color:#443d43;padding:12px;font-size:22px;}

body.bodylogin .trinputlogin {margin-bottom:24px;}

body.bodylogin .butActionLogin {background:#893c59 !important;color:#fff !important;border:0 !important;border-radius:14px;padding:16px 36px;font-size:17px;box-shadow:none !important;}

body.bodylogin .alogin {color:#913b5b !important;}

body.bodylogin input:focus-visible,body.bodylogin a:focus-visible {outline:2px solid #913b5b;outline-offset:3px;}

@media(max-width:600px){body.bodylogin form#login{padding:28px 16px;}body.bodylogin .pz-login-heading{font-size:28px;margin-bottom:28px;}body.bodylogin #img_logo{width:180px !important;height:180px !important;}body.bodylogin .login_center{padding:16px 8px;}}

</style>

<h1 class="pz-login-heading">Puchaty Zakątek</h1>

HTML;

    $source=str_replace('<!-- PZ_LOGIN_BRAND_V1 -->','<!-- PZ_LOGIN_BRAND_V1 -->'."\n".$brand,$source);

}

if(!str_contains($source,'PZ_LOGIN_FAVICON_V1')){
    $anchor="top_htmlhead('', \$titleofloginpage";
    if(substr_count($source,$anchor)!==1)throw new RuntimeException('Unsupported login head structure');
    $code=<<<'PHP'
// PZ_LOGIN_FAVICON_V1
$conf->global->MAIN_FAVICON_URL = DOL_URL_ROOT.'/custom/puchatyzakatek/img/logo.jpg?v=pz1';
PHP;
    $source=str_replace($anchor,$code."\n".$anchor,$source);
}
if(!str_contains($source,'PZ_LOGIN_TITLE_V1')){
    $anchor="top_htmlhead('', \$titleofloginpage";
    if(substr_count($source,$anchor)!==1)throw new RuntimeException('Unsupported login title call');
    $code=<<<'PHP'
// PZ_LOGIN_TITLE_V1
$titleofloginpage = 'Puchaty Zakątek';
PHP;
    $source=str_replace($anchor,$code."\n".$anchor,$source);
}
// Upgrade existing branded templates as well as fresh installations.
$source=str_replace('/custom/puchatyzakatek/img/logo.jpg','/custom/puchatyzakatek/img/logo-transparent.png',$source);
if(file_put_contents($targetPath,$source)===false)throw new RuntimeException('Cannot write branded login template');
echo "PZ_LOGIN_BRAND_OK\n";

