<?php
/*
 * @name Bizuno ERP - Stripe Interface Extension
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
 * @filesource /controllers/api/funnels/ifStripe/ifStripe.php
 */

namespace bizuno;

class ifStripe {
    public  $moduleID = 'api';
    public  $methodDir= 'funnels';
    public  $code     = 'ifStripe';
    private $J12      = [];
    private $J13      = [];
    private $counts   = ['j12'=>0,'j13'=>0]; // counts for each journal
    private $totals   = ['j12'=>0,'j13'=>0]; // totals for each journal
    private $totFee   = 0; // total fees charged
    private $totAll   = 0; // Total sales/refunds
    private $totNet   = 0; // total cash transferred into bank account, inclusive of fees
    public  $defaults;
    public  $settings;
    public  $lang     = ['title' => 'Stripe Interface',
        'description' => 'The Stripe interface provides capability to import purchases and orders. It also creates a journal entry for the fees charged.',
        'gl_acct_purchases_lbl' => 'Purchases GL Account',
        'gl_acct_sales_lbl' => 'Sales GL Account',
        'gl_acct_cash_lbl' => 'Payment GL Account',
        'gl_acct_exp_lbl' => 'Expense GL Account',
        'gl_acct_purch_tip' => 'GL Account to use for recording vendor purchases (Typically an inventory GL account type)',
        'gl_acct_sales_tip' => 'GL Account to use for recording sales (Typically an income GL account type)',
        'gl_acct_cash_tip' => 'GL Account to use for recording payment (Typically an cash GL account type)',
        'gl_acct_exp_tip' => 'GL Account to use for recording processing fees (Typically an expense GL account type)',
        'import_orders' => 'Import Transactions',
        'import_orders_desc' => 'Select an exported Stripe .csv file to process. This is typicallly the Itemized Payout Reconciliation report. This process should be run after the completion of a fiscal period.',
        'processing_fees' => 'Stripe processing fees'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->lang    = array_replace(getLang($this->moduleID), $this->lang);
        $this->defaults= ['contact_id'=>0,'catalog_field'=>'amazon','ship_std'=>0,'ship_exp'=>0,'gift_wrap_sku'=>'','notes_sku'=>'','auto_journal'=>0,
            'gl_acct_purch'=>getModuleCache('phreebooks','settings','vendors',  'gl_purchases'),
            'gl_acct_sales'=>getModuleCache('phreebooks','settings','customers','gl_sales'),
            'gl_acct_cash' =>getModuleCache('phreebooks','settings','customers','gl_cash'),
            'gl_acct_exp'  =>getModuleCache('phreebooks','settings','customers','gl_expense')];
        $userMeta      = getMetaMethod($this->methodDir, $this->code);
        $this->settings= array_replace($this->defaults, !empty($userMeta['settings']) ? $userMeta['settings'] : []);
    }

    public function settingsStructure()
    {
        $data = [
            'gl_acct_sales'=> ['attr'=>['type'=>'ledger','id'=>'general_gl_acct_sales','value'=>$this->settings['gl_acct_sales']]],
            'gl_acct_cash' => ['attr'=>['type'=>'ledger','id'=>'general_gl_acct_cash', 'value'=>$this->settings['gl_acct_cash']]],
            'gl_acct_exp'  => ['attr'=>['type'=>'ledger','id'=>'general_gl_acct_exp',  'value'=>$this->settings['gl_acct_exp']]]];
        foreach (array_keys($data) as $key) {
            $data[$key]['label'] = !empty($this->lang[$key."_lbl"]) ? $this->lang[$key."_lbl"] : $key;
            if (!empty($this->lang[$key."_tip"])) { $data[$key]['tip'] = $this->lang[$key."_tip"]; }
        }
        return $data;
    }

    /**
     * Home landing page for this module
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function home(&$layout=[])
    {
        if (!$security = validateAccess('ifStripe', 2)) { return; }
        $data = ['title'=>$this->lang['title'],
            'divs'=>[
                'head'    => ['order'=> 1,'type'=>'fields','keys'=>['imgLogo']],
                'lineBR'  => ['order'=> 2,'type'=>'html',  'html'=>"<br />"],
                'manager' => ['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'setOrder'=> ['order'=>30,'type'=>'panel','key'=>'setOrder','classes'=>['block33']]]]],
            'panels' => [
                'setOrder'  => ['label'=>$this->lang['import_orders'],'type'=>'divs','divs'=>[
                    'formBOF' => ['order'=>10,'type'=>'form',  'key' =>'frmOrders'],
                    'desc'    => ['order'=>20,'type'=>'html',  'html'=>"<p>".$this->lang['import_orders_desc']."</p>"],
                    'body'    => ['order'=>30,'type'=>'fields','keys'=>['fileOrders','btnOrders']],
                    'formEOF' => ['order'=>90,'type'=>'html',  'html'=>"</form>"]]]],
            'forms'  => ['frmOrders'=>['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/admin/ordersGo&modID=$this->code"]]],
            'fields' => [
                'imgLogo'   => ['styles'=>['cursor'=>'pointer'],'events'=>['onClick'=>"winHref('https://www.stripe.com');"],'attr'=>['type'=>'img','src'=>BIZUNO_URL_PORTAL.'0/controllers/api/funnels/ifStripe/ifStripe.png']],
                'fileOrders'=> ['order'=>30,'attr'  =>['type'=>'file']],
                'btnOrders' => ['order'=>40,'icon'=>'next', 'events'=>['onClick'=>"jqBiz('body').addClass('loading'); jqBiz('#frmOrders').submit();"]]],
            'jsReady'=>['init'=>"ajaxForm('frmOrders');"]];
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }

    /**
     * Processes the .csv file, posts sales to journal 12 and credits to journal 13
     * @global \bizuno\type $io
     * @param array $layout
     * @return modified $layout
     */
    public function ordersGo(&$layout=[])
    {
        if (!$security = validateAccess('j2_mgr', 2))  { return; }
        if (!$this->processCSV()) { return; }
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/journal.php', 'journal');
        // *************** START TRANSACTION *************************
        dbTransactionStart();
        foreach ($this->J12 as $entry) { if (!$this->postJrnl($entry, 12)) { return; } } // customer purchases
        foreach ($this->J13 as $entry) { if (!$this->postJrnl($entry, 13)) { return; } } // custoemr refunds
        if (!$this->postJ02()) { return; } // Post the fees to the journal
        dbTransactionCommit();
        // ***************************** END TRANSACTION *******************************
        msgAdd("Finished processing, here are some stats:", 'info');
        msgAdd("{$this->counts['j12']} Customer sales processed totaling $ "  .number_format($this->totals['j12'], 2), 'info');
        msgAdd("{$this->counts['j13']} Customer refunds processed totaling $ ".number_format($this->totals['j13'], 2), 'info');
        msgAdd("Total sales less refunds = $ ".number_format($this->totAll, 2), 'info');
        msgAdd("Total fees charged = $ ".number_format($this->totFee, 2), 'info');
        msgAdd("Total Stripe deposit amount = $ ".number_format($this->totNet, 2), 'info');
        msgAdd("Total Stripe fee percent ".number_format(100*$this->totFee/$this->totAll, 2)." %", 'info');
        msgLog("Stripe deposit processed ($ ".number_format($this->totNet, 2).")");
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jqBiz('body').removeClass('loading');"]]);
    }

    private function processCSV()
    {
        global $io;
        if (!$io->validateUpload('fileOrders', '', ['csv','txt'])) { return; }
        ini_set('auto_detect_line_endings', TRUE);
        $handle= fopen($_FILES['fileOrders']['tmp_name'], 'r');
        $keys  = false; //fgetcsv($handle);
        while (($values = fgetcsv($handle)) !== FALSE) {
            if (sizeof($values) <= 2) { continue; } // blank lines, single column header, skip
            if (empty($keys))         { $keys = $values; continue; } // first valid line are the keys
            if (sizeof($keys) <> sizeof($values)) { return msgAdd("The csv file is malformed, the total number of columns are not the same between the header and data!"); }
            $row     = array_combine($keys, $values);
            $desc    = trim($row['description']);
            $isRefund= substr($desc, 0, 6)=='REFUND' ? true : false;
            if ($isRefund) { $row['description'] = substr($desc, strpos($desc, '('), -1); }
            if (strpos($desc, 'Entry ID:') !== false) { // Manual entry
                $parts = explode(',', trim($row['description']), 2);
                $email = '';
                $prods = explode(':', $parts[1]);
                $items = [['qty'=>1, 'sku'=>'', 'description'=>trim($prods[1]) ]];
            } else { // j12
                $parts = explode('-', trim($row['description']), 3);
                $email = trim($parts[1]);
                $items = $this->extractSKUs($parts[2], '-');
            }
            $split = explode(' ', trim($parts[0]));
            $ordNum= $split[count($split)-1];
            $main  = [
                'journal_id'  => $isRefund ? 13 : 12,
                'post_date'   => substr($row['created'], 0, 10),
                'total_amount'=> $row['gross'],
                'invoice_num' => $ordNum,
                'description' => trim($parts[0]),
                'email_b'     => $email,
                'items'       => $items];
            if ($isRefund) { $this->J13[] = $main; }
            else           { $this->J12[] = $main; }
            // track some totals for validation
            $this->totFee += floatval($row['fee']);
            $this->totAll += floatval($row['gross']);
            $this->totNet += floatval($row['net']);
        }
        msgDebug("\nFinished parsing csv with total = $this->totAll and fee = $this->totFee");
        fclose($handle);
        ini_set('auto_detect_line_endings', FALSE);
        return true;
    }

    private function extractSKUs($strSKU, $sep='-')
    {
        $things = explode($sep, trim($strSKU)); // breaks apart items
        $items  = [];
        foreach ($things as $thing) {
            $what = explode('x', trim($thing));
            $items[] = ['qty'=>trim($what[0]), 'sku'=>trim($what[1])];
        }
        return $items;
    }

    private function postJ02()
    {
        msgDebug("\nEntering postJ02, working with merchant fees = $this->totFee");
        $ledger = new journal(0, 2, $this->last_date);
        $ledger->main['primary_name_b']= $this->lang['title'];
        $ledger->main['description']   = $this->lang['processing_fees'];
        $ledger->main['invoice_num']   = 'Stripe'.str_replace('-', '', $this->last_date);
        $ledger->main['total_amount']  = $this->totFee;
        $ledger->items[] = [
            'qty'          => 1,
            'gl_type'      => 'gl',
            'gl_account'   => $this->settings['gl_acct_cash'],
            'description'  => $this->lang['processing_fees'],
            'credit_amount'=> $this->totFee,
            'post_date'    => $this->last_date];
        $ledger->items[] = [
            'qty'          => 1,
            'gl_type'      => 'gl',
            'gl_account'   => $this->settings['gl_acct_exp'],
            'description'  => $this->lang['processing_fees'],
            'debit_amount' => $this->totFee,
            'post_date'    => $this->last_date];
        return $this->postMain($ledger)? true : false;
    }

    private function postJrnl($order, $jID)
    {
        msgDebug("\nEntering postJrnl, working with order = ".print_r($order, true));
        $this->last_date = $order['post_date'];
        $ledger = new journal(0, $jID, $order['post_date']);
        $this->addCustomer($ledger->main, $order['email_b'], $jID);
        // Fill address information if in contacts table
        $ledger->main['description'] = $order['description'];
        $ledger->main['invoice_num'] = $jID==13 ? 'CM'.$order['invoice_num'] : $order['invoice_num'];
        $ledger->main['total_amount']= $order['total_amount']<0 ? -$order['total_amount'] : $order['total_amount'];
        $ledger->main['gl_acct_id']  = $this->settings['gl_acct_cash'];
        $qtyTotal = 0;
        foreach ($order['items'] as $row) { $qtyTotal += $row['qty']; }
        $avgPrice = $order['total_amount'] / $qtyTotal;
        foreach ($order['items'] as $row) {
            $item = $this->findSKU($row['sku'], $avgPrice);
            $ledger->items[] = [
                'qty'          => $row['qty'],
                'sku'          => $item['sku'],
                'gl_type'      => 'itm',
                'gl_account'   => $item['gl_account'],
                'description'  => !empty($row['description']) ? $row['description'] : $item['description_short'],
                'credit_amount'=> $item['full_price']<0 ? 0 :  $item['full_price'] * $row['qty'],
                'debit_amount' => $item['full_price']>0 ? 0 : -$item['full_price'] * $row['qty'],
                'post_date'    => $order['post_date']];
        }
        $ledger->items[] = [
            'qty'          => 1,
            'gl_type'      => 'ttl',
            'gl_account'   => $this->settings['gl_acct_cash'],
            'description'  => 'Stripe total',
            'credit_amount'=> $order['total_amount']>0 ? 0 : -$order['total_amount'],
            'debit_amount' => $order['total_amount']<0 ? 0 :  $order['total_amount'],
            'post_date'    => $order['post_date']];
        $this->counts['j'.$jID]++;
        $this->totals['j'.$jID] += $ledger->main['total_amount'];
        return $this->postMain($ledger)? true : false;
    }

    private function addCustomer(&$main, $email)
    {
        $cust = !empty($email) ? dbGetRow(BIZUNO_DB_PREFIX.'contacts', "email='$email'") : [];
        msgDebug("\nfound customer record: ".print_r($cust, true));
        $main['contact_id_b']  = !empty($cust['ref_id'])      ? $cust['ref_id']      : 0;
        $main['address_id_b']  = !empty($cust['address_id'])  ? $cust['address_id']  : 0;
        $main['primary_name_b']= !empty($cust['primary_name'])? $cust['primary_name']: $email;
        $main['contact_b']     = !empty($cust['contact'])     ? $cust['contact']     : '';
        $main['address1_b']    = !empty($cust['address1'])    ? $cust['address1']    : '';
        $main['address2_b']    = !empty($cust['address2'])    ? $cust['address2']    : '';
        $main['city_b']        = !empty($cust['city'])        ? $cust['city']        : '';
        $main['state_b']       = !empty($cust['state'])       ? $cust['state']       : '';
        $main['postal_code_b'] = !empty($cust['postal_code']) ? $cust['postal_code'] : '';
        $main['country_b']     = !empty($cust['country'])     ? $cust['country']     : 'USA';
        $main['telephone1_b']  = !empty($cust['telephone1'])  ? $cust['telephone1']  : '';
        $main['email_b']       = !empty($cust['email'])       ? $cust['email']       : $email;
        if (empty($cust) && empty($email)) { // generic sale/refund
            $main['primary_name_b']= 'Uncategorized Sale/Refund';
            $main['email_b']       = '';
        }
    }

    private function findSKU($skuGuess, $avgPrice)
    {
        if (!empty($skuGuess)) {
            $sku = dbGetRow(BIZUNO_DB_PREFIX.'inventory', "description_short='$skuGuess' OR description_sales='$skuGuess'");
            if (!empty($sku)) { return $sku; }
        }
        return ['sku'=>'','full_price'=>$avgPrice,'gl_account'=>$this->settings['gl_acct_sales'],'description_short'=>$skuGuess];
    }

    private function postMain($ledger)
    {
        msgDebug("\nReady to post with main: ".print_r($ledger->main, true));
        msgDebug("\nReady to post with items: ".print_r($ledger->items, true));
        $success = $ledger->Post();
        return !empty($success) ? true : false;
    }
}
