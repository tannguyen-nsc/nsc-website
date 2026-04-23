<?php

namespace NscSoftware\Components\NSCBlockHowWeWorkPagePartnership;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockHowWeWorkPagePartnership',
        'label' => __('NSC Block: How We Work — Partnership', 'NscSoftware'),
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
                'label' => __('Heading — line 1', 'NscSoftware'),
                'name' => 'headingLine1',
                'type' => 'text',
                'default_value' => 'A Partnership Approach',
            ],
            [
                'label' => __('Heading — line 2 (mobile: first part)', 'NscSoftware'),
                'name' => 'headingMobileLine2a',
                'type' => 'text',
                'default_value' => 'to Software',
            ],
            [
                'label' => __('Heading — line 2 (mobile: second part)', 'NscSoftware'),
                'name' => 'headingMobileLine2b',
                'type' => 'text',
                'default_value' => 'Development',
            ],
            [
                'label' => __('Heading — line 2 (desktop, one line)', 'NscSoftware'),
                'name' => 'headingDesktopLine2',
                'type' => 'text',
                'default_value' => 'to Software Development',
            ],
            [
                'label' => __('Body', 'NscSoftware'),
                'name' => 'body',
                'type' => 'wysiwyg',
                'toolbar' => 'basic',
                'media_upload' => 0,
                'default_value' => '<p>At NSC Software, we go beyond writing code. We partner with organizations to design, build, and operate reliable software systems that support long-term business growth.</p><p>Our teams combine technical expertise, structured delivery processes, and flexible collaboration models to support projects of different scales and complexity.</p>',
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
