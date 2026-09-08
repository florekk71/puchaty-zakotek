<?php
require '../../main.inc.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($user->id)
    || empty($_POST['token']) || !hash_equals((string) ($_SESSION['token'] ?? ''), (string) $_POST['token'])) {
    http_response_code(403); exit('Odśwież stronę przed zmianą użytkownika.');
}
if (method_exists($user, 'call_trigger')) $user->call_trigger('USER_LOGOUT', $user);
$_SESSION = array();
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', array('expires'=>time()-3600, 'path'=>$p['path'],
        'domain'=>$p['domain'], 'secure'=>$p['secure'], 'httponly'=>true, 'samesite'=>$p['samesite'] ?: 'Lax'));
}
session_destroy();
header('Cache-Control: no-store');
header('Location: '.DOL_URL_ROOT.'/custom/puchatyzakatek/index.php', true, 303);
exit;
