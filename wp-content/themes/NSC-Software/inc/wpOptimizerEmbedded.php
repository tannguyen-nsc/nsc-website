<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Theme fallback for environments where plugin installation is restricted.
 * Loads NHT WP Optimizer library from theme when plugin is unavailable.
 */
add_action('after_setup_theme', static function (): void {
    // if (!class_exists('WP_Optimizer_Library')) {
    //     $embeddedLib = get_template_directory() . '/inc/wpOptimizerLibrary.php';
    //     if (file_exists($embeddedLib)) {
    //         require_once $embeddedLib;
    //     }
    // }

    // if (class_exists('WP_Optimizer_Library') && method_exists('WP_Optimizer_Library', 'init')) {
    //     WP_Optimizer_Library::init();
    // }
}, 120);

