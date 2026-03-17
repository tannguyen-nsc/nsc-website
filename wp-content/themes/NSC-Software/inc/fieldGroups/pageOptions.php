<?php

/**
 * Page-level options (e.g. header type) shown in the sidebar when editing a page.
 */

use ACFComposer\ACFComposer;

add_action('NscSoftware/afterRegisterComponents', function () {
    ACFComposer::registerFieldGroup([
        'name' => 'pageOptions',
        'title' => __('Page options', 'NscSoftware'),
        'fields' => [
            [
                'label' => __('Header type', 'NscSoftware'),
                'name' => 'header_type',
                'type' => 'select',
                'choices' => [
                    '' => __('Default', 'NscSoftware'),
                    'home' => __('Home (add class "home" to header)', 'NscSoftware'),
                    'transparent_floating' => __('Transparent / floating (e.g. About page)', 'NscSoftware'),
                ],
                'default_value' => '',
                'instructions' => __('Choose the header style for this page. Home: wave hero style. Transparent: floating header. Default: standard header.', 'NscSoftware'),
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'page',
                ],
            ],
        ],
        'position' => 'side',
    ]);
});
