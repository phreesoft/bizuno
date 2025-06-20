<?php
/*
 * Bizuno extension extISO9001 dashboard - Open Corrective Actions
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
 * @filesource /controllers/quality/dashboards/qa_stop_work/qa_stop_work.php
 */

namespace bizuno;

class qa_stop_work
{
    public  $moduleID = 'quality';
    public  $methodID = 'extISO9001';
    public  $methodDir= 'dashboards';
    public  $code     = 'qa_stop_work';
    public  $secID    = 'extISO9001';
    public  $category = 'quality';
    public  $struc;
    public  $lang     = ['title' => 'Current Stop Work',
        'description' => 'Lists open quality tickets that prompted a Stop Work situation.'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->fieldStructure();
    }

    /**
     * Sets the page fields with their structure
     * @return array - page structure
     */
    private function fieldStructure()
    {
        $order = [['id'=>'asc','text'=>lang('decreasing')],['id'=>'desc','text'=>lang('decreasing')]];
        $this->struc = [
            // Admin fields
            'users'   => ['order'=>10,'label'=>lang('users'),        'clean'=>'array',   'attr'=>['type'=>'users',   'value'=>[0],],  'admin'=>true],
            'roles'   => ['order'=>20,'label'=>lang('groups'),       'clean'=>'array',   'attr'=>['type'=>'roles',   'value'=>[-1]],  'admin'=>true],
            // User fields
            'range'   => ['order'=>40,'label'=>lang('disp_due'),     'clean'=>'boolean', 'attr'=>['type'=>'select',  'value'=>8],     'values'=>viewKeyDropdown(getModuleCache('bizuno', 'options', 'qa_status'), true)],
            'trim'    => ['order'=>70,'label'=>lang('truncate_fit'), 'clean'=>'integer', 'attr'=>['type'=>'spinner', 'value'=>20],    'options'=>['min'=>10,'max'=>80,'width'=>100]],
            'order'   => ['order'=>80,'label'=>lang('sort_order'),   'clean'=>'db_field','attr'=>['type'=>'select',  'value'=>'desc'],'values'=>$order]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }

    /**
     * Generates the structure for the dashboard view
     * @param array $opts - Personalized user/menu options
     * @return modified $layout
     */
    public function render($opts=[])
    {
        $rows   = [];
        $action = BIZUNO_AJAX."&bizRt=$this->moduleID/tickets/exportData&type=status&range={$opts['range']}";
        $iconExp= ['attr'=>['type'=>'button','value'=>lang('export_data')],'events'=>['onClick'=>"jqBiz('#form{$this->code}').submit();"]];
        $filter = "journal_id=30 AND closed='0'"; // AND printed=2
        $order  = $opts['order']=='desc' ? 'post_date DESC, invoice_num DESC' : 'post_date, invoice_num';
        $result = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', $filter, $order, ['id','invoice_num','description','post_date']);
        foreach ($result as $entry) { // build the list
            if (!in_array($entry['printed'], ['2'])) { continue; } // printed is db version of status
            $left   = viewDate($entry['post_date'])." - ".viewText($entry['description'], $opts['trim']);
            $right  = '';
            $action = html5('', ['styles'=>['background'=>'red','color'=>'#fff'],'events'=>['onClick'=>"winHref(bizunoHome+'&bizRt=$this->moduleID/correctives/manager&rID={$entry['id']}');"],'attr'=>['type'=>'button','value'=>"#{$entry['invoice_num']}"]]);
            $rows[] = viewDashLink($left, $right, $action);
        }
        if (empty($rows)) { $rows[] = '<div><span>'.lang('no_results').'</span></div>'; }
        $html = !empty($result) ? '<form id="form'.$this->code.'" action="'.$action.'">'.html5('', $iconExp).'</form>' : '';
        return ['lists'=>$rows, 'html'=>$html, 'jsHead'=>"ajaxDownload('form{$this->code}');"];
      }
}
