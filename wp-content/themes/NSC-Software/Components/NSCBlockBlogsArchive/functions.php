<?php

namespace NscSoftware\Components\NSCBlockBlogsArchive;

use NscSoftware\FieldVariables;
use NscSoftware\Utils\Options;
use Timber\Timber;

const POST_TYPE = 'post';
const TAXONOMY = 'category';

add_filter('NscSoftware/addComponentData?name=NSCBlockBlogsArchive', function ($data) {
    $data['uuid'] = $data['uuid'] ?? wp_generate_uuid4();
    $postsPerPage = (int) ($data['postsPerPage'] ?? $data['options']['postsPerPage'] ?? 12);
    // On a static Page, paged is not in main query; use request (e.g. /blogs/?paged=2)
    $paged = get_query_var('paged');
    if (!$paged && isset($_GET['paged'])) {
        $paged = (int) $_GET['paged'];
    }
    $paged = max(1, (int) $paged);

    // Build base query args for posts
    $args = [
        'post_status' => 'publish',
        'post_type' => POST_TYPE,
        'posts_per_page' => $postsPerPage,
        'paged' => $paged,
        'ignore_sticky_posts' => 1,
    ];

    // Category filter: from query var or GET (when using static page as blog archive)
    $cat = get_query_var('cat') ?: (isset($_GET['cat']) ? (int) $_GET['cat'] : 0);
    $categoryName = get_query_var('category_name') ?: (isset($_GET['category_name']) ? sanitize_title($_GET['category_name']) : '');
    if ($cat) {
        $args['cat'] = $cat;
    } elseif ($categoryName) {
        $args['category_name'] = $categoryName;
    }

    $query = new \WP_Query($args);
    $timberPosts = Timber::get_posts($args);
    $data['posts'] = is_object($timberPosts) && method_exists($timberPosts, 'to_array')
        ? $timberPosts->to_array()
        : (array) $timberPosts;

    // Pagination (static page: use ?paged=n)
    $baseUrl = get_permalink(get_the_ID());
    $sep = strpos($baseUrl, '?') !== false ? '&' : '?';
    $data['pagination'] = [
        'current' => $paged,
        'total' => (int) $query->max_num_pages,
        'prev' => null,
        'next' => null,
    ];
    if ($paged > 1) {
        $data['pagination']['prev'] = (object) [
            'link' => $baseUrl . $sep . 'paged=' . ($paged - 1),
        ];
    }
    if ($paged < $data['pagination']['total']) {
        $data['pagination']['next'] = (object) [
            'link' => $baseUrl . $sep . 'paged=' . ($paged + 1),
        ];
    }

    // Terms for filter (categories) – links use current page + query for static Blogs page
    $terms = get_terms([
        'taxonomy' => TAXONOMY,
        'hide_empty' => true,
    ]);
    $currentPageUrl = get_permalink(get_the_ID());
    $queriedSlug = $categoryName ?: '';
    $data['terms'] = [];
    $data['terms'][] = (object) [
        'link' => $currentPageUrl,
        'title' => $data['labels']['allPosts'] ?? __('All', 'NscSoftware'),
        'isActive' => empty($cat) && empty($categoryName),
    ];
    foreach ($terms as $term) {
        $timberTerm = Timber::get_term($term);
        $timberTerm->link = $currentPageUrl . (strpos($currentPageUrl, '?') !== false ? '&' : '?') . 'category_name=' . $term->slug;
        $timberTerm->isActive = ($queriedSlug && $term->slug === $queriedSlug) || ($cat && (int) $cat === $term->term_id);
        $data['terms'][] = $timberTerm;
    }

    $data['labels'] = Options::getTranslatable('NSCBlockBlogsArchive')['labels'] ?? [];
    $data['buildUri'] = trailingslashit(get_template_directory_uri()) . 'frontend/build';
    $data['loadMore'] = Options::getGlobal('NSCBlockBlogsArchive')['loadMore'] ?? false;

    return $data;
});

// Allow paged and category on the Blogs page
add_action('init', function () {
    add_rewrite_tag('%paged%', '([^/]+)');
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'cat';
    $vars[] = 'category_name';
    return $vars;
});

Options::addGlobal('NSCBlockBlogsArchive', [
    [
        'label' => __('Load More button', 'NscSoftware'),
        'name' => 'loadMore',
        'type' => 'true_false',
        'default_value' => 0,
        'ui' => 1,
    ],
]);

Options::addTranslatable('NSCBlockBlogsArchive', [
    [
        'label' => __('Labels', 'NscSoftware'),
        'name' => 'labelsTab',
        'type' => 'tab',
        'placement' => 'top',
        'endpoint' => 0,
    ],
    [
        'label' => '',
        'name' => 'labels',
        'type' => 'group',
        'sub_fields' => [
            [
                'label' => __('Filter by', 'NscSoftware'),
                'name' => 'filterBy',
                'type' => 'text',
                'default_value' => __('Filter by', 'NscSoftware'),
            ],
            [
                'label' => __('All', 'NscSoftware'),
                'name' => 'allPosts',
                'type' => 'text',
                'default_value' => __('All', 'NscSoftware'),
            ],
            [
                'label' => __('Read More', 'NscSoftware'),
                'name' => 'readMore',
                'type' => 'text',
                'default_value' => __('Read More', 'NscSoftware'),
            ],
            [
                'label' => __('Previous', 'NscSoftware'),
                'name' => 'previous',
                'type' => 'text',
                'default_value' => __('Prev', 'NscSoftware'),
            ],
            [
                'label' => __('Next', 'NscSoftware'),
                'name' => 'next',
                'type' => 'text',
                'default_value' => __('Next', 'NscSoftware'),
            ],
            [
                'label' => __('Load More', 'NscSoftware'),
                'name' => 'loadMore',
                'type' => 'text',
                'default_value' => __('Load More', 'NscSoftware'),
            ],
            [
                'label' => __('No posts found', 'NscSoftware'),
                'name' => 'noPostsFound',
                'type' => 'text',
                'default_value' => __('No post found.', 'NscSoftware'),
            ],
        ],
    ],
]);

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
            ],
            [
                'label' => __('Description', 'NscSoftware'),
                'name' => 'description',
                'type' => 'wysiwyg',
                'toolbar' => 'basic',
                'media_upload' => 0,
            ],
            [
                'label' => __('Options', 'NscSoftware'),
                'name' => 'optionsTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'label' => __('Posts per page', 'NscSoftware'),
                'name' => 'postsPerPage',
                'type' => 'number',
                'default_value' => 12,
                'min' => 1,
                'max' => 100,
            ],
            [
                'label' => '',
                'name' => 'options',
                'type' => 'group',
                'layout' => 'row',
                'sub_fields' => [
                    FieldVariables\getTheme(),
                    FieldVariables\getHidden(),
                ],
            ],
        ],
    ];
}
