<?php

namespace NscSoftware\Components\NSCBlockHowWeWorkPageHero;

use NscSoftware\FieldVariables;

function getACFLayout()
{
    return [
        'name' => 'nscBlockHowWeWorkPageHero',
        'label' => __('NSC Block: How we work — Hero', 'NscSoftware'),
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
                'instructions' => __('Optional. Defaults to how-we-work hero assets from the theme build.', 'NscSoftware'),
            ],
            [
                'label' => __('Background image (mobile)', 'NscSoftware'),
                'name' => 'imageMobile',
                'type' => 'image',
                'preview_size' => 'medium',
                'return_format' => 'array',
            ],
            [
                'label' => __('Title — first word', 'NscSoftware'),
                'name' => 'titleHow',
                'type' => 'text',
                'default_value' => 'How',
            ],
            [
                'label' => __('Title — second part', 'NscSoftware'),
                'name' => 'titleWeWork',
                'type' => 'text',
                'default_value' => 'We Work',
            ],
            [
                'label' => __('Lead line', 'NscSoftware'),
                'name' => 'lead',
                'type' => 'textarea',
                'rows' => 2,
                'default_value' => 'Flexible engagement models designed for transparency, efficiency, and long-term partnership.',
            ],
            [
                'label' => __('Intro copy', 'NscSoftware'),
                'name' => 'intro',
                'type' => 'wysiwyg',
                'toolbar' => 'basic',
                'media_upload' => 0,
                'default_value' => '<p>At <span class="how-we-work-page-hero__brand">NSC Software</span>, we understand that every organization has different technical needs, team structures, and development goals. That&rsquo;s why we offer flexible collaboration models that allow clients to choose the level of control, scalability, and management support that best fits their project.</p><p>Our engagement models are designed to ensure clear communication, predictable delivery, and efficient collaboration throughout the entire development lifecycle.</p>',
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
