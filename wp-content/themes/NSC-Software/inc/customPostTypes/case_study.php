<?php

/**
 * Case study custom post type (detail + archive integration with frontend case studies listing).
 *
 * Query: post_type => 'case_study', post_status => 'publish'
 * REST: GET /wp-json/wp/v2/case_study
 */

namespace NscSoftware\CustomPostTypes;

add_action('init', static function (): void {
    $labels = [
        'name'                  => _x('Case studies', 'Post type general name', 'NscSoftware'),
        'singular_name'         => _x('Case study', 'Post type singular name', 'NscSoftware'),
        'menu_name'             => _x('Case studies', 'Admin Menu text', 'NscSoftware'),
        'name_admin_bar'        => _x('Case study', 'Add New on Toolbar', 'NscSoftware'),
        'add_new'               => __('Add New', 'NscSoftware'),
        'add_new_item'          => __('Add new case study', 'NscSoftware'),
        'new_item'              => __('New case study', 'NscSoftware'),
        'edit_item'             => __('Edit case study', 'NscSoftware'),
        'view_item'             => __('View case study', 'NscSoftware'),
        'all_items'             => __('All case studies', 'NscSoftware'),
        'search_items'          => __('Search case studies', 'NscSoftware'),
        'parent_item_colon'     => __('Parent case studies:', 'NscSoftware'),
        'not_found'             => __('No case studies found.', 'NscSoftware'),
        'not_found_in_trash'    => __('No case studies found in Trash.', 'NscSoftware'),
        'featured_image'        => __('Case study image', 'NscSoftware'),
        'set_featured_image'    => __('Set case study image', 'NscSoftware'),
        'remove_featured_image' => __('Remove case study image', 'NscSoftware'),
        'use_featured_image'    => __('Use as case study image', 'NscSoftware'),
        'archives'              => __('Case study archives', 'NscSoftware'),
        'insert_into_item'      => __('Insert into case study', 'NscSoftware'),
        'uploaded_to_this_item' => __('Uploaded to this case study', 'NscSoftware'),
        'filter_items_list'     => __('Filter case studies list', 'NscSoftware'),
        'items_list'            => __('Case studies list', 'NscSoftware'),
        'items_list_navigation' => __('Case studies list navigation', 'NscSoftware'),
    ];

    $args = [
        'labels'             => $labels,
        'description'        => __('Case study entries for listing and detail pages.', 'NscSoftware'),
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_admin_bar'  => true,
        'show_in_nav_menus'  => true,
        'show_in_rest'       => true,
        'menu_position'      => 22,
        'menu_icon'          => 'dashicons-analytics',
        'capability_type'    => 'post',
        'hierarchical'       => false,
        'supports'           => ['title', 'excerpt', 'thumbnail', 'revisions'],
        'taxonomies'         => [
            'case_study_category',
            'case_study_industry',
            'case_study_country',
            'case_study_solution',
            'case_study_service',
            'case_study_technology',
        ],
        'has_archive'        => true,
        'rewrite'            => [
            'slug'       => 'case-study',
            'with_front' => false,
        ],
        'query_var'          => true,
    ];

    register_post_type('case_study', $args);
}, 0);
