<?php
if(!defined('DOL_DOCUMENT_ROOT'))exit;
function pz_backup_script($restore=false) {
    global $user;if(empty($user->admin))throw new InvalidArgumentException('Kopie całej bazy wymagają administratora.');
    $script= <<<'PS'
$ErrorActionPreference='Stop'
[Console]::OutputEncoding=New-Object System.Text.UTF8Encoding($false)
$utf8=New-Object System.Text.UTF8Encoding($false)
function Run-Docker {param([string[]]$ArgList) & docker @ArgList; if($LASTEXITCODE -ne 0){throw "Docker: $($ArgList[0]) zakończony błędem $LASTEXITCODE"}}
$backupRoot=Join-Path $env:USERPROFILE 'Documents\Puchaty-kopie'
New-Item -ItemType Directory -Path $backupRoot -Force|Out-Null
$tag=[Guid]::NewGuid().ToString('N')
$shellLocal=Join-Path ([IO.Path]::GetTempPath()) ('pz-backup-'+$tag+'.sh')
$shellRemote='/tmp/pz-backup-'+$tag+'.sh'
$dumpRemote='/tmp/pz-backup-'+$tag+'.sql'
$shell=@'
#!/bin/sh
set -eu
pw="${MARIADB_ROOT_PASSWORD:-${MYSQL_ROOT_PASSWORD:-}}"
test -n "$pw" || { echo "Brak hasla administratora MariaDB w konfiguracji kontenera" >&2; exit 1; }
export MYSQL_PWD="$pw"
database="${MARIADB_DATABASE:-${MYSQL_DATABASE:-dolibarr}}"
if [ "$1" = backup ]; then
 mariadb-dump --user=root --single-transaction --routines --triggers --databases "$database" > "$2"
 test -s "$2"
else
 mariadb --user=root < "$2"
fi
'@
[IO.File]::WriteAllText($shellLocal,$shell.Replace("`r`n","`n"),$utf8)
$sqlInput=$null
$stopped=$false
try {
 Run-Docker -ArgList @('cp',$shellLocal,('puchaty-mariadb:'+$shellRemote))
 if($restoreMode){
  $chosen=Read-Host 'Pelna sciezka do wlasnej kopii SQL do przywrocenia'
  $sqlInput=(Resolve-Path -LiteralPath $chosen).Path
  if([IO.Path]::GetExtension($sqlInput) -ne '.sql'){throw 'Wybierz plik .sql'}
  if((Get-Item -LiteralPath $sqlInput).Length -eq 0){throw 'Kopia jest pusta'}
  Write-Host "Przywrocenie zastapi cala baze Dolibarra, w tym konta, wizyty i pozostale moduly. Plik: $sqlInput"
  $answer=Read-Host 'Aby kontynuowac wpisz PRZYWROC CALA BAZE'
  if($answer -cne 'PRZYWROC CALA BAZE'){throw 'Przywracanie anulowane'}
 }
 if($restoreMode){Run-Docker -ArgList @('stop','puchaty-dolibarr');$stopped=$true}
 $name=if($restoreMode){'przed-przywroceniem-'}else{'puchaty-'}
 $output=Join-Path $backupRoot ($name+(Get-Date -Format 'yyyyMMdd-HHmmss')+'-'+$tag.Substring(0,6)+'.sql')
 Run-Docker -ArgList @('exec','puchaty-mariadb','sh',$shellRemote,'backup',$dumpRemote)
 Run-Docker -ArgList @('cp',('puchaty-mariadb:'+$dumpRemote),$output)
 if((Get-Item -LiteralPath $output).Length -le 0){throw 'Brak poprawnej kopii. Przerwano.'}
 Write-Host "Kopia zapisana: $output"
 if($restoreMode){
  # Stop writes during restore; the database container stays up.

  try {
   Run-Docker -ArgList @('cp',$sqlInput,('puchaty-mariadb:'+$dumpRemote))
   Run-Docker -ArgList @('exec','puchaty-mariadb','sh',$shellRemote,'restore',$dumpRemote)
  }finally{Run-Docker -ArgList @('start','puchaty-dolibarr');$stopped=$false}
  Write-Host 'Baza przywrocona. Zaloguj sie ponownie. W razie bledu uzyj kopii przed-przywroceniem.'
 }
}finally{
 if($stopped){Run-Docker -ArgList @('start','puchaty-dolibarr')}
 if(Test-Path -LiteralPath $shellLocal){Remove-Item -LiteralPath $shellLocal}
 & docker exec puchaty-mariadb rm -f -- $shellRemote $dumpRemote
}
PS;
    return array('name'=>$restore?'przywroc-puchaty.ps1':'kopia-puchaty.ps1','script'=>"\xEF\xBB\xBF".'$restoreMode='.($restore?'$true':'$false')."\n".$script);
}
