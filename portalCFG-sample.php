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
 * @copyright  2008-2026, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-05-19 (Phase 2: BIZUNO_FS_LIBRARY now points at src/; portal/controller.php load shifted to BIZUNO_FS_LIBRARY)
 * @filesource /portalCFG-sample.php
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

// Encryption key for password peppering and PII encryption.
// As of v7.x, session cookies are signed with a separate per-install secret
// (BIZUNO_INSTANCE_KEY) that bizuno auto-generates into BIZUNO_DATA/biz-instance-key.php
// on first boot — that file is the cookie root of trust and must be backed up.
// BIZUNO_KEY is still used elsewhere (password hashes, PII encryption) and MUST be
// set to a unique value per install — leaving it as the shipped default is a known
// vulnerability. The Bizuno installer rotates this automatically; if you copied this
// file by hand, change the literal below before going to production.
// 16 alpha-numeric characters, randomly generated
if ( !defined( 'BIZUNO_KEY' ) ) { define( 'BIZUNO_KEY', '0123456789abcdef' ); }

// Database credentials
if ( !defined( 'BIZUNO_DB_PREFIX' ) ) { define( 'BIZUNO_DB_PREFIX', '' ); } // Database table prefix
if ( !defined( 'BIZUNO_DB_CREDS' ) ) { define( 'BIZUNO_DB_CREDS', ['type'=>'mysql', 'host'=>'localhost', 'name'=>'dbName', 'user'=>'dbUser', 'pass'=>'dbPassword', 'prefix'=>BIZUNO_DB_PREFIX ] ); }

// If you want to allow users to send support tickets to your administrator, set email here
//define('BIZUNO_SUPPORT_NAME', 'My Business Support');
//define('BIZUNO_SUPPORT_EMAIL','webmaster@my_D_domain.com');

// CSRF Layer 2 — synchronizer-token enforcement.
// Defaults to ON (set in bizunoCFG.php). Uncomment the line below ONLY if you have a
// specific reason to fall back to warn-only mode (e.g. mid-rollout testing on an
// install with custom myExt extensions whose ajax paths don't yet attach the token).
// Warn-only logs CSRF mismatches to trace.txt without rejecting the request.
//define('BIZUNO_CSRF_ENFORCE', false);

/******************** END - Site Specific Settings ***********************/

// Platform-specific file-system paths.
//
//   BIZUNO_FS_PORTAL  — directory containing index.php (web-served root)
//   BIZUNO_FS_LIBRARY — directory containing the Bizuno PHP source. Defaults
//                       to BIZUNO_FS_PORTAL.'src/' (shipped layout) but may
//                       be set to an absolute path OUTSIDE the webroot for a
//                       hardened install — keeps the PHP source out of any
//                       directory Apache can serve. Common pattern on
//                       ISPConfig and similar managed-hosting setups:
//                         define('BIZUNO_FS_LIBRARY', '/var/www/biz.example.com/private/bizuno/src/');
//                         define('BIZUNO_FS_ASSETS',  '/var/www/biz.example.com/private/bizuno/vendor/');
//                         define('BIZUNO_DATA',       '/var/www/biz.example.com/private/bizuno/data/');
//   BIZUNO_FS_ASSETS  — directory containing composer's vendor/. Same pattern
//                       — defaults under the webroot but can be moved out.
//
// All three are `if (!defined())` guarded so a user-supplied value in
// portalCFG.php takes precedence over these computed defaults. The Bizuno
// installer rewrites BIZUNO_BIZID / BIZUNO_KEY / BIZUNO_DB_CREDS but leaves
// any custom path defines you've added untouched.
if ( !defined( 'BIZUNO_FS_PORTAL' ) )   { define( 'BIZUNO_FS_PORTAL',   $_SERVER['DOCUMENT_ROOT'].'/'); }
if ( !defined( 'BIZUNO_FS_LIBRARY' ) )  { define( 'BIZUNO_FS_LIBRARY',  BIZUNO_FS_PORTAL . 'src/' ); }
if ( !defined( 'BIZUNO_FS_ASSETS' ) )   { define( 'BIZUNO_FS_ASSETS',   BIZUNO_FS_PORTAL . 'vendor/' ); }

// Platform Specific - URL's
if ( !defined( 'BIZUNO_URL_PORTAL' ) )  { define( 'BIZUNO_URL_PORTAL',  (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] ); } // full url to Bizuno root folder
if ( !defined( 'BIZUNO_URL_AJAX' ) )    { define( 'BIZUNO_URL_AJAX',    BIZUNO_URL_PORTAL.'?ajax=1' ); }
if ( !defined( 'BIZUNO_URL_API' ) )     { define( 'BIZUNO_URL_API',     BIZUNO_URL_PORTAL.'?bizRt=' ); }
if ( !defined( 'BIZUNO_URL_FS' ) )      { define( 'BIZUNO_URL_FS',      BIZUNO_URL_PORTAL.'?bizRt=portal/api/fs&src=' ); }
if ( !defined( 'BIZUNO_URL_SCRIPTS' ) ) { define( 'BIZUNO_URL_SCRIPTS', BIZUNO_URL_PORTAL.'/scripts/' );  }

// Initialize Bizuno Library
require ( BIZUNO_FS_LIBRARY . 'bizunoCFG.php' );

// Initialize Portal — sits under src/portal/ post-Phase 2, so loaded via
// BIZUNO_FS_LIBRARY (which now points at src/) rather than BIZUNO_FS_PORTAL.
require ( BIZUNO_FS_LIBRARY . 'portal/controller.php' );
