<?php

namespace NscSoftware\Theme;

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
    // Site-wide labels (formerly Theme translatable options).
    $context['theme']->labels = [
        'feed' => __('%s Feed', 'NscSoftware'),
        'skipToMainContent' => __('Skip to main content', 'NscSoftware'),
        'mainContentAriaLabel' => __('Content', 'NscSoftware'),
    ];
    $context['theme']->buildUri = trailingslashit(get_template_directory_uri()) . 'frontend/build';

    return $context;
});
