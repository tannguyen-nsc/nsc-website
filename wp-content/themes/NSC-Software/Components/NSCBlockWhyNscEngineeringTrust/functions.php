<?php

namespace NscSoftware\Components\NSCBlockWhyNscEngineeringTrust;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockWhyNscEngineeringTrust',
        'label' => __('NSC Block: Why NSC — Engineering trust', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
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
                'name' => 'heading',
                'type' => 'wysiwyg',
                'toolbar' => 'basic',
                'media_upload' => 0,
                'instructions' => __('Use a line break where needed (e.g. before “Trust”).', 'NscSoftware'),
                'default_value' => 'Engineering You Can <br class="sm:hidden"> Trust',
            ],
            [
                'label' => __('Stats', 'NscSoftware'),
                'name' => 'stats',
                'type' => 'repeater',
                'min' => 1,
                'max' => 12,
                'layout' => 'block',
                'button_label' => __('Add stat', 'NscSoftware'),
                'sub_fields' => [
                    [
                        'label' => __('Index (e.g. 01.)', 'NscSoftware'),
                        'name' => 'num',
                        'type' => 'text',
                    ],
                    [
                        'label' => __('Value (large line)', 'NscSoftware'),
                        'name' => 'value',
                        'type' => 'text',
                    ],
                    [
                        'label' => __('Label', 'NscSoftware'),
                        'name' => 'label',
                        'type' => 'text',
                    ],
                ],
            ],
            [
                'label' => __('Options', 'NscSoftware'),
                'name' => 'optionsTab',
                'type' => 'tab',
                'placement' => 'top',
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
