<?php
/**
 * Plugin Name: NHT WP Optimizer
 * Description: WordPress optimization helpers for admin UX, uploads, and media workflows.
 * Version: 1.0.0
 * Author: NHT
 * Text Domain: wp-optimizer
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WP_OPTIMIZER_VERSION', '1.0.0');
define('WP_OPTIMIZER_FILE', __FILE__);
define('WP_OPTIMIZER_PATH', plugin_dir_path(__FILE__));
define('WP_OPTIMIZER_URL', plugin_dir_url(__FILE__));

require_once WP_OPTIMIZER_PATH . 'includes/nht-optimizer-library.php';

add_action('plugins_loaded', static function (): void {
    if (class_exists('WP_Optimizer_Library')) {
        WP_Optimizer_Library::init();
    }
});

