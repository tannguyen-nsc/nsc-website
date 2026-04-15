<?php

namespace NscSoftware\Components\NSCBlockHowWeWorkPageLongterm;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockHowWeWorkPageLongterm',
        'label' => __('NSC Block: How we work — Long-term', 'NscSoftware'),
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
                'label' => __('Heading', 'NscSoftware'),
                'name' => 'title',
                'type' => 'text',
                'default_value' => 'Built for Long-Term Partnerships',
            ],
            [
                'label' => __('Body', 'NscSoftware'),
                'name' => 'body',
                'type' => 'textarea',
                'rows' => 4,
                'default_value' => 'We invest in communication clarity, delivery predictability, and engineering ownership — so collaboration feels like an extension of your team, not a handoff to a black box.',
            ],
            [
                'label' => __('Image', 'NscSoftware'),
                'name' => 'image',
                'type' => 'image',
                'preview_size' => 'medium',
                'return_format' => 'array',
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
