<?php
/*
 * Module PhreeBooks - Tools
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
 * @version    7.x Last Update: 2026-04-24
 * @filesource /controllers/phreebooks/tools.php
 */

namespace bizuno;

bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/functions.php', 'phreebooksProcess', 'function');

class phreebooksTools
{
    public $moduleID = 'phreebooks';
    public $dirUploads;

    function __construct()
    {
        $this->dirUploads = 'data/phreebooks/uploads/';
    }

    public function jrnlData(&$layout=[])
    {
        global $io;
        $total_v= $total_c= 0;
        $output = [];
//      $code   = clean('code', 'text', 'get'); // not used yet // 6_12 : dashbaord summary_6_12
        $range  = clean('range','cmd',  'get');
        $fqdn   = "\\bizuno\\summary_6_12";
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/dashboards/summary_6_12/summary_6_12.php', $fqdn);
        $dash   = new $fqdn();
        $data   = $dash->dataSales($range);
        $raw[]  = [jslang('Date'), jsLang('purchases'), jsLang('sales')];
        foreach ($data as $date => $values) {
            $total_v += $values['v'];
            $total_c += $values['c'];
            $raw[]    = [viewFormat($date, 'date'), viewFormat($values['v'],'currency'), viewFormat($values['c'],'currency')];
        }
        $raw[] = [jslang('total'), viewFormat($total_v,'currency'), viewFormat($total_c,'currency')];
        if (sizeof($raw) < 2) { return msgAdd('There are no sales this period!'); }
        foreach ($raw as $row) { $output[] = '"'.implode('","', $row).'"'; }
        $io->download('data', implode("\n", $output), "JournalData-".biz_date('Y-m-d').".csv");
    }

    public function agingData()
    {
        global $io;
        $fqdn  = "\\bizuno\\aged_receivables";
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/contacts/dashboards/aged_receivables/aged_receivables.php', $fqdn);
        $dash  = new $fqdn([]);
        $data  = $dash->getTotals();
        msgDebug("\nRecevied back from aging calculation: ".print_r($data, true));
        if (empty($data['data'])) { return msgAdd('There are no aged receivables!'); }
        $io->download('data', arrayToCSV($data['data']), "agedReceivables-".biz_date('Y-m-d').".csv");
    }

    public function exportSales()
    {
        global $io;
        $output= [];
        $range = clean('range', 'char',   'get');
        $selRep= clean('selRep','integer','get');
        $cData = chartSales(12, $range, 10, $selRep);
        foreach ($cData['data'] as $row) {
            $rData = [];
            foreach ($row as $value) { $rData[] = strpos($value, ',')!==false ? '"'.$value.'"' : $value; }
            $output[] = implode(',', $rData);
        }
        $io->download('data', implode("\n", $output), "Top-Sales-".biz_date('Y-m-d').".csv");
    }

    /**
     * This function adds a fiscal year to the books, it defaults to a 12 period year starting on the next available date
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function fyAdd(&$layout=[])
    {
        if (!validateAccess('admin', 3)) { return; }
        $maxFY    = dbGetValue(BIZUNO_DB_PREFIX."journal_periods", "MAX(fiscal_year)",'', false);
        $maxPeriod= dbGetValue(BIZUNO_DB_PREFIX."journal_periods", "MAX(period)",     '', false);
        $maxDate  = dbGetValue(BIZUNO_DB_PREFIX."journal_periods", "MAX(end_date)",   '', false);
        $nextDate = localeCalculateDate($maxDate, $day_offset=1);
        $maxFY++;
        $maxPeriod++;
        setNewFiscalYear($maxFY, $maxPeriod, $nextDate);
        $fy_max = dbGetValue(BIZUNO_DB_PREFIX."journal_periods", ['MAX(fiscal_year) AS fiscal_year', 'MAX(period) AS period'], "", false);
        setModuleCache('phreebooks', 'fy', 'fy_max',       $fy_max['fiscal_year']);
        setModuleCache('phreebooks', 'fy', 'fy_period_max',$fy_max['period']);
        $this->setChartHistory($maxPeriod, $fy_max['period']);
        periodAutoUpdate(false);
        msgLog(lang('fiscal_year')." - ".lang('add').": $maxFY");
        $layout = array_replace_recursive($layout, ['content'=>  ['action'=>'eval','actionData'=>"location.reload();"]]);
    }

    /**
     * Updates the journal_history database table from the specified firstPeriod to maxPeriod
     * @param integer $firstPeriod - First period to create the history record
     * @param integer $maxPeriod - highest period to load data
     */
    private function setChartHistory($firstPeriod, $maxPeriod)
    {
        $re_acct = getChartDefault(44);
        $acct_string = $this->getGLtoClose(); // select list of accounts that need to be closed, adjusted
        $records = $carryOver = [];
        $lastPriorPeriod = $firstPeriod - 1;
        foreach (getModuleCache('phreebooks', 'chart') as $glAccount) {
//          if (isset($glAccount['heading']) && $glAccount['heading']) { continue; } // Commented out - Prevents headings row gl accounts from being generated for next fiscal year
            if (!in_array($glAccount['id'], $acct_string)) { $carryOver[] = $glAccount['id']; }
            for ($i = $firstPeriod; $i <= $maxPeriod; $i++) {
                $records[] = "('{$glAccount['id']}', '{$glAccount['type']}', '$i', NOW())";
            }
        }
        if (sizeof($records) > 0) {
            dbGetResult("INSERT INTO ".BIZUNO_DB_PREFIX."journal_history (gl_account, gl_type, period, last_update) VALUES ".implode(",\n",$records));
        }
        foreach ($carryOver as $glAcct) { // get carry over gl account beginning balances and fill new FY
            $bb = dbGetValue(BIZUNO_DB_PREFIX.'journal_history', "beginning_balance+debit_amount-credit_amount", "gl_account='$glAcct' AND period=$lastPriorPeriod", false);
            dbWrite(BIZUNO_DB_PREFIX.'journal_history', ['beginning_balance'=>$bb], 'update', "gl_account='$glAcct' AND period>=$firstPeriod");
        }
        $closedGL = implode("','",$acct_string);
        $re = dbGetValue(BIZUNO_DB_PREFIX."journal_history", "SUM(beginning_balance+debit_amount-credit_amount) AS bb", "gl_account IN ('$closedGL') AND period=$lastPriorPeriod", false);
        dbWrite(BIZUNO_DB_PREFIX.'journal_history', ['beginning_balance'=>$re], 'update', "gl_account='$re_acct' AND period>=$firstPeriod");
    }

    /**
     * This function saves the updated fiscal calendar dates.
     * NOTE: The dates cannot be changed unless there are no journal entries in the period being altered.
     */
    public function fySave()
    {
        if (!validateAccess('admin', 3)) { return; }
        $pStart= clean('pStart','array', 'post');
        $pEnd  = clean('pEnd',  'array', 'post');
        foreach ($pStart as $period => $date) {
            $dateStart= clean($date,         'date');
            $dateEnd  = clean($pEnd[$period],'date');
            if ($dateStart && $dateEnd) {
                dbWrite(BIZUNO_DB_PREFIX."journal_periods", ['start_date'=>$dateStart, 'end_date'=>$dateEnd, 'last_update'=>biz_date('Y-m-d')], 'update', "period='$period'");
            }
        }
        setModuleCache('phreebooks', 'fy', 'period', 0); // force a new period as the dates may require this
        periodAutoUpdate(false);
        msgLog(lang('fiscal_year')." - ".lang('edit'));
        msgAdd(lang('msg_settings_saved'), 'success');
    }

    /**
     * Fiscal year close structure to solicit user input to get year to close
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function fyCloseValidate(&$layout=[])
    {
        $icnFyGo = ['attr'=>['type'=>'button', 'value'=>lang('fy_del_btn_go', $this->moduleID)],
            'events'=>  ['onClick'=>"jqBiz('#tabAdmin').tabs('add',{title:'Close FY',href:'".BIZUNO_URL_AJAX."&bizRt=phreebooks/tools/fyCloseHome'}); bizWindowClose('winFyClose');"]];
        $icnCancel = ['attr'=>['type'=>'button', 'value'=>lang('fy_del_btn_cancel', $this->moduleID)],
            'events'=>  ['onClick'=>"bizWindowClose('winFyClose');"]];
        $html  = '<p>'.lang('fy_del_desc', $this->moduleID) .'</p><div style="float:right">'.html5('', $icnFyGo).'</div><div>'.html5('', $icnCancel).'</div>';
        $data = ['type'=>'popup','title'=>lang('fy_del_title', $this->moduleID),'attr'=>['id'=>'winFyClose'],
            'divs' => ['body'=>['order'=>50,'type'=>'html','html'=>$html]]];
        $layout= array_replace_recursive($layout, $data);
    }

    /**
     * Sets the main structure for closing/deleting Fiscal Years, The tab for PhreeBooks settings is also included
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function fyCloseHome(&$layout=[])
    {
        if (!$security = validateAccess('admin', 4)) { return; }
        $title = getModuleCache('phreebooks', 'properties', 'title');
        $fy    = dbGetValue(BIZUNO_DB_PREFIX.'journal_periods', 'fiscal_year', '', false);
        $layout= array_replace_recursive($layout, ['type'=>'divHTML',
            'divs'    => ['fyClose'=>['order'=>10,'type'=>'divs','attr'=>['id'=>"divCloseFY"],'divs'=>[
                'tbClose'=> ['order'=>10,'type'=>'toolbar','key' =>'tbFyClose'],
                'head'   => ['order'=>20,'type'=>'html',   'html'=>"<p>".sprintf(lang('fy_del_instr', $this->moduleID), $fy)."</p>"],
                'body'   => ['order'=>50,'type'=>'html',   'html'=>$this->getViewFyClose(),'label'=>$title],
//              'body'   => ['order'=>50,'type'=>'tabs',   'key' =>'tabFyClose'],
            ]]],
            'toolbars'=> ['tbFyClose' =>['icons'=>['start'=>['order'=>10,'title'=>lang('start'),'icon'=>'next','type'=>'menu','events'=>['onClick'=>"divSubmit('phreebooks/tools/fyClose', 'divCloseFY');"]]]]],
//          'tabs'    => ['tabFyClose'=>['attr'=>['tabPosition'=>'left', 'headerWidth'=>200],'divs'=>['phreebooks' => ['order'=>10,'label'=>$title,'type'=>'html','html'=>$this->getViewFyClose()]]]],
            ]);
    }

    private function getViewFyClose()
    {
        $html = '<h2><u>What will happen when a Fiscal Year is Closed?</u></h2>
<p>The following is a summary of the tasks performed while closing a fiscal year. The fiscal year being closed is indicated above.</p>
<h3>Close Process</h3>
<p>The close process will remove all ledger records for the closing fiscal year. Fiscal calendar periods that are vacated during this process will be removed and
the fiscal calendar will be re-sequenced starting with period 1 being the first period of the first remaining fiscal year.</p>
The following is a summary of the PhreeBooks module closing task list:
<ul>
<li>Adds sales and purchase historical meta with totals to each contact and inventory item with activity for historical record keeping and sales/purchase charting</li>
<li>Delete all journal entries for the closing fiscal year, tables journal_main and journal_item and associated attachments, this includes all sales journals, purchase journals, quality audits & tickets, training and maintenance records</li>
<li>Delete table journal_history records for the closing fiscal year</li>
<li>Clean up COGS owed and COGS usage table for closing fiscal year</li>
<li>Delete journal periods for affected fiscal year</li>
<li>Re-sequence journal periods in journal_history table</li>
<li>Delete all gl chart of accounts if inactive and no journal entries exist against the account</li>
<li>Remove any audit log entries for the affected period</li>
<li>Change status of customers and vendors to inactive if they have no journal activity after removal of fiscal year</li>
<li>Delete any inventory item that has status of inactive and has no journal activity after removal of fiscal year</li>
</ul>
<p>Upon completion, a log file can be found in your backup folder and should be downloaded and retained for historical record keeping.</p>
<h3>Post-close Clean-up</h3>
<p>Following the fiscal year close process, the following tools should be run to complete the process:</p>
<ul>
<li>Journal balances</li>
<li>Contact attachment sync</li>
<li>Inventory attachment sync</li>
<li>Journal attachment sync</li>
<li>Run all inventory tools</li>
<li></li>
</ul>';
        return $html;
    }

    /**
     * Adds to the cron cache for all PhreeBooks module tasks associated with closing a Fiscal Year
     *
     * Journal entries are kept but the period is reduced by the number of accounting periods.
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function fyClose(&$layout=[])
    {
        global $io;
        if (!$security = validateAccess('admin', 4)) { return; }
        $fyInfo    = dbGetPeriodInfo(1);
        $minPeriod = $fyInfo['period_min'];
        $maxPeriod = $fyInfo['period_max'];
        $lastDate  = dbGetValue(BIZUNO_DB_PREFIX.'journal_periods', 'end_date',   "period=$maxPeriod");
        // make sure the FY selected is not the current or future FY
        if ($fyInfo['fiscal_year'] == getModuleCache('phreebooks', 'fy', 'fiscal_year')) { return; }
        // generate three parts, preflight check [taskPre], action (phreebooks first) [taskClose], post action [taskPost] validations (journal validation, inventory tools, etc)
        $cron = ['fy'=>$fyInfo['fiscal_year'], 'fyStartDate'=>$fyInfo['period_start'], 'fyEndDate'=>$lastDate,
            'periodStart'=>$minPeriod, 'periodEnd'=>$maxPeriod, 'curStep'=>0, 'ttlSteps'=>0, 'curBlk'=>0, 'ttlBlk'=>0, 'ttlRecord'=>0, 'msg'=>[]];
        $this->fyCloseHistorySales($cron, true);
        $this->fyCloseHistoryPurch($cron, true);
        $this->fyCloseTableGenJournal($cron, true);
        $this->fyCloseReindexJrnlMain($cron, true);
        $this->fyCloseReindexJrnlItem($cron, true);
        $this->fyCloseReindexGenJournal($cron, true);
        $this->fyCloseCleanAudit($cron, true);
        $this->fyCloseCleanChart($cron, true);
        $this->fyCloseCleanContact($cron, true);
        $this->fyCloseCleanInventory($cron, true);
        $this->fyCloseCleanInvUsage($cron, true);
        $this->fyCloseCleanInvHist($cron, true);
        $msg  = "Log file for closing fiscal year {$fyInfo['fiscal_year']}. Bizuno release ".MODULE_BIZUNO_VERSION.", generated ".biz_date('Y-m-d H:i:s');
$cron['ttlBlk']++; $cron['ttlBlk']++; // Fudge Factor
        setUserCron('fyClose', $cron);
        $io->fileWrite("$msg\n\n", "backups/fy_{$cron['fy']}_close_log.txt", false, false, true);
        $layout = array_replace_recursive($layout, ['content' => ['action'=>'eval', 'actionData'=>"cronInit('fyClose', '$this->moduleID/tools/fyCloseNext');"]]);
    }

    /**
     * Executes the next step in closing the fiscal year for the PhreeBooks module
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function fyCloseNext(&$layout=[])
    {
        global $io;
        if (!$security = validateAccess('admin', 4)) { return; }
        set_time_limit(1800); // 30 minutes
        $cron = getUserCron('fyClose');
        $ttlRecords= number_format($cron['ttlRecord']);
        msgDebug("\nEntering fyCloseNext with cron = ".print_r($cron, true));
        $cron['msg'][] = "\nEntering step: {$cron['curStep']} of {$cron['ttlSteps']}<br />Block {$cron['curBlk']} of {$cron['ttlBlk']}<br />Total of $ttlRecords records.<br />";
        switch ($cron['curStep']) {
            case  0: $this->fyCloseHistorySales($cron);      break; // add to the contacts history meta for sales
            case  1: $this->fyCloseHistoryPurch($cron);      break; // add to the contacts history meta for purchases
            case  2: $this->fyCloseTableGenJournal($cron);   break;
            case  3: $this->fyCloseReindexJrnlMain($cron);   break;
            case  4: $this->fyCloseReindexJrnlItem($cron);   break;
            case  5: $this->fyCloseReindexGenJournal($cron); break;
            case  6: $this->fyCloseCleanAudit($cron);        break;
            case  7: $this->fyCloseCleanChart($cron);        break;
            case  8: $this->fyCloseCleanContact($cron);      break; // contacts_log, contacts_meta
            case  9: $this->fyCloseCleanInventory($cron);    break; // inventory_meta
            case 10: $this->fyCloseCleanInvUsage($cron);     break; // journal_cogs_usage
            case 11: $this->fyCloseCleanInvHist($cron);      break; // inventory_history
            default: $cron['msg'][] = "\nMissing step increment..."; $cron['curStep']++; $cron['curBlk']++; break; // for missing steps
        }
        msgDebug("\nBack from current step with cron = ".print_r($cron, true));
        $msgStep= "\n".implode("\n", $cron['msg']);
        $cron['msg']= []; // reset the message array every step
        $msg    = "Completed step: {$cron['curStep']} of {$cron['ttlSteps']}<br />Block {$cron['curBlk']} of {$cron['ttlBlk']}<br />Total of $ttlRecords records.<br />";
        if ($cron['curStep']>=$cron['ttlSteps']) { // wrap up this iteration
            msgLog("BSI Tools - Fiscal Year {$cron['fy']} Delete Completed!");
            $data = ['content'=>['percent'=>100,'msg'=>'Finished! '.$msg,'baseID'=>'fyClose','urlID'=>"$this->moduleID/tools/fyCloseNext"]];
            clearUserCron('fyClose');
        } else { // return to update progress bar and start next step
            $prcnt= floor(100*$cron['curBlk']/$cron['ttlBlk']);
            $data = ['content'=>['percent'=>$prcnt,'msg'=>$msg,'baseID'=>'fyClose','urlID'=>"$this->moduleID/tools/fyCloseNext"]];
            setUserCron('fyClose', $cron);
        }
        $io->fileWrite($msgStep, "backups/fy_{$cron['fy']}_close_log.txt", false, true, false);
        $layout = array_replace_recursive($layout, $data);
    }

    private function fyCloseHistorySales(&$cron=[], $cntOnly=false) // add to the contacts history meta for sales
    {
        $cron['msg'][] = "Entering Step 0: fyCloseHistorySales with curBlk={$cron['curBlk']} and ttlBlk={$cron['ttlBlk']}.";
        msgDebug("\nEntering fyCloseHistorySales.");
        $this->setHistoryContact($cron, $cntOnly, 'c', 12);
    }
    private function fyCloseHistoryPurch(&$cron=[], $cntOnly=false) // add to the contacts history meta for purchases
    {
        $cron['msg'][] = "Entering Step 1: fyCloseHistoryPurch with curBlk={$cron['curBlk']} and ttlBlk={$cron['ttlBlk']}.";
        msgDebug("\nEntering fyCloseHistoryPurch.");
        $this->setHistoryContact($cron, $cntOnly, 'v', 6);
    }
    private function setHistoryContact(&$cron, $cntOnly, $type, $journal)
    {
        $jIDs   = $type=='v' ? '6, 7'              : '12, 13';
        $msg    = $type=='v' ? 'Purchases'         : 'Sales';
        $metaKey= $journal==6? 'contact_purchases' : 'contact_sales';
        $chunk  = 200;
        $cnt    = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'COUNT(*) AS cnt', "post_date <='{$cron['fyEndDate']}' AND journal_id IN ($jIDs)", false);
        msgDebug("\nRead number of $msg/credits for FY ending {$cron['fyEndDate']} = $cnt");
        if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
        if (empty($cnt)) { $cron['msg'][] = "Completed Step 0/1: setHistoryContact for journal = $journal."; $cron['curStep']++; return; } // reset for next step
        $cron['curBlk']++;
        if (empty($cron['ttlBlk'])) { $cron['ttlBlk'] = ceil($cnt/$chunk); }
        $cron['msg'][] = "Processing $msg History: Block {$cron['curBlk']} ($chunk records) of {$cron['ttlBlk']} blocks.";

        // Let's go
        dbTransactionStart();
        $rIDs = [];
        $rows = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', "post_date <='{$cron['fyEndDate']}' AND journal_id IN ($jIDs)", 'id', '', $chunk);
        foreach ($rows as $row) {
            $rIDs[] = $row['id'];
            $cID = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'id', "id={$row['contact_id_b']}");
            if (empty($cID)) { // orphaned, save to duplicate table
                $cron['msg'][] = "    Found an orphaned record id = {$row['contact_id_b']} and primary_name = {$row['primary_name_b']}.";
                continue;
            }
            $meta = dbMetaGet(0, $metaKey, 'contacts', $cID);
            $mIdx = metaIdxClean($meta);
            $mDate= substr($row['post_date'], 0, 7).'-01';
            $total= round(in_array($journal, [7,13])?-$row['total_amount']:$row['total_amount']);
            if (array_key_exists($mDate, $meta)) {
                if (in_array($journal, [7,13])) { $meta[$mDate]['qty']--; } else { $meta[$mDate]['qty']++; }
                $meta[$mDate]['total']+= $total;
            } else {
                if (in_array($journal, [7,13])) { $meta[$mDate]['qty']=-1; } else { $meta[$mDate]['qty']=1; }
                $meta[$mDate]['total'] = $total;
                ksort($meta);
            }
            dbMetaSet($mIdx, $metaKey, $meta, 'contacts', $cID);
            $this->setHistoryInventory($row['id'], $journal, $cron); // Update inventory history meta
        }
        if (!empty($rIDs)) { // remove records, all finished here for this block
            dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."journal_main WHERE id IN ("                   .implode(',', $rIDs).")");
            dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."journal_meta WHERE ref_id IN ("               .implode(',', $rIDs).")");
            dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."journal_item WHERE ref_id IN ("               .implode(',', $rIDs).")");
            dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."inventory_history WHERE ref_id IN ("          .implode(',', $rIDs).")");
            dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."journal_cogs_owed WHERE journal_main_id IN (" .implode(',', $rIDs).")");
            dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."journal_cogs_usage WHERE journal_main_id IN (".implode(',', $rIDs).")");
        }
        dbTransactionCommit();
    }
    private function setHistoryInventory($mID, $journal, &$cron)
    {
        $metaKey= $journal==6? 'inventory_purchases' : 'inventory_sales';
        $rows   = dbGetMulti(BIZUNO_DB_PREFIX.'journal_item', "ref_id=$mID AND gl_type='itm'", '', ['qty', 'sku', 'credit_amount', 'debit_amount', 'post_date']);
        foreach ($rows as $row) {
//          msgDebug("\nFetched row = ".print_r($row, true));
            $inv   = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['id', 'inventory_type'], "sku='".addslashes($row['sku'])."'");
            if (empty($inv)) { // orphaned, save to duplicate table
                $cron['msg'][] = "    Found an orphaned sku = {$row['sku']}";
                continue;
            }
            if (!in_array($inv['inventory_type'], INVENTORY_COGS_TYPES)) { continue; } // if not in tracked inventory, skip
            $meta  = dbMetaGet(0, $metaKey, 'inventory', $inv['id']);
            $mIdx  = metaIdxClean($meta);
            $mDate = substr($row['post_date'], 0, 7).'-01';
            $itmTtl= $row['debit_amount']+$row['credit_amount'];
            $total = round(in_array($journal, [7,13])?-$itmTtl:$itmTtl);
            if (array_key_exists($mDate, $meta)) {
                $meta[$mDate]['qty']  += $row['qty'];
                $meta[$mDate]['total']+= $total;
            } else {
                $meta[$mDate]['qty']   = $row['qty'];
                $meta[$mDate]['total'] = $total;
                ksort($meta);
            }
            dbMetaSet($mIdx, $metaKey, $meta, 'inventory', $inv['id']);
        }
    }
    private function fyCloseTableGenJournal(&$cron=[], $cntOnly=false)
    {
        $cron['msg'][] = "Entering Step 2: fyCloseTableGenJournal with curBlk={$cron['curBlk']} and ttlBlk={$cron['ttlBlk']}.";
        $tableM= BIZUNO_DB_PREFIX.'journal_main';
        $tableI= BIZUNO_DB_PREFIX.'journal_item';
        $tableH= BIZUNO_DB_PREFIX.'journal_history';
        $tableP= BIZUNO_DB_PREFIX.'journal_periods';
        $crit  = "period <= {$cron['periodEnd']}";
        $cntM  = dbGetValue($tableM, 'COUNT(*) AS cnt', "post_date <='{$cron['fyEndDate']}' AND journal_id NOT IN (6,7,12,13)", false);
        msgDebug("\nRead number of $tableM records for FY ending {$cron['fyEndDate']} = $cntM");
//      $cntI  = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'COUNT(*) AS cnt', "post_date <='{$cron['fyEndDate']}'", false); // no way to exclude by jID
//      msgDebug("\nRead number of $tableI records for FY ending {$cron['fyEndDate']} = $cntI");
        $cntH  = dbGetValue($tableH, 'COUNT(*) AS cnt', $crit, false);
        msgDebug("\nRead number of $tableH records for FY ending {$cron['fyEndDate']} = $cntH");
        $cntP  = dbGetValue($tableP, 'COUNT(*) AS cnt', $crit, false);
        msgDebug("\nRead number of $tableP records for FY ending {$cron['fyEndDate']} = $cntP");
        if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']++; $cron['ttlRecord']+=$cntM; $cron['ttlRecord']+=$cntH; $cron['ttlRecord']+=$cntP; return; }

        // Let's go
        dbTransactionStart();
        $cron['msg'][] = "Read $cntM records to delete from table: $tableM";
        $cron['msg'][] = "Executing SQL: DELETE FROM $tableM WHERE post_date <='{$cron['fyEndDate']}'"; // This should delete all journal main entries remaining
        dbGetResult("DELETE FROM $tableM WHERE post_date <='{$cron['fyEndDate']}'");
//      $cron['msg'][] = "Read $cntI records to delete from table: $tableI";
        $cron['msg'][] = "Executing SQL: DELETE FROM $tableI WHERE post_date <='{$cron['fyEndDate']}'"; // This should delete all journal item entries remaining
        dbGetResult("DELETE FROM $tableI WHERE post_date <='{$cron['fyEndDate']}'");
        $cron['msg'][] = "Read $cntH records to delete from table: $tableH";
        $cron['msg'][] = "Executing SQL: DELETE FROM $tableH WHERE $crit";
        dbGetResult("DELETE FROM $tableH WHERE $crit");
        $cron['msg'][] = "Read $cntP records to delete from table: $tableP";
        $cron['msg'][] = "Executing SQL: DELETE FROM $tableP WHERE $crit";
        dbGetResult("DELETE FROM $tableP WHERE $crit");
        // Some catchalls to remove orphaned records
        //dbGetResult("SELECT ji.* FROM journal_item ji LEFT JOIN journal_main jm ON ji.ref_id = jm.id WHERE jm.id IS NULL");
        // dbGetResult("DELETE ji FROM journal_item ji LEFT JOIN journal_main jm ON ji.ref_id = jm.id WHERE jm.id IS NULL");
        // dbGetResult("SELECT jm.* FROM journal_meta jm LEFT JOIN journal_main j ON jm.ref_id = j.id WHERE j.id IS NULL");
        // dbGetResult("SELECT COUNT(*) AS orphaned_count FROM journal_meta WHERE ref_id NOT IN (SELECT id FROM journal_main)");
        // dbGetResult("DELETE jm FROM journal_meta jm LEFT JOIN journal_main j ON jm.ref_id = j.id WHERE j.id IS NULL");
        // dbGetResult("SELECT ih.* FROM journal_cogs_usage ih LEFT JOIN journal_main j ON ih.journal_main_id = j.id WHERE j.id IS NULL");
        // dbGetResult("SELECT COUNT(*) AS orphaned_count FROM journal_cogs_usage WHERE journal_main_id NOT IN (SELECT id FROM journal_main)");
        // dbGetResult("DELETE ih FROM journal_cogs_usage ih LEFT JOIN journal_main j ON ih.journal_main_id = j.id WHERE j.id IS NULL");
        $cron['curStep']++;
        $cron['curBlk']++;
        $cron['msg'][] = "Completed Step 2: fyCloseTableGenJournal.";
        dbTransactionCommit();
    }
    private function fyCloseReindexJrnlMain(&$cron=[], $cntOnly=false)
    {
        $cron['msg'][] = "Entering Step 3: fyCloseReindexJrnlMain with curBlk={$cron['curBlk']} and ttlBlk={$cron['ttlBlk']}.";
        $table= BIZUNO_DB_PREFIX.'journal_main';
        $cnt  = dbGetValue($table, 'COUNT(*) AS cnt', '', false);
        msgDebug("\nRead number of $table records for FY ending {$cron['fyEndDate']} = $cnt");
        if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']++; $cron['ttlRecord']+=$cnt; return; }
        
        // Let's go
        dbTransactionStart();
        $sql = "UPDATE $table SET period = period - {$cron['periodEnd']}";
        $cron['msg'][] = "Reindexing table $table to align with new periods.";
        $cron['msg'][] = "    Executing SQL: $sql";
        dbGetResult($sql);
        $cron['curStep']++;
        $cron['curBlk']++;
        $cron['msg'][] = "Completed Step 3: fyCloseReindexJrnlMain.";
        dbTransactionCommit();
    }
    private function fyCloseReindexJrnlItem(&$cron=[], $cntOnly=false)
    {
        $cron['msg'][] = "Entering Step 4: fyCloseReindexJrnlItem with curBlk={$cron['curBlk']} and ttlBlk={$cron['ttlBlk']}.";
        $table= BIZUNO_DB_PREFIX.'journal_item';
        $cnt  = dbGetValue($table, 'COUNT(*) AS cnt', '', false);
        msgDebug("\nRead number of $table records for FY ending {$cron['fyEndDate']} = $cnt");
        if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']++; $cron['ttlRecord']+=$cnt; return; }
        
        // Let's go
        dbTransactionStart();
        $sql = "UPDATE $table SET reconciled = reconciled - {$cron['periodEnd']} WHERE reconciled > 0";
        $cron['msg'][] = "Reindexing table $table to align with new periods.";
        $cron['msg'][] = "    Executing SQL: $sql";
        dbGetResult($sql);
        $cron['curStep']++;
        $cron['curBlk']++;
        $cron['msg'][] = "Completed Step 4: fyCloseReindexJrnlItem.";
        dbTransactionCommit();
    }
    private function fyCloseReindexGenJournal(&$cron=[], $cntOnly=false)
    {
        $cron['msg'][] = "Entering Step 5: fyCloseReindexGenJournal with curBlk={$cron['curBlk']} and ttlBlk={$cron['ttlBlk']}.";
        $tableH= BIZUNO_DB_PREFIX.'journal_history';
        $cntH  = dbGetValue($tableH, 'COUNT(*) AS cnt', '', false);
        msgDebug("\nRead number of $tableH records for FY ending {$cron['fyEndDate']} = $cntH");
        $tableP= BIZUNO_DB_PREFIX.'journal_periods';
        $cntP  = dbGetValue($tableP, 'COUNT(*) AS cnt', '', false);
        msgDebug("\nRead number of $tableP records for FY ending {$cron['fyEndDate']} = $cntP");
        if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']++; $cron['ttlRecord']+=$cntH+$cntP; return; }
        
        // Let's go
        dbTransactionStart();
        $sqlH = "UPDATE $tableH SET period = period-{$cron['periodEnd']}";
        $cron['msg'][] = "Reindexing table $tableH to align with new periods.";
        $cron['msg'][] = "    Executing SQL: $sqlH";
        dbGetResult($sqlH);
        $sqlP = "UPDATE $tableP SET period = period-{$cron['periodEnd']}";
        $cron['msg'][] = "Reindexing table $tableP to align with new periods.";
        $cron['msg'][] = "    Executing SQL: $sqlP";
        dbGetResult($sqlP);
        $props = dbGetPeriodInfo(getModuleCache('phreebooks', 'fy', 'period') - $cron['periodEnd']);
        setModuleCache('phreebooks', 'fy', false, $props);
        $cron['curStep']++;
        $cron['curBlk']++;
        $cron['msg'][] = "Completed Step 5: fyCloseReindexGenJournal.";
        dbTransactionCommit();
    }
    private function fyCloseCleanAudit(&$cron=[], $cntOnly=false)
    {
        $cron['msg'][] = "Entering Step 6: fyCloseCleanAudit with curBlk={$cron['curBlk']} and ttlBlk={$cron['ttlBlk']}.";
        $table= BIZUNO_DB_PREFIX.'audit_log';
        $crit = "`date`<='{$cron['fyEndDate']}'";
        $cnt  = dbGetValue($table, 'COUNT(*) AS cnt', $crit, false);
        msgDebug("\nRead number of $table records for FY ending {$cron['fyEndDate']} = $cnt");
        if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']++; $cron['ttlRecord']+=$cnt; return; }
        
        // Let's go
        dbTransactionStart();
        $cron['msg'][] = "Read $cnt records to delete from table: $table";
        $cron['msg'][] = "    Executing SQL: DELETE FROM $table WHERE $crit";
        dbGetResult("DELETE FROM $table WHERE $crit");
        $cron['curStep']++;
        $cron['curBlk']++;
        $cron['msg'][] = "Completed Step 6: fyCloseCleanAudit.";
        dbTransactionCommit();
    }
    private function fyCloseCleanChart(&$cron=[], $cntOnly=false)
    {
        $cron['msg'][] = "Entering Step 7: fyCloseCleanChart with curBlk={$cron['curBlk']} and ttlBlk={$cron['ttlBlk']}.";
        $chart= getMetaCommon('chart_of_accounts');
        foreach ($chart as $idx => $acct) { if (empty($acct['inactive'])) { unset($chart[$idx]); } }
        msgDebug("\nRead number of inactive gl accounts for FY ending {$cron['fyEndDate']} = ".sizeof($chart));
        if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']++; $cron['ttlRecord']+=sizeof($chart); return; }
        
        // Let's go
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/chart.php', 'phreebooksChart');
        $glAcct = new phreebooksChart();
        dbTransactionStart();
        $cron['msg'][] = "Read number of inactive gl accounts for FY ending {$cron['fyEndDate']} = ".sizeof($chart);
        foreach ($chart as $acct) {
            // test for the existence of the gl account in the journal
            $hitM = dbGetRow(BIZUNO_DB_PREFIX.'journal_main', "gl_acct_id={$acct['id']}");
            $hitI = dbGetRow(BIZUNO_DB_PREFIX.'journal_item', "gl_account={$acct['id']}");
            if (empty($hitM) && empty($hitI)) { // if not there, delete it
                msgDebug("\nGL Chart account: {$acct['id']} is inactive and has no activity, deleting.");
                $_GET['rID'] = $acct['id'];
                $glAcct->delete();
            }
        }
        $cron['curStep']++;
        $cron['curBlk']++;
        $cron['msg'][] = "Completed Step 7: fyCloseCleanChart.";
        dbTransactionCommit();
    }
    private function fyCloseCleanContact(&$cron=[], $cntOnly=false) // contacts_log, contacts_meta
    {
        $cron['msg'][] = "Entering Step 8: fyCloseCleanContact with curBlk={$cron['curBlk']} and ttlBlk={$cron['ttlBlk']}.";
        $chunk  = 200;
        $cnt    = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'COUNT(*) AS cnt', "ctype_c='1' OR ctype_v='1'", false);
        msgDebug("\nRead $cnt contacts for cleaning and verifying journal activity.");
        if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
        $cron['msg'][] = "Validating contacts usage: Block {$cron['curBlk']} ($chunk records) of {$cron['ttlBlk']} blocks.";

        // Let's go
        dbTransactionStart();
        if (empty($cron['clnCID'])) { $cron['clnCID'] = $cron['clnCIDcnt'] = 0; }
        $rows = dbGetMulti(BIZUNO_DB_PREFIX.'contacts', "id>{$cron['clnCID']} AND (ctype_c='1' OR ctype_v='1')", 'id', '', $chunk);
        if (empty($rows)) { 
            $cron['msg'][] = "Finished Step 8: fyCloseCleanContact, total records marked as No Activity = {$cron['clnCIDcnt']}";
            $cron['curStep']++; 
        } else {
            $cron['curBlk']++;
            foreach ($rows as $row) {
                $cron['clnCID'] = $row['id'];
                $hit = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'id', "contact_id_b={$row['id']}");
                if (empty($hit) && $row['ctype_b']=='0' && $row['ctype_e']=='0' && $row['ctype_u']=='0' &&
                        !in_array($row['inactive'], ['2'])) { // 2=locked; not active c, active v, u, e, b, flag not used
                    msgDebug("\nContact {$row['primary_name']} has no activity, marking them inactive.");
                    dbWrite(BIZUNO_DB_PREFIX.'contacts', ['inactive'=>'1'], 'update', "id={$row['id']}");
                    $cron['clnCIDcnt']++;
                }
            }
        }
        // Removed orphaned records
        // dbGetResult("SELECT cm.* FROM contacts_meta cm LEFT JOIN contacts c ON cm.ref_id = c.id WHERE c.id IS NULL");
        // dbGetResult("SELECT COUNT(*) AS orphaned_count FROM contacts_meta WHERE ref_id NOT IN (SELECT id FROM contacts)");
        // dbGetResult("DELETE cm FROM contacts_meta cm LEFT JOIN contacts c ON cm.ref_id = c.id WHERE c.id IS NULL");
        // dbGetResult("SELECT cm.* FROM contacts_log cm LEFT JOIN contacts c ON cm.contact_id = c.id WHERE c.id IS NULL");
        // dbGetResult("SELECT COUNT(*) AS orphaned_count FROM contacts_log WHERE contact_id NOT IN (SELECT id FROM contacts)");
        // dbGetResult("DELETE cm FROM contacts_log cm LEFT JOIN contacts c ON cm.contact_id = c.id WHERE c.id IS NULL");
        dbTransactionCommit();
    }
    private function fyCloseCleanInventory(&$cron=[], $cntOnly=false) // inventory_meta
    {
        $cron['msg'][] = "Entering Step 9: fyCloseCleanInventory with curBlk={$cron['curBlk']} and ttlBlk={$cron['ttlBlk']}.";
        $chunk  = 200;
        $cnt    = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'COUNT(*) AS cnt', "inactive='1'", false);
        msgDebug("\nRead $cnt inventory items for cleaning and verifying journal activity.");
        if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
        $cron['msg'][] = "Validating inventory usage: Block {$cron['curBlk']} ($chunk records) of {$cron['ttlBlk']} blocks.";

        // Let's go
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/inventory/main.php', 'inventoryMain');
        $inventory = new inventoryMain();
        dbTransactionStart();
        if (empty($cron['clnIID'])) { $cron['clnIID'] = $cron['clnIIDcnt'] = 0; }
        $rows = dbGetMulti(BIZUNO_DB_PREFIX.'inventory', "id>{$cron['clnIID']} AND inactive='1'", 'id', '', $chunk);
        if (empty($rows)) { 
            $cron['msg'][] = "Finished Step 9: fyCloseCleanInventory, total records deleted = {$cron['clnIIDcnt']}";
            $cron['curStep']++; 
        } else {
            $cron['curBlk']++;
            foreach ($rows as $row) {
                $cron['clnIID'] = $row['id'];
                $hit = dbGetRow(BIZUNO_DB_PREFIX.'journal_item', "sku='".addslashes($row['sku'])."'");
                if (empty($hit)) { // flag not used
                    msgDebug("\nInventory {$row['sku']} has no activity, deleting.");
                    $_GET['rID'] = $row['id'];
                    $inventory->delete();
                    $cron['clnIIDcnt']++;
                }
            }
        }
        // Removed orphaned records
        // dbGetResult("SELECT im.* FROM inventory_meta im LEFT JOIN inventory i ON im.ref_id = i.id WHERE i.id IS NULL");
        // dbGetResult("SELECT COUNT(*) AS orphaned_count FROM inventory_meta WHERE ref_id NOT IN (SELECT id FROM inventory)");
        // dbGetResult("DELETE im FROM inventory_meta im LEFT JOIN inventory i ON im.ref_id = i.id WHERE i.id IS NULL");
        // dbGetResult("SELECT ih.* FROM inventory_history ih LEFT JOIN journal_main j ON ih.ref_id = j.id WHERE j.id IS NULL");
        // dbGetResult("SELECT COUNT(*) AS orphaned_count FROM inventory_history WHERE ref_id NOT IN (SELECT id FROM journal_main)");
        // dbGetResult("DELETE ih FROM inventory_history ih LEFT JOIN journal_main j ON ih.ref_id = j.id WHERE j.id IS NULL");
        dbTransactionCommit();
    }
    private function fyCloseCleanInvUsage(&$cron=[], $cntOnly=false)
    {
        msgDebug("\nEntering fyCloseCleanInvUsage");
        $cron['msg'][] = "Entering Step 10: fyCloseCleanInvUsage with curBlk={$cron['curBlk']} and ttlBlk={$cron['ttlBlk']}.";
        $table= BIZUNO_DB_PREFIX.'journal_cogs_usage';
        if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']++; $cron['ttlRecord']+=1; return; }
        
        // Let's go
        dbTransactionStart();
        $jm    = BIZUNO_DB_PREFIX.'journal_main';
        $cron['msg'][] = "Cleaning up orphaned records from table: $table";
        $cron['msg'][] = "    Executing SQL: SELECT * FROM $table jcu LEFT JOIN $jm jm ON jcu.journal_main_id = jm.id WHERE jm.id IS NULL";
        $stmt = dbGetResult("SELECT jm.id FROM $table jcu LEFT JOIN $jm jm ON jcu.journal_main_id = jm.id WHERE jm.id IS NULL");
        $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        msgDebug("\nFinding orphaned cogs_usage records and fetched ".sizeof($rows)." rows.");
        $cron['msg'][] = "Read ".sizeof($rows)." records to delete from table: $table";
        dbGetResult("DELETE FROM $table WHERE NOT EXISTS (SELECT 1 FROM $jm jm WHERE jm.id = $table.journal_main_id)");
        $cron['curStep']++;
        $cron['curBlk']++;
        $cron['msg'][] = "Completed Step 10: fyCloseCleanInvUsage.";
        dbTransactionCommit();
    }

    private function fyCloseCleanInvHist(&$cron=[], $cntOnly=false)
    {
        msgDebug("\nEntering fyCloseCleanInvHist");
        $cron['msg'][] = "Entering Step 11: fyCloseCleanInvHist with curBlk={$cron['curBlk']} and ttlBlk={$cron['ttlBlk']}.";
        $table= BIZUNO_DB_PREFIX.'inventory_history';
        if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']++; $cron['ttlRecord']+=1; return; }
        
        // Let's go
        dbTransactionStart();
        $jm    = BIZUNO_DB_PREFIX.'journal_main';
        $cron['msg'][] = "Cleaning up orphaned records from table: $table";
        $cron['msg'][] = "    Executing SQL: SELECT * FROM $table ih LEFT JOIN $jm jm ON ih.ref_id = jm.id WHERE jm.id IS NULL";
        $stmt = dbGetResult("SELECT jm.id FROM $table ih LEFT JOIN $jm jm ON ih.ref_id = jm.id WHERE jm.id IS NULL");
        $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        msgDebug("\nFinding orphaned inventory history records and fetched ".sizeof($rows)." rows.");
        $cron['msg'][] = "Read ".sizeof($rows)." records to delete from table: $table";
        dbGetResult("DELETE FROM $table WHERE NOT EXISTS (SELECT 1 FROM $jm jm WHERE jm.id = $table.ref_id)");
        $cron['curStep']++;
        $cron['curBlk']++;
        $cron['msg'][] = "Completed Step 11: fyCloseCleanInvHist.";
        dbTransactionCommit();
    }

    /**
     * This method reposts a single journal entry
     * @param integer $rID - Record id from the table journal_main
     * @return boolean - true on success, false (with msg) on error.
     */
    public function glRepost($rID=0)
    {
        ini_set("max_execution_time", 300); // 5 minutes per post
        if (!validateAccess('j2_mgr', 3)) { return; }
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/journal.php', 'journal');
        dbTransactionStart();
        $jID = dbGetvalue(BIZUNO_DB_PREFIX.'journal_main', 'journal_id', "id=$rID");
        $repost = new journal($rID, $jID);
        if ($repost->Post()) {
            dbTransactionCommit();
            return true;
        }
    }

    /**
     * Method to initiate reposting of journal records for a specified date range
     * @param array $layout - structure of view
     * @return array - modified $layout
     */
    public function glRepostBulk(&$layout=[])
    {
        $jIDs     = array_keys(clean('jID', 'array', 'post'));
        $dateStart= clean('repost_begin','date', 'post');
        $dateEnd  = clean('repost_end',  'date', 'post');
        if (sizeof($jIDs) == 0) { return msgAdd(lang('err_pb_repost_empty', $this->moduleID)); }
        $result = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', "journal_id IN (".implode(',', $jIDs).") AND post_date>='$dateStart' AND post_date<='$dateEnd'", 'post_date', ['id']);
        if (sizeof($result) == 0) { return msgAdd(lang('no_results')); }
        foreach ($result as $row) { $rows[] = $row['id']; }
        msgDebug("\nRows to process = ".print_r($rows, true));
        if (empty($rows)) { return msgAdd("No rows to process", 'info'); }
        setUserCron('glRepost', ['cnt'=>0, 'total'=>sizeof($rows), 'rows'=>$rows]);
        $layout = array_replace_recursive($layout, ['content' => ['action'=>'eval', 'actionData'=>"cronInit('glRepost', 'phreebooks/tools/glRepostBulkNext');"]]);
    }

    /**
     * Ajax continuation of glRepostBulk
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function glRepostBulkNext(&$layout=[])
    {
        $cron= getUserCron('glRepost');
        $id  = array_shift($cron['rows']);
        if (!empty($id)) { $this->glRepost($id); }
        $cron['cnt']++;
        if (sizeof($cron['rows']) == 0) {
            msgLog("PhreeBooks Tools (Repost Journals) - ({$cron['total']} records)");
            $data = ['content'=>['percent'=>100,'msg'=>"Processed {$cron['total']} Journal Entries",'baseID'=>'glRepost','urlID'=>'phreebooks/tools/glRepostBulkNext']];
            clearUserCron('glRepost');
        } else { // return to update progress bar and start next step
            $percent = floor(100*$cron['cnt']/$cron['total']);
            setUserCron('glRepost', $cron);
            $data = ['content'=>['percent'=>$percent,'msg'=>"Completed journal record id = $id",'baseID'=>'glRepost','urlID'=>'phreebooks/tools/glRepostBulkNext']];
        }
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Tool to repair the journal_history table with journal_main actuals
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function glRepair(&$layout=[])
    {
        if (!validateAccess('admin', 3)) { return; }
        $tmp = dbGetMulti(BIZUNO_DB_PREFIX.'journal_periods', '', 'period');
        foreach ($tmp as $row) { $rows['p'.$row['period']] = ['period'=>$row['period'], 'fy'=>$row['fiscal_year']]; }
        msgDebug("\nSetting cron row count = ". print_r($rows, true));
        setUserCron('repairGL', ['cnt'=>0, 'total'=>sizeof($rows), 'rows'=>$rows]);
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"cronInit('repairGL', 'phreebooks/tools/glRepairNext');"]]);
    }

    /**
     * Ajax continuation of glRepair
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function glRepairNext(&$layout=[])
    {
        $cron      = getUserCron('repairGL');
        $fatalError= false;
        $nextPeriod= array_shift($cron['rows']);
        $period    = !empty($nextPeriod['period']) ? $nextPeriod['period'] : 0;
        $curPerFY  = $nextPeriod['fy'];
        $re_acct   = getChartDefault(44);
        if (isset($cron['rows']['p'.($period+1)]['fy'])) {
            $nextPerFY = $cron['rows']['p'.($period+1)]['fy'];
        } else {
            $nextPerFY = false;
        }
        msgDebug("\nWorking with period $period and fy $curPerFY and next period fy $nextPerFY");
        $tolerance = 0.0001;
        dbTransactionStart();
        // get journal_history for given period
        $curHistory = dbGetMulti(BIZUNO_DB_PREFIX.'journal_history', "period=$period");
        msgDebug("\nFound ".sizeof($curHistory)." history records (GL Accounts) in period $period");
        // set beginning balance values for current period, test for zero
        $trialBalance = 0;
        foreach ($curHistory as $row) {
            $nextBB = $row['beginning_balance'] + $row['debit_amount'] - $row['credit_amount'];
            $history[$row['gl_account']] = ['begbal'=>$row['beginning_balance'], 'debit'=>$row['debit_amount'], 'credit'=>$row['credit_amount'], 'nextBB'=>$nextBB];
            $trialBalance += $row['beginning_balance']; // zero test gathering
        }
        msgDebug("\nTrial balance = $trialBalance");
        if (abs($trialBalance) > $tolerance) {
            // sometimes this will happen from results in FY roll-over where prior year agregate exceeds tolerance, only show message if amount is large
            // enough to warrant concern which means there is a bigger problem
            if (abs($trialBalance) > 100*$tolerance) {
                msgAdd("Trial balance for period $period is out of balance by $trialBalance. Retained earnings account $re_acct will be adjusted and testing will continue.", 'trap');
            }
            // Make the correction anyway as when FY's are closed this will eventually become the newe actual
            dbGetResult("UPDATE ".BIZUNO_DB_PREFIX."journal_history SET beginning_balance = beginning_balance - $trialBalance WHERE period=$period AND gl_account='$re_acct'");
        }
        // get all from journal_item for given period
        $stmt = dbGetResult("SELECT m.id, m.journal_id, i.gl_account, i.debit_amount, i.credit_amount
            FROM ".BIZUNO_DB_PREFIX."journal_main m JOIN ".BIZUNO_DB_PREFIX."journal_item i ON m.id=i.ref_id WHERE period=$period");
        $glPosts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        msgDebug("\nFound ".sizeof($glPosts)." posts in period $period");
        // for each ref_id, make sure each main record is in balance, add to beginning_balance, flag ID's not in balance, these need to be fixed manually
        $mID = 0;
        $mains = [];
$skips = [];
        $rID = ['debit'=>0, 'credit'=>0];
        foreach ($glPosts as $row) {
            if (!in_array($row['journal_id'], [2,6,7,12,13,14,15,16,17,18,19,20,21,22])) {
if ($row['journal_id']=='1010-00') { $skips[] = ['mID'=>$row['id'], 'jID'=>$row['journal_id'], 'debit'=>$row['debit_amount'], 'credit'=>$row['credit_amount']]; }
                continue;
            } // doesn't affect journal
            if ($row['id'] <> $mID) { // if new main id, test for balance of previous id and reset values
                if (abs($rID['debit']-$rID['credit']) > $tolerance) {
                    if (!$this->glRepairEntry($mID)) {
                        $fatalError = true;
                        msgAdd("Journal record $mID is out of balance, this results from a database corruption issue and needs to be corrected manually in the db! The repair has been halted");
                    }
                }
                $mID = $row['id'];
                $rID = ['debit'=>0, 'credit'=>0];
            }
            // add to running total for this main record
            $rID['debit']  += $row['debit_amount'];
            $rID['credit'] += $row['credit_amount'];
            // add debits and credits to running total for period
            if (!isset($mains[$row['gl_account']])) { $mains[$row['gl_account']] = ['debit'=>0, 'credit'=>0]; }
            $mains[$row['gl_account']]['debit']  += $row['debit_amount'];
            $mains[$row['gl_account']]['credit'] += $row['credit_amount'];
        }
        // get gl accounts that close at end of FY
        $closedGL = $this->getGLtoClose();
        // get journal_history beginning balances for next period
        $nextHistory= dbGetMulti(BIZUNO_DB_PREFIX.'journal_history', "period=".($period+1));
        // test ending balance with next period beginning balance (except FY boundaries), correct next beginning balance here if out of whack
        $retainedEarnings = 0;
        $updateEndFY = false;
        $historyRE = '';
        $endFY = $curPerFY <> $nextPerFY ? true : false;
        if ($nextPerFY) { foreach ($nextHistory as $row) {
            $fixBB = false;
            if (!isset($mains[$row['gl_account']]))  { $mains[$row['gl_account']]  = ['debit'=>0, 'credit'=>0]; }
            if (!isset($history[$row['gl_account']])){ $history[$row['gl_account']]= ['debit'=>0, 'credit'=>0, 'begbal'=>0, 'nextBB'=>0]; }
            $actualBal = $history[$row['gl_account']]['begbal'] + $mains[$row['gl_account']]['debit'] - $mains[$row['gl_account']]['credit'];
            if ($row['gl_account'] == $re_acct) { $historyRE = $row['beginning_balance']; }
            msgDebug("\nPeriod $period, glAcct={$row['gl_account']}, history bb={$history[$row['gl_account']]['begbal']}, debit={$history[$row['gl_account']]['debit']}, credit={$history[$row['gl_account']]['credit']}, nextbb={$history[$row['gl_account']]['nextBB']} - historyNextBB={$row['beginning_balance']} - mains: debit={$mains[$row['gl_account']]['debit']}, credit={$mains[$row['gl_account']]['credit']}, ");
            // check posted debits and credits to history
            if (abs($history[$row['gl_account']]['debit'] - $mains[$row['gl_account']]['debit']) > $tolerance) {
                msgAdd("Historical debit amount for period ".($period+1)." ({$history[$row['gl_account']]['debit']}), gl_account {$row['gl_account']} doesn't match the journal postings for period $period ({$mains[$row['gl_account']]['debit']}). The balance will be repaired to actuals.");
                $fixBB = true;
            }
            if (abs($history[$row['gl_account']]['credit'] - $mains[$row['gl_account']]['credit']) > $tolerance) {
                msgAdd("Historical credit amount for period ".($period+1)." ({$history[$row['gl_account']]['debit']}), gl_account {$row['gl_account']} doesn't match the journal postings for period $period ({$mains[$row['gl_account']]['debit']}). The balance will be repaired to actuals.");
                $fixBB = true;
            }
            // Check next beginning balance
            if ($endFY && in_array($row['gl_account'], $closedGL)) {
                $retainedEarnings += $actualBal;
            } elseif ($re_acct <> $row['gl_account']) { // check next period bb with history table calculation, except RE account which is the collection account
                if (abs($history[$row['gl_account']]['nextBB'] - $row['beginning_balance']) > 10*$tolerance) { // need 10x to allow corrections to rounding carry-over
                    msgAdd("Beginning balance in history table for period ".($period+1)." (gl: {$row['gl_account']}) doesn't match the journal postings for period $period. The beginning balance will be repaired to actuals.");
                    $fixBB = true;
                }
            }
            if ($fixBB) {
                // repair debits and credit in current period to actuals
                msgDebug("\nWriting to journal_history gl_account={$row['gl_account']} and period=$period debit amount: {$mains[$row['gl_account']]['debit']} and credit {$mains[$row['gl_account']]['credit']}");
                dbWrite(BIZUNO_DB_PREFIX.'journal_history', ['debit_amount'=>$mains[$row['gl_account']]['debit'], 'credit_amount'=>$mains[$row['gl_account']]['credit']], 'update', "period=$period AND gl_account='{$row['gl_account']}'");
                // repair beginning balance for next period
                msgDebug("\nWriting to journal_history gl_account={$row['gl_account']} and period=".($period+1)." beginning balance amount: $actualBal");
                dbWrite(BIZUNO_DB_PREFIX.'journal_history', ['beginning_balance'=>$actualBal], 'update', "period=".($period+1)." AND gl_account='{$row['gl_account']}'");
                $updateEndFY = true;
            }
        } }
        if ($endFY) {
            $acct_string = implode("','", $closedGL);
            msgDebug("\nUpdating end of FY balances for period $period, gl account=$re_acct and history was $historyRE and will be set to = $retainedEarnings");
            if ($nextPerFY) { dbWrite(BIZUNO_DB_PREFIX.'journal_history', ['beginning_balance'=>0], 'update', "period=".($period+1)." AND gl_account IN ('$acct_string')"); }
            if ($nextPerFY) { dbWrite(BIZUNO_DB_PREFIX.'journal_history', ['beginning_balance'=>$retainedEarnings], 'update', "period=".($period+1)." AND gl_account='$re_acct'"); }
        }
        $cron['cnt']++;
        dbTransactionCommit();
        if ($fatalError || sizeof($cron['rows']) == 0) {
            msgLog("PhreeBooks Tools (repairGL) - ({$cron['total']} records)");
            $data = ['content'=>['percent'=>100,'msg'=>"Processed {$cron['total']} Periods",'baseID'=>'repairGL','urlID'=>'phreebooks/tools/glRepairNext']];
            clearUserCron('repairGL');
        } else { // return to update progress bar and start next step
            $percent = floor(100*$cron['cnt']/$cron['total']);
            setUserCron('repairGL', $cron);
            $data = ['content'=>['percent'=>$percent,'msg'=>"Completed period $period",'baseID'=>'repairGL','urlID'=>'phreebooks/tools/glRepairNext']];
        }
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Tests and repairs a single journal entry that is causing balance errors fond during tests
     * @param integer $mID - database journal_main record id
     * @return boolean true
     */
    private function glRepairEntry($mID)
    {
        $precision = getModuleCache('phreebooks', 'currency', 'iso')[getDefaultCurrency()]['dec_len'];
        $tolerance = 50 / pow(10, $precision); // i.e. 50 cent in USD
        $rows = dbGetMulti(BIZUNO_DB_PREFIX.'journal_item', "ref_id=$mID");
        $diff = $ttlRow = $ttlDbt = $ttlCrt = 0;
        foreach ($rows as $row) {
            $diff += $row['debit_amount'] - $row['credit_amount'];
            if ($row['gl_type'] == 'ttl') {
                $ttlRow = $row['id'];
                $ttlDbt = $row['debit_amount'];
                $ttlCrt = $row['credit_amount'];
            }
        }
        if (abs($diff) > $tolerance) { return false; }
        $adjDbt = $ttlDbt ? ($ttlDbt - $diff) : 0;
        $adjCrt = $ttlCrt ? ($ttlCrt + $diff) : 0;
        msgAdd("Corrected main: $mID item: record $ttlRow, debit: $ttlDbt and credit: $ttlCrt diff: $diff, adjustment debit: $adjDbt, credit: $adjCrt");
        dbWrite(BIZUNO_DB_PREFIX.'journal_item', ['debit_amount'=>$adjDbt, 'credit_amount'=>$adjCrt], 'update', "id=$ttlRow");
        return true;
    }

    /**
     * Retrieves the list of GL accounts to close
     * @return array - gl accounts that are closed at the end of a Fiscal Year
     */
    private function getGLtoClose()
    {
        $acct_list = [];
        foreach (getModuleCache('phreebooks', 'chart') as $row) {
            if (in_array($row['type'], [30,32,34,42,44])) { $acct_list[] = $row['id']; }
        }
        return $acct_list;
    }

    /**
     * Purge all journal entries and associated tables. THIS IS A COMPLETE WIPE OF ANY JOURNAL RELATED DATABASE TABLES. Contacts and Inventory are unaffected.
     * @return null - user message will be created with status
     */
    public function glPurge()
    {
        global $io;
        if (!$security = validateAccess('admin', 4)) { return; }
        $data = clean('data', 'text', 'get');
        if ('purge' == $data) {
            dbGetResult("TRUNCATE TABLE ".BIZUNO_DB_PREFIX."inventory_history");
            dbGetResult("TRUNCATE TABLE ".BIZUNO_DB_PREFIX."journal_cogs_owed");
            dbGetResult("TRUNCATE TABLE ".BIZUNO_DB_PREFIX."journal_cogs_usage");
            dbGetResult("TRUNCATE TABLE ".BIZUNO_DB_PREFIX."journal_item");
            dbGetResult("TRUNCATE TABLE ".BIZUNO_DB_PREFIX."journal_main");
            dbGetResult("UPDATE ".BIZUNO_DB_PREFIX."journal_history SET beginning_balance=0, debit_amount=0, credit_amount=0, budget=0, stmt_balance=0, last_update=NULL");
            dbGetResult("UPDATE ".BIZUNO_DB_PREFIX."inventory SET qty_stock=0, qty_po=0, qty_so=0");
            $io->folderDelete("data/phreebooks/uploads");
            msgAdd(lang('phreebooks_purge_success', $this->moduleID), 'success');
            msgLog(lang('phreebooks_purge_success', $this->moduleID));
        } else {
            msgAdd("You must type the word 'purge' in the field and press the purge button!");
        }
    }

    /**
     * Prunes the COGS owed table by reposting purchase and then sales, done through ajax steps
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function pruneCogs(&$layout=[])
    {
        $result = dbGetMulti(BIZUNO_DB_PREFIX."journal_cogs_owed");
        if (sizeof($result) == 0) { return msgAdd("No rows to process!"); }
        foreach ($result as $row) { $rows[$row['sku']] = $row['journal_main_id']; }
        msgDebug("\nRows to process = ".print_r($rows, true));
        setUserCron('pruneCogs', ['cnt'=>0, 'total'=>sizeof($rows), 'rows'=>array_keys($rows)]);
        $layout = array_replace_recursive($layout, ['content' => ['action'=>'eval', 'actionData'=>"cronInit('pruneCogs', 'phreebooks/tools/pruneCogsNext');"]]);
    }

    /**
     * Controller for cogs pruning, manages a block to prune
     * @param type $layout
     */
    public function pruneCogsNext(&$layout=[])
    {
        $cron = getUserCron('pruneCogs');
        $sku = array_shift($cron['rows']);
        // find the last inventory increase that included this SKU
        $stmt = dbGetResult("SELECT m.id FROM ".BIZUNO_DB_PREFIX."journal_main m JOIN ".BIZUNO_DB_PREFIX."journal_item i ON m.id=i.ref_id
            WHERE m.journal_id IN (6,14,15,16) AND i.qty>0 AND i.sku='$sku' ORDER BY m.post_date DESC LIMIT 1");
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!empty($result['id'])) { $this->glRepost($result['id']); }
        $cron['cnt']++;
        if (sizeof($cron['rows']) == 0) {
            msgLog("PhreeBooks Tools (prune COGS owed) - ({$cron['total']} records)");
            $data = ['content'=>['percent'=>100,'msg'=>"Processed {$cron['total']} SKUs",'baseID'=>'pruneCogs','urlID'=>'phreebooks/tools/pruneCogsNext']];
            clearUserCron('pruneCogs');
        } else { // return to update progress bar and start next step
            $percent = floor(100*$cron['cnt']/$cron['total']);
            setUserCron('pruneCogs', $cron);
            $data = ['content'=>['percent'=>$percent,'msg'=>"Completed journal record id = {$result['id']}",'baseID'=>'pruneCogs','urlID'=>'phreebooks/tools/pruneCogsNext']];
        }
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Creates the download file for this dashboard
     * @global type $io
     * @return type
     */
    public function salesByRep()
    {
        global $io;
        $fqdn  = "\\bizuno\\sales_by_rep";
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/dashboards/sales_by_rep/sales_by_rep.php', $fqdn);
        $dash  = new $fqdn([]);
        $data  = $dash->getTotals();
        msgDebug("\nRecevied back from sales by rep: ".print_r($data, true));
        if (empty($data)) { return msgAdd('There is no data to download!'); }
        $io->download('data', arrayToCSV($data), "SalesByRep-".biz_date('Y-m-d').".csv");
    }

    /**
     * This method deletes all attachments prior to a specified date and clears the attach flag in the db
     * @return user status message
     */
    public function cleanAttach()
    {
        global $io;
        if (!$security = validateAccess('admin', 3)) { return; }
        $jIDs = clean('data', 'json', 'get');
        $jrnl = $mains = [];
        $dateLast='';
        foreach ($jIDs as $jID => $dateEnd) { 
            $jrnl[$jID] = clean($dateEnd, 'date');
            $dateLast = max($dateLast, $jrnl[$jID]);
        }   
        $results = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', "post_date<'$dateLast' AND journal_id<23 AND attach='1'", 'id', ['id', 'journal_id', 'post_date']);
        msgDebug("\nFound total number of db attach flags = ".sizeof($results));
        // Filter the mains based on post_date and journal specific requested dedlete date to trim to just the delete list
        foreach ($results as $row) {
            if ($row['post_date'] < $jrnl['j'.$row['journal_id']]) { $mains[] = $row['id']; }
            else { msgDebug("\nSkipping id = {$row['id']}, journal_id = {$row['journal_id']} with post_date = {$row['post_date']}"); }
        }
        msgDebug("\nFiltered down to = ".sizeof($mains));
        $files = $io->folderRead($this->dirUploads);
        msgDebug("\nFound total number of files = ".sizeof($files));
        foreach ($files as $fn) {
            $mainID = explode('_', $fn)[1];
            if (in_array($mainID, $mains)) {
                msgDebug("Deleting file: $fn");
                unlink(BIZUNO_DATA."$this->dirUploads{$fn}");
            }
        }
        if (sizeof($mains) > 0) {
            msgDebug("\nUpdating table phreebooks, removing attach for id = ".print_r($mains, true));
            dbWrite(BIZUNO_DB_PREFIX.'journal_main', ['attach'=>'0'], 'update', "id IN (".implode(',', $mains).")");
            msgAdd(sprintf(lang('msg_attach_clean_success', $this->moduleID), sizeof($mains)), 'success');
        } else {
            msgAdd(lang('msg_attach_clean_empty', $this->moduleID), 'info');
        }
    }
}
