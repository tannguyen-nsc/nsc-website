<?php

namespace NscSoftware\Components\NSCBlockHowWeWork;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockHowWeWork',
        'label' => __('NSC Block: How We Work', 'NscSoftware'),
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
                'label' => __('Section title', 'NscSoftware'),
                'name' => 'title',
                'type' => 'text',
                'default_value' => 'How We Work',
            ],
            [
                'label' => __('Subheading', 'NscSoftware'),
                'name' => 'subheading',
                'type' => 'text',
            ],
            [
                'label' => __('Subtitle / tagline', 'NscSoftware'),
                'name' => 'subtitle',
                'type' => 'text',
            ],
            [
                'label' => __('Image (team/visual)', 'NscSoftware'),
                'name' => 'image',
                'type' => 'image',
                'preview_size' => 'medium',
                'return_format' => 'array',
            ],
            [
                'label' => __('Image (mobile)', 'NscSoftware'),
                'name' => 'imageMobile',
                'type' => 'image',
                'preview_size' => 'medium',
                'return_format' => 'array',
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
