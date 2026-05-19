<?php
/**
 * Plugin Name:       Bizuno – Full ERP/Accounting Portal
 * Plugin URI:        https://www.phreesoft.com
 * Description:       Powerful open-source ERP, double-entry accounting, inventory, CRM & business management portal for WordPress. Activate, click the Bizuno menu in admin, and complete the quick install to launch your full system.
 * Version:           7.3.9
 * Requires at least: 6.5
 * Tested up to:      6.9.4
 * Requires PHP:      8.2
 * Author:            PhreeSoft, Inc.
 * Author URI:        https://www.phreesoft.com
 * Author Email:      support@phreesoft.com
 * Text Domain:       bizuno
 * Domain Path:       /src/locale
 * License:           AGPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/agpl-3.0.txt
 * Update URI:        https://github.com/phreesoft/bizuno
 */

defined( 'ABSPATH' ) || exit;

class bizuno_wp
{
    public function __construct()
    {
        // Class initialization, actions, filters wired up by bizuno-accounting
        // (the WP admin wrapper plugin). This file is the bare plugin entry that
        // registers the plugin with WordPress and handles auto-updates; Bizuno's
        // actual functionality is bootstrapped when bizuno-accounting loads its
        // portalCFG.php and requires src/portal/controller.php from this plugin.
    }
}
new bizuno_wp();

// ─── Auto-updates ────────────────────────────────────────────────────────────
// Pull releases from GitHub. As of Phase 3, the release.yml workflow builds
// `bizuno-wp-VERSION.zip` on every `git tag v*` push and attaches it to the
// matching GitHub Release. plugin-update-checker auto-detects the GitHub URL
// and uses the GitHub API to discover new releases; the zip asset's name
// must match the plugin slug (`bizuno-wp`). Public repo means no auth token
// needed — the library hits api.github.com unauthenticated, well within rate
// limits for the once-per-12-hours update check WP performs by default.
require_once plugin_dir_path( __FILE__ ) . 'vendor/yahniselsts/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
$bizunoUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/phreesoft/bizuno/',
    __FILE__,
    'bizuno-wp'
);
$bizunoUpdateChecker->setBranch( 'main' );
// Optionally pin to releases (vs. raw main HEAD) — releases are what users want.
// The library calls this "release assets" mode: only published GitHub Releases
// trigger updates, and the bundled zip asset is what gets installed.
$bizunoUpdateChecker->getVcsApi()->enableReleaseAssets( '/^bizuno-wp-.*\.zip$/' );
