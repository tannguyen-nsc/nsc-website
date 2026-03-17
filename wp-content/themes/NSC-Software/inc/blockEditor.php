<?php

/**
 * Block Editor related adjustments.
 */

namespace NscSoftware\BlockEditor;

/**
 * Disable Full Site Editing
 */
define('DISABLE_FSE', '__return_true');

/**
 * Disable Templates and Template Parts in Block Editor
 */
add_filter('block_editor_settings_all', function ($settings) {
    $settings['supportsTemplateMode'] = false;
    return $settings;
}, 10);

/**
 * Remove editor from Wordpress pages, since NscSoftware uses ACF.
 */
add_action('init', function () {
    remove_post_type_support('page', 'editor');
    remove_action('wp_enqueue_scripts', 'wp_enqueue_classic_theme_styles');
});

/**
 * Remove default WordPress styles on the front-end so the frontend build CSS
 * (frontend/build/css/style.css) is not overridden by global-styles typography
 * (e.g. "Public Sans", "Trebuchet MS" on h1) or block library styles.
 */
add_action('wp_enqueue_scripts', function () {
    if (is_admin()) {
        return;
    }
    wp_dequeue_style('core-block-supports');
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('global-styles');
    wp_dequeue_style('global-styles-inline-css');
    wp_dequeue_style('block-style-variation-styles');
    // Theme main.scss defines --font-family-heading with Public Sans/Trebuchet and overrides build; skip on front so build CSS wins.
    wp_dequeue_style('NscSoftware/assets/main');
}, 100);

/**
 * Strip the global-styles-inline-css style tag from output (id is generated from handle 'global-styles' when WP prints inline styles).
 */
add_action('template_redirect', function () {
    if (is_admin()) {
        return;
    }
    ob_start(function ($html) {
        return preg_replace('/<style[^>]*id=[\'"]global-styles-inline-css[\'"][^>]*>.*?<\\/style>\s*/is', '', $html);
    });
}, 0);
add_action('shutdown', function () {
    if (is_admin() || ob_get_level() === 0) {
        return;
    }
    ob_end_flush();
}, 0);
