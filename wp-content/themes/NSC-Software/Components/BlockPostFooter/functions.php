<?php

namespace NscSoftware\Components\BlockPostFooter;

use NscSoftware\Utils\Options;

add_filter('NscSoftware/addComponentData?name=BlockPostFooter', function ($data) {

    return $data;
});

Options::addTranslatable('BlockPostFooter', [
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
                'label' => __('Tags', 'NscSoftware'),
                'name' => 'tagsLabel',
                'type' => 'text',
                'default_value' => __('Tags', 'NscSoftware'),
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('Posted by', 'NscSoftware'),
                'name' => 'postedByLabel',
                'type' => 'text',
                'default_value' => __('Posted by', 'NscSoftware'),
                'wrapper' => [
                    'width' => '50',
                ],
            ],
        ],
    ],
]);
