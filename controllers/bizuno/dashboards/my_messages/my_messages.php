<?php
/*
 * Bizuno dashboard - My Messages
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
 * @version    7.x Last Update: 2025-11-19
 * @filesource /controllers/bizuno/dashboards/my_messages/my_messages.php
 */

namespace bizuno;

class my_messages
{
    public $moduleID = 'bizuno';
    public $methodDir= 'dashboards';
    public $code     = 'my_messages';
    public $category = 'general';
    public  $struc;
    public $lang = ['title'=>'My Messages',
        'description'=> 'Allows sending and receiving messages to other users.',
        'send_message_to' => 'Send message to:',
        'msg_no_user_found' => "The message was not sent, could be the user does not have this dashboard installed!"];

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
            'users' => ['order'=>10,'label'=>lang('users'), 'clean'=>'array','attr'=>['type'=>'users','value'=>[0]], 'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),'clean'=>'array','attr'=>['type'=>'roles','value'=>[-1]],'admin'=>true]];
        if (!empty(getUserCache('role', 'administrate'))) { // only allow adds if admin access
            $this->struc['title'] = ['order'=>50,'label'=>lang('title'),'clean'=>'text','attr'=>['type'=>'text','value'=>'']];
        }
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }

    /**
     * Generates the structure for the dashboard view
     * @global object $currencies - Sets the currency values for proper display
     * @param array $layout - structure coming in
     * @param array $opts - Personalized user/menu options
     * @return modified $layout
     */
    public function render($opts=[])
    {
        if (empty($opts['data'])) { $rows[] = "<div><span>".lang('no_results')."</span></div>"; }
        else { for ($i=0,$j=1; $i<sizeof($opts['data']); $i++,$j++) {
            $content= "&#9679; {$opts['data'][$i]['title']}";
            $trash  = '<span style="float:right">'.html5('', ['icon'=>'trash','size'=>'small','events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) { dashSubmit('$this->code', $j); }"]]);
            $rows[] = viewDashList($content, $trash);
        } }
        return ['lists'=>$rows];
    }
    public function save(&$usrMeta)
    {
        $rmID = clean('rID', 'integer', 'get');
        $title= clean($this->code.'title', 'text', 'post');
        if (!$rmID && empty($title)) { return; } // do nothing if no title or url entered
        if ($rmID) { array_splice($usrMeta[$this->code]['opts']['data'], $rmID-1, 1); }
        else { 
            $usrMeta[$this->code]['opts']['data'][]= ['title'=>$title];
            $usrMeta[$this->code]['opts']['data']  = sortOrder($usrMeta[$this->code]['opts']['data'], 'title');
        }
    }
}
