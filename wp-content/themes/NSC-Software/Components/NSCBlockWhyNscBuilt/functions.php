<?php

namespace NscSoftware\Components\NSCBlockWhyNscBuilt;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockWhyNscBuilt',
        'label' => __('NSC Block: Why NSC — Built in Vietnam', 'NscSoftware'),
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
                'label' => __('Section title', 'NscSoftware'),
                'name' => 'title',
                'type' => 'wysiwyg',
                'toolbar' => 'basic',
                'media_upload' => 0,
                'default_value' => 'Built in Vietnam. <br class="hidden lg:block"> Delivered Globally.',
            ],
            [
                'label' => __('Intro', 'NscSoftware'),
                'name' => 'intro',
                'type' => 'wysiwyg',
                'toolbar' => 'basic',
                'media_upload' => 0,
                'default_value' => '<p>Founded in Vietnam with a global vision, NSC Software provides high-quality software development and technology consulting for companies worldwide.</p>',
            ],
            [
                'label' => __('Cards', 'NscSoftware'),
                'name' => 'cards',
                'type' => 'repeater',
                'min' => 1,
                'max' => 6,
                'layout' => 'block',
                'button_label' => __('Add card', 'NscSoftware'),
                'sub_fields' => [
                    [
                        'label' => __('Text column first (image on the right)', 'NscSoftware'),
                        'name' => 'text_first',
                        'type' => 'true_false',
                        'default_value' => 0,
                    ],
                    [
                        'label' => __('Image', 'NscSoftware'),
                        'name' => 'image',
                        'type' => 'image',
                        'preview_size' => 'medium',
                        'return_format' => 'array',
                    ],
                    [
                        'label' => __('Body', 'NscSoftware'),
                        'name' => 'body',
                        'type' => 'wysiwyg',
                        'toolbar' => 'basic',
                        'media_upload' => 0,
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
