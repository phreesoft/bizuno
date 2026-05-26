#!/usr/bin/env php
<?php
/**
 * reset-bizuno-password.php — emergency password reset for Bizuno
 *
 * Use this when normal "lost password" recovery is unavailable:
 *   - mail/SMTP not configured on a fresh install
 *   - BIZUNO_KEY mismatch after a cross-server DB restore (encrypted mail
 *     creds become unreadable, lost-password emails can't send)
 *   - admin email is unreachable, locked-out scenarios
 *
 * Bypasses Bizuno's web auth entirely. Reads portalCFG.php to find the
 * DB connection and current BIZUNO_KEY, looks up the user by email,
 * and writes a fresh password hash directly into contacts_meta with
 * the same `password_hash(hash_hmac('sha256', $pw, BIZUNO_KEY), …)`
 * scheme Bizuno's login validator uses.
 *
 * USAGE (run from the Bizuno install root — wherever portalCFG.php is):
 *
 *   php bin/reset-bizuno-password.php <email> <new-password>
 *
 * Example:
 *
 *   php bin/reset-bizuno-password.php admin@example.com TempPass-2026!
 *
 * After it succeeds, log in via the web UI and change the password to
 * something only you know via Bizuno's normal profile screen. Then
 * DELETE this script from production — any user with shell access to
 * portalCFG.php and the DB can use it to take over any account.
 */

declare(strict_types=1);

// ─── arg parsing ──────────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "ERROR: must be run from the command line, not via HTTP.\n");
    exit(1);
}

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php " . basename(__FILE__) . " <email> <new-password>\n");
    fwrite(STDERR, "\nExample:\n  php " . basename(__FILE__) . " admin@example.com TempPass-2026!\n");
    exit(1);
}

$email   = $argv[1];
$newPass = $argv[2];

// Sanity-check the email format before doing anything destructive
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "ERROR: '$email' doesn't look like a valid email address.\n");
    exit(1);
}
if (strlen($newPass) < 8) {
    fwrite(STDERR, "ERROR: password must be at least 8 characters.\n");
    exit(1);
}

// ─── locate portalCFG.php ─────────────────────────────────────────────────
// Walk up from the script location looking for portalCFG.php. Lets the
// script work whether it lives in bin/ or copied to the install root.
$candidates = [
    getcwd() . '/portalCFG.php',
    __DIR__ . '/../portalCFG.php',
    __DIR__ . '/portalCFG.php',
];
$cfg = null;
foreach ($candidates as $path) {
    if (file_exists($path)) { $cfg = realpath($path); break; }
}
if (!$cfg) {
    fwrite(STDERR, "ERROR: portalCFG.php not found in any of:\n  - " . implode("\n  - ", $candidates) . "\n");
    fwrite(STDERR, "Run this script from the Bizuno install root (the dir containing portalCFG.php).\n");
    exit(1);
}

// ─── synthesize web-request $_SERVER context for CLI use ──────────────────
// Bizuno's portalCFG.php derives filesystem paths from $_SERVER['DOCUMENT_ROOT']
// (e.g. BIZUNO_FS_PORTAL = $_SERVER['DOCUMENT_ROOT'].'/') and URL constants
// from $_SERVER['HTTP_HOST']. From CLI those are unset, so the require chain
// fails on "/src/bizunoCFG.php" not found. Synthesize them from the install
// directory before requiring portalCFG.php — Bizuno's `if(!defined())`
// guards then produce sensible filesystem paths.
$installDir = dirname($cfg);
$_SERVER['DOCUMENT_ROOT'] = $installDir;
$_SERVER['HTTP_HOST']     = 'localhost';
$_SERVER['HTTPS']         = '';
$_SERVER['REQUEST_URI']   = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

// Suppress NOTICE/WARNING from CLI-context idiosyncrasies during the require.
// We only care about FATAL — anything less is web-oriented bootstrap noise.
$prevErrorReporting = error_reporting(E_ERROR | E_PARSE);
require $cfg;
error_reporting($prevErrorReporting);

if (!defined('BIZUNO_KEY') || !defined('BIZUNO_DB_CREDS') || !defined('BIZUNO_DB_PREFIX')) {
    fwrite(STDERR, "ERROR: portalCFG.php loaded but expected constants (BIZUNO_KEY / BIZUNO_DB_CREDS / BIZUNO_DB_PREFIX) aren't defined.\n");
    exit(1);
}

$creds  = BIZUNO_DB_CREDS;
$prefix = BIZUNO_DB_PREFIX;

echo "Using portalCFG.php at $cfg\n";
echo "  BIZUNO_KEY (first 4 chars): " . substr(BIZUNO_KEY, 0, 4) . "…\n";
echo "  DB host/name:               {$creds['host']} / {$creds['name']}\n";
echo "  Table prefix:               $prefix\n";
echo "\n";

// ─── DB connection ────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host={$creds['host']};dbname={$creds['name']};charset=utf8mb4",
        $creds['user'],
        $creds['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "ERROR: DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

// ─── look up the user ─────────────────────────────────────────────────────
// Bizuno's user records: contacts table with ctype_u='1', email column matches.
$stmt = $pdo->prepare("
    SELECT id, primary_name, short_name, inactive
    FROM `{$prefix}contacts`
    WHERE ctype_u = '1' AND email = :email
    LIMIT 1
");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    fwrite(STDERR, "ERROR: no user found with email '$email'.\n");
    fwrite(STDERR, "Check the email matches exactly (case-sensitive in some Bizuno installs).\n");
    fwrite(STDERR, "Listing all user-type contacts for reference:\n");
    $list = $pdo->query("SELECT id, email, primary_name, short_name FROM `{$prefix}contacts` WHERE ctype_u='1' ORDER BY id");
    foreach ($list as $row) {
        fwrite(STDERR, "  id={$row['id']}  email={$row['email']}  name={$row['primary_name']}  login={$row['short_name']}\n");
    }
    exit(1);
}

if (!empty($user['inactive'])) {
    fwrite(STDERR, "WARNING: user is marked inactive. Resetting password but you'll need to reactivate the account in admin UI to log in.\n");
}

echo "Found user:\n";
echo "  id:           {$user['id']}\n";
echo "  primary_name: {$user['primary_name']}\n";
echo "  short_name:   {$user['short_name']}  (this is the login username)\n";
echo "\n";

// ─── compute the new hash (same algorithm as src/portal/viewAuth.php) ─────
$peppered = hash_hmac('sha256', $newPass, BIZUNO_KEY);
$hash = password_hash($peppered, PASSWORD_DEFAULT);

if (!$hash) {
    fwrite(STDERR, "ERROR: password_hash() returned false. Out of memory?\n");
    exit(1);
}

// ─── upsert into contacts_meta ────────────────────────────────────────────
// Bizuno stores per-user secrets in <prefix>contacts_meta keyed on ref_id
// (the contact's id) and meta_key. If a 'user_auth' row already exists,
// update its meta_value; otherwise insert.
$check = $pdo->prepare("
    SELECT id FROM `{$prefix}contacts_meta`
    WHERE ref_id = :ref AND meta_key = 'user_auth'
    LIMIT 1
");
$check->execute([':ref' => $user['id']]);
$existing = $check->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $upd = $pdo->prepare("
        UPDATE `{$prefix}contacts_meta`
        SET meta_value = :v
        WHERE id = :id
    ");
    $upd->execute([':v' => $hash, ':id' => $existing['id']]);
    echo "✓ Updated existing user_auth row (id={$existing['id']}).\n";
} else {
    $ins = $pdo->prepare("
        INSERT INTO `{$prefix}contacts_meta` (ref_id, meta_key, meta_value)
        VALUES (:ref, 'user_auth', :v)
    ");
    $ins->execute([':ref' => $user['id'], ':v' => $hash]);
    echo "✓ Inserted new user_auth row (id=" . $pdo->lastInsertId() . ").\n";
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  PASSWORD RESET SUCCESSFUL\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  Log in with:\n";
echo "    email:    $email\n";
echo "    password: $newPass\n";
echo "\n";
echo "  Next steps:\n";
echo "   1. Open the Bizuno login page in your browser.\n";
echo "   2. Sign in with the credentials above.\n";
echo "   3. Change the password from the Bizuno profile screen.\n";
echo "   4. DELETE this script (`rm $cfg/" . basename(__FILE__) . "` or similar).\n";
echo "═══════════════════════════════════════════════════════════════\n";
