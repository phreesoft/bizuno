<?php
/*
 * Bizuno dashboard - List maintenance tasks on a given recurring schedule, works with extension Maintenance Manager
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
 * @filesource /controllers/bizuno/dashboards/maint_tasks/maint_tasks.php
 */

namespace bizuno;

class maint_tasks
{
    public $moduleID   = 'bizuno';
    public $methodID   = 'extMaint';
    public $pageID     = 'maint';
    public $methodDir  = 'dashboards';
    public $code       = 'maint_tasks';
    public $secID      = 'mgr_maint';
    public $category   = 'general';
    public  $struc;
    public $lang       = ['title'=>'Maintenance Reminder',
        'description' => 'Lists your maintenance items that are due for action.',
        'msg_confirm_complete' => 'Please confirm to mark this task complete.'];
    private $journalID = 35;
    private $metaPrefix= 'maintenance';

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
        $this->struc = [
            // Admin fields
            'users'   => ['order'=>10,'label'=>lang('users'),   'clean'=>'array',  'attr'=>['type'=>'users', 'value'=>[0],], 'admin' =>true],
            'roles'   => ['order'=>20,'label'=>lang('groups'),  'clean'=>'array',  'attr'=>['type'=>'roles', 'value'=>[-1]], 'admin' =>true],
            // User fields
            'store_id'=> ['order'=>40,'label'=>lang('store_id'),'clean'=>'integer','attr'=>['type'=>'select','value'=>-1],'values'=>viewStores()]];
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
        $today = biz_date();
        $tasks = dbMetaGet('%', $this->metaPrefix);
        foreach ($tasks as $task) { // check to see if new journal entries need to be created
            $rID = metaIdxClean($task);
            switch($task['lead_time']) {
                default:
                case '1d': $maintDate = localeCalculateDate($task['maint_date'],  -1,  0); break;
                case '2d': $maintDate = localeCalculateDate($task['maint_date'],  -2,  0); break;
                case '1w': $maintDate = localeCalculateDate($task['maint_date'],  -7,  0); break;
                case '2w': $maintDate = localeCalculateDate($task['maint_date'], -14,  0); break;
                case '1m': $maintDate = localeCalculateDate($task['maint_date'],   0, -1); break;
            }
            msgDebug("\nMaint Date = $maintDate and today = $today");
            if ($maintDate > $today) { continue; }
            $jTitle= $task['title']." (Due ".viewFormat($task['maint_date'], 'date').")";
            $task['maint_date'] = localePeriodicDate($task['maint_date'], $task['frequency']);
            msgDebug("\nUpdating the meta task with values = ".print_r($task, true));
            dbMetaSet($rID, $this->metaPrefix, $task);
            // set the journal entry
            $jEntry= [
                'journal_id' =>$this->journalID, 'so_po_ref_id'=>$rID, 'contact_id_b'=>!empty($task['rep_id'])?$task['rep_id']:0,'description'=>$jTitle,'store_id'=>$task['store_id'],
                'invoice_num'=>$task['task_num'],'rep_id'      =>$task['contact_id'], 'admin_id'    =>getUserCache('profile', 'userID'),        'post_date'  =>'NULL']; // , 'notes'=>$task['notes']
            dbWrite(BIZUNO_DB_PREFIX.'journal_main', $jEntry); // add a new journal entry
        }
        $filter= "journal_id={$this->journalID} AND post_date IS NULL";
        if (!empty(getUserCache('profile', 'restrict_store')) && sizeof(getModuleCache('bizuno', 'stores')) > 1) {
            $filter .= " AND store_id=".getUserCache('profile', 'store_id', false, -1);
        } elseif ($opts['store_id'] > -1) {
            $filter .= " AND store_id='{$opts['store_id']}'";
        }
        $result = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', $filter, '', ['id','description']);
        if (empty($result)) { $rows[] = '<span>'.lang('no_results').'</span>'; }
        else { foreach ($result as $entry) {
            $event = "if (confirm('".$this->lang['msg_confirm_complete']."')) dashSubmit('$this->code', {$entry['id']});";
            $row   = '<span style="float:right;height:16px;">'.html5('work_icon', ['icon'=>'work', 'size'=>'small','events'=>['onClick'=>$event]]).'</span>';
            $row  .= '<span style="float:left">'.html5('', ['events'=>['onClick'=>"winHref(bizunoHome+'&bizRt=$this->moduleID/$this->pageID/manager&rID={$entry['id']}');"],'attr'=>['type'=>'button','value'=>lang('view')]]);
            $row  .= '<span style="min-height:16px;">'.$entry['description'].'</span>';
            $rows[]= $row;
        } }
        return ['lists'=>$rows];
    }
    public function save()
    {
        $rID = clean('rID', 'integer', 'get');
        if (!empty($rID)) { dbWrite(BIZUNO_DB_PREFIX.'journal_main', ['post_date'=>biz_date()], 'update', "id=$rID"); }
    }
}
