<?php

declare(strict_types=1);

/**
 * CF7 + Polylang: duplicate contact forms per language (linked translations), translate visible
 * strings while preserving CF7/mail tags in brackets. Used by create-nsc-cf7-form.php (seed_lang).
 */

function nsc_seed_cf7_shortcode_for_form(int $formId): string
{
    return $formId > 0 ? sprintf('[contact-form-7 id="%d"]', $formId) : '';
}

function nsc_seed_cf7_parse_id_from_shortcode(string $shortcode): int
{
    if (preg_match('/\bid\s*=\s*"(\d+)"/i', $shortcode, $m)) {
        return (int) $m[1];
    }

    return 0;
}

/**
 * Translate plain-text segments; keep [...] blocks (form-tags, mail-tags) unchanged.
 */
function nsc_seed_cf7_translate_preserving_bracket_tags(string $text, callable $translate): string
{
    $parts = preg_split('/(\[[^\]]+\])/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($parts)) {
        return $translate($text);
    }
    $out = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        if (isset($part[0]) && $part[0] === '[') {
            $out .= $part;

            continue;
        }
        if (trim($part) === '') {
            $out .= $part;

            continue;
        }
        $out .= $translate($part);
    }

    return $out;
}

/**
 * @param array<string, mixed> $mail
 * @return array<string, mixed>
 */
function nsc_seed_cf7_translate_mail_array(array $mail, callable $translate): array
{
    foreach (['subject', 'body', 'sender'] as $field) {
        if (!empty($mail[$field]) && is_string($mail[$field])) {
            $mail[$field] = nsc_seed_cf7_translate_preserving_bracket_tags($mail[$field], $translate);
        }
    }

    return $mail;
}

/**
 * @param array<string, mixed> $messages
 * @return array<string, mixed>
 */
function nsc_seed_cf7_translate_messages_array(array $messages, callable $translate): array
{
    $out = [];
    foreach ($messages as $k => $v) {
        if (is_string($v) && !nsc_seed_should_skip_translating_string($v)) {
            $out[$k] = $translate($v);
        } else {
            $out[$k] = $v;
        }
    }

    return $out;
}

function nsc_seed_cf7_locale_for_polylang_slug(string $langSlug): string
{
    if (function_exists('PLL')) {
        $pll = \PLL();
        if (is_object($pll) && isset($pll->model) && is_object($pll->model)) {
            $obj = $pll->model->get_language($langSlug);
            if ($obj && !empty($obj->locale)) {
                return (string) $obj->locale;
            }
        }
    }

    return get_locale();
}

/**
 * Copy CF7 post meta from canonical to translation with string translation.
 */
function nsc_seed_cf7_copy_meta_to_translation(int $canonicalId, int $translationId, string $langSlug, callable $translate): void
{
    $form = get_post_meta($canonicalId, '_form', true);
    if (is_string($form) && $form !== '') {
        update_post_meta($translationId, '_form', nsc_seed_cf7_translate_preserving_bracket_tags($form, $translate));
    }

    foreach (['_mail', '_mail_2'] as $metaKey) {
        $mail = get_post_meta($canonicalId, $metaKey, true);
        if (is_array($mail)) {
            update_post_meta($translationId, $metaKey, nsc_seed_cf7_translate_mail_array($mail, $translate));
        }
    }

    $messages = get_post_meta($canonicalId, '_messages', true);
    if (is_array($messages) && $messages !== []) {
        update_post_meta($translationId, '_messages', nsc_seed_cf7_translate_messages_array($messages, $translate));
    } elseif (is_array($messages)) {
        update_post_meta($translationId, '_messages', $messages);
    }

    $additional = get_post_meta($canonicalId, '_additional_settings', true);
    update_post_meta($translationId, '_additional_settings', is_string($additional) ? $additional : '');

    update_post_meta($translationId, '_locale', nsc_seed_cf7_locale_for_polylang_slug($langSlug));
}

/**
 * Create/update linked wpcf7_contact_form translations for the current seed_lang request.
 */
function nsc_seed_cf7_sync_linked_translations(int $canonicalFormId): void
{
    if (!function_exists('nsc_seed_polylang_active') || !nsc_seed_polylang_active()
        || $canonicalFormId <= 0
        || !function_exists('nsc_seed_polylang_upsert_linked_post')) {
        return;
    }

    nsc_seed_polylang_set_default_language_on_post($canonicalFormId);

    $targets = function_exists('nsc_seed_polylang_sync_target_slugs_for_request')
        ? nsc_seed_polylang_sync_target_slugs_for_request()
        : [];
    if ($targets === []) {
        return;
    }

    $sourceLang = nsc_seed_polylang_default_slug();
    if ($sourceLang === '') {
        return;
    }

    $canonicalTitle = get_the_title($canonicalFormId);

    foreach ($targets as $lang) {
        $translate = static function (string $s) use ($lang, $sourceLang): string {
            return nsc_seed_translate_text($s, $lang, $sourceLang);
        };

        $tTitle = $canonicalTitle !== '' ? $translate($canonicalTitle) : $canonicalTitle;

        nsc_seed_polylang_upsert_linked_post(
            $canonicalFormId,
            $lang,
            [
                'post_type' => 'wpcf7_contact_form',
                'post_status' => 'publish',
                'post_title' => $tTitle,
                'post_content' => '',
            ],
            static function (int $trId) use ($canonicalFormId, $lang, $translate): void {
                nsc_seed_cf7_copy_meta_to_translation($canonicalFormId, $trId, $lang, $translate);
            }
        );
    }
}

/**
 * Rewrite [contact-form-7 id="…"] to the Polylang-linked form ID for $targetLang.
 *
 * @param array<int, array<string, mixed>>|null $components
 * @return array<int, array<string, mixed>>|null
 */
function nsc_seed_polylang_localize_cf7_shortcodes_in_flexible(?array $components, string $targetLang): ?array
{
    if ($components === null || $targetLang === '' || !function_exists('pll_get_post')) {
        return $components;
    }
    $def = function_exists('nsc_seed_polylang_default_slug') ? nsc_seed_polylang_default_slug() : '';
    if ($def !== '' && $targetLang === $def) {
        return $components;
    }

    $primaryFallback = (int) get_option('nsc_cf7_primary_form_id', 0);

    $out = [];
    foreach ($components as $i => $block) {
        $out[$i] = is_array($block)
            ? nsc_seed_polylang_localize_cf7_in_component($block, $targetLang, $primaryFallback)
            : $block;
    }

    return $out;
}

/**
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function nsc_seed_polylang_localize_cf7_in_component(array $node, string $targetLang, int $primaryFallback): array
{
    $out = [];
    foreach ($node as $k => $v) {
        if ($k === 'cf7Shortcode' && is_string($v)) {
            $out[$k] = nsc_seed_polylang_localize_cf7_shortcode_string($v, $targetLang, $primaryFallback);

            continue;
        }
        if (is_array($v)) {
            $out[$k] = nsc_seed_polylang_localize_cf7_in_component($v, $targetLang, $primaryFallback);
        } else {
            $out[$k] = $v;
        }
    }

    return $out;
}

function nsc_seed_polylang_localize_cf7_shortcode_string(string $shortcode, string $targetLang, int $primaryFallback): string
{
    $canonicalId = nsc_seed_cf7_parse_id_from_shortcode($shortcode);
    if ($canonicalId <= 0) {
        $canonicalId = $primaryFallback;
    }
    if ($canonicalId <= 0) {
        return $shortcode;
    }

    $def = function_exists('nsc_seed_polylang_default_slug') ? nsc_seed_polylang_default_slug() : '';
    if ($def !== '' && function_exists('pll_get_post')) {
        $maybeCanon = pll_get_post($canonicalId, $def);
        if ($maybeCanon) {
            $canonicalId = (int) $maybeCanon;
        }
    }

    $tr = pll_get_post($canonicalId, $targetLang);
    if (!$tr) {
        return $shortcode;
    }
    $newId = (int) $tr;

    if (preg_match('/\bid\s*=\s*"\d*"/i', $shortcode)) {
        return preg_replace('/\bid\s*=\s*"\d*"/i', 'id="' . $newId . '"', $shortcode, 1);
    }

    return nsc_seed_cf7_shortcode_for_form($newId);
}

/**
 * Runtime: CF7 form ID for current Polylang language from a canonical (default-language) ID.
 */
function nsc_cf7_form_id_for_current_language(int $canonicalFormId): int
{
    if ($canonicalFormId <= 0) {
        return 0;
    }
    if (!function_exists('pll_current_language') || !function_exists('pll_get_post')) {
        return $canonicalFormId;
    }
    $lang = pll_current_language('slug');
    if (!is_string($lang) || $lang === '') {
        return $canonicalFormId;
    }
    $tr = pll_get_post($canonicalFormId, $lang);

    return $tr ? (int) $tr : $canonicalFormId;
}
