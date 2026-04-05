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
 * Used so "What We Do" gets active class when on Our Services or Technology Capabilities.
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

/**
 * Mark the menu item that points at the Career page as current on single job posts.
 * WordPress does not treat the job CPT as part of the career page hierarchy.
 *
 * @param object|null $menu Timber\Menu or object with ->items.
 */
function mark_career_menu_active_for_job_single($menu): void
{
    if (!is_singular('job') || !$menu || !isset($menu->items) || !is_array($menu->items)) {
        return;
    }

    $careerPage = get_page_by_path('career', OBJECT, 'page');
    if (!$careerPage instanceof \WP_Post) {
        return;
    }

    $careerUrl = get_permalink($careerPage);
    $careerPath = nsc_menu_item_path((string) $careerUrl);
    if ($careerPath === '') {
        return;
    }

    mark_menu_items_matching_career_path($menu->items, $careerPath);
}

/**
 * @param array $items Menu items (may have ->children, ->link).
 */
function mark_menu_items_matching_career_path(array $items, string $careerPath): bool
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
        if ($path !== '' && $path === $careerPath) {
            $item->current = true;
            $matched = true;
        }
        if (!empty($item->children) && is_array($item->children)) {
            if (mark_menu_items_matching_career_path($item->children, $careerPath)) {
                $item->current_ancestor = true;
                $matched = true;
            }
        }
    }

    return $matched;
}

/**
 * Mark the menu item that points at the Case Studies page as current on single case study posts.
 *
 * @param object|null $menu Timber\Menu or object with ->items.
 */
function mark_case_study_archive_menu_active($menu): void
{
    if (!is_singular('case_study') || !$menu || !isset($menu->items) || !is_array($menu->items)) {
        return;
    }

    $casePage = get_page_by_path('case-studies', OBJECT, 'page');
    if (!$casePage instanceof \WP_Post) {
        return;
    }

    $url = get_permalink($casePage);
    $path = nsc_menu_item_path((string) $url);
    if ($path === '') {
        return;
    }

    mark_menu_items_matching_case_studies_path($menu->items, $path);
}

/**
 * @param array $items Menu items (may have ->children, ->link).
 */
function mark_menu_items_matching_case_studies_path(array $items, string $caseStudiesPath): bool
{
    $matched = false;
    foreach ($items as $item) {
        $link = '';
        if (isset($item->link) && is_string($item->link)) {
            $link = $item->link;
        } elseif (isset($item->url) && is_string($item->url)) {
            $link = $item->url;
        }
        $p = nsc_menu_item_path($link);
        if ($p !== '' && $p === $caseStudiesPath) {
            $item->current = true;
            $matched = true;
        }
        if (!empty($item->children) && is_array($item->children)) {
            if (mark_menu_items_matching_case_studies_path($item->children, $caseStudiesPath)) {
                $item->current_ancestor = true;
                $matched = true;
            }
        }
    }

    return $matched;
}

/**
 * Default slug for the Contact page in the site’s default Polylang language (used to find the translation group).
 *
 * @return string
 */
function get_contact_page_canonical_slug(): string
{
    $slug = \apply_filters('nsc_contact_page_slug', 'contact');

    return \is_string($slug) && $slug !== '' ? $slug : 'contact';
}

/**
 * All page post IDs that are Polylang translations of the Contact page (including the default language).
 * Empty if no contact page is found.
 *
 * @return int[]
 */
function get_contact_page_translation_ids(): array
{
    static $cache = null;
    if (\is_array($cache)) {
        return $cache;
    }

    $slug = get_contact_page_canonical_slug();
    $baseId = 0;

    if (\function_exists('pll_default_language')) {
        $def = \pll_default_language('slug');
        if (\is_string($def) && $def !== '') {
            $q = new \WP_Query([
                'post_type' => 'page',
                'name' => $slug,
                'posts_per_page' => 1,
                'post_status' => 'publish',
                'lang' => $def,
                'suppress_filters' => false,
                'no_found_rows' => true,
                'fields' => 'ids',
            ]);
            if ($q->have_posts()) {
                $baseId = (int) $q->posts[0];
            }
            \wp_reset_postdata();
        }
    }

    if ($baseId <= 0) {
        $p = \get_page_by_path($slug, \OBJECT, 'page');
        if ($p instanceof \WP_Post) {
            $baseId = (int) $p->ID;
        }
    }

    $baseId = (int) \apply_filters('nsc_contact_page_base_id', $baseId);
    if ($baseId <= 0) {
        $cache = [];

        return $cache;
    }

    $ids = [];
    if (\function_exists('pll_get_post_translations')) {
        $tr = \pll_get_post_translations($baseId);
        if (\is_array($tr)) {
            foreach ($tr as $pid) {
                $pid = (int) $pid;
                if ($pid > 0) {
                    $ids[] = $pid;
                }
            }
            $cache = \array_values(\array_unique($ids));

            return $cache;
        }
    }

    $cache = [$baseId];

    return $cache;
}

/**
 * True when the main query is any language’s Contact page (Polylang translation group), not only slug "contact".
 */
function is_contact_page_context(): bool
{
    if (!\is_page()) {
        return false;
    }

    $current = (int) \get_queried_object_id();
    if ($current <= 0) {
        return false;
    }

    $ids = get_contact_page_translation_ids();

    return $ids !== [] && \in_array($current, $ids, true);
}
