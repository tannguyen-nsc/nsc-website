<?php

/**
 * Post (blog) sidebar: featured flag + related content links.
 */

use ACFComposer\ACFComposer;

add_action('NscSoftware/afterRegisterComponents', function () {
    ACFComposer::registerFieldGroup([
        'name' => 'nscPostSidebar',
        'title' => __('Post sidebar (blog)', 'NscSoftware'),
        'fields' => [
            [
                'label' => __('Featured article', 'NscSoftware'),
                'name' => 'nsc_featured_article',
                'type' => 'true_false',
                'instructions' => __('Mark as featured (shown in archive and on single).', 'NscSoftware'),
                'default_value' => 0,
                'ui' => 1,
            ],
            [
                'label' => __('Square thumbnail', 'NscSoftware'),
                'name' => 'nsc_square_thumbnail',
                'type' => 'image',
                'instructions' => __('Optional square thumbnail for archive list cards.', 'NscSoftware'),
                'return_format' => 'array',
                'preview_size' => 'medium',
                'library' => 'all',
                'mime_types' => 'jpg,jpeg,png,webp,avif',
            ],
            [
                'label' => __('Related content heading', 'NscSoftware'),
                'name' => 'nsc_related_heading',
                'type' => 'text',
                'default_value' => __('Related content', 'NscSoftware'),
            ],
            [
                'label' => __('Related links', 'NscSoftware'),
                'name' => 'nsc_related_links',
                'type' => 'repeater',
                'layout' => 'block',
                'button_label' => __('Add link', 'NscSoftware'),
                'sub_fields' => [
                    [
                        'label' => __('Link label', 'NscSoftware'),
                        'name' => 'linkLabel',
                        'type' => 'text',
                        'required' => 1,
                    ],
                    [
                        'label' => __('Link URL', 'NscSoftware'),
                        'name' => 'linkUrl',
                        'type' => 'url',
                        'required' => 1,
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
        ],
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'post',
                ],
            ],
        ],
        'position' => 'side',
        'style' => 'default',
    ]);
});
