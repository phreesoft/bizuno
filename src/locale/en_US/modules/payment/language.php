<?php
/*
 * Language translation for Payment module
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
 * @version    7.x Last Update: 2025-05-19
 * @filesource /controllers/payment/locale/en_US/language.php
 */

$lang = [
    'title' => 'Payment',
    'description' => 'The payment module is a wrapper for user configurable payment methods. Some methods are included with the core package and others are available for download from the PhreeSoft website.',
    'payment_settings_discount_gl' => 'Default GL Discount Account to use for this payment method.',
    'payment_settings_deposit_prefix' => 'Default prefix to use for deposit slips.',
    'stored' => 'Stored',
    // Settings
    'gl_payment_c_lbl' => 'AR Cash GL Account',
    'gl_payment_c_tip'  => 'Default GL account to use for payments received from customers. Typically a Cash type account.',
    'gl_discount_c_lbl' => 'AR Disc GL Account',
    'gl_discount_c_tip' => 'Default GL account to use for payment discounts from customers. Typically a Sales/Income type account.',
    'gl_payment_v_lbl' => 'AP Cash GL Account',
    'gl_payment_v_tip'  => 'Default GL account to use for payments to vendors (bills). Typically a Cash type account.',
    'gl_discount_v_lbl' => 'AP Disc GL Account',
    'gl_discount_v_tip' => 'Default GL account to use for payment discounts to vendors. Typically a Cost of Goods Sold type account.',
    'prefix_lbl' => 'Reference Prefix',
    'prefix_tip' => 'Default prefix for deposits. Deposits with the same ID are grouped together and simplify bank account reconciliation.',
    // Messages
    'msg_approval_success' => '%s - Approval code: %s --> CVV2 results: %s',
    'err_payment_dup' => 'This payment has already been processed, resubmission to the payment gateway has been skipped!',
    'err_cvv_mismatch' => 'CAUTION! The CCV code was returned but did not match at the processor. Your processor responded with: %s. This may be an indication of a stolen credit card!',
    'err_avs_mismatch' => 'CAUTION! The Address Verification Response code was returned but did not match at your processor. Your processor responded with: %s. This may be an indication of a stolen credit card!',
    // AVS Codes
    'AVS_A' => 'Address matches - Postal Code does not match.',
    'AVS_B' => 'Street address match, Postal code in wrong format. (International issuer)',
    'AVS_C' => 'Street address and postal code in wrong formats.',
    'AVS_D' => 'Street address and postal code match. (international issuer)',
    'AVS_E' => 'AVS Error.',
    'AVS_G' => 'Service not supported by non-US issuer.',
    'AVS_I' => 'Address information not verified by international issuer.',
    'AVS_M' => 'Street address and Postal code match. (international issuer)',
    'AVS_N' => 'No match on address (street) or postal code.',
    'AVS_O' => 'No response sent.',
    'AVS_P' => 'Postal code matches, street address not verified due to incompatible formats.',
    'AVS_R' => 'Retry, system unavailable or timed out.',
    'AVS_S' => 'Service not supported by issuer.',
    'AVS_U' => 'Address information is unavailable.',
    'AVS_W' => '9 digit postal code matches, address (street) does not match.',
    'AVS_X' => 'Exact AVS match.',
    'AVS_Y' => 'Address (street) and 5 digit postal code match.',
    'AVS_Z' => '5 digit postal code matches, address (street) does not match.',
    // CCV Codes
    'CVV_M' => 'CVV2 matches',
    'CVV_N' => 'CVV2 does not match',
    'CVV_P' => 'Not Processed',
    'CVV_S' => 'Issuer indicates that CVV2 data should be present on the card, but the merchant has indicated that the CVV2 data is not present on the card.',
    'CVV_U' => 'Issuer has not certified for CVV2 or issuer has not provided Visa with the CVV2 encryption keys.',
    // ACH Settings
    'ach_accounts' => 'ACH Accounts',
    'title_nacha' => 'ACH Manager',
    'panel_nacha' => 'Nacha Panel',
    'description' => 'The Bizuno Pro Payment extension ...',
    'file_nacha' => 'NACHA Transmit History',
    'nacha_map_lbl' => 'Nacha Map',
    'biz_route_lbl' =>'Transit Routing #',
    'biz_route_tip'=>'EFT Transit Routing Number, 9 digit number, assigned by bank',
    'biz_id_lbl'    =>'Company ID/Acct #',
    'biz_id_tip'   =>'EFT Company ID, numbers only, assigned by bank. Some banks (e.g. Chase) use the account number here.',
    'biz_entry_lbl' =>'Company Entry',
    'biz_entry_tip'=>'Company Entry Description, see spec for details, typically set to TRADE PAY.',
    'biz_name_lbl'  =>'Company Name',
    'biz_name_tip' =>'Company name, pulled from your business name in Settings -> Bizuno -> My Business by default.',
    ];
