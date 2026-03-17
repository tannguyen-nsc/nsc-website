<?php

namespace NscSoftware\Components\NSCBlockWhyUs;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockWhyUs',
        'label' => __('NSC Block: Why Us', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'label' => __('Background image (desktop)', 'NscSoftware'),
                'name' => 'backgroundImage',
                'type' => 'image',
                'preview_size' => 'medium',
                'return_format' => 'array',
            ],
            [
                'label' => __('Background image (mobile)', 'NscSoftware'),
                'name' => 'backgroundImageMobile',
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
                'label' => __('Section title', 'NscSoftware'),
                'name' => 'title',
                'type' => 'text',
                'default_value' => 'Why NSC Software?',
            ],
            [
                'label' => __('Items', 'NscSoftware'),
                'name' => 'items',
                'type' => 'repeater',
                'min' => 1,
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'label' => __('Title', 'NscSoftware'),
                        'name' => 'title',
                        'type' => 'text',
                        'required' => 1,
                    ],
                    [
                        'label' => __('Description', 'NscSoftware'),
                        'name' => 'description',
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
                    FieldVariables\getTheme(),
                    FieldVariables\getHidden(),
                ],
            ],
        ],
    ];
}
