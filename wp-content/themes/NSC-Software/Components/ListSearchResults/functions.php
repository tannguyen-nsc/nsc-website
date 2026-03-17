<?php

namespace NscSoftware\Components\ListSearchResults;

use NscSoftware\Utils\Options;

add_filter('NscSoftware/addComponentData?name=ListSearchResults', function ($data) {
    $data['uuid'] = $data['uuid'] ?? wp_generate_uuid4();
    return $data;
});

Options::addTranslatable('ListSearchResults', [
    [
        'label' => __('Content', 'NscSoftware'),
        'name' => 'contentTab',
        'type' => 'tab',
        'placement' => 'top',
        'endpoint' => 0
    ],
    [
        'label' => __('Title', 'NscSoftware'),
        'instructions' => __('Title of the search Page.', 'NscSoftware'),
        'name' => 'preContentHtml',
        'type' => 'wysiwyg',
        'required' => 1,
        'default_value' => __('Search Result', 'NscSoftware'),
        'media_upload' => 0,
        'delay' => 0,
    ],
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
                'label' => __('Previous', 'NscSoftware'),
                'name' => 'previous',
                'type' => 'text',
                'default_value' => __('Prev', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('Next', 'NscSoftware'),
                'name' => 'next',
                'type' => 'text',
                'default_value' => __('Next', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('Placeholder - Search', 'NscSoftware'),
                'instructions' => __('The text for the input field.', 'NscSoftware'),
                'name' => 'searchPlaceholder',
                'type' => 'text',
                'required' => 1,
                'default_value' => __('Search …', 'NscSoftware'),
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('Button - Search', 'NscSoftware'),
                'instructions' => __('The text for the search button', 'NscSoftware'),
                'name' => 'search',
                'type' => 'text',
                'default_value' => __('Search', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('Read More', 'NscSoftware'),
                'name' => 'readMore',
                'type' => 'text',
                'default_value' => __('Read More', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
            [
                'label' => __('No Results', 'NscSoftware'),
                'name' => 'noResults',
                'type' => 'text',
                'default_value' => __('No results found.', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
        ],
    ],
]);
