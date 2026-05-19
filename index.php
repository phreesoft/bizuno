<?php
/**
 * Bizuno Portal entry point
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
 * @version    7.x Last Update: 2026-05-19 (added preflightCheck() so a missing vendor/ shows a friendly setup-required page instead of a misleading DB-error screen)
 * @filesource /index.php
 */

namespace bizuno;

if (file_exists('portalCFG.php')) { require( 'portalCFG.php' ); }
elseif (file_exists('portalCFG-sample.php')) { require( 'portalCFG-sample.php' ); }
else { die( 'Mayday! There seems to be a critical file missing, you may want to re-install Bizuno from the GitHub source!' ); }

// Pre-flight: verify PHP version, required extensions, and that composer
// dependencies are installed BEFORE the autoloader is touched. If anything
// is missing, this renders a friendly setup-required page and exits. On a
// healthy install it's a fast file-exists + extension_loaded sweep and
// returns silently. Loaded from portal/ because portalCFG.php has just
// defined the path constants this check uses (BIZUNO_FS_LIBRARY, BIZUNO_FS_ASSETS).
require( BIZUNO_FS_LIBRARY . 'portal/installCheck.php' );
preflightCheck();

new portalCtl();
