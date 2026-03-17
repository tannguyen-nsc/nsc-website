<?php

namespace NscSoftware\Components\GridPostsLatest;

use NscSoftware\FieldVariables;
use NscSoftware\Utils\Options;
use Timber\Timber;

const POST_TYPE = 'post';

add_filter('NscSoftware/addComponentData?name=GridPostsLatest', function ($data) {
    $data['uuid'] = $data['uuid'] ?? wp_generate_uuid4();
    $data['taxonomies'] = $data['taxonomies'] ?? [];
    $data['options']['maxColumns'] = 3;
    $postsPerPage = $data['options']['maxPosts'] ?? 3;

    $posts = Timber::get_posts([
        'post_status' => 'publish',
        'post_type' => POST_TYPE,
        'cat' => join(',', array_map(function ($taxonomy) {
            return $taxonomy->term_id;
        }, $data['taxonomies'])),
        'posts_per_page' => $postsPerPage + 1,
        'ignore_sticky_posts' => 1,
    ]);

    $data['posts'] = array_slice(array_filter($posts->to_array(), function ($post) {
        return $post->ID !== get_the_ID();
    }), 0, $postsPerPage);

    $data['postTypeArchiveLink'] = get_permalink(get_option('page_for_posts')) ?? get_post_type_archive_link(POST_TYPE);

    return $data;
});

function getACFLayout()
{
    return [
        'name' => 'gridPostsLatest',
        'label' => __('Grid: Posts Latest', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0
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
                'label' => __('Categories', 'NscSoftware'),
                'instructions' => __('Select 1 or more categories or leave empty to show from all posts.', 'NscSoftware'),
                'name' => 'taxonomies',
                'type' => 'taxonomy',
                'taxonomy' => 'category',
                'field_type' => 'multi_select',
                'allow_null' => 1,
                'multiple' => 1,
                'add_term' => 0,
                'save_terms' => 0,
                'load_terms' => 0,
                'return_format' => 'object'
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
                    FieldVariables\getTheme(),
                    [
                        'label' => __('Max Posts', 'NscSoftware'),
                        'name' => 'maxPosts',
                        'type' => 'number',
                        'default_value' => 3,
                        'min' => 1,
                        'step' => 1
                    ]
                ]
            ],
        ]
    ];
}

Options::addTranslatable('GridPostsLatest', [
    [
        'label' => __('Content', 'NscSoftware'),
        'name' => 'contentTab',
        'type' => 'tab',
        'placement' => 'top',
        'endpoint' => 0
    ],
    [
        'label' => __('Title', 'NscSoftware'),
        'instructions' => __('Want to add a headline? And a paragraph? Go ahead! Or just leave it empty and nothing will be shown.', 'NscSoftware'),
        'name' => 'preContentHtml',
        'type' => 'wysiwyg',
        'default_value' => '<h2>' . __('Related Posts', 'NscSoftware') . '</h2>',
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
                'label' => __('All Posts', 'NscSoftware'),
                'name' => 'allPosts',
                'type' => 'text',
                'default_value' => __('See More Posts', 'NscSoftware'),
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
                    'width' => 50
                ],
            ]
        ],
    ]
]);
