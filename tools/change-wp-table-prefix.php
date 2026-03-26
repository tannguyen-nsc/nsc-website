<?php

declare(strict_types=1);

/**
 * Change WordPress database table prefix (single-site installs).
 *
 * Run from the command line only. Back up the database and wp-config.php first.
 *
 * Usage:
 *   php tools/change-wp-table-prefix.php --new=wp_
 *   php tools/change-wp-table-prefix.php --new=wp_ --path="D:\laragon\www\nsc"
 *   php tools/change-wp-table-prefix.php --new=wp_ --dry-run
 *
 * Options:
 *   --new=PREFIX    New table prefix (e.g. wp_ or custom_). Required.
 *   --path=DIR      WordPress root (directory containing wp-load.php). Default: parent of this script's directory.
 *   --dry-run       Print planned actions; do not change the database or wp-config.php.
 *   --help          Show this text.
 *
 * Not supported: WordPress multisite (network installs).
 *
 * Logic is shared with wp-content/themes/NSC-Software/inc/nscMigrateTablePrefix.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$longopts = [
    'new:',
    'path:',
    'dry-run',
    'help',
];
$opts = getopt('', $longopts);

if (isset($opts['help']) || $opts === false) {
    $self = basename(__FILE__);
    fwrite(STDOUT, <<<TXT
Usage: php {$self} --new=PREFIX [--path=WP_ROOT] [--dry-run]

  --new=PREFIX   New table prefix (letters, numbers, underscores only).
  --path=DIR     WordPress root (where wp-load.php lives). Default: ../ from this script.
  --dry-run      Show what would happen; no writes.
  --help         This message.

Back up your database and wp-config.php before running. Multisite is not supported.

TXT);
    exit(isset($opts['help']) ? 0 : 1);
}

if (empty($opts['new'])) {
    fwrite(STDERR, "Error: --new=PREFIX is required.\n");
    exit(1);
}

$wpRoot = isset($opts['path'])
    ? rtrim(str_replace('\\', '/', (string) $opts['path']), '/')
    : dirname(__DIR__);
$wpLoad = $wpRoot . '/wp-load.php';
if (!is_readable($wpLoad)) {
    fwrite(STDERR, "Error: wp-load.php not found at: {$wpLoad}\n");
    exit(1);
}

$dryRun = isset($opts['dry-run']);

require_once $wpLoad;

$newPrefix = \NscSoftware\WpTablePrefix\normalize_prefix_input((string) $opts['new']);
if ($newPrefix === '') {
    fwrite(STDERR, "Error: invalid prefix (use letters, numbers, underscores; ends with _).\n");
    exit(1);
}

global $wpdb;
$oldPrefix = $wpdb->prefix;

fwrite(STDOUT, "WordPress root: {$wpRoot}\n");
fwrite(STDOUT, "Current prefix: {$oldPrefix}\n");
fwrite(STDOUT, "Target prefix: {$newPrefix}\n");
fwrite(STDOUT, ($dryRun ? "Dry run — no changes.\n" : "Migrating…\n"));

$result = \NscSoftware\WpTablePrefix\migrate($newPrefix, $wpRoot, $dryRun);

if ($result instanceof \WP_Error) {
    fwrite(STDERR, 'Error: ' . $result->get_error_message() . "\n");
    exit(1);
}

fwrite(STDOUT, $dryRun ? "Dry run completed — no changes made.\n" : "Done. Verify the site; flush permalinks (Settings → Permalinks → Save) if needed.\n");
exit(0);
