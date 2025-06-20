<?php
/*
 * Module api - Common Functions
 * 
 * Handles common mehods shared amongst all classes in this module
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
 * @filesource /controllers/api/common.php
 */

namespace bizuno;

class apiCommon
{
    public $lang;
    
    function __construct($options=[])
    {
        $this->lang = getLang($this->moduleID);
    }

    /**
     * Communicates with the remote server through the RESTful API
     * @global type $portal
     * @param type $type
     * @param type $server
     * @param type $endpoint
     * @param type $data
     * @return type
     */
    public function restGo($type, $server, $endpoint, $data=[])
    {
        global $portal;
        $opts = [];
        if (!empty($portal->useOauth)) { // Set the credentials
            $portal->id   = $this->options['oauth_client_id'];
            $portal->pass = $this->options['oauth_client_secret'];
//      } else { // the following duplicates the credentials and causes failed transaction
//          $opts = ['headers'=>['email'=>$this->options['rest_user_name'], 'pass'=>$this->options['rest_user_pass']]];
        }
        $resp = $portal->restRequest($type, $server, "wp-json/bizuno-api/v1/$endpoint", $data, $opts);
        msgDebug("\nAPI Common received back from REST: ".print_r($resp, true));
        if (isset($resp['message'])) {
            msgDebug("\nMerging the msgStack!");
            msgMerge($resp['message']);
        }
        return $resp;
    }
}
