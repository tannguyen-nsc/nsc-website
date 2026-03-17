<?php

namespace NscSoftware\Components\BlockNotFound;

use NscSoftware\Utils\Options;

Options::addTranslatable('BlockNotFound', [
    [
        'label' => __('Content', 'NscSoftware'),
        'name' => 'general',
        'type' => 'tab',
        'placement' => 'top',
        'endpoint' => 0,
    ],
    [
        'label' => __('Text', 'NscSoftware'),
        'instructions' => __('Content to be displayed on the 404 Not Found Page', 'NscSoftware'),
        'name' => 'contentHtml',
        'type' => 'wysiwyg',
        'delay' => 0,
        'media_upload' => 0,
        'required' => 1,
        'default_value' => sprintf('<h1>%1$s</h1><p>%2$s</p>', __('Not Found', 'NscSoftware'), __('The page you are looking for does not exist.', 'NscSoftware')),
    ],
    [
        'label' => __('Back to Homepage Label', 'NscSoftware'),
        'instructions' => __('Leave empty to remove back to home link below the content area.', 'NscSoftware'),
        'name' => 'backLinkLabel',
        'type' => 'text',
        'default_value' => __('Back to homepage', 'NscSoftware')
    ]
]);
