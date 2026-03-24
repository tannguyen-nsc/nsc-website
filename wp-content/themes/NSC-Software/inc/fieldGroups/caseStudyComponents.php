<?php

/**
 * Case study single: flexible components (Hero, Instruction, Quote, Contact, Main content).
 * Renders via templates/single-case-study.twig (matches case study detail + contact strip before overview).
 */

use ACFComposer\ACFComposer;
use NscSoftware\Components;

add_action('NscSoftware/afterRegisterComponents', static function (): void {
    ACFComposer::registerFieldGroup([
        'name' => 'caseStudyComponents',
        'title' => __('Case study components', 'NscSoftware'),
        'style' => 'seamless',
        'fields' => [
            [
                'name' => 'caseStudyComponents',
                'label' => __('Case study components', 'NscSoftware'),
                'type' => 'flexible_content',
                'button_label' => __('Add component', 'NscSoftware'),
                'instructions' => __('Add Hero, Instruction, Quote, Contact, and Main content (overview + gallery + sidebar). Order matches the public case study detail page.', 'NscSoftware'),
                'layouts' => [
                    Components\NSCCaseStudyHero\getACFLayout(),
                    Components\NSCCaseStudyInstruction\getACFLayout(),
                    Components\NSCCaseStudyQuote\getACFLayout(),
                    Components\NSCCaseStudyContact\getACFLayout(),
                    Components\NSCCaseStudyMain\getACFLayout(),
                ],
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'case_study',
                ],
            ],
        ],
        'position' => 'acf_after_title',
    ]);
});
