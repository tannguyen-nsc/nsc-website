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
                'label' => __('Heading line 1 (black)', 'NscSoftware'),
                'name' => 'headingLineBlack',
                'type' => 'text',
                'default_value' => 'Built for',
            ],
            [
                'label' => __('Heading line 2 (accent)', 'NscSoftware'),
                'name' => 'title',
                'type' => 'text',
                'default_value' => 'Long-Term Partnerships',
            ],
            [
                'label' => __('Body', 'NscSoftware'),
                'name' => 'body',
                'type' => 'textarea',
                'rows' => 4,
                'default_value' => 'Our goal is not just to complete projects, but to build lasting technology partnerships. By combining flexible engagement models with experienced engineering teams, NSC Software helps organizations scale development while maintaining high standards of quality and reliability.',
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
