<?php
/*
 * @name Bizuno ERP - Bizuno Pro Payment Module - Nacha map for Chase Bank
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
 * @filesource /controllers/phreebooks/nachaMaps/chase.php
 *
 * US-NACHA-CCD flat file business account format
 *
 * NOTE: This is the format for Chase, will NOT work for others.
 * https://www.chase.com/content/dam/chase-ux/documents/digital/resources/cbo_nacha_filespecs.pdf
 */

$map = [
    'id'         => 'Chase',
    'title'      => 'Chase Bank',
    'description'=> 'Chase bank ACH bulk payment nacha definition file.',
    'file_head'  => [
        ['length'=> 1, 'format'=>'const', 'pad'=>' ', 'justify'=>'r', 'data'=>'1'], // '1' - Record Type
        ['length'=> 2, 'format'=>'const', 'pad'=>' ', 'justify'=>'r', 'data'=>'01'], // EFT Priority Code
        ['length'=> 1, 'format'=>'const', 'pad'=>' ', 'justify'=>'r', 'data'=>' '], // EFT Transit Routing Number
        ['length'=> 9, 'format'=>'data',  'pad'=>'0', 'justify'=>'r', 'data'=>'biz_route'], // EFT Transit Routing Number
        ['length'=>10, 'format'=>'const', 'pad'=>'0', 'justify'=>'r', 'data'=>'0000000000'], // Must be 10 zeros
        ['length'=> 6, 'format'=>'data',  'pad'=>' ', 'justify'=>'r', 'data'=>'date_ymd'], // File Creation Date
        ['length'=> 4, 'format'=>'data',  'pad'=>' ', 'justify'=>'r', 'data'=>'time_hi'], // File Creation Time
        ['length'=> 1, 'format'=>'const', 'pad'=>' ', 'justify'=>'l', 'data'=>'A'], // 'A' - File ID Modifier
        ['length'=> 3, 'format'=>'const', 'pad'=>' ', 'justify'=>'r', 'data'=>'094'], // '094' - Record Size
        ['length'=> 2, 'format'=>'const', 'pad'=>' ', 'justify'=>'r', 'data'=>'10'], // Blocking Factor
        ['length'=> 1, 'format'=>'const', 'pad'=>' ', 'justify'=>'r', 'data'=>'1'], // '1' - Format Code
        ['length'=>23, 'format'=>'const', 'pad'=>' ', 'justify'=>'l', 'data'=>'JP MORGAN CHASE'], // Chase Processing Site
        ['length'=>23, 'format'=>'data',  'pad'=>' ', 'justify'=>'l', 'data'=>'biz_name'], // My Company Name
        ['length'=> 8, 'format'=>'const', 'pad'=>' ', 'justify'=>'l', 'data'=>'']], // Reference
    'batch_head' => [
        ['length'=> 1, 'format'=>'const', 'pad'=>' ', 'justify'=>'r', 'data'=>'5'], // '5' - Record Type
        ['length'=> 3, 'format'=>'const', 'pad'=>'0', 'justify'=>'r', 'data'=>'220'], // Service Class Code
        ['length'=>16, 'format'=>'data',  'pad'=>' ', 'justify'=>'l', 'data'=>'biz_name'], // Small Company Name
        ['length'=>20, 'format'=>'data',  'pad'=>'0', 'justify'=>'r', 'data'=>'biz_id'], // Chase funding Account Number
        ['length'=>10, 'format'=>'const', 'pad'=>'0', 'justify'=>'l', 'data'=>'0000000000'], // Company ID number
        ['length'=> 3, 'format'=>'const', 'pad'=>' ', 'justify'=>'l', 'data'=>'CCD'], // Standard Class Code
        ['length'=>10, 'format'=>'data',  'pad'=>' ', 'justify'=>'l', 'data'=>'biz_entry'], // Company Entry Description
        ['length'=> 6, 'format'=>'data',  'pad'=>' ', 'justify'=>'r', 'data'=>'date_text'], // Company Description Date
        ['length'=> 6, 'format'=>'data',  'pad'=>' ', 'justify'=>'r', 'data'=>'date_plus_1'], // Effective Date
        ['length'=> 3, 'format'=>'const', 'pad'=>' ', 'justify'=>'r', 'data'=>''], // Payment date
        ['length'=> 1, 'format'=>'const', 'pad'=>' ', 'justify'=>'l', 'data'=>'1'], // '1' - Originator Status Code
        ['length'=> 8, 'format'=>'data',  'pad'=>'0', 'justify'=>'l', 'data'=>'biz_route_act'], // Transit routing Number
        ['length'=> 7, 'format'=>'const', 'pad'=>'0', 'justify'=>'r', 'data'=>'1']], // Batch Number, always 1 for single batch
    'details' => [
        ['length'=> 1, 'format'=>'const', 'pad'=>' ', 'justify'=>'r', 'data'=>'6'], // '6' - Record Type
        ['length'=> 2, 'format'=>'const', 'pad'=>' ', 'justify'=>'r', 'data'=>'22'], // Transaction Code
        ['length'=> 8, 'format'=>'data',  'pad'=>'0', 'justify'=>'r', 'data'=>'payee_route_act'], // Payee Transit routing number
        ['length'=> 1, 'format'=>'data',  'pad'=>'0', 'justify'=>'r', 'data'=>'payee_route_chk'], // Routing number check digit
        ['length'=>17, 'format'=>'data',  'pad'=>' ', 'justify'=>'l', 'data'=>'payee_account'], // Payee Bank Account Number
        ['length'=>10, 'format'=>'data',  'pad'=>'0', 'justify'=>'r', 'data'=>'payee_amount'], // Payment Amount
        ['length'=>15, 'format'=>'data',  'pad'=>' ', 'justify'=>'l', 'data'=>'payee_id'], // Payee ID/Cross Ref number
        ['length'=>22, 'format'=>'data',  'pad'=>' ', 'justify'=>'l', 'data'=>'payee_name'], // Payee Name
        ['length'=> 2, 'format'=>'const', 'pad'=>' ', 'justify'=>'l', 'data'=>''], // Discretionary Data
        ['length'=> 1, 'format'=>'const', 'pad'=>'0', 'justify'=>'r', 'data'=>''], // Addenda Record Indicator
        ['length'=> 8, 'format'=>'data',  'pad'=>'0', 'justify'=>'r', 'data'=>'biz_route_act'], // Chase routing Number
        ['length'=> 7, 'format'=>'data',  'pad'=>'0', 'justify'=>'r', 'data'=>'row_count']], // Trace Number
    'batch_ctl' => [
        ['length'=> 1, 'format'=>'const', 'pad'=>' ', 'justify'=>'r', 'data'=>'8'], // '8' - Record Type
        ['length'=> 3, 'format'=>'const', 'pad'=>'0', 'justify'=>'r', 'data'=>'220'], // Service Class code
        ['length'=> 6, 'format'=>'data',  'pad'=>'0', 'justify'=>'r', 'data'=>'row_count'], // Addenda Count
        ['length'=>10, 'format'=>'data',  'pad'=>'0', 'justify'=>'r', 'data'=>'hash_total'], // Hash Total
        ['length'=>12, 'format'=>'data',  'pad'=>'0', 'justify'=>'r', 'data'=>'total_debit'], // Total Debit Amount
        ['length'=>12, 'format'=>'data',  'pad'=>'0', 'justify'=>'r', 'data'=>'total_credit'], // Total Credit Amount
        ['length'=>10, 'format'=>'const', 'pad'=>'0', 'justify'=>'l', 'data'=>'0000000000'], // Company ID Number
        ['length'=>19, 'format'=>'const', 'pad'=>' ', 'justify'=>'l', 'data'=>''], // Filler/Reserved
        ['length'=> 6, 'format'=>'const', 'pad'=>' ', 'justify'=>'l', 'data'=>''], // Filler/Reserved
        ['length'=> 8, 'format'=>'data',  'pad'=>' ', 'justify'=>'l', 'data'=>'biz_route_act'], // Transit Routing Number
        ['length'=> 7, 'format'=>'const', 'pad'=>'0', 'justify'=>'r', 'data'=>'1']], // Batch Number, always 1 for single batch
    'file_ctl' => [
        ['length'=> 1, 'format'=>'const', 'pad'=>' ', 'justify'=>'r', 'data'=>'9'], // '9' - Record Type
        ['length'=> 6, 'format'=>'const', 'pad'=>'0', 'justify'=>'r', 'data'=>'1'], // Batch Count, always 1 since our batches are small
        ['length'=> 6, 'format'=>'data',  'pad'=>'0', 'justify'=>'r', 'data'=>'block_count'], // Block Count
        ['length'=> 8, 'format'=>'data',  'pad'=>'0', 'justify'=>'r', 'data'=>'row_count'], // Addenda Count
        ['length'=>10, 'format'=>'data',  'pad'=>'0', 'justify'=>'r', 'data'=>'hash_total'], // Hash Total
        ['length'=>12, 'format'=>'data',  'pad'=>'0', 'justify'=>'r', 'data'=>'total_debit'], // Total Debit Amount
        ['length'=>12, 'format'=>'data',  'pad'=>'0', 'justify'=>'r', 'data'=>'total_credit'], // Total Credit Amount
        ['length'=>39, 'format'=>'const', 'pad'=>' ', 'justify'=>'l', 'data'=>'']], // Filler/Reserved
    ];