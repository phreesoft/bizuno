<?php
/*
 * Bizuno dashboard - Company Links
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
 * @version    7.x Last Update: 2025-05-14
 * @filesource /controllers/bizuno/dashboards/company_links/company_links.php
 */

namespace bizuno;

class company_links
{
    public  $moduleID  = 'bizuno';
    public  $methodDir = 'dashboards';
    public  $code      = 'company_links';
    public  $category  = 'general';
    private $metaPrefix= 'company_links';
    public  $struc;
    public  $lang      = ['title'=>'Company Links',
        'description' => 'Lists URLs for all users as company wide links. Permissions are set based on the Profile settings for the role.'];
    
    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->fieldStructure();
    }
    private function fieldStructure()
    {
        $this->struc = [ // Admin fields
            'users' => ['order'=>10,'label'=>lang('users'), 'clean'=>'array','attr'=>['type'=>'users','value'=>[0]], 'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),'clean'=>'array','attr'=>['type'=>'roles','value'=>[-1]],'admin'=>true]];
        if (!empty(getuserCache('role', 'administrate'))) { // only allow adds if admin access
            $this->struc['title']= ['order'=>50,'label'=>lang('title'),'clean'=>'text',    'attr'=>['type'=>'text','value'=>'']];
            $this->struc['url']  = ['order'=>60,'label'=>lang('url'),  'clean'=>'url_full','attr'=>['type'=>'text','value'=>'']];
        }
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }
    public function render()
    {
        $security= getuserCache('role', 'administrate');
        $bizList = dbMetaGet(0, $this->metaPrefix); // only a single meta row
        metaIdxClean($bizList);
        if (empty($bizList['data'])) { $rows[] = '<div><span>'.lang('no_results')."</span></div>"; }
        else { for ($i=0,$j=1; $i<sizeof($bizList['data']); $i++,$j++) {
            $trash  = '<span style="float:right">'.html5('', ['icon'=>'trash','size'=>'small','events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) { dashSubmit('$this->code', $j); }"]]);
            $rows[]= viewDashList(viewFavicon($bizList['data'][$i]['url'], $bizList['data'][$i]['title'], true)." {$bizList['data'][$i]['title']}", !empty($security) ? $trash : '');
        } }
        return ['lists'=>$rows];
    }
    public function save()
    {
        $rmID   = clean('rID', 'integer', 'get');
        $title  = clean($this->code.'title','text', 'post');
        $url    = clean($this->code.'url',  'text', 'post');
        if (!$rmID && empty($title)) { return msgAdd(lang('bad_data')); } // do nothing if no title or url entered
        $bizList= dbMetaGet(0, $this->metaPrefix);
        $rID    = metaIdxClean($bizList);
        if ($rmID) { array_splice($bizList['data'], $rmID-1, 1); }
        else       {
            $bizList['data'][]= ['title'=>$title, 'url'=>$url];
            $bizList['data']  = sortOrder($bizList['data'], 'title');
        }
        dbMetaSet($rID, $this->metaPrefix, $bizList);
    }
}
