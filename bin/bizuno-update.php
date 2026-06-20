#!/usr/bin/env php
<?php
/**
 * bizuno-update.php — auto-updater for a Bizuno installation
 *
 * Pulls the latest *production* release of phreesoft/bizuno from GitHub
 * (the releases/latest endpoint excludes drafts and pre-releases), backs up
 * the current program library, swaps the new files into place with an
 * atomic directory rename, and flags the business cache for a rebuild so any
 * schema migrations run on the next web request.
 *
 * This is a command-line alternative to the in-browser updater at Settings →
 * Bizuno → Software Updates (which applies updates directly, in-request, behind
 * a progress modal with the site held in maintenance mode). Use this script for
 * SSH/cron-driven updates, or when the web user can't write to the library's
 * parent directory. `--cron` applies a data/updates/pending.json marker if one
 * is present and otherwise exits 0 quietly.
 *
 * USAGE (run from the Bizuno install root — wherever portalCFG.php is):
 *
 *   php bin/bizuno-update.php --check              # report installed vs latest, change nothing
 *   php bin/bizuno-update.php --apply              # update to the latest production release (prompts)
 *   php bin/bizuno-update.php --apply --yes        # ...without the confirmation prompt
 *   php bin/bizuno-update.php --version=7.4.5 --apply
 *   php bin/bizuno-update.php --cron               # apply a UI-queued update if one is pending, else exit quietly
 *
 * FLAGS
 *   --check            Show versions and exit (no changes).
 *   --apply            Perform the update.
 *   --cron             Process data/updates/pending.json if present; otherwise exit 0 silently.
 *                      Intended for a system crontab line or Bizuno's funnelCron mechanism.
 *   --version=X.Y.Z    Pin a specific release tag instead of "latest".
 *   --force            Update even if the target is not newer than the installed version.
 *   --yes              Skip the interactive confirmation prompt.
 *   --quiet            Suppress normal output (errors still print). Implied by --cron.
 *   --token=GHTOKEN    GitHub token for the API (also read from env GITHUB_TOKEN). Optional;
 *                      raises the anonymous 60/hr rate limit.
 *
 * SAFETY
 *   The previous library is kept next to the new one as `<lib>.old-<timestamp>`
 *   for instant rollback. If any step of the swap fails the original is restored
 *   automatically. Always test the site after an update; if something is wrong,
 *   rename the `.old-<timestamp>` directory back over the library.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "ERROR: must be run from the command line, not via HTTP.\n");
    exit(1);
}

const GH_OWNER = 'phreesoft';
const GH_REPO  = 'bizuno';

// ─── arg parsing ──────────────────────────────────────────────────────────
$opt = ['check'=>false,'apply'=>false,'cron'=>false,'force'=>false,'yes'=>false,'quiet'=>false,'version'=>'','token'=>(string)getenv('GITHUB_TOKEN')];
foreach (array_slice($argv, 1) as $arg) {
    if     ($arg === '--check')  { $opt['check'] = true; }
    elseif ($arg === '--apply')  { $opt['apply'] = true; }
    elseif ($arg === '--cron')   { $opt['cron']  = true; $opt['quiet'] = true; }
    elseif ($arg === '--force')  { $opt['force'] = true; }
    elseif ($arg === '--yes')    { $opt['yes']   = true; }
    elseif ($arg === '--quiet')  { $opt['quiet'] = true; }
    elseif (strpos($arg, '--version=') === 0) { $opt['version'] = ltrim(substr($arg, 10), 'vV'); }
    elseif (strpos($arg, '--token=')   === 0) { $opt['token']   = substr($arg, 8); }
    else { fwrite(STDERR, "ERROR: unknown argument '$arg'. Run with no arguments for usage.\n"); exit(1); }
}
if (!$opt['check'] && !$opt['apply'] && !$opt['cron']) {
    fwrite(STDERR, "Usage: php ".basename(__FILE__)." [--check | --apply | --cron] [--version=X.Y.Z] [--force] [--yes] [--quiet]\n");
    fwrite(STDERR, "See the header of this file for details.\n");
    exit(1);
}

function out(string $msg): void { global $opt; if (!$opt['quiet']) { echo $msg."\n"; } }
function fail(string $msg): void { fwrite(STDERR, "ERROR: $msg\n"); exit(1); }

// ─── locate and load portalCFG.php (for BIZUNO_FS_LIBRARY / BIZUNO_DATA / DB) ──
$candidates = [getcwd().'/portalCFG.php', __DIR__.'/../portalCFG.php', __DIR__.'/portalCFG.php'];
$cfg = null;
foreach ($candidates as $path) { if (file_exists($path)) { $cfg = realpath($path); break; } }
if (!$cfg) { fail("portalCFG.php not found. Run this from the Bizuno install root.\n  Looked in:\n  - ".implode("\n  - ", $candidates)); }

// Bizuno's portalCFG.php derives paths from $_SERVER; synthesize a request context for CLI.
$installDir = dirname($cfg);
$_SERVER['DOCUMENT_ROOT']  = $installDir;
$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['HTTPS']          = '';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$prev = error_reporting(E_ERROR | E_PARSE);
require $cfg;
error_reporting($prev);

if (!defined('BIZUNO_FS_LIBRARY') || !defined('BIZUNO_DATA')) {
    fail("portalCFG.php loaded but BIZUNO_FS_LIBRARY / BIZUNO_DATA are not defined.");
}
$libDir  = rtrim(BIZUNO_FS_LIBRARY, '/\\');
$dataDir = rtrim(BIZUNO_DATA, '/\\');
$workDir = $dataDir.'/updates';
if (!is_dir($workDir) && !@mkdir($workDir, 0750, true) && !is_dir($workDir)) { fail("cannot create work dir: $workDir"); }

$current = is_file($libDir.'/VERSION') ? trim((string)file_get_contents($libDir.'/VERSION'))
         : (defined('MODULE_BIZUNO_VERSION') ? MODULE_BIZUNO_VERSION : '0.0.0');
out("Installed version: $current");

// ─── determine the target release ─────────────────────────────────────────
$markerFile = $workDir.'/pending.json';
$target = $opt['version'];
$zipUrl = '';

if ($opt['cron']) {
    if (!is_file($markerFile)) { out("No pending update; nothing to do."); exit(0); }
    $marker = json_decode((string)file_get_contents($markerFile), true);
    if (empty($marker['target'])) { @unlink($markerFile); fail("pending.json is malformed; removed it."); }
    $target = ltrim((string)$marker['target'], 'vV');
    $zipUrl = (string)($marker['zipball_url'] ?? '');
    out("Processing UI-queued update to $target");
}

if ($target === '') { // resolve "latest production release" from GitHub
    $rel = ghApi('https://api.github.com/repos/'.GH_OWNER.'/'.GH_REPO.'/releases/latest', $opt['token']);
    if (empty($rel['tag_name'])) { fail("could not read the latest release from GitHub."); }
    $target = ltrim((string)$rel['tag_name'], 'vV');
    $zipUrl = (string)($rel['zipball_url'] ?? '');
}
if ($zipUrl === '') { $zipUrl = 'https://api.github.com/repos/'.GH_OWNER.'/'.GH_REPO.'/zipball/v'.$target; }
out("Latest/target version: $target");

$newer = version_compare($target, $current, '>');
if (!$newer && !$opt['force']) {
    out($target === $current ? "Already up to date." : "Installed version is newer than the target; nothing to do (use --force to override).");
    if ($opt['cron']) { @unlink($markerFile); } // clear a now-stale queue entry
    exit(0);
}
if ($opt['check']) { out($newer ? "An update is available: $current -> $target" : "No update needed."); exit(0); }

// ─── confirm ──────────────────────────────────────────────────────────────
if (!$opt['yes'] && !$opt['quiet']) {
    echo "About to update Bizuno from $current to $target.\n";
    echo "The current library will be backed up next to itself before any files change.\n";
    echo "Continue? [y/N]: ";
    $ans = strtolower(trim((string)fgets(STDIN)));
    if ($ans !== 'y' && $ans !== 'yes') { out("Aborted."); exit(0); }
}

$ts      = date('Ymd-His');
$logFile = $workDir.'/update.log';
logLine($logFile, "=== update $current -> $target started ===");

// ─── download the release zipball ─────────────────────────────────────────
$zipPath = $workDir."/bizuno-$target-$ts.zip";
out("Downloading $zipUrl ...");
if (!ghDownload($zipUrl, $zipPath, $opt['token'])) { logLine($logFile, "download failed"); fail("download failed from $zipUrl"); }
if (filesize($zipPath) < 1024) { @unlink($zipPath); fail("downloaded archive is too small to be valid."); }

// ─── extract & locate the new src/ ────────────────────────────────────────
$stageDir = $workDir."/stage-$ts";
out("Extracting ...");
$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) { fail("could not open the downloaded zip archive."); }
@mkdir($stageDir, 0750, true);
$zip->extractTo($stageDir);
$zip->close();

// GitHub zipballs wrap everything in a single top folder: <owner>-<repo>-<sha>/
$tops = array_values(array_filter(glob($stageDir.'/*'), 'is_dir'));
if (count($tops) !== 1) { rrmdir($stageDir); fail("unexpected archive layout (expected one top-level folder)."); }
$newSrc = $tops[0].'/src';
if (!is_dir($newSrc) || !is_file($newSrc.'/bizunoCFG.php') || !is_file($newSrc.'/VERSION')) {
    rrmdir($stageDir);
    fail("the release archive does not contain a valid src/ library — aborting before touching the installation.");
}
$stagedVer = trim((string)file_get_contents($newSrc.'/VERSION'));
out("Staged library version: $stagedVer");

// ─── pre-flight: we must be able to rename within the library's parent ────
$parent = dirname($libDir);
$base   = basename($libDir);
if (!is_writable($parent)) { rrmdir($stageDir); fail("no write permission on $parent — cannot swap the library. Run as the web/site user."); }

// Copy the staged src into a sibling of the live library so the final swap is a
// same-filesystem rename (data/ may live on a different mount than the library).
$newDir = $parent.'/.'.$base.'-new-'.$ts;
$oldDir = $parent.'/'.$base.'.old-'.$ts;
out("Preparing new library at $newDir ...");
if (!rcopy($newSrc, $newDir)) { rrmdir($stageDir); rrmdir($newDir); fail("failed staging the new library into place."); }

// ─── the swap (with automatic rollback) ───────────────────────────────────
out("Swapping library into place ...");
if (!@rename($libDir, $oldDir)) { rrmdir($stageDir); rrmdir($newDir); fail("could not move the current library aside."); }
if (!@rename($newDir, $libDir)) {
    @rename($oldDir, $libDir); // put it back exactly as it was
    rrmdir($stageDir); rrmdir($newDir);
    logLine($logFile, "swap failed, rolled back");
    fail("could not move the new library into place — rolled back to $current.");
}
logLine($logFile, "swapped $current -> $stagedVer (backup: $oldDir)");
out("Library updated. Backup of the previous version: $oldDir");

// ─── flag the business cache to rebuild (runs schema migrations on next hit) ──
clearBizCache($logFile);

// ─── cleanup ──────────────────────────────────────────────────────────────
rrmdir($stageDir);
@unlink($zipPath);
if (is_file($markerFile)) { @unlink($markerFile); }
logLine($logFile, "=== update complete: now $stagedVer ===");

out("");
out("Done. Bizuno is now version $stagedVer.");
out("Reload the app and confirm it works. If anything is wrong, restore the backup:");
out("  rm -rf ".escapeshellarg($libDir)." && mv ".escapeshellarg($oldDir)." ".escapeshellarg($libDir));
exit(0);


// ─────────────────────────────── helpers ──────────────────────────────────

/** GET a GitHub API URL and return the decoded JSON (or [] on failure). */
function ghApi(string $url, string $token): array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'];
    if ($token !== '') { $headers[] = "Authorization: Bearer $token"; }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => 'bizuno-update/1.0',
        CURLOPT_HTTPHEADER     => $headers]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($body === false || $code >= 400) { return []; }
    $json = json_decode((string)$body, true);
    return is_array($json) ? $json : [];
}

/** Stream a (possibly large, redirecting) download to a local file. */
function ghDownload(string $url, string $dest, string $token): bool
{
    $fp = fopen($dest, 'w');
    if (!$fp) { return false; }
    $ch = curl_init($url);
    $headers = ['Accept: application/vnd.github+json'];
    if ($token !== '') { $headers[] = "Authorization: Bearer $token"; }
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_USERAGENT      => 'bizuno-update/1.0',
        CURLOPT_HTTPHEADER     => $headers]);
    $ok   = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    fclose($fp);
    if ($ok === false || $code >= 400) { @unlink($dest); return false; }
    return true;
}

/** Recursively copy a directory tree. Returns false on any failure. */
function rcopy(string $src, string $dst): bool
{
    if (!is_dir($src)) { return false; }
    if (!is_dir($dst) && !@mkdir($dst, 0750, true) && !is_dir($dst)) { return false; }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST);
    foreach ($items as $item) {
        $sub = substr($item->getPathname(), strlen($src) + 1);
        $to  = $dst.'/'.$sub;
        if ($item->isDir()) { if (!is_dir($to) && !@mkdir($to, 0750, true) && !is_dir($to)) { return false; } }
        else                { if (!@copy($item->getPathname(), $to)) { return false; } }
    }
    return true;
}

/** Recursively delete a directory tree (best effort). */
function rrmdir(string $dir): void
{
    if (!is_dir($dir)) { return; }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
    @rmdir($dir);
}

/** Zero common_meta.bizuno_cache_expires so the next web request rebuilds the cache and runs migrations. */
function clearBizCache(string $logFile): void
{
    if (!defined('BIZUNO_DB_CREDS') || !defined('BIZUNO_DB_PREFIX')) {
        out("Note: DB constants unavailable; clear the business cache from Settings → Bizuno → Tools after updating.");
        return;
    }
    $c = BIZUNO_DB_CREDS;
    try {
        $pdo = new PDO("mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4", $c['user'], $c['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("UPDATE `".BIZUNO_DB_PREFIX."common_meta` SET bizuno_cache_expires = 0");
        out("Business cache flagged for rebuild (migrations run on next request).");
        logLine($logFile, "business cache cleared");
    } catch (Throwable $e) {
        out("Note: could not clear the business cache automatically (".$e->getMessage()."). Do it from Settings → Bizuno → Tools.");
        logLine($logFile, "cache clear failed: ".$e->getMessage());
    }
}

function logLine(string $file, string $msg): void { @file_put_contents($file, date('c')." $msg\n", FILE_APPEND); }
