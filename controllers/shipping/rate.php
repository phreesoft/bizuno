<?php
/*
 * Shipping Extension - Ship and Rate methods
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
 * @version    7.x Last Update: 2025-07-22
 * @filesource /controllers/shipping/rate.php
 */

namespace bizuno;

bizAutoLoad(dirname(__FILE__).'/common.php', 'shippingCommon');

class shippingRate extends shippingCommon
{
    public $moduleID = 'shipping';
    public $pageID   = 'rate';

    function __construct()
    {
        parent::__construct();
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function rateMain(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $data  = clean('data', 'json', 'get');
        $fields= $this->getRateFields($data);
        msgDebug("\nEntering rateMain with fldGen = ".print_r($fields, true));
        $layout = array_replace_recursive($layout, ['type'=>'divHTML',
            'toolbars' => ['tbShipping'=>['icons'=>[
                'rate'    => ['order'=>20,'label'=>lang('rate_quote'),'icon'=>'quote','events'=>['onClick'=>"jqBiz('#frmEstimate').submit();"]]]]],
            'divs'     => [
                'toolbar' => ['order'=>10,'type'=>'toolbar',  'key'=>'tbShipping'],
                'divRate' => ['order'=>60,'type'=>'accordion','key'=>'accShipRate']],
            'accordion'=> ['accShipRate'=>['divs'=>[
                'divRateSettings'=> ['order'=>30,'label'=>lang('settings'),'type'=>'divs','divs'=>[
                    'formBOF'  => ['order'=>10,'type'=>'form','key'=>'frmEstimate'],
                    'divDetail' => ['order'=>50,'type'=>'divs',    'classes'=>['areaView'],'divs'=>[
                        'shipTo'  => ['order'=>20,'type'=>'panel','key'=>'shipTo',  'classes'=>['block25']],
                        'options' => ['order'=>30,'type'=>'panel','key'=>'options', 'classes'=>['block33']],
                        'details' => ['order'=>40,'type'=>'panel','key'=>'details', 'classes'=>['block33']]]],
                    'formEOF' => ['order'=>90,'type'=>'html','html'=>"</form>"]]],
                'divRateResults' => ['order'=>70,'label'=>lang('rate_quote'),'type'=>'html','html'=>"&nbsp;"]]]],
            'panels' => [
                'shipTo'  => ['label'=>lang('address_type_s'),'type'=>'fields','keys'=>$fields['keyAddr']],
                'options' => ['label'=>lang('options'),       'type'=>'fields','keys'=>$fields['keyOpt']],
                'details' => ['label'=>lang('details'),       'type'=>'fields','keys'=>$fields['keyDtl']]],
            'forms'    => ['frmEstimate'=>['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/rateList"]]],
            'fields'   => $fields['fields'],
            'jsReady'  => ['init'=>"ajaxForm('frmEstimate');"]]);
    }

    /**
     *
     * @param type $data
     * @return string
     */
    private function getRateFields($data=[])
    {
        $resi  = clean('resi', ['format'=>'integer','default'=>0], 'get');
        $total = !empty($data['totals']['total_amount']) ? clean($data['totals']['total_amount'], 'currency') : 0;
        if (!empty($data['item'])) {
            $this->guessShipment($data['item']);
        } else {
            $this->shipment['Qty'] = 1;
            $this->shipment['Wt']  = 0;
            $this->shipment['Ins'] = 0;
        }
        $guess = $this->shipment;
        $fields= [
            'keyAddr'=> ['city','state','postal_code','country'],
            'keyOpt' => ['num_boxes','weight','residential','length','txtWidth','width','txtHeight','height','insurance','ins_amount','extra1'], // ,'hazmat'
            'keyDtl' => ['ship_date','total_amount','ltl_class'],
            'fields' => [
                'city'        => ['order'=>10,'label'=>lang('city'),       'attr'=>['value'=>!empty($data['ship']['city_s'])       ? $data['ship']['city_s']       : '']],
                'state'       => ['order'=>20,'label'=>lang('state'),      'attr'=>['value'=>!empty($data['ship']['state_s'])      ? $data['ship']['state_s']      : '']],
                'postal_code' => ['order'=>30,'label'=>lang('postal_code'),'attr'=>['value'=>!empty($data['ship']['postal_code_s'])? $data['ship']['postal_code_s']: ''], 'size'=>10],
                'country'     => ['order'=>40,'label'=>lang('country'),    'attr'=>['type'=>'country', 'value'=>!empty($data['ship']['country_s']) ? $data['ship']['country_s'] : 'USA']],
                'num_boxes'   => ['order'=>10,'label'=>lang('num_boxes'),'attr'=>['type'=>'integer','value'=>$guess['Qty'],'size'=>5]],
                'weight'      => ['order'=>20,'label'=>$this->lang['ship_weight'],'attr'=>['type'=>'float','value'=>$guess['Wt'], 'size'=>10]],
                'residential' => ['order'=>30,'label'=>lang('residential_address'),'attr'=>['type'=>'checkbox']],
                'length'      => ['order'=>40,'label'=>$this->lang['dimensions'],'break'=>false,'attr' =>['type'=>'integer','value'=>$guess['L'],'size'=>3]],
                'txtWidth'    => ['order'=>49,'html' =>'X','break'=>false,'attr'=>['type'=>'raw']],
                'width'       => ['order'=>50,'break'=>false,'attr'=>['type'=>'integer','value'=>$guess['W'],'size'=>3]],
                'txtHeight'   => ['order'=>59,'html' =>'X','break'=>false,'attr'=>['type'=>'raw']],
                'height'      => ['order'=>60,'attr'=>['type'=>'integer','value'=>$guess['H'],'size'=>3]],
                'ship_date'   => ['order'=>10,'label'=>lang('ship_date'),'attr' =>['type'=>'date', 'value'=>biz_date('Y-m-d')]],
                'total_amount'=> ['order'=>40,'attr'=>['type'=>'hidden', 'value'=>$total]],
                'ltl_class'   => ['order'=>50,'label'=>lang('ltl_class'),'options'=>['width'=>100],'values'=>viewKeyDropdown($this->options['ltlClasses'], true),
                    'attr' => ['type'=>'select','value'=>$this->settings['general']['ltl_class']]],
                'insurance'   => ['order'=>60,'label'=>$this->lang['inc_insurance'],'attr'=>['type'=>'checkbox','checked'=>false, 'size'=>8]],
                'ins_amount'  => ['order'=>61,'label'=>$this->lang['amt_insurance'],'attr'=>['type'=>'currency','value'=>$guess['Ins']]],
                'extra1'      => ['order'=>70,'label'=>$this->lang['extras'],'values'=>viewKeyDropdown($this->options['extras'], true),'attr'=>['type'=>'select','name'=>'extra1[]','size'=>15,'multiple'=>'multiple','format'=>'array','value'=>[]]],
//              'hazmat'      => ['order'=>80,'label'=>$this->lang['hazardous'],'attr'=>['type'=>'checkbox','checked'=>false]],
                ]];
//        $addBook = dbLoadStructure(BIZUNO_DB_PREFIX.'contacts');
//        foreach ($fields['keyAddr'] as $idx) {
//            $fields['fields'][$idx.'_d'] = $addBook[$idx];
//            if (!empty($data['ship'][$idx.'_s'])) { $fields['fields'][$idx.'_d']['attr']['value'] = $data['ship'][$idx.'_s']; }
//        }
        if ($resi) { $fields['fields']['residential']['attr']['checked'] = true; }
        if (sizeof(getModuleCache('bizuno', 'stores')) > 1) {
            $fields['keyDtl'][] = 'store_id_b';
            $fields['fields']['store_id_b'] = ['order'=>20,'label'=>lang('bill_to'),  'values'=>viewStores(),'attr'=>['type'=>'select','value'=>!empty($data['storeID'])?$data['storeID']:0]];
            $fields['keyDtl'][] = 'store_id_p';
            $fields['fields']['store_id_p'] = ['order'=>30,'label'=>lang('ship_from'),'values'=>viewStores(),'attr'=>['type'=>'select','value'=>!empty($data['storeID'])?$data['storeID']:0]];
        }
        // show the desired methods to quote
        $order = 71;
        foreach ($this->myCarriers as $method => $value) {
            $htmlEl = ['order'=>$order,'label'=>$value['title'],'position'=>'after','break'=>true,'attr'=>['type'=>'checkbox','value'=>$method,'id'=>'method[]']];
            if (isset($value['settings']['default']) && $value['settings']['default']) { $htmlEl['attr']['checked'] = 'checked'; }
            $fields['keyDtl'][] = $method;
            $fields['fields'][$method] = $htmlEl;
            $order++;
        }
        msgDebug("\nReturning from getViewSettings with field array = ".print_r($fields, true));
        return $fields;
    }

    /**
     *
     * @param array $layout
     */
    public function rateList(&$layout=[])
    {
        $carriers = clean('method', 'array', 'post');
        $pkg = [
            'settings' => [
                'ship_date'   => clean('ship_date',  'date',    'post'),
                'insurance'   => clean('insurance',  'integer', 'post'),
//              'hazmat'      => clean('hazmat',     'integer', 'post'),
                'ins_amount'  => clean('ins_amount', 'currency','post'),
                'weight'      => clean('weight',     'float',   'post'),
                'length'      => clean('length',     'float',   'post'),
                'width'       => clean('width',      'float',   'post'),
                'height'      => clean('height',     'float',   'post'),
                'num_boxes'   => clean('num_boxes',  'integer', 'post'),
                'ltl_class'   => clean('ltl_class',  'text',    'post'),
                'residential' => clean('residential','boolean', 'post'),
                'verify_add'  => clean('verify_add', 'boolean', 'post')],
            'extras' => [
                'extra1'      => clean('extra1',     'array',   'post')]];
        $this->fieldsAddress($pkg, ['suffix'=>'_s','cID'=>clean('store_id_b', 'integer', 'post')]); // shipper
        $this->fieldsAddress($pkg, ['suffix'=>'_o','cID'=>clean('store_id_p', 'integer', 'post')]); // origin
        $this->fieldsAddress($pkg, ['suffix'=>'_p','cID'=>clean('store_id_b', 'integer', 'post')]); // payor
        $this->fieldsAddress($pkg, ['suffix'=>'',  'src'=>'post']); // destination
        if (!empty(clean('residential', 'integer', 'post'))) { $pkg['destination']['residential'] = true; } // check for resi
        msgDebug("\nReady to process rates with pkg = ".print_r($pkg, true));
        $rates = [];
        foreach ($carriers as $carrier) {
            $est = $this->loadCarrier($carrier);
            if (method_exists($est, 'rateQuote')) { $rates[$carrier] = $est->rateQuote($pkg); }
        }
        msgDebug("\nrate return array = ".print_r($rates, true));
        $data = [
            'content'=> ['action'=>'divHTML', 'type'=>'html', 'divID'=>'divRateResults'],
            'divs'   => ['body'=>['order'=>50,'type'=>'table','key'  =>'tblRates']],
            'tables' => ['tblRates'=>$this->getViewTable($rates)],
            'jsBody' => ['init'=>"function shippingRateReturn(method, service, cost, glAcct) {
    if (jqBiz('#totals_shipping_gl')) {
        bizSelSet('method_code', method+':'+service);
        bizSelSet('totals_shipping_gl', glAcct);
        bizNumSet('freight', cost);
        totalUpdate('rate list');
        bizWindowClose('shippingEst');
    }
}"],
            'jsReady' => ['init'=>"jqBiz('#accShipRate').accordion('select','".jsLang('rate_quote')."');"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * API method to retrieve shipping rates from default carriers
     * @param array $pkg - Formatted order/package information, MUST BE IN BIZUNO API FORMAT
     */
    public function rateAPI($pkg=[])
    {
        msgDebug("\nEntering rateAPI");
        $rates = $output = [];
        foreach ($this->myCarriers as $carrier => $props) {
            if (empty($props['settings']['default'])) { continue; }
            $est = $this->loadCarrier($carrier);
            if (method_exists($est, 'rateQuote')) { $rates[$carrier] = $est->rateQuote($pkg); }
        }
        msgDebug("\nrateAPI return array = ".print_r($rates, true));
        foreach ($rates as $carrier => $quotes) {
            foreach ($quotes as $idx => $quote) { $output[] = ['id'=>"{$carrier}_{$idx}", 'title'=>$quote['title'], 'cost'=>$quote['cost'], 'quote'=>$quote['quote']]; }
        }
        return $output;
    }

    /**
     *
     */
    private function getViewTable($rates)
    {
        $struc = ['classes'=>[],'styles'=>['border-collapse'=>'collapse','width'=>'100%'],'attr'=>['type'=>'table'],
            'tbody'=>['attr'=>['type'=>'tbody']]];
        if (empty($rates)) {
            $struc['tbody']['tr'][] = ['attr'=>['type'=>'tr'],'td'=>[['attr'=>['type'=>'td','value'=>lang('no_results'),'colspan'=>5]]]];
            return $struc;
        }
        foreach ($rates as $carrier => $values) {
            if (!is_array($values)) { continue; }
            $settings= getMetaMethod('carriers', $carrier);
            $shipSet = array_replace($settings, ['module'=>$this->moduleID, 'folder'=>'carriers']);
            $image   = htmlFindImage($shipSet, 32);
            $struc['tbody']['tr'][] = ['classes'=>['panel-header'],'attr'=>['type'=>'tr'],'td'=>[
                ['attr'=>['type'=>'th','value'=>($image ? $image : $shipSet['title'])]],
                ['attr'=>['type'=>'th','value'=>lang('rate_quote')]],
                ['attr'=>['type'=>'th','value'=>lang('list_price')]],
                ['attr'=>['type'=>'th','value'=>lang('cost')]],
                ['attr'=>['type'=>'th','value'=>lang('notes'),'nowrap'=>'nowrap']]]];
            foreach ($values as $service => $prices) {
            $struc['tbody']['tr'][] = ['styles'=>['cursor'=>'pointer'], 'events'=>['onClick'=>"shippingRateReturn('$carrier', '$service', '{$prices['quote']}', '{$prices['gl_acct']}')"],
                'attr'=>['type'=>'tr'], 'values'=>['carrier'=>$carrier,'service'=>$service,'glAcct'=>$prices['gl_acct']], 'td'=>[
                    'title'=> ['attr'=>['type'=>'td','value'=>$prices['title']]],
                    'quote'=> ['attr'=>['type'=>'td','value'=>viewFormat($prices['quote'], 'currency')]],
                    'book' => ['attr'=>['type'=>'td','value'=>(isset($prices['book'])?viewFormat($prices['book'], 'currency'):'')]],
                    'cost' => ['attr'=>['type'=>'td','value'=>(isset($prices['cost'])?viewFormat($prices['cost'], 'currency'):'')]],
                    'notes'=> ['attr'=>['type'=>'td','value'=>(isset($prices['note'])?$prices['note']:''),'nowrap'=>'nowrap']]]];
            }
        }
        return $struc;
    }
}
