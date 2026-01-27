<?php
/*
 * PhreeBooks dashboard - Currency Converter using XE
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
 * @version    7.x Last Update: 2025-04-24
 * @filesource /controllers/phreebooks/dashboards/currency_xe/currency_xe.php
 */

namespace bizuno;

class currency_xe
{
    public $moduleID  = 'phreebooks';
    public $methodDir = 'dashboards';
    public $code      = 'currency_xe';
    public $secID     = 'j2_mgr';
    public $category  = 'general_ledger';
    public $noSettings= true;
    public  $struc;
    public $lang      = ['title' => 'XE Currency Exchange Rates',
        'description' => 'Lists an exchange rate converter using the xe.com web site as source.',
        'update_desc' => 'Update your Bizuno stored exchange rates:',
        'no_multi_langs' => 'Your business is not set up with multi-currency! No updates are available.'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->fieldStructure();
    }

    /**
     * Sets the page fields with their structure
     * @return array - page structure
     */
    private function fieldStructure()
    {
        $this->struc = [
            // Admin fields
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
        global $html5;
        $defISO= getDefaultCurrency();
        $ISOs  = getModuleCache('phreebooks', 'currency', 'iso', false, []);
        $cVals = [];
        foreach ($ISOs as $code => $iso) {
            if ($defISO == $code) { continue; }
            $cVals[$code] = $iso['title'];
        }
        $lang  = substr(getUserCache('profile', 'language', false, 'en_US'), 0, 2);
        $size  = 'compact'; // choices are 'compact' (320 x 300), other option is 'normal' (560 x 310)
        $html  = '<div><div id="xecurrencywidget"></div>
<script>var xeCurrencyWidget = {"domain":"www.bizuno.com","language":"'.$lang.'","size":"'.$size.'"};</script>
<script src="https://www.xe.com/syndication/currencyconverterwidget.js"></script>
</div>';
        if (empty($cVals)) { return ['html'=>'<br />'.$this->lang['no_multi_langs']]; }
        $data = [
            'divs'  => ['body'=>['attr'=>['id'=>'oanda_ecc'],'type'=>'divs','divs'=>[
                'oanda'  =>['order'=>10,'type'=>'html',  'html'=>$html],
                'desc'   =>['order'=>20,'type'=>'html',  'html'=>"<br />{$this->lang['update_desc']}<br />"],
                'formBOF'=>['order'=>40,'type'=>'form',  'key' =>'xeForm'],
                'body'   =>['order'=>50,'type'=>'fields','keys'=>['defCur','excRate','excISO','btnUpd']],
                'formEOF'=>['order'=>90,'type'=>'html',  'html'=>"</form>"]]]],
            'fields'=> [
                'defCur' => ['order'=>10,'break'=>false,'html'=>"1 $defISO = ",'attr'=>['type'=>'raw']],
                'excRate'=> ['order'=>20,'break'=>false,'options'=>['width'=>100],'attr'=>['value'=>'']],
                'excISO' => ['order'=>30,'values'=>viewKeyDropdown($cVals), 'attr'=>['type'=>'select']],
                'btnUpd' => ['order'=>40,'attr'=>['type'=>'button','value'=>lang('update')],'events'=>['onClick'=>"jqBiz('#xeForm').submit();"]]],
            'forms' => ['xeForm'=>['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=phreebooks/currency/setExcRate"]]],
            'jsReady'=>[$this->code=>"ajaxForm('xeForm');"]];
        return ['html'=>$html5->buildDivs($data)];
    }
}
