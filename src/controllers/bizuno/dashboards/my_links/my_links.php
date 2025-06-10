<?php
/*
 * Bizuno dashboard - My Links
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
 * @version    7.x Last Update: 2025-06-10
 * @filesource /controllers/bizuno/dashboards/my_links/my_links.php
 *
 */

namespace bizuno;

class my_links
{
    public $moduleID = 'bizuno';
    public $methodDir= 'dashboards';
    public $code     = 'my_links';
    public $category = 'general';
    public $struc;
    public $lang = ['title'=>'My Links',
        'description'=> 'Lists URLs for personal use with a single click. Opens link in a new window.'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->fieldStructure();
    }
    private function fieldStructure()
    {
        $this->struc = [ // Admin fields
            'users' => ['order'=>10,'label'=>lang('users'), 'clean'=>'array',   'attr'=>['type'=>'users','value'=>[0]], 'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),'clean'=>'array',   'attr'=>['type'=>'roles','value'=>[-1]],'admin'=>true],
            'title' => ['order'=>50,'label'=>lang('title'), 'clean'=>'text',    'attr'=>['type'=>'text','value'=>'']],
            'url'   => ['order'=>60,'label'=>lang('url'),   'clean'=>'url_full','attr'=>['type'=>'text','value'=>'']]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }
    public function render($opts=[])
    {
        if (empty($opts['data'])) { $rows[] = '<div><span>'.lang('no_results')."</span></div>"; }
        else { for ($i=0,$j=1; $i<sizeof($opts['data']); $i++,$j++) {
            $trash = '<span style="float:right">'.html5('', ['icon'=>'trash','size'=>'small','events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) { dashSubmit('$this->code', $j); }"]]);
            $rows[]= viewDashList(viewFavicon($opts['data'][$i]['url'], $opts['data'][$i]['title'], true)." {$opts['data'][$i]['title']}", $trash);
        } }
        return ['lists'=>$rows];
    }
    public function save(&$usrMeta)
    {
        $rmID = clean('rID', 'integer', 'get');
        $title= clean($this->code.'title','text', 'post');
        $url  = clean($this->code.'url',  'text', 'post');
        if (empty($rmID) && empty($url)) { return msgAdd(lang('bad_data')); } // do nothing if no title or url entered
        if ($rmID) { array_splice($usrMeta[$this->code]['opts']['data'], $rmID-1, 1); }
        else       { 
            $usrMeta[$this->code]['opts']['data'][] = ['title'=>$title, 'url'=>$url]; }
            $usrMeta[$this->code]['opts']['data'] = sortOrder($usrMeta[$this->code]['opts']['data'], 'title');
    }
}
