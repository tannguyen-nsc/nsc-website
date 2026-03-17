<?php

namespace NscSoftware\Components\BlockPostHeader;

use NscSoftware\Utils\Options;

add_filter('NscSoftware/addComponentData?name=BlockPostHeader', function ($data) {
    $data['dateFormat'] = get_option('date_format');
    return $data;
});

Options::addTranslatable('BlockPostHeader', [
    [
        'label' => __('Labels', 'NscSoftware'),
        'name' => 'labelsTab',
        'type' => 'tab',
        'placement' => 'top',
        'endpoint' => 0
    ],
    [
        'label' => '',
        'name' => 'labels',
        'type' => 'group',
        'sub_fields' => [
            [
                'label' => __('Posted by', 'NscSoftware'),
                'name' => 'postedBy',
                'type' => 'text',
                'default_value' => __('Posted by', 'NscSoftware'),
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('(Posted) in', 'NscSoftware'),
                'name' => 'postedIn',
                'type' => 'text',
                'default_value' => __('in', 'NscSoftware'),
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('Reading Time - (20) min read', 'NscSoftware'),
                // translators: %d: Placeholder for a number
                'instructions' => __('%d is placeholder for number of minutes', 'NscSoftware'),
                'name' => 'readingTime',
                'type' => 'text',
                // translators: %d: Placeholder for a number
                'default_value' => __('%d min read', 'NscSoftware'),
                'wrapper' => [
                    'width' => '50',
                ],
            ],
        ],
    ],
]);
