<?php
/*
 * Tools for contacts module
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
 * @filesource /controllers/contacts/tools.php
 */

namespace bizuno;

class contactsTools
{
    public $moduleID = 'contacts';
    public $srcID = 0;
    public $destID = 0;
    public $srcC = 0;
    public $destC = 0;

    function __construct()
    {
        $this->lang = getLang($this->moduleID);
    }

    /**
     * form builder - Merges 2 database contact ID's to a single record
     * @param array $layout - current working structure
     * @return modified $layout
     */
    public function merge(&$layout=[])
    {
        $icnSave= ['icon'=>'save','label'=>lang('merge'),
            'events'=>['onClick'=>"jsonAction('$this->moduleID/tools/mergeSave', jqBiz('#mergeSrc').val(), jqBiz('#mergeDest').val());"]];
        $props  = ['defaults'=>['type'=>'a', 'callback'=>''],'attr'=>['type'=>'contact']];
        $html   = '<p>'.lang('msg_contacts_merge_src', $this->moduleID) .'</p><p>'.html5('mergeSrc', $props).'</p>'.
                  '<p>'.lang('msg_contacts_merge_dest', $this->moduleID).'</p><p>'.html5('mergeDest',$props).'</p>'.
                  '<p>'.lang('msg_contacts_merge_bill', $this->moduleID).'</p>'   .html5('icnMergeSave', $icnSave);
        $data   = ['type'=>'popup','title'=>lang('contacts_merge', $this->moduleID),'attr'=>['id'=>'winMerge'],
            'divs'   => ['body'=>['order'=>50,'type'=>'html','html'=>$html]],
            'jsReady'=> ['init'=>"bizFocus('mergeSrc');"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Performs the merge of 2 contacts in the db
     * @param array $layout - current working structure
     * @return modifed $layout
     */
    public function mergeSave(&$layout=[])
    {
        global $io;
        if (!$security = validateAccess('admin', 4)) { return; }
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/contacts/address.php','contactsAddress');
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/payment/wallet.php',  'paymentWallet');
        $this->srcID = clean('rID', 'integer', 'get'); // record ID to merge
        $this->destID= clean('data','integer', 'get'); // record ID to keep
        if (!$this->srcID || !$this->destID) { return msgAdd("Bad IDs, Source ID = $this->srcID and Destination ID = $this->destID"); }
        if ($this->srcID == $this->destID)   { return msgAdd("Source and destination cannot be the same!"); }
        // Get the two records
        $this->srcC  = dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id=$this->srcID");
        $this->destC = dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id=$this->destID");
        dbTransactionStart();
        msgAdd(lang('stats').': ', 'info');
        $this->mergeTypes();
        $this->mergeMeta();
        $this->mergeNotes();
        $this->mergeBookmarks();
        $this->mergeNotExists();
        $this->mergeChangeCID();
        $this->mergeSpecial();
        $this->mergeWallet();
        dbSanitizeDates($this->destC, ['first_date', 'date_last', 'last_date_1', 'last_date_2']);
        dbWrite(BIZUNO_DB_PREFIX.'contacts', $this->destC, 'update', "id=$this->destID"); // save all of the changes
        msgAdd("deleted contact {$this->srcC['short_name']} (ID: {$this->srcID})", 'info');
        // Move attachments
        msgDebug("\nMoving file at path: ".getModuleCache('contacts', 'properties', 'attachPath', 'contacts')." from rID_{$this->srcID}_ to rID_{$this->destID}_");
        $io->fileMove(getModuleCache('contacts', 'properties', 'attachPath', 'contacts'), "rID_{$this->srcID}_", "rID_{$this->destID}_");
        // Finish things up
        dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."contacts WHERE id=$this->srcID");
        dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."contacts_meta WHERE ref_id=$this->srcID");
        dbTransactionCommit();
        msgLog(lang("contacts").'-'.lang('merge')." - $this->srcID => $this->destID");
        $data = ['content'=>['action'=>'eval','actionData'=>"bizWindowClose('winMerge'); bizGridReload('dgContacts');"]];
        $layout = array_replace_recursive($layout, $data);
    }
    private function mergeTypes()
    {
       $types = ['ctype_b', 'ctype_c', 'ctype_e', 'ctype_i', 'ctype_j', 'ctype_u', 'ctype_v'];
       foreach ($types as $type) { if (!empty($this->srcC[$type])) { $this->destC[$type] = '1'; } }
    }
    private function mergeMeta()
    {
        $keys = ['address_b', 'address_i', 'address_s', 'crm_project', 'price_c', 'price_v', 'reminder'];
        foreach($keys as $key) {
            $cnt = dbWrite(BIZUNO_DB_PREFIX.'contacts_meta', ['ref_id'=>$this->destID], 'update', "ref_id=$this->srcID AND meta_key='$key'");
            msgAdd("Merged table: contact_meta, key: $key - total ".(!empty($cnt) ? $cnt : '0')." records", 'info');
        }
    }
    private function mergeNotes()
    {
        $noteS = dbMetaGet(0, 'notes', 'contacts', $this->srcID);
        $noteD = dbMetaGet(0, 'notes', 'contacts', $this->destID);
        $noteM = implode("\n<br />", [!empty($noteD['value'])?$noteD['value']:'', !empty($noteS['value'])?$noteS['value']:'']);
        msgDebug("\nMerged notes:", 'info');
        if (!empty($noteM)) { dbMetaSet($noteD['_rID'], 'notes', $noteM, 'contacts', $this->destID); }
    }
    private function mergeBookmarks()
    {
        // Merge bookmarks_docs
        $docsS = dbMetaGet(0, 'bookmarks_docs', 'contacts', $this->srcID);
        $docsD = dbMetaGet(0, 'bookmarks_docs', 'contacts', $this->destID);
        $docsM = array_unique(array_merge(!empty($docsD['value'])?$docsD['value']:[], !empty($docsS['value'])?$docsS['value']:[]), SORT_REGULAR);
        msgDebug("\nMerged bookmarked docs, total is now ".sizeof($docsM), 'info');
        if (!empty($docsM)) { dbMetaSet($docsD['_rID'], 'bookmarks_docs', $docsM, 'contacts', $this->destID); }
        // Merge bookmarks_phreeform
        $formS = dbMetaGet(0, 'bookmarks_phreeform', 'contacts', $this->srcID);
        $formD = dbMetaGet(0, 'bookmarks_phreeform', 'contacts', $this->destID);
        $formM = array_unique(array_merge(!empty($formD['value'])?$formD['value']:[], !empty($formS['value'])?$formS['value']:[]), SORT_REGULAR);
        msgDebug("\nMerged bookmarked reports, total is now ".sizeof($formM), 'info');
        if (!empty($formM)) { dbMetaSet($formD['_rID'], 'bookmarks_phreeform', $formM, 'contacts', $this->destID); }
    }
    private function mergeNotExists()
    {
        $opts= dbMetaGet(0, 'user_options', 'contacts', $this->destID);
        if (empty($opts)) {
            $src = dbMetaGet(0, 'user_options', 'contacts', $this->srcID);
            if (!empty($src)) { dbWrite(BIZUNO_DB_PREFIX.'contacts_meta', ['ref_id'=>$this->destID], 'update', "ref_id=$this->srcID and meta_key='user_options'"); }
        }
        $prof= dbMetaGet(0, 'user_profile', 'contacts', $this->destID);
        if (empty($prof)) {
            $src = dbMetaGet(0, 'user_profile', 'contacts', $this->srcID);
            if (!empty($src)) { dbWrite(BIZUNO_DB_PREFIX.'contacts_meta', ['ref_id'=>$this->destID], 'update', "ref_id=$this->srcID and meta_key='user_profile'"); }
        }
        $keys = dbGetMulti(BIZUNO_DB_PREFIX.'contacts_meta', "meta_key LIKE 'dashboard_%'", '', ['DISTINCT meta_key'], 0, false);
        foreach ($keys as $key) {
            $dest= dbMetaGet(0, $key['meta_key'], 'contacts', $this->destID);
            if (!empty($dest)) { continue; } // if the destination exists, don't overwrite it
            $src = dbMetaGet(0, $key['meta_key'], 'contacts', $this->srcID);
            if (!empty($src)) { dbWrite(BIZUNO_DB_PREFIX.'contacts_meta', ['ref_id'=>$this->destID], 'update', "ref_id=$this->srcID and meta_key='{$key['meta_key']}'"); }
        }
    }
    private function mergeChangeCID()
    {
        $this->mergeMetaTax('tax_rate_c', 'taxAuths');
        $this->mergeMetaTax('tax_rate_v', 'taxAuths');
        $metaT = dbMetaGet('%', 'training'); // common_meta:training
        msgDebug("\nRead ".sizeof($metaT)." rows from key training.");
        if (!empty($metaT)) {
            foreach($metaT as $meta) {
                $found = false;
                if ($meta['user_id']==$this->srcID) { $meta['user_id'] = $this->destID; $found = true; }
                if ($found) {
                    msgDebug("\nWriting updated meta = ".print_r($meta, true));
                    $metaID = metaIdxClean($meta);
                    dbMetaSet($metaID, 'training', $meta);
                }
            }
        }
        // @TODO - journal_meta:returns Many places need merge.
        //$rowsR= dbMetaGet(0, 'return', 'journal', '%');
        // @TODO - journal_meta:qa_ticket QA Tickets are journal entries, should be able to change in function mergeSpecial
        // Is the cID in both db table and meta? if also in meta then iterration is needed
    }
    private function mergeMetaTax($key, $index)
    {
        $taxC = dbMetaGet('%', $key);
        msgDebug("\nRead ".sizeof($taxC)." rows from key $key.");
        if (empty($taxC)) { return; }
        foreach($taxC as $rate) {
            if (isset($rate[$index]) && is_array($rate[$index]) && empty($rate[$index]['rows'])) { // make new format
                $rate[$index] = ['total'=>sizeof($rate[$index]), 'rows'=>$rate[$index]];
            }
            $found = false;
            foreach ($rate[$index]['rows'] as $idx=>$row) {
                if ($row['cID']==$this->srcID) {
                    $rate[$index]['rows'][$idx] = $this->destID;
                    $found = true;
                }
            }
            if ($found) {
                msgDebug("\nWriting updated meta = ".print_r($rate, true));
                $metaID = metaIdxClean($rate);
                dbMetaSet($metaID, $key, $rate);
            }
        } 
    }
    private function mergeSpecial()
    {
        // Some easy changes to the db tables 
        $aCnt  = dbWrite(BIZUNO_DB_PREFIX.'audit_log',    ['user_id'=>$this->destID],     'update', "user_id=$this->srcID"); // audit log
        msgAdd("Journal main address billing = $aCnt; ", 'info');
        $cCnt  = dbWrite(BIZUNO_DB_PREFIX.'contacts_log', ['contact_id'=>$this->destID],  'update', "contact_id=$this->srcID"); // Merge contacts Log
        msgAdd("Contacts Log changes = $cCnt; ", 'info');
        $bCnt  = dbWrite(BIZUNO_DB_PREFIX.'journal_main', ['contact_id_b'=>$this->destID],'update', "contact_id_b=$this->srcID"); // journal_main billing
        msgAdd("Journal main address billing = $bCnt; ", 'info');
        $sCnt  = dbWrite(BIZUNO_DB_PREFIX.'journal_main', ['contact_id_s'=>$this->destID],'update', "contact_id_s=$this->srcID"); // journal_main shipping
        msgAdd("Journal main address shipping = $sCnt; ", 'info');
        // special merge keeping specific information from one record or another based on certain criteria
        $this->destC['first_date'] = min($this->srcC['first_date'], $this->destC['first_date']);
        $this->destC['date_last']  = date('Y-m-d H:i:s');
        $this->destC['last_date_1']= max($this->srcC['last_date_1'],$this->destC['last_date_1']);
        $this->destC['last_date_2']= max($this->srcC['last_date_2'],$this->destC['last_date_2']);
        $fields = ['price_sheet', 'terms', 'tax_exempt', 'tax_rate_id', 'contact_first', 'contact_last', 'flex_field_1', 'account_number', 'gov_id_number',
            'primary_name', 'contact', 'address1', 'address2', 'city', 'state', 'postal_code', 'country',
            'contact_first', 'contact_last', 'flex_field_1', 'telephone1', 'telephone2', 'telephone3', 'telephone4', 'email', 'website'];
        foreach ($fields as $field) { 
            if (empty($this->destC[$field]) && !empty($this->srcC[$field])) { $this->destC[$field] = $this->srcC[$field]; }
        }
    }
    private function mergeWallet()
    {
        $wallet= new \bizuno\paymentWallet();
        $srcID = getWalletID($this->srcID);
        $destID= getWalletID($this->destID);
        msgDebug("\nMerging wallet, Src cID: $srcID => Dest cID: $destID");
        if ($wallet->modifyID($srcID, $destID)) { msgAdd("\nWallet merge successful.", 'info'); }
    }
    
    /**
     * Closes all customer quotes (Journal 9) before the supplied date
     * @return success message with number of records closed
     */
    public function j9Close()
    {
        if (!$security = validateAccess('j9_mgr', 3)) { return; }
        $def = localeCalculateDate(biz_date('Y-m-d'), 0, -1);
        $date= clean('data', ['format'=>'date', 'default'=>$def], 'get');
        $cnt = dbWrite(BIZUNO_DB_PREFIX."journal_main", ['closed'=>'1'], 'update', "journal_id=9 AND post_date<'$date'");
        msgAdd(sprintf(lang('close_j9_success', $this->moduleID), $cnt), 'success');
    }

    /**
     * Generates a pop up bar chart for monthly sales
     * @param array $layout - current working structure
     * @return modified $layout
     */
    public function chartSales(&$layout=[])
    {
        $rID   = clean('rID', 'integer', 'get');
        $type  = clean('cType', 'char', 'get');
        if (!$rID) { return msgAdd(lang('err_bad_id')); }
        $struc = $this->chartSalesData($rID, $type);
        $title = $type=='v' ? lang('purchases_by_month', $this->moduleID) : lang('sales_by_month', $this->moduleID);
        $action= BIZUNO_URL_AJAX."&bizRt=$this->moduleID/tools/chartSalesGo&rID=$rID&type=$type";
        $layout = array_merge_recursive($layout, ['type'=>'divHTML',
            'divs'=>[
                'divBOF' => ['order'=> 1,'type'=>'html','html'=>"<div>"],
                'divEOF' => ['order'=>99,'type'=>'html','html'=>"</div>"]]]);
        googleColumn($layout, 'contactChart', ['type'=>'gColumn', 'title'=>$title, 'data'=>array_values($struc), 'callback'=>$action]); // , 'legend'=>$legend
    }

    /**
     * Calculates the contact sales/purchase chart data/download data for a given range
     * @param type $rID
     * @param type $type
     * @return type
     */
    private function chartSalesData($rID, $type, $limit=12)
    {
        $dates= localeGetDates(localeCalculateDate(biz_date('Y-m-d'), 0, -$limit));
        $debJID = $type=='v' ? 6 : 12;
        msgDebug("\nDates = ".print_r($dates, true));
        $sql = "SELECT MONTH(post_date) AS month, YEAR(post_date) AS year, SUM(total_amount) AS total
            FROM ".BIZUNO_DB_PREFIX."journal_main WHERE contact_id_b=$rID and journal_id=$debJID AND post_date>='{$dates['ThisYear']}-{$dates['ThisMonth']}-01'
            GROUP BY year, month LIMIT $limit";
        msgDebug("\nSQL = $sql");
        if (!$stmt = dbGetResult($sql)) { return msgAdd(lang('err_bad_sql')); }
        $debits= $stmt->fetchAll(\PDO::FETCH_ASSOC);
        // Credit memos
        $credJID= $type=='v' ? 7 : 13;
        msgDebug("\nDates = ".print_r($dates, true));
        $sql = "SELECT MONTH(post_date) AS month, YEAR(post_date) AS year, SUM(total_amount) AS total
            FROM ".BIZUNO_DB_PREFIX."journal_main WHERE contact_id_b=$rID and journal_id=$credJID AND post_date>='{$dates['ThisYear']}-{$dates['ThisMonth']}-01'
            GROUP BY year, month LIMIT $limit";
        msgDebug("\nSQL = $sql");
        if (!$stmt = dbGetResult($sql)) { return msgAdd(lang('err_bad_sql')); }
        $credits= $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $precision = getModuleCache('phreebooks', 'currency', 'iso')[getDefaultCurrency()]['dec_len'];
        $struc[] = [lang('date'), lang('total')];
        for ($i = 0; $i < $limit; $i++) { // since we have 12 months to work with we need 12 array entries
            $struc[$dates['ThisYear'].$dates['ThisMonth']] = [date('M Y', strtotime($dates['ThisYear'].'-'.$dates['ThisMonth'])), 0];
            $dates['ThisMonth']++;
            if ($dates['ThisMonth'] == 13) { $dates['ThisYear']++; $dates['ThisMonth'] = 1;}
        }
        foreach ($debits as $row) {
            if (isset($struc[$row['year'].$row['month']])) { $struc[$row['year'].$row['month']][1] += round($row['total'], $precision); }
        }
        foreach ($credits as $row) {
            if (isset($struc[$row['year'].$row['month']])) { $struc[$row['year'].$row['month']][1] -= round($row['total'], $precision); }
        }
        return $struc;
    }

    /**
     *
     * @global type $io
     */
    public function chartSalesGo()
    {
        global $io;
        $rID   = clean('rID', 'integer','get');
        $type  = clean('type','char',   'get');
        $title = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'primary_name', "id=$rID");
        $struc = $this->chartSalesData($rID, $type, getModuleCache('phreebooks', 'fy', 'period') - 1); // last 4 years
        $output= [];
        foreach ($struc as $row) { $output[] = implode(",", $row); }
        $io->download('data', implode("\n", $output), "Contact-Sales-$title.csv");
    }

    /**
     * Generates a chart with yearly purchase volume and cost
     * @param array $layout - current working structure
     * @return modified $layout
     */
    public function chartHistPurch(&$layout=[])
    {
        $rID   = clean('rID', 'integer', 'get');
        if (!$rID) { return msgAdd(lang('err_bad_id')); }
        $name  = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'primary_name', "id=$rID");
        $struc = $this->chartHistData($rID, 6);
        $title = sprintf("%s history for: $name", lang('purchase'));
        $layout= array_merge_recursive($layout, ['type'=>'divHTML',
            'divs'=>['divBOF'=>['order'=>1, 'type'=>'html', 'html'=>"<div>"], 'divEOF'=>['order'=>99, 'type'=>'html', 'html'=>"</div>"]]]);
        googleLine1($layout, 'contHistPurch', ['title'=>$title, 'data'=>array_values($struc)]);
    }
    /**
     * Generates a chart with yearly purchase volume and cost
     * @param array $layout - current working structure
     * @return modified $layout
     */
    public function chartHistSales(&$layout=[])
    {
        $rID   = clean('rID', 'integer', 'get');
        if (!$rID) { return msgAdd(lang('err_bad_id')); }
        $name  = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'primary_name', "id=$rID");
        $struc = $this->chartHistData($rID, 12);
        $title = sprintf("%s history for: $name", lang('sales'));
        $layout= array_merge_recursive($layout, ['type'=>'divHTML',
            'divs'=>['divBOF'=>['order'=>1, 'type'=>'html', 'html'=>"<div>"], 'divEOF'=>['order'=>99, 'type'=>'html', 'html'=>"</div>"]]]);
        googleLine1($layout, 'contHistSales', ['title'=>$title, 'data'=>array_values($struc)]);
    }

    private function chartHistData($rID, $jID=12)
    {
        $output= [];
        $frstYr= biz_date('Y');
        $jIDs  = $jID==6 ? '6, 7' : '12, 13';
        $key   = $jID==6 ? 'contact_purchases' : 'contact_sales';
        $meta  = getMetaContact($rID, $key);
        msgDebug("\nRead meta for contact rID = $rID and meta = ".print_r($meta, true));
        if (!empty($meta)) { 
            foreach ($meta as $date => $values) {
                $year = substr($date, 0, 4);
                $frstYr = min($year, $frstYr);
                if (!isset($output['Y'.$year])) { $output['Y'.$year] = ['year'=>(string)$year, 'total'=>0]; }
                $output['Y'.$year]['total']+= $values['total']; 
            }
        }
        // Now get all of the journal data
        $sql = "SELECT YEAR(post_date) AS year, SUM(total_amount) AS total FROM ".BIZUNO_DB_PREFIX."journal_main 
            WHERE contact_id_b=$rID AND journal_id IN ($jIDs) GROUP BY year";
        msgDebug("\nSQL = $sql");
        if (!$stmt = dbGetResult($sql)) { return msgAdd(lang('err_bad_sql')); }
        $result= $stmt->fetchAll(\PDO::FETCH_ASSOC);
        msgDebug("\nresult = ".print_r($result, true));
        $precision = getModuleCache('phreebooks', 'currency', 'iso')[getDefaultCurrency()]['dec_len'];
        if (!empty($result)) { 
            foreach ($result as $values) {
                $year  = $values['year'];
                $frstYr= min($year, $frstYr);
                if (!isset($output['Y'.$year])) { $output['Y'.$year] = ['year'=>(string)$year, 'total'=>0]; }
                $output['Y'.$year]['total']+= round($values['total'], $precision); 
            }
        }
        ksort($output); // sort by year
        $struc = [[lang('year'), lang('total')]];
        for ($i = $frstYr; $i <= biz_date('Y'); $i++) { // now make sure there is a value for every year
            if (!isset($output['Y'.$i])) { $struc[] = [$i, 0]; }
            else                         { $struc[] = [$output['Y'.$i]['year'], $output['Y'.$i]['total']]; }
        }
        msgDebug("\nReturing with yearly data = ".print_r($struc, true));
        return array_values($struc); // lose the indexes
    }
}
