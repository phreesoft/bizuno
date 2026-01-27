<?php
/*
 * Bizuno dashboard - List training tasks on a given recurring schedule, works with extension Training Manager
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
 * @version    7.x Last Update: 2025-11-24
 * @filesource /controllers/quality/dashboards/training_tasks/training_tasks.php
 */

namespace bizuno;

class training_tasks
{
    public  $moduleID  = 'quality';
    public  $pageID    = 'training';
    public  $methodDir = 'dashboards';
    public  $code      = 'training_tasks';
    public  $secID     = 'training';
    public  $category  = 'quality';
    private $metaPrefix= 'training';
    public  $struc;
    public  $lang      = ['title' => 'Training Reminder',
        'description' => 'Lists your training items that are due for action.',
        'msg_confirm_complete' => 'Please confirm to mark this task complete.'];
    private $journalID = 34;

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
            'users'   => ['order'=>10,'label'=>lang('users'),       'clean'=>'array',  'attr'=>['type'=>'users',   'value'=>[0],],'admin'=>true],
            'roles'   => ['order'=>20,'label'=>lang('groups'),      'clean'=>'array',  'attr'=>['type'=>'roles',   'value'=>[-1]],'admin'=>true],
            // User fields
            'store_id'=> ['order'=>40,'label'=>lang('store_id'),    'clean'=>'integer','attr'=>['type'=>'select',  'value'=>-1],  'values'=>viewStores()],
            'trim'    => ['order'=>70,'label'=>lang('truncate_fit'),'clean'=>'integer','attr'=>['type'=>'spinner', 'value'=>20],  'options'=>['min'=>10,'max'=>80,'width'=>100]]];
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
        metaIdxClean($tasks);
        foreach ($tasks as $task) { // check to see if new journal entries need to be created
            $rID = metaIdxClean($task);
            switch($task['lead_time']) {
                default:
                case '1d': $trainDate = localeCalculateDate($task['train_date'],  -1,  0); break;
                case '2d': $trainDate = localeCalculateDate($task['train_date'],  -2,  0); break;
                case '1w': $trainDate = localeCalculateDate($task['train_date'],  -7,  0); break;
                case '2w': $trainDate = localeCalculateDate($task['train_date'], -14,  0); break;
                case '1m': $trainDate = localeCalculateDate($task['train_date'],   0, -1); break;
            }
            msgDebug("\nTraining Date = $trainDate and today = $today");
            if ($trainDate > $today) { continue; }
            $jTitle= $task['title']." (Due ".viewFormat($task['train_date'], 'date').")";
            $task['train_date'] = localePeriodicDate($task['train_date'], $task['frequency']);
            msgDebug("\nUpdating the meta task with values = ".print_r($task, true));
            dbMetaSet($rID, $this->metaPrefix, $task);
            // set the journal entry
            $jEntry= [
                'journal_id'  =>$this->journalID, 'so_po_ref_id'=>$rID, 'invoice_num'=>$task['ref_num'], 'description'=>$jTitle, 'store_id'=>$task['store_id'],
                'contact_id_b'=>!empty($task['rep_id'])?$task['rep_id']:0, 'admin_id'=>getUserCache('profile', 'userID'), 'rep_id'=>$task['contact_id'],
                'tax_rate_id' =>0, 'post_date'=>'NULL']; // Notes are stored in the meta, this CLUTTERS the journal
            dbWrite(BIZUNO_DB_PREFIX.'journal_main', $jEntry); // add a new journal entry
        }
        $filter= "journal_id={$this->journalID} AND post_date IS NULL";
        if (!empty(getUserCache('profile', 'restrict_store')) && sizeof(getModuleCache('bizuno', 'stores')) > 1) {
            $filter .= " AND store_id=".getUserCache('profile', 'store_id', false, -1);
        } elseif ($opts['store_id'] > -1) {
            $filter .= " AND store_id='{$opts['store_id']}'";
        }
        $result = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', $filter, '', ['id','description']);
        msgDebug("\nresults = ".print_r($result, true));
        if (empty($result)) { $rows[] = "<span>".lang('no_results')."</span>"; }
        else { foreach ($result as $entry) {
            $event = "if (confirm('".$this->lang['msg_confirm_complete']."')) dashSubmit('$this->code', {$entry['id']});";
            $row   = '<span style="float:right;height:16px;">'.html5('work_icon', ['icon'=>'work', 'size'=>'small','events'=>['onClick'=>$event]]).'</span>';
            $row  .= '<span style="float:left">'.html5('', ['events'=>['onClick'=>"winHref(bizunoHome+'?bizRt=$this->moduleID/training/manager&rID={$entry['id']}');"],'attr'=>['type'=>'button','value'=>lang('view')]]);
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
