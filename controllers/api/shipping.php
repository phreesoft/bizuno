<?php
/*
 * Module api - shipping class
 * 
 * Handles all API operations related to 3rd party shipping 
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
 * @filesource /controllers/api/shipping.php
 */

namespace bizuno;

bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/api/common.php', 'apiCommon');

class apiShipping extends apiCommon
{
    public $moduleID = 'api';
    public $pageID = 'shipping';

    function __construct($options=[])
    {
        $this->lang = getLang($this->moduleID);
        parent::__construct($options);
    }
    /**************** REST Endpoints to retrieve shipping rates *************/
    /**
     * Fetches the shipping rates using the Bizuno carriers and settings.
     * @param array $request
     */
    public function rates_list($request)
    {
/*  IS THIS USED?
        $package= $this->rest_open($request); // do not use request since this is a post
        $this->getRatesPrep();
        $layout = ['pkg'=>['destination'=>$package], 'rates'=>[]];
//THIS PATH IS WRONG!!!
        compose('bizuno', 'export', 'shippingRates', $layout); 
        $output = ['rates'=>$layout['rates']];
        return $this->rest_close($output); */
    }
    /********************** Local get rate and return either local or REST *******************/
    public function getRates(&$layout=[])
    {
        $package = ['destination'=>[
            'country'    => clean('country',    'alpha_num', 'get'), // US
            'state'      => clean('state',      'alpha_num', 'get'), // TX
            'postcode'   => clean('postcode',   'alpha_num', 'get'), // 76092
            'city'       => clean('city',       'alpha_num', 'get'), // Southlake
            'address'    => clean('address',    'text',      'get'), // 
            'address_1'  => clean('address_1',  'text',      'get'), // 
            'address_2'  => clean('address_2',  'text',      'get'), // 
            'totalWeight'=> clean('totalWeight','float',     'get')]]; // 1.804
        msgDebug("\nEntering getRates with package = ".print_r($package, true));
        $data = ['pkg'=>$package, 'rates'=>[]];
        if (!empty($package['destination']['postcode'])) {
            compose('api', 'export', 'shippingRates', $data);
        }
        msgDebug("\nReturning from API getRates with layout = ".print_r($data, true));
        $layout= array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode($data)]);
    }
    private function getRatesPrep()
    {
        $_POST['country_s']  = clean('country',    ['format'=>'alpha_num', 'default'=>''], 'get');
        $_POST['state_s']    = clean('state',      ['format'=>'alpha_num', 'default'=>''], 'get');
        $_POST['postcode_s'] = clean('postcode',   ['format'=>'alpha_num', 'default'=>''], 'get');
        $_POST['city_s']     = clean('city',       ['format'=>'alpha_num', 'default'=>''], 'get');
        $_POST['address1_s'] = clean('address1',   ['format'=>'alpha_num', 'default'=>''], 'get');
        $_POST['address2_s'] = clean('address2',   ['format'=>'alpha_num', 'default'=>''], 'get');
        $_POST['totalWeight']= round(clean('totalWeight',['format'=>'float', 'default'=>0], 'get'), 1);
    }
}
