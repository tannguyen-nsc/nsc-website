<?php

declare(strict_types=1);

/**
 * Site-wide feature flags (NSC Theme Options → Global → Site features).
 * Values are read via Options::getGlobal() using the site default language, not the visitor language.
 * When a feature is off: related menus are hidden, public URLs redirect home, home-page blocks stay hidden,
 * and the matching “Hide on front” switch is forced on and locked in the page editor.
 */

use NscSoftware\Utils\Options;

// Register options as soon as ACF and Options helpers are available.
add_action('acf/init', static function (): void {
    if (!class_exists(Options::class)) {
        return;
    }
    Options::addGlobal('NSCFeatures', [
        [
            'label' => __('Site features', 'NscSoftware'),
            'name' => 'siteFeaturesTab',
            'type' => 'tab',
            'placement' => 'top',
            'endpoint' => 0,
        ],
        [
            'label' => __('Blog feature', 'NscSoftware'),
            'name' => 'feature_blog',
            'type' => 'true_false',
            'ui' => 1,
            'default_value' => 0,
            'instructions' => __('When off: Blog menu items are hidden, blog posts and the blog landing page redirect to the home page, and the Blogs (Home) block stays hidden (its “Hide on front” switch is on and locked).', 'NscSoftware'),
        ],
        [
            'label' => __('Career feature', 'NscSoftware'),
            'name' => 'feature_career',
            'type' => 'true_false',
            'ui' => 1,
            'default_value' => 0,
            'instructions' => __('When off: Careers menu items are hidden, career page and job openings redirect home, and career/job blocks stay hidden (locked “Hide on front” where gated).', 'NscSoftware'),
        ],
        [
            'label' => __('Case studies feature', 'NscSoftware'),
            'name' => 'feature_case_studies',
            'type' => 'true_false',
            'ui' => 1,
            'default_value' => 0,
            'instructions' => __('When off: Case Studies menu items are hidden, case study URLs redirect home, and the case studies archive block stays hidden (locked “Hide on front”).', 'NscSoftware'),
        ],
        [
            'label' => __('Language switcher', 'NscSoftware'),
            'name' => 'feature_language',
            'type' => 'true_false',
            'ui' => 1,
            'default_value' => 1,
            'instructions' => __('When off: the header language dropdown and any Polylang “Languages” item in Navigation are hidden. Default is on. Does not disable multilingual URLs—only the switcher UI.', 'NscSoftware'),
        ],
    ], 'Global');
}, 5);

/**
 * @return bool
 */
function nsc_feature_blog_enabled(): bool
{
    return nsc_feature_truthy(Options::getGlobal('NSCFeatures', 'feature_blog'));
}

/**
 * @return bool
 */
function nsc_feature_career_enabled(): bool
{
    return nsc_feature_truthy(Options::getGlobal('NSCFeatures', 'feature_career'));
}

/**
 * @return bool
 */
function nsc_feature_case_studies_enabled(): bool
{
    return nsc_feature_truthy(Options::getGlobal('NSCFeatures', 'feature_case_studies'));
}

/**
 * @return bool
 */
function nsc_feature_language_enabled(): bool
{
    return nsc_feature_truthy(Options::getGlobal('NSCFeatures', 'feature_language'));
}

/**
 * Whether header / nav should show any language-switcher UI (Twig block + Polylang “Languages” menu items).
 * Requires the feature on and at least two Polylang languages; one language has nothing to switch to.
 */
function nsc_should_show_language_switcher_ui(): bool
{
    if (!\function_exists('nsc_feature_language_enabled') || !\nsc_feature_language_enabled()) {
        return false;
    }
    if (!\function_exists('pll_languages_list')) {
        return false;
    }
    $slugs = \pll_languages_list(['hide_empty' => false, 'fields' => 'slug']);
    if (!\is_array($slugs)) {
        return false;
    }

    return \count($slugs) >= 2;
}

/**
 * @param mixed $v
 */
function nsc_feature_truthy($v): bool
{
    return $v === true || $v === 1 || $v === '1';
}

/**
 * Whether a nav menu item is Polylang’s language switcher (placeholder or items expanded from it on the front).
 *
 * @param object $item wp_nav_menu / Timber item.
 */
function nsc_nav_menu_item_is_polylang_language_switcher(object $item): bool
{
    $classes = isset($item->classes) && \is_array($item->classes) ? $item->classes : [];
    if (\in_array('pll-parent-menu-item', $classes, true)) {
        return true;
    }
    if (isset($item->lang) && \is_string($item->lang) && $item->lang !== '') {
        return true;
    }
    $url = '';
    if (isset($item->url)) {
        $url = (string) $item->url;
    } elseif (isset($item->link)) {
        $url = (string) $item->link;
    }
    if ($url === '#pll_switcher') {
        return true;
    }
    $rawId = isset($item->ID) ? $item->ID : (isset($item->id) ? $item->id : null);
    if ($rawId !== null && \is_numeric($rawId)) {
        $pllMeta = \get_post_meta((int) $rawId, '_pll_menu_item', true);
        if (!empty($pllMeta)) {
            return true;
        }
    }

    return false;
}

/**
 * Strip Polylang language-switcher entries from menus when the switcher UI should not show
 * (feature off or fewer than two languages). Polylang expands the block in wp_get_nav_menu_items (priority 20).
 *
 * @param mixed $items
 * @return mixed
 */
function nsc_filter_nav_menu_remove_polylang_language_switcher($items, $menu, $args)
{
    if (\is_admin() || !\function_exists('nsc_should_show_language_switcher_ui') || \nsc_should_show_language_switcher_ui()) {
        return $items;
    }
    if (!\is_array($items)) {
        return $items;
    }

    return \array_values(\array_filter($items, static function ($item): bool {
        return !\is_object($item) || !\nsc_nav_menu_item_is_polylang_language_switcher($item);
    }));
}

add_filter('wp_get_nav_menu_items', 'nsc_filter_nav_menu_remove_polylang_language_switcher', 25, 3);

/**
 * Page IDs for a canonical seed slug (default language + Polylang translations).
 *
 * @return array<int, int>
 */
function nsc_get_feature_page_translation_ids(string $canonicalSlug): array
{
    static $cache = [];
    if (isset($cache[$canonicalSlug])) {
        return $cache[$canonicalSlug];
    }
    $qargs = [
        'name' => $canonicalSlug,
        'post_type' => 'page',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'no_found_rows' => true,
        'suppress_filters' => false,
    ];
    if (\function_exists('pll_default_language')) {
        $lang = \pll_default_language('slug');
        if (\is_string($lang) && $lang !== '') {
            $qargs['lang'] = $lang;
        }
    }
    $posts = \get_posts($qargs);
    if (!$posts || !($posts[0] instanceof \WP_Post)) {
        $cache[$canonicalSlug] = [];

        return [];
    }
    $baseId = (int) $posts[0]->ID;
    $ids = [$baseId];
    if (\function_exists('pll_get_post_translations')) {
        $tr = \pll_get_post_translations($baseId);
        if (\is_array($tr)) {
            foreach ($tr as $tid) {
                $tid = (int) $tid;
                if ($tid > 0) {
                    $ids[] = $tid;
                }
            }
        }
    }
    $out = \array_values(\array_unique($ids));
    $cache[$canonicalSlug] = $out;

    return $out;
}

/**
 * Whether the current singular page is the given feature landing (any translation).
 */
function nsc_is_queried_page_in_feature_group(string $canonicalSlug): bool
{
    if (!\is_page()) {
        return false;
    }
    $pid = \get_queried_object_id();

    return \in_array($pid, nsc_get_feature_page_translation_ids($canonicalSlug), true);
}

/**
 * @param array<int, \WP_Post> $items
 * @return array<int, \WP_Post>
 */
function nsc_filter_nav_menu_objects_by_features(array $items): array
{
    $sets = [
        'blogs' => static function (): bool {
            return nsc_feature_blog_enabled();
        },
        'career' => static function (): bool {
            return nsc_feature_career_enabled();
        },
        'case-studies' => static function (): bool {
            return nsc_feature_case_studies_enabled();
        },
    ];
    $out = [];
    foreach ($items as $item) {
        if (!($item instanceof \WP_Post)) {
            continue;
        }
        if ($item->type === 'post_type' && $item->object === 'page') {
            $oid = (int) $item->object_id;
            foreach ($sets as $slug => $enabledFn) {
                $ids = nsc_get_feature_page_translation_ids($slug);
                if ($ids !== [] && \in_array($oid, $ids, true) && !$enabledFn()) {
                    continue 2;
                }
            }
        }
        $out[] = $item;
    }

    return $out;
}

add_filter('wp_nav_menu_objects', static function (array $items, $args): array {
    $loc = '';
    if (\is_object($args) && isset($args->theme_location)) {
        $loc = (string) $args->theme_location;
    } elseif (\is_array($args) && isset($args['theme_location'])) {
        $loc = (string) $args['theme_location'];
    }
    if (!\in_array($loc, ['navigation_main', 'sitemap_footer', 'navigation_footer'], true)) {
        return $items;
    }

    return nsc_filter_nav_menu_objects_by_features($items);
}, 10, 2);

/**
 * Timber loads menus via wp_get_nav_menu_items(), which does not always invoke wp_nav_menu_objects.
 * Filter here on the front end only so wp-admin → Appearance → Menus stays complete.
 *
 * @param array<int, \WP_Post> $items
 * @return array<int, \WP_Post>
 */
add_filter('wp_get_nav_menu_items', static function ($items, $menu, $args) {
    if (\is_admin()) {
        return $items;
    }
    if (!\is_array($items)) {
        return $items;
    }

    return nsc_filter_nav_menu_objects_by_features($items);
}, 10, 3);

/**
 * @param object|null $menu Timber menu
 * @return object|null
 */
function nsc_features_filter_timber_menu($menu)
{
    if (!$menu || !isset($menu->items) || !\is_array($menu->items)) {
        return $menu;
    }
    $menu->items = nsc_features_filter_timber_menu_items($menu->items);

    return $menu;
}

/**
 * @param array<int, object> $items
 * @return array<int, object>
 */
function nsc_features_filter_timber_menu_items(array $items): array
{
    $out = [];
    foreach ($items as $item) {
        if (!\is_object($item)) {
            continue;
        }
        if (\function_exists('nsc_should_show_language_switcher_ui') && !\nsc_should_show_language_switcher_ui() && \nsc_nav_menu_item_is_polylang_language_switcher($item)) {
            continue;
        }
        $object = isset($item->object) ? (string) $item->object : '';
        $objectId = isset($item->object_id) ? (int) $item->object_id : 0;
        if ($object === 'page' && $objectId > 0) {
            foreach (['blogs' => 'nsc_feature_blog_enabled', 'career' => 'nsc_feature_career_enabled', 'case-studies' => 'nsc_feature_case_studies_enabled'] as $slug => $fn) {
                $ids = nsc_get_feature_page_translation_ids($slug);
                if ($ids !== [] && \in_array($objectId, $ids, true) && \function_exists($fn) && !$fn()) {
                    continue 2;
                }
            }
        }
        if (!empty($item->children) && \is_array($item->children)) {
            $item->children = nsc_features_filter_timber_menu_items($item->children);
        }
        $out[] = $item;
    }

    return $out;
}

/**
 * Force a hide flag in component options when a feature is off.
 *
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 */
function nsc_gate_component_option(array $data, string $optionKey): array
{
    $data['options'] = isset($data['options']) && \is_array($data['options']) ? $data['options'] : [];
    $data['options'][$optionKey] = 1;

    return $data;
}

add_filter('NscSoftware/addComponentData?name=NSCBlockBlogsHome', static function ($data) {
    if (!\is_array($data)) {
        $data = [];
    }
    if (!nsc_feature_blog_enabled()) {
        return nsc_gate_component_option($data, 'hiddenBlogHome');
    }

    return $data;
}, 25);

add_filter('NscSoftware/addComponentData?name=NSCBlockBlogsArchive', static function ($data) {
    if (!\is_array($data)) {
        $data = [];
    }
    if (!nsc_feature_blog_enabled()) {
        return nsc_gate_component_option($data, 'hidden');
    }

    return $data;
}, 25);

foreach (['NSCBlockJobsArchive', 'NSCBlockCareerCoreValues', 'NSCBlockCareerWeAreNsc'] as $gateComponent) {
    add_filter('NscSoftware/addComponentData?name=' . $gateComponent, static function ($data) use ($gateComponent) {
        if (!\is_array($data)) {
            $data = [];
        }
        if (!nsc_feature_career_enabled()) {
            $key = $gateComponent === 'NSCBlockJobsArchive' ? 'hiddenJobsArchive' : 'hidden';

            return nsc_gate_component_option($data, $key);
        }

        return $data;
    }, 25);
}

add_filter('NscSoftware/addComponentData?name=NSCBlockCaseStudiesArchive', static function ($data) {
    if (!\is_array($data)) {
        $data = [];
    }
    if (!nsc_feature_case_studies_enabled()) {
        return nsc_gate_component_option($data, 'hiddenCaseStudiesArchive');
    }

    return $data;
}, 25);

/**
 * ACF: when feature is off, force hide toggles on and lock the three gated fields (unique `name` per layout).
 */
function nsc_register_feature_gate_acf_hooks(): void
{
    $gates = [
        'hiddenBlogHome' => static function (): bool {
            return nsc_feature_blog_enabled();
        },
        'hiddenJobsArchive' => static function (): bool {
            return nsc_feature_career_enabled();
        },
        'hiddenCaseStudiesArchive' => static function (): bool {
            return nsc_feature_case_studies_enabled();
        },
    ];
    foreach ($gates as $fieldName => $enabledFn) {
        add_filter('acf/load_value/name=' . $fieldName, static function ($value) use ($enabledFn) {
            if (!$enabledFn()) {
                return 1;
            }

            return $value;
        }, 10, 3);

        add_filter('acf/update_value/name=' . $fieldName, static function ($value) use ($enabledFn) {
            if (!$enabledFn()) {
                return 1;
            }

            return $value;
        }, 10, 4);

        add_filter('acf/prepare_field/name=' . $fieldName, static function ($field) use ($enabledFn) {
            if (!\is_array($field)) {
                return $field;
            }
            if (!$enabledFn()) {
                $field['disabled'] = true;
                $field['readonly'] = true;
                $note = __('Controlled by NSC Theme Options → Global → Site features.', 'NscSoftware');
                $field['instructions'] = trim((string) ($field['instructions'] ?? '') . ' ' . $note);
            }

            return $field;
        });
    }
}

add_action('acf/init', static function (): void {
    nsc_register_feature_gate_acf_hooks();
}, 20);

add_action('template_redirect', static function (): void {
    if (\is_admin() || \wp_doing_ajax() || \wp_doing_cron()) {
        return;
    }
    if (!\function_exists('nsc_feature_blog_enabled')) {
        return;
    }
    $home = \home_url('/');

    if (!nsc_feature_blog_enabled()) {
        if (\is_singular('post')) {
            \wp_safe_redirect($home);
            exit;
        }
        if (\is_home() && !\is_front_page()) {
            \wp_safe_redirect($home);
            exit;
        }
        if (\is_category() || \is_tag() || \is_date() || \is_author()) {
            \wp_safe_redirect($home);
            exit;
        }
        if (nsc_is_queried_page_in_feature_group('blogs')) {
            \wp_safe_redirect($home);
            exit;
        }
    }

    if (!nsc_feature_career_enabled()) {
        if (\is_singular('job')) {
            \wp_safe_redirect($home);
            exit;
        }
        if (nsc_is_queried_page_in_feature_group('career')) {
            \wp_safe_redirect($home);
            exit;
        }
    }

    if (!nsc_feature_case_studies_enabled()) {
        if (\is_singular('case_study')) {
            \wp_safe_redirect($home);
            exit;
        }
        if (\is_post_type_archive('case_study')) {
            \wp_safe_redirect($home);
            exit;
        }
        if (\is_tax(\get_object_taxonomies('case_study'))) {
            $q = \get_queried_object();
            if ($q instanceof \WP_Term && isset($q->taxonomy) && \in_array($q->taxonomy, \get_object_taxonomies('case_study'), true)) {
                \wp_safe_redirect($home);
                exit;
            }
        }
        if (nsc_is_queried_page_in_feature_group('case-studies')) {
            \wp_safe_redirect($home);
            exit;
        }
    }
}, 1);
