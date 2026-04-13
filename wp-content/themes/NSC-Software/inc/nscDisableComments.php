<?php

/**
 * Disable comments and pingbacks for every post type (including pages), front and admin.
 */

namespace NscSoftware\DisableComments;

add_filter('comments_open', '__return_false', 20, 2);
add_filter('pings_open', '__return_false', 20, 2);

add_filter('comments_array', '__return_empty_array', 20, 2);

add_filter('pre_option_default_comment_status', static function () {
    return 'closed';
});
add_filter('pre_option_default_ping_status', static function () {
    return 'closed';
});

add_action('init', static function () {
    foreach (get_post_types([], 'names') as $post_type) {
        if (post_type_supports($post_type, 'comments')) {
            remove_post_type_support($post_type, 'comments');
        }
        if (post_type_supports($post_type, 'trackbacks')) {
            remove_post_type_support($post_type, 'trackbacks');
        }
    }
}, 100);

add_action('admin_menu', static function () {
    remove_menu_page('edit-comments.php');
});

add_action('admin_bar_menu', static function ($wp_admin_bar) {
    $wp_admin_bar->remove_node('comments');
}, 999);

add_action('admin_init', static function () {
    global $pagenow;
    if ($pagenow === 'edit-comments.php') {
        wp_safe_redirect(admin_url());
        exit;
    }
});

add_filter('feed_links_show_comments_feed', '__return_false');
