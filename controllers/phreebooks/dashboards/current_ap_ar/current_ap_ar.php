<?php
/*
 * PhreeBooks dashboard - Curernt accounts payable totals and receivables totals
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
 * @version    7.x Last Update: 2026-03-01
 * @filesource /controllers/phreebooks/dashboards/current_ap_ar/current_ap_ar.php
 */

namespace bizuno;

class current_ap_ar
{
    public  $moduleID  = 'phreebooks';
    public  $methodDir = 'dashboards';
    public  $code      = 'current_ap_ar';
    public  $secID     = 'j2_mgr';
    public  $category  = 'general_ledger';
    public  $noSettings= true;
    public  $struc;
    public  $lang      = ['title' => 'Current A/R & A/P',
        'description'  => 'Lists current Accounts Receivables (A/R) and Accounts Payables (A/P) balances.'];

    public function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->fieldStructure();
    }

    private function fieldStructure()
    {
        $this->struc = [ // Admin fields
            'users' => ['order'=>10,'label'=>lang('users'), 'clean'=>'array', 'attr'=>['type'=>'users', 'value'=>[0],], 'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),'clean'=>'array', 'attr'=>['type'=>'roles', 'value'=>[-1]], 'admin'=>true]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }

    public function render()
    {
        $period = getModuleCache('phreebooks', 'fy', 'period');
        $html  = '<div><div id="'.$this->code.'_attr" style="display:none"><div>'.lang('msg_no_settings').'</div></div>';
        // Build content box
        $html .= '<table width="100%" border = "0">';
        // Accounts Receivables
        $html .= "<tr><td colspan=\"2\"><b>".lang('gl_acct_type_20')."</b></td></tr>";
        $html .= $this->add_income_stmt_data(20, $period, $negate=true);
        // Accounts Payables
        $html .= "<tr><td colspan=\"2\"><b>".lang('gl_acct_type_2')."</b></td></tr>";
        $html .= $this->add_income_stmt_data(2, $period, $negate = false);
        $html .= '</table>';
        return ['html'=>$html];
     }

    private function add_income_stmt_data($type, $period, $negate=false)
    {
        $rows = dbGetMulti(BIZUNO_DB_PREFIX."journal_history", "period=$period AND gl_type=$type", "gl_account");
        $total = 0;
        $html  = '';
        foreach ($rows as $row) {
            $title = getModuleCache('phreebooks', 'chart', $row['gl_account'], 'title');
            $balance = $row['beginning_balance'] + $row['debit_amount'] - $row['credit_amount'];
            if ($negate) { $balance = -$balance; }
            $total += $balance;
            if ($balance) { $html .= "<tr><td>{$row['gl_account']}</td><td>$title</td><td style=\"text-align:right\">".viewFormat($balance, 'currency')."</td></tr>"; }
        }
        $html .= "<tr><td colspan=\"2\" style=\"text-align:right\"><b>".lang('total')."</b></td><td style=\"text-align:right\"><b>".viewFormat($total, 'currency')."</b></td></tr>";
        return $html;
    }
}
