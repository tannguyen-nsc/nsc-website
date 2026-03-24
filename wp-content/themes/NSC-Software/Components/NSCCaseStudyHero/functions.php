<?php

namespace NscSoftware\Components\NSCCaseStudyHero;

use NscSoftware\FieldVariables;

function getACFLayout(): array
{
    return [
        'name' => 'nscCaseStudyHero',
        'label' => __('NSC Case Study: Hero', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'label' => __('Hero background (desktop)', 'NscSoftware'),
                'name' => 'backgroundDesktop',
                'type' => 'image',
                'return_format' => 'array',
                'preview_size' => 'medium',
                'instructions' => __('Large screens (lg+). If empty, uses hero-light-case-study.png from the theme build.', 'NscSoftware'),
            ],
            [
                'label' => __('Hero background (mobile)', 'NscSoftware'),
                'name' => 'backgroundMobile',
                'type' => 'image',
                'return_format' => 'array',
                'preview_size' => 'medium',
                'instructions' => __('Below lg breakpoint. If empty, uses the desktop image when set, else the same default as desktop (hero-light-case-study.png).', 'NscSoftware'),
            ],
            [
                'label' => __('Intro', 'NscSoftware'),
                'name' => 'intro',
                'type' => 'wysiwyg',
                'toolbar' => 'basic',
                'media_upload' => 0,
                'instructions' => __('Shown under the title. If empty, the post excerpt is used.', 'NscSoftware'),
            ],
            [
                'label' => __('Customer label', 'NscSoftware'),
                'name' => 'customerLabel',
                'type' => 'text',
                'default_value' => 'Customer:',
            ],
            [
                'label' => __('Customer logo', 'NscSoftware'),
                'name' => 'customerLogo',
                'type' => 'image',
                'return_format' => 'array',
                'preview_size' => 'medium',
            ],
            [
                'label' => __('Customer tagline', 'NscSoftware'),
                'name' => 'customerTagline',
                'type' => 'text',
            ],
            [
                'label' => __('Options', 'NscSoftware'),
                'name' => 'optionsTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'label' => '',
                'name' => 'options',
                'type' => 'group',
                'layout' => 'row',
                'sub_fields' => [
                    FieldVariables\getHidden(),
                ],
            ],
        ],
    ];
}

/**
 * Full-size URL for a media attachment (image sizes first, then direct file — e.g. SVG).
 */
function resolve_attachment_id_to_url(int $attachmentId): string
{
    if ($attachmentId <= 0) {
        return '';
    }
    $url = wp_get_attachment_image_url($attachmentId, 'full');
    if ($url) {
        return (string) $url;
    }
    $url = wp_get_attachment_url($attachmentId);

    return $url ? (string) $url : '';
}

/**
 * @param mixed $value ACF image field (array, attachment ID, URL string, or empty).
 */
function resolve_acf_image_to_url($value): string
{
    if ($value === null || $value === '' || $value === false) {
        return '';
    }
    if (is_numeric($value)) {
        return resolve_attachment_id_to_url((int) $value);
    }
    if (is_string($value)) {
        $trim = trim($value);
        if ($trim === '') {
            return '';
        }
        if (is_numeric($trim)) {
            return resolve_attachment_id_to_url((int) $trim);
        }
        if (preg_match('#^https?://#i', $trim) || (isset($trim[0]) && $trim[0] === '/')) {
            return $trim;
        }

        return '';
    }
    if (is_array($value)) {
        if (!empty($value['url'])) {
            return (string) $value['url'];
        }
        $id = (int) ($value['ID'] ?? $value['id'] ?? 0);
        if ($id > 0) {
            return resolve_attachment_id_to_url($id);
        }

        return '';
    }
    if (is_object($value)) {
        $id = (int) ($value->ID ?? $value->id ?? 0);

        return $id > 0 ? resolve_attachment_id_to_url($id) : '';
    }

    return '';
}

\add_filter('NscSoftware/addComponentData?name=NSCCaseStudyHero', static function ($data) {
    if (!is_array($data)) {
        $data = [];
    }

    $buildUri = trailingslashit(get_template_directory_uri()) . 'frontend/build';
    $defaultHero = trailingslashit($buildUri) . 'img/hero-light-case-study.png';

    $deskCustom = resolve_acf_image_to_url($data['backgroundDesktop'] ?? null);
    $mobCustom = resolve_acf_image_to_url($data['backgroundMobile'] ?? null);

    $deskUrl = $deskCustom !== '' ? $deskCustom : $defaultHero;
    $mobUrl = $mobCustom !== '' ? $mobCustom : ($deskCustom !== '' ? $deskCustom : $defaultHero);

    $data['heroBackgroundDesktopUrl'] = $deskUrl;
    $data['heroBackgroundMobileUrl'] = $mobUrl;

    return $data;
});
