<?php

namespace NscSoftware\Components\NSCBlockWhyNscDifferent;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockWhyNscDifferent',
        'label' => __('NSC Block: Why NSC — What makes us different', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
            ],
            [
                'label' => __('Section background', 'NscSoftware'),
                'name' => 'backgroundImage',
                'type' => 'image',
                'preview_size' => 'medium',
                'return_format' => 'array',
                'instructions' => __('Optional. Defaults to what-make-diff-bg.webp from the theme build.', 'NscSoftware'),
            ],
            [
                'label' => __('Heading icon', 'NscSoftware'),
                'name' => 'headingIcon',
                'type' => 'image',
                'preview_size' => 'thumbnail',
                'return_format' => 'array',
            ],
            [
                'label' => __('Heading line 1', 'NscSoftware'),
                'name' => 'titleLine1',
                'type' => 'text',
                'default_value' => 'WHAT MAKES NSC',
            ],
            [
                'label' => __('Heading line 2 (highlighted)', 'NscSoftware'),
                'name' => 'titleLine2',
                'type' => 'text',
                'default_value' => 'DIFFERENT',
            ],
            [
                'label' => __('Items', 'NscSoftware'),
                'name' => 'items',
                'type' => 'repeater',
                'min' => 1,
                'max' => 12,
                'layout' => 'block',
                'button_label' => __('Add item', 'NscSoftware'),
                'instructions' => __('Each item: heading (without number), detail content (include lists/images as needed), and the feature image used for previews and data attributes.', 'NscSoftware'),
                'sub_fields' => [
                    [
                        'label' => __('Heading (without leading number)', 'NscSoftware'),
                        'name' => 'title',
                        'type' => 'text',
                    ],
                    [
                        'label' => __('Detail content', 'NscSoftware'),
                        'name' => 'body',
                        'type' => 'wysiwyg',
                        'toolbar' => 'basic',
                        'media_upload' => 1,
                        'instructions' => __('Paragraphs and lists only — the large photo below each item comes from Feature image (WordPress often strips &lt;img&gt; from WYSIWYG).', 'NscSoftware'),
                    ],
                    [
                        'label' => __('Feature image', 'NscSoftware'),
                        'name' => 'feature_image',
                        'type' => 'image',
                        'preview_size' => 'medium',
                        'return_format' => 'array',
                        'instructions' => __('Used for column preview and data-feature-img (required for the interaction script).', 'NscSoftware'),
                    ],
                    [
                        'label' => __('Feature image alt', 'NscSoftware'),
                        'name' => 'feature_alt',
                        'type' => 'text',
                    ],
                ],
            ],
            [
                'label' => __('Bottom sheet decoration', 'NscSoftware'),
                'name' => 'bottomSheetBg',
                'type' => 'image',
                'preview_size' => 'medium',
                'return_format' => 'array',
                'instructions' => __('Optional. Defaults to why-nsc-different-col-bg.png from the theme build.', 'NscSoftware'),
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
