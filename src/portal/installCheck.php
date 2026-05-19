<?php
/**
 * Bizuno pre-flight install check.
 *
 * Runs once on every request — before the autoloader, the database, or any
 * controller code — and verifies that the environment can actually run Bizuno.
 * If anything required is missing, renders a friendly setup-required page
 * with concrete recovery steps and exits before the rest of the bootstrap
 * gets a chance to fail with confusing secondary errors (e.g. the misleading
 * "Database connection failed" that shows up when vendor/ is missing).
 *
 * What it checks (in order):
 *   1. PHP version (>= 8.0; recommends 8.2)
 *   2. Required PHP extensions (mbstring, pdo_mysql, json, curl, openssl, zip, gd)
 *   3. Composer-managed dependencies (vendor/autoload.php exists)
 *
 * What it doesn't check (deliberately — those are the installer's job):
 *   - Database connectivity (the installer handles that with its own UI)
 *   - BIZUNO_DATA writability (installer handles)
 *   - BIZUNO_KEY rotation (installer handles)
 *
 * @author     Dave Premo, PhreeSoft <support@phreesoft.com>
 * @copyright  2008-2026, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-05-19 (introduced after vendor/ was untracked in Phase 1; without this check, a fresh clone bootstraps to a misleading "Database connection failed" page)
 * @filesource /portal/installCheck.php
 */

namespace bizuno;

/**
 * Run the pre-flight check. Either returns silently (environment is fine)
 * or renders an HTML page and exits the request (environment isn't).
 */
function preflightCheck()
{
    $problems = [];

    // ── 1. PHP version ────────────────────────────────────────────────────
    if (version_compare(PHP_VERSION, '8.0.0', '<')) {
        $problems[] = [
            'title' => 'PHP version too old',
            'body'  => 'Bizuno requires PHP 8.0 or higher. This server is running PHP '
                    . PHP_VERSION . '. Ask your host to upgrade — PHP 8.2 is recommended.',
            'fix'   => null,
        ];
    }

    // ── 2. Required PHP extensions ─────────────────────────────────────────
    $required = [
        'mbstring'  => 'Multibyte string handling — used everywhere UTF-8 matters',
        'pdo_mysql' => 'Database driver for MySQL / MariaDB',
        'json'      => 'JSON encode/decode — used by the report engine and the REST API',
        'curl'      => 'HTTP client — used by shipping carriers and payment gateways',
        'openssl'   => 'TLS + crypto — needed by the encryption helper and HTTPS calls',
        'zip'       => 'Archive support — backup tool and form/report import',
        'gd'        => 'Image library — barcodes, logo handling, image resize',
    ];
    $missing = [];
    foreach ($required as $ext => $what) {
        if (!extension_loaded($ext)) { $missing[$ext] = $what; }
    }
    if ($missing) {
        $bullets = '';
        foreach ($missing as $ext => $what) {
            $bullets .= '<li><code>' . htmlspecialchars($ext) . '</code> — '
                     . htmlspecialchars($what) . '</li>';
        }
        $problems[] = [
            'title' => 'Required PHP extensions missing',
            'body'  => 'These PHP extensions are not loaded and Bizuno can\'t run without them:'
                    . '<ul style="margin:8px 0 0 0;">' . $bullets . '</ul>',
            'fix'   => 'Install them via your distribution\'s package manager '
                    . '(e.g. <code>apt install php-mbstring php-mysql php-curl php-zip php-gd</code> on '
                    . 'Debian/Ubuntu, or <code>yum install php-mbstring php-pdo php-mysql php-curl php-zip php-gd</code> '
                    . 'on RHEL/CentOS) and restart PHP-FPM / Apache.',
        ];
    }

    // ── 3. Composer dependencies ──────────────────────────────────────────
    // Use the constant if it's defined (portalCFG.php has loaded), otherwise
    // fall back to the conventional path so this check still works if called
    // outside the normal bootstrap.
    $vendorAutoload = defined('BIZUNO_FS_ASSETS')
        ? BIZUNO_FS_ASSETS . 'autoload.php'
        : __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($vendorAutoload)) {
        $problems[] = [
            'title' => 'Dependencies not installed',
            'body'  => 'Bizuno\'s third-party libraries (tFPDF, FPDI, PHPMailer, '
                    . 'phpseclib, picqer/php-barcode-generator, …) haven\'t been '
                    . 'installed. The <code>vendor/</code> directory is empty or '
                    . 'missing.',
            'fix'   => '<p>You have two paths, in order of preference:</p>'
                    . '<h3 style="margin-bottom:6px;">A. Install via Composer (recommended)</h3>'
                    . '<p>From a terminal in this directory:</p>'
                    . '<pre>composer install --no-dev --optimize-autoloader</pre>'
                    . '<p>That fetches everything listed in <code>composer.json</code>. '
                    . 'Don\'t have Composer? Get it from <a href="https://getcomposer.org/download/" target="_blank" rel="noopener">getcomposer.org</a>.</p>'
                    . '<h3 style="margin-bottom:6px;">B. Use a pre-built release</h3>'
                    . '<p>Download a zip release from the <a href="https://github.com/phreesoft/bizuno/releases" target="_blank" rel="noopener">Bizuno releases page</a> '
                    . 'with dependencies already bundled. Unzip in place of this directory.</p>'
                    . '<p>For WordPress users: install the '
                    . '<a href="https://wordpress.org/plugins/bizuno-accounting/" target="_blank" rel="noopener">Bizuno Accounting plugin</a> '
                    . 'instead — it bundles everything and integrates with WP admin.</p>'
                    . '<p>Full install guide: <a href="https://github.com/phreesoft/bizuno#installation" target="_blank" rel="noopener">github.com/phreesoft/bizuno</a></p>',
        ];
    }

    if (!$problems) { return; }  // All good — let the normal bootstrap continue

    // ── Render the setup-required page and exit ───────────────────────────
    renderSetupRequiredPage($problems);
    exit(0);
}

/**
 * Render the friendly "Setup Required" HTML page.
 * Self-contained CSS — doesn't depend on any of Bizuno's assets, since by
 * definition those may not be loadable yet.
 */
function renderSetupRequiredPage(array $problems)
{
    // Suppress any prior output (e.g. a stray notice) so our page is clean.
    if (ob_get_level()) { @ob_end_clean(); }
    @header('HTTP/1.1 503 Service Unavailable');
    @header('Content-Type: text/html; charset=UTF-8');
    @header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    @header('Retry-After: 60');

    $year     = date('Y');
    $phpVer   = PHP_VERSION;
    $cardsHtml = '';
    foreach ($problems as $p) {
        $cardsHtml .= '<section class="card">'
                   .  '<h2>' . htmlspecialchars($p['title']) . '</h2>'
                   .  '<div class="card-body">' . $p['body'];
        if (!empty($p['fix'])) {
            $cardsHtml .= '<div class="card-fix"><strong>How to fix:</strong>' . $p['fix'] . '</div>';
        }
        $cardsHtml .= '</div></section>';
    }

    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bizuno — Setup Required</title>
<style>
  *,*::before,*::after { box-sizing: border-box; }
  html,body { margin:0; padding:0; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
    background: #f5f6f8;
    color: #2c3e50;
    line-height: 1.55;
    padding: 40px 20px;
  }
  .container { max-width: 760px; margin: 0 auto; }
  header { display:flex; align-items:center; gap:12px; margin-bottom:24px; }
  header h1 { margin:0; font-size:26px; font-weight:600; }
  header .badge {
    background:#e3f2fd; color:#1565c0; font-size:12px; font-weight:600;
    padding:3px 10px; border-radius:12px; letter-spacing:0.04em;
  }
  .intro { font-size:16px; color:#555; margin:0 0 24px; }
  .card {
    background:#fff; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,0.08);
    margin-bottom:18px; overflow:hidden;
  }
  .card h2 {
    margin:0; padding:14px 20px; font-size:16px; font-weight:600;
    background:#fff8e1; color:#5d4037;
    border-bottom:1px solid #f0e6c6;
  }
  .card-body { padding:18px 20px; }
  .card-body ul, .card-body p { margin: 0 0 10px; }
  .card-body ul { padding-left: 22px; }
  .card-body code {
    background:#eef1f5; color:#c0392b; font-family: Menlo, Consolas, monospace;
    padding:1px 6px; border-radius:3px; font-size:0.92em;
  }
  .card-body pre {
    background:#2c3e50; color:#ecf0f1; padding:14px 16px; border-radius:4px;
    overflow-x:auto; font-family: Menlo, Consolas, monospace; font-size:0.9em;
  }
  .card-fix {
    margin-top: 14px; padding: 14px 16px;
    background:#f1f8e9; border-left:3px solid #7cb342; border-radius:0 4px 4px 0;
  }
  .card-fix h3 { color:#33691e; font-size:14px; margin-top:8px; }
  .card-fix a { color:#2e7d32; }
  footer { color:#888; font-size:13px; margin-top:30px; text-align:center; }
  footer a { color:#666; text-decoration:none; }
  footer a:hover { text-decoration:underline; }
</style>
</head>
<body>
<div class="container">
  <header>
    <h1>Bizuno — Setup Required</h1>
    <span class="badge">503</span>
  </header>
  <p class="intro">
    Bizuno can't start until the environment is ready. The issue(s) below
    need fixing before you can install or log in. This is a one-time setup
    page — once everything is in place, you'll never see it again.
  </p>
  $cardsHtml
  <footer>
    <p>PHP $phpVer · Bizuno ERP &copy; $year PhreeSoft, Inc. ·
       <a href="https://github.com/phreesoft/bizuno" target="_blank" rel="noopener">GitHub</a> ·
       <a href="https://bizuno.com/docs/" target="_blank" rel="noopener">Documentation</a> ·
       <a href="https://phreesoft.com/support/" target="_blank" rel="noopener">Support</a>
    </p>
  </footer>
</div>
</body>
</html>
HTML;
}
