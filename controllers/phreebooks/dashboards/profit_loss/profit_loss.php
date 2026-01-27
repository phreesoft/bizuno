<?php
/*
 * PhreeBooks dashboard - Profit/Loss Summary
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
 * @version    7.x Last Update: 2025-11-30
 * @filesource /controllers/phreebooks/dashboards/profit_loss/profit_loss.php
 *
 */

namespace bizuno;

class profit_loss
{
    public $moduleID  = 'phreebooks';
    public $methodDir = 'dashboards';
    public $code      = 'profit_loss';
    public $secID     = 'j2_mgr';
    public $category  = 'general_ledger';
    public $noSettings= true;
    public  $struc;
    public $lang      = ['title'=>'Profit/Loss Summary',
        'description'=> 'Displays an overview of your business income and expenses for the current month.'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->fieldStructure();
    }
    private function fieldStructure()
    {
        $this->struc = [
            // Admin fields
            'users' => ['order'=>10,'label'=>lang('users'), 'clean'=>'array', 'attr'=>['type'=>'users', 'value'=>[0],], 'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),'clean'=>'array', 'attr'=>['type'=>'roles', 'value'=>[-1]], 'admin'=>true]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }
    public function render()
    {
        $period  = getModuleCache('phreebooks', 'fy', 'period');
        $cData[] = [lang('type'), lang('total')]; // headings
        $sales   = $this->getValue(30, $period, $negate=true);
        $cogs    = $this->getValue(32, $period, false);
        $cData[] = [lang('gl_acct_type_32'), ['v'=>$cogs, 'f'=>viewFormat($cogs, 'currency')]];
        $expenses= $this->getValue(34, $period, false);
        $cData[] = [lang('gl_acct_type_34'), ['v'=>$expenses, 'f'=>viewFormat($expenses, 'currency')]];
        $netInc  = $sales - $cogs - $expenses;
        $cData[] = [lang('net_income'), ['v'=>max(0, $netInc), 'f'=>viewFormat($netInc, 'currency')]]; // Net Income
        return ['type'=>'gChart', 'data'=>$cData];
    }
    function getValue($type, $period, $negate=false)
    {
        $rows = dbGetMulti(BIZUNO_DB_PREFIX.'journal_history', "period=$period AND gl_type=$type", 'gl_account');
        $total= 0;
        foreach ($rows as $row) { $total += $negate ? $row['credit_amount'] - $row['debit_amount'] : $row['debit_amount'] - $row['credit_amount']; }
        if ($total < 0) { $total = 0; }
        return $total;
    }
}
