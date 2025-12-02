<?php
/*
 * Bizuno Config file
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
 * @version    7.x Last Update: 2025-11-22
 * @filesource /bizunoCFG.php
 */

namespace bizuno;

if (!defined('SCRIPT_START_TIME')) { define('SCRIPT_START_TIME', microtime(true)); }

define('MODULE_BIZUNO_VERSION', file_exists(__DIR__.'/VERSION') ? file_get_contents(__DIR__.'/VERSION') : '7.3.3'); // Pull from file 

// URLs to PhreeSoft Images
define('BIZUNO_LOGO',    'https://www.bizuno.com/bizuno.png'); // URL to default logo
define('BIZUNO_ICON',    'https://www.bizuno.com/bizuno_icon.png'); // URL to default Bizuno icon on the bizuno.com site

define('PHREESOFT_LOGO', 'https://www.phreesoft.com/phreesoft.png'); // URL to default logo
define('PHREESOFT_URL',  'https://www.phreesoft.com/wp-json/phreesoft-custom/v1'); // URL to PhreeSoft RESTful API
define('PHREESOFT_IP',   '71.78.123.232');

// set some sitewide constants
//define('COG_ITEM_TYPES', 'ma,mi,ms,sa,si,sr'); // DEPRECATED
define('INVENTORY_COGS_TYPES', ['ma','mi','ms','sa','si','sr']); // Inventory types that track costs in the gl
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
define('BIZTHEMES_EASYUI', ['auto', // Auto Detect, chooses either Bizuno theme or Black theme depending on theusers default browser choice
    'bizuno', 'black', 'bootstrap', 'default', 'gray', 'material', 'material-blue', 'material-teal', 'metro', // Standard themes
    'metro', 'metro-blue', 'metro-gray', 'metro-green', 'metro-orange', 'metro-red', // Metro themes
    'ui-cupertino', 'ui-dark-hive', 'ui-pepper-grinder', 'ui-sunny']); // jQuery UI themes 

// Fetch the Bizuno library classes and functions
require_once ( BIZUNO_FS_LIBRARY . 'model/functions.php' ); // Core functions, needs to be included first
require_once ( BIZUNO_FS_LIBRARY . 'locale/cleaner.php' );
require_once ( BIZUNO_FS_LIBRARY . 'locale/currency.php' );
require_once ( BIZUNO_FS_LIBRARY . 'model/db.php' );
require_once ( BIZUNO_FS_LIBRARY . 'model/encrypter.php' );
require_once ( BIZUNO_FS_LIBRARY . 'model/io.php' );
require_once ( BIZUNO_FS_LIBRARY . 'model/manager.php' );
require_once ( BIZUNO_FS_LIBRARY . 'model/msg.php' );
require_once ( BIZUNO_FS_LIBRARY . 'model/mail.php' );
require_once ( BIZUNO_FS_LIBRARY . 'view/main.php' );
require_once ( BIZUNO_FS_LIBRARY . 'view/easyUI/html5.php' );

// Set the PDF renderer application
$pdfRenderer = 'TCPDF'; // Options are 'TCPDF' (Default) and 'tFPDF'
if ('tFPDF'==$pdfRenderer) { // http://www.fpdf.org/
    define('BIZUNO_PDF_ENGINE', 'FPDF');
    define('BIZUNO_3P_PDF', BIZUNO_FS_ASSETS.'FPDF/');
} else { // Current: https://github.com/tecnickcom/tc-lib-pdf - was: https://tcpdf.org/
    define('BIZUNO_PDF_ENGINE', 'TCPDF');
    define('BIZUNO_3P_PDF', BIZUNO_FS_ASSETS.'TCPDF/');
}

// Load the Bizuno third party libraries
if (file_exists(BIZUNO_FS_ASSETS . 'autoload.php' ) ) { // using composer
    require ( BIZUNO_FS_ASSETS  . 'autoload.php' );
} else { // If not using composer, try to load each library used seperately
    if (file_exists( BIZUNO_FS_ASSETS . 'tecnickcom/tcpdf/tcpdf.php' ) && 'TCPDF' == BIZUNO_PDF_ENGINE) {
        require ( BIZUNO_FS_ASSETS . 'tecnickcom/tcpdf/tcpdf.php' );
        require ( BIZUNO_FS_ASSETS . 'setasign/fpdi/src/autoload.php' );
    } elseif (file_exists( BIZUNO_FS_ASSETS . 'setasign/fpdf/fpdf.php' ) && 'FPDF' == BIZUNO_PDF_ENGINE) {
        require ( BIZUNO_FS_ASSETS . 'setasign/fpdf/fpdf.php' );
        require ( BIZUNO_FS_ASSETS . 'setasign/fpdi/src/autoload.php' );
    }
    if (file_exists( BIZUNO_FS_ASSETS . 'phpmailer/phpmailer/src/PHPMailer.php' )) {
        require ( BIZUNO_FS_ASSETS . 'phpmailer/phpmailer/src/PHPMailer.php' );
        require ( BIZUNO_FS_ASSETS . 'phpmailer/phpmailer/src/SMTP.php' );
        require ( BIZUNO_FS_ASSETS . 'phpmailer/phpmailer/src/Exception.php' );
    }
    if (file_exists( BIZUNO_FS_ASSETS . 'phpseclib/phpseclib/phpseclib/Net/SSH2.php' )) {
        require ( BIZUNO_FS_ASSETS . 'paragonie/constant_time_encoding/src/EncoderInterface.php' ); // ParagonIE, needs to be before phpseclib
        require ( BIZUNO_FS_ASSETS . 'paragonie/constant_time_encoding/src/Base64.php' );
        require ( BIZUNO_FS_ASSETS . 'phpseclib/phpseclib/phpseclib/Crypt/Common/AsymmetricKey.php' );
        require ( BIZUNO_FS_ASSETS . 'phpseclib/phpseclib/phpseclib/Crypt/Hash.php' );
        require ( BIZUNO_FS_ASSETS . 'phpseclib/phpseclib/phpseclib/Crypt/PublicKeyLoader.php' );
        require ( BIZUNO_FS_ASSETS . 'phpseclib/phpseclib/phpseclib/Crypt/RSA.php' );
        require ( BIZUNO_FS_ASSETS . 'phpseclib/phpseclib/phpseclib/Math/BigInteger.php' );
        require ( BIZUNO_FS_ASSETS . 'phpseclib/phpseclib/phpseclib/Net/SSH2.php' );
    }
}
