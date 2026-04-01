<?php

namespace NscSoftware\Components\NSCBlockBlogsArchive;

use NscSoftware\FieldVariables;
use Timber\Timber;

const POST_TYPE = 'post';
const TAXONOMY = 'category';
const VUE_POSTS_MAX = 500;

/**
 * ACF true_false stores "1"/"0"; include legacy "yes"/"true" values too.
 *
 * @return array<string, mixed>
 */
function featured_article_yes_meta_query(): array
{
    return [
        'relation' => 'OR',
        [
            'key' => 'nsc_featured_article',
            'value' => '1',
            'compare' => '=',
        ],
        [
            'key' => 'nsc_featured_article',
            'value' => 'yes',
            'compare' => '=',
        ],
        [
            'key' => 'nsc_featured_article',
            'value' => 'true',
            'compare' => '=',
        ],
    ];
}

/**
 * @return array<string, string>
 */
function default_archive_list_labels(): array
{
    return [
        'blogsListHeading' => __('Blogs', 'NscSoftware'),
        'searchPlaceholder' => __('Search', 'NscSoftware'),
        'searchResultSingular' => __('result', 'NscSoftware'),
        'searchResultPlural' => __('results', 'NscSoftware'),
        'allCategoriesLabel' => __('All Categories', 'NscSoftware'),
        'readMore' => __('Read More', 'NscSoftware'),
        'previous' => __('Prev', 'NscSoftware'),
        'next' => __('Next', 'NscSoftware'),
        'noBlogFound' => __('No blog found.', 'NscSoftware'),
    ];
}

/**
 * @param array<string, mixed> $raw
 * @return array<string, string>
 */
function normalize_archive_list_labels(array $raw): array
{
    $out = default_archive_list_labels();
    foreach ($out as $key => $default) {
        if (!array_key_exists($key, $raw)) {
            continue;
        }

        $v = $raw[$key];
        if (is_string($v) && $v !== '') {
            $out[$key] = $v;
        }
    }

    return $out;
}

function is_uncategorized_category(\WP_Term $term): bool
{
    return $term->taxonomy === 'category' && $term->slug === 'uncategorized';
}

/**
 * First non-Uncategorized category name, or empty.
 */
function primary_public_category_name(int $postId): string
{
    $cats = get_the_category($postId);
    if (empty($cats) || !is_array($cats)) {
        return '';
    }

    foreach ($cats as $cat) {
        if (!$cat instanceof \WP_Term) {
            continue;
        }

        if ($cat->slug === 'uncategorized') {
            continue;
        }

        return (string) $cat->name;
    }

    return '';
}

function is_seeded_post(int $postId): bool
{
    if ($postId <= 0) {
        return false;
    }

    $raw = get_post_meta($postId, 'is_seeded', true);
    if (is_bool($raw)) {
        return $raw;
    }
    if (is_numeric($raw)) {
        return ((int) $raw) === 1;
    }
    if (is_string($raw)) {
        $v = strtolower(trim($raw));
        return in_array($v, ['1', 'yes', 'true', 'on'], true);
    }

    return false;
}

/**
 * Featured image URL: Timber thumbnail, else WP featured image, else placeholder.
 *
 * @param object $post Timber Post-like with ID, optional thumbnail
 */
function resolve_post_list_image_url($post, string $placeholderUrl): string
{
    if (!is_object($post) || !isset($post->ID)) {
        return $placeholderUrl;
    }

    $id = (int) $post->ID;
    if (!empty($post->thumbnail)) {
        $thumb = $post->thumbnail;
        if (is_object($thumb) && !empty($thumb->src)) {
            return (string) $thumb->src;
        }
    }

    $url = get_the_post_thumbnail_url($id, 'large');
    if ($url) {
        return $url;
    }

    $url = get_the_post_thumbnail_url($id, 'medium_large');
    if ($url) {
        return $url;
    }

    return is_seeded_post($id) ? $placeholderUrl : '';
}

/**
 * Optional square thumbnail URL from post field.
 */
function resolve_post_square_image_url(int $postId): string
{
    if ($postId <= 0) {
        return '';
    }

    $value = function_exists('get_field') ? get_field('nsc_square_thumbnail', $postId) : null;
    if (is_array($value)) {
        $url = isset($value['url']) ? trim((string) $value['url']) : '';
        if ($url !== '') {
            return $url;
        }
    }

    if (is_numeric($value)) {
        $url = wp_get_attachment_image_url((int) $value, 'medium_large');
        if (is_string($url) && $url !== '') {
            return $url;
        }

        $url = wp_get_attachment_url((int) $value);
        if (is_string($url) && $url !== '') {
            return $url;
        }
    }

    if (is_string($value)) {
        $url = trim($value);
        if ($url !== '') {
            return $url;
        }
    }

    // Fallback for environments where ACF returns empty but meta stores attachment ID.
    $raw = get_post_meta($postId, 'nsc_square_thumbnail', true);
    if (is_numeric($raw)) {
        $url = wp_get_attachment_image_url((int) $raw, 'medium_large');
        if (is_string($url) && $url !== '') {
            return $url;
        }

        $url = wp_get_attachment_url((int) $raw);
        if (is_string($url) && $url !== '') {
            return $url;
        }
    }

    if (is_string($raw)) {
        $raw = trim($raw);
        if ($raw !== '' && filter_var($raw, FILTER_VALIDATE_URL)) {
            return $raw;
        }
    }

    return '';
}

/**
 * @param array<int, mixed> $rows
 */
function flexible_rows_include_blogs_archive(array $rows): bool
{
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        if (($row['acf_fc_layout'] ?? '') === 'nscBlockBlogsArchive') {
            return true;
        }
    }

    return false;
}

/**
 * True when the main query is a singular Page whose flexible content includes Blogs (Archive).
 * Uses get_field when available; falls back to raw meta scan so enqueue / detection still works if ACF
 * returns an unexpected shape for pageComponents.
 */
function current_page_includes_blogs_archive_block(): bool
{
    if (!is_singular('page')) {
        return false;
    }

    $pageId = (int) get_queried_object_id();
    if ($pageId <= 0) {
        return false;
    }

    if (function_exists('get_field')) {
        $components = get_field('pageComponents', $pageId);
        if (is_array($components) && flexible_rows_include_blogs_archive($components)) {
            return true;
        }
    }

    // Seeded blogs page slug from create-nsc-pages.php
    if (is_page(['blogs', 'blog'])) {
        return true;
    }

    // Last resort: serialized flexible rows in post meta (layout name stored as string).
    $raw = get_post_meta($pageId, 'pageComponents', true);
    if (is_array($raw) && flexible_rows_include_blogs_archive($raw)) {
        return true;
    }

    $allMeta = get_post_meta($pageId);
    if (is_array($allMeta)) {
        foreach ($allMeta as $values) {
            foreach ((array) $values as $v) {
                if (is_string($v) && strpos($v, 'nscBlockBlogsArchive') !== false) {
                    return true;
                }
            }
        }
    }

    return false;
}

/**
 * @param object $post Timber Post-like object with ID, content
 */
function post_plain_excerpt($post, int $maxLen = 280): string
{
    $id = (int) $post->ID;
    $text = get_the_excerpt($id);
    if ($text === '') {
        $text = (string) ($post->content ?? '');
    }

    $text = wp_strip_all_tags($text);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    if ($maxLen > 0 && strlen($text) > $maxLen) {
        $text = substr($text, 0, $maxLen) . '…';
    }

    return $text;
}

/**
 * @return list<array{id:int,title:string,excerpt:string,category:string,image:string,squareImage:string,link:string}>
 */
function build_vue_blog_items(string $placeholderImageUrl): array
{
    $args = [
        'post_status' => 'publish',
        'post_type' => POST_TYPE,
        'posts_per_page' => VUE_POSTS_MAX,
        'orderby' => 'date',
        'order' => 'DESC',
        'ignore_sticky_posts' => 1,
    ];
    $collection = Timber::get_posts($args);
    $posts = is_object($collection) && method_exists($collection, 'to_array')
        ? $collection->to_array()
        : (array) $collection;

    $items = [];
    foreach ($posts as $post) {
        if (!is_object($post) || !isset($post->ID)) {
            continue;
        }

        $catName = primary_public_category_name((int) $post->ID);
        $img = resolve_post_list_image_url($post, $placeholderImageUrl);

        $squareImg = resolve_post_square_image_url((int) $post->ID);

        $items[] = [
            'id' => (int) $post->ID,
            'title' => html_entity_decode((string) $post->title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'excerpt' => post_plain_excerpt($post),
            'category' => $catName,
            'image' => $img,
            'squareImage' => $squareImg,
            'link' => (string) $post->link,
        ];
    }

    return $items;
}

/**
 * @return list<Post>
 */
function get_featured_posts_for_archive(int $limit = 4): array
{
    $limit = max(1, min(24, $limit));
    $q = [
        'post_type' => POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'ignore_sticky_posts' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
        'meta_query' => featured_article_yes_meta_query(),
    ];
    $featured = Timber::get_posts($q);
    return is_object($featured) && method_exists($featured, 'to_array')
        ? $featured->to_array()
        : (array) $featured;
}

add_filter('NscSoftware/addComponentData?name=NSCBlockBlogsArchive', function ($data) {
    $data['uuid'] = $data['uuid'] ?? wp_generate_uuid4();

    $buildUri = trailingslashit(get_template_directory_uri()) . 'frontend/build';
    $data['buildUri'] = $buildUri;
    $placeholderImage = $buildUri . '/img/blog1.webp';

    $opts = isset($data['options']) && is_array($data['options']) ? $data['options'] : [];
    $hideCategories = !empty($opts['hideCategories']);
    $rawFeaturedLimit = $opts['featuredPostsLimit'] ?? null;
    $featuredLimit = ($rawFeaturedLimit === null || $rawFeaturedLimit === '') ? 4 : (int) $rawFeaturedLimit;
    $featuredLimit = max(1, min(24, $featuredLimit));
    $rawBlogListPerPage = $opts['blogListPerPage'] ?? null;
    $blogListPerPage = ($rawBlogListPerPage === null || $rawBlogListPerPage === '') ? 6 : (int) $rawBlogListPerPage;
    $blogListPerPage = max(1, min(48, $blogListPerPage));

    $listLabels = normalize_archive_list_labels(
        is_array($data['listLabels'] ?? null) ? $data['listLabels'] : []
    );

    $data['blogsListHeading'] = $listLabels['blogsListHeading'];
    $data['labels'] = [
        'readMore' => $listLabels['readMore'],
    ];

    $vueBlogs = build_vue_blog_items($placeholderImage);

    $filterDefs = [];
    if (!$hideCategories) {
        $filterDefs[] = ['id' => 'all', 'label' => $listLabels['allCategoriesLabel']];
        $terms = get_terms([
            'taxonomy' => TAXONOMY,
            'hide_empty' => true,
        ]);
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                if (!$term instanceof \WP_Term || is_uncategorized_category($term)) {
                    continue;
                }

                $filterDefs[] = [
                    'id' => $term->name,
                    'label' => $term->name,
                ];
            }
        }
    }

    $vuePayload = [
        'blogs' => $vueBlogs,
        'filters' => $filterDefs,
        'perPage' => $blogListPerPage,
        'labels' => [
            'searchPlaceholder' => $listLabels['searchPlaceholder'],
            'searchResultSingular' => $listLabels['searchResultSingular'],
            'searchResultPlural' => $listLabels['searchResultPlural'],
            'readMore' => $listLabels['readMore'],
            'previous' => $listLabels['previous'],
            'next' => $listLabels['next'],
            'empty' => $listLabels['noBlogFound'],
        ],
    ];

    $encoded = wp_json_encode(
        $vuePayload,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
    $data['blog_vue_data_json'] = $encoded !== false
        ? $encoded
        : wp_json_encode(
            [
                'blogs' => [],
                'filters' => [['id' => 'all', 'label' => __('All Categories', 'NscSoftware')]],
                'perPage' => $blogListPerPage,
                'labels' => [
                    'empty' => __('No blog found.', 'NscSoftware'),
                ],
            ],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );

    $featuredTimber = get_featured_posts_for_archive($featuredLimit);
    $data['featuredPosts'] = $featuredTimber;

    $data['posts'] = [];
    $data['pagination'] = ['current' => 1, 'total' => 1, 'prev' => null, 'next' => null];
    $data['terms'] = [];

    return $data;
});

add_action('init', function () {
    add_rewrite_tag('%paged%', '([^/]+)');
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'cat';
    $vars[] = 'category_name';

    return $vars;
});

function getACFLayout()
{
    return [
        'name' => 'nscBlockBlogsArchive',
        'label' => __('NSC Block: Blogs (Archive)', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'label' => __('Heading icon', 'NscSoftware'),
                'name' => 'headingIcon',
                'type' => 'image',
                'preview_size' => 'thumbnail',
                'return_format' => 'array',
            ],
            [
                'label' => __('Section title', 'NscSoftware'),
                'name' => 'title',
                'type' => 'text',
                'default_value' => 'BLOGS',
                'instructions' => __('Shown in the top heading row (with icon).', 'NscSoftware'),
            ],
            [
                'label' => __('Description', 'NscSoftware'),
                'name' => 'description',
                'type' => 'wysiwyg',
                'toolbar' => 'basic',
                'media_upload' => 0,
                'instructions' => __('Optional. Not shown in the archive layout (matches static blogs build). Use the Hero block above for intro text.', 'NscSoftware'),
            ],
            [
                'label' => __('Blog list & filters', 'NscSoftware'),
                'name' => 'listLabels',
                'type' => 'group',
                'instructions' => __('Labels for the searchable blog list, filters, and pagination.', 'NscSoftware'),
                'sub_fields' => [
                    [
                        'label' => __('Blogs list heading', 'NscSoftware'),
                        'name' => 'blogsListHeading',
                        'type' => 'text',
                        'default_value' => 'Blogs',
                        'instructions' => __('Heading above the searchable list (below Featured).', 'NscSoftware'),
                    ],
                    [
                        'label' => __('Search placeholder', 'NscSoftware'),
                        'name' => 'searchPlaceholder',
                        'type' => 'text',
                        'default_value' => 'Search',
                    ],
                    [
                        'label' => __('Search: word after count when 1 match', 'NscSoftware'),
                        'name' => 'searchResultSingular',
                        'type' => 'text',
                        'default_value' => 'result',
                        'instructions' => __('e.g. “1 result”.', 'NscSoftware'),
                    ],
                    [
                        'label' => __('Search: word after count when 0 or 2+ matches', 'NscSoftware'),
                        'name' => 'searchResultPlural',
                        'type' => 'text',
                        'default_value' => 'results',
                        'instructions' => __('e.g. “0 results”, “5 results”.', 'NscSoftware'),
                    ],
                    [
                        'label' => __('All categories (filter button)', 'NscSoftware'),
                        'name' => 'allCategoriesLabel',
                        'type' => 'text',
                        'default_value' => 'All Categories',
                    ],
                    [
                        'label' => __('Read more', 'NscSoftware'),
                        'name' => 'readMore',
                        'type' => 'text',
                        'default_value' => 'Read More',
                    ],
                    [
                        'label' => __('Previous (pagination)', 'NscSoftware'),
                        'name' => 'previous',
                        'type' => 'text',
                        'default_value' => 'Prev',
                    ],
                    [
                        'label' => __('Next (pagination)', 'NscSoftware'),
                        'name' => 'next',
                        'type' => 'text',
                        'default_value' => 'Next',
                    ],
                    [
                        'label' => __('No blog found (empty list)', 'NscSoftware'),
                        'name' => 'noBlogFound',
                        'type' => 'text',
                        'default_value' => 'No blog found.',
                    ],
                ],
            ],
            [
                'label' => __('Options', 'NscSoftware'),
                'name' => 'optionsTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'label' => '',
                'name' => 'options',
                'type' => 'group',
                'layout' => 'row',
                'sub_fields' => [
                    FieldVariables\getArchiveFeaturedPostsLimitField(),
                    FieldVariables\getArchiveBlogListPerPageField(),
                    [
                        'label' => __('Hide categories filter', 'NscSoftware'),
                        'name' => 'hideCategories',
                        'type' => 'true_false',
                        'ui' => 1,
                        'default_value' => 0,
                        'instructions' => __('Hide category filter buttons in archive list (including All Categories).', 'NscSoftware'),
                    ],
                    FieldVariables\getHidden(),
                ],
            ],
        ],
    ];
}
