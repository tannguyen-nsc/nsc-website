<?php

namespace NscSoftware\Components\NSCBlockAiSecurity;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockAiSecurity',
        'label' => __('NSC Block: AI Security (Trust & Security)', 'NscSoftware'),
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
                'label' => __('Subtitle (e.g. Three Pillars of Trust)', 'NscSoftware'),
                'name' => 'subtitle',
                'type' => 'text',
            ],
            [
                'label' => __('Items', 'NscSoftware'),
                'name' => 'items',
                'type' => 'repeater',
                'min' => 1,
                'max' => 30,
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'label' => __('Title', 'NscSoftware'),
                        'name' => 'title',
                        'type' => 'text',
                    ],
                    [
                        'label' => __('Content', 'NscSoftware'),
                        'name' => 'content',
                        'type' => 'textarea',
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
                    FieldVariables\getRepeaterItemLimitField(3),
                    FieldVariables\getHidden(),
                ],
            ],
        ],
    ];
}
