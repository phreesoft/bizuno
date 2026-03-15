<?php
/*
 * Bizuno dashboard - Company To-Do
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
 * @filesource /controllers/bizuno/dashboards/company_to_do/company_to_do.php
 */

namespace bizuno;

class company_to_do
{
    public  $moduleID  = 'bizuno';
    public  $methodDir = 'dashboards';
    public  $code      = 'company_to_do';
    public  $category  = 'general';
    private $metaPrefix= 'company_to_do';
    public  $struc;
    public  $lang      = ['title'=>'Company To Do',
    'description'=>'Creates a list of activities and things to do viewable throughout the company. Permissions are set based on the Profile settings for the role.'];

    function __construct()
    {
        $this->fieldStructure();
    }
    private function fieldStructure()
    {
        $this->struc = [ // Admin fields
            'users' => ['order'=>10,'label'=>lang('users'), 'clean'=>'array','attr'=>['type'=>'users','value'=>[0]], 'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),'clean'=>'array','attr'=>['type'=>'roles','value'=>[-1]],'admin'=>true]];
        if (!empty(getuserCache('role', 'administrate'))) { // only allow adds if admin access
            $this->struc['title']= ['order'=>50,'label'=>lang('title'),'clean'=>'text',    'attr'=>['type'=>'text','value'=>'']];
        }
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }
    public function render()
    {
        $security= getuserCache('role', 'administrate');
        $bizList = dbMetaGet(0, $this->metaPrefix); // only a single meta row
        metaIdxClean($bizList);
        msgDebug("\nwroking with values = ".print_r($bizList, true));
        if (empty($bizList['data'])) { $rows[] = '<div><span>'.lang('no_results')."</span></div>"; }
        else { for ($i=0,$j=1; $i<sizeof($bizList['data']); $i++,$j++) {
            $content= "&#9679; {$bizList['data'][$i]['title']}";
            $trash  = '<span style="float:right">'.html5('', ['icon'=>'trash','size'=>'small','events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) { dashSubmit('$this->code', $j); }"]]);
            $rows[] = viewDashList($content, !empty($security) ? $trash : '');
        } }
        return ['lists'=>$rows];
    }
    public function save() // removes a row from the list
    {
        $rmID   = clean('rID', 'integer', 'get');
        $title  = clean($this->code.'title','text', 'post');
        if (!$rmID && empty($title)) { return msgAdd(lang('bad_data')); } // do nothing if no title
        $bizList= dbMetaGet(0, $this->metaPrefix);
        $rID    = metaIdxClean($bizList);
        if ($rmID) { array_splice($bizList['data'], $rmID-1, 1); }
        else       {
            $bizList['data'][] = ['title'=>$title];
            $bizList['data'] = sortOrder($bizList['data'], 'title');
        }
        dbMetaSet($rID, $this->metaPrefix, $bizList);
    }
}
