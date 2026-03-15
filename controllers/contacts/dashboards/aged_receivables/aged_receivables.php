<?php
/*
 * Contacts dashboard - New Customers
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
 * @filesource /controllers/contacts/dashboards/aged_receivables/aged_receivables.php
 */

namespace bizuno;

class aged_receivables
{
    public $moduleID  = 'contacts';
    public $methodDir = 'dashboards';
    public $code      = 'aged_receivables';
    public $secID     = 'j18_mgr';
    public $category  = 'customers';
    public $noSettings= true;
    public  $struc;
    public $lang      = ['title'=>'Aged Receivables',
        'description'=>'Displays aged receivables. Does not include accounts that are current.',
        'aging' => 'Aging',
        'current' => 'Current',
        'late_1_30' => 'Late 1-30',
        'late_31_60' => 'Late 31-60',
        'late_61_90' => 'Late 61-90',
        'late_91_120' => 'Late 91-120',
        'late_over_120' => 'Over 120'];

    function __construct()
    {
        $this->fieldStructure();
    }
    private function fieldStructure()
    {
        $this->struc = [
            // Admin fields
            'users' => ['order'=>10,'label'=>lang('users'),     'clean'=>'array',   'attr'=>['type'=>'users',   'value'=>[0],], 'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),    'clean'=>'array',   'attr'=>['type'=>'roles',   'value'=>[-1]], 'admin'=>true],
            'reps'  => ['order'=>30,'label'=>lang('just_reps'), 'clean'=>'boolean', 'attr'=>['type'=>'selNoYes','value'=>0],    'admin'=>true]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }
    public function render()
    {
        $data  = $this->getTotals();
        $table = [
            [$this->lang['current'],      ['v'=>$data['balance_0'],  'f'=>viewFormat($data['balance_0'],  'currency')]],
            [$this->lang['late_1_30'],    ['v'=>$data['balance_30'], 'f'=>viewFormat($data['balance_30'], 'currency')]],
            [$this->lang['late_31_60'],   ['v'=>$data['balance_60'], 'f'=>viewFormat($data['balance_60'], 'currency')]],
            [$this->lang['late_61_90'],   ['v'=>$data['balance_90'], 'f'=>viewFormat($data['balance_90'], 'currency')]],
            [$this->lang['late_91_120'],  ['v'=>$data['balance_120'],'f'=>viewFormat($data['balance_120'],'currency')]],
            [$this->lang['late_over_120'],['v'=>$data['balance_121'],'f'=>viewFormat($data['balance_121'],'currency')]],
            [lang('total'),        ['v'=>$data['total'],      'f'=>viewFormat($data['total'],      'currency')]]];
        $header= [['title'=>$this->lang['aging'], 'type'=>'string'], ['title'=>lang('amount'), 'type'=>'number']];
        return ['type'=>'gTable', 'header'=>$header, 'data'=>$table, 'callback'=>BIZUNO_URL_AJAX."&bizRt=phreebooks/tools/agingData"];
    }
    public function getTotals()
    {
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/functions.php', 'calculate_aging', 'function');
        $output= ['data'=>[['Customer','Post Date','Ref Number','Current','1-30','31-60','61-90','91-120','Over 120']], 'total'=>0,
            'balance_121'=>0, 'balance_120'=>0, 'balance_91'=>0, 'balance_90'=>0, 'balance_61'=>0, 'balance_60'=>0, 'balance_30'=>0, 'balance_0'=>0];
        $rows  = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', "journal_id IN (12, 13) AND closed='0'", 'primary_name_b', ['DISTINCT contact_id_b'], 0, false);
        foreach ($rows as $row) {
            $aging = calculate_aging($row['contact_id_b']);
            $output['balance_0']  += $aging['balance_0'];
            $output['balance_30'] += $aging['balance_30'];
            $output['balance_60'] += $aging['balance_60'];
            $output['balance_90'] += $aging['balance_90'];
            $output['balance_120']+= $aging['balance_120'];
            $output['balance_61'] += $aging['balance_61'];
            $output['balance_91'] += $aging['balance_91'];
            $output['balance_121']+= $aging['balance_121'];
            $output['total']      += $aging['balance_0'] + $aging['balance_30'] + $aging['balance_60'] + $aging['balance_90'] + $aging['balance_120'] + $aging['balance_121'];
            foreach ($aging['data'] as $entry) { $output['data'][] = $entry; }
        }
        return $output;
    }
}
