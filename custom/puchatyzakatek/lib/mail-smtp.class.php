<?php
require_once DOL_DOCUMENT_ROOT.'/core/class/smtps.class.php';
class PzMimeSMTP extends SMTPs {
    public $pzMime='';
    public function getBodyContent(){return $this->pzMime;}
}
