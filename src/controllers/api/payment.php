<?php
/*
 * Module api - payment class
 * 
 * Handles all API operations related to external payments
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
 * @version    7.x Last Update: 2026-02-28
 * @filesource /controllers/api/payment.php
 */

namespace bizuno;

class apiPayment extends apiCommon
{
    public $moduleID = 'api';
    public $pageID = 'payment';
    public $userID = 0;

    function __construct($options=[])
    {
        parent::__construct($options);
    }
    /********************** REST Endpoints ************************/
    /**
     * [POST] Saves a users new card to their wallet
     */
    public function wallet_add_request($request)
    {
        $data  = $this->rest_open($request);        
        $postID= $this->productImport($data['product']);
        $output= ['result'=>!empty($postID)?'Success':'Fail', 'ID'=>$postID];
        return $this->rest_close($output);
    }
    /**
     * [GET] Response side - Retrieve wallet cards
     */
    public function wallet_add_response()
    {
        $data = [];
        if (!$this->bizActive)  { return; }
        $this->rest_open();
        if (empty($this->pfID)) { 
            msgAdd("Bad payfabric ID passed: $this->pfID");
            $status = 400;
        } else {
            compose('payment', 'wallet', 'add', $data);
            $status = 200;
        }
        $this->rest_close($data, $status);
    }
    /**
     * [GET] Deletes a users card from their wallet
     */
    public function wallet_delete_request($request)
    {
        $data  = $this->rest_open($request);
        $postID= $this->productImport($data['product']);
        $output= ['result'=>!empty($postID)?'Success':'Fail', 'ID'=>$postID];
        return $this->rest_close($output);
    }
    /**
     * [GET] Response side - Retrieve wallet cards
     */
    public function wallet_delete_response()
    {
        $data = [];
        if (!$this->bizActive)  { return; }
        $this->rest_open();
        if (empty($this->pfID)) { 
            msgAdd("Bad payfabric ID passed: $this->pfID");
            $status = 400;
        } else {
            compose('payment', 'wallet', 'delete', $data);
            $status = 200;
        }
        $this->rest_close($data, $status);
    }
    /**
     * [GET] Request side - Pulls the wallet from the Bizuno host and returns the list
     * To be used to manage the users wallet in the admin and during checkout
     */
    public function wallet_list_request($pfID='')
    {
        if ( !$this->bizActive)   { return; }
        if ( empty ( $pfID ) ) { error_log("Bad payfabric ID passed: $pfID"); return; }
        $this->client_open();
        $resp = $this->restGo('get', $this->options['url'], 'wallet/list', ['pfID'=>$this->pfID]);
        $this->client_close();
        if ( isset($resp['message'] ) ) { msgMerge($resp['message']); }
        return $resp;
    }
    /**
     * [GET] Response side - Retrieve wallet cards
     */
    public function wallet_list_response()
    {
        $data = [];
        if (!$this->bizActive)  { return; }
        $this->rest_open();
        if (empty($this->pfID)) { 
            msgAdd("Bad payfabric ID passed: $this->pfID");
            $status = 400;
        } else {
            compose('payment', 'wallet', 'list', $data);
            $status = 200;
        }
        $this->rest_close($data, $status);
    }
}