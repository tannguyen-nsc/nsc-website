<?php

/**
 * Job opening (CPT job) fields: skills repeater, descriptions, customer, project, tech, listing date.
 */

use ACFComposer\ACFComposer;

add_action('NscSoftware/afterRegisterComponents', static function (): void {
    ACFComposer::registerFieldGroup([
        'name' => 'nscJobFields',
        'title' => __('Job opening details', 'NscSoftware'),
        'fields' => [
            [
                'label' => __('Listing date', 'NscSoftware'),
                'name' => 'nsc_job_listing_date',
                'type' => 'date_picker',
                'instructions' => __('Shown in the open positions section.', 'NscSoftware'),
                'display_format' => 'd/m/Y',
                'return_format' => 'Y-m-d',
                'first_day' => 1,
            ],
            [
                'label' => __('Required skills', 'NscSoftware'),
                'name' => 'nsc_job_required_skills',
                'type' => 'repeater',
                'layout' => 'block',
                'button_label' => __('Add skill', 'NscSoftware'),
                'sub_fields' => [
                    [
                        'label' => __('Title', 'NscSoftware'),
                        'name' => 'skill_title',
                        'type' => 'text',
                        'required' => 1,
                    ],
                    [
                        'label' => __('Expected points', 'NscSoftware'),
                        'name' => 'skill_expected_points',
                        'type' => 'number',
                        'min' => 0,
                        'step' => 1,
                    ],
                    [
                        'label' => __('Total points', 'NscSoftware'),
                        'name' => 'skill_total_points',
                        'type' => 'number',
                        'min' => 1,
                        'step' => 1,
                        'default_value' => 4,
                    ],
                    [
                        'label' => __('Description', 'NscSoftware'),
                        'name' => 'skill_description',
                        'type' => 'wysiwyg',
                        'tabs' => 'all',
                        'toolbar' => 'basic',
                        'media_upload' => 1,
                        'delay' => 0,
                    ],
                ],
            ],
            [
                'label' => __('Job description', 'NscSoftware'),
                'name' => 'nsc_job_description',
                'type' => 'wysiwyg',
                'tabs' => 'all',
                'toolbar' => 'full',
                'media_upload' => 1,
                'delay' => 0,
            ],
            [
                'label' => __('Customer company name', 'NscSoftware'),
                'name' => 'nsc_job_customer_company',
                'type' => 'text',
                'instructions' => __('Displayed in the open positions section.', 'NscSoftware'),
            ],
            [
                'label' => __('Customer (detail content)', 'NscSoftware'),
                'name' => 'nsc_job_customer_content',
                'type' => 'wysiwyg',
                'instructions' => __('Shown on the career / job detail page.', 'NscSoftware'),
                'tabs' => 'all',
                'toolbar' => 'full',
                'media_upload' => 1,
                'delay' => 0,
            ],
            [
                'label' => __('Project', 'NscSoftware'),
                'name' => 'nsc_job_project',
                'type' => 'wysiwyg',
                'tabs' => 'all',
                'toolbar' => 'full',
                'media_upload' => 1,
                'delay' => 0,
            ],
            [
                'label' => __('Key technologies', 'NscSoftware'),
                'name' => 'nsc_job_key_technologies',
                'type' => 'repeater',
                'layout' => 'table',
                'button_label' => __('Add technology', 'NscSoftware'),
                'sub_fields' => [
                    [
                        'label' => __('Technology', 'NscSoftware'),
                        'name' => 'technology_name',
                        'type' => 'text',
                        'required' => 1,
                    ],
                ],
            ],
        ],
        'location' => [
            [
                [
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'job',
                ],
            ],
        ],
        'position' => 'normal',
        'style' => 'default',
    ]);
});
