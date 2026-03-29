<?php

namespace NscSoftware;

use NscSoftware\Utils\FileLoader;

require_once __DIR__ . '/vendor/autoload.php';

if (!defined('WP_ENV')) {
    define('WP_ENV', function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production');
} elseif (!defined('WP_ENVIRONMENT_TYPE')) {
    define('WP_ENVIRONMENT_TYPE', WP_ENV);
}

// Check if the required plugins are installed and activated.
// If they aren't, this function redirects the template rendering to use
// plugin-inactive.php instead and shows a warning in the admin backend.
if (Init::checkRequiredPlugins()) {
    FileLoader::loadPhpFiles('inc');
    add_action('after_setup_theme', ['NscSoftware\Init', 'initTheme']);
    add_action('after_setup_theme', ['NscSoftware\Init', 'loadComponents'], 101);
}

// Remove the admin-bar inline-CSS as it isn't compatible with the sticky footer CSS.
// This prevents unintended scrolling on pages with few content, when logged in.
add_theme_support('admin-bar', ['callback' => '__return_false']);

add_action('after_setup_theme', function () {
    // Make theme available for translation.
    // Translations can be filed in the /languages/ directory.
    load_theme_textdomain('NscSoftware', get_template_directory() . '/languages');
});

/**
 * WP Mail SMTP: relax SSL verification when certificate verify fails (e.g. local/dev).
 * Only applies when NSC_SMTP_RELAX_SSL is true in wp-config.php. Do not enable in production.
 */
add_filter('wp_mail_smtp_custom_options', function ($phpmailer) {
    if (!defined('NSC_SMTP_RELAX_SSL') || !NSC_SMTP_RELAX_SSL) {
        return $phpmailer;
    }

    $options = is_array($phpmailer->SMTPOptions) ? $phpmailer->SMTPOptions : [];
    $ssl = isset($options['ssl']) && is_array($options['ssl']) ? $options['ssl'] : [];
    $ssl['verify_peer'] = false;
    $ssl['verify_peer_name'] = false;
    $ssl['allow_self_signed'] = true;
    $options['ssl'] = $ssl;
    $phpmailer->SMTPOptions = $options;
    return $phpmailer;
});

add_filter( 'mime_types', function ( $existing_mimes ) {
  $existing_mimes['webp'] = 'image/webp';
  return $existing_mimes;
});

add_filter('file_is_displayable_image', function ($result, $path) {
  if ($result === false) {
      $displayable_image_types = array( IMAGETYPE_WEBP );
      $info = @getimagesize( $path );
      if (empty($info)) {
          $result = false;
      } elseif (!in_array($info[2], $displayable_image_types)) {
          $result = false;
      } else {
          $result = true;
      }
  }

  return $result;
}, 10, 2);