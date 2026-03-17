<?php

/**
 * This is an example file showcasing how you can add custom post types to your NscSoftware theme.
 *
 * For a full list of parameters see https://developer.wordpress.org/reference/functions/register_post_type/ or use https://generatewp.com/post-type/ to generate the code for you.
 */

namespace NscSoftware\CustomPostTypes;

// add_action('init', function () {
//     $labels = [
//         'name'                  => _x('Post Types', 'Post Type General Name', 'NscSoftware'),
//         'singular_name'         => _x('Post Type', 'Post Type Singular Name', 'NscSoftware'),
//         'menu_name'             => __('Post Types', 'NscSoftware'),
//         'name_admin_bar'        => __('Post Type', 'NscSoftware'),
//         'archives'              => __('Item Archives', 'NscSoftware'),
//         'attributes'            => __('Item Attributes', 'NscSoftware'),
//         'parent_item_colon'     => __('Parent Item:', 'NscSoftware'),
//         'all_items'             => __('All Items', 'NscSoftware'),
//         'add_new_item'          => __('Add New Item', 'NscSoftware'),
//         'add_new'               => __('Add New', 'NscSoftware'),
//         'new_item'              => __('New Item', 'NscSoftware'),
//         'edit_item'             => __('Edit Item', 'NscSoftware'),
//         'update_item'           => __('Update Item', 'NscSoftware'),
//         'view_item'             => __('View Item', 'NscSoftware'),
//         'view_items'            => __('View Items', 'NscSoftware'),
//         'search_items'          => __('Search Item', 'NscSoftware'),
//         'not_found'             => __('Not found', 'NscSoftware'),
//         'not_found_in_trash'    => __('Not found in Trash', 'NscSoftware'),
//         'featured_image'        => __('Featured Image', 'NscSoftware'),
//         'set_featured_image'    => __('Set featured image', 'NscSoftware'),
//         'remove_featured_image' => __('Remove featured image', 'NscSoftware'),
//         'use_featured_image'    => __('Use as featured image', 'NscSoftware'),
//         'insert_into_item'      => __('Insert into item', 'NscSoftware'),
//         'uploaded_to_this_item' => __('Uploaded to this item', 'NscSoftware'),
//         'items_list'            => __('Items list', 'NscSoftware'),
//         'items_list_navigation' => __('Items list navigation', 'NscSoftware'),
//         'filter_items_list'     => __('Filter items list', 'NscSoftware'),
//     ];
//     $args = [
//         'label'                 => __('Post Type', 'NscSoftware'),
//         'description'           => __('Post Type Description', 'NscSoftware'),
//         'labels'                => $labels,
//         'supports'              => ['title', 'editor', 'revisions'],
//         'taxonomies'            => ['category', 'post_tag'],
//         'hierarchical'          => false,
//         'public'                => true,
//         'show_ui'               => true,
//         'show_in_menu'          => true,
//         'menu_position'         => 5,
//         'show_in_admin_bar'     => true,
//         'show_in_nav_menus'     => true,
//         'can_export'            => true,
//         'has_archive'           => true,
//         'exclude_from_search'   => false,
//         'publicly_queryable'    => true,
//         'capability_type'       => 'page',
//     ];
//     register_post_type('example', $args);
// });
