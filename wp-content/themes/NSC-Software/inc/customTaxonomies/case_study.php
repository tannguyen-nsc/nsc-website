<?php

/**
 * Case study taxonomies:
 * - case_study_category: primary filter for listing (matches frontend: Technology, Fintech, …).
 * - Industry, Country, Solutions, Services, Technologies: tag-style, multi-select (overview meta).
 */

namespace NscSoftware\CustomTaxonomies;

add_action('init', static function (): void {
    // --- Listing category (one primary term per card; filter on case studies page) ---
    register_taxonomy('case_study_category', 'case_study', [
        'labels' => [
            'name' => _x('Case study categories', 'taxonomy general name', 'NscSoftware'),
            'singular_name' => _x('Case study category', 'taxonomy singular name', 'NscSoftware'),
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
        'rewrite' => ['slug' => 'case-study-category', 'with_front' => false],
    ]);

    $tagLike = static function (string $taxonomy, string $plural, string $singular, string $slug): void {
        register_taxonomy($taxonomy, 'case_study', [
            'labels' => [
                'name' => $plural,
                'singular_name' => $singular,
                'search_items' => sprintf(__('Search %s', 'NscSoftware'), $plural),
                'all_items' => sprintf(__('All %s', 'NscSoftware'), $plural),
                'edit_item' => sprintf(__('Edit %s', 'NscSoftware'), $singular),
                'update_item' => sprintf(__('Update %s', 'NscSoftware'), $singular),
                'add_new_item' => sprintf(__('Add new %s', 'NscSoftware'), $singular),
                'new_item_name' => sprintf(__('New %s name', 'NscSoftware'), $singular),
                'menu_name' => $plural,
                'separate_items_with_commas' => sprintf(__('Separate %s with commas', 'NscSoftware'), strtolower($plural)),
                'add_or_remove_items' => sprintf(__('Add or remove %s', 'NscSoftware'), strtolower($plural)),
                'choose_from_most_used' => __('Choose from the most used', 'NscSoftware'),
            ],
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_rest' => true,
            'show_in_nav_menus' => false,
            'rewrite' => ['slug' => $slug, 'with_front' => false],
        ]);
    };

    $tagLike(
        'case_study_industry',
        _x('Industries', 'taxonomy general name', 'NscSoftware'),
        _x('Industry', 'taxonomy singular name', 'NscSoftware'),
        'case-study-industry'
    );

    $tagLike(
        'case_study_country',
        _x('Countries', 'taxonomy general name', 'NscSoftware'),
        _x('Country', 'taxonomy singular name', 'NscSoftware'),
        'case-study-country'
    );

    $tagLike(
        'case_study_solution',
        _x('Solutions', 'taxonomy general name', 'NscSoftware'),
        _x('Solution', 'taxonomy singular name', 'NscSoftware'),
        'case-study-solution'
    );

    $tagLike(
        'case_study_service',
        _x('Services', 'taxonomy general name', 'NscSoftware'),
        _x('Service', 'taxonomy singular name', 'NscSoftware'),
        'case-study-service'
    );

    $tagLike(
        'case_study_technology',
        _x('Technologies & frameworks', 'taxonomy general name', 'NscSoftware'),
        _x('Technology / framework', 'taxonomy singular name', 'NscSoftware'),
        'case-study-technology'
    );
}, 0);

/**
 * Seed default listing categories to match the static case studies filter labels (Technology, Fintech, …).
 * Runs once when the taxonomy has no terms.
 */
add_action('init', static function (): void {
    $existing = get_terms([
        'taxonomy' => 'case_study_category',
        'hide_empty' => false,
        'number' => 1,
        'fields' => 'ids',
    ]);
    if (is_wp_error($existing) || !empty($existing)) {
        return;
    }

    $defaults = [
        'Technology',
        'Fintech',
        'Blockchain',
        'Web 3',
        'Saas',
        'Education',
        'Lifestyle',
    ];

    foreach ($defaults as $name) {
        if (! term_exists($name, 'case_study_category')) {
            wp_insert_term($name, 'case_study_category');
        }
    }
}, 20);
