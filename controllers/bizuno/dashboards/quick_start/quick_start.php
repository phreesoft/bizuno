<?php
/*
 * Bizuno dsahboard - Quick start with list of suggestions on getting going form new installs
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
 * @version    7.x Last Update: 2025-04-24
 * @filesource /controllers/bizuno/dashboards/quick_start/quick_start.php
 */

namespace bizuno;

class quick_start
{
    public $moduleID   = 'bizuno';
    public $methodDir  = 'dashboards';
    public $code       = 'quick_start';
    public $category   = 'general';
    public $noSettings = true;
    public $noCollapse = true;
    public  $struc;
    public $lang = ['title'=>'Welcome to Bizuno!',
    'description'=> 'Displays the Bizuno welcome message and suggestions of what to do to get started.',
    'msg_welcome'=> '<p>Thank you for your interest in Bizuno. PhreeSoft believes that a good business accounting/ERP application must revolve around your business and make you life easier. To get the most out of Bizuno, spend a few minutes before you start with your Bizuno business settings.</p>
<p>Here are a few ideas to help you get started. NOTE: Your chart of accounts and default currency need to be set BEFORE any journal entries are made. You may also want to spend a few minutes reading the <a href="https://www.phreesoft.com" target="_blank">Working With Bizuno</a> guide in the PhreeSoft Biz School. The Biz School is PhreeSoft\'s online resource for all things Bizuno.</p>
<ul>
    <li>Set up your <a href="'.BIZUNO_URL_PORTAL.'&bizRt=bizuno/admin/adminHome&tab=3" target="_blank">business</a> information and connect with your PhreeSoft account.</li>
    <li>Set up your <a href="'.BIZUNO_URL_PORTAL.'&bizRt=contacts/main/manager&type=c" target="_blank">customers</a> or <a href="'.BIZUNO_URL_PORTAL.'&bizRt=contacts/main/manager&type=v" target="_blank">vendors</a>.</li>
    <li>Configure your <a href="'.BIZUNO_URL_PORTAL.'&bizRt=phreebooks/admin/adminHome&tab=3" target="_blank">sales tax</a> or <a href="'.BIZUNO_URL_PORTAL.'&bizRt=phreebooks/admin/adminHome&tab=4" target="_blank">purchase tax</a> rates.</li>
    <li>Have inventory? You can add it <a href="'.BIZUNO_URL_PORTAL.'&bizRt=inventory/main/manager" target="_blank">here</a> OR visit the Biz School for how to import from a .csv file.</li>
</ul>
<p>We hope you enjoy the application and remember that PhreeSoft support is always there to help.</p>'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
    }

    public function render(&$layout=[])
    {
        $html = $this->lang['msg_welcome'];
        return ['html'=>$html];
    }
}
