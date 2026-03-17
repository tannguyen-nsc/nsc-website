<?php

namespace NscSoftware\Components\NSCBlockHero;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockHero',
        'label' => __('NSC Block: Hero', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'label' => __('Hero style', 'NscSoftware'),
                'name' => 'heroStyle',
                'type' => 'select',
                'choices' => [
                    'home' => __('Home (wave banner)', 'NscSoftware'),
                    'left_text' => __('About us (left-text banner)', 'NscSoftware'),
                    'dark' => __('AI / Our Services (dark banner)', 'NscSoftware'),
                ],
                'default_value' => 'home',
            ],
            [
                'label' => __('Background / hero image', 'NscSoftware'),
                'name' => 'image',
                'type' => 'image',
                'preview_size' => 'medium',
                'return_format' => 'array',
                'instructions' => __('Used for Home and Dark (single image).', 'NscSoftware'),
                'conditional_logic' => [
                    [
                        [
                            'fieldPath' => 'heroStyle',
                            'operator' => '==',
                            'value' => 'home',
                        ],
                    ],
                    [
                        [
                            'fieldPath' => 'heroStyle',
                            'operator' => '==',
                            'value' => 'dark',
                        ],
                    ],
                ],
            ],
            [
                'label' => __('Image (desktop)', 'NscSoftware'),
                'name' => 'imageDesktop',
                'type' => 'image',
                'preview_size' => 'medium',
                'return_format' => 'array',
                'instructions' => __('For Left-text and Dark styles when different from mobile.', 'NscSoftware'),
                'conditional_logic' => [
                    [
                        [
                            'fieldPath' => 'heroStyle',
                            'operator' => '==',
                            'value' => 'left_text',
                        ],
                    ],
                    [
                        [
                            'fieldPath' => 'heroStyle',
                            'operator' => '==',
                            'value' => 'dark',
                        ],
                    ],
                ],
            ],
            [
                'label' => __('Image (mobile)', 'NscSoftware'),
                'name' => 'imageMobile',
                'type' => 'image',
                'preview_size' => 'medium',
                'return_format' => 'array',
                'conditional_logic' => [
                    [
                        [
                            'fieldPath' => 'heroStyle',
                            'operator' => '==',
                            'value' => 'left_text',
                        ],
                    ],
                    [
                        [
                            'fieldPath' => 'heroStyle',
                            'operator' => '==',
                            'value' => 'dark',
                        ],
                    ],
                ],
            ],
            [
                'label' => __('Headline', 'NscSoftware'),
                'name' => 'headline',
                'type' => 'wysiwyg',
                'instructions' => __('Use bold or a span with class "highlight" for highlighted words.', 'NscSoftware'),
                'toolbar' => 'basic',
                'media_upload' => 0,
                'default_value' => '<span class="highlight">AI-Driven</span> <br class="lg:hidden"> <sb>Software Development</sb> <br> <sb>Powered by</sb> <br class="lg:hidden"> <span class="highlight">Senior Engineers</span>',
            ],
            [
                'label' => __('Description', 'NscSoftware'),
                'name' => 'description',
                'type' => 'wysiwyg',
                'toolbar' => 'basic',
                'media_upload' => 1,
                'default_value' => '<p>Hire Vietnam’s <b>Top 7% IT Talents</b> to <br> Deliver AI Enterprise Solutions</p>',
            ],
            [
                'label' => __('ISO / badge images', 'NscSoftware'),
                'name' => 'badgeImages',
                'type' => 'repeater',
                'min' => 0,
                'max' => 4,
                'layout' => 'table',
                'sub_fields' => [
                    [
                        'label' => __('Image', 'NscSoftware'),
                        'name' => 'image',
                        'type' => 'image',
                        'return_format' => 'array',
                    ],
                ],
            ],
            [
                'label' => __('Button (CTA)', 'NscSoftware'),
                'name' => 'button',
                'type' => 'group',
                'sub_fields' => [
                    [
                        'label' => __('Label', 'NscSoftware'),
                        'name' => 'label',
                        'type' => 'text',
                        'default_value' => 'Explore',
                    ],
                    [
                        'label' => __('URL', 'NscSoftware'),
                        'name' => 'url',
                        'type' => 'url',
                        'default_value' => home_url('/'),
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
