<?php

use ACFComposer\ACFComposer;
use NscSoftware\Components;

add_action('NscSoftware/afterRegisterComponents', function () {
    ACFComposer::registerFieldGroup([
        'name' => 'pageComponents',
        'title' => __('Page Components', 'NscSoftware'),
        'style' => 'seamless',
        'fields' => [
            [
                'name' => 'pageComponents',
                'label' => __('Page Components', 'NscSoftware'),
                'type' => 'flexible_content',
                'button_label' => __('Add Component', 'NscSoftware'),
                'layouts' => [
                    Components\NSCBlockAiDriven\getACFLayout(),
                    Components\NSCBlockBlogsArchive\getACFLayout(),
                    Components\NSCBlockBlogsHome\getACFLayout(),
                    Components\NSCBlockContactUs\getACFLayout(),
                    Components\NSCBlockHero\getACFLayout(),
                    Components\NSCBlockHowWeWork\getACFLayout(),
                    Components\NSCBlockOurServices\getACFLayout(),
                    Components\NSCBlockSectionHeading\getACFLayout(),
                    Components\NSCBlockStats\getACFLayout(),
                    Components\NSCBlockTestimonials\getACFLayout(),
                    Components\NSCBlockWhyUs\getACFLayout(),
                ],
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'page'
                ]
            ],
        ],
    ]);
});
