<?php

declare(strict_types=1);

/**
 * NSC nav menu bootstrapper (header + footer sitemap).
 *
 * Usage:
 *   http://localhost/nsc/create-nsc-menus.php?token=nsc-create-menus-2026
 *   http://localhost/nsc/create-nsc-menus.php?token=nsc-create-menus-2026&rebuild=1
 *   http://localhost/nsc/create-nsc-menus.php?token=nsc-create-menus-2026&seed_lang=ja
 *   http://localhost/nsc/create-nsc-menus.php?token=nsc-create-menus-2026&seed_lang=all
 *
 * Notes:
 * - Uses the same page slugs as create-nsc-pages.php (home, about, ai, our-services, technology-apabilities,
 *   blogs, career, case-studies, contact). Run create-nsc-pages.php first so pages exist.
 * - Main nav matches desktop header: Home, About Us, AI, What We Do ▾ (Our Services, Technology Capabilities),
 *   Blog, Careers, Case Studies, Contact Us (CSS classes: highlight, no-link-cursor, contact-btn).
 * - Footer sitemap menu order: Home, About, What We Do ▾ (children), Careers, Blog, Case Studies — same
 *   top-level order as the static HTML footer columns.
 * - Default run (no seed_lang): builds “Main Navigation” + “Footer Sitemap” for the Polylang default language,
 *   assigns theme locations (Main → navigation_main; Footer sitemap menu → sitemap_footer + navigation_footer),
 *   syncs those IDs into Polylang nav_menus for every language (same term; Polylang rewrites page links).
 * - seed_lang / seed_lang=all: builds per-language menus (e.g. “Main Navigation (de)”) with translated titles
 *   and page IDs; sets Polylang locations per locale: navigation_main, sitemap_footer, and navigation_footer.
 * - rebuild=1: removes all items in the target menu(s) before seeding. Without it, only items marked by a
 *   previous seed (_nsc_seeded) are removed and replaced.
 */

$requiredToken = 'nsc-create-menus-2026';
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

if (!function_exists('wp_update_nav_menu_item')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "WordPress bootstrap failed.\n";
    exit;
}

$nscSeedPolylang = get_template_directory() . '/inc/nscSeedPolylang.php';
if (is_readable($nscSeedPolylang)) {
    require_once $nscSeedPolylang;
}
if (function_exists('nsc_seed_bootstrap_acf_polylang_default_language')) {
    nsc_seed_bootstrap_acf_polylang_default_language();
}

$nscSeedMenus = get_template_directory() . '/inc/nscSeedMenus.php';
if (is_readable($nscSeedMenus)) {
    require_once $nscSeedMenus;
}

if (!function_exists('nsc_seed_menus_run')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "nsc_seed_menus_run not found. Ensure wp-content/themes/NSC-Software/inc/nscSeedMenus.php exists.\n";
    exit;
}

$rebuild = isset($_GET['rebuild']) && (string) $_GET['rebuild'] === '1';
$results = nsc_seed_menus_run(['rebuild' => $rebuild]);

header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html><head><meta charset="utf-8"><title>NSC Menus Setup</title>';
echo '<style>body{font-family:Arial,sans-serif;padding:24px}table{border-collapse:collapse;width:100%;max-width:900px}th,td{border:1px solid #ddd;padding:8px}th{background:#f7f7f7;text-align:left}.ok{color:#0a7f2e}.error{color:#b00020}</style>';
echo '</head><body>';
echo '<h1>NSC Menus Setup</h1>';
echo '<p>Seeded <strong>navigation_main</strong> (header) and the footer sitemap tree as <strong>sitemap_footer</strong> + <strong>navigation_footer</strong> (same menu term for both — NSC footer column + sitemap) from page slugs in <code>create-nsc-pages.php</code>.';
echo ' With Polylang, assignments follow each language (e.g. Navigation Main English / Deutsch, Footer Sitemap + Navigation Footer per locale).';
echo ' Optional <code>rebuild=1</code> clears target menu items first. Optional <code>seed_lang</code> / <code>seed_lang=all</code> mirrors the page seeder.</p>';
echo '<table><thead><tr><th>Scope</th><th>Field</th><th>Status</th><th>Details</th></tr></thead><tbody>';
foreach ($results as $row) {
    $statusClass = $row['status'] === 'error' ? 'error' : 'ok';
    echo '<tr>';
    echo '<td>' . esc_html($row['scope']) . '</td>';
    echo '<td>' . esc_html($row['field']) . '</td>';
    echo '<td class="' . esc_attr($statusClass) . '">' . esc_html($row['status']) . '</td>';
    echo '<td>' . esc_html($row['message']) . '</td>';
    echo '</tr>';
}
echo '</tbody></table></body></html>';
