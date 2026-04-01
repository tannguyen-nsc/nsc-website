<?php

namespace NscSoftware\Components\NSCBlockCaseStudiesArchive;

use NscSoftware\FieldVariables;
use Timber\Timber;

const POST_TYPE = 'case_study';
const TAXONOMY = 'case_study_category';
const VUE_POSTS_MAX = 500;

/**
 * @return array<string, string>
 */
function default_list_labels(): array
{
    return [
        'allCategoriesLabel' => __('All Categories', 'NscSoftware'),
        'readMore' => __('Read More', 'NscSoftware'),
        'previous' => __('Previous', 'NscSoftware'),
        'next' => __('Next', 'NscSoftware'),
        'emptyList' => __('No case studies found in this category.', 'NscSoftware'),
    ];
}

/**
 * @param array<string, mixed> $raw
 * @return array<string, string>
 */
function normalize_list_labels(array $raw): array
{
    $out = default_list_labels();
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

/**
 * First assigned category name for filters (matches Vue filter by name).
 */
function primary_case_study_category_name(int $postId): string
{
    $terms = get_the_terms($postId, TAXONOMY);
    if (empty($terms) || is_wp_error($terms)) {
        return '';
    }

    foreach ($terms as $term) {
        if ($term instanceof \WP_Term) {
            return (string) $term->name;
        }
    }

    return '';
}

/**
 * @return list<array{id:int,title:string,description:string,category:string,image:string,link:string}>
 */
function build_vue_case_study_items(string $placeholderImageUrl): array
{
    $args = [
        'post_status' => 'publish',
        'post_type' => POST_TYPE,
        'posts_per_page' => VUE_POSTS_MAX,
        'orderby' => 'date',
        'order' => 'DESC',
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

        $id = (int) $post->ID;
        $catName = primary_case_study_category_name($id);
        $img = \NscSoftware\Components\NSCBlockBlogsArchive\resolve_post_list_image_url($post, $placeholderImageUrl);
        $excerpt = \NscSoftware\Components\NSCBlockBlogsArchive\post_plain_excerpt($post);

        $items[] = [
            'id' => $id,
            'title' => html_entity_decode((string) $post->title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'description' => $excerpt,
            'category' => $catName,
            'image' => $img,
            'link' => (string) $post->link,
        ];
    }

    return $items;
}

add_filter('NscSoftware/addComponentData?name=NSCBlockCaseStudiesArchive', function ($data) {
    $buildUri = trailingslashit(get_template_directory_uri()) . 'frontend/build';
    $data['buildUri'] = $buildUri;
    $placeholderImage = $buildUri . '/img/blog1.webp';

    $opts = isset($data['options']) && is_array($data['options']) ? $data['options'] : [];
    $hideCategories = !empty($opts['hideCategories']);
    $rawPerPage = $opts['caseStudiesPerPage'] ?? null;
    $perPage = ($rawPerPage === null || $rawPerPage === '') ? 6 : (int) $rawPerPage;
    $perPage = max(1, min(48, $perPage));

    $listLabels = normalize_list_labels(
        is_array($data['listLabels'] ?? null) ? $data['listLabels'] : []
    );

    $vueStudies = build_vue_case_study_items($placeholderImage);

    $filterDefs = [];
    if (!$hideCategories) {
        $filterDefs[] = ['id' => 'all', 'label' => $listLabels['allCategoriesLabel']];
        $terms = get_terms([
            'taxonomy' => TAXONOMY,
            'hide_empty' => true,
        ]);
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                if (!$term instanceof \WP_Term) {
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
        'studies' => $vueStudies,
        'filters' => $filterDefs,
        'perPage' => $perPage,
        'labels' => [
            'readMore' => $listLabels['readMore'],
            'previous' => $listLabels['previous'],
            'next' => $listLabels['next'],
            'empty' => $listLabels['emptyList'],
        ],
    ];

    $encoded = wp_json_encode(
        $vuePayload,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
    $data['case_studies_vue_data_json'] = $encoded !== false
        ? $encoded
        : wp_json_encode(
            [
                'studies' => [],
                'filters' => [['id' => 'all', 'label' => __('All Categories', 'NscSoftware')]],
                'perPage' => $perPage,
                'labels' => ['empty' => $listLabels['emptyList']],
            ],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );

    return $data;
});

function getACFLayout(): array
{
    return [
        'name' => 'nscBlockCaseStudiesArchive',
        'label' => __('NSC Block: Case studies (Archive)', 'NscSoftware'),
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
                'default_value' => 'Case Studies',
            ],
            [
                'label' => __('List & filters', 'NscSoftware'),
                'name' => 'listLabels',
                'type' => 'group',
                'sub_fields' => [
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
                        'default_value' => 'Previous',
                    ],
                    [
                        'label' => __('Next (pagination)', 'NscSoftware'),
                        'name' => 'next',
                        'type' => 'text',
                        'default_value' => 'Next',
                    ],
                    [
                        'label' => __('Empty state (no results)', 'NscSoftware'),
                        'name' => 'emptyList',
                        'type' => 'textarea',
                        'rows' => 2,
                        'default_value' => 'No case studies found in this category.',
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
                    FieldVariables\getCaseStudiesListPerPageField(),
                    [
                        'label' => __('Hide categories filter', 'NscSoftware'),
                        'name' => 'hideCategories',
                        'type' => 'true_false',
                        'ui' => 1,
                        'default_value' => 0,
                        'instructions' => __('Hide category filter buttons in archive list (including All Categories).', 'NscSoftware'),
                    ],
                    FieldVariables\getHiddenCaseStudiesArchive(),
                ],
            ],
        ],
    ];
}
