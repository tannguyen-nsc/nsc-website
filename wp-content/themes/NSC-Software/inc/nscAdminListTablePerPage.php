<?php

declare(strict_types=1);

/**
 * Admin list tables: per-page controls (Screen Options + inline dropdown).
 */

namespace NscSoftware\AdminListTablePerPage;

const NONCE_ACTION = 'nsc_list_per_page';

/**
 * Table-limit behavior is controlled by NHT WP Optimizer option.
 */
function optimizer_table_limit_enabled(): bool
{
    $opt = \get_option('wp_optimizer_options', []);
    if (!\is_array($opt)) {
        return false;
    }

    return !empty($opt['optimize_table_list']);
}

/**
 * Maximum items per page allowed when saving Screen Options (default 5000).
 *
 * @return int Positive integer.
 */
function get_max_per_page(): int
{
    $max = (int) \apply_filters('nsc_admin_list_per_page_max', 5000);

    return \max(999, $max);
}

/**
 * Stored value when the user picks “All” in the dropdown (same upper bound as max).
 */
function get_all_per_page_value(): int
{
    return (int) \apply_filters('nsc_admin_list_per_page_all_value', get_max_per_page());
}

/**
 * Numeric choices shown in the tablenav dropdown (plus “All”).
 *
 * @return list<int>
 */
function get_dropdown_numeric_choices(): array
{
    $choices = [10, 20, 50, 100, 500];

    return \array_values(
        \array_unique(
            \array_map('intval', (array) \apply_filters('nsc_admin_list_per_page_dropdown_choices', $choices))
        )
    );
}

/**
 * @return non-falsy-string|null
 */
function get_screen_per_page_option(\WP_Screen $screen): ?string
{
    if (null === $screen->get_option('per_page')) {
        return null;
    }

    $option = $screen->get_option('per_page', 'option');
    if (!$option) {
        $option = \str_replace('-', '_', "{$screen->id}_per_page");
    }

    return $option;
}

function get_stored_per_page_for_screen(\WP_Screen $screen, string $option): int
{
    $perPage = (int) \get_user_option($option);
    if ($perPage < 1) {
        $perPage = (int) $screen->get_option('per_page', 'default');
        if ($perPage < 1) {
            $perPage = 20;
        }
    }

    return $perPage;
}

/**
 * Apply core’s set_screen_options() mapping; mutates $option in the else branch like core.
 */
function map_screen_per_page_option(string &$option): string
{
    $mapOption = $option;
    $type = \str_replace('edit_', '', $mapOption);
    $type = \str_replace('_per_page', '', $type);

    if (\in_array($type, \get_taxonomies(), true)) {
        return 'edit_tags_per_page';
    }

    if (\in_array($type, \get_post_types(), true)) {
        return 'edit_per_page';
    }

    $option = \str_replace('-', '_', $option);

    return $mapOption;
}

/**
 * Options whose values are clamped to 1–999 in core before save.
 *
 * @return list<string>
 */
function core_capped_per_page_map_options(): array
{
    return [
        'edit_per_page',
        'users_per_page',
        'edit_comments_per_page',
        'upload_per_page',
        'edit_tags_per_page',
        'plugins_per_page',
        'export_personal_data_requests_per_page',
        'remove_personal_data_requests_per_page',
        'sites_network_per_page',
        'users_network_per_page',
        'site_users_network_per_page',
        'plugins_network_per_page',
        'themes_network_per_page',
        'site_themes_network_per_page',
    ];
}

/**
 * Save per_page &gt; 999 for screens that core would reject, then redirect like core.
 */
function maybe_save_extended_per_page(): void
{
    if (!optimizer_table_limit_enabled()) {
        return;
    }

    if (!\is_admin() || \wp_doing_ajax()) {
        return;
    }

    if (
        !isset($_POST['wp_screen_options']['option'], $_POST['wp_screen_options']['value'], $_POST['screenoptionnonce'])
        || !\is_array($_POST['wp_screen_options'])
    ) {
        return;
    }

    \check_admin_referer('screen-options-nonce', 'screenoptionnonce');

    $option = \sanitize_key(\wp_unslash((string) $_POST['wp_screen_options']['option']));
    if ($option !== \wp_unslash((string) $_POST['wp_screen_options']['option'])) {
        return;
    }

    $value = (int) $_POST['wp_screen_options']['value'];
    $max = get_max_per_page();

    if ($value < 1 || $value > $max) {
        return;
    }

    if ($value <= 999) {
        return;
    }

    $mapOption = map_screen_per_page_option($option);
    $capped = \in_array($mapOption, core_capped_per_page_map_options(), true);

    if (\defined('WP_NETWORK_ADMIN') && WP_NETWORK_ADMIN && !\is_super_admin()) {
        return;
    }

    if (!$capped) {
        return;
    }

    $user = \wp_get_current_user();
    if (!$user || !$user->ID) {
        return;
    }

    \update_user_meta($user->ID, $option, $value);

    unset($_POST['wp_screen_options']);

    $url = \remove_query_arg(['pagenum', 'apage', 'paged'], \wp_get_referer());
    if (isset($_POST['mode'])) {
        $url = \add_query_arg(['mode' => \sanitize_key(\wp_unslash($_POST['mode']))], $url);
    }

    \wp_safe_redirect($url);
    exit;
}

\add_action('init', __NAMESPACE__ . '\\maybe_save_extended_per_page', 1);

/**
 * GET ?nsc_per_page= — save list limit from tablenav dropdown and redirect.
 */
function maybe_apply_per_page_from_dropdown(): void
{
    if (!optimizer_table_limit_enabled()) {
        return;
    }

    if (!isset($_GET['nsc_per_page'], $_GET['_wpnonce']) || !\is_user_logged_in()) {
        return;
    }

    if (!\wp_verify_nonce(\sanitize_text_field(\wp_unslash((string) $_GET['_wpnonce'])), NONCE_ACTION)) {
        return;
    }

    if (\defined('WP_NETWORK_ADMIN') && WP_NETWORK_ADMIN && !\is_super_admin()) {
        return;
    }

    $option = isset($_GET['nsc_per_page_option']) ? \sanitize_key((string) \wp_unslash($_GET['nsc_per_page_option'])) : '';
    if ($option === '' || !\preg_match('/^[a-z0-9_]+_per_page$/', $option)) {
        return;
    }

    $raw = isset($_GET['nsc_per_page']) ? \sanitize_text_field(\wp_unslash((string) $_GET['nsc_per_page'])) : '';

    if ($raw === 'all') {
        $value = get_all_per_page_value();
    } else {
        $value = (int) $raw;
        if ($value < 1) {
            return;
        }
        $value = \min($value, get_max_per_page());
    }

    $uid = (int) \get_current_user_id();
    if ($uid < 1) {
        return;
    }

    \update_user_meta($uid, $option, $value);

    $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) \wp_unslash($_SERVER['REQUEST_URI']) : '';
    if ($requestUri === '') {
        return;
    }

    $redirect = \remove_query_arg(
        ['nsc_per_page', 'nsc_per_page_option', '_wpnonce', 'paged', 'pagenum', 'apage'],
        \home_url($requestUri)
    );

    \wp_safe_redirect($redirect);
    exit;
}

\add_action('admin_init', __NAMESPACE__ . '\\maybe_apply_per_page_from_dropdown', 1);

/**
 * Raise the HTML max on Screen Options inputs so values above 999 can be entered.
 */
function enqueue_list_per_page_max_script(string $hookSuffix): void
{
    if (!optimizer_table_limit_enabled()) {
        return;
    }

    if ($hookSuffix === '') {
        return;
    }

    $max = get_max_per_page();
    $handle = 'nsc-admin-list-per-page-max';

    \wp_register_script($handle, false, [], false, true);
    \wp_enqueue_script($handle);
    $js = \sprintf(
        'document.querySelectorAll("input.screen-per-page").forEach(function(el){el.setAttribute("max",%d);});',
        $max
    );
    \wp_add_inline_script($handle, $js, 'after');
}

\add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_list_per_page_max_script', 999);

/**
 * Tablenav dropdown: scripts + data (only on screens with a per_page option).
 */
function enqueue_list_per_page_dropdown(string $hookSuffix): void
{
    if (!optimizer_table_limit_enabled()) {
        return;
    }

    if ($hookSuffix === '') {
        return;
    }

    $screen = \get_current_screen();
    if (!$screen) {
        return;
    }

    $option = get_screen_per_page_option($screen);
    if ($option === null) {
        return;
    }

    $handle = 'nsc-admin-list-per-page-dropdown';
    \wp_register_script($handle, false, [], false, true);
    \wp_enqueue_script($handle);

    $stored = get_stored_per_page_for_screen($screen, $option);
    $allVal = get_all_per_page_value();
    $choices = get_dropdown_numeric_choices();

    $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) \wp_unslash($_SERVER['REQUEST_URI']) : '';
    $cleanUrl = $requestUri !== ''
        ? \remove_query_arg(
            ['nsc_per_page', 'nsc_per_page_option', '_wpnonce', 'paged', 'pagenum', 'apage'],
            \home_url($requestUri)
        )
        : '';

    \wp_localize_script($handle, 'nscListPerPage', [
        'cleanUrl' => $cleanUrl,
        'nonce' => \wp_create_nonce(NONCE_ACTION),
        'option' => $option,
        'choices' => $choices,
        'allValue' => $allVal,
        'stored' => $stored,
        'i18n' => [
            /* translators: Label before the per-page dropdown in admin list tables. */
            'perPage' => \__('Per page:', 'NscSoftware'),
            'all' => \__('All', 'NscSoftware'),
        ],
    ]);

    $inline = <<<'JS'
(function(){
  var cfg = window.nscListPerPage;
  if (!cfg || !cfg.cleanUrl) { return; }
  function buildSelect(suffix){
    var wrap = document.createElement("div");
    wrap.className = "alignleft actions nsc-list-per-page-wrap";
    var label = document.createElement("label");
    label.className = "nsc-list-per-page-label";
    label.setAttribute("for", "nsc-list-per-page-" + suffix);
    label.textContent = cfg.i18n.perPage;
    var sel = document.createElement("select");
    sel.id = "nsc-list-per-page-" + suffix;
    sel.className = "nsc-list-per-page-select";
    sel.setAttribute("aria-label", cfg.i18n.perPage);
    var stored = parseInt(String(cfg.stored), 10) || 0;
    var allVal = parseInt(String(cfg.allValue), 10) || 0;
    var choices = cfg.choices || [];
    var pickAll = (allVal > 0 && stored === allVal);
    var found = false;
    var i, o;
    for (i = 0; i < choices.length; i++) {
      o = document.createElement("option");
      o.value = String(choices[i]);
      o.textContent = String(choices[i]);
      if (!pickAll && stored === choices[i]) { o.selected = true; found = true; }
      sel.appendChild(o);
    }
    if (!pickAll && !found && stored > 0) {
      o = document.createElement("option");
      o.value = String(stored);
      o.textContent = String(stored);
      o.selected = true;
      found = true;
      sel.insertBefore(o, sel.firstChild);
    }
    o = document.createElement("option");
    o.value = "all";
    o.textContent = cfg.i18n.all;
    if (pickAll || (!found && stored < 1)) { o.selected = true; }
    sel.appendChild(o);
    sel.addEventListener("change", function(){
      var url = new URL(cfg.cleanUrl, window.location.origin);
      url.searchParams.set("nsc_per_page", sel.value);
      url.searchParams.set("nsc_per_page_option", cfg.option);
      url.searchParams.set("_wpnonce", cfg.nonce);
      window.location.href = url.toString();
    });
    wrap.appendChild(label);
    wrap.appendChild(sel);
    return wrap;
  }
  function inject(){
    var top = document.querySelector(".tablenav.top");
    var bot = document.querySelector(".tablenav.bottom");
    var pages, w;
    if (top && !top.querySelector(".nsc-list-per-page-wrap")) {
      pages = top.querySelector(".tablenav-pages");
      w = buildSelect("top");
      if (pages) { top.insertBefore(w, pages); } else { top.appendChild(w); }
    }
    if (bot && !bot.querySelector(".nsc-list-per-page-wrap") && bot !== top) {
      pages = bot.querySelector(".tablenav-pages");
      w = buildSelect("bottom");
      if (pages) { bot.insertBefore(w, pages); } else { bot.appendChild(w); }
    }
  }
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", inject);
  } else { inject(); }
})();
JS;

    \wp_add_inline_script($handle, $inline, 'after');

    $css = <<<'CSS'
.nsc-list-per-page-wrap { margin: 0 8px 0 0; padding: 3px 0 0; }
.nsc-list-per-page-wrap .nsc-list-per-page-label { display: inline-block; margin: 0 6px 0 0; font-weight: 600; vertical-align: middle; }
.nsc-list-per-page-wrap select.nsc-list-per-page-select { vertical-align: baseline; min-width: 5.5em; }
.tablenav.top .nsc-list-per-page-wrap + .tablenav-pages { float: right; }
CSS;
    \wp_register_style('nsc-admin-list-per-page-dropdown', false, [], false);
    \wp_enqueue_style('nsc-admin-list-per-page-dropdown');
    \wp_add_inline_style('nsc-admin-list-per-page-dropdown', $css);
}

\add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_list_per_page_dropdown', 1000);
