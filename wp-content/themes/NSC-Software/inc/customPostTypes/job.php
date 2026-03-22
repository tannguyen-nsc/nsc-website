<?php

/**
 * Job openings custom post type for the Careers / open positions section.
 *
 * Query in PHP/Timber: post_type => 'job', post_status => 'publish'
 * REST API: GET /wp-json/wp/v2/job
 */

namespace NscSoftware\CustomPostTypes;

add_action('init', function (): void {
    $labels = [
        'name'                  => _x('Job openings', 'Post type general name', 'NscSoftware'),
        'singular_name'         => _x('Job opening', 'Post type singular name', 'NscSoftware'),
        'menu_name'             => _x('Job openings', 'Admin Menu text', 'NscSoftware'),
        'name_admin_bar'        => _x('Job opening', 'Add New on Toolbar', 'NscSoftware'),
        'add_new'               => __('Add New', 'NscSoftware'),
        'add_new_item'          => __('Add new job opening', 'NscSoftware'),
        'new_item'              => __('New job opening', 'NscSoftware'),
        'edit_item'             => __('Edit job opening', 'NscSoftware'),
        'view_item'             => __('View job opening', 'NscSoftware'),
        'all_items'             => __('All job openings', 'NscSoftware'),
        'search_items'          => __('Search job openings', 'NscSoftware'),
        'parent_item_colon'     => __('Parent job openings:', 'NscSoftware'),
        'not_found'             => __('No job openings found.', 'NscSoftware'),
        'not_found_in_trash'    => __('No job openings found in Trash.', 'NscSoftware'),
        'featured_image'        => __('Job listing image', 'NscSoftware'),
        'set_featured_image'    => __('Set job listing image', 'NscSoftware'),
        'remove_featured_image' => __('Remove job listing image', 'NscSoftware'),
        'use_featured_image'    => __('Use as job listing image', 'NscSoftware'),
        'archives'              => __('Job opening archives', 'NscSoftware'),
        'insert_into_item'      => __('Insert into job opening', 'NscSoftware'),
        'uploaded_to_this_item' => __('Uploaded to this job opening', 'NscSoftware'),
        'filter_items_list'     => __('Filter job openings list', 'NscSoftware'),
        'items_list'            => __('Job openings list', 'NscSoftware'),
        'items_list_navigation' => __('Job openings list navigation', 'NscSoftware'),
    ];

    $args = [
        'labels'             => $labels,
        'description'        => __('Open positions shown on the Careers page.', 'NscSoftware'),
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_admin_bar'  => true,
        'show_in_nav_menus'  => false,
        'show_in_rest'       => true,
        'menu_position'      => 21,
        'menu_icon'          => 'dashicons-id',
        'capability_type'    => 'post',
        'hierarchical'       => false,
        'supports'           => ['title', 'excerpt', 'thumbnail', 'revisions'],
        'taxonomies'         => ['post_tag', 'job_category', 'job_employment'],
        'has_archive'        => false,
        'rewrite'            => [
            'slug'       => 'job',
            'with_front' => false,
        ],
        'query_var'          => true,
    ];

    register_post_type('job', $args);
}, 0);
