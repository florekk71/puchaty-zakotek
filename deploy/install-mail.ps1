param([string]$ProjectRoot='C:\PuchatyZakatek',[string]$Container='puchaty-dolibarr')
$ErrorActionPreference='Stop'
function Run-Docker {
 param([string[]]$DockerArgs)
 $dockerCommand=Get-Command docker -CommandType Application -ErrorAction Stop | Select-Object -First 1
 $savedPreference=$ErrorActionPreference
 try {
  # Docker Compose writes progress to stderr even when the command succeeds.
  # Windows PowerShell 5.1 must not throw before LASTEXITCODE is captured.
  $ErrorActionPreference='Continue'
  $PSNativeCommandUseErrorActionPreference=$false
  $output=& $dockerCommand.Source @DockerArgs 2>&1
  $exitCode=$LASTEXITCODE
 } finally { $ErrorActionPreference=$savedPreference }
 if($exitCode -ne 0){throw ("Docker zakonczyl polecenie bledem (kod {0}): {1}" -f $exitCode,($output -join "`n"))}
 return ($output -join "`n")
}
$root=(Resolve-Path -LiteralPath $ProjectRoot).Path
$compose=@('compose.yml','docker-compose.yml')|ForEach-Object {Join-Path $root $_}|Where-Object {Test-Path -LiteralPath $_}|Select-Object -First 1
if(!$compose){throw 'Nie znaleziono Compose.'}
if(!(Test-Path (Join-Path $root 'custom/puchatyzakatek/scripts/mail-worker.php'))){throw 'Najpierw zsynchronizuj modul z GitHubem.'}
$config=Join-Path $root 'mail-config.ini'
if(!(Test-Path -LiteralPath $config)){Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'mail-config.example.ini') -Destination $config}
$original=[IO.File]::ReadAllText($compose)
$mount='      - ./mail-config.ini:/run/secrets/pz-mail.ini:ro'
$next=$original
if(!$next.Contains($mount.Trim())){
 if($next.Contains('/run/secrets/pz-mail.ini')){throw 'Inne montowanie poczty. Przerwano bez zmian.'}
 $anchor='(?m)^([ \t]*-[ \t]+\./custom:/var/www/html/custom)[ \t]*\r?$'
 if([regex]::Matches($next,$anchor).Count -ne 1){throw 'Nieznany uklad Compose.'}
 $next=[regex]::Replace($next,$anchor,{param($m) $m.Groups[1].Value+"`n"+$mount})
}
$backup=Join-Path $root ('backup/mail-'+[guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $backup|Out-Null
Copy-Item -LiteralPath $compose -Destination (Join-Path $backup 'compose.original')
try {
 [IO.File]::WriteAllText($compose,$next,[Text.UTF8Encoding]::new($false))
 Run-Docker -DockerArgs @('compose','-f',$compose,'config','--quiet')|Out-Null
 Run-Docker -DockerArgs @('compose','-f',$compose,'up','-d','--no-deps','--force-recreate','dolibarr')|Write-Host
}catch{
 Copy-Item -LiteralPath (Join-Path $backup 'compose.original') -Destination $compose -Force
 throw
}
$dockerExe=(Get-Command docker -CommandType Application|Select-Object -First 1).Source
$runner=Join-Path $root 'pz-mail-worker.ps1'
$runnerCode=@'
$ErrorActionPreference='Continue'
$PSNativeCommandUseErrorActionPreference=$false
& '__DOCKER__' exec '__CONTAINER__' php /var/www/html/custom/puchatyzakatek/scripts/mail-worker.php run 2>&1 | Out-File -LiteralPath (Join-Path $PSScriptRoot 'pz-mail-worker.log') -Encoding utf8
exit $LASTEXITCODE
'@
$runnerCode=$runnerCode.Replace('__DOCKER__',$dockerExe.Replace("'","''")).Replace('__CONTAINER__',$Container.Replace("'","''"))
[IO.File]::WriteAllText($runner,$runnerCode,[Text.UTF8Encoding]::new($false))
$action=New-ScheduledTaskAction -Execute 'powershell.exe' -Argument ('-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File "'+$runner+'"')
$trigger=New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$principal=New-ScheduledTaskPrincipal -UserId ([Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType Interactive -RunLevel Limited
$settings=New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 5) -StartWhenAvailable -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
Register-ScheduledTask -TaskName ('Puchaty-poczta-'+$Container) -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Force|Out-Null
Write-Host "GOTOWE. Konfiguracja: $config. Zadanie co minute, gdy uzytkownik Windows jest zalogowany."
Write-Host 'Uzupelnij SMTP i test_recipient. Po tescie ustaw enabled=true i wybrane rodzaje wiadomosci=true.'
Write-Host 'Po zmianie pliku INI uruchom instalator ponownie, aby odswiezyc montowanie w Dockerze.'

