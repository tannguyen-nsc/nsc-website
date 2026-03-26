<?php

declare(strict_types=1);

/**
 * Nav menus + Polylang: Polylang reads assignments from its own option, not from raw theme_mod.
 * Seed scripts that only call set_theme_mod() leave the front-end with empty locations unless we
 * sync or fall back to stored theme_mods.
 */

/**
 * @return array<string, int>
 */
function nsc_get_raw_theme_mod_nav_menu_locations(): array
{
    $mods = get_option('theme_mods_' . get_stylesheet());
    if (!is_array($mods) || empty($mods['nav_menu_locations']) || !is_array($mods['nav_menu_locations'])) {
        return [];
    }

    return $mods['nav_menu_locations'];
}

/**
 * Theme location → menu term_id. Uses Polylang-filtered locations, then raw theme_mod if still 0.
 */
function nsc_resolve_nav_menu_term_id_for_location(string $location): int
{
    $filtered = get_nav_menu_locations();
    $id = (int) ($filtered[$location] ?? 0);
    if ($id > 0) {
        return $id;
    }

    $raw = nsc_get_raw_theme_mod_nav_menu_locations();

    return (int) ($raw[$location] ?? 0);
}

/**
 * Store the same menu term_id for every Polylang language under this theme location.
 *
 * Polylang resolves locations with options['nav_menus'][theme][location][curlang->slug]. If only the
 * default language is set (previous behavior), other languages get 0 → wrong fallbacks and nav /
 * language-switcher context can stick to one locale (e.g. always Japanese). Same menu_id for all
 * slugs matches seeded sites; Polylang still rewrites page links per menu item for the active language.
 */
function nsc_polylang_sync_nav_menu_for_default_language(string $location, int $menuTermId): void
{
    if ($menuTermId <= 0 || !function_exists('PLL') || !function_exists('pll_languages_list')) {
        return;
    }

    $slugs = pll_languages_list(['fields' => 'slug']);
    if (!is_array($slugs) || $slugs === []) {
        return;
    }

    $pll = PLL();
    $theme = get_stylesheet();
    $navMenus = $pll->options->get('nav_menus');
    if (!is_array($navMenus)) {
        $navMenus = [];
    }
    if (!isset($navMenus[$theme]) || !is_array($navMenus[$theme])) {
        $navMenus[$theme] = [];
    }
    if (!isset($navMenus[$theme][$location]) || !is_array($navMenus[$theme][$location])) {
        $navMenus[$theme][$location] = [];
    }
    foreach ($slugs as $slug) {
        if (is_string($slug) && $slug !== '') {
            $navMenus[$theme][$location][$slug] = $menuTermId;
        }
    }
    $pll->options->set('nav_menus', $navMenus);
}

/**
 * Assign one nav menu term to a theme location for a single Polylang language.
 * Location slugs match register_nav_menus: navigation_main, navigation_footer, sitemap_footer, footer_policy.
 * Does not modify other language slots (use this after seed_lang-specific menu builds).
 */
function nsc_polylang_assign_nav_menu_for_language(string $location, string $langSlug, int $menuTermId): void
{
    if ($menuTermId <= 0 || !function_exists('PLL') || $langSlug === '') {
        return;
    }

    $pll = PLL();
    $theme = get_stylesheet();
    $navMenus = $pll->options->get('nav_menus');
    if (!is_array($navMenus)) {
        $navMenus = [];
    }
    if (!isset($navMenus[$theme]) || !is_array($navMenus[$theme])) {
        $navMenus[$theme] = [];
    }
    if (!isset($navMenus[$theme][$location]) || !is_array($navMenus[$theme][$location])) {
        $navMenus[$theme][$location] = [];
    }
    $navMenus[$theme][$location][$langSlug] = $menuTermId;
    $pll->options->set('nav_menus', $navMenus);
}

/**
 * @param bool $fallbackToPages When no menu is assigned, use a pages-based menu (header/footer primary only; not sitemap).
 *
 * @return \Timber\Menu|null
 */
function nsc_timber_get_menu_for_location(string $location, bool $fallbackToPages = true)
{
    if (!class_exists(\Timber\Timber::class)) {
        return null;
    }

    $id = nsc_resolve_nav_menu_term_id_for_location($location);
    if ($id > 0) {
        $byId = \Timber\Timber::get_menu($id);
        if ($byId && !empty($byId->items)) {
            return $byId;
        }
    }

    $byLoc = \Timber\Timber::get_menu($location);
    if ($byLoc && !empty($byLoc->items)) {
        return $byLoc;
    }

    if ($fallbackToPages) {
        return \Timber\Timber::get_pages_menu();
    }

    return null;
}
