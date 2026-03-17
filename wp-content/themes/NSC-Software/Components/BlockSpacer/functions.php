<?php

namespace NscSoftware\Components\BlockSpacer;

use NscSoftware\FieldVariables;

add_filter('NscSoftware/addComponentData?name=BlockSpacer', function ($data) {
    $data['status'] = $data['options']['percentageDistance'] >= 101 ? 'expand' : 'collapse';
    return $data;
});

function getACFLayout()
{
    return [
        'name' => 'blockSpacer',
        'label' => __('Block: Spacer', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Options', 'NscSoftware'),
                'name' => 'optionsTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0
            ],
            [
                'label' => '',
                'name' => 'options',
                'type' => 'group',
                'layout' => 'row',
                'sub_fields' => [
                    FieldVariables\getTheme(),
                    [
                        'label' => __('Vertical space', 'NscSoftware'),
                        'instructions' => __('Distance between two components.', 'NscSoftware'),
                        'name' => 'percentageDistance',
                        'type' => 'range',
                        'prepend' => __('Distance', 'NscSoftware'),
                        'append' => __('%', 'NscSoftware'),
                        'default_value' => 50,
                        'min' => 0,
                        'max' => 200,
                        'step' => 50,
                        'wrapper' =>  [
                            'width' => '50',
                        ],
                    ],
                    [
                        'label' => __('Examples', 'NscSoftware'),
                        'name' => '',
                        'type' => 'message',
                        'message' => sprintf(
                            '%1$s' . PHP_EOL . '%2$s' . PHP_EOL . '%3$s',
                            __('0% no spacing between components', 'NscSoftware'),
                            __('50% reduces vertical space (by half)', 'NscSoftware'),
                            __('150% extends vertical space (by 50%)', 'NscSoftware')
                        ),
                        'new_lines' => 'br',
                        'esc_html' => 1,
                        'wrapper' =>  [
                            'width' => '50',
                        ],
                    ],
                ]
            ]
        ]
    ];
}
