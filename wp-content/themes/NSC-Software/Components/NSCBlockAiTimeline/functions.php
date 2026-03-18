<?php

namespace NscSoftware\Components\NSCBlockAiTimeline;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockAiTimeline',
        'label' => __('NSC Block: AI Timeline (Bionic Process)', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'label' => __('Background image', 'NscSoftware'),
                'name' => 'backgroundImage',
                'type' => 'image',
                'preview_size' => 'medium',
                'return_format' => 'array',
            ],
            [
                'label' => __('Heading icon', 'NscSoftware'),
                'name' => 'headingIcon',
                'type' => 'image',
                'preview_size' => 'thumbnail',
                'return_format' => 'array',
            ],
            [
                'label' => __('Title', 'NscSoftware'),
                'name' => 'title',
                'type' => 'text',
            ],
            [
                'label' => __('Description', 'NscSoftware'),
                'name' => 'description',
                'type' => 'wysiwyg',
            ],
            [
                'label' => __('Timeline phases', 'NscSoftware'),
                'name' => 'phases',
                'type' => 'repeater',
                'min' => 1,
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'label' => __('Phase label', 'NscSoftware'),
                        'name' => 'milestone',
                        'type' => 'text',
                        'instructions' => __('e.g. Phase 1', 'NscSoftware'),
                    ],
                    [
                        'label' => __('Title', 'NscSoftware'),
                        'name' => 'title',
                        'type' => 'text',
                    ],
                    [
                        'label' => __('Content', 'NscSoftware'),
                        'name' => 'content',
                        'type' => 'wysiwyg',
                        'instructions' => __('Supports HTML (e.g. &lt;br&gt;).', 'NscSoftware'),
                    ],
                ],
            ],
            [
                'label' => __('Quote (below timeline)', 'NscSoftware'),
                'name' => 'quote',
                'type' => 'wysiwyg',
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
