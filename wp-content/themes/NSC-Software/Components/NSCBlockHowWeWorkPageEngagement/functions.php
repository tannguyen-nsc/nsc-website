<?php

namespace NscSoftware\Components\NSCBlockHowWeWorkPageEngagement;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockHowWeWorkPageEngagement',
        'label' => __('NSC Block: How We Work — Engagement models', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
            ],
            [
                'label' => __('Background (desktop)', 'NscSoftware'),
                'name' => 'backgroundDesktop',
                'type' => 'image',
                'preview_size' => 'medium',
                'return_format' => 'array',
            ],
            [
                'label' => __('Background (mobile)', 'NscSoftware'),
                'name' => 'backgroundMobile',
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
                'label' => __('Section heading', 'NscSoftware'),
                'name' => 'sectionHeading',
                'type' => 'text',
                'default_value' => 'Our Engagement Models',
            ],
            [
                'label' => __('Collaboration process heading', 'NscSoftware'),
                'name' => 'collaborationHeading',
                'type' => 'text',
                'default_value' => 'Our Collaboration Process',
            ],
            [
                'label' => __('“Best suited for” box title', 'NscSoftware'),
                'name' => 'boxBestSuitedTitle',
                'type' => 'text',
                'default_value' => 'Best suited for',
            ],
            [
                'label' => __('“Key benefits” box title', 'NscSoftware'),
                'name' => 'boxKeyBenefitsTitle',
                'type' => 'text',
                'default_value' => 'Key benefits',
            ],
            [
                'label' => __('Engagement models', 'NscSoftware'),
                'name' => 'engagementModels',
                'type' => 'repeater',
                'min' => 1,
                'max' => 8,
                'layout' => 'block',
                'button_label' => __('Add model', 'NscSoftware'),
                'sub_fields' => [
                    [
                        'label' => __('Tab number (optional)', 'NscSoftware'),
                        'name' => 'tabNumber',
                        'type' => 'text',
                        'instructions' => __('Shown in the tab and mobile list. Leave empty to use 1, 2, 3… from row order.', 'NscSoftware'),
                    ],
                    [
                        'label' => __('Tab label', 'NscSoftware'),
                        'name' => 'tabLabel',
                        'type' => 'text',
                        'required' => 1,
                    ],
                    [
                        'label' => __('Lead (emphasis)', 'NscSoftware'),
                        'name' => 'leadStrong',
                        'type' => 'textarea',
                        'rows' => 2,
                    ],
                    [
                        'label' => __('Lead (paragraph)', 'NscSoftware'),
                        'name' => 'leadParagraph',
                        'type' => 'textarea',
                        'rows' => 3,
                    ],
                    [
                        'label' => __('Best suited — bullets', 'NscSoftware'),
                        'name' => 'bestSuitedLines',
                        'type' => 'repeater',
                        'min' => 0,
                        'max' => 12,
                        'layout' => 'table',
                        'button_label' => __('Add line', 'NscSoftware'),
                        'sub_fields' => [
                            [
                                'label' => __('Line', 'NscSoftware'),
                                'name' => 'text',
                                'type' => 'text',
                            ],
                        ],
                    ],
                    [
                        'label' => __('Key benefits — bullets', 'NscSoftware'),
                        'name' => 'keyBenefitLines',
                        'type' => 'repeater',
                        'min' => 0,
                        'max' => 12,
                        'layout' => 'table',
                        'button_label' => __('Add line', 'NscSoftware'),
                        'sub_fields' => [
                            [
                                'label' => __('Line', 'NscSoftware'),
                                'name' => 'text',
                                'type' => 'text',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'label' => __('Collaboration process steps', 'NscSoftware'),
                'name' => 'processSteps',
                'type' => 'repeater',
                'min' => 1,
                'max' => 12,
                'layout' => 'block',
                'button_label' => __('Add step', 'NscSoftware'),
                'sub_fields' => [
                    [
                        'label' => __('Step label', 'NscSoftware'),
                        'name' => 'stepLabel',
                        'type' => 'text',
                        'default_value' => '01.',
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
                        'rows' => 3,
                    ],
                    [
                        'label' => __('Image', 'NscSoftware'),
                        'name' => 'image',
                        'type' => 'image',
                        'preview_size' => 'medium',
                        'return_format' => 'array',
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
                    FieldVariables\getRepeaterItemLimitField(4),
                    FieldVariables\getHidden(),
                ],
            ],
        ],
    ];
}
