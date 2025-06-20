<?php
/*
 * Bizuno Extension Docs dashboard - Document Manager Extension
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
 * @filesource /controllers/bizuno/dashboards/bookmark_docs/bookmark_docs.php
 */

namespace bizuno;

class bookmark_docs
{
    public  $moduleID  = 'bizuno';
    public  $pageID    = 'docs';
    public  $methodDir = 'dashboards';
    public  $code      = 'bookmark_docs';
    public  $category  = 'quality';
    private $secID     = 'mgr_docs';
    private $metaPrefix= 'fixed_asset';
    public  $noSettings= true;
    public  $struc;
    public  $lang      = ['title'=>'Bookmarked Docs',
        'description'=> 'Lists your bookmarked documents from the Document Manager extension.'];

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
        $this->struc = [ // Admin fields
            'users' => ['order'=>10,'label'=>lang('users'), 'clean'=>'array', 'attr'=>['type'=>'users', 'value'=>[0],], 'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),'clean'=>'array', 'attr'=>['type'=>'roles', 'value'=>[-1]], 'admin'=>true]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }

    /**
     * Generates the structure for the dashboard view
     * @global object $currencies - Sets the currency values for proper display
     * @param array $layout - structure coming in
     * @param array $opts - Personalized user/menu options
     * @return modified $layout
     */
    public function render()
    {
        $result= dbMetaGet(0, 'bookmarks_phreeform', 'contacts', getUserCache('profile', 'userID'));
        msgDebug("\nRead bookmarks for userID: ".getUserCache('profile', 'userID')." to be: ".print_r($result, true));
//        $html  = html5('', ['classes'=>['easyui-datalist'],'options'=>['lines'=>'true'],'attr'=>['type'=>'ul']]);
        if (!empty($result)) {
            foreach ($result as $doc) {
//                $html .= html5('', ['options'=>['value'=>$doc['id']],'attr'=>['type'=>'li']]);
                // if the below is uncommented, the download.js javascript needs to be loaded
                //$html .= '<div style="float:right;height:16px;">'.html5('',['icon'=>'download','size'=>'small','events'=>['onClick'=>"alert('download); jsonAction(bizunoAjax+'&bizRt=proGL/docs/docExport&rID='+{$doc['id']});"]]).'</div>';
                $edit   = '<div style="float:right;height:16px;">'.html5('',['icon'=>'edit','size'=>'small','events'=>['onClick'=>"hrefClick(bizunoAjax+'&bizRt=proGL/docs/manager', {$doc['id']});"]]).'</div>';
                $content= html5('', ['icon'=>viewMimeIcon($doc['mime_type']), 'size'=>'small', 'label'=>$doc['title']]).' '.$doc['title']."</li>";
                $rows[] = viewDashList($content, $edit);
            }
        } else { $rows[] = '<div>'.lang('no_results').'</div>'; }
        return ['lists'=>$rows];
    }

    public function save() {
        $menu_id = clean('menuID', 'alpha', 'get');
        $rmID    = clean('rID', 'integer', 'get');
        $rID     = clean($this->code.'_0', 'text', 'post');
        $title   = dbGetValue(BIZUNO_DB_PREFIX.$this->methodID, 'title', "id='$rID'");
        if (!$rmID && $rID == '') { return false; } // do nothing if no title or url entered
        // fetch the current settings
        $metaVal = dbMetaGet(0, "dashboard_{$menu_id}", 'contacts', getUserCache('profile', 'userID'));
        $metaID  = metaIdxClean($metaVal);
        msgDebug("\nread dashboard meta: ".print_r($metaVal, true));
        if ($rmID) { array_splice($metaVal['settings']['data'], $rmID - 1, 1); }
        else       { $metaVal['settings']['data'][$rID] = $title; }
        dbMetaSet($metaID, "dashboard_{$menu_id}", $metaVal, 'contacts');
    }
}
