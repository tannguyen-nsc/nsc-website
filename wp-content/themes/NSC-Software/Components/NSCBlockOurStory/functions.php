<?php

namespace NscSoftware\Components\NSCBlockOurStory;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockOurStory',
        'label' => __('NSC Block: Our Story', 'NscSoftware'),
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
                'default_value' => 'OUR STORY',
            ],
            [
                'label' => __('Intro content', 'NscSoftware'),
                'name' => 'content',
                'type' => 'wysiwyg',
                'instructions' => __('Paragraph above the columns (supports HTML e.g. &lt;b&gt;).', 'NscSoftware'),
            ],
            [
                'label' => __('Columns', 'NscSoftware'),
                'name' => 'columns',
                'type' => 'repeater',
                'min' => 1,
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'label' => __('Title', 'NscSoftware'),
                        'name' => 'title',
                        'type' => 'text',
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
                    FieldVariables\getHidden(),
                ],
            ],
        ],
    ];
}
