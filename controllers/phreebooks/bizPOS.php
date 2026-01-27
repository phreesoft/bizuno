<?php
/*
 * @name Bizuno ERP - Point of Sale Extension
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
 * @copyright  2008-2026, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2025-06-08
 * @filesource /controllers/phreebooks/bizPOS.php
 *
 * POS printer library: https://github.com/mike42/escpos-php
 */

namespace bizuno;

class extBizPOSAdmin
{
    public  $moduleID = 'phreebooks';

    function __construct()
    {
        $this->lang     = getExtLang($this->moduleID);
        $this->settings = getModuleCache($this->moduleID, 'settings', false, false, []);
        $this->tillDefaults = [
            'id'      => '',
            'title'   => $this->lang['till_title'],
            'store_id'=> 0,
            'gl_cash' => getChartDefault(0),
            'gl_diff' => getChartDefault(0),
            'max_disc'=> 0,
            'printer' => 0];
    }

    /**
     * Manager for tills tab
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function tillManager(&$layout=[])
    {
        if (!$security = validateAccess('admin', 1)) { return; }
        $layout = array_replace_recursive($layout, ['type'=>'divHTML',
            'divs' => [
                "divtill" => ['order'=>50,'type'=>'accordion','key'=>'accTills']],
            'accordion'=>['accTills'=>['divs'=>[
                'manager'=> ['order'=>30,'label'=>$this->lang['tills'],'type'=>'datagrid','key' =>'dgTills'],
                'detail' => ['order'=>70,'label'=>lang('details'),     'type'=>'html',    'html'=>'&nbsp;'],]]],
            'datagrid' =>['dgTills'=>$this->dgTills('dgTills', $security)]]);
    }

    /**
     * Fetches the till rows from the cache
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function tillManagerRows(&$layout=[])
    {
        if (!$security = validateAccess('admin', 1)) { return; }
        $tills  = getModuleCache($this->moduleID, 'tills');
        msgDebug("\nRead tills = ".print_r($tills, true));
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode(['total'=>sizeof($tills), 'rows'=>array_values($tills)])]);
    }

    /**
     * Edit a till record
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function tillEdit(&$layout=[])
    {
        if (!$security = validateAccess('admin', 1)) { return; }
        $rID     = clean('id', 'text', 'get');
        $values  = !empty($rID) ? getModuleCache($this->moduleID, 'tills', $rID) : $this->tillDefaults;
        $objXML  = bizuno_simpleXML(file_get_contents(BIZUNO_FS_LIBRARY."controllers/$this->moduleID/PrinterCodes.xml"));
        foreach ($objXML->data as $row) { $printers[] = ['id'=>$row->id, 'text'=>"$row->Manufacturer $row->Model"]; }
        $data    = ['type'=>'divHTML',
            'divs'    => [
                'toolbar' => ['order'=>10,'type'=>'toolbar','key' =>'tbTills'],
                'heading' => ['order'=>15,'type'=>'html',   'html'=>"<h1>{$this->lang['till']}</h1>"],
                'formBOF' => ['order'=>20,'type'=>'form',   'key' =>'frmTills'],
                'body'    => ['order'=>50,'type'=>'fields', 'keys'=>['id','title','store_id','gl_cash','gl_diff','max_disc','printer']],
                'formEOF' => ['order'=>99,'type'=>'html',   'html'=>'</form>']],
            'toolbars'=> ['tbTills'=>  ['icons' => [
                'save' => ['order'=>10,'icon'=>'save','events'=>['onClick'=>"jqBiz('body').addClass('loading'); jqBiz('#frmTills').submit();"]]]]],
            'forms'   => ['frmTills'=>  ['attr'=>  ['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/admin/tillSave"]]],
            'fields'  => [
                'id'      => ['label'=>$this->lang['till_id'],   'break'=>true,'position'=>'after','attr'=>['value'=>$values['id']]],
                'title'   => ['label'=>$this->lang['till_title'],'break'=>true,'position'=>'after','attr'=>['value'=>$values['title']]],
                'store_id'=> ['label'=>lang('store_id'),         'break'=>true,'position'=>'after','attr'=>['type'=>'hidden','value'=>$values['store_id']]],
                'gl_cash' => ['label'=>$this->lang['gl_cash'],   'break'=>true,'position'=>'after','attr'=>['type'=>'ledger','value'=>$values['gl_cash']]],
                'gl_diff' => ['label'=>$this->lang['gl_diff'],   'break'=>true,'position'=>'after','attr'=>['type'=>'ledger','value'=>$values['gl_diff']]],
                'max_disc'=> ['label'=>$this->lang['max_disc'],  'break'=>true,'position'=>'after','attr'=>['value'=>$values['max_disc']]],
                'printer' => ['label'=>$this->lang['printer'],   'break'=>true,'position'=>'after','values'=>$printers,'attr'=>['type'=>'select','value'=>$values['printer']]]],
            'jsReady' => ['init'=>"ajaxForm('frmTills');"]];
        if ($values['id']) { $data['fields']['id']['attr']['readonly'] = 'readonly'; }
        if (sizeof(getModuleCache('bizuno', 'stores')) > 1) {
            $data['fields']['store_id']['break']        = true;
            $data['fields']['store_id']['attr']['type'] = 'select';
            $data['fields']['store_id']['values']       = viewStores();
        }
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     *
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function tillSave(&$layout=[])
    {
        if (!validateAccess('admin', 3)) { return; }
        $rID    = clean('id', 'text', 'post');
        if (!$rID) { $rID = getNextReference('next_till_num'); }
        $tills  = getModuleCache($this->moduleID, 'tills');
        $values = [
            'id'          => $rID,
            'title'       => clean('title',   'text',   'post'),
            'store_id'    => clean('store_id','integer','post'),
            'gl_cash'     => clean('gl_cash', 'text',   'post'),
            'gl_diff'     => clean('gl_diff', 'text',   'post'),
            'max_disc'    => clean('max_disc','float',  'post'),
            'printer'     => clean('printer', 'text',   'post'),
            'manufacturer'=> 'Generic',
            'model'       => 'All',
            'drawerCode'  => '',
            'drawer2Code' => '',
            'cutterCode'  => '',
            'cutterPart'  => ''];
        $objXML = bizuno_simpleXML(file_get_contents(BIZUNO_FS_LIBRARY."controllers/$this->moduleID/PrinterCodes.xml"));
        foreach ($objXML->data as $row) { if ($row->id == $values['printer']) {
            $values['manufacturer']= isset($row->Manufacturer)? $row->Manufacturer: 'Generic';
            $values['model']       = isset($row->Model)       ? $row->Model       : 'Generic';
            $values['drawerCode']  = isset($row->DrawerCode)  ? $row->DrawerCode  : '';
            $values['drawer2Code'] = isset($row->Drawer2Code) ? $row->Drawer2Code : '';
            $values['cutterCode']  = isset($row->CutterCode)  ? $row->CutterCode  : '';
            $values['cutterPart']  = isset($row->CutterPart)  ? $row->CutterPart  : '';
        } }
        $tills[$rID] = $values;
        setModuleCache($this->moduleID, 'tills', false, $tills);
        msgAdd($this->lang['tills'].": {$values['title']} - ".lang('msg_settings_saved'), 'success');
        msgLog($this->lang['tills'].": {$values['title']} ($rID) - ".lang('msg_settings_saved'));
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jqBiz('#accTills').accordion('select', 0); jqBiz('#dgTills').datagrid('reload'); jqBiz('#detail').html('&nbsp;');"]]);
    }

    /**
     * Deletes a register from the list
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function tillDelete(&$layout=[])
    {
        if (!validateAccess('admin', 4)) { return; }
        $rID   = clean('data', 'text', 'get');
        if (!$rID) { return msgAdd("Bad data!"); }
        $title = getModuleCache($this->moduleID, 'tills', $rID, 'title');
        clearModuleCache($this->moduleID, 'tills', $rID);
        msgLog($this->lang['till'].": $title ($rID) - ".lang('deleted'));
        $layout= array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jqBiz('#accTills').accordion('select', 0); jqBiz('#dgTills').datagrid('reload'); jqBiz('#detail').html('&nbsp;');"]]);
    }

    /**
     *
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function tillSelect(&$layout=[])
    {
        if (!$security = validateAccess('admin', 1)) { return; }
        if (getUserCache('profile', 'tillID')) { return; }
        $tills = getModuleCache($this->moduleID, 'tills');
        if (!sizeof($tills)) { return msgAdd($this->lang['err_no_tills']); }
        if ( sizeof($tills)==1) { return setUserCache('profile', 'tillID', 0); } // only one till use it and don't ask
        $viewTills = viewDropdown(getModuleCache($this->moduleID, 'tills'), 'id', 'title', true);
        $html   = '<p>'.$this->lang['till_select_desc']."</p>".html5('tillID',['values'=>$viewTills,'attr'=>['type'=>'select', 'value'=>'']]);
        $html  .= html5('iconGO',['icon'=>'next','events'=>['onClick'=>"jsonAction('$this->moduleID/admin/tillSet', 0, jqBiz('#tillID').val());"]]);
        $data   = ['type'=>'popup','title'=>'','attr'=>['id'=>'winNewTill','width'=>400,'height'=>200],
            'divs'=>['winNewTill'=>['order'=>50,'type'=>'html','html'=>$html]]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     *
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function tillSet(&$layout=[])
    {
        if (!$security = validateAccess('admin', 1)) { return; }
        $tillID = clean('data', 'text', 'get');
        if (!$tillID) { return msgAdd("Bad Data!"); }
        setUserCache('profile', 'tillID', $tillID);
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval', 'actionData'=>"bizWindowClose('winNewTill');"]]);
    }

    /**
     *
     * @param type $name
     * @param type $security
     * @return type
     */
    private function dgTills($name, $security=0)
    {
        return [
            'id'     => $name,
            'attr'   => ['toolbar'=>"#{$name}Toolbar", 'singleSelect'=>true, 'url'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/admin/tillManagerRows"],
            'events' => [
                'onDblClickRow'=> "function(rowIndex, rowData) { accordionEdit('accTills', 'dgTills', 'detail', '".lang('details')."', '$this->moduleID/admin/tillEdit&id='+rowData.id); }"],
            'source' => ['actions'=> [
                'tillNew'=> ['order'=>10,'icon'=>'new','events'=>['onClick'=>"accordionEdit('accTills', 'dgTills', 'detail', '".lang('details')."', '$this->moduleID/admin/tillEdit');"]]]],
            'columns'=> [
                'action' => ['order'=> 1,'label'=>'','attr'=>['width'=>50],
                    'events' => ['formatter'=>"function(value,row,index){ return {$name}Formatter(value,row,index); }"],
                    'actions'=> ['delete'=> ['icon'=>'trash','size'=>'small','order'=>90,'hidden'=>$security>3?false:true,
                        'events'=> ['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('$this->moduleID/admin/tillDelete', 0, jqBiz('#$name').datagrid('getRows')[indexTBD]['id']);"]]]],
                'id'      => ['order'=>10,'label'=>$this->lang['till_id'],   'attr'=>['width'=> 50,'resizable'=>true]],
                'title'   => ['order'=>20,'label'=>lang('title'),            'attr'=>['width'=>150,'resizable'=>true]],
                'store_id'=> ['order'=>30,'label'=>lang('store_id'),'attr'=>['width'=> 75,'resizable'=>true]],
                'gl_cash' => ['order'=>40,'label'=>$this->lang['gl_cash'],   'attr'=>['width'=> 75,'resizable'=>true]],
                'gl_diff' => ['order'=>50,'label'=>$this->lang['gl_diff'],   'attr'=>['width'=> 75,'resizable'=>true]],
                'max_disc'=> ['order'=>70,'label'=>$this->lang['max_disc'],  'attr'=>['width'=> 75,'resizable'=>true]],
                'printer' => ['order'=>80,'label'=>$this->lang['printer'],   'attr'=>['width'=> 75,'resizable'=>true]]]];
    }
}
