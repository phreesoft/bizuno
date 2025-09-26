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
 * @version    7.x Last Update: 2025-09-24
 * @filesource /controllers/phreebooks/adminEdi.php
 */

// NEEDS TO BE MODIFIED TO NEW MANAGER ARCHITECTURE






namespace bizuno;

class phreebooksAdminEdi
{
    public $moduleID= 'phreebooks';
    public $pageID  = 'adminEdi';

    function __construct()
    {
        $this->lang = getLang($this->moduleID);
    }
    public function settingsStructure()
    {
        $data = [
            'edi' => ['order'=>50,'label'=>$this->lang['edi'],'fields'=>[
                'email_to'   => ['attr'=>['value'=>'sales@myBusiness.com']],
                'email_error'=> ['attr'=>['value'=>'support@myBusiness.com']],
                'sku_cross'  => ['attr'=>['value'=>'PART-TBD']],
                'commodity'  => ['attr'=>['value'=>'']]]]];
        settingsFill($data, $this->moduleID);
        return $data;
    }
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess('admin', 1)) { return; }
        $layout = array_replace_recursive($layout,  ['type'=>'divHTML',
            'divs'     => ['ediClients'=>['order'=>50,'type'=>'accordion','key'=>'accClients']],
            'accordion'=> ['accClients'=>['divs'=>[
                'manager'=>['order'=>30,'label'=>$this->lang['edi'],'type'=>'datagrid','key' =>'clients'],
                'details'=>['order'=>70,'label'=>lang('details'),   'type'=>'html',    'html'=>'&nbsp;']]]],
            'datagrid' => ['clients'=>$this->dgClients('dgClients', $security)]]);
        msgDebug("\nLayout is now: ".print_r($layout, true));
    }
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess('admin', 1)) { return; }
        $creds  = getMetaCommon('edi');
        msgDebug("\nRetrieved creds from meta = ".print_r($creds, true));

        $clients= getModuleCache($this->moduleID, 'clients');
        msgDebug("\nread client list: ".print_r($clients, true));
        $layout = array_replace_recursive($layout, ['content'=>['total'=>sizeof($creds),'rows'=>array_values($creds)]]);
    }
    private function managerSettings()
    {
        $data = ['path'=>'EDI', 'values' => [
            ['index'=>'rows',  'clean'=>'integer','default'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows')],
            ['index'=>'page',  'clean'=>'integer','default'=>1],
            ['index'=>'sort',  'clean'=>'text',   'default'=>''],
            ['index'=>'order', 'clean'=>'text',   'default'=>'ASC'],
            ['index'=>'search','clean'=>'text',   'default'=>'']]];
        $this->defaults = updateSelection($data);
    }
    public function edit(&$layout=[])
    {
        $cID   = clean('rID', 'integer', 'get');
        if (!$security = validateAccess('admin', $cID?3:2)) { return; }
        $name  = $cID ? dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'primary_name', "id=$cID") : '';
        $values= getModuleCache($this->moduleID, 'clients', "C$cID");
        $defs  = ['type'=>'c', 'data'=>'ediContact', 'callback'=>''];
        $fields= [
            'cID'      => ['order'=>10,'label'=>lang('rep_id_i'),'defaults'=>$defs,'attr'=>['type'=>'contact','value'=>$cID]],
            'store_id' => ['order'=>30,'label'=>lang('store_id'),'values'=>viewStores(),'attr'=>['type'=>'select','value'=>!empty($values['store_id'])?$values['store_id']:0]],
            'inst'     => ['order'=>20,'html'=>$this->lang['edi_inst'],       'attr'=>['type'=>'raw']],
            'cTitle'   => ['order'=>30,'label'=>$this->lang['edi_ed_title'],  'attr'=>['value'=>!empty($values['cTitle'])  ?$values['cTitle']  :'']],
            'ediID'    => ['order'=>35,'label'=>$this->lang['edi_ed_srcid'],  'attr'=>['value'=>!empty($values['ediID'])   ?$values['ediID']   :'']],
            'sepTag'   => ['order'=>40,'label'=>$this->lang['edi_ed_septag'], 'attr'=>['value'=>!empty($values['sepTag'])  ?$values['sepTag']  :'~','size'=>3,'maxlength'=>1]],
            'sepSec'   => ['order'=>45,'label'=>$this->lang['edi_ed_sepsec'], 'attr'=>['value'=>!empty($values['sepSec'])  ?$values['sepSec']  :'*','size'=>3,'maxlength'=>1]],
            'hostName' => ['order'=>50,'label'=>$this->lang['edi_sftp_host'], 'attr'=>['value'=>!empty($values['hostName'])?$values['hostName']:'']],
            'userName' => ['order'=>55,'label'=>$this->lang['edi_sftp_name'], 'attr'=>['value'=>!empty($values['userName'])?$values['userName']:'']],
            'userPass' => ['order'=>60,'label'=>$this->lang['edi_sftp_pass'], 'attr'=>['value'=>!empty($values['userPass'])?$values['userPass']:'']],
            'pathPut'  => ['order'=>65,'label'=>$this->lang['edi_ed_pathput'],'attr'=>['value'=>!empty($values['pathPut']) ?$values['pathPut'] :'']],
            'pathGet'  => ['order'=>70,'label'=>$this->lang['edi_ed_pathget'],'attr'=>['value'=>!empty($values['pathGet']) ?$values['pathGet'] :'']],
            'rcvrID'   => ['order'=>75,'label'=>$this->lang['edi_ed_my_id'],  'attr'=>['value'=>!empty($values['rcvrID'])  ?$values['rcvrID']  :'']]];
        $data = ['type'=>'divHTML',
            'divs'    => [
                'toolbar'=>['order'=>10,'type'=>'toolbar','key'=>'tbEDI'],
                'body'   =>['order'=>50,'type'=>'divs','divs'=>[
                    'formBOF' => ['order'=>15,'type'=>'form',  'key' =>"frmClients"],
                    'body'    => ['order'=>50,'type'=>'fields','keys'=>array_keys($fields)],
                    'formEOF' => ['order'=>95,'type'=>'html',  'html'=>"</form>"]]]],
            'toolbars'=> ['tbEDI'=>['icons'=>[
                'save'=> ['order'=>20,'events'=>['onClick'=>"jqBiz('#frmClients').submit();"]],
                'new' => ['order'=>40,'events'=>['onClick'=>"accordionEdit('accClients','dgClients','details','".jsLang('details')."', '$this->moduleID/$this->pageID/edit', 0);"]]]]],
            'forms'   => ['frmClients'=>['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=$this->moduleID/admin/save"]]],
            'fields'  => $fields,
            'jsHead'  => ['init'=>"var ediContact = ".json_encode([['id'=>$cID, 'primary_name'=>$name]]).";"],
            'jsReady' => ['init'=>"ajaxForm('frmClients');"]];
        $layout = array_replace_recursive($layout, $data);
    }
    public function save(&$layout=[])
    {
        $cID    = clean('cID','integer', 'post');
        if (empty($cID)) { return msgAdd("No contact was selected, this is a required field!"); }
        $clients= getModuleCache($this->moduleID, 'clients');
        $clients["C$cID"] = [
            'cID'     => $cID,
            'store_id'=> clean('store_id','integer',  'post'),
            'cTitle'  => clean('cTitle',  'filename', 'post'),
            'ediID'   => clean('ediID',   'filename', 'post'),
            'sepTag'  => clean('sepTag',  'char',     'post'),
            'sepSec'  => clean('sepSec',  'char',     'post'),
            'hostName'=> clean('hostName','filename', 'post'),
            'userName'=> clean('userName','filename', 'post'),
            'userPass'=> clean('userPass','filename', 'post'),
            'pathPut' => clean('pathPut', 'filename', 'post'),
            'pathGet' => clean('pathGet', 'filename', 'post'),
            'rcvrID'  => clean('rcvrID',  'filename', 'post')];
        msgDebug("\n  writing values = ".print_r($clients, true));
        setModuleCache($this->moduleID, 'clients', '', $clients);
        msgLog($this->lang['title'].'-'.lang('save')."-".$clients["C$cID"]['cTitle']);
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jqBiz('#accClients').accordion('select', 0); bizGridReload('dgClients'); jqBiz('#details').html('&nbsp;');"]]);
    }
    public function delete(&$layout=[])
    {
        if (!validateAccess('admin', 4)) { return; }
        $cID = clean('rID', 'integer', 'get');
        if (!$cID) { return; }
        $values = getModuleCache($this->moduleID, 'clients');
        unset($values["C$cID"]);
        setModuleCache($this->moduleID, 'clients', '', $values);
        msgLog($this->lang['title'].'-'.lang('delete')."-".$values['cTitle']);
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval', 'actionData'=>"bizGridReload('dgClients');"]]);
    }
    private function dgClients($name, $security)
    {
        $this->managerSettings();
        $data = ['id'=>$name,'rows'=>$this->defaults['rows'],'page'=>$this->defaults['page'],
            'attr'   => ['toolbar'=>"#{$name}Bar", 'idField'=>'cID', 'url'=>BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/managerRows"],
            'events' => [
                'onDblClickRow'=> "function(rowIndex, rowData) { accordionEdit('accClients', 'dgClients', 'details', '".lang('details')."', '$this->moduleID/$this->pageID/edit', rowData.cID); }",
                'rowStyler'    => "function(index, row) { if (row.inactive==1) { return {class:'row-inactive'}; } else {
                    if (typeof row.start_date=='undefined' || typeof row.end_date=='undefined') { return; }
                    if (compareDate(dbDate(row.start_date)) == 1 || compareDate(dbDate(row.end_date)) == -1) { return {class:'journal-waiting'}; } } }"],
            'source' => [
                'actions'  => [
                    'new' => ['order'=>10,'label'=>lang('New'),'icon'=>'new',  'events'=>['onClick'=>"accordionEdit('accClients', 'dgClients', 'details', '".lang('details')."', '$this->moduleID/$this->pageID/edit', 0);"]]],
                'sort'     => ['s0'=>['order'=>10,'field'=>($this->defaults['sort'].' '.$this->defaults['order'])]]],
                'columns'  => [
                    'cID'     => ['order'=>0,'attr'=>['hidden'=>true]],
                    'action'  => ['order'=>1,'label'=>lang('action'),'events'=>['formatter'=>$name.'Formatter'],
                        'actions'   => [
                            'edit'  => ['icon'=>'edit','order'=>70,'hidden'=>$security>2?false:true,
                                'events'=>['onClick'=>"accordionEdit('accClients', 'dgClients', 'details', '".lang('details')."', '$this->moduleID/$this->pageID/edit', idTBD);"]],
                            'delete'=> ['icon'=>'trash','order'=>90,'hidden'=>$security>3?false:true,
                                'events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('$this->moduleID/$this->pageID/delete', idTBD);"]]]],
                    'cTitle'  => ['order'=>10,'label'=>$this->lang['edi_ed_title'], 'attr'=>['width'=>160,'sortable'=>true,'resizable'=>true]],
                    'ediID'   => ['order'=>20,'label'=>$this->lang['edi_ed_srcid'], 'attr'=>['width'=>160,'sortable'=>true,'resizable'=>true]],
                    'hostName'=> ['order'=>30,'label'=>$this->lang['edi_sftp_host'],'attr'=>['width'=>160,'sortable'=>true,'resizable'=>true]],
                    'userName'=> ['order'=>40,'label'=>$this->lang['edi_sftp_name'],'attr'=>['width'=>160,'sortable'=>true,'resizable'=>true]],
                    'rcvrID'  => ['order'=>50,'label'=>$this->lang['edi_ed_my_id'], 'attr'=>['width'=>160,'sortable'=>true,'resizable'=>true]],
                ]];
        return $data;
    }
}
