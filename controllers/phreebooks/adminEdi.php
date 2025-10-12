<?php
/*
 * @name Bizuno ERP - Bizuno Pro EDI Module
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade Bizuno to newer
 * versions in the future. If you wish to customize Bizuno for your
 * needs please contact PhreeSoft for more information.
 *
 * @name       Bizuno ERP
 * @author     Dave Premo, PhreeSoft <support@phreesoft.com>
 * @copyright  2008-2025, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2025-10-12
 * @filesource /controllers/phreebooks/adminEdi.php
 */

namespace bizuno;

class phreebooksAdminEdi extends mgrJournal
{
    public    $moduleID  = 'phreebooks';
    public    $pageID    = 'adminEdi';
    protected $secID     = 'admin';
    protected $domSuffix = 'admEDI';
    protected $metaPrefix= 'edi_client';

    function __construct()
    {
        parent::__construct();
        $this->managerSettings();
        $this->fieldStructure();
    }
    private function fieldStructure()
    {
//      $stores = getModuleCache('bizuno', 'stores');
        $defs = ['type'=>'c', 'callback'=>''];
        $this->struc = [ // Props panel
            '_rID'    => ['order'=> 1,                                       'clean'=>'integer','attr'=>['type'=>'hidden', 'value'=>0]],
            'cID'     => ['order'=>10,'label'=>lang('rep_id_i'),             'clean'=>'integer','defaults'=>$defs,'attr'=>['type'=>'contact', 'value'=>0]],
//          'store_id'=> ['order'=>30,'label'=>lang('store_id'),'values'=>viewStores(),'attr'=>['type'=>'select','value'=>!empty($values['store_id'])?$values['store_id']:0]],
            'inst'    => ['order'=>20,'html'=>$this->lang['edi_inst'],       'clean'=>'text','attr'=>['type'=>'raw']],
            'cTitle'  => ['order'=>30,'label'=>$this->lang['edi_ed_title'],  'clean'=>'text','attr'=>['value'=>'']],
            'ediID'   => ['order'=>35,'label'=>$this->lang['edi_ed_srcid'],  'clean'=>'text','attr'=>['value'=>'']],
            'sepTag'  => ['order'=>40,'label'=>$this->lang['edi_ed_septag'], 'clean'=>'char','attr'=>['value'=>'~','size'=>3,'maxlength'=>1]],
            'sepSec'  => ['order'=>45,'label'=>$this->lang['edi_ed_sepsec'], 'clean'=>'char','attr'=>['value'=>'*','size'=>3,'maxlength'=>1]],
            'hostName'=> ['order'=>50,'label'=>$this->lang['edi_sftp_host'], 'clean'=>'text','attr'=>['value'=>'']],
            'userName'=> ['order'=>55,'label'=>$this->lang['edi_sftp_name'], 'clean'=>'text','attr'=>['value'=>'']],
            'userPass'=> ['order'=>60,'label'=>$this->lang['edi_sftp_pass'], 'clean'=>'text','attr'=>['value'=>'']],
            'pathPut' => ['order'=>65,'label'=>$this->lang['edi_ed_pathput'],'clean'=>'text','attr'=>['value'=>'']],
            'pathGet' => ['order'=>70,'label'=>$this->lang['edi_ed_pathget'],'clean'=>'text','attr'=>['value'=>'']],
            'rcvrID'  => ['order'=>75,'label'=>$this->lang['edi_ed_my_id'],  'clean'=>'text','attr'=>['value'=>'']]];
    }
    protected function managerGrid($security=0, $args=[])
    {
        $data = array_replace_recursive(parent::gridBase($security, $args), [
            'source' => [
                'search' => ['cTitle', 'hostName', 'userName']],
            'columns'=> [
                'cID'     => ['order'=>0,'attr'=>['hidden'=>true]],
                'cTitle'  => ['order'=>10,'label'=>$this->lang['edi_ed_title'], 'attr'=>['width'=>160,'sortable'=>true,'resizable'=>true]],
                'ediID'   => ['order'=>20,'label'=>$this->lang['edi_ed_srcid'], 'attr'=>['width'=>160,'sortable'=>true,'resizable'=>true]],
                'hostName'=> ['order'=>30,'label'=>$this->lang['edi_sftp_host'],'attr'=>['width'=>160,'sortable'=>true,'resizable'=>true]],
                'userName'=> ['order'=>40,'label'=>$this->lang['edi_sftp_name'],'attr'=>['width'=>160,'sortable'=>true,'resizable'=>true]],
                'rcvrID'  => ['order'=>50,'label'=>$this->lang['edi_ed_my_id'], 'attr'=>['width'=>160,'sortable'=>true,'resizable'=>true]]]]);
        return $data;
    }
    protected function managerSettings()
    {
        parent::managerDefaults();
    }
    /******************************** Common Meta Manager ********************************/
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::managerMain($layout, $security, ['dom'=>'div','title'=>sprintf(lang('tbd_manager'), $this->lang['edi'])]);
    }
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::mgrRowsMeta($layout, $security);
    }
    public function edit(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::editMeta($layout, $security);
        unset($layout['toolbars']["tb{$this->domSuffix}"]['icons']['copy']); // turn off copy
    }
    public function save(&$layout=[])
    {
        $rID = clean('_rID', 'integer', 'post');
        if (!validateAccess($this->secID, empty($rID)?2:3)) { return; }
        parent::saveMeta($layout, $args=['_rID'=>$rID]);
    }
    public function delete(&$layout=[])
    {
        if (!validateAccess($this->secID, 4)) { return; }
        parent::deleteMeta($layout);
    }
}
