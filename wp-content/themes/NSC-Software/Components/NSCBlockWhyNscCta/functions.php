<?php

namespace NscSoftware\Components\NSCBlockWhyNscCta;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockWhyNscCta',
        'label' => __('NSC Block: Why NSC — CTA', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
            ],
            [
                'label' => __('Heading', 'NscSoftware'),
                'name' => 'title',
                'type' => 'text',
                'default_value' => 'Start Building with NSC Software',
            ],
            [
                'label' => __('Body', 'NscSoftware'),
                'name' => 'body',
                'type' => 'textarea',
                'rows' => 3,
                'default_value' => 'Whether you\'re a startup building a new product or an enterprise scaling your technology platform, NSC Software provides the engineering expertise needed to bring your ideas to life.',
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
                        'default_value' => 'Let\'s build the future of technology together',
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
