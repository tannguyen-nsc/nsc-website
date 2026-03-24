<?php

declare(strict_types=1);

/**
 * NSC Theme Options (ACF): Polylang language tabs in wp-admin so each language’s option row can be
 * edited without using the admin-bar language switcher alone.
 *
 * Uses query arg nsc_opt_lang + hidden field on save. Requires Polylang.
 */

namespace NscSoftware\ThemeOptionsAdminLang;

/**
 * Whether we are on an NSC Theme Options ACF screen (parent or submenu).
 */
function is_nsc_theme_options_screen(): bool
{
    if (!is_admin()) {
        return false;
    }
    $page = isset($_GET['page']) ? (string) $_GET['page'] : '';

    return $page !== '' && stripos($page, 'NSCThemeOptions') === 0;
}

/**
 * Valid Polylang slug for this request (save helper, theme tabs, Polylang admin ?lang=, then default).
 */
function get_requested_edit_language(): string
{
    if (!function_exists('pll_languages_list')) {
        return '';
    }
    $allowed = pll_languages_list(['fields' => 'slug']);
    if (!is_array($allowed) || $allowed === []) {
        return '';
    }
    if (!empty($_POST['nsc_opt_lang'])) {
        $c = sanitize_key((string) $_POST['nsc_opt_lang']);
        if (in_array($c, $allowed, true)) {
            return $c;
        }
    }
    if (!empty($_GET['nsc_opt_lang'])) {
        $c = sanitize_key((string) $_GET['nsc_opt_lang']);
        if (in_array($c, $allowed, true)) {
            return $c;
        }
    }
    // Polylang wp-admin language switcher uses ?lang= (our tabs used to ignore it → wrong option row).
    if (!empty($_GET['lang'])) {
        $c = sanitize_key((string) $_GET['lang']);
        if (in_array($c, $allowed, true)) {
            return $c;
        }
    }
    if (is_admin() && function_exists('pll_current_language')) {
        $cur = pll_current_language();
        if (is_string($cur) && $cur !== '' && in_array($cur, $allowed, true)) {
            return $cur;
        }
    }
    $def = function_exists('pll_default_language') ? pll_default_language('slug') : '';
    if (is_string($def) && $def !== '' && in_array($def, $allowed, true)) {
        return $def;
    }

    return (string) ($allowed[0] ?? '');
}

/**
 * @return array<int, object>
 */
function get_polylang_languages(): array
{
    if (!function_exists('pll_languages_list')) {
        return [];
    }
    $list = pll_languages_list(['hide_empty' => false]);
    if (!is_array($list)) {
        return [];
    }

    return $list;
}

/**
 * Inner markup for the language tab bar + hint (injected before the options form, inside #nsc-theme-options-lang-tabs).
 */
function get_language_tabs_inner_html(): string
{
    $languages = get_polylang_languages();
    if ($languages === []) {
        return '';
    }
    $current = get_requested_edit_language();
    $page = isset($_GET['page']) ? (string) $_GET['page'] : '';
    $base = admin_url('admin.php');

    ob_start();
    echo '<div class="nsc-theme-options-lang-tabs__nav nav-tab-wrapper">';
    echo '<span class="nsc-theme-options-lang-tabs__title">' . esc_html__('Language', 'NscSoftware') . '</span>';
    foreach ($languages as $lang) {
        $slug = isset($lang->slug) ? (string) $lang->slug : '';
        if ($slug === '') {
            continue;
        }
        $url = add_query_arg(
            [
                'page' => $page,
                'nsc_opt_lang' => $slug,
            ],
            $base
        );
        $active = ($slug === $current) ? ' nav-tab-active' : '';
        $label = isset($lang->name) ? (string) $lang->name : strtoupper($slug);
        echo '<a href="' . esc_url($url) . '" class="nav-tab' . esc_attr($active) . '">' . esc_html($label) . '</a>';
    }
    echo '</div>';
    echo '<p class="nsc-theme-options-lang-tabs__hint">' . esc_html__(
        'Fields below are saved for the selected language. Switch tabs to translate NSC Theme Options (Header, Footer, Blog, Careers, etc.).',
        'NscSoftware'
    ) . '</p>';

    return (string) ob_get_clean();
}

add_filter(
    'acf/settings/current_language',
    static function ($language) {
        if (!is_admin() || !is_nsc_theme_options_screen()) {
            return $language;
        }
        $selected = get_requested_edit_language();

        return $selected !== '' ? $selected : $language;
    },
    25
);

add_action(
    'acf/input/admin_head',
    static function (): void {
        if (!is_nsc_theme_options_screen() || !function_exists('pll_languages_list')) {
            return;
        }
        if (get_polylang_languages() === []) {
            return;
        }
        echo '<style id="nsc-theme-options-lang-tabs-css">'
            . '#nsc-theme-options-lang-tabs{box-sizing:border-box;margin:0 0 20px;border:1px solid #c3c4c7;background:#fff;box-shadow:0 1px 1px rgba(0,0,0,.04);}'
            . '#nsc-theme-options-lang-tabs .nsc-theme-options-lang-tabs__nav{margin:0;padding:12px 24px 0;border-bottom:1px solid #c3c4c7;background:#f0f0f1;}'
            . '#nsc-theme-options-lang-tabs .nsc-theme-options-lang-tabs__title{display:inline-block;margin:8px 10px 8px 0;font-weight:600;color:#1d2327;}'
            . '#nsc-theme-options-lang-tabs .nsc-theme-options-lang-tabs__hint{margin:0;padding:12px 24px 16px;color:#50575e;font-size:13px;}'
            . '#nsc-theme-options-lang-tabs form#post,#nsc-theme-options-lang-tabs form.acf-form,#nsc-theme-options-lang-tabs form#acf-form{margin:0;padding:12px 24px 32px;}'
            . '</style>';
    },
    5
);

add_action(
    'acf/input/admin_footer',
    static function (): void {
        if (!is_nsc_theme_options_screen() || !function_exists('pll_languages_list')) {
            return;
        }
        $inner = get_language_tabs_inner_html();
        if ($inner === '') {
            return;
        }
        $json = wp_json_encode(
            $inner,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        if (!is_string($json)) {
            return;
        }
        // Wrap the ACF options form inside #nsc-theme-options-lang-tabs (tabs + fields in one block).
        echo '<script>(function(){var html=' . $json . ';'
            . 'var w=document.querySelector("#wpbody-content .wrap")||document.querySelector(".wrap");'
            . 'if(!w){return;}'
            . 'var f=w.querySelector("form#post")||w.querySelector("form.acf-form")||w.querySelector("form#acf-form")||w.querySelector("form[method=post]");'
            . 'if(!f||f.closest("#nsc-theme-options-lang-tabs")){return;}'
            . 'var o=document.createElement("div");o.id="nsc-theme-options-lang-tabs";o.innerHTML=html;'
            . 'f.parentNode.insertBefore(o,f);o.appendChild(f);'
            . '})();</script>';
    },
    1
);

add_action(
    'acf/input/admin_footer',
    static function (): void {
        if (!is_nsc_theme_options_screen() || !function_exists('pll_languages_list')) {
            return;
        }
        $lang = get_requested_edit_language();
        if ($lang === '') {
            return;
        }
        $json = wp_json_encode($lang);
        if (!is_string($json)) {
            return;
        }
        // Preserve language on ACF options save (POST may not include GET query args).
        echo '<script>(function(){var v=' . $json . ";var f=document.querySelector('#post')||document.querySelector('form.acf-form')||document.querySelector('form#acf-form')||document.querySelector('.wrap > form');if(!f){return;}if(f.querySelector('input[name=\"nsc_opt_lang\"]')){return;}var i=document.createElement('input');i.type='hidden';i.name='nsc_opt_lang';i.value=v;f.appendChild(i);})();</script>";
    },
    99
);
