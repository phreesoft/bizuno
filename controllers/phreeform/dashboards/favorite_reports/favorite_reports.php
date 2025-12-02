<?php
/*
 * Phreeform dashboard - Favorite Reports
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
 * @version    7.x Last Update: 2025-09-11
 * @filesource /controllers/phreeform/dashboards/favorite_reports/favorite_reports.php
 */

namespace bizuno;

class favorite_reports
{
    public  $moduleID  = 'phreeform';
    public  $methodDir = 'dashboards';
    public  $code      = 'favorite_reports';
    public  $category  = 'bizuno';
    public  $struc     = [];
    private $allRpts   = [];
    public  $lang      = ['title'=>'Favorite Reports',
        'description'=> 'Lists your favorite reports for quick display. Unique lists can be made for each menu heading.'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->allRpts = getMetaCommon('phreeform_cache');
        $this->fieldStructure();
    }
    private function fieldStructure()
    {
        $reports= [['id'=>'0', 'text'=>lang('select')]];
        foreach ($this->allRpts as $row) { if (validateUsersRoles($row)) { $reports[] = ['id'=>$row['id'], 'text'=>$row['title']]; } }
        $this->struc = [ // Admin fields
            'users' => ['order'=>10,'label'=>lang('users'), 'clean'=>'array',  'attr'=>['type'=>'users','value'=>[ 0]],'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),'clean'=>'array',  'attr'=>['type'=>'roles','value'=>[-1]],'admin'=>true],
            // User fields
            'rptID' => ['order'=>40,                        'clean'=>'integer','attr'=>['type'=>'select','value'=>0],'values'=>$reports]];
        metaPopulate($this->struc, getMetaDashboard($this->code));
    }
    public function render($opts=[])
    {
        msgDebug("\nEntering {$this->code}:render with opts = ".print_r($opts, true));
        if ( empty($opts['data'])) { $rows[] = '<div><span>'.lang('no_results').'</span></div>'; }
        else { 
            foreach ($opts['data'] as $rptID) {
                if (empty($rptID)) { msgDebug("\nrptID is empty? Skipping"); continue; }
                $rpt = $this->allRpts['r'.$rptID];
                if (empty($rpt)) { msgDebug("\nReport ID $rptID not found"); continue; } // report is missing?
                $theList[] = $rpt;
            }
            $sorted = sortOrder($theList, 'title');
            foreach ($sorted as $row) {
                $content= html5('', ['icon'=>viewMimeIcon($row['mime_type']),'events'=>['onClick'=>"winOpen('phreeform', 'phreeform/render/open&rID={$row['id']}');"]]).viewText($row['title']);
                $trash  = '<span style="float:right">'.html5('', ['icon'=>'trash','size'=>'small','events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) { dashSubmit('$this->code', ".$row['id']."); }"]]);
                $rows[] = viewDashList($content, $trash);
            }
        }
        return ['lists'=>$rows];
    }
    public function save(&$usrMeta)
    {
        $rmID = clean('rID', 'integer', 'get');
        $rptID= clean($this->code.'rptID', 'integer', 'post');
        if (empty($rmID) && empty($rptID)) { return; } // do nothing if no title or url entered
        if ($rmID) { array_splice($usrMeta[$this->code]['opts']['data'], array_search($rmID, $usrMeta[$this->code]['opts']['data']), 1); }
        else       { $usrMeta[$this->code]['opts']['data'][] = $rptID; }
    }
}
