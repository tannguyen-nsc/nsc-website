<?php

namespace NscSoftware\Components\NSCBlockOurCapabilities;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockOurCapabilities',
        'label' => __('NSC Block: Our Capabilities (About)', 'NscSoftware'),
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
                'label' => __('Heading title', 'NscSoftware'),
                'name' => 'title',
                'type' => 'text',
                'default_value' => 'OUR CAPABILITIES',
            ],
            [
                'label' => __('Left: title line 1', 'NscSoftware'),
                'name' => 'titleLine1',
                'type' => 'text',
                'default_value' => 'Full-Stack Engineering.',
            ],
            [
                'label' => __('Left: title line 2', 'NscSoftware'),
                'name' => 'titleLine2',
                'type' => 'text',
                'default_value' => 'Enterprise Delivery.',
            ],
            [
                'label' => __('Button', 'NscSoftware'),
                'name' => 'button',
                'type' => 'group',
                'sub_fields' => [
                    [
                        'label' => __('Label', 'NscSoftware'),
                        'name' => 'label',
                        'type' => 'text',
                    ],
                    [
                        'label' => __('URL', 'NscSoftware'),
                        'name' => 'url',
                        'type' => 'url',
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
                'label' => __('Right: paragraphs', 'NscSoftware'),
                'name' => 'paragraphs',
                'type' => 'wysiwyg',
                'instructions' => __('One or more paragraphs (supports HTML).', 'NscSoftware'),
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
                    FieldVariables\getHidden(),
                ],
            ],
        ],
    ];
}
