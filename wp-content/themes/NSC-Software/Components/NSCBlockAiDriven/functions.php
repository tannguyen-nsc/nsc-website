<?php

namespace NscSoftware\Components\NSCBlockAiDriven;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockAiDriven',
        'label' => __('NSC Block: AI-Driven', 'NscSoftware'),
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
                'label' => __('Headline 1', 'NscSoftware'),
                'name' => 'headline1',
                'type' => 'wysiwyg',
                'toolbar' => 'basic',
                'media_upload' => 0,
                'default_value' => '<span>AI-Driven</span> <br> Software <br> Development',
            ],
            [
                'label' => __('Headline 2', 'NscSoftware'),
                'name' => 'headline2',
                'type' => 'wysiwyg',
                'toolbar' => 'basic',
                'media_upload' => 0,
                'default_value' => 'Power by <br> <span>Senior <br class="hidden lg:block"> Engineers</span>',
            ],
            [
                'label' => __('Description paragraphs', 'NscSoftware'),
                'name' => 'description',
                'type' => 'wysiwyg',
                'toolbar' => 'basic',
                'media_upload' => 0,
            ],
            [
                'label' => __('Button (CTA)', 'NscSoftware'),
                'name' => 'button',
                'type' => 'group',
                'sub_fields' => [
                    ['label' => __('Label', 'NscSoftware'), 'name' => 'label', 'type' => 'text', 'default_value' => 'Learn How We Leverage AI'],
                    ['label' => __('URL', 'NscSoftware'), 'name' => 'url', 'type' => 'url', 'default_value' => home_url('/')],
                    ['label' => __('Open in new tab', 'NscSoftware'), 'name' => 'openInNewTab', 'type' => 'true_false', 'default_value' => 0],
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
