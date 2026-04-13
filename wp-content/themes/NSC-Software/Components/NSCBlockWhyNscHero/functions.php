<?php

namespace NscSoftware\Components\NSCBlockWhyNscHero;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockWhyNscHero',
        'label' => __('NSC Block: Why NSC — Hero', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
            ],
            [
                'label' => __('Background image (desktop)', 'NscSoftware'),
                'name' => 'imageDesktop',
                'type' => 'image',
                'preview_size' => 'medium',
                'return_format' => 'array',
                'instructions' => __('Optional. Defaults to the Why NSC hero asset from the theme build.', 'NscSoftware'),
            ],
            [
                'label' => __('Background image (mobile)', 'NscSoftware'),
                'name' => 'imageMobile',
                'type' => 'image',
                'preview_size' => 'medium',
                'return_format' => 'array',
                'instructions' => __('Optional. Defaults to the Why NSC hero asset from the theme build.', 'NscSoftware'),
            ],
            [
                'label' => __('Title — first word', 'NscSoftware'),
                'name' => 'titleWhy',
                'type' => 'text',
                'default_value' => 'Why',
            ],
            [
                'label' => __('Title — brand', 'NscSoftware'),
                'name' => 'titleBrand',
                'type' => 'text',
                'default_value' => 'NSC Software',
            ],
            [
                'label' => __('Lead line', 'NscSoftware'),
                'name' => 'lead',
                'type' => 'text',
                'default_value' => 'Engineering Excellence Powered by Senior Talent',
            ],
            [
                'label' => __('Intro paragraph', 'NscSoftware'),
                'name' => 'intro',
                'type' => 'wysiwyg',
                'toolbar' => 'basic',
                'media_upload' => 0,
                'default_value' => '<p>At NSC Software, we believe great software is built by experienced engineers, strong technical leadership, and efficient collaboration. By combining Vietnam\'s top technology talent with AI-enhanced development workflows, we help global companies build scalable, high-quality digital solutions.</p>',
            ],
            [
                'label' => __('Mission — prefix', 'NscSoftware'),
                'name' => 'missionPrefix',
                'type' => 'text',
                'default_value' => 'Our mission is simple:',
            ],
            [
                'label' => __('Mission — emphasis', 'NscSoftware'),
                'name' => 'missionEmphasis',
                'type' => 'textarea',
                'rows' => 2,
                'default_value' => 'deliver world-class engineering quality while enabling businesses to move faster, smarter, and more efficiently.',
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
