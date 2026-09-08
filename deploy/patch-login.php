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
if(file_put_contents($targetPath,$source)===false)throw new RuntimeException('Cannot write branded login template');
echo "PZ_LOGIN_BRAND_OK\n";
