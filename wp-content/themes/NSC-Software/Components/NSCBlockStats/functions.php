<?php

namespace NscSoftware\Components\NSCBlockStats;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockStats',
        'label' => __('NSC Block: Stats', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'label' => __('Stats image (desktop)', 'NscSoftware'),
                'name' => 'imageDesktop',
                'type' => 'image',
                'preview_size' => 'medium',
                'return_format' => 'array',
            ],
            [
                'label' => __('Stats image (mobile)', 'NscSoftware'),
                'name' => 'imageMobile',
                'type' => 'image',
                'preview_size' => 'medium',
                'return_format' => 'array',
            ],
            [
                'label' => __('Stats', 'NscSoftware'),
                'name' => 'stats',
                'type' => 'repeater',
                'min' => 1,
                'max' => 30,
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'label' => __('Number', 'NscSoftware'),
                        'name' => 'number',
                        'type' => 'text',
                        'default_value' => '200',
                    ],
                    [
                        'label' => __('Suffix', 'NscSoftware'),
                        'name' => 'suffix',
                        'type' => 'text',
                        'default_value' => '+',
                        'instructions' => __('e.g. + or %', 'NscSoftware'),
                    ],
                    [
                        'label' => __('Title', 'NscSoftware'),
                        'name' => 'title',
                        'type' => 'text',
                        'default_value' => 'Senior Experts',
                    ],
                    [
                        'label' => __('Subtitle', 'NscSoftware'),
                        'name' => 'subtitle',
                        'type' => 'text',
                        'instructions' => __('Optional', 'NscSoftware'),
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
                    FieldVariables\getRepeaterItemLimitField(4),
                    FieldVariables\getHidden(),
                ],
            ],
        ],
    ];
}
