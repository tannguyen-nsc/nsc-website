<?php

use ACFComposer\ACFComposer;
use NscSoftware\Components;

add_action('NscSoftware/afterRegisterComponents', function () {
    ACFComposer::registerFieldGroup([
        'name' => 'reusableComponents',
        'title' => __('Reusable Components', 'NscSoftware'),
        'style' => 'seamless',
        'menu_order' => 1,
        'fields' => [
            [
                'name' => 'reusableComponents',
                'label' => __('Reusable Components', 'NscSoftware'),
                'type' => 'flexible_content',
                'button_label' => __('Add Component', 'NscSoftware'),
                'layouts' => [
                    Components\NSCBlockSectionHeading\getACFLayout(),
                ],
            ]
        ],
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'reusable-components'
                ],
            ]
        ]
    ]);
});
