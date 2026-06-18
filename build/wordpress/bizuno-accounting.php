<?php
/**
 * Plugin Name:       Bizuno Accounting – ERP/Accounting/CRM for WordPress
 * Plugin URI:        https://wordpress.org/plugins/bizuno-accounting/
 * Description:       Powerful open-source ERP, double-entry accounting, inventory, CRM & business management. Runs as a secure portal in WP admin. Activate, click the Bizuno menu and follow the install wizard.
 * Version:           7.4.3
 * Requires at least: 6.5
 * Tested up to:      6.9.4
 * Requires PHP:      8.2
 * Author:            PhreeSoft, Inc.
 * Author URI:        https://www.phreesoft.com
 * Author Email:      support@phreesoft.com
 * Text Domain:       bizuno-accounting
 * Domain Path:       /src/locale
 * License:           AGPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/agpl-3.0.txt
 */

// This file is the standalone WordPress plugin entry. As of 7.3.9 it absorbed
// the WP-admin integration that used to live in the separate bizuno-accounting
// repo — there is no longer a sibling library plugin (`bizuno-wp` is gone) and
// no GitHub-Releases auto-updater (wordpress.org's normal update channel does
// the job). The plugin is fully self-contained: src/ ships at <plugin>/src/,
// composer dependencies at <plugin>/vendor/, third-party UI assets at
// <plugin>/scripts/. All path constants are resolved relative to this file in
// portalCFG.php.

defined( 'ABSPATH' ) || exit;

class bizuno_accounting
{
    private $bizSlug = 'bizuno';

    public function __construct()
    {
        // ─── Actions ───────────────────────────────────────────────────────
        add_action ( 'init',                       [ $this, 'initializeBizuno' ] );
        add_action ( 'admin_menu',                 [ $this, 'admin_menu_bizuno' ] );
        add_action ( 'wp_before_admin_bar_render', [ $this, 'bizuno_admin_menu_mods' ] );
        add_action ( 'phpmailer_init',             [ $this, 'bizuno_phpmailer_init' ], 10, 1 );
        add_action ( 'template_redirect',          [ $this, 'bizunoPageRedirect' ] );
        add_action ( 'wp_ajax_bizuno_ajax',        [ $this, 'bizunoAjax' ] );
        add_action ( 'wp_ajax_nopriv_bizuno_ajax', [ $this, 'bizunoAjax' ] );
        add_action ( 'bizuno_daily_event',         [ $this, 'daily_cron' ] );
        // ─── Legacy bizuno-wp migration ────────────────────────────────────
        // Pre-7.3.9 sites had two plugins: this one (admin glue) + bizuno-wp
        // (library). The library now ships inside this plugin. Auto-deactivate
        // bizuno-wp once on upgrade, then nag the admin to delete its files.
        add_action ( 'admin_init',                 [ $this, 'maybeMigrateLegacyBizunoWp' ] );
        add_action ( 'admin_notices',              [ $this, 'legacyBizunoWpNotice' ] );
        // ─── Filters ───────────────────────────────────────────────────────
        // Remove XML-RPC pingback to cut a noisy attack surface for users who
        // never explicitly enabled pingbacks.
        add_filter ( 'xmlrpc_methods', function( $methods ) { unset( $methods[ 'pingback.ping' ] ); return $methods; } );
        // ─── Lifecycle hooks ───────────────────────────────────────────────
        register_deactivation_hook ( __FILE__, [ $this, 'deactivate' ] );
        register_uninstall_hook    ( __FILE__, 'bizuno_accounting_uninstall' );  // must be a top-level function
    }

    /**
     * Wire up Bizuno's runtime globals on every WP request. The heavy lifting
     * (path constants, DB creds, library require) is in portalCFG.php; the
     * rest of this method just makes sure msgStack / cleaner / io / db
     * singletons exist so any later Bizuno call has them available.
     */
    public function initializeBizuno()
    {
        global $msgStack, $cleaner, $db, $io;
        require_once ( plugin_dir_path( __FILE__ ) . 'portalCFG.php' );
        // portalCFG.php is the canonical source for path/URL constants
        // including BIZUNO_URL_VIEW — no need to re-define here.
        // These three are cheap, allocation-only globals (no DB, no I/O on
        // construction) so they're safe to set up on every request.
        if ( !isset( $msgStack ) || !( $msgStack instanceof \bizuno\messageStack ) ) { $msgStack = new \bizuno\messageStack(); }
        if ( !isset( $cleaner )  || !( $cleaner  instanceof \bizuno\cleaner ) )      { $cleaner  = new \bizuno\cleaner(); }
        if ( !isset( $io )       || !( $io       instanceof \bizuno\io ) )           { $io       = new \bizuno\io(); }
        // DB connection is opened LAZILY. Previously this ran on every WP
        // `init` — i.e. on every storefront page view and every unrelated
        // AJAX call — holding a MySQL connection for the whole request even
        // when Bizuno was never touched. On shared hosts with a low
        // `max_user_connections` cap (Bizuno shares WordPress's DB user) that
        // exhausts the pool and surfaces as MySQL error 1203. The only
        // consumer of $db at init time is the admin install-status notice
        // (verifyDbInstalled), which is meaningful only on real wp-admin page
        // loads. Front-end /bizuno page views and bizuno_ajax calls let
        // portalCtl open its own single connection, so we no longer
        // double-connect there either.
        if ( is_admin() && !wp_doing_ajax() ) {
            if ( !isset( $db ) || !( $db instanceof \bizuno\db ) ) { $db = new \bizuno\db( BIZUNO_DB_CREDS ); }
            $this->verifyDbInstalled();
        }
    }

    /**
     * Add the "Bizuno" entry to the WP admin sidebar. The page itself is now
     * a deprecation notice (we redirect real Bizuno traffic through the
     * front-end /bizuno slug, not the admin page).
     */
    public function admin_menu_bizuno()
    {
        add_menu_page(
            'Bizuno',
            'Bizuno',
            'manage_options',
            'bizuno',
            'bizuno_accounting_html',
            plugins_url( 'icon_16.png', __FILE__ ),
            90
        );
    }

    /**
     * Adds a "Bizuno Accounting" entry to the WP admin toolbar (user-menu
     * dropdown, top right). Shows up for any logged-in user with access —
     * the link opens the /bizuno page in a new tab. Skipped if the /bizuno
     * placeholder page doesn't exist (activation creates it; admins can
     * delete it, in which case we just leave the toolbar alone).
     */
    public function bizuno_admin_menu_mods()
    {
        global $wp_admin_bar;
        if ( empty( get_page_by_path( $this->bizSlug ) ) ) { return; }
        $logout = $wp_admin_bar->get_node( 'logout' );
        $wp_admin_bar->remove_node( 'logout' );
        $wp_admin_bar->add_node( [
            'id'     => 'tb-admin-bizBooks',
            'title'  => 'Bizuno Accounting',
            'href'   => BIZUNO_URL_PORTAL,
            'parent' => 'user-actions',
            'meta'   => [ 'target' => '_blank' ],
        ] );
        $wp_admin_bar->add_node( $logout );  // re-add logout so it sits below
    }

    /**
     * Force HTML mode for any outbound mail sent while we're rendering the
     * /bizuno page (Bizuno's outbound email templates are HTML).
     */
    public function bizuno_phpmailer_init( $phpmailer )
    {
        if ( get_post_field( 'post_name' ) == 'bizuno' ) { $phpmailer->IsHTML( true ); }
    }

    /**
     * Intercept the front-end /bizuno page and hand control to Bizuno's own
     * controller instead of rendering the WP theme. Bizuno authenticates
     * independently of WordPress (its own bizunoSession cookie + sign-in
     * screen), so we do NOT gate on is_user_logged_in() here — that would
     * force a redundant WordPress login first. portalCtl::getScope() returns
     * 'guest' for an unauthenticated visitor and renders Bizuno's own login;
     * a valid Bizuno session lands straight in the full UI.
     */
    public function bizunoPageRedirect()
    {
        global $post;
        if ( !empty( $post->post_name ) && $this->bizSlug == $post->post_name ) {
            // /bizuno is a live application endpoint, never a cacheable static
            // page. Tell page-cache layers (Bluehost Endurance Page Cache, WP
            // Super Cache, W3TC, WP Rocket, Batcache, …) to skip it and send
            // no-store headers for any CDN/proxy in front. Without this, a
            // cached copy is served WITHOUT invoking PHP, so this hook never
            // fires and the page won't boot Bizuno — and a logged-in render
            // could be served to another visitor.
            if ( !defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
            nocache_headers();
            new \bizuno\portalCtl();
            exit();
        }
    }

    /**
     * AJAX endpoint — wired to both bizuno_ajax and nopriv_bizuno_ajax so
     * Bizuno can be called from front-end forms or admin contexts alike.
     * Bizuno's own portalCtl handles auth from there.
     */
    public function bizunoAjax()
    {
        // AJAX responses are per-user, per-request application data — never
        // cacheable. Same rationale as bizunoPageRedirect(): keep page-cache
        // layers and any CDN/proxy from storing or replaying them.
        if ( !defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
        nocache_headers();
        new \bizuno\portalCtl();
        exit();
    }

    /**
     * Daily cron — runs once/day via wp_schedule_event. Bizuno's
     * periodAutoUpdate keeps fiscal-period rollover honest.
     */
    public function daily_cron()
    {
        \bizuno\periodAutoUpdate( false );
    }

    /**
     * First-run nudge — if the Bizuno DB tables aren't created yet, show
     * an admin notice pointing the user at the /bizuno page where the
     * installer runs on first hit.
     */
    private function verifyDbInstalled()
    {
        if ( !is_admin() ) { return; }
        if ( \bizuno\dbTableExists( BIZUNO_DB_PREFIX . 'configuration' ) ) { return true; }
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p>You\'re just about ready to get started managing your business! '
           . 'Click <a href="' . esc_url( home_url( "/$this->bizSlug" ) ) . '" target="_blank">HERE</a> to open a new page at your website to run the database installer script. '
           . 'Remember to bookmark the page so you can quickly access your Bizuno business in the future.</p>';
        echo '</div>';
    }

    /**
     * One-time migration from the pre-7.3.9 two-plugin layout. If the
     * legacy `bizuno-wp` library plugin is currently active, deactivate it
     * — its code now ships inside this plugin and leaving it active causes
     * its now-defunct GitHub auto-updater bootstrap to keep firing on
     * every request for nothing.
     *
     * Gated by a one-shot option so we run it exactly once. If a future
     * admin reactivates `bizuno-wp` deliberately, we won't fight them —
     * the persistent admin notice (below) will still nag until they
     * delete the legacy plugin's files.
     */
    public function maybeMigrateLegacyBizunoWp()
    {
        if ( get_option( 'bizuno_accounting_legacy_migrated', false ) ) { return; }
        if ( !function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if ( is_plugin_active( 'bizuno-wp/bizuno-wp.php' ) ) {
            deactivate_plugins( 'bizuno-wp/bizuno-wp.php' );
        }
        update_option( 'bizuno_accounting_legacy_migrated', true );
    }

    /**
     * Persistent (non-dismissible) admin notice that appears as long as
     * the `bizuno-wp/` directory exists in wp-content/plugins/. Goes away
     * automatically once the admin deletes the legacy plugin.
     *
     * Capability-gated so only users who can act on the recommendation
     * (delete plugins) see the nag — no need to clutter the screen for
     * editors / authors.
     */
    public function legacyBizunoWpNotice()
    {
        if ( !current_user_can( 'activate_plugins' ) ) { return; }
        if ( !file_exists( WP_PLUGIN_DIR . '/bizuno-wp/bizuno-wp.php' ) ) { return; }

        $plugins_search_url = admin_url( 'plugins.php?s=bizuno-wp' );
        echo '<div class="notice notice-warning">';
        echo '<p><strong>Bizuno: legacy <code>bizuno-wp</code> plugin still installed</strong></p>';
        echo '<p>';
        echo wp_kses_post( sprintf(
            /* translators: 1: anchor open tag pointing at WP plugins list filtered for bizuno-wp, 2: anchor close tag */
            __( 'As of Bizuno Accounting 7.4.0, the separate <code>bizuno-wp</code> library plugin is no longer needed — its code is now bundled inside this plugin. It has been automatically deactivated, but the files remain on disk. Please %1$sdelete the bizuno-wp plugin%2$s from your Plugins list to complete the migration.', 'bizuno-accounting' ),
            '<a href="' . esc_url( $plugins_search_url ) . '">',
            '</a>'
        ) );
        echo '</p>';
        echo '</div>';
    }

    /**
     * Clean up the daily cron event on deactivation. Data and tables
     * survive deactivation — those only get cleared by uninstall.
     */
    public static function deactivate()
    {
        wp_clear_scheduled_hook( 'bizuno_daily_event' );
    }
}
new bizuno_accounting();


/* ────────── Operations outside of class ─────────────────────────────────────
 * WordPress requires register_uninstall_hook's callback to be a plain
 * function (not a method) so it can run in the WP context after the plugin
 * file is loaded fresh.
 */

register_activation_hook( __FILE__, 'bizuno_accounting_activate' );

/**
 * On plugin activation:
 *   1. Schedule the daily cron that keeps Bizuno's fiscal periods rolling.
 *   2. Create the public /bizuno placeholder page. The Bizuno UI replaces
 *      this content on the fly for logged-in users; anonymous visitors see
 *      the placeholder explaining they need to log in.
 *   3. If the legacy `bizuno-wp` library plugin is still active, deactivate
 *      it (the code now ships inside this plugin). The admin_init handler
 *      `maybeMigrateLegacyBizunoWp` does the same thing — duplicating it
 *      here lets fresh-install users land in a clean state immediately,
 *      without waiting for the next admin request to fire admin_init.
 */
function bizuno_accounting_activate()
{
    if ( !wp_next_scheduled( 'bizuno_daily_event' ) ) {
        wp_schedule_event( time(), 'daily', 'bizuno_daily_event' );
    }
    if ( !get_page_by_path( 'bizuno' ) ) {
        wp_insert_post( [
            'post_title'   => 'Bizuno',
            'post_name'    => 'bizuno',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' =>
                "This page is reserved for authorized users of Bizuno Accounting/ERP.\n"
              . "To access Bizuno, please <a href=\"/wp-login.php\">click here</a> to log into your WordPress site and select Bizuno Accounting from the profile menu in the upper right corner of the screen.\n"
              . "If Bizuno Accounting is not an option, see your administrator to gain permission.</p>\n"
              . "<p>Administrators: To authorize a user, navigate to the WordPress administration page -> Users -> search  username/eMail.\n"
              . "Edit the user and check the 'Allow access to: <My Business>' box along with a role and click Save.",
        ] );
    }
    // Deactivate the legacy bizuno-wp library plugin if still present.
    if ( !function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if ( is_plugin_active( 'bizuno-wp/bizuno-wp.php' ) ) {
        deactivate_plugins( 'bizuno-wp/bizuno-wp.php' );
    }
    update_option( 'bizuno_accounting_legacy_migrated', true );
}

/**
 * Admin-menu "Bizuno" page handler. Real Bizuno now runs in full-screen
 * front-end mode (via /bizuno + template_redirect); this admin landing page
 * exists only to point users at the new flow.
 */
function bizuno_accounting_html()
{
    echo "<p>Please Note: This access method to Bizuno Accounting has deprecated!</p>";
    echo "<p>Bizuno Accounting now only runs in full screen mode to allow all users to use the app in a consistent environment.</p>";
    echo "<p>Permission must be granted by checking the 'Enable Bizuno Accounting' checkbox in each individual users profile. Once access has been granted, Bizuno Accounting can be accessed from the Users profile drop down menu after the user has logged into your site.</p>";
}

/**
 * Hard uninstall — drop all Bizuno tables (anything matching {prefix}bizuno_*),
 * remove user-meta keys starting with bizuno_, and recursively delete the
 * /bizuno upload directory. This is destructive; WP's uninstall machinery
 * only calls it when a site admin explicitly chooses "Delete" in the
 * Plugins screen, not on routine deactivation.
 */
function bizuno_accounting_uninstall()
{
    global $wpdb;
    // Direct DB queries + DROP TABLE are intentional here — uninstall is the
    // one place a plugin legitimately needs to wipe its own tables. Object
    // caching also doesn't apply: by the time uninstall runs the plugin is
    // already being torn down. Suppress phpcs warnings that would otherwise
    // flag this as suspicious.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'bizuno_%'" );
    $tables = $wpdb->get_results( "SHOW TABLES LIKE '{$wpdb->prefix}bizuno_%'", ARRAY_A );
    foreach ( $tables as $row ) {
        $table = array_shift( $row );
        $wpdb->query( "DROP TABLE IF EXISTS $table" );
    }
    // phpcs:enable
    $upload_dir = wp_upload_dir();
    bizuno_accounting_rmdir( $upload_dir['basedir'] . '/bizuno' );
    delete_option( 'bizuno_accounting_legacy_migrated' );
}

/**
 * Recursive rmdir helper for uninstall — WP doesn't ship one.
 */
function bizuno_accounting_rmdir( $dir )
{
    if ( !is_dir( $dir ) ) { return; }
    $objects = scandir( $dir );
    foreach ( $objects as $object ) {
        if ( $object === '.' || $object === '..' ) { continue; }
        $path = $dir . '/' . $object;
        if ( is_dir( $path ) ) {
            bizuno_accounting_rmdir( $path );
        } else {
            wp_delete_file( $path );
        }
    }
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- WP has no rmdir replacement; needed to remove Bizuno's per-site upload dir during uninstall.
    rmdir( $dir );
}
