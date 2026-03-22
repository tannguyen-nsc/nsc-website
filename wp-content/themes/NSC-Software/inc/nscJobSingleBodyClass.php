<?php

/**
 * Body class for single job template (matches static career-details build).
 */

add_filter('body_class', static function (array $classes): array {
    if (is_singular('job')) {
        $classes[] = 'page-career-details';
    }

    return $classes;
}, 20);
