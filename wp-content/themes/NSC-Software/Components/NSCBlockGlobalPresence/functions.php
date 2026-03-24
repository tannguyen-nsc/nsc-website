<?php

namespace NscSoftware\Components\NSCBlockGlobalPresence;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockGlobalPresence',
        'label' => __('NSC Block: Global Presence', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'label' => __('Background (desktop)', 'NscSoftware'),
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
                'default_value' => 'GLOBAL PRESENCE',
            ],
            [
                'label' => __('Locations', 'NscSoftware'),
                'name' => 'locations',
                'type' => 'repeater',
                'min' => 1,
                'max' => 30,
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'label' => __('Label (marker text)', 'NscSoftware'),
                        'name' => 'label',
                        'type' => 'text',
                        'instructions' => __('e.g. Dallas, Sydney', 'NscSoftware'),
                    ],
                    [
                        'label' => __('Image', 'NscSoftware'),
                        'name' => 'image',
                        'type' => 'image',
                        'preview_size' => 'medium',
                        'return_format' => 'array',
                    ],
                    [
                        'label' => __('Title (card heading)', 'NscSoftware'),
                        'name' => 'title',
                        'type' => 'text',
                    ],
                    [
                        'label' => __('Address / details', 'NscSoftware'),
                        'name' => 'address',
                        'type' => 'textarea',
                        'instructions' => __('Address only; do not add Tel here. The template outputs Tel: from Phone link below.', 'NscSoftware'),
                    ],
                    [
                        'label' => __('Phone link (text only)', 'NscSoftware'),
                        'name' => 'phoneLink',
                        'type' => 'text',
                        'instructions' => __('e.g. +1 (713) 428 2289 — will be output as tel: link in Twig.', 'NscSoftware'),
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
                    FieldVariables\getRepeaterItemLimitField(5),
                    FieldVariables\getHidden(),
                ],
            ],
        ],
    ];
}
