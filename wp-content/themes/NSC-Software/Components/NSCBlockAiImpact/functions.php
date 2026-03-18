<?php

namespace NscSoftware\Components\NSCBlockAiImpact;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockAiImpact',
        'label' => __('NSC Block: AI Impact (accordion)', 'NscSoftware'),
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
                'label' => __('Accordion items', 'NscSoftware'),
                'name' => 'items',
                'type' => 'repeater',
                'min' => 1,
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'label' => __('Number (e.g. 01)', 'NscSoftware'),
                        'name' => 'num',
                        'type' => 'text',
                    ],
                    [
                        'label' => __('Title (include number in text if desired, e.g. "01. Enterprise GenAI & RAG")', 'NscSoftware'),
                        'name' => 'title',
                        'type' => 'text',
                    ],
                    [
                        'label' => __('Content', 'NscSoftware'),
                        'name' => 'content',
                        'type' => 'wysiwyg',
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
                    FieldVariables\getHidden(),
                ],
            ],
        ],
    ];
}
