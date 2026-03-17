<?php

/**
 * Moves the Yoast SEO plugin box to the bottom of the backend interface.
 */

namespace NscSoftware\YoastToBottom;

add_filter('wpseo_metabox_prio', function () {
    return 'low';
});
