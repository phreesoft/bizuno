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
 * @version    7.x Last Update: 2025-04-24
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
    public    $pageID    = 'main';
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
        $data = array_replace_recursive(parent::gridBase($security, $args), ['metaTable'=>'journal',
            'source' => [
                'tables' => [
                    'journal_meta'=>['table'=>BIZUNO_DB_PREFIX.'journal_meta','join'=>'JOIN','links'=>BIZUNO_DB_PREFIX."journal_main.id=".BIZUNO_DB_PREFIX."journal_meta.ref_id"]],
                'search'  => ['edi_source', 'spec', 'control_num', 'main_id'],
                'filters' => [
                    'metaKey'=> ['order'=> 1,'break'=>true,'sql'=>BIZUNO_DB_PREFIX."journal_meta.meta_key LIKE '{$this->metaPrefix}%'", 'hidden'=>true], // m.meta_key LIKE 'shipment_%'
                    'spec'   => ['order'=>30,'break'=>true,'sql'=>BIZUNO_DB_PREFIX."journal_meta.spec='{$this->defaults['spec']}'",'label'=>$this->lang['edi_spec'],
                        'values'=>$this->specs,   'attr'=>['type'=>'select','value'=>$this->defaults['spec']]],
                    'status' => ['order'=>40,'break'=>true,'sql'=>BIZUNO_DB_PREFIX."journal_meta.status='{$this->defaults['status']}'",'label'=>lang('status'),
                        'values'=>$this->statuses,'attr'=>['type'=>'select','value'=>$this->defaults['status']]]]],
            'columns' => [
                'action' => [
                    'actions'=> [
                        'view'  => ['order'=>30,'icon'=>'search','label'=>lang('view'),
                            'events'=> ['onClick' => "jsonAction('$this->moduleID/main/view', idTBD);"]], //
                        'sales' => ['order'=>50,'icon'=>'sales', 'label'=>lang('fill_sale'), 'display'=> "row.spec=='850'",
                            'events'=> ['onClick' => "jsonAction('$this->moduleID/main/ediManual()', idTBD);"]]]],
                'edi_source' => ['order'=>10,'field'=>'edi_source', 'label'=>$this->lang['edi_ed_title'],'attr'=>['width'=>240,'sortable'=>true,'resizable'=>true]],
                'spec'       => ['order'=>20,'field'=>'spec',       'label'=>$this->lang['edi_spec'],    'attr'=>['width'=>100,'sortable'=>true,'resizable'=>true]],
                'edi_date'   => ['order'=>30,'field'=>'edi_date',   'label'=>$this->lang['edi_rcv_date'],'format'=>'datetime','attr'=>['width'=>150,'sortable'=>true,'resizable'=>true]],
                'ack_date'   => ['order'=>40,'field'=>'ack_date',   'label'=>$this->lang['edi_ack_date'],'format'=>'datetime','attr'=>['width'=>150,'sortable'=>true,'resizable'=>true]],
                'status'     => ['order'=>50,'field'=>'status',     'label'=>lang('status'),             'attr'=>['width'=>100,'sortable'=>true,'resizable'=>true]],
                'control_num'=> ['order'=>60,'field'=>'control_num','label'=>$this->lang['edi_ctl_num'], 'attr'=>['width'=>150,'sortable'=>true,'resizable'=>true]],
                'main_id'    => ['order'=>70,'field'=>'main_id',    'label'=>$this->lang['edi_jrnl_id'], 'attr'=>['width'=>150,'sortable'=>true,'resizable'=>true]]]]);
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
        $this->defaults['sort']  = clean('sort',  ['format'=>'date',    'default'=>'edi_date'],'post');
        $this->defaults['spec']  = clean('spec',  ['format'=>'integer', 'default'=>0],         'post');
        $this->defaults['status']= clean('status',['format'=>'db_field','default'=>''],        'post');
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
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $grid = $this->managerGrid($security, ['refID'=>'%', 'type'=>'journal']);
        if ($this->defaults['status']=='') { unset($grid['source']['filters']['status']); } // all statuses
        if (empty($this->defaults['spec'])){ unset($grid['source']['filters']['spec']); } // all specs
        $meta = $this->mgrRowsDBPrep($grid);
        unset($grid['source']['filters']['metaKey'], $grid['source']['filters']['jID'], $grid['source']['filters']['period']);
        $this->mgrRowsDBFltr($layout, $grid, $meta);
    }
    public function edit(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 2)) { return; }
        $args = ['_table'=>'journal', 'title'=>'view_edi_record'];
        parent::editMeta($layout, $security, $args);
        unset($layout['toolbars']["tb{$this->domSuffix}"]['icons']['save'], $layout['toolbars']["tb{$this->domSuffix}"]['icons']['new'],
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
