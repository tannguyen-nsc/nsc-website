<?php

namespace NscSoftware\Components\NSCBlockHowWeWorkPageCta;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockHowWeWorkPageCta',
        'label' => __('NSC Block: How We Work — CTA', 'NscSoftware'),
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
                'default_value' => 'Let\'s Build Together',
            ],
            [
                'label' => __('Lead paragraph', 'NscSoftware'),
                'name' => 'lead',
                'type' => 'textarea',
                'rows' => 3,
                'default_value' => 'Whether you need a fully managed development partner or additional engineering expertise, NSC Software provides flexible collaboration models tailored to your goals.',
            ],
            [
                'label' => __('Emphasis line', 'NscSoftware'),
                'name' => 'emphasis',
                'type' => 'textarea',
                'rows' => 2,
                'default_value' => 'Talk with our team to explore the engagement model that best fits your project!',
            ],
            [
                'label' => __('Button', 'NscSoftware'),
                'name' => 'button',
                'type' => 'group',
                'sub_fields' => [
                    [
                        'label' => __('Label', 'NscSoftware'),
                        'name' => 'label',
                        'type' => 'text',
                        'default_value' => 'Talk to Our Team',
                    ],
                    [
                        'label' => __('URL', 'NscSoftware'),
                        'name' => 'url',
                        'type' => 'url',
                        'instructions' => __('Leave empty to use the Contact page (Polylang-aware).', 'NscSoftware'),
                    ],
                    [
                        'label' => __('Open in new tab', 'NscSoftware'),
                        'name' => 'openInNewTab',
                        'type' => 'true_false',
                        'default_value' => 0,
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
