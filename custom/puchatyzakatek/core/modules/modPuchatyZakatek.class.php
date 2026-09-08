<?php

include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modPuchatyZakatek extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;

        $this->numero = 500000;

        $this->rights_class = 'puchatyzakatek';

        $this->family = 'other';

        $this->module_position = 500;

        $this->name = preg_replace('/^mod/i', '', get_class($this));

        $this->description = 'System obsługi salonu groomerskiego Puchaty Zakątek';

        $this->version = '0.5.5';

        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);

        $this->picto = 'paw';

        $this->module_parts = array(
            'css' => array(
                '/puchatyzakatek/css/puchatyzakatek.css'
            ),
            'js' => array(
                '/puchatyzakatek/js/puchatyzakatek.js'
            ),
        );

        $this->dirs = array(
            '/puchatyzakatek/temp'
        );

        $this->config_page_url = array(
            'setup.php@puchatyzakatek'
        );

        $this->depends = array('modSociete');
        $this->requiredby = array();
        $this->conflictwith = array();

        $this->langfiles = array(
            'puchatyzakatek@puchatyzakatek'
        );

        $this->phpmin = array(8, 1);
        $this->need_dolibarr_version = array(20, 0);

        $this->warnings_activation = array();
        $this->warnings_activation_ext = array();

        $this->const = array();

        $this->tabs = array();

        $this->dictionaries = array();

        $this->boxes = array();

        $this->cronjobs = array();

        $this->rights = array();

        $r = 0;

        $this->rights[$r][0] = 500001;
        $this->rights[$r][1] = 'Korzystanie z modułu Puchaty Zakątek';
        $this->rights[$r][4] = 'read';
        $this->rights[$r][5] = '';
        $r++;

        $this->rights[$r][0] = 500002;
        $this->rights[$r][1] = 'Zarządzanie modułem Puchaty Zakątek';
        $this->rights[$r][4] = 'write';
        $this->rights[$r][5] = '';
        $r++;

        $this->rights[$r][0] = 500003;
        $this->rights[$r][1] = 'Pełna obsługa salonu i wszystkich jego klientów';
        $this->rights[$r][4] = 'manage';
        $this->rights[$r][5] = '';
        $r++;
        $this->menu = array();

        $r = 0;

        $this->menu[$r++] = array(
            'fk_menu' => '',
            'type' => 'top',
            'titre' => 'Puchaty Zakątek',
            'mainmenu' => 'puchatyzakatek',
            'leftmenu' => '',
            'url' => '/puchatyzakatek/index.php',
            'langs' => 'puchatyzakatek@puchatyzakatek',
            'position' => 100,
            'enabled' => 'isModEnabled("puchatyzakatek")',
            'perms' => '$user->admin || $user->hasRight("puchatyzakatek", "read") || $user->hasRight("puchatyzakatek", "write") || $user->hasRight("puchatyzakatek", "manage")',
            'target' => '',
            'user' => 2
        );
    }

    public function init($options = '')
    {
        $result = $this->_load_tables('/puchatyzakatek/sql/');

        if ($result < 0) {
            return -1;
        }

        $sql = array();

        return $this->_init($sql, $options);
    }

    public function remove($options = '')
    {
        $sql = array();

        return $this->_remove($sql, $options);
    }
}
