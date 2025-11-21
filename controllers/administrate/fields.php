<?php
/*
 * Methods to handle custom fields
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
 * @version    7.x Last Update: 2025-10-24
 * @filesource /controllers/administrate/fields.php
 */

namespace bizuno;

class administrateFields extends mgrJournal
{
    public    $moduleID  = 'administrate';
    public    $pageID    = 'fields';
    protected $secID     = 'admin';
    protected $domSuffix = 'Fields';
    protected $metaPrefix= 'fields';
    private   $cstmFields= ['contacts', 'inventory'];

    function __construct()
    {
        parent::__construct();
        $this->managerSettings();
        $this->fieldStructure();
     }
    private function fieldStructure()
    {
        $this->struc = [
            '_rID' => ['panel'=>'properties','order'=> 1,                        'clean'=>'integer', 'attr'=>['type'=>'hidden', 'value'=>0]], // For common_meta
            'table'=> ['panel'=>'properties','order'=>20,'label'=>lang('table'), 'clean'=>'db_field','attr'=>['value'=>'']],
            'label'=> ['panel'=>'properties','order'=>10,'label'=>lang('label'), 'clean'=>'db_field','attr'=>['value'=>'']],
            'title'=> ['panel'=>'properties','order'=>30,'label'=>lang('title'), 'clean'=>'text',    'attr'=>['value'=>'']],
            'sort' => ['panel'=>'accounting','order'=>40,'label'=>lang('order'), 'clean'=>'integer', 'attr'=>['type'=>'spinner', 'value'=>50]]];
    }
    protected function managerGrid($security=0, $args=[])
    {
        $data = array_replace_recursive(parent::gridBase($security, $args), [
//            'source' => ['actions'=>['new'=>['order'=>10,'icon'=>'add','events'=>['onClick'=>"jsonAction('$this->moduleID/$this->pageID/add')"]]]],
            'columns'=> [
                'table'  => ['label'=>lang('table'),  'order'=>10,'attr'=>['width'=>100,'sortable'=>true,'resizable'=>true]],
                'field'  => ['label'=>lang('field'),  'order'=>10,'attr'=>['width'=>100,'sortable'=>true,'resizable'=>true]],
                'type'   => ['label'=>lang('type'),   'order'=>20,'attr'=>['width'=> 80,'sortable'=>true,'resizable'=>true]],
                'label'  => ['label'=>lang('label'),  'order'=>30,'attr'=>['width'=>160,'sortable'=>true,'resizable'=>true]],
                'order'  => ['label'=>lang('order'),  'order'=>40,'attr'=>['width'=> 80,'sortable'=>true,'resizable'=>true]],
                'default'=> ['label'=>lang('default'),'order'=>50,'attr'=>['width'=> 80,'sortable'=>true,'resizable'=>true]]]]);
        return $data;
    }
    protected function managerSettings()
    {
        parent::managerDefaults();
        $this->defaults['sort'] = clean('sort', ['format'=>'text','default'=>'label'], 'post');
    }
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::managerMain($layout, $security, ['dom'=>'div', 'work'=>true]);
        unset($layout['datagrid']["dg{$this->domSuffix}"]['source']['actions']['new']);
        $layout['datagrid']["dg{$this->domSuffix}"]['source']['actions']['newJ']['icon'] = 'add';
        $layout['datagrid']["dg{$this->domSuffix}"]['source']['actions']['newJ']['label']= lang('add');
    }
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        $output = [];
        foreach ($this->cstmFields as $table) {
            $values = dbLoadStructure(BIZUNO_DB_PREFIX.$table);
            foreach ($values as $settings) {
                if ($settings['tab'] != 0) {
                    $output[] = [
                        '_rID'=>"$table.{$settings['field']}",'table'=>$table, 'field'=>$settings['field'], 'label'=>$settings['label'], 'order'=>$settings['order'],
                        'type' =>$settings['attr']['type'], 'default'=>$settings['default']];
                }
            }
        }
        $sorted = sortOrder($output, $this->defaults['sort'], $this->defaults['order']);
        $slice  = array_slice($sorted, ($this->defaults['page']-1)*$this->defaults['rows'], $this->defaults['rows']);
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode(['total'=>sizeof($output), 'rows'=>$slice])]);
    }
    public function add(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 2)) { return; }
        $options= [['id'=>'', 'text'=>lang('select')]];
        foreach ($this->cstmFields as $table) {  $options[] = ['id'=>$table, 'text'=>lang($table)]; }
        $fldSel = ['id'=>'table', 'props'=>['values'=>$options, 'attr'=>['type'=>'select']]];
        $args   = ['fldSel'=>$fldSel, 'desc'=>$this->lang['new_field_desc']];
        parent::addDB($layout, $security, $args);
    }
    
    public function edit(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        $dbFld = clean('rID', 'text', 'get'); // format table.field
        if (empty($dbFld)) { // it's an add
            $table = clean('table', 'db_field', 'get');
            $field = '';
            if (!in_array($table, $this->cstmFields)) { return msgAdd($this->lang['err_new_field_add']);}
        } else {
            $parts = explode('.', $dbFld, 2);
            $table = $parts[0];
            $field = $parts[1];
            if (empty($table) || empty($field)) { return msgAdd($this->lang['err_new_field_edit']); }
            msgAdd($this->lang['xf_msg_edit_warn'], 'caution');
        }
        $struc = dbLoadStructure(BIZUNO_DB_PREFIX.$table);
        $props = !empty($field) ? $struc[$field] : ['attr'=>['type'=>'text']];
        msgDebug("\n Working with field properties: ".print_r($props, true));
        $gList = [];
        $groups= [['id'=>'', 'text'=>'']];
        foreach ($struc as $value) {
            if (empty($value['group'])) { continue; }
            if (!in_array($value['group'], $gList)) {
                $gList[] = $value['group'];
                $groups[]= ['id'=>$value['group'], 'text'=>$value['group']];
            }
        }
        $jsBody = "jqBiz('#group').combobox({
    data:grpData, valueField:'id', textField:'id', width:100, delay:1000,
    onChange: function (newVal) {
        var datas = jqBiz('#group').combobox('options').data;
        datas.push({ id:newVal });
        jqBiz('#group').combobox('loadData', datas);
        jqBiz('#group').combobox('setValue', newVal);
    }
});";
        $data = ['type'=>'divHTML',
            'divs'    => ['detail' =>['order'=>50,'type'=>'divs','divs'=>[
                'toolbar'=> ['order'=>10,'type'=>'toolbar','key' =>'tbField'],
                'formBOF'=> ['order'=>15,'type'=>'form',   'key' =>'frmField'],
                'body'   => ['order'=>50,'type'=>'html',   'html'=>$this->getViewFields($props, $table, $field)],
                'formEOF'=> ['order'=>85,'type'=>'html',   'html'=>"</form>"]]]],
            'toolbars'=> ['tbField'=>['icons' =>[
                'new' => ['order'=>20,'events'=>['onClick'=>"accordionEdit('acc{$this->domSuffix}','dg{$this->domSuffix}','dtl{$this->domSuffix}','".lang('details')."','$this->moduleID/$this->page/add', 0);"]],
                'save'=> ['order'=>40,'events'=>['onClick'=>"jqBiz('#frmField').submit();"]]]]],
            'forms'   => ['frmField'=>['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=administrate/fields/save"]]],
            'jsHead'  => ['grpData' => "var grpData=".json_encode($groups).";"],
            'jsBody'  => ['init'=>$jsBody],
            'jsReady' => ['init'=>"ajaxForm('frmField');"]];
        $layout = array_replace_recursive($layout, $data);
    }

    private function getViewFields($props, $table, $field)
    {
        msgDebug("\nEntering getViewFields with and table = $table and field = $field");
        $tabs  = [];
        $type  = isset($props['attr']['type']) ? $props['attr']['type'] : 'text';
        if (in_array($type, ['textarea', 'varchar'])) { $type = 'text'; } // variations of text field
        $metaTabs = dbMetaGet('%', 'tabs');
//      msgDebug("\nRead tabs = ".print_r($metaTabs, true));
        foreach ($metaTabs as $meta) { $tabs[] = ['id'=>$meta['_rID'], 'text'=>$meta['title']]; }
        $ints  = viewKeyDropdown(['tinyint'=>'-127 '.lang('to').' 127', 'smallint'=>'-32,768 '.lang('to').' 32,768',
            'mediumint'=>'-8,388,608 '.lang('to').' 8,388,607', 'int'=>'-2,147,483,648 '.lang('to').' 2,147,483,647',
            'bigint'   =>lang('greater_than').' 2,147,483,648']);
        $floats= viewKeyDropdown(['float'=>$this->lang['xf_lbl_db_float'], 'double'=>$this->lang['xf_lbl_db_double']]);
        $checks= viewKeyDropdown(['0'=>lang('unchecked'),'1'=>lang('checked')]);
        $fields= [
            'table'           => ['attr'=>['type'=>'hidden','value'=>$table]],
            'id'              => ['attr'=>['type'=>'hidden','value'=>$field]], // holds old_field_name
            'field'           => ['label'=>$this->lang['xf_lbl_field'],'position'=>'after','attr'=>['value'=>$field]],
            'label'           => ['label'=>$this->lang['xf_lbl_label'],'position'=>'after','attr'=>['value'=>isset($props['label'])?$props['label']:'']],
            'tag'             => ['label'=>$this->lang['xf_lbl_tag'],  'position'=>'after','attr'=>['value'=>isset($props['tag'])?$props['tag']:'']],
            'tab'             => ['label'=>$this->lang['xf_lbl_tab'],  'position'=>'after','values'=>$tabs,'attr'=>['type'=>'select','value'=>isset($props['tab'])?$props['tab']:''],
                'options'=>['width'=>200]],
            'group'           => ['label'=>$this->lang['xf_lbl_group'],'position'=>'after','attr'=>['type'=>'select','value'=>isset($props['group'])?$props['group']:''],
                'options'=>['editable'=>'true']],
            'order'           => ['label'=>$this->lang['xf_lbl_order'],'position'=>'after','attr'=>['value'=>isset($props['order'])?$props['order']:'']],
            'type'            => ['position'=>'after', 'attr'=>['type'=>'radio','value'=>$type]],
            'text_length'     => ['label'=>$this->lang['xf_lbl_text_length']."<br />"],
            'text_default'    => ['label'=>lang('default')."<br />", 'attr'=>  ['type'=>'textarea']],
            'link_default'    => ['label'=>lang('default')."<br />"],
            'int_select'      => ['label'=>lang('range')."<br />", 'values'=>$ints, 'attr'=>['type'=>'select']],
            'int_default'     => ['label'=>lang('default')."<br />", 'attr'=>['value'=>isset($props['attr']['value'])?$props['attr']['value']:'0']],
            'float_select'    => ['label'=>lang('precision')."<br />",'values'=>$floats, 'attr'=>['type'=>'select']],
            'float_default'   => ['label'=>lang('default')."<br />", 'attr'=>['value'=>isset($props['attr']['value'])?$props['attr']['value']:'0']],
            'radio_default'   => ['label'=>$this->lang['xf_lbl_radio_default']."<br />", 'attr'=>['type'=>'textarea']],
            'checkbox_default'=> ['label'=>lang('default')."<br />",'values'=>$checks,'attr'=>['type'=>'select']]];
        switch ($props['attr']['type']) {
            case 'varchar':
            case 'text':
            case 'textarea': $fields['type']['attr']['value'] = 'text';
            case 'html':
                $fields['text_length']['attr']['value'] = isset($props['attr']['maxlength']) ? $props['attr']['maxlength'] : 32;
                // continue like link, which is just text
            case 'link_url':
            case 'link_image':
            case 'link_inventory':
//                $fields['type']['attr']['value'] = $props['attr']['type'];
                $fields['text_default']['attr']['value'] = isset($props['default']) ? $props['default'] : '';
                break;
            case 'integer':
                $data_type = (strpos($props['dbType'],'(') === false) ? strtolower($props['dbType']) : strtolower(substr($props['dbType'],0,strpos($props['dbType'],'(')));
                $fields['int_select']['attr']['value'] = $data_type;
                break;
            case 'float':
                $data_type = (strpos($props['dbType'],'(') === false) ? strtolower($props['dbType']) : strtolower(substr($props['dbType'],0,strpos($props['dbType'],'(')));
                $fields['float_select']['attr']['value'] = $data_type;
                break;
            case 'radio':
            case 'select':
            case 'checkbox_multi':
            case 'enum':
                $tmp = [];
                if (isset($props['opts'])) { foreach ($props['opts'] as $row) { $tmp[] = $row['id'].":".$row['text']; } }
                $fields['radio_default']['attr']['value'] = implode(';', $tmp);
                break;
            case 'checkbox':
                $fields['checkbox_default']['attr']['value'] = $props['default'];
                break;
            default:
        }
//        $output  = html5('module',$fields['module']);
        $output .= html5('table', $fields['table']);
        $output .= html5('id',    $fields['id']);
        $output .= '<table>
         <tbody>
          <tr><td colspan="2">'.$this->lang['xf_lbl_field_tip'] .'</td></tr>
          <tr><td colspan="2">'.html5('field', $fields['field']).'</td></tr>
          <tr><td colspan="2">'.html5('label', $fields['label']).'</td></tr>
          <tr><td colspan="2">'.html5('tag',   $fields['tag'])  .'</td></tr>
          <tr><td colspan="2">'.html5('tab',   $fields['tab'])  .'</td></tr>
          <tr><td colspan="2">'.html5('group', $fields['group']).'</td></tr>
          <tr><td colspan="2">'.html5('order', $fields['order'])."</td></tr>";
// @TODO ************************************* THIS NEEDS FIXIN ***********************************************//
        if (isset($viewData['options']) && is_array($viewData['options'])){
            $output .= '  <tr class="panel-header"><th colspan="2">'.lang('options')."</th></tr>";
            $output .= '  <tr><td colspan="2">'.$viewData['options']['description']."</td></tr>";
            foreach ($viewData['options']['values'] as $key => $settings) { $output .= "  <tr><td>".html5($key, $settings)."</td></tr>"; }
        }

        $output .= '  <tr class="panel-header"><th colspan="2">'.lang('attributes')."</th></tr>";

        $output .= "  <tr><td>";
        $fields['type']['label'] = $this->lang['xf_lbl_text'];
        $fields['type']['attr']['checked'] = $type=='text' ? 'checked' : false;
        $fields['type']['attr']['value'] = 'text'; // radio button type
        $output .= html5('type', $fields['type'])."<br />";
        $fields['type']['label'] = $this->lang['xf_lbl_html'];
        $fields['type']['attr']['checked'] = $type=='html' ? 'checked' : false;
        $fields['type']['attr']['value'] = 'html';
        $output .= html5('type', $fields['type'])."<br />";
        $output .= "  </td><td>";
        $output .= html5('text_length', $fields['text_length'])."<br />";
        $output .= html5('text_default', $fields['text_default']);
        $output .= '  </td></tr><tr><td colspan="2"><hr /></td></tr>';
        $output .= "  <tr><td>";
        $fields['type']['label'] = $this->lang['xf_lbl_link_url'];
        $fields['type']['attr']['checked'] = $type=='link_url' ? 'checked' : false;
        $fields['type']['attr']['value'] = 'link_url';
        $output .= html5('type', $fields['type'])."<br />";
        $fields['type']['label'] = $this->lang['xf_lbl_link_image'];
        $fields['type']['attr']['checked'] = $type=='link_image' ? 'checked' : false;
        $fields['type']['attr']['value'] = 'link_image';
        $output .= html5('type', $fields['type'])."<br />";
        $fields['type']['label'] = $this->lang['xf_lbl_link_inventory'];
        $fields['type']['attr']['checked'] = $type=='link_inventory' ? 'checked' : false;
        $fields['type']['attr']['value'] = 'link_inventory';
        $output .= html5('type', $fields['type']);
        $output .= "  </td><td>";
        $output .= html5('link_default', $fields['link_default']);
        $output .= '  </td></tr><tr><td colspan="2"><hr /></td></tr>';
        $output .= "  <tr><td>";
        $fields['type']['label'] = $this->lang['xf_lbl_int'];
        $fields['type']['attr']['checked'] = $type=='integer' ? 'checked' : false;
        $fields['type']['attr']['value'] = 'integer';
        $output .= html5('type', $fields['type']);
        $output .= "  </td><td>";
        $output .= html5('int_select',  $fields['int_select'])."<br />";
        $output .= html5('int_default', $fields['int_default']);
        $output .= '  </td></tr><tr><td colspan="2"><hr /></td></tr>';
        $output .= "  <tr><td>";
        $fields['type']['label'] = $this->lang['xf_lbl_float'];
        $fields['type']['attr']['checked'] = $type=='float' ? 'checked' : false;
        $fields['type']['attr']['value'] = 'float';
        $output .= html5('type', $fields['type']);
        $output .= "  </td><td>";
        $output .= html5('float_select',  $fields['float_select'])."<br />";
        //$output .= html5('float_format',  $fields['float_format'])."<br />"; // for decimal type
        $output .= html5('float_default', $fields['float_default']);
        $output .= '  </td></tr><tr><td colspan="2"><hr /></td></tr>';
        $output .= "  <tr><td>";
        $fields['type']['label'] = $this->lang['xf_lbl_checkbox_multi'];
        $fields['type']['attr']['checked'] = $type=='checkbox_multi' ? 'checked' : false;
        $fields['type']['attr']['value'] = 'checkbox_multi';
        $output .= html5('type', $fields['type'])."<br />";
        $fields['type']['label'] = $this->lang['xf_lbl_select'];
        $fields['type']['attr']['checked'] = $type=='select' ? 'checked' : false;
        $fields['type']['attr']['value'] = 'select';
        $output .= html5('type', $fields['type'])."<br />";
        $fields['type']['label'] = $this->lang['xf_lbl_radio'];
        $fields['type']['attr']['checked'] = $type=='radio' ? true : false;
        $fields['type']['attr']['value'] = 'radio';
        $output .= html5('type', $fields['type'])."</td>";
        $output .= "  </td><td>";
        $output .= html5('radio_default', $fields['radio_default']);
        $output .= '  </td></tr><tr><td colspan="2"><hr /></td></tr>';
        $output .= "  <tr><td>";
        $fields['type']['label'] = $this->lang['xf_lbl_checkbox'];
        $fields['type']['attr']['checked'] = $type=='checkbox' ? 'checked' : false;
        $fields['type']['attr']['value'] = 'checkbox';
        $output .= html5('type', $fields['type']);
        $output .= "  </td><td>";
        $output .= html5('checkbox_default', $fields['checkbox_default']);
        $output .= '  </td></tr><tr><td colspan="2"><hr /></td></tr>';
        $output .= "  <tr><td>";
        $fields['type']['label'] = lang('date');
        $fields['type']['attr']['checked'] = $type=='date' ? 'checked' : false;
        $fields['type']['attr']['value'] = 'date';
        $output .= html5('type', $fields['type'])."<br />";
        $fields['type']['label'] = lang('time');
        $fields['type']['attr']['checked'] = $type=='time' ? 'checked' : false;
        $fields['type']['attr']['value'] = 'time';
        $output .= html5('type', $fields['type'])."<br />";
        $fields['type']['label'] = $this->lang['xf_lbl_datetime'];
        $fields['type']['attr']['checked'] = $type=='datetime' ? 'checked' : false;
        $fields['type']['attr']['value'] = 'datetime';
        $output .= html5('type', $fields['type'])."<br />";
        $fields['type']['label'] = $this->lang['xf_lbl_timestamp'];
        $fields['type']['attr']['checked'] = $type=='timestamp' ? 'checked' : false;
        $fields['type']['attr']['value'] = 'timestamp';
        $output .= html5('type', $fields['type']);
        $output .= '  </td></tr>';
        $output .= " </tbody>";
        $output .= "</table>";
        return $output;
    }

    /**
     * Method to save a new/modified custom field
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function save(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        $request = $_POST;
        $rID   = isset($request['id'])    ? clean($request['id'],    'integer'): 0;
        $table = isset($request['table']) ? clean($request['table'], 'text')   : '';
        if (empty($table)) { return msgAdd("Table information missing!"); }
        // clean out all non-allowed values and then check if we have a empty string
        $old_field = clean($request['id'], 'text'); // get the old field name
        $new_field = preg_replace("[^A-Za-z0-9_]", "", clean($request['field'], 'text'));
        if ($new_field == '') { return msgAdd(lang('err_field_empty')); }
        if (!$old_field || $old_field <> $new_field) { // changed field name
            $exists = dbFieldExists(BIZUNO_DB_PREFIX.$table, $new_field);
            if ($exists) { return msgAdd($this->lang['xf_err_field_exists']); }
        }
        $type   = clean('type', ['format'=>'text', 'default'=>'text'], 'post');
        $comment= [];
        switch ($type) {
            default:
            case 'html':
            case 'text':
                $length = clean('text_length', 'integer', 'post');
                if ($length < 1) { $length = '32'; }
                $default = clean('text_default', 'text', 'post');
                if ($length < 256)          { $fieldType = "VARCHAR($length) DEFAULT '".addslashes($default)."'"; }
                elseif ($length < 65536)    { $fieldType = 'TEXT'; }
                elseif ($length < 16777216) { $fieldType = 'MEDIUMTEXT'; }
                else                        { $fieldType = 'LONGTEXT'; }
                if     ($type=='html') { $comment[] = 'type:html'; }
                elseif ($length > 256) { $comment[] = 'type:textarea'; }
                break;
            case 'link_url':       $comment[] ='type:linkurl';
            case 'link_image':     if (sizeof($comment)==0) { $comment[] ='type:linkimg'; }
            case 'link_inventory': if (sizeof($comment)==0) { $comment[] ='type:linkinv'; }
                $default   = clean('link_default', 'text', 'post');
                $fieldType = "VARCHAR(255) DEFAULT '".addslashes($default)."'";
                break;
            case 'integer': $comment[] ='type:integer';
                $select  = clean('int_select',  'text', 'post');
                $default = clean('int_default','integer', 'post');
                switch ($select) {
                    case 'tinyint':   $fieldType = "TINYINT DEFAULT '$default'";  break;
                    case 'smallint':  $fieldType = "SMALLINT DEFAULT '$default'"; break;
                    case 'mediumint': $fieldType = "MEDIUMINT DEFAULT '$default'";break;
                    default:
                    case 'int':       $fieldType = "INT DEFAULT '$default'";      break;
                    case 'bigint':    $fieldType = "BIGINT DEFAULT '$default'";   break;
                }
                break;
            case 'float': $comment[] ='type:float';
                $select  = clean('float_select', 'text', 'post');
//              $format  = clean($request['float_format'], 'text');
                $default = clean('float_default','float', 'post');
                switch ($select) {
                    default:
                    case 'float':  $fieldType = "FLOAT DEFAULT '$default'"; break;
                    case 'double': $fieldType = "DOUBLE DEFAULT '$default'";break;
//                  case 2: $fieldType = "DECIMAL($format) DEFAULT '$default'";break;
                }
                break;
            case 'select':         $comment[] ='type:select'; // default selection is asssumed to be listed first
            case 'radio':          if (sizeof($comment)==0) { $comment[] ='type:radio'; }
            case 'checkbox_multi': if (sizeof($comment)==0) { $comment[] ='type:checkbox_multi'; }
                $default = clean('radio_default', 'text', 'post');
                $choices = explode(';', $default);
                $keys    = [];
                $values  = [];
                foreach ($choices as $choice) {
                    $pairs = explode(':', $choice, 2);
                    $keys[]   = trim($pairs[0]);
                    $values[] = isset($pairs[1]) ? trim($pairs[1]) : trim($pairs[0]);
                }
                $fieldType = "ENUM('".implode("','", $keys)."') DEFAULT '{$keys[0]}'";
                $comment[] = "opts:".implode(":", $values);
                break;
            case 'checkbox': $comment[] ='type:checkbox';
                $select    = clean('checkbox_default', 'char', 'post');
                $fieldType = "ENUM('0','1') DEFAULT '$select'";
                break;
            case 'date':      $fieldType = "DATE";     break;
            case 'time':      $fieldType = "TIME";     break;
            case 'datetime':  $fieldType = "DATETIME"; break;
            case 'timestamp': $fieldType = "TIMESTAMP";break;
        }
        $order= clean('order','integer', 'post');
        $tab  = clean('tab',  'integer', 'post');
        if ($order) { $comment[] = "order:$order"; }
        if ($tab)   { $comment[] = "tab:$tab"; }
        if (isset($request['label']) && strlen($request['label']) > 0) {
            $label = str_replace([':',';'], ['.',','], clean($request['label'],'text'));
            $comment[] = "label:$label";
        }
        if (isset($request['tag']) && strlen($request['tag']) > 0) {
            $tag = str_replace([':',';'], ['.',','], clean($request['tag'],'text'));
            $comment[] = "tag:$tag";
        }
        if (isset($request['group']) && strlen($request['group']) > 0) {
            $group = str_replace([':',';'], ['.',','], clean($request['group'],'text'));
            $comment[] = "group:$group";
        }
        $cmt = addslashes(implode(";",$comment));
        if ($old_field) { $sql = "ALTER TABLE `".BIZUNO_DB_PREFIX."$table` CHANGE `$old_field` `$new_field` $fieldType COMMENT '$cmt'"; }
        else      { $sql = "ALTER TABLE `".BIZUNO_DB_PREFIX."$table` ADD COLUMN `$new_field` $fieldType COMMENT '$cmt'"; }
        dbGetResult($sql);
        msgAdd(lang('extra_fields')." (".($old_field?lang('update'):lang('add')).") ".lang('msg_database_write'), 'success');
        msgLog(lang('extra_fields')." (".($old_field?lang('update'):lang('add')).") $table.$new_field");
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jqBiz('#accFields').accordion('select', 0); bizGridReload('dgFields'); jqBiz('#detail').html('&nbsp;');"]]);
    }

    /**
     * Method to delete a custom field
     * @param array $layout - structure coming in
     * @return modified $structure
     */
    public function delete(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        $table = clean('table','text', 'get');
        $field = clean('data', 'text', 'get');
        if (!$table || !$field) { return msgAdd("Table and/of field information missing!"); }
        if ($field) {
            msgLog(lang('extra_tabs')." (".lang('delete').") $table.$field");
            $layout = array_replace_recursive($layout, ['content' => ['action'=>'eval','actionData'=>"bizGridReload('dgFields');"],
                'dbAction'=> [BIZUNO_DB_PREFIX.'TBD' => "ALTER TABLE `".BIZUNO_DB_PREFIX."$table` DROP COLUMN `$field`"]]);
        }
    }
}
