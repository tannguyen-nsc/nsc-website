<?php

namespace NscSoftware\Components\NSCBlockCompanySnapshot;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockCompanySnapshot',
        'label' => __('NSC Block: Company Snapshot', 'NscSoftware'),
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
                'default_value' => 'Company Snapshot',
            ],
            [
                'label' => __('Stats', 'NscSoftware'),
                'name' => 'stats',
                'type' => 'repeater',
                'min' => 1,
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'label' => __('Number', 'NscSoftware'),
                        'name' => 'number',
                        'type' => 'text',
                    ],
                    [
                        'label' => __('Suffix', 'NscSoftware'),
                        'name' => 'suffix',
                        'type' => 'text',
                        'instructions' => __('e.g. + or %', 'NscSoftware'),
                    ],
                    [
                        'label' => __('Title', 'NscSoftware'),
                        'name' => 'title',
                        'type' => 'text',
                    ],
                    [
                        'label' => __('Subtitle', 'NscSoftware'),
                        'name' => 'subtitle',
                        'type' => 'text',
                    ],
                ],
            ],
            [
                'label' => __('Certification images (shown after stats)', 'NscSoftware'),
                'name' => 'certImages',
                'type' => 'repeater',
                'min' => 0,
                'max' => 2,
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'label' => __('Image', 'NscSoftware'),
                        'name' => 'image',
                        'type' => 'image',
                        'preview_size' => 'thumbnail',
                        'return_format' => 'array',
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
