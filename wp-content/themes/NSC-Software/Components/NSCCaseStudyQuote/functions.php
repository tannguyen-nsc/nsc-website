<?php

namespace NscSoftware\Components\NSCCaseStudyQuote;

use NscSoftware\FieldVariables;

function getACFLayout(): array
{
    return [
        'name' => 'nscCaseStudyQuote',
        'label' => __('NSC Case Study: Quote', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'label' => __('Quote', 'NscSoftware'),
                'name' => 'quoteText',
                'type' => 'textarea',
                'rows' => 5,
                'required' => 1,
            ],
            [
                'label' => __('Attribution name', 'NscSoftware'),
                'name' => 'citeName',
                'type' => 'text',
            ],
            [
                'label' => __('Attribution role', 'NscSoftware'),
                'name' => 'citeRole',
                'type' => 'text',
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
