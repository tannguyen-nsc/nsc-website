<?php

/**
 * This is an example file showcasing how you can add custom taxonomies to your NscSoftware theme.
 *
 * For a full list of parameters see https://developer.wordpress.org/reference/functions/register_taxonomy/ or use https://generatewp.com/taxonomy/ to generate the code for you.
 */

namespace NscSoftware\CustomTaxonomies;

// add_action('init', function () {
//     $labels = [
//         'name'                       => _x('Taxonomies', 'Taxonomy General Name', 'NscSoftware'),
//         'singular_name'              => _x('Taxonomy', 'Taxonomy Singular Name', 'NscSoftware'),
//         'menu_name'                  => __('Taxonomy', 'NscSoftware'),
//         'all_items'                  => __('All Items', 'NscSoftware'),
//         'parent_item'                => __('Parent Item', 'NscSoftware'),
//         'parent_item_colon'          => __('Parent Item:', 'NscSoftware'),
//         'new_item_name'              => __('New Item Name', 'NscSoftware'),
//         'add_new_item'               => __('Add New Item', 'NscSoftware'),
//         'edit_item'                  => __('Edit Item', 'NscSoftware'),
//         'update_item'                => __('Update Item', 'NscSoftware'),
//         'view_item'                  => __('View Item', 'NscSoftware'),
//         'separate_items_with_commas' => __('Separate items with commas', 'NscSoftware'),
//         'add_or_remove_items'        => __('Add or remove items', 'NscSoftware'),
//         'choose_from_most_used'      => __('Choose from the most used', 'NscSoftware'),
//         'popular_items'              => __('Popular Items', 'NscSoftware'),
//         'search_items'               => __('Search Items', 'NscSoftware'),
//         'not_found'                  => __('Not Found', 'NscSoftware'),
//         'no_terms'                   => __('No items', 'NscSoftware'),
//         'items_list'                 => __('Items list', 'NscSoftware'),
//         'items_list_navigation'      => __('Items list navigation', 'NscSoftware'),
//     ];
//     $args = [
//         'labels'                     => $labels,
//         'hierarchical'               => false,
//         'public'                     => true,
//         'show_ui'                    => true,
//         'show_admin_column'          => true,
//         'show_in_nav_menus'          => true,
//         'show_tagcloud'              => true,
//     ];

//     register_taxonomy('example', ['post'], $args);
// });
