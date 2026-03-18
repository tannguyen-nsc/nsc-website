<?php

namespace NscSoftware\Components\NSCHeader;

use NscSoftware\Menu;
use NscSoftware\Utils\Asset;
use NscSoftware\Utils\Options;
use Timber\Timber;

add_action('init', function () {
    register_nav_menus([
        'navigation_main' => __('Navigation Main', 'NscSoftware'),
    ]);
});

add_filter('NscSoftware/addComponentData?name=NSCHeader', function ($data) {
    $themeBuildUri = trailingslashit(get_template_directory_uri()) . 'frontend/build';
    $data['buildUri'] = $themeBuildUri;
    $data['menu'] = Timber::get_menu('navigation_main') ?? Timber::get_pages_menu();
    Menu\ensure_menu_item_classes($data['menu']);
    Menu\set_current_ancestor_on_parents($data['menu']);
    $blogName = get_bloginfo('name');

    $data['logo'] = [
        'src' => get_theme_mod('custom_logo') ? wp_get_attachment_image_url(get_theme_mod('custom_logo'), 'full') : null,
        'url' => null,
        'alt' => $blogName,
    ];
    $data['logoWhite'] = [
        'src' => null,
        'url' => null,
        'alt' => $blogName,
    ];
    $data['mobileLogo'] = [
        'white' => ['src' => null, 'url' => null, 'alt' => $blogName],
        'colored' => ['src' => null, 'url' => null, 'alt' => $blogName],
    ];

    $options = Options::getTranslatable('NSCHeader');
    if (!empty($options['logo'])) {
        $data['logo']['url'] = $options['logo']['url'] ?? $options['logo']['src'] ?? null;
        $data['logo']['src'] = $data['logo']['src'] ?: $data['logo']['url'];
        if (!empty($options['logo']['alt'])) {
            $data['logo']['alt'] = $options['logo']['alt'];
        }
    }
    if (!empty($options['logoWhite'])) {
        $data['logoWhite']['url'] = $options['logoWhite']['url'] ?? $options['logoWhite']['src'] ?? null;
        $data['logoWhite']['src'] = $data['logoWhite']['src'] ?: $data['logoWhite']['url'];
        if (!empty($options['logoWhite']['alt'])) {
            $data['logoWhite']['alt'] = $options['logoWhite']['alt'];
        }
    }
    if (!empty($options['mobileLogoWhite'])) {
        $data['mobileLogo']['white']['url'] = $options['mobileLogoWhite']['url'] ?? $options['mobileLogoWhite']['src'] ?? null;
        $data['mobileLogo']['white']['src'] = $data['mobileLogo']['white']['src'] ?: $data['mobileLogo']['white']['url'];
    }
    if (!empty($options['mobileLogoColored'])) {
        $data['mobileLogo']['colored']['url'] = $options['mobileLogoColored']['url'] ?? $options['mobileLogoColored']['src'] ?? null;
        $data['mobileLogo']['colored']['src'] = $data['mobileLogo']['colored']['src'] ?: $data['mobileLogo']['colored']['url'];
    }

    $defaultLogo = $themeBuildUri . '/img/logo.png';
    $defaultLogoWhite = $themeBuildUri . '/img/logo-white.png';
    $defaultMobWhite = $themeBuildUri . '/img/mob-logo-white.png';
    $defaultMobColored = $themeBuildUri . '/img/mob-logo.png';

    if (empty($data['logo']['src']) && empty($data['logo']['url'])) {
        $data['logo']['src'] = $defaultLogo;
    }
    if (empty($data['logoWhite']['src']) && empty($data['logoWhite']['url'])) {
        $data['logoWhite']['src'] = $defaultLogoWhite;
    }
    if (empty($data['mobileLogo']['white']['src']) && empty($data['mobileLogo']['white']['url'])) {
        $data['mobileLogo']['white']['src'] = $defaultMobWhite;
    }
    if (empty($data['mobileLogo']['colored']['src']) && empty($data['mobileLogo']['colored']['url'])) {
        $data['mobileLogo']['colored']['src'] = $defaultMobColored;
    }

    // Ensure Twig always has a non-empty src (default wins if option URL is empty string).
    $data['logo']['src'] = !empty($data['logo']['src']) ? $data['logo']['src'] : $defaultLogo;
    $data['logoWhite']['src'] = !empty($data['logoWhite']['src']) ? $data['logoWhite']['src'] : $defaultLogoWhite;
    $data['mobileLogo']['white']['src'] = !empty($data['mobileLogo']['white']['src']) ? $data['mobileLogo']['white']['src'] : $defaultMobWhite;
    $data['mobileLogo']['colored']['src'] = !empty($data['mobileLogo']['colored']['src']) ? $data['mobileLogo']['colored']['src'] : $defaultMobColored;

    // Header type per page: home (class "home"), transparent_floating (class "transparent-floating"), or default (no extra class).
    $postId = get_queried_object_id();
    if (!$postId && is_singular()) {
        global $post;
        $postId = ($post instanceof \WP_Post) ? (int) $post->ID : 0;
    }
    $headerType = '';
    $rawHeaderType = null;
    if ($postId && function_exists('get_field')) {
        $rawHeaderType = get_field('header_type', $postId);
        if (is_array($rawHeaderType)) {
            $headerType = $rawHeaderType['value'] ?? $rawHeaderType[0] ?? '';
        } else {
            $headerType = is_string($rawHeaderType) ? $rawHeaderType : '';
        }
    }
    $data['headerType'] = in_array($headerType, ['home', 'transparent_floating'], true) ? $headerType : '';
    if ($data['headerType'] === 'transparent_floating') {
        $data['headerClass'] = 'transparent-floating';
    } elseif ($data['headerType'] === 'home') {
        $data['headerClass'] = 'home';
    } else {
        $data['headerClass'] = '';
    }

    // Labels (from options); mobile-center shows page title when on a singular page.
    $data['labels'] = (isset($options['labels']) && is_array($options['labels'])) ? $options['labels'] : [];
    if (empty($data['labels']['mobileHomeText'])) {
        $data['labels']['mobileHomeText'] = __('Home', 'NscSoftware');
    }
    if ($postId && (is_singular() || is_front_page())) {
        $data['labels']['mobileHomeText'] = get_the_title($postId);
    }

    return $data;
});

Options::addTranslatable('NSCHeader', [
    [
        'label' => __('Logos', 'NscSoftware'),
        'name' => 'logosTab',
        'type' => 'tab',
        'placement' => 'top',
        'endpoint' => 0,
    ],
    [
        'label' => __('Logo (desktop)', 'NscSoftware'),
        'name' => 'logo',
        'type' => 'image',
        'preview_size' => 'medium',
        'return_format' => 'array',
        'wrapper' => ['width' => '50'],
    ],
    [
        'label' => __('Logo white (desktop, transparent header)', 'NscSoftware'),
        'name' => 'logoWhite',
        'type' => 'image',
        'preview_size' => 'medium',
        'return_format' => 'array',
        'wrapper' => ['width' => '50'],
    ],
    [
        'label' => __('Mobile logo white', 'NscSoftware'),
        'name' => 'mobileLogoWhite',
        'type' => 'image',
        'preview_size' => 'medium',
        'return_format' => 'array',
        'wrapper' => ['width' => '50'],
    ],
    [
        'label' => __('Mobile logo colored', 'NscSoftware'),
        'name' => 'mobileLogoColored',
        'type' => 'image',
        'preview_size' => 'medium',
        'return_format' => 'array',
        'wrapper' => ['width' => '50'],
    ],
    [
        'label' => __('Labels', 'NscSoftware'),
        'name' => 'labelsTab',
        'type' => 'tab',
        'placement' => 'top',
        'endpoint' => 0,
    ],
    [
        'label' => '',
        'name' => 'labels',
        'type' => 'group',
        'sub_fields' => [
            [
                'label' => __('Mobile home text', 'NscSoftware'),
                'name' => 'mobileHomeText',
                'type' => 'text',
                'default_value' => __('Home', 'NscSoftware'),
            ],
            [
                'label' => __('Language label', 'NscSoftware'),
                'name' => 'languageLabel',
                'type' => 'text',
                'default_value' => __('Language: English', 'NscSoftware'),
            ],
            [
                'label' => __('Aria label (menu)', 'NscSoftware'),
                'name' => 'ariaLabel',
                'type' => 'text',
                'default_value' => __('Main navigation', 'NscSoftware'),
            ],
        ],
    ],
]);
