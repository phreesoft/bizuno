<?php
/*
 * @name Bizuno ERP - QuickBooks Conversion Extension
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
 * Do not edit or add to this file if you wish to upgrade bizuno-migrate to newer
 * versions in the future. If you wish to customize bizuno-locale for your
 * needs please contact PhreeSoft for more information.
 *
 * @name       Bizuno ERP
 * @author     Dave Premo, PhreeSoft <support@phreesoft.com>
 * @copyright  2008-2025, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2021-05-19
 * @filesource /bizuno-migrate/controllers/xfrQuickBooks/admin.php
 *
 * Release Notes:
 * A full Biz School class is available the the PhreeSoft.com web site at:
 * https://docs.google.com/presentation/d/e/2PACX-1vSlqzcdoUURty1hFZ1w_Lv8U4nrXXZ7qXtsNMF0cPet7MnMTHoxI7Rln9lHq0pw2d8hUai-EN4-0K_b/pub?start=false&loop=false
 *
 * The user security must be re-set manually as the Bizuno technique is now different than PhreeBooks
 * Departments are not used in Bizuno, warn for converters
 * Edit roles and assign users to roles
 * Reports may need editing, especially if they used a special class
 * POS table files, other addons???
 *
 */

namespace bizuno;

ini_set('memory_limit','2048M');

bizAutoLoad(dirname(__FILE__).'/map.php',      'mapFilename','function');
bizAutoLoad(dirname(__FILE__).'/functions.php','readCSV',    'function');
bizAutoLoad(BIZBOOKS_ROOT.'controllers/phreebooks/journal.php', 'journal');
bizAutoLoad(BIZBOOKS_ROOT.'controllers/phreebooks/functions.php', 'phreebooksProcess', 'function');

class xfrQuickBooksAdmin
{
    public  $moduleID    = 'xfrQuickBooks';
    private $dirBackup   = 'backups/';
    private $total_steps = 17; // number of iterations to perform

    function __construct()
    {
        if (session_status() == PHP_SESSION_NONE) { session_start(); }
        $this->myFolder  = BIZUNO_DATA;
        $lang = [];
        require_once(dirname(__FILE__)."/locale/en_US/language.php"); // replaces lang
        $this->lang      = $lang;
        $this->settings  = array_replace_recursive(getStructureValues($this->settingsStructure()), getModuleCache($this->moduleID, 'settings', false, false, []));
        $this->structure = [
            'url'     => dirname(__FILE__).'/',
            ];
        if (!@is_writable($this->myFolder)) { return msgAdd('Error: your business folder needs to be writable! I cannot reach it.'); }
        $_SESSION[$this->moduleID]['cache_lock'] = 'yes'; // lock the cache so it doesn't try to reload in the middle
    }

    /**
     * Hook to add a tab to convert from QuickBooks to Bizuno, extends /bizuno/import/impExpMain
     */
    public function impExpMain(&$layout)
    {
        if (!$security = validateAccess('backup', 4)) { return; }
        $layout['tabs']['tabImpExp']['divs'][$this->moduleID] = ['order'=>30,'label'=>$this->lang['title'], 'type'=>'html', 'html'=>'',
            'options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=bizuno/import/migrateMgr&modID=$this->moduleID'"]];
    }

    /**
     *
     * @return array
     */
    public function settingsStructure()
    {
        $data = ['general' => ['order'=>10,'label'=>lang('general'),'fields'=>[
            'test_mode'=> ['attr'=>['type'=>'selNoYes', 'value'=>0]],
            'gl_digits'=> ['options'=>['min'=>4,'max'=>  5,'width'=>100],'attr'=>['type'=>'spinner','value'=>  5]],
            'inv_chunk'=> ['options'=>['min'=>1,'max'=>100,'width'=>100],'attr'=>['type'=>'spinner','value'=>100]],
            'con_chunk'=> ['options'=>['min'=>1,'max'=>100,'width'=>100],'attr'=>['type'=>'spinner','value'=>100]],
            'adj_chunk'=> ['options'=>['min'=>1,'max'=> 25,'width'=>100],'attr'=>['type'=>'spinner','value'=> 25]],
            'jrl_chunk'=> ['options'=>['min'=>1,'max'=> 10,'width'=>100],'attr'=>['type'=>'spinner','value'=> 10]],
            ]]];
        settingsFill($data, $this->moduleID);
        return $data;
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess('backup', 4)) { return; }
        $upload_mb = min((int)(ini_get('upload_max_filesize')), (int)(ini_get('post_max_size')), (int)(ini_get('memory_limit')));
        $uploadHTML= "Upload a QuickBooks zip file to import: ".sprintf(lang('max_upload'), $upload_mb);
        $fields    = [
            'fldQBFile'=> ['order'=>50,'attr'=>['type'=>'file']],
            'btnStep0' => ['order'=>60,'break'=>true,'events'=>['onClick'=>"jqBiz('body').addClass('loading'); jqBiz('#frmStep0').submit();"],'attr'=>['type'=>'button','value'=>lang('upload')]]];
        $jsReady   = "ajaxForm('frmStep0');";
        $divs      = ['step0' => ['order'=>20,'type'=>'panel','key'=>'step0','classes'=>['block25']]];
        $panels    = ['step0' => ['label'=>$this->lang['title'],'type'=>'divs','divs'=>[
            'desc'   => ['order'=>10,'type'=>'html',  'html'=>"<p>$uploadHTML</p>"],
            'formBOF'=> ['order'=>20,'type'=>'form',  'key' =>'frmStep0'],
            'body'   => ['order'=>30,'type'=>'fields','keys'=>['fldQBFile','btnStep0']],
            'formEOF'=> ['order'=>90,'type'=>'html',  'html'=>"</form>"]]]];
        $order     = 25;
        for ($i=1; $i<=$this->total_steps; $i++) {
            $divs["step$i"]  = ['order'=>$order,'type'=>'panel','key'=>"step$i",'classes'=>['block25']];
            $panels["step$i"]= ['label'=>$this->lang["title_step$i"],'type'=>'divs','divs'=>[
                'desc'  => ['order'=>20,'type'=>'html',  'html'=>"<p>{$this->lang["desc_step$i"]}</p>"],
                'body'  => ['order'=>30,'type'=>'fields','keys'=>["btnStep$i"]],
                'status'=> ['order'=>80,'type'=>'html',  'html'=>"<progStep$i></progStep$i>"]]];
            $fields["btnStep$i"] = ['order'=>60,'break'=>true,'events'=>['onClick'=>"cronInit('$this->moduleID','bizuno/import/migrateInit&modID=$this->moduleID&step=$i');"],'attr'=>['type'=>'button','value'=>lang('start')]];
            $jsReady .= " jqBiz('progStep$i').attr({value:0,max:100});";
            $order = $order + 5;
        }
        $data = ['type'=>'divHTML', 'title'=>$this->lang['title'],
            'divs'   => ['manager' => ['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>$divs]],
            'panels' => $panels,
            'forms'  => ['frmStep0'=> ['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=$this->moduleID/admin/uploadConvert",'enctype'=>"multipart/form-data"]]],
            'fields' => $fields,
            'jsReady'=> ['init'=>$jsReady]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Initiates the cron process for the requested step
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function migrateInit(&$layout=[])
    {
        if (!$security = validateAccess('backup', 4)) { return; }
        $step = clean('step', 'integer', 'get');
        $total= $this->upgradeSteps($step);
        msgDebug("\nNumber of rows to process = $total");
        setUserCron($this->moduleID, ['step'=>$step,'total'=>$total,'remaining'=>$total]);
        if (!empty($total)) {
            $data= ['content'=>['action'=>'eval', 'msg'=>"Starting to process $total rows", 'actionData'=>"cronInit('$this->moduleID','bizuno/import/migrateNext&modID=$this->moduleID&step=$step');"]];
            @unlink($this->myFolder."upgrade_log_$step.txt"); // erases the log prior to each step
        } else { // nothing to do
            msgLog("Completed $total rows.)");
            $data = ['content'=>['percent'=>100, 'msg'=>"Processed $total rows", 'baseID'=>$this->moduleID, 'urlID'=>"bizuno/import/migrateNext&modID=$this->moduleID&step=$step"]];
        }
        logWrite($step);
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Execution of the next cron action for a requested step
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function migrateNext(&$layout=[])
    {
        if (!$security = validateAccess('backup', 4)) { return; }
        $step = clean('step', 'integer', 'get');
        $cron = getUserCron($this->moduleID);

        $cron['remaining'] = $this->upgrade($layout, $step);

        if (empty($cron['remaining'])) {
            msgLog("Completed {$cron['total']} rows.)");
            $data = ['content'=>['percent'=>100, 'msg'=>"Processed {$cron['total']} rows", 'baseID'=>$this->moduleID, 'urlID'=>"bizuno/import/migrateNext&modID=$this->moduleID&step=$step"]];
            clearUserCron($this->moduleID);
            logWrite($step); // write log on final step
        } else {
            $cnt = $cron['total'] - $cron['remaining'];
            $percent = floor(100*$cnt/$cron['total']);
            $data = ['content'=>['percent'=>$percent, 'msg'=>"Completed $cnt of {$cron['total']} rows", 'baseID'=>$this->moduleID, 'urlID'=>"bizuno/import/migrateNext&modID=$this->moduleID&step=$step"]];
            setUserCron($this->moduleID, $cron);
            if (msgErrors()) { logWrite($step); } // to save log space, only write log when there are errors
        }
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Pre-processes the step and returns with the number of rows to process
     * @param integer $step - Step number
     * @return integer - # rows to process
     */
    private function upgradeSteps($step)
    {
        set_time_limit(6000); // 10 minutes
        $cnt = 0;
        switch ($step) {
            case  1: // Chart of Accounts
                $fn = mapFilename('chart');
                $rows = file(BIZUNO_DATA."temp/qbfiles/$fn", FILE_SKIP_EMPTY_LINES);
                if (empty($rows)) { msgAdd("Cannot find the file or file is empty: BIZUNO_DATA/temp/qbfiles/$fn. Bailing..."); }
                $cnt = !empty($rows) ? sizeof($rows) : 0;
                break;
            case  2: $cnt = getInvSource();        break;
            case  3: $cnt = getAssySource();       break;
            case  4: $cnt = getContactSource('c'); break;
            case  5: $cnt = getContactSource('v'); break;
            case  6: $cnt = getAdjustments();      break; // before purchases and sales to save gobs of time
            case  7: $cnt = getJournalSource(6);   break;
            case  8: $cnt = getJournalSource(12);  break;
            case  9: $cnt = getGLSource();         break;
            case 10: $cnt = getXfrSource();        break;
            case 11: $cnt = getJournalSource(4);   break;
            case 12: $cnt = getJournalSource(10);  break;
            case 13: $cnt = getJournalSource(3);   break;
            case 14: $cnt = getJournalSource(9);   break;
            case 15: $cnt = getBankSource(18);     break;
            case 16: $cnt = getBankSource(20);     break;
            case 17:
                $filePath = "temp/qbfiles/trial_balance.txt";
                if (!file_exists(BIZUNO_DATA.$filePath)) { break; }
                $rows = json_decode(file_get_contents(BIZUNO_DATA.$filePath), true);
                msgDebug("\nRead ".sizeof($rows)." rows from file to process.");
                $cnt = !empty($rows) ? sizeof($rows) : 0;
                break;
            case 16:
            default: return 0;
        }
        return $cnt;
    }

    /**
     * Performs the next import step
     * @global class $io - Input/output class
     * @param integer $step - current step to execute
     * @return boolean - true with no errors, false otherwise
     */
    private function upgrade(&$layout, $step)
    {
        set_time_limit(12000); // 20 minutes
        switch ($step) {
            case  1: // Chart of Accounts
                if (processChart()) { // were done
                    msgDebug("\nSuccess, refreshing browser cache!");
                    $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval', 'actionData'=>"reloadSessionStorage(chartRefresh);"]]);
                }
                break;
            case  2: return processInventory();         // Inventory
            case  3: break;                             // Inventory Assemblies - nothing to do
            case  4: $type = 'c';                       // Customers
            case  5: if (empty($type)) { $type = 'v'; } // Vendors
                    return processContacts($type);
            case  6: return processAdjustments();       // Adjustments
            case  7: $jID = 6;                          // Purchases
                    $_GET['type'] = 'v';
            case  8: if (empty($jID)) { $jID = 12; }    // Sales - what about jID=7 and jID=13 ?
                    return processJournal($jID);
            case  9: return processGL();                // General Ledger
            case 10: return processXfr();               // General Ledger - funds transfers
            case 11: $jID = 4;                          // Purchase Orders
                    $_GET['type'] = 'v';
            case 12: if (empty($jID)) { $jID = 10; }    // Sales Orders
                    return processJournal($jID);
            case 13: $jID = 3;                          // Vendor Quotes
                    $_GET['type'] = 'v';
            case 14: if (empty($jID)) { $jID = 9; }     // Custoemr Quotes
                    return processJournal($jID);
            case 15: $jID = 18;                          // Customer Receipts
            case 16: if (empty($jID)) { $jID = 20; }     // Vendor Payments
                return processBank($jID);
            case 17: // adjust trial balance
                return processTrialBalance();
            default:
        }
        return 0;
    }

    /********************************************* Upload and unzip source data ************************************************/
    /**
     * Grid to list files to restore
     * @param string $name - HTML element id of the grid
     * @return array $data - grid structure
     */
    private function dgQBImport($name='dgQBImport')
    {
        return ['id'=>$name, 'title'=>lang('files'),
            'attr'   => ['idField'=>'title', 'url'=>BIZUNO_AJAX."&bizRt=bizuno/backup/mgrRows&db=1"],
            'columns'=> [
                'action'=> ['order'=>1,'label'=>lang('action'),'events'=>['formatter'=>"function(value,row,index) { return {$name}Formatter(value,row,index); }"],
                    'actions' => ['start'=>['order'=>30,'icon'=>'import','events'=>['onClick'=>"if (confirm('".$this->lang['msg_restore_confirm']."')) { xfrClickFile('{$this->dirBackup}idTBD') }"]]]],
                'title' => ['order'=>10,'label'=>lang('filename'),'attr'=>['width'=>200,'align'=>'center','resizable'=>true]],
                'size'  => ['order'=>20,'label'=>lang('size'),    'attr'=>['width'=> 75,'align'=>'center','resizable'=>true]],
                'date'  => ['order'=>30,'label'=>lang('date'),    'attr'=>['width'=> 75,'align'=>'center','resizable'=>true]]]];
    }

    /**
     * Method to receive a file to upload into the backup folder for db restoration
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function uploadConvert(&$layout)
    {
        global $io;
        $io->uploadSave('fldQBFile', $this->dirBackup);
        // unzip file and put into folder in /temp folder
        $filename = clean($_FILES['fldQBFile']['name'], 'filename');
        $io->zipUnzip(BIZUNO_DATA."backups/$filename", BIZUNO_DATA.'temp/');
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jqBiz('#dgQBImport').datagrid('reload');"]]);
    }

    /**
     * Settings home screen
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function adminHome(&$layout=[])
    {
        if (!$security = validateAccess('admin', 1)) { return; }
        $layout = array_replace_recursive($layout, adminStructure($this->moduleID, $this->settingsStructure(), $this->lang));
    }

    /**
     * Saves the users settings
     */
    public function adminSave()
    {
        readModuleSettings($this->moduleID, $this->settingsStructure());
    }
}