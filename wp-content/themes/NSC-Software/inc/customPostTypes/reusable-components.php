<?php

namespace NscSoftware\CustomPostTypes;

add_action('init', function () {
    $labels = [
        'name'                  => _x('Reusable Components', 'Component Post Type', 'NscSoftware'),
        'singular_name'         => _x('Reusable Components', 'Component Post Type', 'NscSoftware'),
        'menu_name'             => _x('Reusable Components', 'Component Post Type', 'NscSoftware'),
        'name_admin_bar'        => __('Reusable Components', 'NscSoftware'),
        'archives'              => __('Reusable Component Archives', 'NscSoftware'),
        'attributes'            => __('Reusable Component Attributes', 'NscSoftware'),
        'parent_item_colon'     => __('Parent Reusable Component:', 'NscSoftware'),
        'all_items'             => __('All Reusable Components', 'NscSoftware'),
        'add_new_item'          => __('Add New Reusable Components', 'NscSoftware'),
        'new_item'              => __('New Reusable Components', 'NscSoftware'),
        'edit_item'             => __('Edit Reusable Components', 'NscSoftware'),
        'update_item'           => __('Update Reusable Components', 'NscSoftware'),
        'view_item'             => __('View Reusable Components', 'NscSoftware'),
        'view_items'            => __('View Reusable Components', 'NscSoftware'),
        'search_items'          => __('Search Reusable Components', 'NscSoftware'),
        'not_found'             => __('No reusable components found', 'NscSoftware'),
        'not_found_in_trash'    => __('No reusable components found in Trash', 'NscSoftware'),
        'items_list'            => __('Reusable components list', 'NscSoftware'),
        'items_list_navigation' => __('Reusable components list navigation', 'NscSoftware'),
        'filter_items_list'     => __('Filter reusable components list', 'NscSoftware'),
    ];
    $args = [
        'labels'                => $labels,
        'supports'              => ['title', 'revisions'],
        'hierarchical'          => false,
        'public'                => false,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 20,
        'menu_icon'             => 'dashicons-controls-repeat',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => false,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'capability_type'       => 'page',
        'rewrite'               => false
    ];
    register_post_type('reusable-components', $args);
});
