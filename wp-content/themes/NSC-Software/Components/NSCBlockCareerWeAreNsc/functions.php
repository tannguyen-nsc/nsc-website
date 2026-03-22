<?php

namespace NscSoftware\Components\NSCBlockCareerWeAreNsc;

function getACFLayout(): array
{
    return [
        'name' => 'nscBlockCareerWeAreNsc',
        'label' => __('NSC Block: Career — We are NSC', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'label' => __('Heading icon', 'NscSoftware'),
                'name' => 'headingIcon',
                'type' => 'image',
                'preview_size' => 'thumbnail',
                'return_format' => 'array',
            ],
            [
                'label' => __('Heading (line 1–2 HTML allowed)', 'NscSoftware'),
                'name' => 'heading',
                'type' => 'wysiwyg',
                'toolbar' => 'basic',
                'media_upload' => 0,
                'default_value' => 'WE ARE NSC <br class="hidden xl:block"> SOFTWARE',
                'instructions' => __('Use a line break between “NSC” and “SOFTWARE” to match the static careers layout.', 'NscSoftware'),
            ],
            [
                'label' => __('Body', 'NscSoftware'),
                'name' => 'body',
                'type' => 'wysiwyg',
                'toolbar' => 'basic',
                'media_upload' => 0,
            ],
            [
                'label' => __('CTA link text', 'NscSoftware'),
                'name' => 'ctaText',
                'type' => 'text',
                'default_value' => 'Join NSC and help shape the future of software',
            ],
            [
                'label' => __('CTA link URL', 'NscSoftware'),
                'name' => 'ctaUrl',
                'type' => 'text',
                'default_value' => '#open-positions-app',
                'instructions' => __('Use #open-positions-app to scroll to the jobs list on this page.', 'NscSoftware'),
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
                    \NscSoftware\FieldVariables\getHidden(),
                ],
            ],
        ],
    ];
}
