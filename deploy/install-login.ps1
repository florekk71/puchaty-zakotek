param([string]$ProjectRoot=(Split-Path $PSScriptRoot),[string]$Container='puchaty-dolibarr')
$ErrorActionPreference='Stop'
$OutputEncoding=[Console]::OutputEncoding=[System.Text.UTF8Encoding]::new($false)
function Run-Docker {param([string[]]$DockerArgs) $output=& docker @DockerArgs 2>&1;if($LASTEXITCODE -ne 0){throw ($output -join "`n")};return ($output -join "`n")}
$root=(Resolve-Path -LiteralPath $ProjectRoot).Path
$compose=@('compose.yml','docker-compose.yml')|ForEach-Object {Join-Path $root $_}|Where-Object {Test-Path -LiteralPath $_}|Select-Object -First 1
if(!$compose){throw 'Nie znaleziono pliku Compose.'}
if(!(Test-Path -LiteralPath (Join-Path $root 'custom/puchatyzakatek/img/logo.jpg'))){throw 'Nie znaleziono logo modulu.'}
$original=[IO.File]::ReadAllText($compose)
$mount='      - ./custom/.pz-core/login.tpl.php:/var/www/html/core/tpl/login.tpl.php:ro'
if($original.Contains('/var/www/html/core/tpl/login.tpl.php') -and !$original.Contains($mount.Trim())){throw 'Istnieje inne montowanie szablonu logowania. Przerwano bez zmian.'}
$next=$original
if(!$next.Contains($mount.Trim())){
 $anchor='(?m)^([ \t]*-[ \t]+\./custom:/var/www/html/custom)[ \t]*\r?$'
 if([regex]::Matches($next,$anchor).Count -ne 1){throw 'Nieznany uklad wolumenow Compose.'}
 $next=[regex]::Replace($next,$anchor,{param($m) $m.Groups[1].Value+"`n"+$mount})
}
$tag=[guid]::NewGuid().ToString('N');$remote='/tmp/pz-login-'+$tag
$backup=Join-Path $root ('backup/login-'+$tag)
$target=Join-Path $root 'custom/.pz-core/login.tpl.php'
New-Item -ItemType Directory -Force -Path $backup,(Split-Path $target)|Out-Null
Copy-Item -LiteralPath $compose -Destination (Join-Path $backup 'compose.original')
$existed=Test-Path -LiteralPath $target
if($existed){Copy-Item -LiteralPath $target -Destination (Join-Path $backup 'login.mount.original')}
$changed=$false
try {
 Run-Docker -DockerArgs @('cp',($Container+':/var/www/html/core/tpl/login.tpl.php'),(Join-Path $backup 'login.tpl.php'))|Out-Null
 Run-Docker -DockerArgs @('cp',(Join-Path $PSScriptRoot 'patch-login.php'),($Container+':'+$remote+'.php'))|Out-Null
 Run-Docker -DockerArgs @('exec',$Container,'php',($remote+'.php'),'/var/www/html/core/tpl/login.tpl.php',($remote+'.tpl.php'))|Out-Null
 Run-Docker -DockerArgs @('exec',$Container,'php','-l',($remote+'.tpl.php'))|Write-Host
 $changed=$true
 Run-Docker -DockerArgs @('cp',($Container+':'+$remote+'.tpl.php'),$target)|Out-Null
 [IO.File]::WriteAllText($compose,$next,[System.Text.UTF8Encoding]::new($false))
 Run-Docker -DockerArgs @('compose','-f',$compose,'config','--quiet')|Out-Null
 Run-Docker -DockerArgs @('compose','-f',$compose,'up','-d','--no-deps','--force-recreate','dolibarr')|Write-Host
 Write-Host "GOTOWE: logo Puchatego Zakatka na logowaniu. Odswiez Ctrl+F5. Kopia: $backup"
}catch{
 if($changed){Copy-Item -LiteralPath (Join-Path $backup 'compose.original') -Destination $compose -Force;if($existed){Copy-Item -LiteralPath (Join-Path $backup 'login.mount.original') -Destination $target -Force};try{Run-Docker -DockerArgs @('compose','-f',$compose,'up','-d','--no-deps','--force-recreate','dolibarr')|Out-Null}catch{Write-Warning 'Sprawdz uruchomienie kontenera po przywroceniu Compose.'}}
 throw
}
