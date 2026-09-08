<?php
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security2.lib.php';
if (empty($user->id) || !empty($user->socid)) accessforbidden();
header('Cache-Control: no-store');
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 try {
  $token=(string)($_POST['token']??'');
  if (!$token || !hash_equals((string)($_SESSION['token']??''),$token)) throw new RuntimeException('Sesja formularza wygasła. Wpisz hasła ponownie.');
  $current=$_POST['current_password']??''; $next=$_POST['new_password']??''; $repeat=$_POST['repeat_password']??'';
  if (!is_string($current)||!is_string($next)||!is_string($repeat)) throw new RuntimeException('Nieprawidłowe dane formularza.');
  if (time()<(int)($_SESSION['pz_password_retry_after']??0)) throw new RuntimeException('Odczekaj minutę przed kolejną próbą.');
  $r=$db->query('SELECT pass_crypted FROM '.MAIN_DB_PREFIX.'user WHERE rowid='.(int)$user->id);
  if (!$r) throw new RuntimeException('Nie można sprawdzić hasła. Spróbuj później.');
  $saved=$db->fetch_object($r);
  if (!$saved || !dol_verifyHash($current,$saved->pass_crypted)) {
   $_SESSION['pz_password_failures']=(int)($_SESSION['pz_password_failures']??0)+1;
   if ($_SESSION['pz_password_failures']>=5) {$_SESSION['pz_password_retry_after']=time()+60;$_SESSION['pz_password_failures']=0;}
   throw new RuntimeException('Obecne hasło jest nieprawidłowe.');
  }
  if (mb_strlen($next)<12) throw new RuntimeException('Hasło musi mieć co najmniej 12 znaków.');
  if (strlen($next)>72) throw new RuntimeException('Nowe hasło jest za długie. Skróć je.');
  if (!hash_equals($next,$repeat)) throw new RuntimeException('Nowe hasła nie są takie same.');
  if (dol_verifyHash($next,$saved->pass_crypted)) throw new RuntimeException('Wybierz inne hasło niż dotychczasowe.');
  $result=$user->setPassword($user,$next);
  if (is_int($result) && $result<=0) throw new RuntimeException('Nie udało się zmienić hasła. '.($user->error?:'Spróbuj ponownie.'));
  unset($_SESSION['pz_password_failures'],$_SESSION['pz_password_retry_after']);
  session_regenerate_id(true);
  header('Location: '.DOL_URL_ROOT.'/custom/puchatyzakatek/index.php',true,303); exit;
 } catch (Throwable $e) {$error=$e->getMessage();}
}
function pz_password_escape($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?>
<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Zmiana hasła — Puchaty Zakątek</title>
<style>body{margin:0;background:#fffaf7;color:#443b40;font:16px system-ui,sans-serif;display:grid;min-height:100vh;place-items:center}main{box-sizing:border-box;width:min(94%,460px);padding:30px;background:white;border:1px solid #eadfda;border-radius:24px}h1{font-size:25px;color:#873e55}label{display:block;margin-top:18px}input{box-sizing:border-box;width:100%;padding:12px;margin-top:6px;border:1px solid #d8c4ca;border-radius:12px;font:inherit}button{margin-top:24px;width:100%;padding:13px;border:0;border-radius:12px;background:#873e55;color:white;font:inherit;font-weight:bold}.error{padding:12px;background:#fff0f1;color:#8a2036;border-radius:12px}small{color:#6f6368}</style></head>
<body><main><h1>Puchaty Zakątek</h1><h2>Ustaw własne hasło</h2><p>Konto: <strong><?= pz_password_escape($user->login) ?></strong></p><p>Wpisz obecne hasło tymczasowe i wybierz nowe.</p>
<?php if($error): ?><p class="error" role="alert"><?= pz_password_escape($error) ?></p><?php endif; ?>
<form method="post" action="<?= pz_password_escape(DOL_URL_ROOT.'/custom/puchatyzakatek/password.php') ?>">
<input type="hidden" name="token" value="<?= pz_password_escape(newToken()) ?>">
<label>Obecne hasło<input type="password" name="current_password" autocomplete="current-password" required></label>
<label>Nowe hasło<input type="password" name="new_password" autocomplete="new-password" minlength="12" maxlength="72" required></label><small>Wybierz hasło o długości co najmniej 12 znaków.</small>
<label>Powtórz nowe hasło<input type="password" name="repeat_password" autocomplete="new-password" minlength="12" maxlength="72" required></label>
<button type="submit">Zapisz hasło i przejdź do salonu</button></form></main></body></html>
