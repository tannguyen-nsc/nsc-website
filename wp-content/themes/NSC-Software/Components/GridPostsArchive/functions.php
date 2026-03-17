<?php

namespace NscSoftware\Components\GridPostsArchive;

use NscSoftware\FieldVariables;
use NscSoftware\Utils\Options;
use Timber\Timber;

const POST_TYPE = 'post';
const FILTER_BY_TAXONOMY = 'category';

add_filter('NscSoftware/addComponentData?name=GridPostsArchive', function ($data) {
    $data['uuid'] = $data['uuid'] ?? wp_generate_uuid4();
    $postType = POST_TYPE;
    $taxonomy = FILTER_BY_TAXONOMY;
    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => true,
    ]);
    $queriedObject = get_queried_object();
    if (count($terms) > 1) {
        $data['terms'] = array_map(function ($term) use ($queriedObject) {
            $timberTerm = Timber::get_term($term);
            if ($queriedObject->taxonomy ?? null) {
                $timberTerm->isActive = $queriedObject->taxonomy === $term->taxonomy && $queriedObject->term_id === $term->term_id;
            }
            return $timberTerm;
        }, $terms);
        // Add item for all posts
        array_unshift($data['terms'], [
            'link' => get_post_type_archive_link($postType),
            'title' => $data['labels']['allPosts'],
            'isActive' => is_home() || is_post_type_archive($postType),
        ]);
    }

    if (is_home()) {
        $data['isHome'] = true;
        $data['title'] = $queriedObject->post_title ?? get_bloginfo('name');
    } else {
        $data['title'] =  get_the_archive_title();
        $data['description'] = get_the_archive_description();
    }

    return $data;
});

Options::addGlobal('GridPostsArchive', [
    [
        'label' => __('Load More Button?', 'NscSoftware'),
        'name' => 'loadMore',
        'type' => 'true_false',
        'default_value' => 0,
        'ui' => 1
    ],
]);

Options::addTranslatable('GridPostsArchive', [
    [
        'label' => __('Content', 'NscSoftware'),
        'name' => 'general',
        'type' => 'tab',
        'placement' => 'top',
        'endpoint' => 0,
    ],
    [
        'label' => __('Title', 'NscSoftware'),
        'instructions' => __('Want to add a headline? And a paragraph? Go ahead! Or just leave it empty and nothing will be shown.', 'NscSoftware'),
        'name' => 'preContentHtml',
        'type' => 'wysiwyg',
        'tabs' => 'visual,text',
        'media_upload' => 0,
        'delay' => 0,
    ],
    [
        'label' => __('Labels', 'NscSoftware'),
        'name' => 'labelsTab',
        'type' => 'tab',
        'placement' => 'top',
        'endpoint' => 0
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
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('Previous', 'NscSoftware'),
                'name' => 'previous',
                'type' => 'text',
                'default_value' => __('Prev', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('Next', 'NscSoftware'),
                'name' => 'next',
                'type' => 'text',
                'default_value' => __('Next', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('Load More', 'NscSoftware'),
                'name' => 'loadMore',
                'type' => 'text',
                'default_value' => __('Load More', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('No Posts Found Text', 'NscSoftware'),
                'name' => 'noPostsFound',
                'type' => 'text',
                'default_value' => __('No post found.', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('All Posts', 'NscSoftware'),
                'name' => 'allPosts',
                'type' => 'text',
                'default_value' => __('All', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('Reading Time - (20) min read', 'NscSoftware'),
                'instructions' => __('%d is placeholder for number of minutes', 'NscSoftware'),
                'name' => 'readingTime',
                'type' => 'text',
                'default_value' => __('%d min read', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => 50
                ],
            ],
            [
                'label' => __('Read More', 'NscSoftware'),
                'name' => 'readMore',
                'type' => 'text',
                'default_value' => __('Read More', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ]
        ],
    ],
    [
        'label' => __('Options', 'NscSoftware'),
        'name' => 'optionsTab',
        'type' => 'tab',
        'placement' => 'top',
        'endpoint' => 0
    ],
    [
        'label' => '',
        'name' => 'options',
        'type' => 'group',
        'layout' => 'row',
        'sub_fields' => [
            FieldVariables\getTheme()
        ]
    ],
]);
