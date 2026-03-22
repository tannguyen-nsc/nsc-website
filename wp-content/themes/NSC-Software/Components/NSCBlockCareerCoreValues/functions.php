<?php

namespace NscSoftware\Components\NSCBlockCareerCoreValues;

function getACFLayout(): array
{
    return [
        'name' => 'nscBlockCareerCoreValues',
        'label' => __('NSC Block: Career — Core values', 'NscSoftware'),
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
                'label' => __('Section heading', 'NscSoftware'),
                'name' => 'title',
                'type' => 'text',
                'default_value' => 'CORE VALUES',
            ],
            [
                'label' => __('Values', 'NscSoftware'),
                'name' => 'values',
                'type' => 'repeater',
                'layout' => 'block',
                'button_label' => __('Add value', 'NscSoftware'),
                'min' => 1,
                'max' => 12,
                'sub_fields' => [
                    [
                        'label' => __('Title', 'NscSoftware'),
                        'name' => 'valueTitle',
                        'type' => 'text',
                        'required' => 1,
                    ],
                    [
                        'label' => __('Description', 'NscSoftware'),
                        'name' => 'valueDescription',
                        'type' => 'textarea',
                        'rows' => 3,
                        'new_lines' => 'br',
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
                    \NscSoftware\FieldVariables\getHidden(),
                ],
            ],
        ],
    ];
}
