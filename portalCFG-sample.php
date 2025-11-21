<?php
/**
 * Portal configuration file
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
 * @version    7.x Last Update: 2025-09-10
 * @filesource /index.php
 */

namespace bizuno;

if (!defined('SCRIPT_START_TIME')) { define('SCRIPT_START_TIME', microtime(true)); }

/******************** BEGIN - Site Specific Settings ***********************/

// 1-10 digit AlphaNumeric, cannot be zero
define('BIZUNO_BIZID', '1'); 

// file system path to your data files.
// Can be outside of your web server direct access but mush be within the 
// path of PHP.
define('BIZUNO_DATA', 'data/');

// Encryption key for cookies, and other publically viewable information
// Up to 16 alpha-numeric characters, randomly generated
define('BIZUNO_KEY', '0123456789AbCdEf'); 

// Database credentials
define('BIZUNO_DBTYPE',   'mysql');      // Database Engine
define('BIZUNO_DBHOST',   'localhost');  // Host Name
define('BIZUNO_DB_PREFIX','');           // Database Table Prefix
define('BIZUNO_DBNAME',   'dbName');     // Database Name
define('BIZUNO_DBUSER',   'dbUsername'); // User name
define('BIZUNO_DBPASS',   'dbPassword'); // Password

/******************** END - Site Specific Settings ***********************/

// Set the Bizuno host 
define('BIZUNO_HOST', 'LOCAL');

// Database credentials
define('BIZUNO_DB_CREDS', [
    'type'  => BIZUNO_DBTYPE,
    'host'  => BIZUNO_DBHOST,
    'name'  => BIZUNO_DBNAME,
    'user'  => BIZUNO_DBUSER,
    'pass'  => BIZUNO_DBPASS,
    'prefix'=> BIZUNO_DB_PREFIX]);
define('BIZUNO_PORTAL',  $_SERVER['SERVER_NAME']);

// File System Paths
define('BIZUNO_FS_ASSETS', 'vendor/');
define('BIZUNO_FS_LIBRARY',   BIZUNO_FS_ASSETS.'phreesoft/bizuno/');
// URL's
define('BIZUNO_URL_SCRIPTS', BIZUNO_FS_LIBRARY.'scripts/');
define('BIZUNO_URL_PORTAL',    'https://'.BIZUNO_PORTAL.'/');

// Initialize Bizuno - Make sure the Bizuno library is installed and reachable
if (!file_exists(BIZUNO_FS_LIBRARY.'bizunoCFG.php')) {
    echo 'The Bizuno Library cannot be located. the library and installation instructions can be found <a href="https://github.com/phreesoft/bizuno">HERE</a>';
    exit;
}
require(BIZUNO_FS_LIBRARY.'bizunoCFG.php'); // Config for current release
