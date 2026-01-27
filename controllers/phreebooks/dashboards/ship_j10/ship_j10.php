<?php
/*
 * PhreeBooks dashboard - Reminder for Customer Sales Orders that are due to ship today
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
 * @filesource /controllers/phreebooks/dashboards/ship_j10/ship_j10.php
 */

namespace bizuno;

class ship_j10
{
    public  $moduleID = 'phreebooks';
    public  $methodDir= 'dashboards';
    public  $code     = 'ship_j10';
    public  $secID    = 'j10_mgr';
    public  $category = 'customers';
    public  $struc;
    private $choices;
    private $today;
    public  $lang     = ['title'=>'Sales Orders Due to Ship',
        'description' => 'Lists open customer Sales Orders that are due to ship today (or past due). Links to review the invoice are also provided. Settings are available for enhanced security and control.',
        'email_subject' => 'Open Sales Orders due to ship %s',
        'email_body' => 'The following sales orders are due to ship today:<br /><br />%s<br />The dates from this list can be changed in the Delivery Dates action in the Sale Manager for each individual Sales Order reference.'];
    private $journalID= 10; // length to trim primary_name to fit in frame
    private $sendEmail= false;
    private $emailList= [];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->today  = biz_date();
        $this->choices= getModuleCache('contacts','statuses');
        $this->fieldStructure();
    }

    /**
     * Sets the page fields with their structure
     * @return array - page structure
     */
    private function fieldStructure()
    {
        $roles = viewRoleDropdown();
        array_unshift($roles, ['id'=>'-1', 'text'=>lang('all')]);
        $order = [['id'=>'asc','text'=>lang('decreasing')],['id'=>'desc','text'=>lang('decreasing')]];
        $this->struc = [
            // Admin fields
            'users'   => ['order'=>10,'label'=>lang('users'),        'clean'=>'array',   'attr'=>['type'=>'users',   'value'=>[0],],  'admin'  =>true],
            'roles'   => ['order'=>20,'label'=>lang('groups'),       'clean'=>'array',   'attr'=>['type'=>'roles',   'value'=>[-1]],  'admin'  =>true],
            'reps'    => ['order'=>30,'label'=>lang('just_reps'),    'clean'=>'boolean', 'attr'=>['type'=>'selNoYes','value'=>0],     'admin'  =>true],
            // User fields
            'store_id'=> ['order'=>40,'label'=>lang('store_id'),     'clean'=>'integer', 'attr'=>['type'=>'select',  'value'=>false], 'values' =>viewStores()],
            'num_rows'=> ['order'=>50,'label'=>lang('limit_results'),'clean'=>'integer', 'attr'=>['type'=>'spinner', 'value'=>5],     'options'=>['min'=>0,'max'=>50,'width'=>100]],
//          'selRep'  => ['order'=>60,'label'=>lang('rep_id_c'),     'clean'=>'boolean', 'attr'=>['type'=>'select',  'value'=>1],     'values' =>$roles],
            'trim'    => ['order'=>70,'label'=>lang('truncate_fit'), 'clean'=>'integer', 'attr'=>['type'=>'spinner', 'value'=>20],'options'=>['min'=>10,'max'=>80,'width'=>100]],
            'order'   => ['order'=>80,'label'=>lang('sort_order'),   'clean'=>'db_field','attr'=>['type'=>'select',  'value'=>'desc'],'values' =>$order]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }

    /**
     * Generates the structure for the dashboard view
     * @global object $currencies - Sets the currency values for proper display
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function render($opts=[])
    {
        global $currencies;
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/functions.php', 'getInvoiceInfo', 'function');
        $filter= "m.journal_id=$this->journalID AND m.closed='0' AND i.gl_type='itm' AND i.date_1<='$this->today'";
        if (!empty(getUserCache('profile', 'restrict_store')) && sizeof(getModuleCache('bizuno', 'stores')) > 1) {
            $filter .= " AND store_id=".getUserCache('profile', 'store_id', false, -1);
        } elseif ($opts['store_id'] > -1) {
            $filter .= " AND store_id='{$opts['store_id']}'";
        }
        if (!empty(getUserCache('profile', 'restrict_store'))) { $filter.= " AND store_id=".getUserCache('profile', 'store_id'); }
        $order = "ORDER BY " . ($opts['order']=='desc' ? 'm.post_date DESC, m.invoice_num DESC' : 'm.post_date, m.invoice_num');
        $sql   = "SELECT m.id, m.journal_id, m.post_date, m.store_id, m.contact_id_b, m.primary_name_b, m.invoice_num, m.total_amount, m.currency, m.currency_rate, i.id AS iID, i.qty
            FROM ".BIZUNO_DB_PREFIX."journal_main m JOIN ".BIZUNO_DB_PREFIX."journal_item i ON m.id=i.ref_id WHERE $filter $order";
        $stmt  = dbGetResult($sql);
        $result= $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        $rID   = $rowCnt = 0;
        $output= [];
        foreach ($result as $row) {
            // Check for already shipped
            $lineTotal = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'SUM(qty)', "item_ref_id={$row['iID']}", false);
            if ($lineTotal >= $row['qty']) { continue; } // filled
            if ($row['id'] == $rID) { continue; } // prevent dups
            $output[] = $row;
            $rID = $row['id'];
        }
        if (empty($output)) { $rows[] = "<span>".lang('no_results')."</span>"; }
        else {
            // get the notified list
            $settings = getModuleCache($this->moduleID, $this->methodDir, $this->code, []);
            $notified = !empty($settings['settings']['notified']) ? $settings['settings']['notified'] : [];
            if (empty($notified['date']) || $notified['date'] <> $this->today) { $notified = ['date'=>$this->today, 'rIDs'=>[]]; }
            msgDebug("\nNotified = ".print_r($notified, true));
            foreach ($output as $entry) {
                msgDebug("\nWorking on entry = ".print_r($entry, true));
                $currencies->iso  = $entry['currency'];
                $currencies->rate = $entry['currency_rate'];
                $this->notifyCheck($notified, $entry);
                $store  = sizeof(getModuleCache('bizuno', 'stores')) > 1 ? "[".viewFormat($entry['store_id'], 'storeID')."]" : '-';
                $left   = biz_date('m/d', strtotime($entry['post_date']))." $store ".$this->rowStyler($entry['contact_id_b'], viewText($entry['primary_name_b'], $opts['trim']));
                $right  = '';
                $action = html5('', ['events'=>['onClick'=>"winHref(bizunoHome+'?bizRt=phreebooks/main/manager&jID=$this->journalID&rID={$entry['id']}');"],'attr'=>['type'=>'button','value'=>"#{$entry['invoice_num']}"]]);
                $rows[] = viewDashLink($left, $right, $action);
                $rowCnt++;
                if (!empty($opts['num_rows']) && $rowCnt>=$opts['num_rows']) { break; }
            }
            if ($this->sendEmail && !empty($this->emailList)) { $this->notifyEmail(); }
            $currencies->iso  = getDefaultCurrency();
            $currencies->rate = 1;
            $settings['settings']['notified'] = $notified;
            setModuleCache($this->moduleID, $this->methodDir, $this->code, $settings);
        }
        $legend = getModuleCache('bizuno','settings','general','hide_filters',0) ? ucfirst(lang('filter')).": ".ucfirst(lang('sort'))." ".strtoupper($opts['order']).(!empty($opts['num_rows']) ? " ({$opts['num_rows']});" : '') : '';
        return ['lists'=>$rows, 'legend'=>$legend];
    }

    /**
     *
     * @param array $notified
     * @param type $entry
     * @return type
     */
    private function notifyCheck(&$notified, $entry)
    {
        if ($notified['date'] == $this->today && in_array($entry['id'], $notified['rIDs'])) { return; } // notified already
        msgDebug("\nAdding record {$entry['id']} un-notified invoice # {$entry['invoice_num']} with customer: {$entry['primary_name_b']}");
        $notified['rIDs'][]= $entry['id'];
        $this->emailList[] = ['invNum'=>$entry['invoice_num'], 'name'=>$entry['primary_name_b']];
        $this->sendEmail   = true;
    }
    private function notifyEmail()
    {
        $html = '';
        msgDebug("\nEmail list before email: ".print_r($this->emailList, true));
        foreach ($this->emailList as $row) { $html .= "SO #{$row['invNum']}: {$row['name']}<br />"; }
        $fromEmail = 'support@phreesoft.com';
        $toEmail   = getModuleCache('bizuno', 'settings', 'company', 'email');
        $toName    = getModuleCache('bizuno', 'settings', 'company', 'contact');
        $msgSubject= sprintf($this->lang['email_subject'], viewFormat($this->today, 'date'));
        $msgBody   = sprintf($this->lang['email_body'], $html);
        $mail      = new bizunoMailer($toEmail, $toName, $msgSubject, $msgBody, $fromEmail);
        $mail->sendMail();
        msgAdd($msgBody);
        msgLog($msgSubject);
    }
    private function rowStyler($cID, $cText)
    {
        msgDebug("\nEntering rowStyler with cID = $cID and cText = $cText");
        $cStatus= dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'inactive', "id=$cID");
        foreach ($this->choices as $status) {
            if (empty($status['color'])) { continue; }
            if ($status['id']==$cStatus) { return '<span class="row-'.$status['color'].'">'.$cText.'</span>'; }
        }
        return $cText;
    }
}
