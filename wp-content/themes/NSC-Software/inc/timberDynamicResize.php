<?php

/**
 * Timber dynamic image resize (runtime). Options UI removed from NSC Theme Options —
 * settings still read from the database if present (previously saved ACF values).
 */

namespace NscSoftware\TimberDynamicResize;

use NscSoftware\Utils\TimberDynamicResize;

add_action('acf/init', function () {
    global $timberDynamicResize;
    $timberDynamicResize = new TimberDynamicResize();
});

// WPML rewrite fix.
add_filter('mod_rewrite_rules', function ($rules) {
    $homeRoot = parse_url(home_url());
    if (isset($homeRoot['path'])) {
        $homeRoot = trailingslashit($homeRoot['path']);
    } else {
        $homeRoot = '/';
    }

    $wpmlRoot = parse_url(get_option('home'));
    if (isset($wpmlRoot['path'])) {
        $wpmlRoot = trailingslashit($wpmlRoot['path']);
    } else {
        $wpmlRoot = '/';
    }

    $rules = str_replace("RewriteBase $homeRoot", "RewriteBase $wpmlRoot", $rules);
    $rules = str_replace("RewriteRule . $homeRoot", "RewriteRule . $wpmlRoot", $rules);

    return $rules;
});
