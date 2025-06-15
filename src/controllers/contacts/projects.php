<?php
/*
 * Bizuno Pro - CRM Customer Projects
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
 * @version    7.x Last Update: 2025-06-12
 * @filesource /controllers/contacts/projects.php
 *
 * @TODO - Add last_update field to database and use as main sort DESC
 */

namespace bizuno;

class contactsProjects extends mgrJournal
{
    public    $moduleID  = 'contacts';
    public    $pageID    = 'projects';
    protected $secID     = 'projects';
    protected $domSuffix = 'Projects';
    protected $metaPrefix= 'crm_project';
    protected $nextRefIdx= 'next_cproj_num';

    function __construct()
    {
        parent::__construct();
        $this->markets = ['' =>lang('all'),
            'a' =>'Automotive',     'v'=>'Aviation',        'b'=>'Boat/Marine','cm'=>'Contract Mfg','c' =>'Corporate',
            'd' =>'Dealer/Reseller','e'=>'Electronics',     'f'=>'Fitness',    'g' =>'Government',  'ht'=>'Hospitality',
            'in'=>'Industrial',     'i'=>'ISD/School',      'l'=>'Landscape',  'm' =>'Medical',     'n' =>'Natural Gas/Oil',
            'p' =>'Municipalities', 'r'=>'Reserve/Critical','s'=>'Solar',      'sc'=>'Supply Chain','t' =>'Telecom',
            'tr'=>'Transportation', 'u'=>'Utility',         'w'=>'Wind',       'o' =>'Other'];
        $this->status  = [''=>lang('all'),'n'=>'Prospect','c'=>'Contacting','p'=>'POC Identified',
            'f'=>'Follow-up', 'q'=>'Quote', 's'=>'Sale', 'e'=>'Engineering', 'd'=>'Doc Development',
            'x'=>'Closed',    'z'=>'No Opportunity'];
        $this->attachPath= getModuleCache($this->moduleID, 'properties', 'attachPath', $this->pageID);
        $this->managerSettings();
        $this->fieldStructure();
    }
    protected function fieldStructure()
    {
        $reps = viewRoleDropdown('all');
        $this->struc = [ 
            'id'           => ['panel'=>'general','order'=> 1,                                      'clean'=>'integer',  'attr'=>['type'=>'hidden', 'value'=>0]],
            'proj_num'     => ['panel'=>'general','order'=> 1,'label'=>$this->lang['proj_num'],     'clean'=>'filename', 'attr'=>['type'=>'hidden', 'value'=>0]],
            'title'        => ['panel'=>'general','order'=>10,'label'=>lang('title'),               'clean'=>'text',     'attr'=>['value'=>'']],
            'contact_id'   => ['panel'=>'general','order'=>15,'label'=>lang('ctype_c'),             'clean'=>'integer',  'attr'=>['type'=>'hidden', 'value'=>''],'defaults'=>['type'=>'c', 'data'=>'projContact', 'callback'=>'']],
            'oem'          => ['panel'=>'general','order'=>20,'label'=>$this->lang['oem'],          'clean'=>'integer',  'attr'=>['type'=>'hidden', 'value'=>'']],
            'market'       => ['panel'=>'general','order'=>25,'label'=>$this->lang['market'],       'clean'=>'char',     'values'=>viewKeyDropdown($this->markets, true), 'attr'=>['type'=>'hidden', 'value'=>'']],
            'status'       => ['panel'=>'general','order'=>30,'label'=>lang('status'),              'clean'=>'alpha_num','values'=>viewKeyDropdown($this->status, true),'attr'=>['type'=>'hidden', 'value'=>'']],
            'created_by'   => ['panel'=>'general','order'=>35,'label'=>$this->lang['created_by'],   'clean'=>'integer',  'values'=>$reps, 'attr'=>['type'=>'select'], 'value'=>0],
            'created_date' => ['panel'=>'general','order'=>40,'label'=>$this->lang['created_date'], 'clean'=>'date',     'attr'=>['type'=>'date', 'value'=>biz_date()]],
            'assigned_to'  => ['panel'=>'general','order'=>45,'label'=>$this->lang['assigned_to'],  'clean'=>'integer',  'values'=>$reps, 'attr'=>['type'=>'select', 'value'=>0]],
            'assigned_date'=> ['panel'=>'general','order'=>50,'label'=>$this->lang['assigned_date'],'clean'=>'date',     'attr'=>['type'=>'date', 'value'=>'']],
            'working_by'   => ['panel'=>'general','order'=>55,'label'=>$this->lang['working_by'],   'clean'=>'integer',  'values'=>$reps, 'attr'=>['type'=>'select', 'value'=>0]],
            'reminder_date'=> ['panel'=>'general','order'=>60,'label'=>$this->lang['reminder_date'],'clean'=>'date',     'attr'=>['type'=>'date', 'value'=>'']],
            'notes'        => ['panel'=>'notes',  'order'=>10,                                      'clean'=>'text',     'attr'=>['type'=>'textarea', 'value'=>'']]];
    }
    protected function managerGrid($security=0, $args=[])
    {
        $data    = array_replace_recursive(parent::gridBase($security, $args), [
            'source' => [
                'search' => ['title', 'contact_id', 'proj_num'],
                'filters'=> [
                    'market' => ['order'=>10,'label' =>$this->lang['market'],'values'=>viewKeyDropdown($this->markets),'break'=>true,'attr'=>['type'=>'select','value'=>$this->defaults['market']]],
                    'status' => ['order'=>20,'label' =>lang('status'),       'values'=>viewKeyDropdown($this->status), 'break'=>true,'attr'=>['type'=>'select','value'=>$this->defaults['status']]]]],
            'columns'=> [
                'ref_num'     => ['order'=>10, 'label'=>$this->lang['proj_num'],'attr'=>['sortable'=>true, 'resizable'=>true]],
                'title'       => ['order'=>20, 'label'=>lang('title'),          'attr'=>['sortable'=>true, 'resizable'=>true]],
                'contact_id'  => ['order'=>30, 'label'=>lang('primary_name'),   'attr'=>['sortable'=>true, 'resizable'=>true],'format'=>'contactName'],
                'market'      => ['order'=>40, 'label'=>$this->lang['market'],  'attr'=>['sortable'=>true, 'resizable'=>true],'events'=>['formatter'=>"function(value,row) { return bizQualMarkets[value]; }"]],
                'status'      => ['order'=>50, 'label'=>lang('status'),         'attr'=>['sortable'=>true, 'resizable'=>true],'events'=>['formatter'=>"function(value,row) { return bizQualStatuses[value]; }"]],
                'created_date'=> ['order'=>70, 'label'=>lang('date_first'),     'attr'=>['sortable'=>true, 'resizable'=>true],'format'=>'date']]]);
        return $data;
    }
    protected function managerSettings()
    {
        parent::managerDefaults();
        $this->defaults['sort']  = clean('sort',  ['format'=>'cmd',      'default'=>'post_date'],'post');
        $this->defaults['status']= clean('status',['format'=>'alpha_num','default'=>''],         'post');
        $this->defaults['market']= clean('market',['format'=>'alpha_num','default'=>''],         'post');
    }
    /******************************** Journal Manager ********************************/
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $cID = clean('cID', 'integer', 'get');
        parent::managerMain($layout);
        $layout['jsHead']['vars'] = "var bizQualStatuses = ".json_encode($this->status).";\nvar bizQualMarkets = ".json_encode($this->markets).";";
        if (!empty($cID)) { $layout['type'] = 'divHTML'; }
    }
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::mgrRowsMeta($layout);
        if ($this->defaults['status']=='') { unset($layout['metagrid']['source']['filters']['status']); } // status=all so do test it
        if ($this->defaults['market']=='') { unset($layout['metagrid']['source']['filters']['market']); } // market=all so do test it
    }
    public function edit(&$layout=[])
    {
        $rID = clean('rID', 'integer', 'get');
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::editMeta($layout);
        // add the attachment panel
        $layout['divs']['content']['divs']['atch'] = ['order'=>80,'type'=>'panel','key'=>"atch{$this->domSuffix}",'classes'=>['block50']];
        $layout['panels']["atch{$this->domSuffix}"]= ['type'=>'attach','attr'=>['id'=>"atch{$this->domSuffix}"],'defaults'=>['path'=>$this->attachPath, 'prefix'=>"rID_{$rID}_"]];
        // add contact details
        $name  = !empty($layout['fields']['contact_id']['attr']['value']) ? dbGetValue(BIZUNO_DB_PREFIX."contacts", ['id', 'primary_name'], "ref_id={$layout['fields']['contact_id']['attr']['value']} AND type='m'") : ['id'=>0, 'primary_name'=>''];        
        $layout['jsHead']['cName'] = "var projContact = ".json_encode([['id'=>name['id'], 'primary_name'=>$name['primary_name']]]).";";
    }
    public function save(&$layout=[])
    {
        $rID = clean('rID', 'integer', 'post');
        if (!$security = validateAccess($this->secID, $rID?3:2)) { return; }
        parent::saveMeta($layout);
    }
    public function delete(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        parent::deletMeta($layout);
    }
}