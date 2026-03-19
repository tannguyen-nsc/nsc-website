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
                    Components\NSCBlockAiBanner\getACFLayout(),
                    Components\NSCBlockAiCapabilitiesDetails\getACFLayout(),
                    Components\NSCBlockAiDriven\getACFLayout(),
                    Components\NSCBlockAiImpact\getACFLayout(),
                    Components\NSCBlockAiInfo\getACFLayout(),
                    Components\NSCBlockAiSecurity\getACFLayout(),
                    Components\NSCBlockAiTimeline\getACFLayout(),
                    Components\NSCBlockBlogsArchive\getACFLayout(),
                    Components\NSCBlockBlogsHome\getACFLayout(),
                    Components\NSCBlockCompanySnapshot\getACFLayout(),
                    Components\NSCBlockContactPage\getACFLayout(),
                    Components\NSCBlockContactUs\getACFLayout(),
                    Components\NSCBlockGlobalPresence\getACFLayout(),
                    Components\NSCBlockHero\getACFLayout(),
                    Components\NSCBlockHowWeWork\getACFLayout(),
                    Components\NSCBlockOurCapabilities\getACFLayout(),
                    Components\NSCBlockOurLeaders\getACFLayout(),
                    Components\NSCBlockOurServices\getACFLayout(),
                    Components\NSCBlockOurServicesDetails\getACFLayout(),
                    Components\NSCBlockPolicyPage\getACFLayout(),
                    Components\NSCBlockOurStory\getACFLayout(),
                    Components\NSCBlockSectionHeading\getACFLayout(),
                    Components\NSCBlockStats\getACFLayout(),
                    Components\NSCBlockTechnologyCapability\getACFLayout(),
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
