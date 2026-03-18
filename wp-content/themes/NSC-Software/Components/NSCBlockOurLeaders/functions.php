<?php

namespace NscSoftware\Components\NSCBlockOurLeaders;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockOurLeaders',
        'label' => __('NSC Block: Our Leaders', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'label' => __('Background (top, desktop)', 'NscSoftware'),
                'name' => 'backgroundTop',
                'type' => 'image',
                'preview_size' => 'medium',
                'return_format' => 'array',
            ],
            [
                'label' => __('Background (main, desktop)', 'NscSoftware'),
                'name' => 'backgroundDesktop',
                'type' => 'image',
                'preview_size' => 'medium',
                'return_format' => 'array',
            ],
            [
                'label' => __('Background (mobile)', 'NscSoftware'),
                'name' => 'backgroundMobile',
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
                'label' => __('Heading title', 'NscSoftware'),
                'name' => 'title',
                'type' => 'text',
                'default_value' => 'Our Management Team',
            ],
            [
                'label' => __('Intro content', 'NscSoftware'),
                'name' => 'content',
                'type' => 'wysiwyg',
            ],
            [
                'label' => __('Leaders', 'NscSoftware'),
                'name' => 'leaders',
                'type' => 'repeater',
                'min' => 1,
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'label' => __('Image', 'NscSoftware'),
                        'name' => 'image',
                        'type' => 'image',
                        'preview_size' => 'medium',
                        'return_format' => 'array',
                    ],
                    [
                        'label' => __('Name', 'NscSoftware'),
                        'name' => 'name',
                        'type' => 'text',
                    ],
                    [
                        'label' => __('Role', 'NscSoftware'),
                        'name' => 'role',
                        'type' => 'text',
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
