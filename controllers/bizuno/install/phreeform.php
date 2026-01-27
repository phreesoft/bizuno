<?php
/*
 * Phreeform tree structure for Bizuno
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
 * @version    7.x Last Update: 2025-05-27
 * @filesource /controllers/bizuno/install/phreeform.php
 */

namespace bizuno;

$phreeFormStructure = [
    'misc' => ['title'=>'misc',      'folders'=>[
        'misc:rpt' => ['type'=>'dir', 'title'=>'reports'],
        'misc:misc'=> ['type'=>'dir', 'title'=>'forms']]],
    'bnk'  => ['title'=>'banking',   'folders'=>[
        'bnk:rpt'  => ['type'=>'dir', 'title'=>'reports'],
        'bnk:j18'  => ['type'=>'dir', 'title'=>'bank_deposit'],
        'bnk:j20'  => ['type'=>'dir', 'title'=>'bank_check']]],
    'cust' => ['title'=>'customers', 'folders'=>[
        'cust:rpt' => ['type'=>'dir', 'title'=>'reports'],
        'cust:j9'  => ['type'=>'dir', 'title'=>'journal_id_9'],
        'cust:j10' => ['type'=>'dir', 'title'=>'journal_id_10'],
        'cust:j12' => ['type'=>'dir', 'title'=>'journal_id_12'],
        'cust:j13' => ['type'=>'dir', 'title'=>'journal_id_13'],
        'cust:j18' => ['type'=>'dir', 'title'=>'sales_receipt'],
        'cust:j19' => ['type'=>'dir', 'title'=>'pos_receipt'],
        'cust:lblc'=> ['type'=>'dir', 'title'=>'label'],
        'cust:ltr' => ['type'=>'dir', 'title'=>'letter'],
        'cust:stmt'=> ['type'=>'dir', 'title'=>'statement'],
        'cust:rtn' => ['type'=>'dir', 'title'=>'returns']]],
    'gl'   => ['title'=>'general_ledger', 'folders'=>[
        'gl:rpt'   => ['type'=>'dir', 'title'=>'reports', 'type'=>'dir']]],
    'hr'   => ['title'=>'employees', 'folders'=>[
        'hr:rpt'   => ['type'=>'dir', 'title'=>'reports']]],
    'inv'  => ['title'=>'inventory', 'folders'=>[
        'inv:j14'  => ['type'=>'dir', 'title'=>'journal_id_14'],
        'inv:j16'  => ['type'=>'dir', 'title'=>'journal_id_16'],
        'inv:rpt'  => ['type'=>'dir', 'title'=>'reports'],
        'inv:frm'  => ['type'=>'dir', 'title'=>'forms'],
        'xfa:rpt'  => ['type'=>'dir', 'title'=>'reports']]],
    'mfg'  => ['title'=>'production', 'folders'=>[
        'mfg:rpt'  => ['type'=>'dir', 'title'=>'reports'],
        'mfg:wo'   => ['type'=>'dir', 'title'=>'forms']]],
    'ship' => ['title'=>'shipping',  'folders'=>[
        'ship:rpt' => ['type'=>'dir', 'title'=>'reports']]],
    'vend' => ['title'=>'vendors',   'folders'=>[
        'vend:rpt' => ['type'=>'dir', 'title'=>'reports'],
        'vend:j3'  => ['type'=>'dir', 'title'=>'journal_id_3'],
        'vend:j4'  => ['type'=>'dir', 'title'=>'journal_id_4'],
        'vend:j6'  => ['type'=>'dir', 'title'=>'journal_id_6'],
        'vend:j7'  => ['type'=>'dir', 'title'=>'journal_id_7'],
        'vend:lblv'=> ['type'=>'dir', 'title'=>'label'],
        'vend:stmt'=> ['type'=>'dir', 'title'=>'statement']]]];
