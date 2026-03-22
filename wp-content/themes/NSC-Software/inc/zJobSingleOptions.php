<?php

/**
 * NSC Theme Options → Careers: job single sidebar (Why us, Share), key technologies label, Apply (CF7 shortcode).
 *
 * @see single-job.php templates/single-job.twig
 */

use NscSoftware\Utils\Options;

add_action('NscSoftware/afterRegisterComponents', static function (): void {
    Options::addTranslatable('NSCJobSingle', [
        [
            'label' => __('Sidebar', 'NscSoftware'),
            'name' => 'jobSingleSidebarTab',
            'type' => 'tab',
            'placement' => 'top',
            'endpoint' => 0,
        ],
        [
            'label' => __('“Why us?” block title', 'NscSoftware'),
            'name' => 'jobSidebarWhyUsTitle',
            'type' => 'text',
            'default_value' => __('Why us?', 'NscSoftware'),
            'instructions' => __('First sidebar block on job single (career-details__side-block).', 'NscSoftware'),
        ],
        [
            'label' => __('“Why us?” bullet points', 'NscSoftware'),
            'name' => 'jobSidebarWhyUsItems',
            'type' => 'repeater',
            'layout' => 'table',
            'button_label' => __('Add line', 'NscSoftware'),
            'instructions' => __('When empty, the job single shows the same default lines as the static career-details design (domains, remote collaboration, engineering culture, quality, growth).', 'NscSoftware'),
            'sub_fields' => [
                [
                    'label' => __('Line', 'NscSoftware'),
                    'name' => 'line',
                    'type' => 'text',
                    'required' => 1,
                ],
            ],
        ],
        [
            'label' => __('“Share this vacancy” title', 'NscSoftware'),
            'name' => 'jobSidebarShareTitle',
            'type' => 'text',
            'default_value' => __('Share this vacancy', 'NscSoftware'),
            'instructions' => __('Share icons use the same behavior as blog post detail (Facebook / LinkedIn popup with this page URL).', 'NscSoftware'),
        ],
        [
            'label' => __('Job detail', 'NscSoftware'),
            'name' => 'jobSingleDetailTab',
            'type' => 'tab',
            'placement' => 'top',
            'endpoint' => 0,
        ],
        [
            'label' => __('“Key technologies” label', 'NscSoftware'),
            'name' => 'jobKeyTechnologiesLabel',
            'type' => 'text',
            'default_value' => __('Key technologies', 'NscSoftware'),
            'instructions' => __('Label before the comma-separated list on job single (shown when the job has key technologies). A colon is added after the label.', 'NscSoftware'),
        ],
        [
            'label' => __('Apply section', 'NscSoftware'),
            'name' => 'jobSingleApplyTab',
            'type' => 'tab',
            'placement' => 'top',
            'endpoint' => 0,
        ],
        [
            'label' => __('Apply heading', 'NscSoftware'),
            'name' => 'jobApplyHeading',
            'type' => 'text',
            'default_value' => __('Apply now', 'NscSoftware'),
            'instructions' => __('Heading above the apply form (#career-apply).', 'NscSoftware'),
        ],
        [
            'label' => __('Contact Form 7 shortcode', 'NscSoftware'),
            'name' => 'jobApplyCf7Shortcode',
            'type' => 'textarea',
            'rows' => 3,
            'instructions' => __('Optional. Paste a shortcode to override the default. If empty, the theme uses the form created by create-nsc-cf7-form.php with apply_only=1 (option nsc_cf7_job_apply_form_id). Requires Contact Form 7.', 'NscSoftware'),
        ],
        [
            'label' => __('Privacy policy URL (apply consent link)', 'NscSoftware'),
            'name' => 'jobApplyPrivacyPolicyUrl',
            'type' => 'url',
            'instructions' => __('Used in the fallback form only. Leave empty to use the WordPress Privacy Policy page when available.', 'NscSoftware'),
        ],
    ], 'Careers');
}, 21);

/**
 * Default “Why us?” bullets when the repeater has never been filled (matches frontend career-details.html).
 */
add_filter('acf/load_value/name=jobSidebarWhyUsItems', static function ($value, $postId, $field) {
    // Plain options, Polylang/WPML-style ids (options_en, etc.), or legacy "options".
    $pid = is_string($postId) ? $postId : '';
    $isOptionsContext = $pid === 'option'
        || $pid === 'options'
        || ($pid !== '' && strpos($pid, 'options') === 0);
    if (!$isOptionsContext) {
        return $value;
    }
    if (is_array($value) && count($value) > 0) {
        foreach ($value as $row) {
            if (is_array($row) && trim((string) ($row['line'] ?? '')) !== '') {
                return $value;
            }
        }
    }

    return [
        ['line' => __('Diversity of domains and challenging projects', 'NscSoftware')],
        ['line' => __('Remote-friendly collaboration across regions', 'NscSoftware')],
        ['line' => __('Mature engineering culture and clear processes', 'NscSoftware')],
        ['line' => __('Focus on quality, security, and sustainable delivery', 'NscSoftware')],
        ['line' => __('Support for learning and professional growth', 'NscSoftware')],
    ];
}, 10, 3);
