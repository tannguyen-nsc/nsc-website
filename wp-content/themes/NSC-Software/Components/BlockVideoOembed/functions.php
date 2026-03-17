<?php

namespace NscSoftware\Components\BlockVideoOembed;

use NscSoftware\FieldVariables;
use NscSoftware\Utils\Oembed;

add_filter('NscSoftware/addComponentData?name=BlockVideoOembed', function ($data) {
    $data['oembed'] = Oembed::setSrcAsDataAttribute(
        $data['oembed'],
        [
            'autoplay' => 'true'
        ]
    );

    return $data;
});

function getACFLayout()
{
    return [
        'name' => 'blockVideoOembed',
        'label' =>  __('Block: Video Oembed', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0
            ],
            [
                'label' => __('Poster Image', 'NscSoftware'),
                'instructions' => __('Image-Format: JPG, PNG, SVG, WebP. Aspect Ratio: 16:9. Recommended Size: 1920px × 1080px.', 'NscSoftware'),
                'name' => 'posterImage',
                'type' => 'image',
                'preview_size' => 'medium',
                'mime_types' => 'jpg,jpeg,png,svg,webp',
                'required' => 1,
            ],
            [
                'label' => __('Video', 'NscSoftware'),
                'name' => 'oembed',
                'type' => 'oembed',
                'required' => 1,
                'videoParams' => [
                    'autoplay' => 1,
                ]
            ],
            [
                'label' => __('Options', 'NscSoftware'),
                'name' => 'optionsTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0
            ],
            [
                'label' => '',
                'name' => 'options',
                'type' => 'group',
                'layout' => 'row',
                'sub_fields' => [
                    FieldVariables\getTheme(),
                    FieldVariables\getSize()
                ]
            ]
        ]
    ];
}
