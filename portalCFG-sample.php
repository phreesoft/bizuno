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
 * @version    7.x Last Update: 2025-12-01
 * @filesource /index.php
 */

namespace bizuno;

if (!defined('SCRIPT_START_TIME')) { define('SCRIPT_START_TIME', microtime(true)); }

/******************** BEGIN - Site Specific Settings ***********************/

// 1-10 digit AlphaNumeric, cannot be zero
// Your unique Bizuno Business ID
if ( !defined( 'BIZUNO_BIZID' ) ) { define( 'BIZUNO_BIZID', '123456' ); }

// file system path to your cache, data and backup files.
// Can be outside of your web server direct access but mush be within the path of PHP.
if ( !defined( 'BIZUNO_DATA' ) ) { define( 'BIZUNO_DATA', $_SERVER['DOCUMENT_ROOT'].'/data/' ); }

// Encryption key for cookies, and other publically viewable information
// 16 alpha-numeric characters, randomly generated
if ( !defined( 'BIZUNO_KEY' ) ) { define( 'BIZUNO_KEY', '0123456789abcdef' ); }

// Database credentials
if ( !defined( 'BIZUNO_DB_PREFIX' ) ) { define( 'BIZUNO_DB_PREFIX', '' ); } // Database table prefix
if ( !defined( 'BIZUNO_DB_CREDS' ) ) { define( 'BIZUNO_DB_CREDS', ['type'=>'mysql', 'host'=>'localhost', 'name'=>'dbName', 'user'=>'dbUser', 'pass'=>'dbPassword', 'prefix'=>BIZUNO_DB_PREFIX ] ); }

// If you want to allow users to send support tickets to your administrator, set email here
//define('BIZUNO_SUPPORT_NAME', 'My Business Support');
//define('BIZUNO_SUPPORT_EMAIL','webmaster@my_D_domain.com');

/******************** END - Site Specific Settings ***********************/

// Platform Specific - File System Paths
if ( !defined( 'BIZUNO_FS_PORTAL' ) )   { define( 'BIZUNO_FS_PORTAL',   $_SERVER['DOCUMENT_ROOT'].'/'); }
if ( !defined( 'BIZUNO_FS_LIBRARY' ) )  { define( 'BIZUNO_FS_LIBRARY',  BIZUNO_FS_PORTAL ); }
if ( !defined( 'BIZUNO_FS_ASSETS' ) )   { define( 'BIZUNO_FS_ASSETS',   BIZUNO_FS_PORTAL . 'vendor/' ); }

// Platform Specific - URL's
if ( !defined( 'BIZUNO_URL_PORTAL' ) )  { define( 'BIZUNO_URL_PORTAL',  (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] ); } // full url to Bizuno root folder
if ( !defined( 'BIZUNO_URL_AJAX' ) )    { define( 'BIZUNO_URL_AJAX',    BIZUNO_URL_PORTAL.'?ajax=1' ); }
if ( !defined( 'BIZUNO_URL_API' ) )     { define( 'BIZUNO_URL_API',     BIZUNO_URL_PORTAL.'?bizRt=' ); }
if ( !defined( 'BIZUNO_URL_FS' ) )      { define( 'BIZUNO_URL_FS',      BIZUNO_URL_PORTAL.'?bizRt=portal/api/fs&src=' ); }
if ( !defined( 'BIZUNO_URL_SCRIPTS' ) ) { define( 'BIZUNO_URL_SCRIPTS', BIZUNO_URL_PORTAL.'/scripts/' );  }

// Initialize Bizuno Library
require ( BIZUNO_FS_LIBRARY . 'bizunoCFG.php' );

// Initialize Portal
require ( BIZUNO_FS_PORTAL  . 'portal/controller.php' );
