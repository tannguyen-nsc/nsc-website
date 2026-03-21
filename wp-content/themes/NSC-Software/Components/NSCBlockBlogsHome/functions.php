<?php

namespace NscSoftware\Components\NSCBlockBlogsHome;

use NscSoftware\FieldVariables;
use Timber\Timber;

const POST_TYPE = 'post';

add_filter('NscSoftware/addComponentData?name=NSCBlockBlogsHome', function ($data) {
    $blogPageUrl = get_permalink(get_option('page_for_posts')) ?: get_post_type_archive_link(POST_TYPE);
    $currentPostId = get_the_ID();
    $excludeCurrent = $currentPostId ? [$currentPostId] : [];

    $opts = isset($data['options']) && is_array($data['options']) ? $data['options'] : [];
    $rawHomeLimit = $opts['homeBlogPostsLimit'] ?? null;
    $postsLimit = ($rawHomeLimit === null || $rawHomeLimit === '') ? 4 : (int) $rawHomeLimit;
    $postsLimit = max(1, min(24, $postsLimit));

    // Featured Insights: from selected category or latest posts if no category
    $featuredCategoryIds = [];
    if (!empty($data['featuredCategory'])) {
        $raw = $data['featuredCategory'];
        if (is_array($raw)) {
            $featuredCategoryIds = array_map(function ($term) {
                return is_object($term) ? $term->term_id : (int) $term;
            }, $raw);
        } else {
            $featuredCategoryIds = [is_object($raw) ? $raw->term_id : (int) $raw];
        }
    }

    $featuredArgs = [
        'post_status' => 'publish',
        'post_type' => POST_TYPE,
        'posts_per_page' => $postsLimit,
        'ignore_sticky_posts' => 1,
        'post__not_in' => $excludeCurrent,
    ];
    if (!empty($featuredCategoryIds)) {
        $featuredArgs['cat'] = implode(',', $featuredCategoryIds);
    }
    $featuredPosts = Timber::get_posts($featuredArgs);
    $data['featuredPosts'] = $featuredPosts->to_array();

    // Latest Updates: exclude featured post IDs, get N most recent
    $excludeIds = array_merge($excludeCurrent, array_map(function ($p) {
        return $p->ID;
    }, $data['featuredPosts']));
    $latestArgs = [
        'post_status' => 'publish',
        'post_type' => POST_TYPE,
        'posts_per_page' => $postsLimit,
        'ignore_sticky_posts' => 1,
        'post__not_in' => $excludeIds,
    ];
    if (!empty($data['latestCategory'])) {
        $raw = $data['latestCategory'];
        $ids = is_array($raw)
            ? array_map(function ($t) {
                return is_object($t) ? $t->term_id : (int) $t;
            }, $raw)
            : [is_object($raw) ? $raw->term_id : (int) $raw];
        if (!empty($ids)) {
            $latestArgs['cat'] = implode(',', $ids);
        }
    }
    $latestPosts = Timber::get_posts($latestArgs);
    $data['latestPosts'] = $latestPosts->to_array();

    $data['blogPageUrl'] = $blogPageUrl;
    $labels = $data['labels'] ?? [];
    $data['labels'] = is_array($labels) ? $labels : [];
    $data['buildUri'] = trailingslashit(get_template_directory_uri()) . 'frontend/build';

    return $data;
});

function getACFLayout()
{
    return [
        'name' => 'nscBlockBlogsHome',
        'label' => __('NSC Block: Blogs (Home)', 'NscSoftware'),
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
                'label' => __('Description heading', 'NscSoftware'),
                'name' => 'descriptionHeading',
                'type' => 'text',
                'default_value' => "Ideas that inspire. Stories that shape the future.",
            ],
            [
                'label' => __('Description paragraph', 'NscSoftware'),
                'name' => 'descriptionParagraph',
                'type' => 'textarea',
                'default_value' => 'Stay updated with insights, stories, and perspectives from NSC Software, where we explore how technology, innovation, and people drive business transformation.',
            ],
            [
                'label' => __('Labels', 'NscSoftware'),
                'name' => 'labels',
                'type' => 'group',
                'sub_fields' => [
                    [
                        'label' => __('Featured Insights title', 'NscSoftware'),
                        'name' => 'featuredTitle',
                        'type' => 'text',
                        'default_value' => 'Featured Insights',
                    ],
                    [
                        'label' => __('Featured Insights description', 'NscSoftware'),
                        'name' => 'featuredDescription',
                        'type' => 'text',
                        'default_value' => 'Explore in-depth perspectives from our experts on software development, digital transformation, and emerging technology trends.',
                    ],
                    [
                        'label' => __('Latest Updates title', 'NscSoftware'),
                        'name' => 'latestTitle',
                        'type' => 'text',
                        'default_value' => 'Latest Updates',
                    ],
                    [
                        'label' => __('Latest Updates description', 'NscSoftware'),
                        'name' => 'latestDescription',
                        'type' => 'text',
                        'default_value' => 'Keep up with our latest news, events, and knowledge sharing from the NSC team.',
                    ],
                    [
                        'label' => __('Read More', 'NscSoftware'),
                        'name' => 'readMore',
                        'type' => 'text',
                        'default_value' => 'Read More',
                    ],
                ],
            ],
            [
                'label' => __('Featured Insights category', 'NscSoftware'),
                'name' => 'featuredCategory',
                'type' => 'taxonomy',
                'taxonomy' => 'category',
                'field_type' => 'multi_select',
                'allow_null' => 1,
                'instructions' => __('Leave empty to use latest posts.', 'NscSoftware'),
            ],
            [
                'label' => __('Latest Updates category', 'NscSoftware'),
                'name' => 'latestCategory',
                'type' => 'taxonomy',
                'taxonomy' => 'category',
                'field_type' => 'multi_select',
                'allow_null' => 1,
            ],
            [
                'label' => __('Join conversation', 'NscSoftware'),
                'name' => 'joinConversation',
                'type' => 'group',
                'sub_fields' => [
                    [
                        'label' => __('Image', 'NscSoftware'),
                        'name' => 'image',
                        'type' => 'image',
                        'return_format' => 'array',
                    ],
                    [
                        'label' => __('Title', 'NscSoftware'),
                        'name' => 'title',
                        'type' => 'text',
                        'default_value' => 'Join Conversation',
                    ],
                    [
                        'label' => __('Paragraph', 'NscSoftware'),
                        'name' => 'paragraph',
                        'type' => 'textarea',
                    ],
                    [
                        'label' => __('Button label', 'NscSoftware'),
                        'name' => 'buttonLabel',
                        'type' => 'text',
                        'default_value' => 'Explore all articles on the NSC Blog',
                    ],
                    [
                        'label' => __('Button URL', 'NscSoftware'),
                        'name' => 'buttonUrl',
                        'type' => 'url',
                        'default_value' => home_url('/'),
                    ],
                    [
                        'label' => __('Open in new tab', 'NscSoftware'),
                        'name' => 'openInNewTab',
                        'type' => 'true_false',
                        'default_value' => 0,
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
                    FieldVariables\getBlogHomePostsLimitField(),
                    FieldVariables\getHidden(),
                ],
            ],
        ],
    ];
}
