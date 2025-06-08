<?php

/*
 * Locale langage file - English US (en_US)
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
 * @filesource /local/en_US/locale.php
 */

namespace bizuno;

$langByRef = [
    'bizuno_admin' => $langCore['settings'],
    'bizuno_tools' => $langCore['tools'],
    'bizuno_users' => $langCore['users'],
    'gl_type_account' => $langCore['gl_account'],
    'ctype_c_prc' => sprintf($langCore['tbd_prices'], $langCore['ctype_c']),
    'ctype_u_prc' => sprintf($langCore['tbd_prices'], $langCore['ctype_u']),
    'ctype_v_prc' => sprintf($langCore['tbd_prices'], $langCore['ctype_v']),
    'users_admin_id' => $langCore['id'],
    'gl_acct_id' => $langCore['gl_account'],
    'gl_acct_type_4_mgr' => sprintf($langCore['tbd_manager'], $langCore['gl_acct_type_4']),
    'journal_id_6_mgr' => sprintf($langCore['tbd_manager'], $langCore['journal_id_6']),
    'journal_id_12_mgr' => sprintf($langCore['tbd_manager'], $langCore['journal_id_12']),
    'invoice_num_12' => $langCore['invoice_num_6'],
    'invoice_num_18' => $langCore['invoice_num_17'],
    'invoice_num_22' => $langCore['invoice_num_20'],
    'purch_order_id_9' => $langCore['invoice_num_4'],
    'purch_order_id_10' => $langCore['invoice_num_4'],
    'store_id' => $langCore['store_id'],
    'primary_name_b' => sprintf($langCore['tbd_bill'], $langCore['primary_name']),
    'contact_b' => sprintf($langCore['tbd_bill'], $langCore['contact']),
    'address1_b' => sprintf($langCore['tbd_bill'], $langCore['address1']),
    'address2_b' => sprintf($langCore['tbd_bill'], $langCore['address2']),
    'city_b' => sprintf($langCore['tbd_bill'], $langCore['city']),
    'state_b' => sprintf($langCore['tbd_bill'], $langCore['state']),
    'postal_code_b' => sprintf($langCore['tbd_bill'], $langCore['postal_code']),
    'country_b' => sprintf($langCore['tbd_bill'], $langCore['country']),
    'telephone1_b' => sprintf($langCore['tbd_bill'], $langCore['telephone']),
    'email_b' => sprintf($langCore['tbd_bill'], $langCore['email']),
    'primary_name_s' => sprintf($langCore['tbd_ship'], $langCore['primary_name']),
    'contact_s' => sprintf($langCore['tbd_ship'], $langCore['contact']),
    'address1_s' => sprintf($langCore['tbd_ship'], $langCore['address1']),
    'address2_s' => sprintf($langCore['tbd_ship'], $langCore['address2']),
    'city_s' => sprintf($langCore['tbd_ship'], $langCore['city']),
    'state_s' => sprintf($langCore['tbd_ship'], $langCore['state']),
    'postal_code_s' => sprintf($langCore['tbd_ship'], $langCore['postal_code']),
    'country_s' => sprintf($langCore['tbd_ship'], $langCore['country']),
    'telephone1_s' => sprintf($langCore['tbd_ship'], $langCore['telephone']),
    'email_s' => sprintf($langCore['tbd_ship'], $langCore['email']),
    'tax_rate_id_c' => $langCore['sales_tax'],
    'tax_rate_id_v' => $langCore['purchase_tax'],
    'terminal_date_9' => $langCore['terminal_date_3'],
    'terminal_date_10' => $langCore['ship_date'],
    'terminal_date_12' => $langCore['ship_date'],
    ];
