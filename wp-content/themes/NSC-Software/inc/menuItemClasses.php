<?php

namespace NscSoftware\Menu;

/**
 * Ensures each menu item (and children) has a `classes` array from the CMS
 * (WordPress nav menu "CSS Classes" field). Uses wp_get_nav_menu_items when
 * the menu has a term_id; otherwise leaves existing classes or sets [].
 *
 * @param object|null $menu Timber\Menu or object with ->items and optionally ->term_id.
 * @return void
 */
function ensure_menu_item_classes($menu)
{
    if (!$menu || !isset($menu->items) || !is_array($menu->items)) {
        return;
    }

    $classes_by_id = [];
    $term_id = !empty($menu->term_id) ? (int) $menu->term_id : (isset($menu->id) ? (int) $menu->id : 0);
    if ($term_id) {
        $raw = wp_get_nav_menu_items($term_id);
        if (is_array($raw)) {
            foreach ($raw as $r) {
                $classes_by_id[(int) $r->ID] = isset($r->classes) && is_array($r->classes) ? $r->classes : [];
            }
        }
    }

    \NscSoftware\Menu\set_item_classes($menu->items, $classes_by_id);
}

/**
 * @param array $items Menu items (may have ->children).
 * @param array<int, array> $classes_by_id Map of WP nav menu item ID => classes array.
 */
function set_item_classes(array $items, array $classes_by_id)
{
    foreach ($items as $item) {
        $id = isset($item->id) ? (int) $item->id : (isset($item->ID) ? (int) $item->ID : 0);
        $item->classes = ($id && isset($classes_by_id[$id])) ? $classes_by_id[$id] : (isset($item->classes) && is_array($item->classes) ? $item->classes : []);
        if (!empty($item->children) && is_array($item->children)) {
            set_item_classes($item->children, $classes_by_id);
        }
    }
}

/**
 * Set current_ancestor on parent menu items when any descendant is the current page.
 * Used so "What We Do" gets active class when on Our Services or Our Capabilities.
 *
 * @param object|null $menu Timber\Menu or object with ->items.
 * @return void
 */
function set_current_ancestor_on_parents($menu)
{
    if (!$menu || !isset($menu->items) || !is_array($menu->items)) {
        return;
    }
    foreach ($menu->items as $item) {
        if (!empty($item->children) && is_array($item->children)) {
            $item->current_ancestor = has_current_descendant($item->children);
        }
    }
}

/**
 * @param array $items Menu items (may have ->children, ->current).
 * @return bool
 */
function has_current_descendant(array $items)
{
    foreach ($items as $item) {
        if (!empty($item->current)) {
            return true;
        }
        if (!empty($item->children) && is_array($item->children) && has_current_descendant($item->children)) {
            return true;
        }
    }
    return false;
}

/**
 * True when the main blog listing, a single post, or classic post archives are shown.
 */
function is_blog_navigation_context(): bool
{
    return is_singular('post')
        || (is_home() && !is_front_page())
        || is_category()
        || is_tag()
        || is_date()
        || is_author();
}

/**
 * Normalize URL path for comparing menu links to the posts page / blog archive URL.
 */
function nsc_menu_item_path(string $url): string
{
    if ($url === '') {
        return '';
    }
    $path = wp_parse_url($url, PHP_URL_PATH);

    return is_string($path) ? untrailingslashit($path) : '';
}

/**
 * Mark the menu item that points at the blog archive URL as current (and parents as ancestor).
 * WordPress does not do this automatically on single posts.
 *
 * @param object|null $menu Timber\Menu or object with ->items.
 */
function mark_blog_archive_menu_active($menu): void
{
    if (!is_blog_navigation_context() || !$menu || !isset($menu->items) || !is_array($menu->items)) {
        return;
    }

    $pageForPosts = (int) get_option('page_for_posts');
    $blogUrl = $pageForPosts > 0 ? get_permalink($pageForPosts) : home_url('/blogs/');
    $blogPath = nsc_menu_item_path((string) $blogUrl);
    if ($blogPath === '') {
        return;
    }

    mark_menu_items_matching_blog_path($menu->items, $blogPath);
}

/**
 * @param array $items Menu items (may have ->children, ->link).
 */
function mark_menu_items_matching_blog_path(array $items, string $blogPath): bool
{
    $matched = false;
    foreach ($items as $item) {
        $link = '';
        if (isset($item->link) && is_string($item->link)) {
            $link = $item->link;
        } elseif (isset($item->url) && is_string($item->url)) {
            $link = $item->url;
        }
        $path = nsc_menu_item_path($link);
        if ($path !== '' && $path === $blogPath) {
            $item->current = true;
            $matched = true;
        }
        if (!empty($item->children) && is_array($item->children)) {
            if (mark_menu_items_matching_blog_path($item->children, $blogPath)) {
                $item->current_ancestor = true;
                $matched = true;
            }
        }
    }

    return $matched;
}
