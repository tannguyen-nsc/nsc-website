<?php

/**
 * Translatable options for blog single (author, labels, Connect / CTA, related posts).
 *
 * Registration runs on NscSoftware/afterRegisterComponents so it happens after
 * NSCFooter / NSCHeader register the Global submenu. Otherwise inc/*.php loads
 * before Components and Blog would be the first submenu — parent menu would link
 * to NSCThemeOptions-Blog instead of NSCThemeOptions-Global.
 */

use NscSoftware\Utils\Options;

add_action('NscSoftware/afterRegisterComponents', static function (): void {
    Options::addTranslatable('NSCBlogSingle', [
    [
        'label' => __('About the author', 'NscSoftware'),
        'name' => 'aboutAuthorTab',
        'type' => 'tab',
        'placement' => 'top',
        'endpoint' => 0,
    ],
    [
        'label' => __('About the author heading', 'NscSoftware'),
        'name' => 'aboutAuthorHeading',
        'type' => 'text',
        'default_value' => __('About the author', 'NscSoftware'),
        'instructions' => __('Heading above the author card in the blog single sidebar.', 'NscSoftware'),
    ],
    [
        'label' => __('Avatar / logo', 'NscSoftware'),
        'name' => 'aboutAuthorAvatar',
        'type' => 'image',
        'preview_size' => 'thumbnail',
        'return_format' => 'array',
    ],
    [
        'label' => __('Content', 'NscSoftware'),
        'name' => 'aboutAuthorContent',
        'type' => 'wysiwyg',
        'toolbar' => 'basic',
        'media_upload' => 1,
    ],
    [
        'label' => __('Profile link', 'NscSoftware'),
        'name' => 'aboutAuthorLink',
        'type' => 'group',
        'layout' => 'block',
        'sub_fields' => [
            [
                'label' => __('Link label', 'NscSoftware'),
                'name' => 'linkLabel',
                'type' => 'text',
                'default_value' => __('View full profile', 'NscSoftware'),
            ],
            [
                'label' => __('Link URL', 'NscSoftware'),
                'name' => 'linkUrl',
                'type' => 'url',
            ],
            [
                'label' => __('Open in new tab', 'NscSoftware'),
                'name' => 'openInNewTab',
                'type' => 'true_false',
                'default_value' => 0,
                'ui' => 1,
            ],
        ],
    ],
    [
        'label' => __('Connect box (sidebar CTA)', 'NscSoftware'),
        'name' => 'connectBoxTab',
        'type' => 'tab',
        'placement' => 'top',
        'endpoint' => 0,
    ],
    [
        'label' => __('CTA title', 'NscSoftware'),
        'name' => 'connectBoxTitle',
        'type' => 'text',
        'default_value' => __('Need an innovative and reliable tech partner?', 'NscSoftware'),
        'instructions' => __('Shown above the button in the blog single sidebar.', 'NscSoftware'),
    ],
    [
        'label' => __('Button label', 'NscSoftware'),
        'name' => 'connectBoxButtonLabel',
        'type' => 'text',
        'default_value' => __("Let's Connect", 'NscSoftware'),
    ],
    [
        'label' => __('Button URL', 'NscSoftware'),
        'name' => 'connectBoxButtonUrl',
        'type' => 'url',
    ],
    [
        'label' => __('Open button in new tab', 'NscSoftware'),
        'name' => 'connectBoxOpenNewTab',
        'type' => 'true_false',
        'default_value' => 0,
        'ui' => 1,
    ],
    [
        'label' => __('Background image (optional)', 'NscSoftware'),
        'name' => 'connectBoxBackground',
        'type' => 'image',
        'preview_size' => 'medium',
        'return_format' => 'array',
        'instructions' => __('Overrides the default connect-box graphic from the theme build when set.', 'NscSoftware'),
    ],
    [
        'label' => __('Share & reading labels', 'NscSoftware'),
        'name' => 'singleShareReadTab',
        'type' => 'tab',
        'placement' => 'top',
        'endpoint' => 0,
    ],
    [
        'label' => __('Share article label', 'NscSoftware'),
        'name' => 'shareArticleLabel',
        'type' => 'text',
        'default_value' => __('Share article:', 'NscSoftware'),
        'instructions' => __('Label before the share icons (blog single).', 'NscSoftware'),
    ],
    [
        'label' => __('Reading time suffix (1 minute)', 'NscSoftware'),
        'name' => 'readingTimeSuffixSingular',
        'type' => 'text',
        'default_value' => __('min read', 'NscSoftware'),
        'instructions' => __('Shown after the number when the read time is exactly one (e.g. “1 min read”).', 'NscSoftware'),
    ],
    [
        'label' => __('Reading time suffix (2+ minutes)', 'NscSoftware'),
        'name' => 'readingTimeSuffixPlural',
        'type' => 'text',
        'default_value' => __('mins read', 'NscSoftware'),
        'instructions' => __('Shown after the number when the read time is more than one (e.g. “5 mins read”).', 'NscSoftware'),
    ],
    [
        'label' => __('Related posts (single)', 'NscSoftware'),
        'name' => 'relatedPostsTab',
        'type' => 'tab',
        'placement' => 'top',
        'endpoint' => 0,
    ],
    [
        'label' => __('Related articles heading', 'NscSoftware'),
        'name' => 'relatedArticlesHeading',
        'type' => 'text',
        'default_value' => __('Related Articles', 'NscSoftware'),
        'instructions' => __('Heading above the related posts grid on blog single.', 'NscSoftware'),
    ],
    [
        'label' => __('Related articles count', 'NscSoftware'),
        'name' => 'relatedPostsLimit',
        'type' => 'number',
        'default_value' => 3,
        'min' => 1,
        'max' => 12,
        'step' => 1,
        'instructions' => __('How many related posts to show (same category first; fills with latest posts if needed).', 'NscSoftware'),
    ],
], 'Blog');
}, 20);
