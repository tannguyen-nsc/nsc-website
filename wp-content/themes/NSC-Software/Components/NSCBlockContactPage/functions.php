<?php

namespace NscSoftware\Components\NSCBlockContactPage;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockContactPage',
        'label' => __('NSC Block: Contact Page (form + offices)', 'NscSoftware'),
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
                'default_value' => 'CONTACT US',
            ],
            [
                'label' => __('Tagline (content lines)', 'NscSoftware'),
                'name' => 'contentLines',
                'type' => 'textarea',
                'instructions' => __('One line per paragraph.', 'NscSoftware'),
                'default_value' => "Ideas that inspire.\nStories that shape the future.",
            ],
            [
                'label' => __('Show contact form', 'NscSoftware'),
                'name' => 'showForm',
                'type' => 'true_false',
                'default_value' => 1,
            ],
            [
                'label' => __('Form action URL', 'NscSoftware'),
                'name' => 'formAction',
                'type' => 'url',
                'instructions' => __('Form submit URL. Use # for same page or your form handler.', 'NscSoftware'),
            ],
            [
                'label' => __('Contact Form 7 shortcode', 'NscSoftware'),
                'name' => 'cf7Shortcode',
                'type' => 'text',
                'instructions' => __('Optional. If set, form is replaced by this shortcode. Leave empty for native form.', 'NscSoftware'),
            ],
            [
                'label' => __('Offices section title', 'NscSoftware'),
                'name' => 'officesTitle',
                'type' => 'text',
                'default_value' => 'OUR OFFICES',
            ],
            [
                'label' => __('Offices', 'NscSoftware'),
                'name' => 'offices',
                'type' => 'repeater',
                'min' => 0,
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'label' => __('Office name', 'NscSoftware'),
                        'name' => 'title',
                        'type' => 'text',
                    ],
                    [
                        'label' => __('Address', 'NscSoftware'),
                        'name' => 'address',
                        'type' => 'textarea',
                    ],
                    [
                        'label' => __('Phone (display text)', 'NscSoftware'),
                        'name' => 'phoneDisplay',
                        'type' => 'text',
                        'instructions' => __('e.g. (+84) 866 639 497', 'NscSoftware'),
                    ],
                    [
                        'label' => __('Phone link (tel:)', 'NscSoftware'),
                        'name' => 'phoneLink',
                        'type' => 'text',
                        'instructions' => __('e.g. +84866639497 for click-to-call. Leave empty to show phone as text only.', 'NscSoftware'),
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
                    FieldVariables\getHidden(),
                ],
            ],
        ],
    ];
}
