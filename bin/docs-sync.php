#!/usr/bin/env php
<?php
/**
 * Bizuno docs sync — push bizuno/docs/**.md to BetterDocs on a WordPress site
 * via the WP REST API.
 *
 *   php bin/docs-sync.php --user=<wp-user> --pass=<app-password>
 *
 * Credentials can also come from env (BIZUNO_DOCS_USER, BIZUNO_DOCS_PASS,
 * BIZUNO_DOCS_SITE) which is the CI-friendly path.
 *
 * Run with --dry-run first to preview. Run with --status=draft to include
 * drafts (default is published-only).
 *
 * @author     PhreeSoft <support@phreesoft.com>
 * @copyright  2008-2026, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-05-19 (auth: switched from hand-built Authorization header to CURLOPT_USERPWD so the script behaves byte-for-byte like `curl --user`; survives 301 redirects + sidesteps Apache/mod_security auth-header munging)
 * @filesource /bin/docs-sync.php
 */

declare(strict_types=1);

// ─── Configuration ─────────────────────────────────────────────────────────

const DEFAULT_SITE  = 'https://bizuno.com';
const CPT_SLUG      = 'docs';          // BetterDocs custom post type slug
const TAX_SLUG      = 'doc_category';  // BetterDocs category taxonomy slug
const DOCS_DIR      = __DIR__ . '/../docs';
const SYNC_MAP      = __DIR__ . '/../docs/.sync-map.json';
const VENDOR_AUTO   = __DIR__ . '/../vendor/autoload.php';
const HTTP_TIMEOUT  = 30;
const HTTP_RETRIES  = 2;
const HTTP_RETRY_MS = 1500;

// ─── Bootstrap ─────────────────────────────────────────────────────────────

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}
if (!is_file(VENDOR_AUTO)) {
    fwrite(STDERR, "vendor/autoload.php not found. Run `composer install` first.\n");
    exit(1);
}
require VENDOR_AUTO;
if (!class_exists(\Parsedown::class)) {
    fwrite(STDERR, "erusev/parsedown not installed. Run `composer install` (require-dev).\n");
    exit(1);
}

// ─── CLI parsing ───────────────────────────────────────────────────────────

$short = '';
$long  = ['dry-run', 'only:', 'status:', 'site:', 'user:', 'pass:',
          'rebuild-cache', 'init-map', 'help', 'verbose'];
$opts  = getopt($short, $long);

if (isset($opts['help'])) {
    print_usage();
    exit(0);
}

$dryRun       = isset($opts['dry-run']);
$verbose      = isset($opts['verbose']);
$rebuildCache = isset($opts['rebuild-cache']);
$initMap      = isset($opts['init-map']);
$onlyPath     = $opts['only']   ?? '';
$statusFilter = $opts['status'] ?? 'published';

$site = $opts['site'] ?? getenv('BIZUNO_DOCS_SITE') ?: DEFAULT_SITE;
$user = $opts['user'] ?? getenv('BIZUNO_DOCS_USER') ?: '';
$pass = $opts['pass'] ?? getenv('BIZUNO_DOCS_PASS') ?: '';

if (!$dryRun && (empty($user) || empty($pass))) {
    fwrite(STDERR, "ERROR: WP credentials required (--user / --pass or BIZUNO_DOCS_USER / BIZUNO_DOCS_PASS env).\n");
    fwrite(STDERR, "Generate an Application Password in WP admin → Users → Profile.\n");
    exit(1);
}

$client = new WpRestClient($site, $user, $pass, $verbose);
$pdown  = new \Parsedown();
$pdown->setSafeMode(false);  // we author the markdown ourselves, allow raw HTML

$syncMap = load_sync_map();
if ($rebuildCache || $initMap) {
    log_line('• Rebuilding slug → post-ID cache from WP …');
    $syncMap = $dryRun ? $syncMap : rebuild_sync_map($client);
}

// ─── Walk the docs tree ────────────────────────────────────────────────────

$files = discover_docs(DOCS_DIR);
if ($onlyPath !== '') {
    $files = array_filter($files, fn($f) => str_contains($f, $onlyPath));
}

if (!$files) {
    fwrite(STDERR, "No markdown files found under " . DOCS_DIR . "\n");
    exit(1);
}

log_line(sprintf("Found %d candidate file(s). Status filter: %s. Site: %s. Dry-run: %s",
    count($files), $statusFilter, $site, $dryRun ? 'yes' : 'no'));

$categoryCache = [];   // category-slug => term-id
$stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

foreach ($files as $file) {
    $rel = ltrim(substr($file, strlen(realpath(DOCS_DIR))), DIRECTORY_SEPARATOR);
    try {
        $doc = parse_markdown_file($file);
    } catch (\Throwable $e) {
        log_line(sprintf("  ✗ %-60s parse error: %s", $rel, $e->getMessage()));
        $stats['failed']++;
        continue;
    }
    // Filter by status
    if (!matches_status($doc['front']['status'] ?? 'stub', $statusFilter)) {
        if ($verbose) {
            log_line(sprintf("  · %-60s skipped (status=%s)", $rel, $doc['front']['status'] ?? 'stub'));
        }
        $stats['skipped']++;
        continue;
    }
    // Build the slug from file name (NOT full path) so moved files keep identity
    $slug    = derive_slug($file);
    $title   = (string)($doc['front']['title'] ?? '(untitled)');
    $catSlug = derive_category_slug($file);
    $catName = derive_category_name($file);
    $order   = (int)($doc['front']['order'] ?? 0);

    // Render body to HTML; prepend a small metadata block (audience + last-updated)
    $bodyHtml = $pdown->text($doc['body']);
    $bodyHtml = wrap_with_meta($bodyHtml, $doc['front'], $rel);

    if ($dryRun) {
        log_line(sprintf("  ◦ %-60s would sync as '%s' under [%s] (order %d)", $rel, $title, $catName, $order));
        continue;
    }

    try {
        $catId = ensure_category($client, $categoryCache, $catSlug, $catName);
        $postId = $syncMap[$slug] ?? lookup_post_id_by_slug($client, $slug);
        $payload = [
            'title'       => $title,
            'slug'        => $slug,
            'content'     => $bodyHtml,
            'status'      => 'publish',
            'menu_order'  => $order,
            TAX_SLUG      => [$catId],
        ];
        if ($postId) {
            $client->put(CPT_SLUG . '/' . $postId, $payload);
            $stats['updated']++;
            log_line(sprintf("  ✓ %-60s updated  → %s", $rel, $title));
        } else {
            $resp = $client->post(CPT_SLUG, $payload);
            $newId = (int)($resp['id'] ?? 0);
            if ($newId === 0) { throw new \RuntimeException('WP did not return an id on create'); }
            $syncMap[$slug] = $newId;
            $stats['created']++;
            log_line(sprintf("  + %-60s created  → %s  (id=%d)", $rel, $title, $newId));
        }
    } catch (\Throwable $e) {
        log_line(sprintf("  ✗ %-60s sync error: %s", $rel, $e->getMessage()));
        $stats['failed']++;
    }
}

if (!$dryRun) { save_sync_map($syncMap); }

log_line(sprintf("Done — created %d, updated %d, skipped %d, failed %d",
    $stats['created'], $stats['updated'], $stats['skipped'], $stats['failed']));
exit($stats['failed'] > 0 ? 2 : 0);


// ═══ Helpers ════════════════════════════════════════════════════════════════

/**
 * Walk DOCS_DIR for .md files, skipping the master README, contributing guide,
 * and per-section READMEs (those become category descriptions, not docs).
 */
function discover_docs(string $root): array
{
    $root = realpath($root);
    if ($root === false) { return []; }
    $files = [];
    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || !str_ends_with($f->getFilename(), '.md')) { continue; }
        $base = $f->getFilename();
        if (in_array($base, ['README.md', 'CONTRIBUTING-DOCS.md'], true)) { continue; }
        $files[] = $f->getPathname();
    }
    sort($files);
    return $files;
}

/**
 * Parse a single .md file into { front: assoc-array, body: string }.
 * Front matter is a thin YAML subset — we handle the keys we author and
 * accept the rest as raw strings.
 */
function parse_markdown_file(string $path): array
{
    $raw = file_get_contents($path);
    if ($raw === false) { throw new \RuntimeException("unreadable: $path"); }
    if (!str_starts_with($raw, "---\n")) {
        return ['front' => [], 'body' => $raw];
    }
    $end = strpos($raw, "\n---\n", 4);
    if ($end === false) { throw new \RuntimeException("malformed front-matter: $path"); }
    $yaml = substr($raw, 4, $end - 4);
    $body = substr($raw, $end + 5);
    $front = [];
    foreach (preg_split("/\r?\n/", $yaml) as $line) {
        if (trim($line) === '' || str_starts_with(trim($line), '#')) { continue; }
        if (!preg_match('/^([a-zA-Z0-9_-]+):\s*(.*)$/', $line, $m)) { continue; }
        [$key, $val] = [$m[1], trim($m[2])];
        // Array values like  audience: [bookkeeper, admin]
        if (preg_match('/^\[(.*)\]$/', $val, $am)) {
            $front[$key] = array_map('trim', explode(',', $am[1]));
        } elseif ($val === '~' || $val === 'null') {
            $front[$key] = null;
        } else {
            $front[$key] = trim($val, '"\'');
        }
    }
    return ['front' => $front, 'body' => ltrim($body, "\n")];
}

/**
 * Derive a slug from the file path. Uses the file's basename (no numeric
 * prefix, no extension) so a file moved between directories keeps identity.
 *
 *   .../03-daily-workflows/01-quote-so-invoice-payment-deposit.md
 *     → quote-so-invoice-payment-deposit
 *
 * Release-notes files (08-migration-and-upgrade/03-release-notes/7-3-9.md)
 * get a "release-" prefix to avoid colliding with other 7-3-9-named docs.
 */
function derive_slug(string $path): string
{
    $base = basename($path, '.md');
    $base = preg_replace('/^\d+-/', '', $base);    // drop leading "NN-"
    $base = strtolower($base);
    if (str_contains($path, '/03-release-notes/')) {
        $base = 'release-' . $base;
    }
    return $base;
}

/**
 * Derive the category slug from the file's parent directory. Section
 * folders are like `02-core-concepts` → category slug `core-concepts`.
 *
 * Module subfolders (under 04-modules-in-depth/) get a "modules-" prefix to
 * avoid colliding with top-level section slugs.
 */
function derive_category_slug(string $path): string
{
    $dir   = dirname($path);
    $parts = explode(DIRECTORY_SEPARATOR, $dir);
    $name  = end($parts);
    $name  = preg_replace('/^\d+-/', '', $name);
    if (str_contains($dir, '04-modules-in-depth')) {
        $name = 'modules-' . $name;
    }
    return strtolower($name);
}

/**
 * Human-readable category name from the same directory naming convention.
 *   `02-core-concepts` → "Core Concepts"
 *   `04-modules-in-depth/01-phreebooks` → "PhreeBooks"
 */
function derive_category_name(string $path): string
{
    $dir   = dirname($path);
    $parts = explode(DIRECTORY_SEPARATOR, $dir);
    $name  = end($parts);
    $name  = preg_replace('/^\d+-/', '', $name);
    $name  = str_replace('-', ' ', $name);
    // Title-case but keep known acronyms (PhreeForm, PhreeBooks, EDI, CA, PA, COGS, REST, API)
    $name  = ucwords($name);
    $known = ['Phreebooks' => 'PhreeBooks', 'Phreeform' => 'PhreeForm',
              'Edi' => 'EDI', 'Crm' => 'CRM', 'Po' => 'PO', 'Api' => 'API',
              'Rest' => 'REST', 'Cogs' => 'COGS', 'Ca' => 'CA', 'Pa' => 'PA',
              'Ar' => 'AR', 'Ap' => 'AP', 'Gl' => 'GL'];
    $name = strtr($name, $known);
    return $name;
}

/**
 * Wrap rendered HTML with a small "metadata" header so end users see
 * audience tag + last-updated stamp on the published page.
 */
function wrap_with_meta(string $html, array $front, string $rel): string
{
    $audience = isset($front['audience']) && is_array($front['audience'])
        ? implode(', ', $front['audience'])
        : '';
    $updated  = $front['last-updated'] ?? '';
    $meta     = '';
    if ($audience !== '' || $updated !== '') {
        $bits = [];
        if ($audience !== '') { $bits[] = '<strong>Audience:</strong> ' . htmlspecialchars($audience); }
        if ($updated  !== '') { $bits[] = '<strong>Last updated:</strong> ' . htmlspecialchars($updated); }
        $meta  = '<div class="bizuno-docs-meta" style="font-size:0.85em;color:#666;margin-bottom:1em;">'
               . implode(' &middot; ', $bits)
               . ' &middot; <a href="https://github.com/phreesoft/bizuno/edit/main/docs/' . htmlspecialchars($rel) . '">Edit on GitHub</a>'
               . '</div>';
    }
    return $meta . $html;
}

/**
 * Does a doc's status pass the user's filter?
 *   --status=published      → only `published` (default)
 *   --status=review         → `review` + `published`
 *   --status=draft          → `draft` + `review` + `published`
 *   --status=all|stub       → everything including stubs
 */
function matches_status(string $docStatus, string $filter): bool
{
    $order = ['stub' => 0, 'draft' => 1, 'review' => 2, 'published' => 3];
    $cutoff = $order[$filter] ?? 3;
    if ($filter === 'all') { return true; }
    return ($order[$docStatus] ?? 0) >= $cutoff;
}

/**
 * Ensure a BetterDocs category exists with the given slug; return its term ID.
 * Cached per process so we only hit WP once per category.
 */
function ensure_category(WpRestClient $client, array &$cache, string $slug, string $name): int
{
    if (isset($cache[$slug])) { return $cache[$slug]; }
    // Look up by slug
    $resp = $client->get(TAX_SLUG, ['slug' => $slug]);
    if (!empty($resp) && isset($resp[0]['id'])) {
        $cache[$slug] = (int)$resp[0]['id'];
        return $cache[$slug];
    }
    // Doesn't exist — create it
    $resp = $client->post(TAX_SLUG, ['name' => $name, 'slug' => $slug]);
    $id = (int)($resp['id'] ?? 0);
    if ($id === 0) { throw new \RuntimeException("Could not create category '$name'"); }
    $cache[$slug] = $id;
    return $id;
}

/**
 * Look up a doc's WP post ID by its slug. Returns 0 if not found.
 *
 * No `status` filter is passed — WP REST defaults to `publish`, which matches
 * the only status this script ever writes. Passing an explicit `status=draft`
 * or `status=private` is rejected with `rest_invalid_param` unless the
 * requesting user has the cap to see those statuses on the CPT — and we
 * don't want to require that cap just to look something up.
 */
function lookup_post_id_by_slug(WpRestClient $client, string $slug): int
{
    $resp = $client->get(CPT_SLUG, ['slug' => $slug]);
    return isset($resp[0]['id']) ? (int)$resp[0]['id'] : 0;
}

/**
 * Load the local sync-map cache (slug → WP post ID).
 */
function load_sync_map(): array
{
    if (!is_file(SYNC_MAP)) { return []; }
    $raw = file_get_contents(SYNC_MAP);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Persist the sync map to disk, sorted for clean git diffs.
 */
function save_sync_map(array $map): void
{
    ksort($map);
    file_put_contents(SYNC_MAP, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

/**
 * Rebuild the sync map by paging through every doc in WP and indexing by slug.
 * Restricted to `publish` (WP REST default) — see lookup_post_id_by_slug for why.
 */
function rebuild_sync_map(WpRestClient $client): array
{
    $map  = [];
    $page = 1;
    while (true) {
        $resp = $client->get(CPT_SLUG, ['per_page' => 100, 'page' => $page]);
        if (empty($resp)) { break; }
        foreach ($resp as $row) {
            if (!empty($row['slug']) && !empty($row['id'])) {
                $map[$row['slug']] = (int)$row['id'];
            }
        }
        if (count($resp) < 100) { break; }
        $page++;
    }
    return $map;
}

function log_line(string $msg): void
{
    fwrite(STDOUT, $msg . "\n");
}

function print_usage(): void
{
    echo <<<USAGE
Usage:
  php bin/docs-sync.php [options]

Options:
  --site=<url>          WP site URL (default: BIZUNO_DOCS_SITE env or bizuno.com)
  --user=<username>     WP user (or BIZUNO_DOCS_USER env)
  --pass=<app-password> WP Application Password (or BIZUNO_DOCS_PASS env)
  --status=<level>      Status floor: stub|draft|review|published|all  (default: published)
  --only=<path-substr>  Sync only files whose path contains this substring
  --dry-run             Show what would happen; no API calls
  --rebuild-cache       Re-query WP to rebuild slug → post-ID map
  --init-map            Alias for --rebuild-cache (first-time setup)
  --verbose             Show skipped-file diagnostics
  --help                Show this message

Auth:
  Generate a WP Application Password (WP admin → Users → Profile → Application
  Passwords). Pass it with --pass or set BIZUNO_DOCS_PASS. Don't commit it.

Examples:
  # First-time setup: discover existing docs, build the slug map
  php bin/docs-sync.php --init-map --dry-run --user=$USER --pass=$PASS

  # Routine publish run (in CI)
  BIZUNO_DOCS_USER=ci BIZUNO_DOCS_PASS=xxx php bin/docs-sync.php

  # Push drafts to a staging site
  php bin/docs-sync.php --site=https://staging.bizuno.com --status=draft

USAGE;
}


// ═══ WP REST client ════════════════════════════════════════════════════════

/**
 * Minimal WP REST client with retry, JSON encoding, basic-auth header,
 * and clear error reporting. Uses cURL (universally available on PHP hosts).
 */
final class WpRestClient
{
    private string $site;
    private string $userPwd;
    private bool $verbose;

    public function __construct(string $site, string $user, string $pass, bool $verbose = false)
    {
        $this->site    = rtrim($site, '/');
        // Store credentials raw and let libcurl build/send the Authorization header
        // via CURLOPT_USERPWD. This matches `curl --user user:pass` behavior exactly,
        // including survival across 301 redirects and avoiding the occasional
        // Apache/mod_security oddity where a hand-rolled Authorization header is
        // stripped/munged before WP sees it (observed on bizuno.com during initial
        // bootstrap: hand-rolled header POSTs 401'd while curl --user POSTs 201'd).
        $this->userPwd = $user . ':' . $pass;
        $this->verbose = $verbose;
    }

    public function get(string $endpoint, array $query = []): array
    {
        $url = $this->urlFor($endpoint) . ($query ? '?' . http_build_query($query) : '');
        return $this->request('GET', $url);
    }

    public function post(string $endpoint, array $payload): array
    {
        return $this->request('POST', $this->urlFor($endpoint), $payload);
    }

    public function put(string $endpoint, array $payload): array
    {
        return $this->request('PUT', $this->urlFor($endpoint), $payload);
    }

    private function urlFor(string $endpoint): string
    {
        return $this->site . '/wp-json/wp/v2/' . ltrim($endpoint, '/');
    }

    private function request(string $method, string $url, ?array $payload = null): array
    {
        $attempt = 0;
        $lastErr = '';
        while ($attempt <= HTTP_RETRIES) {
            $ch = curl_init($url);
            $headers = ['Accept: application/json'];
            if ($payload !== null) { $headers[] = 'Content-Type: application/json'; }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER    => true,
                CURLOPT_TIMEOUT           => HTTP_TIMEOUT,
                CURLOPT_CUSTOMREQUEST     => $method,
                CURLOPT_FOLLOWLOCATION    => true,
                CURLOPT_HTTPHEADER        => $headers,
                CURLOPT_USERPWD           => $this->userPwd,
                CURLOPT_HTTPAUTH          => CURLAUTH_BASIC,
                // Preserve auth across redirects that change host (e.g. bizuno.com → www.bizuno.com).
                // Default libcurl behavior drops the credential on cross-host redirects for safety;
                // we override because we explicitly trust the configured site URL.
                CURLOPT_UNRESTRICTED_AUTH => true,
            ]);
            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            }
            $raw  = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err  = curl_error($ch);
            // curl_close() is a deprecated no-op since PHP 8.0 (removed effect)
            // and emits a deprecation notice on PHP 8.5+; the handle frees itself
            // when $ch goes out of scope.
            if ($this->verbose) {
                fwrite(STDERR, sprintf("    %s %s → %d\n", $method, $url, $code));
            }
            // Transient errors: retry
            if ($err !== '' || $code === 0 || $code >= 500) {
                $lastErr = $err !== '' ? $err : "HTTP $code";
                $attempt++;
                if ($attempt <= HTTP_RETRIES) {
                    usleep(HTTP_RETRY_MS * 1000);
                    continue;
                }
                throw new \RuntimeException("$method $url failed: $lastErr");
            }
            if ($code >= 400) {
                $msg = "HTTP $code on $method $url";
                $data = json_decode($raw, true);
                if (isset($data['message'])) { $msg .= ': ' . $data['message']; }
                if (isset($data['code']))    { $msg .= ' [' . $data['code'] . ']'; }
                throw new \RuntimeException($msg);
            }
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        throw new \RuntimeException("$method $url exhausted retries: $lastErr");
    }
}
