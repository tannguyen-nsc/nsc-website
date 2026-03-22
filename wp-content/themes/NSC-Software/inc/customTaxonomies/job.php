<?php

/**
 * Job taxonomies: category (tabs on open positions), employment type (multi), + core tags on job CPT.
 */

namespace NscSoftware\CustomTaxonomies;

add_action('init', static function (): void {
    // --- Job category: Management | Engineering | Business (tab labels on careers listing) ---
    register_taxonomy('job_category', 'job', [
        'labels' => [
            'name' => _x('Job categories', 'taxonomy general name', 'NscSoftware'),
            'singular_name' => _x('Job category', 'taxonomy singular name', 'NscSoftware'),
            'search_items' => __('Search categories', 'NscSoftware'),
            'all_items' => __('All categories', 'NscSoftware'),
            'edit_item' => __('Edit category', 'NscSoftware'),
            'update_item' => __('Update category', 'NscSoftware'),
            'add_new_item' => __('Add new category', 'NscSoftware'),
            'new_item_name' => __('New category name', 'NscSoftware'),
            'menu_name' => __('Categories', 'NscSoftware'),
        ],
        'hierarchical' => true,
        'public' => true,
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'show_in_nav_menus' => false,
        'rewrite' => ['slug' => 'job-category', 'with_front' => false],
    ]);

    // --- Employment type: Full time, Part time (multi-select) ---
    register_taxonomy('job_employment', 'job', [
        'labels' => [
            'name' => _x('Job types', 'taxonomy general name', 'NscSoftware'),
            'singular_name' => _x('Job type', 'taxonomy singular name', 'NscSoftware'),
            'search_items' => __('Search types', 'NscSoftware'),
            'all_items' => __('All types', 'NscSoftware'),
            'edit_item' => __('Edit type', 'NscSoftware'),
            'update_item' => __('Update type', 'NscSoftware'),
            'add_new_item' => __('Add new type', 'NscSoftware'),
            'new_item_name' => __('New type name', 'NscSoftware'),
            'menu_name' => __('Job types', 'NscSoftware'),
            'separate_items_with_commas' => __('Separate types with commas', 'NscSoftware'),
            'add_or_remove_items' => __('Add or remove types', 'NscSoftware'),
            'choose_from_most_used' => __('Choose from the most used', 'NscSoftware'),
        ],
        'hierarchical' => false,
        'public' => true,
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'show_in_nav_menus' => false,
        'rewrite' => ['slug' => 'job-type', 'with_front' => false],
    ]);

    register_taxonomy_for_object_type('post_tag', 'job');
}, 0);
