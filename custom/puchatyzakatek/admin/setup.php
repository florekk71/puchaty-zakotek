<?php

require '../../../main.inc.php';

$langs->loadLangs(array('admin', 'puchatyzakatek@puchatyzakatek'));

if (!$user->admin) {
    accessforbidden();
}

llxHeader('', 'Puchaty Zakątek - konfiguracja');

print load_fiche_titre('Konfiguracja Puchaty Zakątek');

print '<p>Ustawienia salonu, cennik i przygotowanie bazy są dostępne w module.</p>';
print '<p><a class="butAction" href="'.dol_buildpath('/puchatyzakatek/index.php', 1).'">Otwórz Puchaty Zakątek</a></p>';

llxFooter();

$db->close();
