<?php

/**
 * Translatable options for blog single (About the author block).
 */

use NscSoftware\Utils\Options;

Options::addTranslatable('NSCBlogSingle', [
    [
        'label' => __('About the author', 'NscSoftware'),
        'name' => 'aboutAuthorTab',
        'type' => 'tab',
        'placement' => 'top',
        'endpoint' => 0,
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
], 'Blog');
