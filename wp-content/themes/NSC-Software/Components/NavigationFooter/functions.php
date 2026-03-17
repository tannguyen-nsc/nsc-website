<?php

namespace NscSoftware\Components\NavigationFooter;

use NscSoftware\Utils\Options;
use Timber\Timber;

add_action('init', function () {
    register_nav_menus([
        'navigation_footer' => __('Navigation Footer', 'NscSoftware')
    ]);
});

add_filter('NscSoftware/addComponentData?name=NavigationFooter', function ($data) {
    $data['menu'] = Timber::get_menu('navigation_footer') ?? Timber::get_pages_menu();

    return $data;
});

Options::addTranslatable('NavigationFooter', [
    [
        'label' => __('Content', 'NscSoftware'),
        'name' => 'contentTab',
        'type' => 'tab',
        'placement' => 'top',
        'endpoint' => 0
    ],
    [
        'label' => __('Text', 'NscSoftware'),
        'name' => 'contentHtml',
        'type' => 'wysiwyg',
        'delay' => 0,
        'media_upload' => 0,
        'required' => 1,
        'toolbar' => 'basic',
        'default_value' => '©&nbsp;' . date_i18n('Y') . ' ' . get_bloginfo('name'),
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
                'label' => __('Aria Label', 'NscSoftware'),
                'name' => 'ariaLabel',
                'type' => 'text',
                'default_value' => __('Footer', 'NscSoftware'),
                'required' => 1,
                'wrapper' => [
                    'width' => '50',
                ],
            ],
        ],
    ],
]);
