<?php
// Module permissions never grant Dolibarr administrator privileges.
function pz_can_manage() {
    global $user;
    return !empty($user->admin) || $user->hasRight('puchatyzakatek', 'manage');
}

function pz_route_allowed($path, $root = '') {
    $allowed = array('/custom/puchatyzakatek/index.php', '/custom/puchatyzakatek/api.php', '/custom/puchatyzakatek/document.php',
        '/custom/puchatyzakatek/logout.php', '/user/logout.php', '/custom/puchatyzakatek/password.php');
    return in_array($path, array_map(function ($p) use ($root) { return $root.$p; }, $allowed), true);
}

function pz_enforce_route() {
    global $db, $user, $conf;
    if (PHP_SAPI === 'cli') return;
    $login = (string) ($_SESSION['dol_login'] ?? '');
    if ($login === '') return;
    // Resolve the authenticated session, not a target user loaded by a page.
    $q = $db->query("SELECT u.rowid FROM ".MAIN_DB_PREFIX."user u INNER JOIN ".MAIN_DB_PREFIX."user_param p ON p.fk_user=u.rowid WHERE u.login='".$db->escape($login)."' AND u.entity IN (0,".(int)$conf->entity.") AND p.param='PZ_ONLY_MODULE' AND p.value='1'");
    if (!$q) { http_response_code(503); exit('Nie można sprawdzić uprawnień.'); }
    if (!$db->fetch_object($q)) return;
    $path = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if (pz_route_allowed($path, DOL_URL_ROOT)) return;
    // CSS and login assets contain no business actions.
    if (preg_match('~^'.preg_quote(DOL_URL_ROOT, '~').'/theme/[a-zA-Z0-9_-]+/style\.css\.php$~D', $path)) return;
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $path === DOL_URL_ROOT.'/index.php') {
        header('Location: '.DOL_URL_ROOT.'/custom/puchatyzakatek/index.php', true, 303); exit;
    }
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    exit('To konto służy wyłącznie do obsługi Puchatego Zakątka. <a href="'.htmlspecialchars(DOL_URL_ROOT.'/custom/puchatyzakatek/index.php', ENT_QUOTES, 'UTF-8').'">Wróć do salonu</a>');
}
