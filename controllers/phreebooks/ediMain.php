<?php
/*
 * @name Bizuno ERP - Pro EDI extension - EDI Main
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
 * @filesource /controllers/phreebooks/ediMain.php
 *
 * Handles specs:
 *  810 - Invoice
 *  850 - Purchase Order
 *  855 - PO Confirm
 *  856 - Shipment Confirm and Tracking #
 *  997 - Acknowledgment
 */

namespace bizuno;

class phreebooksEdiMain extends mgrJournal
{
    public    $moduleID  = 'phreebooks';
    public    $pageID    = 'ediMain';
    protected $domSuffix = 'Edi';
    protected $metaPrefix= 'edi_spec_'; // then append spec number
    protected $secID     = 'edi';
    protected $nextRefIdx= 'next_edi_num';
    protected $journalID = 10; // post to sales order, pending review

    function __construct()
    {
        parent::__construct();
        $this->statuses = [['id'=>'','text'=>lang('all')],['id'=>'A','text'=>'Accepted'],['id'=>'E','text'=>lang('error')]];
        $this->specs    = [['id'=>0, 'text'=>lang('all')],['id'=>'810','text'=>'810: Invoice'],['id'=>'850','text'=>'850: Purchase Order'],
            ['id'=>'855','text'=>'855: PO Acknowledge'],['id'=>'856','text'=>'856: Ship Confirmation']];
        $this->managerSettings();
        $this->fieldStructure();
    }

    private function fieldStructure()
    {
        $this->struc = [
            '_rID'       => ['panel'=>'general', 'order'=> 1,                                     'clean'=>'integer', 'attr'=>['type'=>'hidden',  'value'=>0]],
            'edi_source' => ['panel'=>'general', 'order'=>10,'label'=>$this->lang['edi_ed_title'],'clean'=>'text',    'attr'=>['type'=>'text',    'value'=>'', 'readonly'=>true]],
            'spec'       => ['panel'=>'general', 'order'=>20,'label'=>$this->lang['edi_spec'],    'clean'=>'integer', 'attr'=>['type'=>'text',    'value'=>'', 'readonly'=>true],'values'=>$this->specs],
            'status'     => ['panel'=>'general', 'order'=>30,'label'=>lang('status'),             'clean'=>'db_field','attr'=>['type'=>'select',  'value'=>'', 'readonly'=>true],'values'=>$this->statuses],
            'edi_date'   => ['panel'=>'general', 'order'=>40,'label'=>lang('post_date'),          'clean'=>'date',    'attr'=>['type'=>'date',    'value'=>'', 'readonly'=>true]],
            'control_num'=> ['panel'=>'general', 'order'=>50,'label'=>$this->lang['edi_ctl_num'], 'clean'=>'integer', 'attr'=>['type'=>'text',    'value'=>'', 'readonly'=>true]],
            'main_id'    => ['panel'=>'general', 'order'=>60,'label'=>$this->lang['edi_jrnl_id'], 'clean'=>'db_field','attr'=>['type'=>'text',    'value'=>'', 'readonly'=>true]],
            'ack_date'   => ['panel'=>'general', 'order'=>70,'label'=>lang('edi_ack_date'),       'clean'=>'date',    'attr'=>['type'=>'date',    'value'=>'', 'readonly'=>true]],
            'edi_name'   => ['panel'=>'edi_data','order'=>10,'label'=>$this->lang['edi_filename'],'clean'=>'filename','attr'=>['type'=>'text',    'value'=>'', 'readonly'=>true]],
            'edi_data'   => ['panel'=>'edi_data','order'=>20,'label'=>$this->lang['edi_req'],     'clean'=>'text',    'attr'=>['type'=>'textarea','value'=>'', 'readonly'=>true]],
            'ack_name'   => ['panel'=>'ack_data','order'=>10,'label'=>$this->lang['ack_filename'],'clean'=>'filename','attr'=>['type'=>'text',    'value'=>'', 'readonly'=>true]],
            'ack_data'   => ['panel'=>'ack_data','order'=>20,'label'=>$this->lang['edi_ack'],     'clean'=>'text',    'attr'=>['type'=>'textarea','value'=>'', 'readonly'=>true]]];
    }
    protected function managerGrid($security=0, $args=[])
    {
        $dateRange= dbSqlDates($this->defaults['period']);
        $sqlPeriod= $dateRange['sql'];
        $stores   = getModuleCache('bizuno', 'stores');
        $data     = array_replace_recursive(parent::gridBase($security, $args), ['metaTable'=>'journal',
            'attr'   => ['url'=>BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/managerRows"],
            'source' => [
                'tables' => ['journal_meta'=>['table'=>BIZUNO_DB_PREFIX.'journal_meta','join'=>'JOIN','links'=>BIZUNO_DB_PREFIX."journal_main.id=".BIZUNO_DB_PREFIX."journal_meta.ref_id"]],
                'search' => ['edi_source', 'spec', 'control_num', 'main_id'],
                'filters'=> [
                    'metaKey' => ['order'=> 1,'break'=>true,'sql'=>BIZUNO_DB_PREFIX."journal_meta.meta_key LIKE '{$this->metaPrefix}%'", 'hidden'=>true], // m.meta_key LIKE 'shipment_%'
//                    'status'  => ['order'=>40,'break'=>true,'sql'=>BIZUNO_DB_PREFIX."journal_meta.status='{$this->defaults['status']}'",'label'=>lang('status'),
//                        'values'=>$this->statuses,'attr'=>['type'=>'select','value'=>$this->defaults['status']]],
                    'period'  => ['order'=>10,'break'=>true,'label'=>lang('period'), 'options'=>['width'=>300],'sql'=>$sqlPeriod,
                        'values'=>viewKeyDropdown(localeDates(true, true, true, true, true)),'attr'=>['type'=>'select','value'=>$this->defaults['period']]],
                    'spec'    => ['order'=>20,'break'=>true,'label'=>$this->lang['edi_spec'],
                        'values'=>$this->specs, 'attr'=>['type'=>'select','value'=>$this->defaults['spec']]],
                    'store_id'=> ['order'=>30,'break'=>true,'label'=>lang('ctype_b'),'sql'=>($this->defaults['store_id']<>-1 ? BIZUNO_DB_PREFIX."journal_main.store_id={$this->defaults['store_id']}" : ''),
                        'values'=>viewStores(),'attr'=>['type'=>sizeof($stores)>1?'select':'hidden','value'=>$this->defaults['store_id']]]]],
            'columns'=> [
                'id'     => ['order'=>0, 'field'=>'DISTINCT '.BIZUNO_DB_PREFIX.'journal_main.id','attr'=>['hidden'=>true]], // need to override gridBase
                'action' => [
                    'actions'=> [
                        'view'  => ['order'=>30,'icon'=>'search','label'=>lang('view'),
                            'events'=> ['onClick' => "jsonAction('$this->moduleID/main/view', idTBD);"], //
                        'sales' => ['order'=>50,'icon'=>'sales', 'label'=>lang('fill_sale'), 'display'=> "row.spec=='850'",
                            'events'=> ['onClick' => "jsonAction('$this->moduleID/main/ediManual()', idTBD);"]]]]],
                'edi_source' => ['order'=>10,'field'=>'journal_meta.id','label'=>$this->lang['edi_ed_title'],'attr'=>['width'=>100, 'sortable'=>true, 'resizable'=>true],'process'=>'meta:edi_source:journal'],
                'edi_date'   => ['order'=>60,'field'=>'journal_meta.id','label'=>$this->lang['edi_rcv_date'],'attr'=>['width'=>100, 'type'=>'date', 'sortable'=>true, 'resizable'=>true],'process'=>'meta:edi_date:journal','format'=>'date'],
                'ack_date'   => ['order'=>70,'field'=>'journal_meta.id','label'=>$this->lang['edi_ack_date'],'attr'=>['width'=>100, 'type'=>'date', 'sortable'=>true, 'resizable'=>true],'process'=>'meta:ack_date:journal','format'=>'date'],
                'store_id'   => ['order'=>20,'field'=>'store_id',       'label'=>lang('store_id'),           'attr'=>['width'=>100, 'sortable'=>true, 'resizable'=>true],'format'=> 'storeID'],
                'spec'       => ['order'=>30,'field'=>'journal_meta.id','label'=>$this->lang['edi_spec'],    'attr'=>['width'=>100, 'sortable'=>true, 'resizable'=>true],'process'=>'meta:spec:journal'],
                'invoice_num'=> ['order'=>50,'field'=>'invoice_num',    'label'=>lang('invoice_num_12'),     'attr'=>['width'=>100, 'sortable'=>true, 'resizable'=>true]],
                'control_num'=> ['order'=>30,'field'=>'journal_meta.id','label'=>$this->lang['edi_ctl_num'], 'attr'=>['width'=>100, 'sortable'=>true, 'resizable'=>true],'process'=>'meta:control_num:journal'],
                'spec'       => ['order'=>30,'field'=>'journal_meta.id','label'=>$this->lang['edi_spec'],    'attr'=>['width'=>100, 'sortable'=>true, 'resizable'=>true],'process'=>'meta:spec:journal']]]);
        if ($GLOBALS['myDevice'] == 'mobile') {
            $data['columns']['ack_date']['attr']['hidden']   = true;
            $data['columns']['control_num']['attr']['hidden']= true;
            $data['columns']['main_id']['attr']['hidden']    = true;
        }
        return $data;
    }
    protected function managerSettings()
    {
        parent::managerDefaults();
        $this->defaults['sort']    = clean('sort',    ['format'=>'cmd',     'default'=>'post_date'],'post');
        $this->defaults['status']  = clean('status',  ['format'=>'db_field','default'=>''],         'post');
        $this->defaults['spec']    = clean('spec',    ['format'=>'integer', 'default'=>0],          'post');
        $this->defaults['period']  = clean('period',  ['format'=>'cmd',     'default'=>getUserCache('profile', 'def_periods', '', 'l')], 'post');
        $this->defaults['store_id']= clean('store_id',['format'=>'integer', 'default'=>-1],         'post');
    }

    /******************************** Journal Manager ********************************/
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $args = ['title'=>$this->lang['edi_title'], 'type'=>'journal'];
        parent::managerMain($layout, $security, $args);
        unset($layout['datagrid']["dg{$this->domSuffix}"]['source']['actions']['new']);
        unset($layout['datagrid']["dg{$this->domSuffix}"]['columns']['action']['actions']['copy']);
    }
    public function managerRows(&$layout=[])
    {
        msgTrap();
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $grid = $this->managerGrid($security, ['refID'=>'%', 'type'=>'journal']);
        $layout = array_replace_recursive($layout, ['type'=>'datagrid','key'=>"dg{$this->domSuffix}",'datagrid'=>["dg{$this->domSuffix}"=>$grid]]);
    }
    public function edit(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 2)) { return; }
        $refID= $_GET['refID']= clean('rID', 'integer', 'get'); // map journal_main.id to refID
        $meta = dbMetaGet(0, $this->metaPrefix, 'journal', $refID);
//        $rID  = $_GET['rID']  = metaIdxClean($meta);
        $args = ['_table'=>'journal', 'title'=>$this->lang['view_edi_record']];
        parent::editMeta($layout, $security, $args);
        unset($layout['toolbars']["tb{$this->domSuffix}"]['icons']['save'],
              $layout['toolbars']["tb{$this->domSuffix}"]['icons']['new'],
              $layout['toolbars']["tb{$this->domSuffix}"]['icons']['copy']);
    }
    public function view()
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $rID = clean('rID', 'integer', 'get');
        if (empty($rID)) { return msgAdd('Bad ID!'); }
        $data = dbMetaGet($rID, $this->metaPrefix, 'journal');
        // @TODO - get the separator based on the source
        msgAdd("EDI Source sent {$data['edi_date']}: ".str_replace('~', "~<br />\n", $data['edi_data']), 'info');
    }
    public function save()
    {
        return msgAdd("EDI records cannot be edited or saved from this manager!", 'info');
    }
    public function delete(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        $args = ['_table'=>'journal'];
        parent::deleteMeta($layout, $args);
    }
}
