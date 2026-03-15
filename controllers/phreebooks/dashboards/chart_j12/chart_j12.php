<?php
/*
 * PhreeBooks dashboard - Sales Summary - chart form
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
 * @version    7.x Last Update: 2026-03-15
 * @filesource /controllers/phreebooks/dashboards/chart_j12/chart_j12.php
 *
 */

namespace bizuno;

class chart_j12
{
    public  $moduleID = 'phreebooks';
    public  $methodDir= 'dashboards';
    public  $code     = 'chart_j12';
    public  $secID    = 'j12_mgr';
    public  $category = 'customers';
    public  $struc;
    private $dates;
    public  $lang     = ['title'=>'Sales Summary',
        'description' => 'Displays sales summary by day, week, month, quarter or year. Settings are available for enhanced security and control.',
        'chart_title' => '%s - %s Total Invoices/Credits for %s'];
    private $pieces   = 10; // number of slices in the pie
    private $journalID= 12;
    
    function __construct()
    {
        $this->dates = localeDates(true, true, true, false, true);
        $this->fieldStructure();
    }
    private function fieldStructure()
    {
        $admin = !empty(getUserCache('role', 'administrate')) ? true : false;
        $roles = viewRoleDropdown();
        array_unshift($roles, ['id'=>-1, 'text'=>lang('all')]);
        $this->struc = [
            // Admin fields
            'users' => ['order'=>10,'label'=>lang('users'),    'clean'=>'array',  'attr'=>['type'=>'users',   'value'=>[0]], 'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),   'clean'=>'array',  'attr'=>['type'=>'roles',   'value'=>[-1]],'admin'=>true],
            'reps'  => ['order'=>30,'label'=>lang('just_reps'),'clean'=>'boolean','attr'=>['type'=>'selNoYes','value'=>0],   'admin'=>true],
            // User fields
            'selRep'=> ['order'=>40,'label'=>lang('rep_id_c'), 'clean'=>'integer','attr'=>['type'=>$admin?'select':'hidden','value'=>0],'values'=>$roles],
            'range' => ['order'=>80,'label'=>lang('range'),    'clean'=>'char',   'attr'=>['type'=>'select',  'value'=>'l'],'values'=>viewKeyDropdown($this->dates)]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
        if (!array_key_exists($this->struc['range']['attr']['value'], $this->dates)) { $this->struc['range']['attr']['value'] = 'l'; }
    }
    public function render($opts=[])
    {
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/functions.php', 'phreebooksProcess', 'function');
        $selRep = !empty($opts['reps']) && empty(getUserCache('role', 'administrate')) ? 0 : $opts['selRep'];
        $action = BIZUNO_URL_AJAX."&bizRt=$this->moduleID/tools/exportSales&range={$opts['range']}&selRep=$selRep";
        $cData  = chartSales($this->journalID, $opts['range'], $this->pieces, $selRep);
        $title  = sprintf($this->lang['chart_title'], $this->dates[$opts['range']], $cData['count'], viewFormat($cData['total'], 'currency'));
        $legend = !empty(getModuleCache('bizuno','settings','general','hide_filters',0)) ? lang('filter').": {$this->dates[$opts['range']]}" : '';
        return ['type'=>'gChart', 'title'=>$title, 'legend'=>$legend, 'data'=>$cData['chart'], 'callback'=>$action];
    }
}
