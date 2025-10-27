<?php
/*
 * Bizuno Extension - Quality
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
 * @version    7.x Last Update: 2025-10-26
 * @filesource /controllers/quality/admin.php
 */

namespace bizuno;

class qualityAdmin

{
    public $moduleID= 'quality';
    public $pageID  = 'admin';
    public $lang;
    public $settings;
    public $structure;

    function __construct()
    {
        $this->lang     = getLang($this->moduleID);
        $this->settings = getModuleCache($this->moduleID, 'settings', false, false, []);
        $this->structure= [
            'attachPath'=> ['audits'=>'data/quality/audits/', 'correctives'=>'data/quality/tickets/', 'training'=>'data/quality/objectives/'],
            'menuBar'   => ['child'=>[
                'quality' => ['order'=>70,'label'=>('quality'),                                          'icon'=>'quality','route'=>"bizuno/main/bizunoHome&menuID=quality",'child'=>[
                    'qa_ticket'=> ['order'=>50,'label'=>sprintf(lang('tbd_manager'), lang('ticket')),    'icon'=>'ticket', 'route'=>"$this->moduleID/tickets/manager"],
                    'qa_obj'   => ['order'=>60,'label'=>sprintf(lang('tbd_manager'), lang('objectives')),'icon'=>'new',    'route'=>"$this->moduleID/objectives/manager"],
                    'qa_audit' => ['order'=>70,'label'=>sprintf(lang('tbd_manager'), lang('audit')),     'icon'=>'support','route'=>"$this->moduleID/audits/manager"],
                    'qa_train' => ['order'=>80,'label'=>sprintf(lang('tbd_manager'), lang('training')),  'icon'=>'mimePpt','route'=>"$this->moduleID/training/manager"],
                    'rpt_qa'   => ['order'=>99,'label'=>('reports'),                                     'icon'=>'mimeDoc','route'=>"phreeform/main/manager&gID=qa"]]]]],
            'hooks'     => [
                'administrate'=>['roles'      =>['edit'       =>['order'=>70,'method'=>'rolesEdit'], 'save'=>['order'=>70,'method'=>'rolesSave']]],
                'inventory'   =>['build'      =>['manager'    =>['order'=>80,'method'=>'invBld']]],
                'inventory'   =>['main'       =>['manager'    =>['order'=>80,'method'=>'invMgr']]],
                'phreebooks'  =>['fulfillment'=>['fulfillEdit'=>['order'=>80,'method'=>'pbRcv']]],
                'phreebooks'  =>['main'       =>['manager'    =>['order'=>80,'method'=>'pbMgr']]],
                'quality'     =>['tickets'    =>['manager'    =>['order'=>80,'method'=>'qaMgr']]],
                'shipping'    =>['manager'    =>['manager'    =>['order'=>80,'method'=>'shipMgr']]]],
//            'lang'      => [
//                'next_qaobj_num'   => $this->lang['cor_mgr'].' - '.$this->lang['ca_num'],
//                'next_ticket_num'  => $this->lang['obj_mgr'].' - '.$this->lang['pa_num'],
//                'next_audit_num'   => $this->lang['aud_mgr'].' - '.$this->lang['task_num'],
//                'next_training_num'=> $this->lang['training_title'].' - '.$this->lang['task_num']],
            ];
        if (!empty($this->settings['manual']['manual_link'])) {
            $this->structure['menuBar']['child']['quality']['child']['QualManual'] = ['order'=>5,'label'=>lang('quality_manual'),'required'=>true,'icon'=>'quality','events'=>['onClick'=>"winHref('{$this->settings['manual']['manual_link']}');"]];
        }
    }

    /**
     * Sets the structure of the user settings for the contacts module
     * @return array - user settings
     */
    public function settingsStructure()
    {
        $fields = [
            'proc_sales'    => ['label'=>$this->lang['proc_sales'],    'parent'=>'customers','options'=>['width'=>600],'attr'=>['value'=>'']],
            'stnd_sales'    => ['label'=>$this->lang['stnd_sales'],    'parent'=>'customers','options'=>['width'=>600],'attr'=>['value'=>'']],
            'inst_sales'    => ['label'=>$this->lang['inst_sales'],    'parent'=>'customers','options'=>['width'=>600],'attr'=>['value'=>'']],
            'proc_inv_mgr'  => ['label'=>$this->lang['proc_inventory'],'parent'=>'inventory','options'=>['width'=>600],'attr'=>['value'=>'']],
            'stnd_inv_mgr'  => ['label'=>$this->lang['stnd_inventory'],'parent'=>'inventory','options'=>['width'=>600],'attr'=>['value'=>'']],
            'inst_inv_mgr'  => ['label'=>$this->lang['inst_inventory'],'parent'=>'inventory','options'=>['width'=>600],'attr'=>['value'=>'']],
            'proc_receiving'=> ['label'=>$this->lang['proc_receive'],  'parent'=>'inventory','options'=>['width'=>600],'attr'=>['value'=>'']],
            'stnd_receiving'=> ['label'=>$this->lang['stnd_receive'],  'parent'=>'inventory','options'=>['width'=>600],'attr'=>['value'=>'']],
            'inst_receiving'=> ['label'=>$this->lang['inst_receive'],  'parent'=>'inventory','options'=>['width'=>600],'attr'=>['value'=>'']],
            'proc_woProd'   => ['label'=>$this->lang['proc_build'],    'parent'=>'inventory','options'=>['width'=>600],'attr'=>['value'=>'']],
            'stnd_woProd'   => ['label'=>$this->lang['stnd_build'],    'parent'=>'inventory','options'=>['width'=>600],'attr'=>['value'=>'']],
            'inst_woProd'   => ['label'=>$this->lang['inst_build'],    'parent'=>'inventory','options'=>['width'=>600],'attr'=>['value'=>'']],
            'proc_qa_ticket'=> ['label'=>$this->lang['proc_quality'],  'parent'=>'quality',  'options'=>['width'=>600],'attr'=>['value'=>'']],
            'stnd_qa_ticket'=> ['label'=>$this->lang['stnd_quality'],  'parent'=>'quality',  'options'=>['width'=>600],'attr'=>['value'=>'']],
            'inst_qa_ticket'=> ['label'=>$this->lang['inst_quality'],  'parent'=>'quality',  'options'=>['width'=>600],'attr'=>['value'=>'']],
            'proc_shipping' => ['label'=>$this->lang['proc_shipping'], 'parent'=>'tools',    'options'=>['width'=>600],'attr'=>['value'=>'']],
            'stnd_shipping' => ['label'=>$this->lang['stnd_shipping'], 'parent'=>'tools',    'options'=>['width'=>600],'attr'=>['value'=>'']],
            'inst_shipping' => ['label'=>$this->lang['inst_shipping'], 'parent'=>'tools',    'options'=>['width'=>600],'attr'=>['value'=>'']]];
        $data = [
            'manual' => ['order'=>20,'label'=>lang('quality_manual'),'fields'=>[
                'manual_title'=> ['label'=>$this->lang['manual_title'],'parent'=>'customers','options'=>['width'=>600],'attr'=>['value'=>lang('quality_manual')]],
                'manual_link' => ['label'=>$this->lang['manual_link'], 'parent'=>'customers','options'=>['width'=>600],'attr'=>['value'=>'']]]],
            'general'=> ['order'=>30,'label'=>lang('general'),'fields'=>$fields]];
        settingsFill($data, $this->moduleID);
        return $data;
    }

    public function initialize()
    {
        // Rebuild some option values
        $metaStat= dbMetaGet('%', 'options_qa_status');
        $idxStat = metaIdxClean($metaStat); // remove the indexes
        $status = [
             '1'=>$this->lang['qa_status_1'],  '2'=>$this->lang['qa_status_2'],  '3'=>$this->lang['qa_status_3'],
             '4'=>$this->lang['qa_status_4'],  '5'=>$this->lang['qa_status_5'],  '6'=>$this->lang['qa_status_6'],
             '7'=>$this->lang['qa_status_7'],  '8'=>$this->lang['qa_status_8'], '85'=>$this->lang['qa_status_85'],
            '90'=>$this->lang['qa_status_90'],'99'=>$this->lang['qa_status_99']];
        asort($status);
        dbMetaSet($idxStat, 'options_qa_status', $status);
        // Put them in the cache for runtime access
        setModuleCache('bizuno', 'options', 'qa_status', $status);
        return true;
    }

    /**
     * Renders the quality process, standard or instruction as an embedded document
     * @param array $layout - Structure coming in
     * @return modified structure
     */
    public function renderQA(&$layout=[])
    {
        $qaIdx = clean('qaIdx', 'cmd', 'get');
        $fields= $this->settingsStructure()['general']['fields'];
        if (!array_key_exists($qaIdx, $fields)) {
            return msgAdd("The document you are looking for cannot be found", 'caution');
        }
        $html = '<iframe src="'.$fields[$qaIdx]['attr']['value'].'" width="600" height="500" frameborder="0" marginheight="0" marginwidth="0">Loading…</iframe>';
        $data = ['type'=>'divHTML','title'=>'Document','attr'=>['id'=>'qaDocs'],
            'divs'=>['iframe'=>['order'=>50,'type'=>'html','html'=>$html]]];
        $layout = array_replace_recursive($layout, $data);
    }

    public function invBld(&$layout=[])
    {
        if (!empty($this->settings['general']['proc_inv_mgr'])) {
            $layout['datagrid']['dgBuild']['source']['actions']['qaProc']= ['order'=>95,'icon'=>'steps',  'label'=>lang('qa_processes'),   'events'=>['onClick'=>"windowEdit('$this->moduleID/$this->pageID/renderQA&qaIdx=proc_woProd', 'qaDoc', '{$this->lang['proc_inventory']}', 600, 500);"]];
        }
        if (!empty($this->settings['general']['stnd_inv_mgr'])) {
            $layout['datagrid']['dgBuild']['source']['actions']['qaStnd']= ['order'=>96,'icon'=>'mimeTxt','label'=>lang('qa_standards'),   'events'=>['onClick'=>"windowEdit('$this->moduleID/$this->pageID/renderQA&qaIdx=stnd_woProd', 'qaDoc', '{$this->lang['stnd_inventory']}', 600, 500);"]];
        }
        if (!empty($this->settings['general']['inst_inv_mgr'])) {
            $layout['datagrid']['dgBuild']['source']['actions']['qaInst']= ['order'=>97,'icon'=>'mimeDoc','label'=>lang('qa_instructions'),'events'=>['onClick'=>"windowEdit('$this->moduleID/$this->pageID/renderQA&qaIdx=inst_woProd', 'qaDoc', '{$this->lang['inst_inventory']}', 600, 500);"]];
        }
    }
    public function invMgr(&$layout=[])
    {
        if (!empty($this->settings['general']['proc_inv_mgr'])) {
            $layout['datagrid']['manager']['source']['actions']['qaProc']= ['order'=>95,'icon'=>'steps',  'label'=>lang('qa_processes'),   'events'=>['onClick'=>"windowEdit('$this->moduleID/$this->pageID/renderQA&qaIdx=proc_inv_mgr', 'qaDoc', '{$this->lang['proc_inventory']}', 600, 500);"]];
        }
        if (!empty($this->settings['general']['stnd_inv_mgr'])) {
            $layout['datagrid']['manager']['source']['actions']['qaStnd']= ['order'=>96,'icon'=>'mimeTxt','label'=>lang('qa_standards'),   'events'=>['onClick'=>"windowEdit('$this->moduleID/$this->pageID/renderQA&qaIdx=stnd_inv_mgr', 'qaDoc', '{$this->lang['stnd_inventory']}', 600, 500);"]];
        }
        if (!empty($this->settings['general']['inst_inv_mgr'])) {
            $layout['datagrid']['manager']['source']['actions']['qaInst']= ['order'=>97,'icon'=>'mimeDoc','label'=>lang('qa_instructions'),'events'=>['onClick'=>"windowEdit('$this->moduleID/$this->pageID/renderQA&qaIdx=inst_inv_mgr', 'qaDoc', '{$this->lang['inst_inventory']}', 600, 500);"]];
        }
    }
    public function pbRcv(&$layout=[])
    {
        if (!empty($this->settings['general']['proc_inv_mgr'])) {
            $layout['datagrid']['manager']['source']['actions']['qaProc']= ['order'=>95,'icon'=>'steps',  'label'=>lang('qa_processes'),   'events'=>['onClick'=>"windowEdit('$this->moduleID/$this->pageID/renderQA&qaIdx=proc_receiving', 'qaDoc', '{$this->lang['proc_inventory']}', 600, 500);"]];
        }
        if (!empty($this->settings['general']['stnd_inv_mgr'])) {
            $layout['datagrid']['manager']['source']['actions']['qaStnd']= ['order'=>96,'icon'=>'mimeTxt','label'=>lang('qa_standards'),   'events'=>['onClick'=>"windowEdit('$this->moduleID/$this->pageID/renderQA&qaIdx=stnd_receiving', 'qaDoc', '{$this->lang['stnd_inventory']}', 600, 500);"]];
        }
        if (!empty($this->settings['general']['inst_inv_mgr'])) {
            $layout['datagrid']['manager']['source']['actions']['qaInst']= ['order'=>97,'icon'=>'mimeDoc','label'=>lang('qa_instructions'),'events'=>['onClick'=>"windowEdit('$this->moduleID/$this->pageID/renderQA&qaIdx=inst_receiving', 'qaDoc', '{$this->lang['inst_inventory']}', 600, 500);"]];
        }
    }
    public function pbMgr(&$layout=[])
    {
        if (!empty($this->settings['general']['proc_inv_mgr'])) {
            $layout['datagrid']['manager']['source']['actions']['qaProc']= ['order'=>95,'icon'=>'steps',  'label'=>lang('qa_processes'),   'events'=>['onClick'=>"windowEdit('$this->moduleID/$this->pageID/renderQA&qaIdx=proc_sales', 'qaDoc', '{$this->lang['proc_inventory']}', 600, 500);"]];
        }
        if (!empty($this->settings['general']['stnd_inv_mgr'])) {
            $layout['datagrid']['manager']['source']['actions']['qaStnd']= ['order'=>96,'icon'=>'mimeTxt','label'=>lang('qa_standards'),   'events'=>['onClick'=>"windowEdit('$this->moduleID/$this->pageID/renderQA&qaIdx=stnd_sales', 'qaDoc', '{$this->lang['stnd_inventory']}', 600, 500);"]];
        }
        if (!empty($this->settings['general']['inst_inv_mgr'])) {
            $layout['datagrid']['manager']['source']['actions']['qaInst']= ['order'=>97,'icon'=>'mimeDoc','label'=>lang('qa_instructions'),'events'=>['onClick'=>"windowEdit('$this->moduleID/$this->pageID/renderQA&qaIdx=inst_sales', 'qaDoc', '{$this->lang['inst_inventory']}', 600, 500);"]];
        }
    }
    public function qaMgr(&$layout=[])
    {
        if (!empty($this->settings['general']['proc_inv_mgr'])) {
            $layout['datagrid']['dgTicket']['source']['actions']['qaProc']= ['order'=>95,'icon'=>'steps',  'label'=>lang('qa_processes'),   'events'=>['onClick'=>"windowEdit('$this->moduleID/$this->pageID/renderQA&qaIdx=proc_qa_ticket', 'qaDoc', '{$this->lang['proc_inventory']}', 600, 500);"]];
        }
        if (!empty($this->settings['general']['stnd_inv_mgr'])) {
            $layout['datagrid']['dgTicket']['source']['actions']['qaStnd']= ['order'=>96,'icon'=>'mimeTxt','label'=>lang('qa_standards'),   'events'=>['onClick'=>"windowEdit('$this->moduleID/$this->pageID/renderQA&qaIdx=stnd_qa_ticket', 'qaDoc', '{$this->lang['stnd_inventory']}', 600, 500);"]];
        }
        if (!empty($this->settings['general']['inst_inv_mgr'])) {
            $layout['datagrid']['dgTicket']['source']['actions']['qaInst']= ['order'=>97,'icon'=>'mimeDoc','label'=>lang('qa_instructions'),'events'=>['onClick'=>"windowEdit('$this->moduleID/$this->pageID/renderQA&qaIdx=inst_qa_ticket, 'qaDoc', '{$this->lang['inst_inventory']}', 600, 500);"]];
        }
    }
    public function shipMgr(&$layout=[])
    {
        if (!empty($this->settings['general']['proc_inv_mgr'])) {
            $layout['datagrid']['dgShipping']['source']['actions']['qaProc']= ['order'=>95,'icon'=>'steps',  'label'=>lang('qa_processes'),   'events'=>['onClick'=>"windowEdit('$this->moduleID/$this->pageID/renderQA&qaIdx=proc_shipping', 'qaDoc', '{$this->lang['proc_inventory']}', 600, 500);"]];
        }
        if (!empty($this->settings['general']['stnd_inv_mgr'])) {
            $layout['datagrid']['dgShipping']['source']['actions']['qaStnd']= ['order'=>96,'icon'=>'mimeTxt','label'=>lang('qa_standards'),   'events'=>['onClick'=>"windowEdit('$this->moduleID/$this->pageID/renderQA&qaIdx=stnd_shipping', 'qaDoc', '{$this->lang['stnd_inventory']}', 600, 500);"]];
        }
        if (!empty($this->settings['general']['inst_inv_mgr'])) {
            $layout['datagrid']['dgShipping']['source']['actions']['qaInst']= ['order'=>97,'icon'=>'mimeDoc','label'=>lang('qa_instructions'),'events'=>['onClick'=>"windowEdit('$this->moduleID/$this->pageID/renderQA&qaIdx=inst_shipping', 'qaDoc', '{$this->lang['inst_inventory']}', 600, 500);"]];
        }
    }
    /**
     * Extends the Roles - Edit - PhreeBooks tab to add Sales and Purchase access
     */
    public function rolesEdit(&$layout=[])
    {
        if (!$security = validateAccess('admin', 3)) { return; }
        $rID     = clean('rID', 'integer', 'get');
        $role    = dbMetaGet($rID, 'bizuno_role');
        $selTrain= !empty($role['training']) ? implode(',', $role['training']) : '';
        $rows    = dbMetaGet('%', 'training');
        $lstTrain= [['id'=>0, 'text'=>lang('none')]]; // get the list of current training tasks
        foreach ($rows as $row) { $lstTrain[] = ['id'=>$row['_rID'], 'text'=>$row['title']]; }
        if (empty($layout['tabs']['tabRoles']['divs']['quality']['divs']['props'])) {
            $layout['tabs']['tabRoles']['divs']['quality']['divs']['props'] = ['order'=>20,'type'=>'panel','classes'=>['block50'],'key'=>'qualSettings'];
            $layout['panels']['qualSettings'] = ['label'=>lang('settings'),'type'=>'fields','keys'=>[]];
        }
        $layout['fields']['group_qa'] = ['order'=>50,'label'=>$this->lang['role_qa'], 'tip'=>'',
            'attr'=>['type'=>'checkbox','checked'=>!empty($role['groups']['qa']) ?true:false]];
        $layout['fields']['training'] = ['order'=>52,'label'=>$this->lang['roles_title'],'options'=>['multiple'=>'true'],'values'=>$lstTrain,'tip'=>$this->lang['roles_description'],
            'attr'=>['type'=>'select','name'=>'training[]','value'=>$selTrain]];
        $layout['panels']['qualSettings']['keys'][] = 'training';
        $layout['panels']['qualSettings']['keys'][] = 'group_qa';
    }

    /**
     * Extends the Roles settings to Save the PhreeBooks Specific settings
     * @return boolean null - updates the database
     */
    public function rolesSave()
    {
        if (empty($rID = clean('_rID', 'integer', 'post'))){ return; }
        if (!$security = validateAccess('admin', 3))     { return; }
        $role = dbMetaGet($rID, 'bizuno_role');
        metaIdxClean($role);
        $role['training']    = clean('training', 'array',  'post');
        $role['groups']['qa']= clean('group_qa', 'boolean','post');
        dbMetaSet($rID, 'bizuno_role', $role);
    }

    /**
     * Administration page for this extension
     * @param array $layout - current structure
     * @return modified $layout
     */
    public function adminHome(&$layout=[])
    {
        if (!$security = validateAccess('admin', 1)) { return; }
        $data = ['tabs'=>['tabAdmin'=>['divs'=>[
            'tabTrain' => ['order'=>20,'label'=>lang('tasks_training'),'type'=>'html','html'=>'','options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/adminTraining/manager'"]],
            'tabAudit' => ['order'=>30,'label'=>lang('tasks_audit'),   'type'=>'html','html'=>'','options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/adminAudits/manager'"]]]]]];
        $layout = array_replace_recursive($layout, adminStructure($this->moduleID, $this->settingsStructure(), $this->lang), $data);
    }

    /**
     * Saves the users settings
     */
    public function adminSave()
    {
        readModuleSettings($this->moduleID, $this->settingsStructure());
    }
}
