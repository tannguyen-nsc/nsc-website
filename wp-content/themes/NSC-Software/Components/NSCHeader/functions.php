<?php

namespace NscSoftware\Components\NSCHeader;

use NscSoftware\Menu;
use NscSoftware\Utils\Asset;
use NscSoftware\Utils\Options;
use Timber\Timber;

// Nav menu helpers live in inc/ (not Composer PSR-4). Load explicitly so career/job-single
// helpers exist even if inc load order or deployment differs on the server.
$menuItemClasses = get_template_directory() . '/inc/menuItemClasses.php';
if (is_readable($menuItemClasses)) {
    require_once $menuItemClasses;
}

add_action('init', function () {
    register_nav_menus([
        'navigation_main' => __('Navigation Main', 'NscSoftware'),
    ]);
});

add_filter('NscSoftware/addComponentData?name=NSCHeader', function ($data) {
    $themeBuildUri = trailingslashit(get_template_directory_uri()) . 'frontend/build';
    $data['buildUri'] = $themeBuildUri;
    $data['menu'] = \function_exists('nsc_timber_get_menu_for_location')
        ? nsc_timber_get_menu_for_location('navigation_main')
        : (Timber::get_menu('navigation_main') ?? Timber::get_pages_menu());
    Menu\ensure_menu_item_classes($data['menu']);
    Menu\set_current_ancestor_on_parents($data['menu']);
    Menu\mark_blog_archive_menu_active($data['menu']);
    if (function_exists('NscSoftware\\Menu\\mark_career_menu_active_for_job_single')) {
        Menu\mark_career_menu_active_for_job_single($data['menu']);
    }

    if (function_exists('NscSoftware\\Menu\\mark_case_study_archive_menu_active')) {
        Menu\mark_case_study_archive_menu_active($data['menu']);
    }

    if (\function_exists('nsc_features_filter_timber_menu')) {
        $data['menu'] = \nsc_features_filter_timber_menu($data['menu']);
    }

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
    $defaultMobColored = $themeBuildUri . '/img/mob-logo.webp';

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

    // Blog / job / case study single: match blog-details, career-details, case-study-details (transparent bar + white logos).
    if (is_singular('post') || is_singular('job') || is_singular('case_study') || is_404()) {
        $data['headerType'] = 'transparent_floating';
        $data['headerClass'] = 'transparent-floating';
    }

    // Labels from options (no mobile title field — derived: Blog section → "Blog", singular → title, else Home).
    $data['labels'] = (isset($options['labels']) && is_array($options['labels'])) ? $options['labels'] : [];
    if (empty($data['labels']['languageLabel'])) {
        $data['labels']['languageLabel'] = \function_exists('nsc_pll_theme') ? nsc_pll_theme('Language: English') : __('Language: English', 'NscSoftware');
    }

    if (empty($data['labels']['ariaLabel'])) {
        $data['labels']['ariaLabel'] = \function_exists('nsc_pll_theme') ? nsc_pll_theme('Main navigation') : __('Main navigation', 'NscSoftware');
    }

    $data['labels']['closeMenu'] = \function_exists('nsc_pll_theme') ? nsc_pll_theme('Close menu') : __('Close menu', 'NscSoftware');
    if (Menu\is_blog_navigation_context()) {
        $data['labels']['mobileHomeText'] = \function_exists('nsc_pll_theme') ? nsc_pll_theme('Blog') : __('Blog', 'NscSoftware');
    } elseif (is_page('case-studies')) {
        $data['labels']['mobileHomeText'] = \function_exists('nsc_pll_theme') ? nsc_pll_theme('Case Studies') : __('Case Studies', 'NscSoftware');
    } elseif (is_singular('job')) {
        $data['labels']['mobileHomeText'] = \function_exists('nsc_pll_theme') ? nsc_pll_theme('Careers') : __('Careers', 'NscSoftware');
    } elseif (is_singular('case_study')) {
        $data['labels']['mobileHomeText'] = \function_exists('nsc_pll_theme') ? nsc_pll_theme('Case Studies') : __('Case Studies', 'NscSoftware');
    } elseif ($postId && is_singular()) {
        $data['labels']['mobileHomeText'] = get_the_title($postId);
    } elseif (is_front_page() && $postId) {
        $data['labels']['mobileHomeText'] = get_the_title($postId);
    } else {
        $data['labels']['mobileHomeText'] = \function_exists('nsc_pll_theme') ? nsc_pll_theme('Home') : __('Home', 'NscSoftware');
    }

    // When on the contact page (any Polylang translation), do not add active to the Contact menu item.
    $data['isContactPage'] = \function_exists('NscSoftware\\Menu\\is_contact_page_context')
        ? Menu\is_contact_page_context()
        : \is_page('contact');
    $data['contactPageIds'] = \function_exists('NscSoftware\\Menu\\get_contact_page_translation_ids')
        ? Menu\get_contact_page_translation_ids()
        : [];

    $data['languageSwitcher'] = '';
    $data['languageSwitcherAria'] = \function_exists('nsc_pll_theme') ? nsc_pll_theme('Languages') : __('Languages', 'NscSoftware');
    $data['languageSwitcherLanguages'] = [];
    $data['languageSwitcherDesktopCode'] = '';
    $data['mobileNavMenuLabel'] = \function_exists('nsc_pll_theme') ? nsc_pll_theme('Menu') : __('Menu', 'NscSoftware');
    if (!is_admin()
        && \function_exists('pll_the_languages')
        && \function_exists('nsc_should_show_language_switcher_ui')
        && \nsc_should_show_language_switcher_ui()) {
        $raw = \pll_the_languages([
            'raw' => 1,
            'echo' => 0,
            'hide_if_empty' => 0,
            'hide_if_no_translation' => 0,
        ]);
        if (\is_array($raw)) {
            if (\function_exists('nsc_pll_filter_switcher_raw_elements')) {
                $raw = \nsc_pll_filter_switcher_raw_elements($raw);
            }

            $data['languageSwitcherLanguages'] = \array_values($raw);
            foreach ($data['languageSwitcherLanguages'] as $row) {
                if (\is_array($row) && !empty($row['current_lang'])) {
                    $slug = isset($row['slug']) ? (string) $row['slug'] : '';
                    $data['languageSwitcherDesktopCode'] = $slug !== '' ? \strtoupper($slug) : '';
                    break;
                }
            }
        }
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
