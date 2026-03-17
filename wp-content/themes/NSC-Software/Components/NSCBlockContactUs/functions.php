<?php

namespace NscSoftware\Components\NSCBlockContactUs;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockContactUs',
        'label' => __('NSC Block: Contact Us', 'NscSoftware'),
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
                'label' => __('Heading icon', 'NscSoftware'),
                'name' => 'headingIcon',
                'type' => 'image',
                'preview_size' => 'thumbnail',
                'return_format' => 'array',
            ],
            [
                'label' => __('Section title', 'NscSoftware'),
                'name' => 'title',
                'type' => 'text',
                'default_value' => 'CONTACT',
            ],
            [
                'label' => __('Content lines', 'NscSoftware'),
                'name' => 'contentLines',
                'type' => 'textarea',
                'instructions' => __('One line per paragraph (e.g. "Ideas that inspire.")', 'NscSoftware'),
                'default_value' => "Ideas that inspire.\nStories that shape the future.",
            ],
            [
                'label' => __('Show contact form', 'NscSoftware'),
                'name' => 'showForm',
                'type' => 'true_false',
                'default_value' => 1,
            ],
            [
                'label' => __('Contact Form 7 shortcode', 'NscSoftware'),
                'name' => 'cf7Shortcode',
                'type' => 'text',
                'instructions' => __('Paste the full Contact Form 7 shortcode (e.g. [contact-form-7 id="123" title="Contact"]). Leave empty to hide the form.', 'NscSoftware'),
                'placeholder' => '[contact-form-7 id="123" title="Contact"]',
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
