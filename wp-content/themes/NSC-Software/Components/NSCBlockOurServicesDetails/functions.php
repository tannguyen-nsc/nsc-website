<?php

namespace NscSoftware\Components\NSCBlockOurServicesDetails;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockOurServicesDetails',
        'label' => __('NSC Block: Our Services Details (page)', 'NscSoftware'),
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
                'label' => __('Title line 1', 'NscSoftware'),
                'name' => 'titleLine1',
                'type' => 'text',
            ],
            [
                'label' => __('Title line 2', 'NscSoftware'),
                'name' => 'titleLine2',
                'type' => 'text',
            ],
            [
                'label' => __('Title line 3 (supports HTML, e.g. &lt;span class="highlight"&gt;ADVANTAGE&lt;/span&gt;)', 'NscSoftware'),
                'name' => 'titleLine3',
                'type' => 'text',
            ],
            [
                'label' => __('Intro paragraphs', 'NscSoftware'),
                'name' => 'introParagraphs',
                'type' => 'wysiwyg',
                'instructions' => __('Two paragraphs for the intro column.', 'NscSoftware'),
            ],
            [
                'label' => __('Service items', 'NscSoftware'),
                'name' => 'serviceItems',
                'type' => 'repeater',
                'min' => 1,
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'label' => __('Number', 'NscSoftware'),
                        'name' => 'number',
                        'type' => 'text',
                    ],
                    [
                        'label' => __('Title', 'NscSoftware'),
                        'name' => 'title',
                        'type' => 'text',
                    ],
                    [
                        'label' => __('Subtitle (description heading)', 'NscSoftware'),
                        'name' => 'subtitle',
                        'type' => 'text',
                    ],
                    [
                        'label' => __('Description paragraph', 'NscSoftware'),
                        'name' => 'description',
                        'type' => 'textarea',
                    ],
                    [
                        'label' => __('List items', 'NscSoftware'),
                        'name' => 'listItems',
                        'type' => 'repeater',
                        'min' => 0,
                        'layout' => 'table',
                        'sub_fields' => [
                            [
                                'label' => __('Item', 'NscSoftware'),
                                'name' => 'item',
                                'type' => 'text',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'label' => __('CTA banner', 'NscSoftware'),
                'name' => 'banner',
                'type' => 'group',
                'sub_fields' => [
                    [
                        'label' => __('Background image', 'NscSoftware'),
                        'name' => 'backgroundImage',
                        'type' => 'image',
                        'preview_size' => 'medium',
                        'return_format' => 'array',
                    ],
                    [
                        'label' => __('Title', 'NscSoftware'),
                        'name' => 'title',
                        'type' => 'text',
                    ],
                    [
                        'label' => __('Description', 'NscSoftware'),
                        'name' => 'description',
                        'type' => 'wysiwyg',
                    ],
                    [
                        'label' => __('Button', 'NscSoftware'),
                        'name' => 'button',
                        'type' => 'group',
                        'sub_fields' => [
                            ['label' => __('Label', 'NscSoftware'), 'name' => 'label', 'type' => 'text'],
                            ['label' => __('URL', 'NscSoftware'), 'name' => 'url', 'type' => 'url'],
                            ['label' => __('Open in new tab', 'NscSoftware'), 'name' => 'openInNewTab', 'type' => 'true_false', 'default_value' => 0],
                        ],
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
