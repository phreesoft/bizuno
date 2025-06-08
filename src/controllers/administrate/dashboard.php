<?php
/*
 * Dashboard administration
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
 * @filesource /controllers/administrate/dashboard.php
 */

namespace bizuno;

class administrateDashboard
{
    public    $moduleID= 'administrate';
    public    $pageID  = 'dashboard';
    protected $secID   = 'admin';

    function __construct()
    {
        $this->lang = getLang($this->moduleID);
    }

    /**
     * manager either at admin (all dashboards) or at user/menu level
     * @param array $layout - structure entering the method
     * @return array - $layout modified with the dashboard settings
     */
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        $data = ['type'=>'divHTML',
            'title'=> sprintf(lang('tbd_manager'), lang('dashboard')),
            'divs' => [
                'heading' => ['order'=> 5,'type'=>'html',   'html'=>'<h1>'.lang('admin_dashboard').'</h1>'],
                'adminSet'=> ['order'=>50,'type'=>'tabs',   'key' =>'tabSettings']],
            'tabs' => ['tabSettings'=> ['attr'=>['tabPosition'=>'left']]]];
        $this->allDash = dbMetaGet(0, 'dashboards'); // Fetch list of all dashboards
        metaIdxClean($this->allDash); // remove the row id, etc
        msgDebug("\nRead all dashboards from meta = ".print_r($this->allDash, true));
        $tree = [];
        foreach ($this->allDash as $dashID => $opts) { // put them into the tabbed lists
            $tree[$opts['group']][$dashID] = ['title'=>$opts['title'], 'description'=>$opts['description']];
        }
//      msgDebug("\nTree is now: ".print_r($tree, true));
        $order= 20;
        foreach ($tree as $group => $dashIDs) {
            $ordered = sortOrder($dashIDs, 'title');
            $html  = '<table style="border-collapse:collapse;width:100%">'."<tbody>\n";
            $html .= '<tr><th>'.lang('title').'</th><th>'.lang('description').'</th><th>'.lang('action').'</th></tr>';
            foreach (array_keys($ordered) as $dashID) { $html .= $this->viewDashboard($dashID); }
            $html .= " </tbody>\n</table>\n";
            $data['tabs']['tabSettings']['divs'][$group] = ['order'=>$order,'label'=>lang($group),'type'=>'html','html'=>$html];
            $order++;
        }
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Generates the view for modules methods including any dashboards
     * @param string $dashID - Dashboard ID
     * @return array - HTML code for the structure
     */
    private function viewDashboard($dashID)
    {
        if (!empty($this->allDash[$dashID]['hidden'])) { return; }
        $fields= [
            'btnMethodProp'=> ['icon'=>'settings'],
            'settingSave'  => ['icon'=>'save']];
        $html  = "  <tr>\n".'<td valign="top"><b>'.$this->allDash[$dashID]['title'].'</b></td>';
        $html .= "    <td><div>".$this->allDash[$dashID]['description']."</div>";
        $html .= '<div id="dash_'.$dashID.'" style="display:none;" class="layout-expand-over">';
        $html .= html5("frmMethod_$dashID", ['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=administrate/dashboard/save&dashID=$dashID"]]);
        $fqcn  = "\\bizuno\\$dashID";
        bizAutoLoad("{$this->allDash[$dashID]['path']}$dashID.php", $fqcn);
        $cls   = new $fqcn();
        foreach ($cls->struc as $setting => $values) {
            $mult = !empty($values['attr']['multiple']) || in_array($setting, ['users', 'roles']) ? '[]' : '';
            $html .= html5($dashID.'_'.$setting.$mult, $values)."<br />\n";
        }
        $fields['settingSave']['events']['onClick'] = "jqBiz('#frmMethod_{$dashID}').submit();";
        $html .= '<div style="text-align:right">'.html5("imgMethod_{$dashID}", $fields['settingSave']).'</div>';
        $html .= "</form></div>";
        htmlQueue("ajaxForm('frmMethod_$dashID');", 'jsReady');
        $html .= "    </td>\n".'  <td valign="top" nowrap="nowrap" style="text-align:right;">' . "\n";
        $fields['btnMethodProp']['events']['onClick'] = "jqBiz('#dash_{$dashID}').toggle('slow');";
        $html .= html5("prop_{$dashID}", $fields['btnMethodProp'])."\n";
        $html .= "    </td>\n  </tr>\n".'<tr><td colspan="4"><hr /></td></tr>'."\n";
        return $html;
    }

    /**
     * Saves default dashboard settings 
     * @param array $layout - structure entering the method
     * @return array - $layout modified with the dashboard settings
     */
    public function save(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        $dashID = clean('dashID', 'db_field', 'get');
        $allDash= dbMetaGet(0, 'dashboards'); // Fetch list of all dashboards
        $rID    = metaIdxClean($allDash); // remove the row id, etc
        $myDash = getDashboard($dashID);
        if (empty($myDash)) { return msgAdd('bad_data', 'trap'); }
        foreach ($myDash->struc as $idx => $props) { $allDash[$dashID]['opts'][$idx] = clean($dashID.'_'.$idx, $props['clean'], 'post'); }
        msgDebug("\nWriting modified allDash = ".print_r($allDash, true));
        dbMetaSet($rID, 'dashboards', $allDash);
        msgAdd(lang('msg_settings_saved'), 'success');
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval', 'actionData'=>"jqBiz('#dash_$dashID').hide('slow');"]]);
    }
}