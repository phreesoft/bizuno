<?php
/*
 * @name Bizuno ERP - PayrollCentric Payroll Interface Extension (https://www.payrollcentric.com)
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
 * @version    7.x Last Update: 2025-08-12
 * @filesource /controllers/phreebooks/payroll/payrollCentric/payrollCentric.php
 */

namespace bizuno;

class payrollCentric
{
    public  $moduleID    = 'phreebooks';
    public  $methodDir   = 'payroll';
    public  $code        = 'payrollCentric';
    private $totalDebits = 0;
    private $totalCredits= 0;
    public  $lang        = ['title'=>'PayrollCentric',
        'description'=> 'The PayrollCentric Payroll interface provides the ability to import your payroll operations from the PayrollCentric.com website as journal entries in Bizuno.',
        'import_desc'=> 'Select a PayrollCentric Payroll file (Reports -> Payroll Details -> Download Spreadsheet) to import into Bizuno as a general journal entry and press the Next icon. File types that can be imported include payroll downloads. (File must be of type csv and have the extension .csv)'];
 
    function __construct() { }

    public function importForm(&$layout=[])
    {
        $fields= [
            'imgLogo' => ['order'=>10,'attr'=>['type'=>'img','height'=>'45','src'=>BIZUNO_URL_FS."0/controllers/$this->moduleID/$this->methodDir/$this->code/logo.png"]],
            'desc'    => ['order'=>20,'html'=>"<p>{$this->lang['import_desc']}</p>",'attr'=>['type'=>'raw']],
            'selFile' => ['order'=>30,'attr'=>['type'=>'file']],
            'btnGo'   => ['order'=>40,'icon'=>'next', 'events'=>['onClick'=>"jqBiz('#formIOP').submit();"]]];
        $data = ['type'=>'popup','title'=>lang('import_journal_title'),'attr'=>['id'=>'winIPP'],
            'divs'     => [
                'formBOF'=> ['order'=>15,'type'=>'form',  'key' =>'formIOP'],
                'body'   => ['order'=>40,'type'=>'fields','keys'=>['imgLogo','desc','selFile','btnGo']],
                'formEOF'=> ['order'=>90,'type'=>'html',  'html'=>"</form>"]],
            'forms'    => ['formIOP'=>['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/$this->methodDir/importGo&modID=$this->code"]]],
            'fields'   => $fields,
            'jsReady'  => ['initIOP'=>"ajaxForm('formIOP', true);"]]; // do not check for preSubmit as it picks the journal edit function ???
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Imports the uploaded file and creates a GL entry
     * @global class $io - import/export class
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function importGo(&$layout=[])
    {
        if (!$security = validateAccess('j2_mgr', 2)) { return; }
        $items = [];
        $rows = $this->prepData();
        msgDebug("\nWorking with item rows = ".print_r($rows, true));
        $today = biz_date('Y-m-d');
        if (empty($rows)) { return msgAdd("Bad file uploaded, please check it out and try again!"); }
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/journal.php', 'journal');
        $balance = $total = 0;
        foreach ($rows as $row) {
            if (empty($row['G/L Account'])) { continue; } // blank lines, single column header, skip
            $date = clean($row['Date'], 'date');
            $balance += $row['Amount'];
            $postDate = !empty($date) ? $date : $today;
            if ($row['Amount']>0) { $total += floatval($row['Amount']); }
            $items[] = [
                'qty'           => 1,
                'gl_type'       => 'gl',
                'gl_account'    => $row['G/L Account'],
                'description'   => $row['Description'],
                'debit_amount'  => floatval($row['Amount'])>0 ?  floatval($row['Amount']) : 0,
                'credit_amount' => floatval($row['Amount'])<0 ? -floatval($row['Amount']) : 0,
                'post_date'     => $postDate];
            
        }
        if (round($balance, 2) != 0) { return msgAdd("Journal is out of balance. Calculated Debits - Credits = $balance, This needs to be zero to post. Bailing!", 'trap'); }
        // *************** START TRANSACTION *************************
        dbTransactionStart();
        $ledger = new journal(0, 2, $postDate);
        $ledger->main['primary_name_b']= 'PayrollCentric';
        $ledger->main['description']   = "PayrollCentric Payroll for $postDate";
        $ledger->main['invoice_num']   = 'PayCent-'.str_replace('-', '', $postDate);
        $ledger->main['total_amount']  = $total;
        $ledger->items                 = $items;
        if (!$ledger->Post()) { msgAdd("A trace file was created to troubleshoot this issue.", 'trap'); return; }
        dbTransactionCommit();
        // ***************  END TRANSACTION  *************************
        msgAdd(sprintf(lang('msg_gl_post_success'), lang('invoice_num_2'), $ledger->main['invoice_num']), 'success');
        msgLog('PayrollCentric - '.lang('save')." ".lang('invoice_num_2')." ".$ledger->main['invoice_num']." (rID={$ledger->main['id']})");
        $layout = array_replace_recursive($layout, ['rID'=>$ledger->main['id'], 'content'=>  ['action'=>'eval','actionData'=>"jqBiz('#dgPhreeBooks').datagrid('reload'); jqBiz('#winIPP').window('destroy');"]]);
    }

    private function prepData()
    {
        global $io;
        if (!$io->validateUpload('selFile', '', ['csv'])) { return; } // removed type=text as windows edited files fail the test
        $output= [];
        $rows  = array_map('str_getcsv', file($_FILES['selFile']['tmp_name']));
        $head  = array_shift($rows);
        foreach ($rows as $row) { $output[] = array_combine($head, $row); }
        return $output;
    }
}
