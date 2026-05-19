<?php
/*
 * Phreebooks dashboard - Mini Financial statement
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
 * @version    7.x Last Update: 2026-03-15
 * @filesource /controllers/phreebooks/dashboards/mini_financial/mini_financial.php
 */

namespace bizuno;

class mini_financial
{
    public $moduleID = 'phreebooks';
    public $methodDir= 'dashboards';
    public $code     = 'mini_financial';
    public $secID    = 'j2_mgr';
    public $category = 'general_ledger';
    public $noSettings= true;
    public  $struc;
    public $lang     = ['title' => 'My Financial Summary',
        'description' => 'Lists a shortened version of your financial situation.',
        'assets' => 'Total Assets',
        'current_assets' => 'Total Current Assets',
        'capital' => 'Capital',
        'cost_of_sales' => 'Cost of Sales',
        'expenses' => 'Expenses',
        'gross_profit' => 'Gross Profit',
        'net_income' => 'Net Income',
        'total_liab' => 'Total Liabilities',
        'total_income' => 'Total Income',
        'tot_liab_capital' => 'Total Liabilities & Capital'];
    private $bal_tot_2     = 0;
    private $bal_tot_3     = 0;
    private $bal_sheet_data= [];
    private $total_3       = 0;
    private $ytd_net_income= 0;

    function __construct()
    {
        $this->fieldStructure();
    }

    /**
     * Sets the page fields with their structure
     * @return array - page structure
     */
    private function fieldStructure()
    {
        $this->struc = [ // Admin fields
            'users' => ['order'=>10,'label'=>lang('users'), 'clean'=>'array', 'attr'=>['type'=>'users', 'value'=>[0],], 'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),'clean'=>'array', 'attr'=>['type'=>'roles', 'value'=>[-1]], 'admin'=>true]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }

    /**
     * Generates the structure for the dashboard view
     * @global object $currencies - Sets the currency values for proper display
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function render()
    {
        $html  = '<div>';
        $html .= '  <div id="'.$this->code.'_attr" style="display:none">';
        $html .= '      <div>'.lang('msg_no_settings').'</div>';
        $html .= '  </div>';
        // Build content box
        $html .= '<table width="100%" border = "0">';
        $period = getModuleCache('phreebooks', 'fy', 'period');
        // build assets
        $cash = [0, 2, 4 ,6];
        $html .= $this->add_bal_sheet_data($cash, false, $period);
        $html .= '<tr><td>&nbsp;&nbsp;' . htmlspecialchars($this->lang['current_assets']) . '</td>';
        $html .= '<td align="right">' . $this->ProcessData($this->bal_tot_2) . '</td></tr>';
        $this->bal_tot_2 = 0;
        $assets = [8, 10, 12];
        $this->add_bal_sheet_data($assets, false, $period);
        $html .= '<tr><td>&nbsp;&nbsp;' . htmlspecialchars(lang('gl_acct_type_8')) . '</td>';
        $html .= '<td align="right">' . $this->ProcessData($this->bal_tot_2) . '</td></tr>';
        $html .= '<tr><td>' . htmlspecialchars($this->lang['assets']) . '</td>';
        $html .= '<td align="right">' . $this->ProcessData($this->bal_tot_3) . '</td></tr>';
        $html .= '<tr><td colspan="2">&nbsp;</td></tr>';
        // build liabilities
        $this->bal_tot_2 = 0;
        $this->bal_tot_3 = 0;
        $payables = [20, 22];
        $this->add_bal_sheet_data($payables, true, $period);
        $html .= '<tr><td>&nbsp;&nbsp;&nbsp;&nbsp;' . htmlspecialchars(lang('gl_acct_type_22')) . '</td>';
        $html .= '<td align="right">' . $this->ProcessData($this->bal_tot_2) . '</td></tr>';
        $this->bal_tot_2 = 0;
        $liabilities = [24];
        $this->add_bal_sheet_data($liabilities, true, $period);
        $html .= '<tr><td>&nbsp;&nbsp;&nbsp;&nbsp;' . htmlspecialchars(lang('gl_acct_type_24')) . '</td>';
        $html .= '<td align="right">&nbsp;&nbsp;' . $this->ProcessData($this->bal_tot_2) . '</td></tr>';
        $html .= '<tr><td>&nbsp;&nbsp;' . htmlspecialchars($this->lang['total_liab']) . '</td>';
        $html .= '<td align="right">' . $this->ProcessData($this->bal_tot_3) . '</td></tr>';
        // build capital
        $this->bal_tot_2 = 0;
        $capital = [40, 42, 44];
        $this->add_bal_sheet_data($capital, true, $period);
        $html .= $this->load_report_data($period); // retrieve and add net income value
        $this->bal_tot_2 += $this->ytd_net_income;
        $this->bal_tot_3 += $this->ytd_net_income;
        $html .= '<tr><td>&nbsp;&nbsp;' . htmlspecialchars($this->lang['net_income']) . '</td>';
        $html .= '<td align="right">' . $this->ProcessData($this->ytd_net_income) . '</td></tr>';
        $html .= '<tr><td>&nbsp;&nbsp;' . htmlspecialchars($this->lang['capital']) . '</td>';
        $html .= '<td align="right">' . $this->ProcessData($this->bal_tot_2) . '</td></tr>';
        $html .= '<tr><td>' . htmlspecialchars($this->lang['tot_liab_capital']) . '</td>';
        $html .= '<td align="right">' . $this->ProcessData($this->bal_tot_3) . '</td></tr>';
        $html .= '</table>';
        return ['html'=>$html];
    }

    function add_bal_sheet_data($the_list, $negate, $period)
    {
        $contents = '';
        foreach($the_list as $account_type) {
            $sql = "SELECT beginning_balance + debit_amount - credit_amount AS balance
                    FROM ".BIZUNO_DB_PREFIX."journal_history WHERE period = $period AND gl_type=$account_type";
            if (!$stmt = dbGetResult($sql)) { return; }
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $total_1 = 0;
            foreach ($result as $row) {
                if ($negate) { $total_1 -= $row['balance']; } else { $total_1 += $row['balance']; }
            }
            $this->bal_tot_2 += $total_1;
            $contents .= '<tr><td>&nbsp;&nbsp;&nbsp;&nbsp;'.lang('gl_acct_type_'.$account_type).'</td>';
            $contents .= '<td align="right">'.$this->ProcessData($total_1).'</td></tr>';
        }
        $this->bal_tot_3 += $this->bal_tot_2;
        return $contents;
    }

    function ProcessData($strData)
    {
        return viewFormat($strData, 'currency');
    }

    function load_report_data($period)
    {
        $contents = '';
        // find the period range within the fiscal year from the first period to current requested period
        $fiscal_year  = dbGetValue(BIZUNO_DB_PREFIX."journal_periods", "fiscal_year", "period=$period");
        $first_period = dbGetValue(BIZUNO_DB_PREFIX."journal_periods", "period", "fiscal_year=$fiscal_year ORDER BY period");
        // build revenues
        $cur_year  = $this->add_income_stmt_data(30, $first_period, $period, $negate=true); // Income account_type
        $ytd_temp  = $this->ProcessData($this->total_3);
        $this->ytd_net_income = $this->total_3;
        $contents .= '<tr><td>&nbsp;&nbsp;&nbsp;&nbsp;'.$this->lang['total_income'].'</td>';
        $contents .= '<td align="right">'.$this->ProcessData($this->total_3).'</td></tr>';
        // less COGS
        $cur_year  = $this->add_income_stmt_data(32, $first_period, $period, $negate = false); // Cost of Sales account_type
        $ytd_temp  = $this->ProcessData($this->total_3);
        $this->ytd_net_income -= $this->total_3;
        $contents .= '<tr><td>&nbsp;&nbsp;&nbsp;&nbsp;'.$this->lang['cost_of_sales'].'</td>';
        $contents .= '<td align="right">('.$this->ProcessData($this->total_3).')</td></tr>';
        $contents .= '<tr><td>&nbsp;&nbsp;&nbsp;&nbsp;'.$this->lang['gross_profit'].'</td>';
        $contents .= '<td align="right">'.$this->ProcessData($this->ytd_net_income).'</td></tr>';
        // less expenses
        $cur_year  = $this->add_income_stmt_data(34, $first_period, $period, $negate = false); // Expenses account_type
        $this->ytd_net_income -= $this->total_3;
        $contents .= '<tr><td>&nbsp;&nbsp;&nbsp;&nbsp;'.$this->lang['expenses'].'</td>';
        $contents .= '<td align="right">('.$this->ProcessData($this->total_3).')</td></tr>';
        $ytd_temp  = $this->ProcessData($this->ytd_net_income);
        return $contents;
    }

    function add_income_stmt_data($type, $first_period, $period, $negate = false)
    {
        $account_array = [];
        $sql = "SELECT id, gl_account, debit_amount - credit_amount AS balance
            FROM ".BIZUNO_DB_PREFIX."journal_history WHERE period=$period AND gl_type=$type ORDER BY gl_account";
        if (!$stmt = dbGetResult($sql)) { return; }
        $cur_period = $stmt->fetch(\PDO::FETCH_ASSOC);

        $sql = "SELECT (SUM(debit_amount) - SUM(credit_amount)) AS balance
            FROM ".BIZUNO_DB_PREFIX."journal_history WHERE period>=$first_period AND period<=$period AND gl_type=$type GROUP BY gl_account ORDER BY gl_account";
        if (!$stmt = dbGetResult($sql)) { return; }
        $ytd_period = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $sql = "SELECT beginning_balance FROM ".BIZUNO_DB_PREFIX."journal_history WHERE period=$first_period AND gl_type=$type GROUP BY gl_account ORDER BY gl_account";
        if (!$stmt = dbGetResult($sql)) {return; }
        $beg_balance  = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $ytd_total_1 = 0;
        foreach ($ytd_period as $key => $row) {
            if ($negate) {
                $ytd_total_1 += -$beg_balance[$key]['beginning_balance'] - $row['balance'];
                $ytd_temp     = $this->ProcessData(-$beg_balance[$key]['beginning_balance'] - $row['balance']);
            } else {
                $ytd_total_1 += $beg_balance[$key]['beginning_balance'] + $row['balance'];
                $ytd_temp     = $this->ProcessData($beg_balance[$key]['beginning_balance'] + $row['balance']);
            }
            $account_array[$cur_period['id']] = [$cur_period['gl_account'], $cur_temp = 0, $ytd_temp];
        }
        $this->total_3 = $ytd_total_1;
        return $account_array;
    }
}
