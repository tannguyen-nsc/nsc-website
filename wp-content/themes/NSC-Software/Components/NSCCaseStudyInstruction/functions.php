<?php

namespace NscSoftware\Components\NSCCaseStudyInstruction;

use NscSoftware\FieldVariables;

function getACFLayout(): array
{
    return [
        'name' => 'nscCaseStudyInstruction',
        'label' => __('NSC Case Study: Instruction', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'label' => __('Business requirement heading', 'NscSoftware'),
                'name' => 'requirementHeading',
                'type' => 'text',
                'default_value' => 'Business Requirement:',
            ],
            [
                'label' => __('Business requirement', 'NscSoftware'),
                'name' => 'requirementBody',
                'type' => 'wysiwyg',
                'toolbar' => 'basic',
                'media_upload' => 0,
            ],
            [
                'label' => __('Result heading', 'NscSoftware'),
                'name' => 'resultHeading',
                'type' => 'text',
                'default_value' => 'Result:',
            ],
            [
                'label' => __('Result', 'NscSoftware'),
                'name' => 'resultBody',
                'type' => 'wysiwyg',
                'toolbar' => 'basic',
                'media_upload' => 0,
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
