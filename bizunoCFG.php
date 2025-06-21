<?php
/*
 * PhreeSoft Client Hosting - Bizuno Config file
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
 * @version    7.x Last Update: 2025-06-21
 * @filesource /src/bizunoCFG.php
 */

namespace bizuno;

if (!defined('SCRIPT_START_TIME')) { define('SCRIPT_START_TIME', microtime(true)); }

define('MODULE_BIZUNO_VERSION', file_exists(__DIR__.'/VERSION') ? file_get_contents(__DIR__.'/VERSION') : '7'); // Pull form 

// URL paths
if (!defined('BIZUNO_HOME')) { define('BIZUNO_HOME', strpos(BIZUNO_SRVR, '?')===false ? BIZUNO_SRVR.'?' : BIZUNO_SRVR); } // Full URL path to Bizuno Home
define('BIZUNO_AJAX',       substr(BIZUNO_SRVR, 0, strlen(BIZUNO_SRVR)-1).'?ajax=1'); // for non-html requests
define('BIZBOOKS_URL_ROOT', BIZUNO_SRVR); // full url to Bizuno root folder
define('BIZBOOKS_URL_FS',   BIZBOOKS_URL_ROOT.'?bizRt=portal/api/fs&src='); // full url to Bizuno portal file system access script
// File system paths
define('BIZBOOKS_ROOT',     BIZUNO_REPO); // file system path to bizuno root index file
// PhreeSoft Images
define('PHREESOFT_LOGO',    BIZBOOKS_URL_FS.'0/view/images/phreesoft.png'); // URL to default logo
define('BIZUNO_LOGO',       BIZBOOKS_URL_FS.'0/view/images/bizuno.png'); // URL to default logo
// Set support ticket email, this makes the menu option show
if (!defined('BIZUNO_SUPPORT_NAME'))  { define('BIZUNO_SUPPORT_NAME', 'Bizuno Support'); }
if (!defined('BIZUNO_SUPPORT_EMAIL')) { define('BIZUNO_SUPPORT_EMAIL','support@phreesoft.com'); }

// Set the PDF renderer application
$pdfRenderer = 'TCPDF'; // Options are 'TCPDF' (Default) and 'tFPDF'
if ('tFPDF'==$pdfRenderer) { // http://www.fpdf.org/
    define('BIZUNO_PDF_ENGINE', 'tFPDF');
    define('BIZUNO_3P_PDF', BIZBOOKS_ROOT.'assets/FPDF/');
} else { // Current: https://github.com/tecnickcom/tc-lib-pdf - was: https://tcpdf.org/
    define('BIZUNO_PDF_ENGINE', 'TCPDF');
    define('BIZUNO_3P_PDF', BIZBOOKS_ROOT.'assets/TCPDF/');
}

// set some sitewide constants
define('COG_ITEM_TYPES', 'ma,mi,ms,sa,si,sr');
define('PHREEBOOKS_CHART_TYPES', [
    '0' => ['id'=>'0',  'account'=>'', 'title'=>'gl_acct_type_0'],    // Cash
    '2' => ['id'=>'2',  'account'=>'', 'title'=>'gl_acct_type_2'],    // Accounts Receivable
    '4' => ['id'=>'4',  'account'=>'', 'title'=>'gl_acct_type_4'],    // Inventory
    '6' => ['id'=>'6',  'account'=>'', 'title'=>'gl_acct_type_6'],    // Other Current Assets
    '8' => ['id'=>'8',  'account'=>'', 'title'=>'gl_acct_type_8'],    // Fixed Assets
   '10' => ['id'=>'10', 'account'=>'', 'title'=>'gl_acct_type_10'],   // Accumulated Depreciation
   '12' => ['id'=>'12', 'account'=>'', 'title'=>'gl_acct_type_12'],   // Other Assets
   '20' => ['id'=>'20', 'account'=>'', 'title'=>'gl_acct_type_20'],   // Accounts Payable
   '22' => ['id'=>'22', 'account'=>'', 'title'=>'gl_acct_type_22'],   // Other Current Liabilities
   '24' => ['id'=>'24', 'account'=>'', 'title'=>'gl_acct_type_24'],   // Long Term Liabilities
   '30' => ['id'=>'30', 'account'=>'', 'title'=>'gl_acct_type_30'],   // Income/Sales
   '32' => ['id'=>'32', 'account'=>'', 'title'=>'gl_acct_type_32'],   // Cost of Sales
   '34' => ['id'=>'34', 'account'=>'', 'title'=>'gl_acct_type_34'],   // Expenses
   '40' => ['id'=>'40', 'account'=>'', 'title'=>'gl_acct_type_40'],   // Equity - Does Not Close
   '42' => ['id'=>'42', 'account'=>'', 'title'=>'gl_acct_type_42'],   // Equity - Gets Closed
   '44' => ['id'=>'44', 'account'=>'', 'title'=>'gl_acct_type_44']]); // Equity - Retained Earnings
define('BIZTHEMES_ICONS', ['default', 'nuvola']);
define('BIZTHEMES_EASYUI', [
    'bizuno', 'black', 'bootstrap', 'default', 'gray', 'material', 'material-blue', 'material-teal', 'metro', // Standard themes
    'metro', 'metro-blue', 'metro-gray', 'metro-green', 'metro-orange', 'metro-red', // Metro themes
    'ui-cupertino', 'ui-dark-hive', 'ui-pepper-grinder', 'ui-sunny']); // jQuery UI themes 

