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
 * @copyright  2008-2025, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2025-11-29
 * @filesource /controllers/phreebooks/dashboards/cash_register/cash_register.php
 */

namespace bizuno;

class cash_register
{
    public $moduleID  = 'phreebooks';
    public $methodDir = 'dashboards';
    public $code      = 'cash_register';
    public $secID     = 'mgr_c';
    public $category  = 'banking';
    public $noSettings= true;
    public  $struc;
    public $lang      = ['title' => 'Bank Account Balances',
        'description' => 'Lists the current balances in each cash gl account.'];
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
        $total  = 0;
        $cashGL = [];
        $glAccts= getModuleCache('phreebooks', 'chart');
        msgDebug("\nworking with gl accts = ".print_r($glAccts, true));
        foreach ($glAccts as $glAcct => $props) {
            if (!isset($props['type']) || !empty($props['type']) || !empty($props['inactive'])) { continue; } // cash accounts are of type 0, or inactive
            $balance = dbGetGLBalance($glAcct, biz_date());
            $cashGL[]= [$props['id'], $props['title'], ['v'=>$balance,'f'=>viewFormat($balance, 'currency')]];
            $total  += $balance;
        }
        $cashGL[]= [lang('total'), '', ['v'=>$total,'f'=>viewFormat($total, 'currency')]];
        $header  = [['title'=>lang('gl_account'), 'type'=>'string'], ['title'=>lang('account'), 'type'=>'string'], ['title'=>lang('balance'), 'type'=>'number']];
        return ['type'=>'gTable','header'=>$header,'data'=>$cashGL];
    }
}
