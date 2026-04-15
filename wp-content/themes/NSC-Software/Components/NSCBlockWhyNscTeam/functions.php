<?php

namespace NscSoftware\Components\NSCBlockWhyNscTeam;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockWhyNscTeam',
        'label' => __('NSC Block: Why NSC — Team', 'NscSoftware'),
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
                'label' => __('Title — prefix line', 'NscSoftware'),
                'name' => 'titlePrefix',
                'type' => 'text',
                'default_value' => 'A Team Built on',
            ],
            [
                'label' => __('Title — accent word 1', 'NscSoftware'),
                'name' => 'titleAccent1',
                'type' => 'text',
                'default_value' => 'Expertise',
            ],
            [
                'label' => __('Title — conjunction', 'NscSoftware'),
                'name' => 'titleConjunction',
                'type' => 'text',
                'default_value' => 'and',
                'instructions' => __('Lowercase “and” between accents; spacing uses non-breaking spaces in the template.', 'NscSoftware'),
            ],
            [
                'label' => __('Title — accent word 2', 'NscSoftware'),
                'name' => 'titleAccent2',
                'type' => 'text',
                'default_value' => 'Expertise',
            ],
            [
                'label' => __('Lead paragraph', 'NscSoftware'),
                'name' => 'lead',
                'type' => 'textarea',
                'rows' => 3,
                'default_value' => 'At NSC Software, we believe that great technology solutions come from great people. Our culture encourages collaboration, continuous learning, and technical excellence.',
            ],
            [
                'label' => __('Card text (beside slider)', 'NscSoftware'),
                'name' => 'cardText',
                'type' => 'wysiwyg',
                'toolbar' => 'basic',
                'media_upload' => 0,
                'instructions' => __('Allows basic formatting (bold, links, line breaks).', 'NscSoftware'),
                'default_value' => 'Engineers at NSC <br> are empowered to take ownership, <br> explore new technologies, and <br> continuously improve their skills <br> while working on challenging <br> global projects.',
            ],
            [
                'label' => __('Slider images', 'NscSoftware'),
                'name' => 'slides',
                'type' => 'repeater',
                'min' => 1,
                'max' => 20,
                'layout' => 'block',
                'button_label' => __('Add slide', 'NscSoftware'),
                'sub_fields' => [
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
                'label' => __('Slider aria-label', 'NscSoftware'),
                'name' => 'sliderAriaLabel',
                'type' => 'text',
                'default_value' => 'NSC Software team',
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
