<?php

declare(strict_types=1);

/**
 * NSC nav menu seeding: main header + footer sitemap, aligned with create-nsc-pages.php slugs.
 *
 * Default run: menus for the default Polylang language (or a single menu if Polylang is off),
 * assigns theme locations, mirrors the same menu term to every language in Polylang.
 * Footer sitemap tree is stored in one menu and assigned to both sitemap_footer and navigation_footer
 * (NSCFooter: column links + sitemap columns) for each locale.
 *
 * seed_lang={slug}|all: builds menus for those languages only (translated labels + translated page IDs),
 * assigns each locale in Polylang via nsc_polylang_assign_nav_menu_for_language().
 *
 * @package NscSoftware
 */

/**
 * @param array{rebuild?: bool} $options
 * @return list<array{scope: string, field: string, status: string, message: string}>
 */
function nsc_seed_menus_run(array $options = []): array
{
    $rebuild = !empty($options['rebuild']);

    if (!function_exists('wp_update_nav_menu_item')) {
        require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
    }

    $polylang = function_exists('nsc_seed_polylang_active') && nsc_seed_polylang_active();
    $defLang = $polylang && function_exists('pll_default_language') ? (string) pll_default_language('slug') : '';
    $translationTargets = function_exists('nsc_seed_polylang_sync_target_slugs_for_request')
        ? nsc_seed_polylang_sync_target_slugs_for_request()
        : [];

    $results = [];

    if (!$polylang || $translationTargets === []) {
        $runLang = ($polylang && $defLang !== '') ? $defLang : '_none';
        $menuIds = nsc_seed_menus_build_for_languages([$runLang], $rebuild, $results);

        $locations = get_nav_menu_locations();
        if (!is_array($locations)) {
            $locations = [];
        }
        $locationsDirty = false;
        if ($menuIds['navigation_main'] > 0) {
            $locations['navigation_main'] = $menuIds['navigation_main'];
            $locationsDirty = true;
        }
        if ($menuIds['sitemap_footer'] > 0) {
            $locations['sitemap_footer'] = $menuIds['sitemap_footer'];
            $locations['navigation_footer'] = $menuIds['sitemap_footer'];
            $locationsDirty = true;
        }
        if ($locationsDirty) {
            set_theme_mod('nav_menu_locations', $locations);
        }

        if ($polylang && function_exists('nsc_polylang_sync_nav_menu_for_default_language')) {
            if ($menuIds['navigation_main'] > 0) {
                nsc_polylang_sync_nav_menu_for_default_language('navigation_main', $menuIds['navigation_main']);
            }
            if ($menuIds['sitemap_footer'] > 0) {
                nsc_polylang_sync_nav_menu_for_default_language('sitemap_footer', $menuIds['sitemap_footer']);
                nsc_polylang_sync_nav_menu_for_default_language('navigation_footer', $menuIds['sitemap_footer']);
            }
        }

        return $results;
    }

    foreach ($translationTargets as $lang) {
        nsc_seed_menus_build_for_languages([$lang], $rebuild, $results);
    }

    return $results;
}

/**
 * @param list<string> $langs Polylang slug or '_none' when Polylang off
 * @param list<array{scope: string, field: string, status: string, message: string}> $results
 * @return array{navigation_main: int, sitemap_footer: int}
 */
function nsc_seed_menus_build_for_languages(array $langs, bool $rebuild, array &$results): array
{
    $out = ['navigation_main' => 0, 'sitemap_footer' => 0];

    foreach ($langs as $lang) {
        $workLang = $lang === '_none' ? '' : $lang;

        $mainId = nsc_seed_menus_ensure_menu_term('navigation_main', $workLang, $results);
        $siteId = nsc_seed_menus_ensure_menu_term('sitemap_footer', $workLang, $results);

        if ($mainId > 0) {
            if ($rebuild) {
                nsc_seed_nav_menu_delete_all_items($mainId);
            } else {
                nsc_seed_nav_menu_delete_marked_items($mainId);
            }
            nsc_seed_menus_populate_main($mainId, $workLang);
            $out['navigation_main'] = $mainId;
            $results[] = [
                'scope' => 'Menu',
                'field' => 'Main navigation (' . ($workLang !== '' ? $workLang : 'site') . ')',
                'status' => 'ok',
                'message' => 'menu_id=' . $mainId . ', structure seeded (matches header / create-nsc-pages slugs)',
            ];
        }

        if ($siteId > 0) {
            if ($rebuild) {
                nsc_seed_nav_menu_delete_all_items($siteId);
            } else {
                nsc_seed_nav_menu_delete_marked_items($siteId);
            }
            nsc_seed_menus_populate_sitemap($siteId, $workLang);
            $out['sitemap_footer'] = $siteId;
            $results[] = [
                'scope' => 'Menu',
                'field' => 'Footer sitemap (' . ($workLang !== '' ? $workLang : 'site') . ')',
                'status' => 'ok',
                'message' => 'menu_id=' . $siteId . ', hierarchy seeded; theme locations sitemap_footer + navigation_footer'
                    . ((function_exists('nsc_seed_polylang_active') && nsc_seed_polylang_active()) && $workLang !== ''
                        && function_exists('nsc_seed_polylang_sync_target_slugs_for_request')
                        && nsc_seed_polylang_sync_target_slugs_for_request() !== []
                        ? ' (Polylang slot: ' . $workLang . ')'
                        : ((function_exists('nsc_seed_polylang_active') && nsc_seed_polylang_active())
                            ? ' (Polylang: default run syncs all language slots)'
                            : '')),
            ];
        }

        if ($workLang !== '' && function_exists('nsc_seed_polylang_sync_target_slugs_for_request')
            && nsc_seed_polylang_sync_target_slugs_for_request() !== []
            && function_exists('nsc_polylang_assign_nav_menu_for_language')) {
            if ($mainId > 0) {
                nsc_polylang_assign_nav_menu_for_language('navigation_main', $workLang, $mainId);
            }
            if ($siteId > 0) {
                nsc_polylang_assign_nav_menu_for_language('sitemap_footer', $workLang, $siteId);
                nsc_polylang_assign_nav_menu_for_language('navigation_footer', $workLang, $siteId);
            }
        }
    }

    return $out;
}

function nsc_seed_nav_menu_delete_marked_items(int $menuId): void
{
    $items = wp_get_nav_menu_items($menuId, ['post_status' => 'any']);
    if (!is_array($items)) {
        return;
    }
    $ids = [];
    foreach ($items as $item) {
        if (isset($item->ID) && get_post_meta((int) $item->ID, '_nsc_seeded', true) === '1') {
            $ids[] = (int) $item->ID;
        }
    }
    foreach ($ids as $id) {
        wp_delete_post($id, true);
    }
}

function nsc_seed_nav_menu_delete_all_items(int $menuId): void
{
    $items = wp_get_nav_menu_items($menuId, ['post_status' => 'any']);
    if (!is_array($items)) {
        return;
    }
    foreach ($items as $item) {
        if (isset($item->ID) && (int) $item->ID > 0) {
            wp_delete_post((int) $item->ID, true);
        }
    }
}

function nsc_seed_menus_page_id(string $slug, string $lang): int
{
    if ($slug === '') {
        return 0;
    }
    if ($lang === '' || !function_exists('pll_default_language')) {
        $p = get_page_by_path($slug, OBJECT, 'page');

        return $p instanceof \WP_Post ? (int) $p->ID : 0;
    }

    $def = pll_default_language('slug');
    if (!is_string($def)) {
        $def = '';
    }

    if ($lang === $def || $def === '') {
        $p = get_page_by_path($slug, OBJECT, 'page');

        return $p instanceof \WP_Post ? (int) $p->ID : 0;
    }

    $tPath = get_page_by_path($slug . '-' . $lang, OBJECT, 'page');
    if ($tPath instanceof \WP_Post) {
        return (int) $tPath->ID;
    }

    $base = get_page_by_path($slug, OBJECT, 'page');
    if ($base instanceof \WP_Post && function_exists('pll_get_post')) {
        $tr = pll_get_post((int) $base->ID, $lang);

        return $tr ? (int) $tr : 0;
    }

    return 0;
}

/**
 * @param list<array{scope: string, field: string, status: string, message: string}> $results
 */
function nsc_seed_menus_ensure_menu_term(string $locationKey, string $lang, array &$results): int
{
    $menuName = nsc_seed_menus_menu_name_for_language($locationKey, $lang);
    $termLangForPolylang = nsc_seed_menus_polylang_term_language_slug($lang);

    foreach (wp_get_nav_menus() as $menu) {
        if ($menu->name !== $menuName) {
            continue;
        }
        $tid = (int) $menu->term_id;
        if ($tid <= 0) {
            continue;
        }
        if ($termLangForPolylang !== '' && function_exists('pll_get_term_language')) {
            $tl = pll_get_term_language($tid);
            if (is_string($tl) && $tl !== '' && $tl !== $termLangForPolylang) {
                continue;
            }
        }

        return $tid;
    }

    $id = wp_create_nav_menu($menuName);
    if (is_wp_error($id)) {
        $results[] = [
            'scope' => 'Menu',
            'field' => $menuName,
            'status' => 'error',
            'message' => $id->get_error_message(),
        ];

        return 0;
    }

    $tid = (int) $id;
    if ($termLangForPolylang !== '' && function_exists('pll_set_term_language')) {
        pll_set_term_language($tid, $termLangForPolylang);
    }

    return $tid;
}

function nsc_seed_menus_menu_name_for_language(string $locationKey, string $lang): string
{
    $baseName = $locationKey === 'navigation_main' ? 'Main Navigation' : 'Footer Sitemap';
    if ($lang === '') {
        return $baseName;
    }
    if (!function_exists('pll_default_language')) {
        return $baseName . ' (' . $lang . ')';
    }
    $def = (string) pll_default_language('slug');
    if ($def !== '' && $lang === $def) {
        return $baseName;
    }

    return $baseName . ' (' . $lang . ')';
}

/**
 * Polylang term language for the nav_menu term (empty lang => default site language in PLL).
 */
function nsc_seed_menus_polylang_term_language_slug(string $lang): string
{
    if ($lang !== '') {
        return $lang;
    }
    if (function_exists('pll_default_language')) {
        $d = pll_default_language('slug');

        return is_string($d) ? $d : '';
    }

    return '';
}

function nsc_seed_menus_translate_label(string $label, string $lang): string
{
    if ($label === '' || $lang === '') {
        return $label;
    }
    if (!function_exists('nsc_seed_polylang_active') || !nsc_seed_polylang_active()) {
        return $label;
    }
    $def = pll_default_language('slug');
    if (!is_string($def) || $def === '' || $lang === $def) {
        return $label;
    }
    if (!function_exists('nsc_seed_translate_text')) {
        return $label;
    }

    return nsc_seed_translate_text($label, $lang, $def);
}

function nsc_seed_menus_mark_item(int $itemId): void
{
    if ($itemId > 0) {
        update_post_meta($itemId, '_nsc_seeded', '1');
    }
}

/**
 * @param array<string, mixed> $args
 */
function nsc_seed_menus_add_item(int $menuId, array $args): int
{
    $defaults = [
        'menu-item-title' => '',
        'menu-item-type' => 'post_type',
        'menu-item-object' => 'page',
        'menu-item-object-id' => 0,
        'menu-item-url' => '',
        'menu-item-status' => 'publish',
        'menu-item-position' => 0,
        'menu-item-parent-id' => 0,
        'menu-item-classes' => '',
    ];
    $item = array_merge($defaults, $args);
    $id = wp_update_nav_menu_item($menuId, 0, $item);

    return is_wp_error($id) ? 0 : (int) $id;
}

function nsc_seed_menus_populate_main(int $menuId, string $lang): void
{
    $t = static function (string $s) use ($lang): string {
        return nsc_seed_menus_translate_label($s, $lang);
    };

    $position = 1;

    $addPage = static function (string $slug, string $label, string $classes = '') use ($menuId, $lang, $t, &$position): void {
        $pid = nsc_seed_menus_page_id($slug, $lang);
        if ($pid <= 0) {
            return;
        }
        $id = nsc_seed_menus_add_item($menuId, [
            'menu-item-title' => $t($label),
            'menu-item-object-id' => $pid,
            'menu-item-position' => $position,
            'menu-item-classes' => $classes,
        ]);
        nsc_seed_menus_mark_item($id);
        ++$position;
    };

    $addPage('home', 'Home');
    $addPage('about', 'About Us');
    $addPage('ai', 'AI', 'highlight');

    $wwdId = nsc_seed_menus_add_item($menuId, [
        'menu-item-title' => $t('What We Do'),
        'menu-item-type' => 'custom',
        'menu-item-object' => '',
        'menu-item-object-id' => 0,
        'menu-item-url' => '#',
        'menu-item-position' => $position,
        'menu-item-classes' => 'no-link-cursor',
    ]);
    nsc_seed_menus_mark_item($wwdId);
    ++$position;

    $childPos = 1;
    $svcId = nsc_seed_menus_page_id('our-services', $lang);
    if ($svcId > 0) {
        $cid = nsc_seed_menus_add_item($menuId, [
            'menu-item-title' => $t('Our Services'),
            'menu-item-object-id' => $svcId,
            'menu-item-position' => $childPos,
            'menu-item-parent-id' => $wwdId,
        ]);
        nsc_seed_menus_mark_item($cid);
        ++$childPos;
    }
    $capId = nsc_seed_menus_page_id('technology-apabilities', $lang);
    if ($capId > 0) {
        $cid = nsc_seed_menus_add_item($menuId, [
            'menu-item-title' => $t('Technology Capabilities'),
            'menu-item-object-id' => $capId,
            'menu-item-position' => $childPos,
            'menu-item-parent-id' => $wwdId,
        ]);
        nsc_seed_menus_mark_item($cid);
    }

    $addPage('blogs', 'Blog');
    $addPage('career', 'Careers');
    $addPage('case-studies', 'Case Studies');
    $addPage('contact', 'Contact Us', 'contact-btn');
}

/**
 * Top-level order: Home, About, What We Do (+ children), Careers, Blog, Case Studies — matches static footer column split.
 */
function nsc_seed_menus_populate_sitemap(int $menuId, string $lang): void
{
    $t = static function (string $s) use ($lang): string {
        return nsc_seed_menus_translate_label($s, $lang);
    };

    $position = 1;

    $addPage = static function (string $slug, string $label) use ($menuId, $lang, $t, &$position): void {
        $pid = nsc_seed_menus_page_id($slug, $lang);
        if ($pid <= 0) {
            return;
        }
        $id = nsc_seed_menus_add_item($menuId, [
            'menu-item-title' => $t($label),
            'menu-item-object-id' => $pid,
            'menu-item-position' => $position,
        ]);
        nsc_seed_menus_mark_item($id);
        ++$position;
    };

    $addPage('home', 'Home');
    $addPage('about', 'About Us');

    $wwdId = nsc_seed_menus_add_item($menuId, [
        'menu-item-title' => $t('What We Do'),
        'menu-item-type' => 'custom',
        'menu-item-object' => '',
        'menu-item-object-id' => 0,
        'menu-item-url' => '#',
        'menu-item-position' => $position,
        'menu-item-classes' => 'no-link-cursor',
    ]);
    nsc_seed_menus_mark_item($wwdId);
    ++$position;

    $childPos = 1;
    foreach (['our-services' => 'Our Services', 'technology-apabilities' => 'Technology Capabilities'] as $slug => $label) {
        $pid = nsc_seed_menus_page_id($slug, $lang);
        if ($pid <= 0) {
            continue;
        }
        $cid = nsc_seed_menus_add_item($menuId, [
            'menu-item-title' => $t($label),
            'menu-item-object-id' => $pid,
            'menu-item-position' => $childPos,
            'menu-item-parent-id' => $wwdId,
        ]);
        nsc_seed_menus_mark_item($cid);
        ++$childPos;
    }

    $addPage('career', 'Careers');
    $addPage('blogs', 'Blog');
    $addPage('case-studies', 'Case Studies');
}
