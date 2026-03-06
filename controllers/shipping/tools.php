<?php
/*
 * Shipping Extension - Tools methods
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
 * @version    7.x Last Update: 2026-02-28
 * @filesource /controllers/shipping/tools.php
 */

namespace bizuno;

class shippingTools
{
    public $moduleID= 'shipping';
    public $pageID  = 'tools';
    public $dirBackup;
    public $myFolder;

    function __construct()
    {
        $this->dirBackup = 'backups/';
        $this->myFolder = BIZUNO_DATA;
    }

    public function manager(&$layout=[])
    {
        if (!$security = validateAccess('admin', 1)) { return; }
        $fields = [
            'cleanDesc'=> ['order'=>10,'html'=>lang('log_clean_desc', $this->moduleID),'attr'=>['type'=>'raw']],
            'dateClean'=> ['order'=>20,'attr'=>['type'=>'date', 'value'=>localeCalculateDate(biz_date('Y-m-d'), 0, -6)]],
            'btnClean' => ['order'=>80,'attr'=>['type'=>'button','value'=>lang('go')],'events'=>['onClick'=>"jqBiz('body').addClass('loading'); jqBiz('#frmBackup').submit();"]],
//          'syncDesc' => ['order'=>10,'html'=>lang('sync_shipments_desc'],'attr'=>['type'=>'raw']],
//          'btnSync'  => ['order'=>80,'attr'=>['type'=>'button','value'=>lang('go')],'events'=>['onClick'=>"jqBiz('body').addClass('loading'); jsonAction('$this->moduleID/$this->pageID/syncShipments');"]],
            ];
        $data = ['type'=>'divHTML',
            'divs'    => ['divTabTools'=>['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                'clean'   => ['order'=>20,'type'=>'panel','classes'=>['block33'],'key'=>'clean'],
//              'syncShip'=> ['order'=>30,'type'=>'panel','classes'=>['block33'],'key'=>'syncShip'],
                'dgFiles' => ['order'=>40,'type'=>'panel','classes'=>['block66'],'key'=>'dgFiles']]]],
            'panels'  => [
                'clean'   => ['title'=>lang('log_backup', $this->moduleID),'type'=>'divs','divs'=>[
                    'formBOF'=> ['order'=>10,'type'=>'form',  'key' =>'frmBackup'],
                    'body'   => ['order'=>30,'type'=>'fields','keys'=>['cleanDesc','dateClean','btnClean']],
                    'formEOF'=> ['order'=>90,'type'=>'html',  'html'=>"</form>"]]],
//              'syncShip'=> ['label'=>lang('sync_shipments_title'],'type'=>'fields','keys'=>['syncDesc','btnSync']],
                'dgFiles' => ['type'=>'datagrid','styles'=>['width'=>'100%'],'key'=>'dgBackup']],
            'forms'   => ['frmBackup' => ['attr'=>['type'=>'form', 'action'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/$this->pageID/cleanLog"]]],
            'datagrid'=> ['dgBackup'=>$this->dgBackup('dgBackup')],
            'fields'  => $fields,
            'jsReady' => ['init'=>"ajaxForm('frmBackup');"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * @param string $name - HTML element id of the grid
     * @return $data - grid structure
     */
    private function dgBackup($name)
    {
        return ['id'=>$name, 'title'=>lang('files'),
            'attr'  => ['idField'=>'name', 'url'=>BIZUNO_URL_AJAX."&bizRt=bizuno/backup/mgrRows"],
            'columns' => [
                'action' => ['order'=>1,'label'=>lang('action'),'events'=>['formatter'=>"function(value,row,index) { return {$name}Formatter(value,row,index); }"],
                    'actions'=> [
                        'download'=>['order'=>30,'icon'=>'download','events'=>['onClick'=>"jqBiz('#attachIFrame').attr('src',bizunoAjax+'&bizRt=bizuno/main/fileDownload&pathID=&fileID=idTBD');"]],
                        'trash'   =>['order'=>70,'icon'=>'trash',   'events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('bizuno/main/fileDelete','$name','idTBD');"]]]],
                'name' => ['order'=>10,'label'=>lang('filename'),'attr'=>['width'=>200,'align'=>'center','resizable'=>true]],
                'size' => ['order'=>20,'label'=>lang('size'),    'attr'=>['width'=> 75,'align'=>'center','resizable'=>true]],
                'date' => ['order'=>30,'label'=>lang('date'),    'attr'=>['width'=> 75,'align'=>'center','resizable'=>true]]]];
    }

    /**
     * Load stored backup files
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function mgrRows(&$layout=[])
    {
        global $io;
        $rows = $io->fileReadGlob($this->dirBackup, ['txt','zip','gz']);
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode(['total'=>sizeof($rows), 'rows'=>$rows])]);
    }

    /**
     * Removes all folders that meet the passed criteria
     * @return null
     */
    public function cleanLog()
    {
        set_time_limit(20000);
        if (!$security = validateAccess('admin', 4)) { return; }
        $toDate  = clean('dateClean', ['format'=>'date', 'default'=>localeCalculateDate(biz_date('Y-m-d'), 0, -3)], 'post'); // default to -3 month from today
        $parts   = explode('-', $toDate);
        $carriers= array_keys(getMetaMethod('carriers'));
        foreach ($carriers as $carrier) {
            if (!is_dir($this->myFolder."data/shipping/labels/$carrier")) { continue; }
            $this->cleanLogSubDir("data/shipping/labels/$carrier/", $parts[0]);
            $this->cleanLogSubDir("data/shipping/labels/$carrier/{$parts[0]}/", $parts[1]);
            $this->cleanLogSubDir("data/shipping/labels/$carrier/{$parts[0]}/{$parts[1]}/", $parts[2]);
        }
        msgAdd(sprintf(lang('log_clean_success', $this->moduleID), viewFormat($toDate, 'date')), 'success');
    }

    /**
     * This function deletes the label files from the shipping folder specified in the path for a given carrier prior to a certain folder number.
     * @param string $path - path relative to myFolder to search for folders
     * @param string $stopDate - Contains the highest folder to delete, not including the specified stopDate
     */
    private function cleanLogSubDir($path, $stopDate)
    {
        global $io;
        msgDebug("\nEntering clean shipping labels with path = $path and stopDate = $stopDate");
        $folders = $io->folderRead($path);
        msgDebug("\nRead folder $path and returned with: ".print_r($folders, true));
        if (sizeof($folders) == 0) { return; }
        foreach ($folders as $folder) {
            if ($folder < $stopDate) {
                msgDebug("\nDeleting labels with path = $path and stopDate: $stopDate < folder: $folder ");
//              msgAdd(sprintf(lang('log_clean_status'], "$path/$folder"), 'info');
                $io->folderDelete("$path/$folder");
// UNCOMMED ABOVE TO ACTUALLY DELETE THE LABELS
            }
        }
    }

    /**
     * Synchronizes the shipping log with the PhreeBooks journals
     */
    public function syncShipments()
    {
        // This tool is no longer needed as the shipping logs are now directly tied to the journal entry
        return msgAdd('This tool is no longer needed.');
/*      $date   = localeCalculateDate(biz_date('Y-m-d'), 0, -3); // was -$this->syncMonths
        $result = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', "journal_id=12 and post_date>='$date'", 'invoice_num', ['invoice_num', 'waiting']);
        $journal= [];
        foreach ($result as $row) { $journal[$row['invoice_num']] = $row['waiting']; }
        $result = dbGetMulti(BIZUNO_DB_PREFIX.'extShipping', "ship_date>='$date'", 'ref_id', ['ref_id']);
        $log    = [];
        foreach ($result as $row) {
            if (strpos($row['ref_id'], '-')) { $row['ref_id'] = substr($row['ref_id'], 0, strrpos($row['ref_id'], '-')); }
            $log[$row['ref_id']] = true;
        }
        $unlogged = [];
        $unshipped= [];
        $extra_log= [];
        foreach ($journal as $inv => $waiting) {
            if (!$waiting && !isset($log[$inv])) { $unlogged[] = $inv; } // shipped and not logged (missing log record)
            if (!$waiting &&  isset($log[$inv])) { continue;           } // shipped and logged, as expected
            if ( $waiting && !isset($log[$inv])) { $unshipped[]= $inv; } // unshipped and not logged, pending to ship, ok state
            if ( $waiting &&  isset($log[$inv])) { $extra_log[]= $inv; } // unshipped but logged, shouldn't ever happen
        }
        // if update (versus notitication) set the journal waiting, clear journal waiting
        msgAdd("Unshipped orders: "     .implode(', ', $unshipped),'caution');
        msgAdd("Missing shipping logs: ".implode(', ', $unlogged), 'caution');
        msgAdd("Extra Logs: "           .implode(', ', $extra_log),'caution');
        msgAdd("Finished Shipping sync processing!", 'success'); */
    }
}
