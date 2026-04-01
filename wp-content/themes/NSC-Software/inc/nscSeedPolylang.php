<?php

declare(strict_types=1);

/**
 * NSC HTTP seed scripts: Polylang translation posts + optional Google Cloud Translation API v2.
 *
 * Languages: read from Polylang (no hard-coded locale list). Default language keeps the primary
 * seeded post. Secondary-language sync runs only when seed_lang is set:
 * - Omit seed_lang: only canonical / default-language rows are updated (no linked-copy sync).
 * - seed_lang={slug}: update that language’s linked copy (or option row) only.
 * - seed_lang=all: every non-default language.
 *
 * Google Translate: define NSC_SEED_GOOGLE_TRANSLATE_API_KEY in wp-config.php (or env var of the same
 * name). Without a key, strings get a visible "(lang) " prefix (lowercase slug, e.g. (vi)) before the
 * source text. Legacy "[LANG] " or "(lang) " prefixes on the source are stripped before re-prefixing.
 *
 * @package NscSoftware
 */

/**
 * @return bool
 */
function nsc_seed_polylang_active(): bool
{
    return function_exists('pll_languages_list')
        && function_exists('pll_set_post_language')
        && function_exists('pll_save_post_translations')
        && function_exists('pll_get_post')
        && function_exists('pll_get_post_translations')
        && function_exists('pll_default_language');
}

/**
 * @return string
 */
function nsc_seed_polylang_default_slug(): string
{
    if (!function_exists('pll_default_language')) {
        return '';
    }

    $s = pll_default_language('slug');

    return is_string($s) ? $s : '';
}

/**
 * Ensure a valid PLL_Language for Polylang 3.8+ term hooks during HTTP seed requests.
 *
 * On the frontend, {@see PLL_Frontend} may set `PLL()->curlang` to `false` when no language is resolved.
 * `Create\Term::get_language()` treats `empty( false )` like “no curlang”, skips that branch, and can
 * fall through to `return $default_language` where `get_default_language()` is also `false` → TypeError.
 * Re-running this on `create_term` (priority 1, before Polylang’s 900/999 handlers) covers nested
 * `wp_insert_term` calls when Polylang translates terms for a post.
 */
function nsc_seed_polylang_ensure_term_language_context(): void
{
    if (!\function_exists('PLL')) {
        return;
    }

    $pll = \PLL();
    if (\property_exists($pll, 'curlang') && $pll->curlang === false) {
        $pll->curlang = null;
    }

    if (\property_exists($pll, 'pref_lang') && $pll->pref_lang === false) {
        $pll->pref_lang = null;
    }

    $langObj = null;
    if (\function_exists('pll_default_language')) {
        $obj = pll_default_language(\OBJECT);
        if ($obj instanceof \PLL_Language) {
            $langObj = $obj;
        }
    }

    if (!$langObj instanceof \PLL_Language && isset($pll->model) && \is_object($pll->model) && \method_exists($pll->model, 'get_language') && \function_exists('pll_languages_list')) {
        $list = pll_languages_list(['fields' => 'slug']);
        if (\is_array($list)) {
            foreach ($list as $s) {
                if (!\is_string($s) || $s === '') {
                    continue;
                }

                $o = $pll->model->get_language($s);
                if ($o instanceof \PLL_Language) {
                    $langObj = $o;
                    break;
                }
            }
        }
    }

    if ($langObj instanceof \PLL_Language) {
        $pll->curlang = $langObj;
        if (empty($_GET['new_lang'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $_GET['new_lang'] = $langObj->slug; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
    }
}

/**
 * Force ACF to read/write post/option fields under Polylang’s default language for this request.
 *
 * Also sets Polylang’s current language for the request. HTTP seed scripts are not wp-admin and do
 * not send term_lang_choice / new_lang; Polylang 3.8+ Term::get_language() must return PLL_Language,
 * but PLL()->curlang is often unset and model->get_default_language() can be false — causing a
 * TypeError when assigning taxonomies (wp_set_object_terms → create_term).
 *
 * Seed scripts run as a single HTTP request. If the visitor has pll_current_language = ja (cookie
 * or /ja/ URL), ACF would save the canonical Home page (slug "home") into the Japanese field bucket.
 * The front page ID stays the default-language post, so the site then loads empty or wrong rows while
 * Japanese copies (home-ja) hold [JA] placeholders — or all URLs appear to show [JA] content.
 *
 * Call once per seed request (create-nsc-*.php, runGlobalOptions). Sync helpers that add filters at
 * priority 99999 still override this for per-language writes.
 */
function nsc_seed_bootstrap_acf_polylang_default_language(): void
{
    static $registered = false;
    if ($registered || !\function_exists('pll_default_language')) {
        return;
    }

    $registered = true;

    nsc_seed_polylang_ensure_term_language_context();

    \add_action(
        'create_term',
        static function (): void {
            nsc_seed_polylang_ensure_term_language_context();
        },
        1,
        3
    );

    $slug = pll_default_language('slug');
    if (!\is_string($slug) || $slug === '') {
        return;
    }

    \add_filter(
        'acf/settings/current_language',
        static function () use ($slug) {
            return $slug;
        },
        9999
    );
}

/**
 * @return list<string>
 */
function nsc_seed_polylang_target_slugs(): array
{
    if (!function_exists('pll_languages_list')) {
        return [];
    }

    $list = pll_languages_list(['fields' => 'slug']);
    if (!is_array($list)) {
        return [];
    }

    $def = nsc_seed_polylang_default_slug();
    $out = [];
    foreach ($list as $slug) {
        if (is_string($slug) && $slug !== '' && $slug !== $def) {
            $out[] = $slug;
        }
    }

    return $out;
}

/**
 * Which non-default Polylang slugs to sync for this HTTP request.
 *
 * Uses query arg seed_lang:
 * - Missing or empty: [] (default-language / canonical content only).
 * - all: all languages except default (same as legacy nsc_seed_polylang_target_slugs()).
 * - A valid slug equal to default: [].
 * - A valid other slug: [ that slug ].
 * - Invalid slug: [].
 *
 * @return list<string>
 */
function nsc_seed_polylang_sync_target_slugs_for_request(): array
{
    if (!function_exists('pll_languages_list')) {
        return [];
    }

    $seedLangParam = $_GET['seed_lang'] ?? $_POST['seed_lang'] ?? '';
    if (empty($seedLangParam)) {
        return [];
    }

    $raw = sanitize_key((string) $seedLangParam);
    if ($raw === '') {
        return [];
    }

    if ($raw === 'all') {
        return nsc_seed_polylang_target_slugs();
    }

    $allowed = pll_languages_list(['fields' => 'slug']);
    if (!is_array($allowed) || !in_array($raw, $allowed, true)) {
        return [];
    }

    $def = nsc_seed_polylang_default_slug();
    if ($def === '' || $raw === $def) {
        return [];
    }

    return [$raw];
}

/**
 * Resolve the default-language post row for a seed slug and post type (same pattern as HTTP seeders + Polylang).
 *
 * @param bool $anyStatus When false, only published posts (used for pages).
 *
 * @return \WP_Post|null
 */
function nsc_seed_get_canonical_post_by_type_and_slug(string $postType, string $slug, bool $anyStatus = true): ?\WP_Post
{
    $slug = \sanitize_title($slug);
    if ($slug === '' || $postType === '') {
        return null;
    }

    $postStatus = $anyStatus ? 'any' : 'publish';
    if (!\function_exists('pll_default_language') || !\function_exists('pll_get_post_language')) {
        $posts = \get_posts([
            'post_type'        => $postType,
            'post_status'      => $postStatus,
            'name'             => $slug,
            'posts_per_page'   => 1,
            'no_found_rows'    => true,
            'suppress_filters' => false,
        ]);

        return !empty($posts[0]) && $posts[0] instanceof \WP_Post ? $posts[0] : null;
    }

    $def = \pll_default_language('slug');
    if (!\is_string($def) || $def === '') {
        $posts = \get_posts([
            'post_type'        => $postType,
            'post_status'      => $postStatus,
            'name'             => $slug,
            'posts_per_page'   => 1,
            'no_found_rows'    => true,
            'suppress_filters' => false,
        ]);

        return !empty($posts[0]) && $posts[0] instanceof \WP_Post ? $posts[0] : null;
    }

    $posts = \get_posts([
        'post_type'        => $postType,
        'post_status'      => $postStatus,
        'name'             => $slug,
        'posts_per_page'   => 1,
        'lang'             => $def,
        'no_found_rows'    => true,
        'suppress_filters' => false,
    ]);
    if (!empty($posts[0]) && $posts[0] instanceof \WP_Post) {
        return $posts[0];
    }

    $posts = \get_posts([
        'post_type'        => $postType,
        'post_status'      => $postStatus,
        'name'             => $slug,
        'posts_per_page'   => 1,
        'no_found_rows'    => true,
        'suppress_filters' => false,
    ]);
    if (empty($posts[0]) || !$posts[0] instanceof \WP_Post) {
        return null;
    }

    $p = $posts[0];
    $postLang = \pll_get_post_language($p->ID);
    if ($postLang === $def) {
        return $p;
    }

    if (\function_exists('pll_get_post')) {
        $canonicalId = \pll_get_post($p->ID, $def);
        if ($canonicalId) {
            $c = \get_post((int) $canonicalId);

            return $c instanceof \WP_Post ? $c : null;
        }
    }

    return null;
}

/**
 * Resolve the default-language page for a seed slug (top-level path segment).
 *
 * @return \WP_Post|null
 */
function nsc_seed_get_canonical_page_by_slug(string $slug): ?\WP_Post
{
    $slug = \sanitize_title($slug);
    if ($slug === '') {
        return null;
    }

    if (!\function_exists('pll_default_language') || !\function_exists('pll_get_post_language')) {
        $p = \get_page_by_path($slug, \OBJECT, 'page');

        return $p instanceof \WP_Post ? $p : null;
    }

    $byType = nsc_seed_get_canonical_post_by_type_and_slug('page', $slug, false);
    if ($byType instanceof \WP_Post) {
        return $byType;
    }

    $p = \get_page_by_path($slug, \OBJECT, 'page');
    if (!$p instanceof \WP_Post) {
        return null;
    }

    $def = \pll_default_language('slug');
    if (!\is_string($def) || $def === '') {
        return $p;
    }

    $postLang = \pll_get_post_language($p->ID);
    if ($postLang === $def) {
        return $p;
    }

    if (\function_exists('pll_get_post')) {
        $canonicalId = \pll_get_post($p->ID, $def);
        if ($canonicalId) {
            $c = \get_post((int) $canonicalId);

            return $c instanceof \WP_Post ? $c : null;
        }
    }

    return null;
}

/**
 * Permalink for a canonical page slug in a given Polylang language.
 * When $polylangSlug is null, uses the current language if set, otherwise the default language.
 */
function nsc_resolve_page_permalink(string $slug, ?string $polylangSlug = null): string
{
    $slug = \sanitize_title($slug);
    if ($slug === '') {
        return \home_url('/');
    }

    $canonical = nsc_seed_get_canonical_page_by_slug($slug);
    if (!$canonical instanceof \WP_Post) {
        return \home_url('/' . $slug . '/');
    }

    $baseId = (int) $canonical->ID;
    $targetLang = $polylangSlug;
    if ($targetLang === null || $targetLang === '') {
        if (\function_exists('pll_current_language')) {
            $cl = \pll_current_language('slug');
            if (\is_string($cl) && $cl !== '') {
                $targetLang = $cl;
            }
        }
    }

    if ($targetLang === '' && \function_exists('pll_default_language')) {
        $dl = \pll_default_language('slug');
        $targetLang = \is_string($dl) ? $dl : '';
    }

    if ($targetLang !== '' && \function_exists('pll_get_post')) {
        $tr = \pll_get_post($baseId, $targetLang);
        if ($tr) {
            return \get_permalink((int) $tr);
        }
    }

    return \get_permalink($baseId);
}

/**
 * Permalink for the default (canonical) Polylang language — used by HTTP page seeders.
 */
function nsc_seed_default_lang_page_permalink(string $slug): string
{
    if (\function_exists('pll_default_language')) {
        $d = \pll_default_language('slug');
        if (\is_string($d) && $d !== '') {
            return nsc_resolve_page_permalink($slug, $d);
        }
    }

    return nsc_resolve_page_permalink($slug, null);
}

function nsc_seed_urls_same_path(string $a, string $b): bool
{
    $pa = \wp_parse_url($a, \PHP_URL_PATH);
    $pb = \wp_parse_url($b, \PHP_URL_PATH);
    $pa = \is_string($pa) ? $pa : '';
    $pb = \is_string($pb) ? $pb : '';

    return \untrailingslashit($pa) === \untrailingslashit($pb);
}

function nsc_seed_polylang_rewrite_internal_url(string $url, string $sourceLang, string $targetLang): string
{
    $url = \trim($url);
    if ($url === '' || $sourceLang === '' || $targetLang === '' || $sourceLang === $targetLang) {
        return $url;
    }

    static $knownSlugs = null;
    if ($knownSlugs === null) {
        $knownSlugs = [
            'our-services',
            'ai',
            'blogs',
            'technology-capabilities',
            'contact',
            'career',
            'case-studies',
            'about',
            'home',
        ];
    }

    foreach ($knownSlugs as $pageSlug) {
        $srcPerm = nsc_resolve_page_permalink($pageSlug, $sourceLang);
        if (nsc_seed_urls_same_path($url, $srcPerm)) {
            return nsc_resolve_page_permalink($pageSlug, $targetLang);
        }
    }

    return $url;
}

/**
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function nsc_seed_polylang_localize_url_fields_recursive(array $node, string $sourceLang, string $targetLang): array
{
    $out = [];
    foreach ($node as $k => $v) {
        if (($k === 'url' || $k === 'buttonUrl') && \is_string($v)) {
            $out[$k] = nsc_seed_polylang_rewrite_internal_url($v, $sourceLang, $targetLang);
        } elseif (\is_array($v)) {
            $out[$k] = nsc_seed_polylang_localize_url_fields_recursive($v, $sourceLang, $targetLang);
        } else {
            $out[$k] = $v;
        }
    }

    return $out;
}

/**
 * @param array<int, array<string, mixed>>|null $components
 * @return array<int, array<string, mixed>>|null
 */
function nsc_seed_polylang_localize_flexible_component_urls(?array $components, string $sourceLang, string $targetLang): ?array
{
    if ($components === null || $sourceLang === '' || $targetLang === '' || $sourceLang === $targetLang) {
        return $components;
    }

    $out = [];
    foreach ($components as $i => $block) {
        $out[$i] = \is_array($block)
            ? nsc_seed_polylang_localize_url_fields_recursive($block, $sourceLang, $targetLang)
            : $block;
    }

    return $out;
}

/**
 * Whether this seed request should run Polylang linked-copy / per-locale option sync.
 */
function nsc_seed_should_run_translation_sync(): bool
{
    return nsc_seed_polylang_sync_target_slugs_for_request() !== [];
}

/**
 * True when seed_lang names one non-default Polylang language (not "all", not empty, not default).
 * Seeders then skip writes to the default-language post and only update that locale via sync helpers.
 */
function nsc_seed_is_single_target_language_run(): bool
{
    if (!nsc_seed_polylang_active()) {
        return false;
    }

    $seedLangParam = $_GET['seed_lang'] ?? $_POST['seed_lang'] ?? '';
    if ($seedLangParam === '') {
        return false;
    }

    $raw = \sanitize_key((string) $seedLangParam);
    if ($raw === '' || $raw === 'all') {
        return false;
    }

    $def = nsc_seed_polylang_default_slug();
    if ($def === '' || $raw === $def) {
        return false;
    }

    $allowed = \pll_languages_list(['fields' => 'slug']);
    if (!\is_array($allowed) || !\in_array($raw, $allowed, true)) {
        return false;
    }

    return true;
}

/**
 * @deprecated Use nsc_seed_is_single_target_language_run()
 */
function nsc_seed_pages_is_single_target_language_run(): bool
{
    return nsc_seed_is_single_target_language_run();
}

/**
 * Extra arguments for get_posts() / WP_Query during HTTP seeding so Polylang uses an explicit
 * language instead of the admin-bar / cookie / URL "current" language (which mis-resolves IDs).
 *
 * When seed_lang targets exactly one non-default language, returns [] — callers should resolve
 * the canonical (default) post and use sync helpers or pll_get_post(), not a blind get_posts().
 *
 * @return array{lang?: string}
 */
function nsc_seed_polylang_get_explicit_lang_query_args(): array
{
    if (!nsc_seed_polylang_active() || nsc_seed_is_single_target_language_run()) {
        return [];
    }

    $def = nsc_seed_polylang_default_slug();

    return ($def !== '' && is_string($def)) ? ['lang' => $def] : [];
}

/**
 * @return array{lang?: string}
 */
function nsc_seed_polylang_default_lang_query_args(): array
{
    if (!nsc_seed_polylang_active()) {
        return [];
    }

    $def = nsc_seed_polylang_default_slug();

    return ($def !== '' && is_string($def)) ? ['lang' => $def] : [];
}

/**
 * @return string
 */
function nsc_seed_google_translate_api_key(): string
{
    if (defined('NSC_SEED_GOOGLE_TRANSLATE_API_KEY') && constant('NSC_SEED_GOOGLE_TRANSLATE_API_KEY') !== '') {
        return (string) constant('NSC_SEED_GOOGLE_TRANSLATE_API_KEY');
    }

    $e = getenv('NSC_SEED_GOOGLE_TRANSLATE_API_KEY');

    return is_string($e) && $e !== '' ? $e : '';
}

/**
 * @return bool
 */
function nsc_seed_should_skip_translating_string(string $text): bool
{
    $t = trim($text);
    if ($t === '') {
        return true;
    }

    if (preg_match('#^https?://#i', $t)) {
        return true;
    }

    if (preg_match('#^\s*\[contact-form-7\b#i', $t)) {
        return true;
    }

    if (preg_match('#^\s*mailto:#i', $t)) {
        return true;
    }

    if (preg_match('#^\s*tel:#i', $t)) {
        return true;
    }

    return false;
}

/**
 * Chunk text for Translation API (rough byte limit).
 *
 * @return list<string>
 */
function nsc_seed_translate_chunks(string $text, int $maxBytes = 4500): array
{
    if (strlen($text) <= $maxBytes) {
        return [$text];
    }

    $parts = [];
    $buf = '';
    $units = preg_split('#(\s+)#u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($units)) {
        return [substr($text, 0, $maxBytes)];
    }

    foreach ($units as $u) {
        if ($u === '') {
            continue;
        }

        if (strlen($buf) + strlen($u) > $maxBytes) {
            if ($buf !== '') {
                $parts[] = $buf;
            }

            $buf = $u;
            while (strlen($buf) > $maxBytes) {
                $parts[] = substr($buf, 0, $maxBytes);
                $buf = substr($buf, $maxBytes);
            }

            continue;
        }

        $buf .= $u;
    }

    if ($buf !== '') {
        $parts[] = $buf;
    }

    return $parts !== [] ? $parts : [$text];
}

/**
 * Call Google Cloud Translation API v2 (same key as browser “Translate” backend for GCP projects).
 *
 * @param list<string> $chunks
 * @return list<string>
 */
function nsc_seed_google_translate_chunks(array $chunks, string $targetLang, string $sourceLang): array
{
    $key = nsc_seed_google_translate_api_key();
    if ($key === '' || $chunks === []) {
        return $chunks;
    }

    $url = 'https://translation.googleapis.com/language/translate/v2?key=' . rawurlencode($key);
    $out = [];
    foreach ($chunks as $chunk) {
        if ($chunk === '') {
            $out[] = '';
            continue;
        }

        $body = [
            'q' => $chunk,
            'target' => $targetLang,
            'format' => (strpos($chunk, '<') !== false) ? 'html' : 'text',
        ];
        if ($sourceLang !== '') {
            $body['source'] = $sourceLang;
        }

        $resp = wp_remote_post($url, [
            'timeout' => 45,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($body),
        ]);
        if (is_wp_error($resp)) {
            $out[] = $chunk;
            continue;
        }

        if ((int) wp_remote_retrieve_response_code($resp) !== 200) {
            $out[] = $chunk;
            continue;
        }

        $json = json_decode((string) wp_remote_retrieve_body($resp), true);
        $translated = $json['data']['translations'][0]['translatedText'] ?? null;
        if (!is_string($translated)) {
            $out[] = $chunk;
            continue;
        }

        $out[] = html_entity_decode($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    return $out;
}

/**
 * Remove leading seed placeholder from a string ([XX] or (xx) + space) so default-language or
 * re-translated content is not double-prefixed.
 */
function nsc_seed_strip_leading_placeholder_prefix(string $text): string
{
    if ($text === '') {
        return $text;
    }

    $stripped = preg_replace('/^(?:\[[A-Z0-9_-]+\]|\([a-z0-9_-]+\))\s+/u', '', $text);

    return $stripped ?? $text;
}

/**
 * Translate a single string (plain or HTML).
 */
function nsc_seed_translate_text(string $text, string $targetLang, string $sourceLang = ''): string
{
    if (nsc_seed_should_skip_translating_string($text)) {
        return $text;
    }

    $text = nsc_seed_strip_leading_placeholder_prefix($text);
    if ($sourceLang !== '' && $targetLang === $sourceLang) {
        return $text;
    }

    $key = nsc_seed_google_translate_api_key();
    if ($key === '') {
        if ($targetLang === '') {
            return $text;
        }

        $label = strtolower($targetLang);

        return '(' . $label . ') ' . $text;
    }

    $chunks = nsc_seed_translate_chunks($text);
    $done = nsc_seed_google_translate_chunks($chunks, $targetLang, $sourceLang);

    return implode('', $done);
}

/**
 * Recursively translate string leaves; preserve URLs, layout keys, attachment IDs (ints), etc.
 *
 * @param mixed $data
 * @return mixed
 */
function nsc_seed_polylang_map_string_fields($data, callable $translate, array $skipKeys)
{
    if (is_string($data)) {
        return nsc_seed_should_skip_translating_string($data) ? $data : $translate($data);
    }

    if (!is_array($data)) {
        return $data;
    }

    $out = [];
    foreach ($data as $k => $v) {
        $key = is_string($k) ? $k : (string) $k;
        if (in_array($key, $skipKeys, true)) {
            $out[$k] = $v;
            continue;
        }

        $out[$k] = nsc_seed_polylang_map_string_fields($v, $translate, $skipKeys);
    }

    return $out;
}

/**
 * Keys that must not be sent to translation (URLs, IDs, layout, shortcodes).
 * Nested keys still apply (e.g. testimonials repeater translates content, readMoreContent, authorName, authorRole; not image).
 *
 * @return list<string>
 */
function nsc_seed_polylang_acf_skip_keys(): array
{
    return [
        'acf_fc_layout',
        'openInNewTab',
        'showForm',
        'url',
        'linkUrl',
        'formAction',
        'buttonUrl',
        'phoneLink',
        'cf7Shortcode',
        'image',
        'customerLogo',
        'backgroundDesktop',
        'backgroundMobile',
        'gallery',
        'badgeImages',
        'certImages',
        'logos',
        'postsPerPage',
        'caseStudiesPerPage',
        'jobsPerPage',
        'defaultTab',
        'theme',
        'hidden',
        'size',
        'heroStyle',
        'preview_size',
    ];
}

/**
 * Run a callback while Polylang “Synchronization” for custom fields is suspended.
 *
 * When Polylang has Settings → Synchronization → Custom fields enabled, updating one translation
 * triggers meta copy to sibling posts (pll_save_post → PLL_Sync_Metas::save_object). That overwrites
 * other locales during NSC seeding (e.g. English run clobbers German pageComponents).
 *
 * @template T
 * @param callable(): T $callback
 * @return T
 */
function nsc_seed_polylang_suspend_post_meta_sync(callable $callback)
{
    static $depth = 0;

    if (!\function_exists('PLL')) {
        return $callback();
    }

    $pll = \PLL();
    if (!isset($pll->sync->post_metas) || !\is_object($pll->sync->post_metas)) {
        return $callback();
    }

    /** @var object $postMetas */
    $postMetas = $pll->sync->post_metas;
    if ($depth === 0) {
        $postMetas->remove_all_meta_actions();
        \remove_action('pll_save_post', [$postMetas, 'save_object'], 10);
    }

    ++$depth;
    try {
        return $callback();
    } finally {
        --$depth;
        if ($depth === 0) {
            \add_action('pll_save_post', [$postMetas, 'save_object'], 10, 3);
            $postMetas->add_all_meta_actions();
        }
    }
}

/**
 * Ensure the canonical post is assigned to the default Polylang language.
 */
function nsc_seed_polylang_set_default_language_on_post(int $postId): void
{
    if (!nsc_seed_polylang_active() || $postId <= 0) {
        return;
    }

    $def = nsc_seed_polylang_default_slug();
    if ($def === '') {
        return;
    }

    $current = function_exists('pll_get_post_language') ? pll_get_post_language($postId) : false;
    if ($current === false || $current === '') {
        pll_set_post_language($postId, $def, false);
    }
}

/**
 * Create or update a translation post and link it in Polylang.
 *
 * @param callable(int $translationPostId): void $applyMeta
 */
function nsc_seed_polylang_upsert_linked_post(
    int $canonicalPostId,
    string $lang,
    array $postarr,
    callable $applyMeta
): int {
    if (!nsc_seed_polylang_active() || $canonicalPostId <= 0 || $lang === '') {
        return 0;
    }

    return (int) nsc_seed_polylang_suspend_post_meta_sync(function () use ($canonicalPostId, $lang, $postarr, $applyMeta): int {
        nsc_seed_polylang_set_default_language_on_post($canonicalPostId);

        $existing = pll_get_post($canonicalPostId, $lang);
        $post = $postarr;
        $post['post_author'] = get_current_user_id() ?: 1;
        if ($existing) {
            $post['ID'] = (int) $existing;
            $r = wp_update_post($post, true);
        } else {
            unset($post['ID']);
            $r = wp_insert_post($post, true);
        }

        if (is_wp_error($r)) {
            return 0;
        }

        $trId = (int) $r;
        pll_set_post_language($trId, $lang, false);
        $applyMeta($trId);

        $translations = pll_get_post_translations($canonicalPostId);
        if (!is_array($translations)) {
            $translations = [];
        }

        $def = nsc_seed_polylang_default_slug();
        if ($def !== '') {
            $translations[$def] = $canonicalPostId;
        }

        $translations[$lang] = $trId;
        pll_save_post_translations($translations);

        return $trId;
    });
}

/**
 * Copy featured image from canonical to translation when missing.
 */
function nsc_seed_polylang_copy_thumbnail_if_missing(int $canonicalId, int $translationId): void
{
    if ($translationId <= 0 || $canonicalId <= 0) {
        return;
    }

    if (has_post_thumbnail($translationId)) {
        return;
    }

    $thumb = get_post_thumbnail_id($canonicalId);
    if ($thumb) {
        set_post_thumbnail($translationId, (int) $thumb);
    }
}

/**
 * Map source term IDs to their Polylang equivalents for $lang (skips if none).
 *
 * @param list<int> $termIds
 * @return list<int>
 */
function nsc_seed_polylang_map_term_ids(array $termIds, string $lang): array
{
    if (!function_exists('pll_get_term') || $lang === '') {
        return [];
    }

    $out = [];
    foreach ($termIds as $tid) {
        $tid = (int) $tid;
        if ($tid <= 0) {
            continue;
        }

        $tr = pll_get_term($tid, $lang);
        if ($tr) {
            $out[] = (int) $tr;
        }
    }

    return array_values(array_unique($out));
}

/**
 * Sync translated Pages (pageComponents + template). Slug pattern: {slug}-{lang}.
 *
 * @param array<int, array<string, mixed>>|null $components
 */
function nsc_seed_polylang_sync_page_translations(int $pageId, string $slug, string $title, string $template, ?array $components): void
{
    if (!nsc_seed_polylang_active() || $pageId <= 0) {
        return;
    }

    $sourceLang = nsc_seed_polylang_default_slug();
    if ($sourceLang === '') {
        return;
    }

    nsc_seed_polylang_set_default_language_on_post($pageId);

    foreach (nsc_seed_polylang_sync_target_slugs_for_request() as $lang) {
        $translate = static function (string $s) use ($lang, $sourceLang): string {
            return nsc_seed_translate_text($s, $lang, $sourceLang);
        };
        $tTitle = nsc_seed_translate_text($title, $lang, $sourceLang);
        $tSlug = sanitize_title($slug . '-' . $lang);
        $translatedComponents = $components !== null
            ? nsc_seed_polylang_map_string_fields($components, $translate, nsc_seed_polylang_acf_skip_keys())
            : null;
        $translatedComponents = nsc_seed_polylang_localize_flexible_component_urls($translatedComponents, $sourceLang, $lang);
        if (\function_exists('nsc_seed_polylang_localize_cf7_shortcodes_in_flexible')) {
            $translatedComponents = nsc_seed_polylang_localize_cf7_shortcodes_in_flexible($translatedComponents, $lang);
        }

        nsc_seed_polylang_upsert_linked_post(
            $pageId,
            $lang,
            [
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_title' => $tTitle,
                'post_name' => $tSlug,
                'post_content' => '',
            ],
            static function (int $trId) use ($translatedComponents, $template, $lang): void {
                \update_post_meta($trId, '_wp_page_template', $template);
                if ($translatedComponents !== null && \function_exists('update_field')) {
                    $acfLang = static function () use ($lang): string {
                        return $lang;
                    };
                    \add_filter('acf/settings/current_language', $acfLang, 99999);
                    \update_field('pageComponents', [], $trId);
                    \update_field('pageComponents', $translatedComponents, $trId);
                    \remove_filter('acf/settings/current_language', $acfLang, 99999);
                }
            }
        );
    }
}

/**
 * @param array<string, mixed> $acfPayload field_name => value on canonical post
 * @param list<string>         $taxonomies  Taxonomy names to copy (term IDs mapped per language via pll_get_term)
 */
function nsc_seed_polylang_sync_post_with_taxonomies(
    int $postId,
    string $postType,
    string $title,
    string $slug,
    string $content,
    string $excerpt,
    array $acfPayload,
    array $taxonomies
): void {
    if (!nsc_seed_polylang_active() || $postId <= 0) {
        return;
    }

    $sourceLang = nsc_seed_polylang_default_slug();
    if ($sourceLang === '') {
        return;
    }

    nsc_seed_polylang_set_default_language_on_post($postId);

    foreach (nsc_seed_polylang_sync_target_slugs_for_request() as $lang) {
        $translate = static function (string $s) use ($lang, $sourceLang): string {
            return nsc_seed_translate_text($s, $lang, $sourceLang);
        };
        $tTitle = nsc_seed_translate_text($title, $lang, $sourceLang);
        $tContent = nsc_seed_translate_text($content, $lang, $sourceLang);
        $tExcerpt = nsc_seed_translate_text($excerpt, $lang, $sourceLang);
        $tSlug = sanitize_title($slug . '-' . $lang);

        $translatedAcf = nsc_seed_polylang_map_string_fields($acfPayload, $translate, nsc_seed_polylang_acf_skip_keys());

        nsc_seed_polylang_upsert_linked_post(
            $postId,
            $lang,
            [
                'post_type' => $postType,
                'post_status' => 'publish',
                'post_title' => $tTitle,
                'post_name' => $tSlug,
                'post_content' => $tContent,
                'post_excerpt' => $tExcerpt,
            ],
            static function (int $trId) use ($translatedAcf, $taxonomies, $lang, $postId): void {
                if (\function_exists('update_field')) {
                    $acfLang = static function () use ($lang): string {
                        return $lang;
                    };
                    \add_filter('acf/settings/current_language', $acfLang, 99999);
                    foreach ($translatedAcf as $fname => $val) {
                        \update_field($fname, $val, $trId);
                    }

                    \remove_filter('acf/settings/current_language', $acfLang, 99999);
                }

                foreach ($taxonomies as $tax) {
                    if (!is_string($tax) || !taxonomy_exists($tax)) {
                        continue;
                    }

                    $ids = wp_get_post_terms($postId, $tax, ['fields' => 'ids']);
                    if (is_wp_error($ids) || !is_array($ids) || $ids === []) {
                        continue;
                    }

                    $mapped = nsc_seed_polylang_map_term_ids(array_map('intval', $ids), $lang);
                    if ($mapped !== []) {
                        wp_set_object_terms($trId, $mapped, $tax, false);
                    }
                }

                nsc_seed_polylang_copy_thumbnail_if_missing($postId, $trId);
            }
        );
    }
}

/**
 * Duplicate selected NSC Theme Options strings into each Polylang language (Footer + Header labels).
 * Requires ACF; uses acf/settings/current_language like the live theme.
 *
 * @return int Number of non-default languages that received updated option rows (0 if skipped).
 */
function nsc_seed_polylang_translate_global_options(): int
{
    if (!nsc_seed_polylang_active() || !function_exists('get_field') || !function_exists('update_field')) {
        return 0;
    }

    $def = nsc_seed_polylang_default_slug();
    if ($def === '') {
        return 0;
    }

    $targets = nsc_seed_polylang_sync_target_slugs_for_request();
    if ($targets === []) {
        return 0;
    }

    $setLang = static function (string $lang): callable {
        return static function () use ($lang) {
            return $lang;
        };
    };

    $readDef = $setLang($def);
    add_filter('acf/settings/current_language', $readDef, 99999);

    $footerPrefix = 'translatable_NSCFooter_';
    $flatFooter = [
        'companyName',
        'companyDescription',
        'businessNumber',
        'email',
        'copyright',
        'businessNumberLabel',
        'emailLabel',
        'contactHeading',
        'siteMapHeading',
        'telLabel',
    ];
    $footerValues = [];
    foreach ($flatFooter as $name) {
        $footerValues[$name] = get_field($footerPrefix . $name, 'option');
    }

    $offices = get_field($footerPrefix . 'offices', 'option');
    if (!is_array($offices)) {
        $offices = [];
    }

    $socialLinks = get_field($footerPrefix . 'socialLinks', 'option');
    if (!is_array($socialLinks)) {
        $socialLinks = [];
    }

    $headerLabels = get_field('translatable_NSCHeader_labels', 'option');
    if (!is_array($headerLabels)) {
        $headerLabels = [];
    }

    $cookiesPrefix = 'translatable_NSCCookiesContent_';
    $flatCookies = [
        'ariaLabel',
        'heading',
        'content',
        'rejectLabel',
        'settingsLabel',
        'acceptLabel',
    ];
    $cookiesValues = [];
    foreach ($flatCookies as $name) {
        $cookiesValues[$name] = get_field($cookiesPrefix . $name, 'option');
    }
    $cookiesSettingsUrl = get_field($cookiesPrefix . 'settingsUrl', 'option');
    $cookiesSettingsOpenInNewTab = get_field($cookiesPrefix . 'settingsOpenInNewTab', 'option');

    remove_filter('acf/settings/current_language', $readDef, 99999);

    foreach ($targets as $lang) {
        $translate = static function (string $s) use ($lang, $def): string {
            return nsc_seed_translate_text($s, $lang, $def);
        };

        $langCb = $setLang($lang);
        add_filter('acf/settings/current_language', $langCb, 99999);

        foreach ($flatFooter as $name) {
            $v = $footerValues[$name] ?? '';
            if (is_string($v) && $v !== '') {
                update_field($footerPrefix . $name, $translate($v), 'option');
            }
        }

        if ($offices !== []) {
            $tOff = nsc_seed_polylang_map_string_fields($offices, $translate, ['phoneLink']);
            update_field($footerPrefix . 'offices', $tOff, 'option');
        }

        if ($socialLinks !== []) {
            $tSoc = nsc_seed_polylang_map_string_fields($socialLinks, $translate, ['url', 'platform']);
            update_field($footerPrefix . 'socialLinks', $tSoc, 'option');
        }

        if ($headerLabels !== []) {
            $tLab = nsc_seed_polylang_map_string_fields($headerLabels, $translate, []);
            update_field('translatable_NSCHeader_labels', $tLab, 'option');
        }

        foreach ($flatCookies as $name) {
            $v = $cookiesValues[$name] ?? '';
            if (is_string($v) && $v !== '') {
                update_field($cookiesPrefix . $name, $translate($v), 'option');
            }
        }

        if (is_string($cookiesSettingsUrl) && $cookiesSettingsUrl !== '') {
            update_field($cookiesPrefix . 'settingsUrl', $cookiesSettingsUrl, 'option');
        }
        update_field($cookiesPrefix . 'settingsOpenInNewTab', (int) !empty($cookiesSettingsOpenInNewTab), 'option');

        remove_filter('acf/settings/current_language', $langCb, 99999);
    }

    return count($targets);
}
