<?php

namespace NscSoftware\Theme;

use NscSoftware\Utils\Options;

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo');

    /*
     * Remove type attribute from link and script tags.
     */
    add_theme_support('html5', ['script', 'style']);
});

add_filter('big_image_size_threshold', '__return_false');

add_filter('timber/context', function ($context) {
    $context['theme']->labels = Options::getTranslatable('Theme')['labels'] ?? [];
    return $context;
});

Options::addTranslatable('Theme', [
    [
        'label' => __('Labels', 'NscSoftware'),
        'name' => 'labels',
        'type' => 'group',
        'sub_fields' => [
            [
                'label' => __('Feed', 'NscSoftware'),
                'instructions' => __('%s is placeholder for site title.', 'NscSoftware'),
                'name' => 'feed',
                'type' => 'text',
                'default_value' => __('%s Feed', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('Skip to main content', 'NscSoftware'),
                'name' => 'skipToMainContent',
                'type' => 'text',
                'default_value' => __('Skip to main content', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('Main Content – Aria Label', 'NscSoftware'),
                'name' => 'mainContentAriaLabel',
                'type' => 'text',
                'default_value' => __('Content', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
        ],
    ],
]);
