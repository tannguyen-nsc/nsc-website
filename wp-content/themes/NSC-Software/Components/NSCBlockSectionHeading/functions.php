<?php

namespace NscSoftware\Components\NSCBlockSectionHeading;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockSectionHeading',
        'label' => __('NSC Block: Section Heading', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'label' => __('Icon image', 'NscSoftware'),
                'name' => 'icon',
                'type' => 'image',
                'preview_size' => 'thumbnail',
                'return_format' => 'array',
            ],
            [
                'label' => __('Title', 'NscSoftware'),
                'name' => 'title',
                'type' => 'text',
                'required' => 1,
            ],
            [
                'label' => __('Options', 'NscSoftware'),
                'name' => 'optionsTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'label' => __('Light variant', 'NscSoftware'),
                'name' => 'light',
                'type' => 'true_false',
                'instructions' => __('Use light text (e.g. on dark background)', 'NscSoftware'),
                'default_value' => 0,
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
