<?php

/**
 * Run NSC global options setup (menus + header/footer options) when URL has
 * nsc_run_global_options=1&token=nsc-global-options-2026. Runs in a normal
 * request so the theme and ACF are already loaded (avoids double-loading plugins).
 */

namespace NscSoftware\RunGlobalOptions;

$requiredToken = 'nsc-global-options-2026';

add_action('template_redirect', function () use ($requiredToken) {
    if (!isset($_GET['nsc_run_global_options'], $_GET['token']) || $_GET['token'] !== $requiredToken) {
        return;
    }

    $baseUrl = home_url('/');
    $results = [];

    $getPageIdBySlug = function (string $slug): int {
        $page = get_page_by_path($slug, OBJECT, 'page');
        return $page instanceof \WP_Post ? (int) $page->ID : 0;
    };

    $ensureNavMenu = function (string $location, string $menuName) use (&$results): int {
        $locations = get_nav_menu_locations();
        $menuId = (int) ($locations[$location] ?? 0);
        if ($menuId > 0) {
            $menu = wp_get_nav_menu_object($menuId);
            if ($menu instanceof \WP_Term) {
                return $menuId;
            }
        }
        $menus = wp_get_nav_menus();
        foreach ($menus as $menu) {
            if ($menu->name === $menuName) {
                $locations[$location] = $menu->term_id;
                set_theme_mod('nav_menu_locations', $locations);
                return (int) $menu->term_id;
            }
        }
        $id = wp_create_nav_menu($menuName);
        if (is_wp_error($id)) {
            return 0;
        }
        $locations[$location] = $id;
        set_theme_mod('nav_menu_locations', $locations);
        return (int) $id;
    };

    $addNavMenuItem = function (int $menuId, int $pageId, string $label, int $position = 0): int {
        if ($pageId <= 0) {
            return 0;
        }
        $item = [
            'menu-item-object-id' => $pageId,
            'menu-item-object'   => 'page',
            'menu-item-type'     => 'post_type',
            'menu-item-title'    => $label,
            'menu-item-status'   => 'publish',
            'menu-item-position' => $position,
        ];
        $id = wp_update_nav_menu_item($menuId, 0, $item);
        return is_wp_error($id) ? 0 : (int) $id;
    };

    $populateMenuFromPages = function (int $menuId, array $pages) use ($getPageIdBySlug, $addNavMenuItem): int {
        $existing = wp_get_nav_menu_items($menuId);
        $existingUrls = [];
        if (is_array($existing)) {
            foreach ($existing as $item) {
                if (isset($item->url)) {
                    $existingUrls[$item->url] = true;
                }
            }
        }
        $position = 0;
        $added = 0;
        foreach ($pages as $slug => $label) {
            $pageId = $getPageIdBySlug($slug);
            if ($pageId <= 0) {
                continue;
            }
            $url = get_permalink($pageId);
            if (isset($existingUrls[$url])) {
                $position++;
                continue;
            }
            $itemId = $addNavMenuItem($menuId, $pageId, $label, $position);
            if ($itemId > 0) {
                $added++;
                $existingUrls[$url] = true;
            }
            $position++;
        }
        return $added;
    };

    $optionPrefixFooter = 'translatable_NSCFooter_';
    $optionPrefixHeader = 'translatable_NSCHeader_';

    $updateOption = function (string $name, $value, string $prefix = '') use ($optionPrefixFooter, $optionPrefixHeader): bool {
        $key = $prefix ? $prefix . $name : $name;
        return function_exists('update_field') ? (bool) update_field($key, $value, 'option') : (bool) update_option('options_' . $key, $value);
    };

    // Main navigation
    $mainNavPages = [
        'home'             => 'Home',
        'about'            => 'About Us',
        'our-services'     => 'Our Services',
        'our-capabilites'  => 'Technology Capabilities',
        'career'           => 'Careers',
        'blogs'            => 'Blog',
        'case-studies'     => 'Case Studies',
        'contact'          => 'Contact',
    ];
    $mainMenuId = $ensureNavMenu('navigation_main', 'Main Navigation');
    if ($mainMenuId > 0) {
        $added = $populateMenuFromPages($mainMenuId, $mainNavPages);
        $results[] = ['scope' => 'Menu', 'field' => 'Main navigation (navigation_main)', 'status' => 'ok', 'message' => "menu_id={$mainMenuId}, added {$added} items"];
    } else {
        $results[] = ['scope' => 'Menu', 'field' => 'Main navigation', 'status' => 'error', 'message' => 'Could not create or get menu'];
    }

    // Footer sitemap
    $sitemapPages = [
        'home'             => 'Home',
        'about'            => 'About Us',
        'our-services'     => 'Our Services',
        'our-capabilites'  => 'Technology Capabilities',
        'career'           => 'Careers',
        'blogs'            => 'Blog',
        'case-studies'     => 'Case Studies',
    ];
    $sitemapMenuId = $ensureNavMenu('sitemap_footer', 'Footer Sitemap');
    if ($sitemapMenuId > 0) {
        $added = $populateMenuFromPages($sitemapMenuId, $sitemapPages);
        $results[] = ['scope' => 'Menu', 'field' => 'Footer sitemap (sitemap_footer)', 'status' => 'ok', 'message' => "menu_id={$sitemapMenuId}, added {$added} items"];
    } else {
        $results[] = ['scope' => 'Menu', 'field' => 'Footer sitemap', 'status' => 'error', 'message' => 'Could not create or get menu'];
    }

    // NSCFooter options
    $updateOption('companyName', 'NSC Software Co., LTD', $optionPrefixFooter);
    $updateOption('companyDescription', "Vietnam's Premier Software Development & Consulting Company", $optionPrefixFooter);
    $updateOption('businessNumber', '0110524817', $optionPrefixFooter);
    $updateOption('email', 'contact@nscsoftware.com', $optionPrefixFooter);
    $results[] = ['scope' => 'NSCFooter', 'field' => 'Company', 'status' => 'ok', 'message' => 'companyName, companyDescription, businessNumber, email'];

    $offices = [
        ['title' => 'NSC Software Headquarters:', 'address' => "Level 22, PVI Tower, Pham Van Bach, Cau Giay, Hanoi, Vietnam", 'phone' => '(+84) 866 639 497', 'phoneLink' => 'tel:+84866639497'],
        ['title' => 'NSC Software Ho Chi Minh:', 'address' => 'Level 10, Five Star Tower, 28 Bis, Ho Chi Minh, Vietnam', 'phone' => '(+84) 866 639 497', 'phoneLink' => 'tel:+84866639497'],
        ['title' => 'NSC Software USA:', 'address' => '4245 N Central Expy, #490, Dallas, TX, USA 75205', 'phone' => '+1 (713) 428 2289', 'phoneLink' => 'tel:+17134282289'],
        ['title' => 'NSC Software Australia:', 'address' => 'Level 24, Three International Towers, 300 Barangaroo Avenue, Sydney NSW 2000, Australia', 'phone' => '(+61) 0488 860 719', 'phoneLink' => 'tel:+61488860719'],
        ['title' => 'NSC Software Europe:', 'address' => 'Am Hauptbahnhof 16, D-60306 Frankfurt am Main, Germany', 'phone' => '(+49) 170 1633520', 'phoneLink' => 'tel:+491701633520'],
    ];
    $updateOption('offices', $offices, $optionPrefixFooter);
    $results[] = ['scope' => 'NSCFooter', 'field' => 'Offices', 'status' => 'ok', 'message' => count($offices) . ' offices'];

    $updateOption('copyright', 'NSC@2026 All copyrights reserved', $optionPrefixFooter);
    $legalLinks = [
        ['label' => 'Privacy Policy', 'url' => $baseUrl . 'privacy-policy/', 'openInNewTab' => 0],
        ['label' => 'Cookies Policy', 'url' => $baseUrl . 'cookies-policy/', 'openInNewTab' => 0],
        ['label' => 'Terms of Use', 'url' => $baseUrl . 'terms-of-use/', 'openInNewTab' => 0],
    ];
    $updateOption('legalLinks', $legalLinks, $optionPrefixFooter);
    $results[] = ['scope' => 'NSCFooter', 'field' => 'Copyright & legal links', 'status' => 'ok', 'message' => 'copyright + ' . count($legalLinks) . ' legal links'];

    $socialLinks = [
        ['platform' => 'linkedin', 'url' => 'https://www.linkedin.com/company/nscsoftware/', 'ariaLabel' => 'LinkedIn'],
        ['platform' => 'facebook', 'url' => $baseUrl, 'ariaLabel' => 'Facebook'],
    ];
    $updateOption('socialLinks', $socialLinks, $optionPrefixFooter);
    $results[] = ['scope' => 'NSCFooter', 'field' => 'Social links', 'status' => 'ok', 'message' => count($socialLinks) . ' links'];

    if (function_exists('update_field')) {
        update_field($optionPrefixHeader . 'labels', [
            'languageLabel' => 'Language: English',
            'ariaLabel'     => 'Main navigation',
        ], 'option');
    }
    $results[] = ['scope' => 'NSCHeader', 'field' => 'Labels', 'status' => 'ok', 'message' => 'languageLabel, ariaLabel'];

    // Blog categories (Technology, Cultures) for archive filters
    $blogCategories = [
        'Technology' => 'technology',
        'Cultures'   => 'cultures',
    ];
    foreach ($blogCategories as $catName => $catSlug) {
        $exists = term_exists($catSlug, 'category');
        if (!$exists) {
            $t = wp_insert_term($catName, 'category', ['slug' => $catSlug]);
            if (is_wp_error($t)) {
                $results[] = ['scope' => 'Blog', 'field' => 'Category ' . $catName, 'status' => 'error', 'message' => $t->get_error_message()];
            } else {
                $results[] = ['scope' => 'Blog', 'field' => 'Category', 'status' => 'ok', 'message' => 'Created: ' . $catName];
            }
        } else {
            $results[] = ['scope' => 'Blog', 'field' => 'Category', 'status' => 'ok', 'message' => $catName . ' already exists'];
        }
    }

    // Translatable: NSC Blog Single — About the author (dummy content)
    $optionPrefixBlogSingle = 'translatable_NSCBlogSingle_';
    if (function_exists('update_field')) {
        update_field(
            $optionPrefixBlogSingle . 'aboutAuthorContent',
            '<p class="blog-details__author-name">NSC Software Co., LTD</p>'
            . '<p class="blog-details__author-subtitle">Vietnam\'s Premier Software Development and Consulting Company</p>'
            . '<p class="blog-details__author-lead">We\'re Vietnam Premier\'s Software Development &amp; Consulting Company</p>'
            . '<p class="blog-details__author-desc">Combining Vietnam\'s Top 7% IT talents - all senior-level engineers - with AI-enabled delivery, NSC Software helps global enterprises design, build, and scale secure, high-performing, and future-ready software solutions that drive long-term business value.</p>',
            'option'
        );
        update_field(
            $optionPrefixBlogSingle . 'aboutAuthorLink',
            [
                'linkLabel'    => 'LinkedIn Profile',
                'linkUrl'      => 'https://www.linkedin.com/company/nscsoftware/',
                'openInNewTab' => 1,
            ],
            'option'
        );
        // Avatar: leave unset in options UI, or set attachment ID if you add media later
        update_field($optionPrefixBlogSingle . 'aboutAuthorAvatar', false, 'option');

        update_field($optionPrefixBlogSingle . 'connectBoxTitle', 'Need an innovative and reliable tech partner?', 'option');
        update_field($optionPrefixBlogSingle . 'connectBoxButtonLabel', "Let's Connect", 'option');
        update_field($optionPrefixBlogSingle . 'connectBoxButtonUrl', $baseUrl . 'contact/', 'option');
        update_field($optionPrefixBlogSingle . 'connectBoxOpenNewTab', 0, 'option');
        update_field($optionPrefixBlogSingle . 'connectBoxBackground', false, 'option');

        update_field($optionPrefixBlogSingle . 'aboutAuthorHeading', 'About the author', 'option');
        update_field($optionPrefixBlogSingle . 'shareArticleLabel', 'Share article:', 'option');
        update_field($optionPrefixBlogSingle . 'readingTimeSuffixSingular', 'min read', 'option');
        update_field($optionPrefixBlogSingle . 'readingTimeSuffixPlural', 'mins read', 'option');

        update_field($optionPrefixBlogSingle . 'relatedArticlesHeading', 'Related Articles', 'option');
        update_field($optionPrefixBlogSingle . 'relatedPostsLimit', 3, 'option');
    }
    $results[] = ['scope' => 'NSCBlogSingle', 'field' => 'About the author', 'status' => 'ok', 'message' => 'content (blog-details-style) + LinkedIn link (avatar empty — Theme Options → Blog)'];
    $results[] = ['scope' => 'NSCBlogSingle', 'field' => 'Related articles', 'status' => 'ok', 'message' => 'heading + relatedPostsLimit=3 (sidebar links use each post’s Related links field)'];
    $results[] = ['scope' => 'NSCBlogSingle', 'field' => 'Connect box', 'status' => 'ok', 'message' => 'CTA title, button, URL (optional background in Theme Options → Blog)'];

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>NSC Global Options</title>';
    echo '<style>body{font-family:Arial,sans-serif;padding:24px}table{border-collapse:collapse;width:100%;max-width:900px}th,td{border:1px solid #ddd;padding:8px}th{background:#f7f7f7;text-align:left}.ok{color:#0a7f2e}.error{color:#b00020}</style>';
    echo '</head><body>';
    echo '<h1>NSC Global Options (Header, Footer & Blog)</h1>';
    echo '<p>Menus, blog categories (Technology, Cultures), blog single (author, related services, related posts count, connect box), and theme options have been set. Edit in WP Admin → NSC Theme Options → <strong>Global</strong> (Header/Footer) or <strong>Blog</strong> (NSC Blog Single).</p>';
    echo '<table><thead><tr><th>Scope</th><th>Field</th><th>Status</th><th>Details</th></tr></thead><tbody>';
    foreach ($results as $row) {
        $statusClass = $row['status'] === 'error' ? 'error' : 'ok';
        echo '<tr><td>' . esc_html($row['scope']) . '</td><td>' . esc_html($row['field']) . '</td><td class="' . esc_attr($statusClass) . '">' . esc_html($row['status']) . '</td><td>' . esc_html($row['message']) . '</td></tr>';
    }
    echo '</tbody></table></body></html>';
    exit;
}, 5);
