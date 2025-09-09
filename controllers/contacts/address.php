<?php
/*
 * Module contacts address methods
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
 * @version    7.x Last Update: 2025-09-09
 * @filesource /controllers/contacts/address.php
 */

namespace bizuno;

class contactsAddress extends mgrJournal
{
    public    $moduleID  = 'contacts';
    public    $pageID    = 'address';
//    private   $reqFields = ['primary_name','address1','telephone1','email'];
    protected $domSuffix = 'Address';
    protected $metaPrefix= 'address_';
    public $aType;
    public $cType;
    public $secID;
    public $struc;

    function __construct()
    {
        $this->aType      = clean('aType', ['format'=>'cmd', 'default'=>'s'], 'request');
        $this->cType      = clean('cType', ['format'=>'cmd', 'default'=>'c'], 'get');
        $this->secID      = "mgr_$this->cType";
        $this->metaPrefix.= $this->aType; // add the suffix
        $this->domSuffix .= strtoupper($this->aType);
        parent::__construct();
        $this->managerSettings();
        $this->fieldStructure();
    }
    public function fieldStructure()
    {
        $this->struc = [
            '_rID'        => ['panel'=>'address','order'=> 1,                               'clean'=>'integer', 'attr'=>['type'=>'hidden', 'value'=>0]],
            '_refID'      => ['panel'=>'address','order'=> 1,                               'clean'=>'integer', 'attr'=>['type'=>'hidden', 'value'=>0]],
            'aType'       => ['panel'=>'address','order'=> 1,                               'clean'=>'char',    'attr'=>['type'=>'hidden', 'value'=>$this->aType]],
            'primary_name'=> ['panel'=>'address','order'=>10, 'label'=>lang('primary_name'),'clean'=>'text',    'attr'=>['size'=>48, 'value'=>'']],
            'contact'     => ['panel'=>'address','order'=>15, 'label'=>lang('contact'),     'clean'=>'text',    'attr'=>['size'=>48, 'value'=>'']],
            'address1'    => ['panel'=>'address','order'=>20, 'label'=>lang('address1'),    'clean'=>'text',    'attr'=>['size'=>48, 'value'=>'']],
            'address2'    => ['panel'=>'address','order'=>25, 'label'=>lang('address2'),    'clean'=>'text',    'attr'=>['size'=>48, 'value'=>'']],
            'city'        => ['panel'=>'address','order'=>30, 'label'=>lang('city'),        'clean'=>'text',    'attr'=>['size'=>24, 'value'=>'']],
            'state'       => ['panel'=>'address','order'=>35, 'label'=>lang('state'),       'clean'=>'text',    'attr'=>['size'=>24, 'value'=>'']],
            'postal_code' => ['panel'=>'address','order'=>40, 'label'=>lang('postal_code'), 'clean'=>'cmd',     'attr'=>['size'=>12, 'value'=>'']],
            'country'     => ['panel'=>'address','order'=>45, 'label'=>lang('country'),     'clean'=>'db_field','attr'=>['type'=>'country', 'value'=>'USA']],
            'name_first'  => ['panel'=>'contact','order'=>10, 'label'=>lang('name_first'),  'clean'=>'text',    'attr'=>['size'=>32, 'value'=>'']],
            'name_last'   => ['panel'=>'contact','order'=>15, 'label'=>lang('name_last'),   'clean'=>'text',    'attr'=>['size'=>32, 'value'=>'']],
            'title'       => ['panel'=>'contact','order'=>20, 'label'=>lang('title'),       'clean'=>'text',    'attr'=>['size'=>32, 'value'=>'']],
            'telephone1'  => ['panel'=>'contact','order'=>25, 'label'=>lang('telephone'),   'clean'=>'filename','attr'=>['size'=>20, 'value'=>'']],
            'telephone2'  => ['panel'=>'contact','order'=>30, 'label'=>lang('telephone2'),  'clean'=>'filename','attr'=>['size'=>20, 'value'=>'']],
            'telephone3'  => ['panel'=>'contact','order'=>35, 'label'=>lang('telephone3'),  'clean'=>'filename','attr'=>['size'=>20, 'value'=>'']],
            'telephone4'  => ['panel'=>'contact','order'=>40, 'label'=>lang('telephone4'),  'clean'=>'filename','attr'=>['size'=>20, 'value'=>'']],
            'email'       => ['panel'=>'contact','order'=>45, 'label'=>lang('email'),       'clean'=>'email',   'attr'=>['size'=>64, 'value'=>'']],
            'email2'      => ['panel'=>'contact','order'=>50, 'label'=>lang('email_m'),     'clean'=>'email',   'attr'=>['size'=>64, 'value'=>'']],
            'email3'      => ['panel'=>'contact','order'=>55, 'label'=>lang('email_p'),     'clean'=>'email',   'attr'=>['size'=>64, 'value'=>'']],
            'email4'      => ['panel'=>'contact','order'=>60, 'label'=>lang('email_r'),     'clean'=>'email',   'attr'=>['size'=>64, 'value'=>'']],
            'website'     => ['panel'=>'contact','order'=>65, 'label'=>lang('website'),     'clean'=>'url_full','attr'=>['size'=>48, 'value'=>'']],
            'notes'       => ['panel'=>'notes',  'order'=>10,                               'clean'=>'text',    'attr'=>['type'=>'editor']]];
    }
    protected function managerGrid($security=0, $args=[])
    {
        msgDebug("\nEntering contacts:address:managerGrid with args = ".print_r($args, true));
        $defs = array_replace(['_refID'=>clean('refID', 'integer', 'get'), '_table'=>'contacts', 'dom'=>''], $args);
        $data = array_replace_recursive(parent::gridBase($security, $defs), [
            'attr'   => ['url'=>BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/managerRows&aType=$this->aType&cType=$this->cType&dom={$defs['dom']}&refID={$defs['_refID']}"],
            'source' => [
                'search' => ['primary_name', 'contact', 'telephone1', 'telephone2', 'telephone3', 'telephone4', 'city', 'postal_code', 'email'],
                'actions'=> [
                    'new' => ['order'=>10,'icon'=>'new',  'events'=>['onClick'=>"accordionEdit('acc{$this->domSuffix}', 'dg{$this->domSuffix}', 'dtl{$this->domSuffix}', '".jsLang('details')."', '$this->moduleID/$this->pageID/edit&aType=$this->aType&dom={$defs['dom']}&table={$defs['_table']}&refID={$defs['_refID']}', 0);"]]]],
            'columns'=> [
                'name_last'   => ['order'=>10, 'label'=>lang('name_last'),   'attr'=>['width'=>120, 'sortable'=>true, 'resizable'=>true, 'hidden'=>$this->aType=='i'?false:true]],
                'name_first'  => ['order'=>15, 'label'=>lang('name_first'),  'attr'=>['width'=>120, 'sortable'=>true, 'resizable'=>true, 'hidden'=>$this->aType=='i'?false:true]],
                'title'       => ['order'=>20, 'label'=>lang('title'),       'attr'=>['width'=>160, 'sortable'=>true, 'resizable'=>true, 'hidden'=>$this->aType=='i'?false:true]],
                'primary_name'=> ['order'=>25, 'label'=>lang('primary_name'),'attr'=>['width'=>200, 'sortable'=>true, 'resizable'=>true, 'hidden'=>$this->aType=='i'?true:false]],
                'address1'    => ['order'=>30, 'label'=>lang('address1'),    'attr'=>['width'=>160, 'sortable'=>true, 'resizable'=>true, 'hidden'=>$this->aType=='i'?true:false]],
                'city'        => ['order'=>35, 'label'=>lang('city'),        'attr'=>['width'=> 90, 'sortable'=>true, 'resizable'=>true, 'hidden'=>$this->aType=='i'?true:false]],
                'state'       => ['order'=>40, 'label'=>lang('state'),       'attr'=>['width'=> 60, 'sortable'=>true, 'resizable'=>true, 'hidden'=>$this->aType=='i'?true:false]],
                'postal_code' => ['order'=>45, 'label'=>lang('postal_code'), 'attr'=>['width'=> 70, 'sortable'=>true, 'resizable'=>true, 'hidden'=>$this->aType=='i'?true:false]],
                'email'       => ['order'=>50, 'label'=>lang('email'),       'attr'=>['width'=>160, 'sortable'=>true, 'resizable'=>true, 'hidden'=>$this->aType=='i'?false:true]],
                'telephone1'  => ['order'=>55, 'label'=>lang('telephone'),   'attr'=>['width'=>100, 'sortable'=>true, 'resizable'=>true, 'hidden'=>$this->aType=='i'?false:true]]]]);
//      if (in_array($GLOBALS['myDevice'], ['mobile', 'tablet'])) { $data['columns']['address1']['attr']['hidden']= true; }
        return $data;
    }
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        if (empty($this->cType) || empty($this->aType)) { return msgAdd(lang('err_no_permission')); }
        $dom  = clean('dom',  ['format'=>'cmd', 'default'=>'div'], 'get'); // or page
        $args = ['dom'=>$dom, 'title'=>sprintf(lang('tbd_manager'), lang("address_type_{$this->aType}"))]; // 'rID'=>$rID,
        parent::managerMain($layout, $security, $args);
        $layout['datagrid']["dg{$this->domSuffix}"]['events']['onDblClickRow'] = "function(rowIndex, rowData){ accordionEdit('acc{$this->domSuffix}', 'dg{$this->domSuffix}', 'dtl{$this->domSuffix}', '".lang('details')."', '$this->moduleID/$this->pageID/edit&aType=$this->aType', rowData._rID); }";
        $layout['datagrid']["dg{$this->domSuffix}"]['columns']['action']['actions']['edit']['events']['onClick'] = "accordionEdit('acc{$this->domSuffix}', 'dg{$this->domSuffix}', 'dtl{$this->domSuffix}', '".lang('details')."', '$this->moduleID/$this->pageID/edit&table=tableTBD', 'idTBD');";
        $layout['datagrid']["dg{$this->domSuffix}"]['columns']['action']['actions']['copy']['events']['onClick'] = "var title=prompt('".lang('err_copy_name_prompt')."'); if (title!=null) jsonAction('$this->moduleID/$this->pageID/copy&table=tableTBD', 'idTBD', title);";
    }
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        if (empty($this->cType) || empty($this->aType)) { return msgAdd(lang('err_no_permission')); }
        $args = ['aType'=>$this->aType, 'cType'=>$this->cType];
        parent::mgrRowsMeta($layout, $security, $args);
        
    }
    protected function managerSettings()
    {
        parent::managerDefaults();
        $this->defaults['sort'] = clean('sort', ['format'=>'cmd','default'=>'primary_name'],'post');
        $this->defaults['order']= clean('order',['format'=>'cmd','default'=>'ASC'],         'post');
    }
    public function edit(&$layout=[])
    {
        $rID  = clean('rID',  'integer', 'get');
        if (!$security = validateAccess($this->secID, 1)) { return; }
        if (empty($this->cType) || empty($this->aType)) { return msgAdd(lang('err_no_permission')); }
        if ($this->aType=='i' && !empty($rID)) { // different edit heading
            $meta = dbMetaGet($rID, 'address_i', 'contacts');
            $title = lang('edit').': '.$meta['name_last'].', '.$meta['name_first'].' - '.$meta['title'];
        }
        $args = ['_table'=>'contacts', 'index'=>'primary_name', 'title'=>!empty($title)?$title:''];
        parent::editMeta($layout, $security, $args);
        $layout['toolbars']["tb{$this->domSuffix}"]['icons']['new'] = ['order'=>40,'hidden'=>$security>1?false:true,'events'=>['onClick'=>"accordionEdit('acc{$this->domSuffix}', 'dg{$this->domSuffix}', 'dtl{$this->domSuffix}', '{$args['title']}', '$this->moduleID/$this->pageID/edit&aType=$this->aType&dom=$this->dom&table={$args['_table']}', 0);"]];
        $layout['toolbars']["tb{$this->domSuffix}"]['icons']['copy']= ['order'=>50,'hidden'=>$security>1?false:true,'events'=>['onClick'=>"var title=prompt('".lang('msg_entry_copy')."'); if (title!=null) jsonAction('$this->moduleID/$this->pageID/copy&aType=$this->aType&refID={$args['_refID']}&table={$args['_table']}','{$args['_rID']}', title);"]];
    }
    public function copy(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 2)) { return; }
        parent::copyMeta($layout, ['index'=>'primary_name', '_table'=>'contacts']);
    }
    public function save(&$layout=[])
    {
        $rID  = clean('_rID', 'integer', 'post');
        if (!$security = validateAccess($this->secID, $rID?3:2)) { return; }
        parent::saveMeta($layout, ['_table'=>'contacts']);
    }
    public function delete(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        parent::deleteMeta($layout, ['_table'=>'contacts']);
    }
}