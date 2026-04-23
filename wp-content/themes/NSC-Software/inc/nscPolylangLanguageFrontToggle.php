<?php

declare(strict_types=1);

/**
 * Per-language visibility on the front-end language switcher (Polylang), without editing the plugin.
 *
 * - Settings: Languages list (admin.php?page=mlang) row action “Show on site” / “Hide from site”.
 * - The default language is always kept in the switcher.
 * - Stored in option nsc_pll_front_hidden_languages (list of language slugs hidden from the public switcher).
 */

const NSC_PLL_FRONT_HIDDEN_OPTION = 'nsc_pll_front_hidden_languages';

/**
 * @return list<string>
 */
function nsc_pll_get_front_hidden_slugs(): array
{
    $raw = \get_option(NSC_PLL_FRONT_HIDDEN_OPTION, []);
    if (!\is_array($raw)) {
        return [];
    }

    $out = [];
    foreach ($raw as $slug) {
        if (!\is_string($slug) || $slug === '') {
            continue;
        }

        $out[] = \sanitize_key($slug);
    }

    return \array_values(\array_unique($out));
}

/**
 * Whether this language should appear in pll_the_languages output on the front (and previews).
 * Default language is always shown.
 */
function nsc_pll_is_language_visible_on_front(string $slug): bool
{
    $slug = \sanitize_key($slug);
    if ($slug === '') {
        return false;
    }

    if (\function_exists('pll_default_language')) {
        $def = \pll_default_language('slug');
        if (\is_string($def) && $def !== '' && $slug === $def) {
            return true;
        }
    }

    return !\in_array($slug, nsc_pll_get_front_hidden_slugs(), true);
}

/**
 * @param list<string> $slugs
 */
function nsc_pll_set_front_hidden_slugs(array $slugs): void
{
    $clean = [];
    foreach ($slugs as $s) {
        if (!\is_string($s) || $s === '') {
            continue;
        }

        $clean[] = \sanitize_key($s);
    }

    $clean = \array_values(\array_unique($clean));

    if ($clean === []) {
        \delete_option(NSC_PLL_FRONT_HIDDEN_OPTION);
    } else {
        \update_option(NSC_PLL_FRONT_HIDDEN_OPTION, $clean, false);
    }
}

function nsc_pll_set_language_hidden_on_front(string $slug, bool $hidden): void
{
    $slug = \sanitize_key($slug);
    if ($slug === '') {
        return;
    }

    if (\function_exists('pll_default_language')) {
        $def = \pll_default_language('slug');
        if (\is_string($def) && $def !== '' && $slug === $def) {
            return;
        }
    }

    $list = nsc_pll_get_front_hidden_slugs();
    if ($hidden) {
        if (!\in_array($slug, $list, true)) {
            $list[] = $slug;
        }
    } else {
        $list = \array_values(\array_diff($list, [$slug]));
    }

    nsc_pll_set_front_hidden_slugs($list);
}

/**
 * @param array<string, mixed> $elements Raw pll_the_languages elements (keyed by slug).
 *
 * @return array<string, mixed>
 */
function nsc_pll_filter_switcher_raw_elements(array $elements): array
{
    foreach ($elements as $slug => $_row) {
        if (!\is_string($slug) || $slug === '') {
            continue;
        }

        if (!nsc_pll_is_language_visible_on_front($slug)) {
            unset($elements[$slug]);
        }
    }

    return $elements;
}

/**
 * Remove hidden languages from Polylang switcher HTML (widgets, etc.).
 */
function nsc_pll_strip_hidden_langs_from_switcher_html(string $html): string
{
    foreach (nsc_pll_get_front_hidden_slugs() as $slug) {
        $html = (string) \preg_replace(
            '/<li\b[^>]*\blang-item-' . \preg_quote($slug, '/') . '\b[^>]*>.*?<\/li>/is',
            '',
            $html
        );
    }

    return $html;
}

/**
 * GET toggle from Languages screen (admin.php?page=mlang).
 */
function nsc_pll_handle_front_toggle_request(): void
{
    if (!\is_admin() || !isset($_GET['page'], $_GET['nsc_pll_front'], $_GET['lang_slug'], $_GET['_wpnonce'])) {
        return;
    }

    if ((string) $_GET['page'] !== 'mlang') {
        return;
    }

    if (!\current_user_can('manage_options')) {
        return;
    }

    $slug = \sanitize_key((string) $_GET['lang_slug']);
    if ($slug === '') {
        return;
    }

    \check_admin_referer('nsc_pll_toggle_front_' . $slug);

    $action = (string) $_GET['nsc_pll_front'];
    if ($action === 'hide') {
        nsc_pll_set_language_hidden_on_front($slug, true);
    } elseif ($action === 'show') {
        nsc_pll_set_language_hidden_on_front($slug, false);
    } else {
        return;
    }

    \wp_safe_redirect(\admin_url('admin.php?page=mlang'));
    exit;
}

/**
 * @param array<string, string> $actions
 * @param \PLL_Language         $item
 *
 * @return array<string, string>
 */
function nsc_pll_languages_row_actions_front_toggle(array $actions, $item): array
{
    if (!\is_object($item) || !isset($item->slug)) {
        return $actions;
    }

    $slug = (string) $item->slug;
    if ($slug === '') {
        return $actions;
    }

    $isDefault = false;
    if (\function_exists('pll_default_language')) {
        $def = \pll_default_language('slug');
        $isDefault = \is_string($def) && $def !== '' && $def === $slug;
    }

    if ($isDefault) {
        $actions['nsc_pll_front'] = '<span class="nsc-pll-front-note">' . \esc_html__(
            'Always on site switcher (default language)',
            'NscSoftware'
        ) . '</span>';

        return $actions;
    }

    $visible = nsc_pll_is_language_visible_on_front($slug);
    $nonce = \wp_nonce_url(
        \add_query_arg(
            [
                'page' => 'mlang',
                'nsc_pll_front' => $visible ? 'hide' : 'show',
                'lang_slug' => $slug,
            ],
            \admin_url('admin.php')
        ),
        'nsc_pll_toggle_front_' . $slug
    );

    if ($visible) {
        $label = \__('Hide from site switcher', 'NscSoftware');
        $title = \esc_attr__('Do not show this language in the public language switcher', 'NscSoftware');
    } else {
        $label = \__('Show on site switcher', 'NscSoftware');
        $title = \esc_attr__('Show this language in the public language switcher', 'NscSoftware');
    }

    $actions['nsc_pll_front'] = '<a href="' . \esc_url($nonce) . '" title="' . $title . '">' . \esc_html($label) . '</a>';

    return $actions;
}

/**
 * Widget / legacy HTML switcher output (not raw).
 *
 * @param string               $html
 * @param array<string, mixed> $args
 */
function nsc_pll_filter_pll_the_languages_html(string $html, array $args): string
{
    if (!empty($args['raw'])) {
        return $html;
    }

    if (\is_admin() && empty($args['admin_render']) && !\is_customize_preview()) {
        return $html;
    }

    return nsc_pll_strip_hidden_langs_from_switcher_html($html);
}

/**
 * Register hooks after Polylang is available.
 *
 * Note: Theme `functions.php` runs after `plugins_loaded` has already fired, so we must not hook
 * registration to `plugins_loaded` — those callbacks would never run.
 */
function nsc_pll_print_mlang_row_action_styles(): void
{
    echo '<style id="nsc-pll-front-row-actions">'
        . '#col-right .wp-list-table .row-actions{opacity:1!important;visibility:visible!important;position:static;}'
        . '</style>';
}

function nsc_pll_register_language_front_toggle_hooks(): void
{
    if (!\function_exists('pll_the_languages')) {
        return;
    }

    \add_action('admin_init', 'nsc_pll_handle_front_toggle_request');
    \add_filter('pll_languages_row_actions', 'nsc_pll_languages_row_actions_front_toggle', 20, 2);
    \add_filter('pll_the_languages', 'nsc_pll_filter_pll_the_languages_html', 20, 2);
    \add_action('admin_print_styles-toplevel_page_mlang', 'nsc_pll_print_mlang_row_action_styles');
}

if (\function_exists('pll_the_languages')) {
    nsc_pll_register_language_front_toggle_hooks();
} else {
    \add_action('init', 'nsc_pll_register_language_front_toggle_hooks', 5);
}
