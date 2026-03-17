<?php
declare(strict_types=1);

/**
 * NSC global options bootstrapper (header & footer).
 *
 * Usage:
 *   http://localhost/nsc/create-nsc-global-options.php?token=nsc-global-options-2026
 *
 * This script does not load the theme (to avoid double-loading plugins). It
 * redirects to your site with the same token so the theme can run the setup
 * in a normal request. Menus and ACF options (NSCHeader, NSCFooter) are set
 * by the theme when you visit that URL.
 *
 * - Main navigation menu (navigation_main)
 * - Footer sitemap menu (sitemap_footer)
 * - Contact offices, legal links, social links, company info, header labels
 */

$requiredToken = 'nsc-global-options-2026';
$providedToken = isset($_GET['token']) ? (string) $_GET['token'] : '';

if ($providedToken !== $requiredToken) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden.\n";
    echo "Use: ?token={$requiredToken}\n";
    exit;
}

$wpLoadPath = __DIR__ . '/wp-load.php';
if (!file_exists($wpLoadPath)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "wp-load.php not found at: {$wpLoadPath}\n";
    exit;
}

require_once $wpLoadPath;

$url = home_url('/?' . http_build_query([
    'nsc_run_global_options' => '1',
    'token' => $requiredToken,
]));

header('Location: ' . $url, true, 302);
exit;
