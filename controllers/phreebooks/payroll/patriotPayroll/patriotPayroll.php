<?php
/*
 * @name Bizuno ERP - Patriot Payroll Interface Extension (https://www.patriotsoftware.com)
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
 * @version    7.x Last Update: 2025-07-07
 * @filesource /controllers/phreebooks/payroll/patriotPayroll/patriotPayroll.php
 */

namespace bizuno;

class patriotPayroll
{
    public $moduleID = 'phreebooks';
    public $methodDir= 'payroll';
    public $code     = 'patriotPayroll';
    public $totals;
    public $lang     = ['title'=>'Patriot Payroll',
        'description'=> 'The Patriot Payroll interface provides the ability to import your payroll operations from the PatriotSoftware.com website as journal entries in Bizuno.',
        'import_desc'=> 'Select an Patriot Payroll file (Reports -> Payroll Details -> Download Spreadsheet) to import into Bizuno as a general journal entry and press the Next icon. File types that can be imported include payroll downloads. (File must be of type csv and have the extension .csv)'];

    function __construct()
    {
        $this->totals = [
            'paymfg' => ['glAcct'=>'50700','type'=>'debit', 'total'=>0,'desc'=>'Salaries & Wages (Prod/Warehouse)'],
            'payofc' => ['glAcct'=>'60560','type'=>'debit', 'total'=>0,'desc'=>'Salaries & Wages (Office)'],
            'paymgt' => ['glAcct'=>'60580','type'=>'debit', 'total'=>0,'desc'=>'Salaries & Wages (Officers)'],
            'child'  => ['glAcct'=>'25100','type'=>'credit','total'=>0,'desc'=>'Employee Garnishments'],
            'health' => ['glAcct'=>'25000','type'=>'credit','total'=>0,'desc'=>'Prepaid Medical Deduction'],
            'fedpay' => ['glAcct'=>'23400','type'=>'credit','total'=>0,'desc'=>'Federal Taxes Withheld'],
            'feduta' => ['glAcct'=>'23500','type'=>'credit','total'=>0,'desc'=>'Federal Unemployment Tax'],
            'txsui'  => ['glAcct'=>'23750','type'=>'credit','total'=>0,'desc'=>'Texas State Unemployment (SUTA)'],
            'expmfg' => ['glAcct'=>'50850','type'=>'debit', 'total'=>0,'desc'=>'Payroll Tax Expense (Prod/Warehouse)'],
            'expofc' => ['glAcct'=>'60820','type'=>'debit', 'total'=>0,'desc'=>'Payroll Tax Expense (Office)'],
            'netpay' => ['glAcct'=>'10200','type'=>'credit','total'=>0,'desc'=>'Net Payroll Paid From Checking'],
            'scorp'  => ['glAcct'=>'60580','type'=>'debit', 'total'=>0,'desc'=>'S-Corp Ins (as extra pay)']];
    }

    /**
     *
     * @param type $layout
     */
    public function importForm(&$layout=[])
    {
        $modID = clean('modID', 'cmd', 'get');
        $fields= [
            'imgLogo' => ['order'=>10,'attr'=>['type'=>'img','height'=>'45','src'=>BIZBOOKS_URL_FS."&src=175/myExt/controllers/$this->moduleID/$this->methodDir/$this->code/$this->code.png"]],
            'desc'    => ['order'=>20,'html'=>"<p>{$this->lang['import_desc']}</p>",'attr'=>['type'=>'raw']],
            'selFile' => ['order'=>30,'attr'=>['type'=>'file']],
            'btnGo'   => ['order'=>40,'icon'=>'next', 'events'=>['onClick'=>"jqBiz('#formIOP').submit();"]]];
        $data = ['type'=>'popup','title'=>lang('phreebooks_import_journal_title'),'attr'=>['id'=>'winIPP'],
            'divs'     => [
                'formBOF'=> ['order'=>15,'type'=>'form',  'key' =>'formIOP'],
                'body'   => ['order'=>40,'type'=>'fields','keys'=>['imgLogo','desc','selFile','btnGo']],
                'formEOF'=> ['order'=>90,'type'=>'html',  'html'=>"</form>"]],
            'forms'    => ['formIOP'=>['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=$this->moduleID/admin/importGo&modID=$modID"]]],
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
        if (!$this->processCSV()) { return; }
        $today = biz_date('Y-m-d');
        bizAutoLoad(BIZBOOKS_ROOT.'controllers/phreebooks/journal.php', 'journal');
        $items = [];
        msgDebug("\nWorking with extracted totals = ".print_r($this->totals, true));
        foreach ($this->totals as $idx => $row) {
            $items[] = [
                'qty'           => 1,
                'gl_type'       => 'gl',
                'gl_account'    => $row['glAcct'],
                'description'   => $row['desc'],
                'debit_amount'  => $row['type']=='debit' ? $row['total'] : 0,
                'credit_amount' => $row['type']=='credit'? $row['total'] : 0,
                'post_date'     => $today];
            if ($idx=='netpay') { $postTotal = $row['total']; }
        }
        // *************** START TRANSACTION *************************
        dbTransactionStart();
        $ledger = new journal(0, 2);
        $ledger->main['primary_name_b']= "Patriot Payroll";
        $ledger->main['description']   = "Patriot Payroll for $today";
        $ledger->main['invoice_num']   = 'PatPay'.$today;
        $ledger->main['total_amount']  = $postTotal;
        $ledger->items                 = $items;
        if (!$ledger->Post()) { return; }
        dbTransactionCommit();
        // ***************  END TRANSACTION  *************************
        msgAdd(sprintf(lang('msg_gl_post_success'), lang('invoice_num_2'), $ledger->main['invoice_num']), 'success');
        msgLog('Patriot Payroll-'.lang('save')." ".lang('invoice_num_2')." ".$ledger->main['invoice_num']." (rID={$ledger->main['id']})");
        $layout = array_replace_recursive($layout, ['rID'=>$ledger->main['id'], 'content'=>  ['action'=>'eval','actionData'=>"jqBiz('#dgPhreeBooks').datagrid('reload'); jqBiz('#winIPP').window('destroy');"]]);
    }

    private function processCSV()
    {
        global $io;
        if (!$io->validateUpload('selFile', '', ['csv','txt'])) { return; }
        ini_set('auto_detect_line_endings', TRUE);
        $handle= fopen($_FILES['selFile']['tmp_name'], 'r');
        $keys  = false; //fgetcsv($handle);
        while (($values = fgetcsv($handle)) !== FALSE) {
            if (sizeof($values) <= 2) { continue; } // blank lines, single column header, skip
            if (empty($keys))         { $keys = $values; continue; } // first valid line are the keys
            if (sizeof($keys) <> sizeof($values)) { return msgAdd("The csv file is malformed, the total number of columns are not the same between the header and data!"); }
            $working = $this->cleanCurrency($values);
            $row = array_combine($keys, $working);
            msgDebug("\nProcessing row => ".print_r($row, true));
            switch ($row['Location Name']) {
                default:
                case '76092 (Mfg)': $pay = 'mfg'; $exp = 'mfg'; break;
                case '76092 (Ofc)': $pay = 'ofc'; $exp = 'ofc'; break;
                case '76092 (Exe)': $pay = 'mgt'; $exp = 'ofc'; break;
            }
            $this->totals['pay'.$pay]['total']+= $row['Regular'] + $row['Sick'] + $row['Vacation'] + $row['Holiday'] + $row['Bonus'] + $row['Time Off'];
            $this->totals['child']['total']   += $row['Child Support'];
            $this->totals['health']['total']  += $row['Healthcare'];
            $this->totals['fedpay']['total']  += $row['Federal Income Tax'] + $row['Medicare'] + $row['Social Security'] +
                                                 $row['Employer Medicare Tax'] + $row['Employer Social Security'];
            $this->totals['feduta']['total']  += $row['Federal Unemployment Tax'];
            $this->totals['txsui']['total']   += $row['Texas State Unemployment (SUTA)'];
            $this->totals['exp'.$exp]['total']+= $row['Employer Medicare Tax'] + $row['Employer Social Security'] +
                                                 $row['Federal Unemployment Tax'] + $row['Texas State Unemployment (SUTA)'];
            $this->totals['netpay']['total']  += $row['Net Pay'];
            $this->totals['scorp']['total']   += $row['S-Corp Owner Health'];  // should be zero as this is only for W-2 reporting
        }
        fclose($handle);
        ini_set('auto_detect_line_endings', FALSE);
        return true;
    }

    private function cleanCurrency($values=[])
    {
        $output = [];
        foreach ($values as $val) { $output[] = str_replace('$', '', $val); }
        return $output;
    }
}
