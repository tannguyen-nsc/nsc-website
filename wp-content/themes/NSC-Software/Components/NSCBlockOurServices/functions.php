<?php

namespace NscSoftware\Components\NSCBlockOurServices;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockOurServices',
        'label' => __('NSC Block: Our Services', 'NscSoftware'),
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
                'default_value' => 'Our Services',
            ],
            [
                'label' => __('Intro description', 'NscSoftware'),
                'name' => 'introDescription',
                'type' => 'textarea',
            ],
            [
                'label' => __('Intro button', 'NscSoftware'),
                'name' => 'introButton',
                'type' => 'group',
                'sub_fields' => [
                    ['label' => __('Label', 'NscSoftware'), 'name' => 'label', 'type' => 'text', 'default_value' => 'Explore our services'],
                    ['label' => __('URL', 'NscSoftware'), 'name' => 'url', 'type' => 'url', 'default_value' => home_url('/')],
                    ['label' => __('Open in new tab', 'NscSoftware'), 'name' => 'openInNewTab', 'type' => 'true_false', 'default_value' => 0],
                ],
            ],
            [
                'label' => __('Services (accordion items)', 'NscSoftware'),
                'name' => 'services',
                'type' => 'repeater',
                'min' => 0,
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'label' => __('Number', 'NscSoftware'),
                        'name' => 'number',
                        'type' => 'text',
                        'placeholder' => '01',
                    ],
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
