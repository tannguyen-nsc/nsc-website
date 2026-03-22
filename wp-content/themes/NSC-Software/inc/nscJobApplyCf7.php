<?php

declare(strict_types=1);

/**
 * Job apply CF7: prefill Position field on singular job posts (locked via JS/CSS, not readonly attr).
 *
 * Form field name must be `job_position` (see create-nsc-cf7-form.php apply form).
 */

/**
 * Custom / overridden CF7 "Messages" strings for the job application form (editable in WP admin).
 *
 * @return array<string, string>
 */
function nsc_job_apply_cf7_message_overrides(): array
{
    return [
        'upload_file_type_invalid' => __('Please upload a PDF, DOC, or DOCX file.', 'NscSoftware'),
        'upload_file_too_large' => __('File is too large (max 5 MB).', 'NscSoftware'),
        'upload_failed' => __('CV upload failed. You can still submit with LinkedIn or try again.', 'NscSoftware'),
        'nsc_cv_uploading' => __('Uploading CV…', 'NscSoftware'),
        'nsc_cv_uploaded' => __('CV uploaded: %s', 'NscSoftware'),
        'nsc_cv_network_error' => __('Network error while uploading. Check your connection and try again.', 'NscSoftware'),
        'nsc_cv_empty_file' => __('The file is empty. Please choose a valid CV.', 'NscSoftware'),
        'nsc_cv_bad_mime' => __('Please upload a PDF, DOC, or DOCX file (type not accepted).', 'NscSoftware'),
    ];
}

/** Register extra message keys so they appear in CF7 admin and survive wpcf7_sanitize_messages(). */
add_filter('wpcf7_messages', static function (array $messages): array {
    $custom = [
        'nsc_cv_uploading' => [
            'description' => __('Job apply: shown while the CV is uploading (staging).', 'NscSoftware'),
        ],
        'nsc_cv_uploaded' => [
            'description' => __('Job apply: success line; use %s for the file name.', 'NscSoftware'),
        ],
        'nsc_cv_network_error' => [
            'description' => __('Job apply: network error while staging CV.', 'NscSoftware'),
        ],
        'nsc_cv_empty_file' => [
            'description' => __('Job apply: chosen file is empty.', 'NscSoftware'),
        ],
        'nsc_cv_bad_mime' => [
            'description' => __('Job apply: file MIME type not accepted.', 'NscSoftware'),
        ],
    ];
    $over = nsc_job_apply_cf7_message_overrides();
    foreach ($custom as $key => $meta) {
        if (!isset($over[$key])) {
            continue;
        }
        $messages[$key] = [
            'description' => $meta['description'],
            'default' => $over[$key],
        ];
    }

    return $messages;
}, 15, 1);

/**
 * Fill missing job-apply message strings (e.g. old DB rows) from theme defaults.
 *
 * @param array<string, string> $messages
 * @return array<string, string>
 */
add_filter('wpcf7_contact_form_property_messages', static function ($messages, $contact_form) {
    if (!$contact_form instanceof \WPCF7_ContactForm || !function_exists('nsc_job_apply_cf7_form_id')) {
        return $messages;
    }
    if (nsc_job_apply_cf7_form_id() <= 0 || (int) $contact_form->id() !== nsc_job_apply_cf7_form_id()) {
        return $messages;
    }
    $messages = is_array($messages) ? $messages : [];
    foreach (nsc_job_apply_cf7_message_overrides() as $key => $value) {
        if (!isset($messages[$key]) || $messages[$key] === '' || $messages[$key] === null) {
            $messages[$key] = $value;
        }
    }

    return $messages;
}, 10, 2);

/**
 * Job apply: validate privacy acceptance on submit (not by disabling the submit button).
 * CF7 adds class wpcf7-acceptance-as-validation + runs wpcf7_validate_acceptance when this is on.
 *
 * @param string $settings
 * @return string
 */
add_filter('wpcf7_contact_form_property_additional_settings', static function ($settings, $contact_form) {
    if (!$contact_form instanceof \WPCF7_ContactForm || !function_exists('nsc_job_apply_cf7_form_id')) {
        return $settings;
    }
    if (nsc_job_apply_cf7_form_id() <= 0 || (int) $contact_form->id() !== nsc_job_apply_cf7_form_id()) {
        return $settings;
    }
    $settings = is_string($settings) ? $settings : '';
    if (preg_match('/^acceptance_as_validation\s*:/m', $settings)) {
        return $settings;
    }
    $settings = trim($settings);
    $line = 'acceptance_as_validation: on';

    return $settings === '' ? 'autop: off' . "\n" . $line : $settings . "\n" . $line;
}, 10, 2);

/** Avoid CF7 default styles conflicting with career-details apply layout on job singles. */
add_filter('wpcf7_load_css', static function ($load) {
    if (function_exists('is_singular') && is_singular('job')) {
        return false;
    }

    return $load;
}, 10, 1);

/**
 * Contact Form 7 ignores `autop: off` in Additional Settings unless this filter runs.
 * Without it, wpcf7_autop() wraps/repairs HTML and breaks custom templates (raw shortcodes,
 * broken grids). Honor is_false('autop') for every form that sets it.
 */
add_filter('wpcf7_autop_or_not', static function ($autop, $options) {
    if (!is_array($options) || (($options['for'] ?? 'form') !== 'form')) {
        return $autop;
    }
    $form = \WPCF7_ContactForm::get_current();
    if (!$form instanceof \WPCF7_ContactForm) {
        return $autop;
    }
    if ($form->is_false('autop')) {
        return false;
    }

    return $autop;
}, 10, 2);

/**
 * Put `career-details__apply-form` on the real <form> like static career-details.html.
 * Otherwise that class sits on a wrapper around .wpcf7 and flex/gap/max-width apply to the
 * wrong node (theme looked wrong vs root frontend build).
 */
add_filter('wpcf7_form_class_attr', static function ($class) {
    $applyId = (int) get_option('nsc_cf7_job_apply_form_id', 0);
    if ($applyId <= 0) {
        return $class;
    }
    $cf = \WPCF7_ContactForm::get_current();
    if (!$cf instanceof \WPCF7_ContactForm || (int) $cf->id() !== $applyId) {
        return $class;
    }
    $parts = preg_split('/\s+/', trim((string) $class), -1, PREG_SPLIT_NO_EMPTY);
    $parts = is_array($parts) ? $parts : [];
    $parts[] = 'career-details__apply-form';

    return implode(' ', array_unique(array_filter(array_map('sanitize_html_class', $parts))));
}, 10, 1);

/**
 * Fix job apply form template stored in DB: CF7 rejects textarea tags when quoted
 * placeholder is not last (see parse_atts in CF7). Updates live without re-running
 * create-nsc-cf7-form.php.
 *
 * @param string|null $form
 */
add_filter('wpcf7_contact_form_property_form', static function ($form, $contact_form) {
    $applyId = (int) get_option('nsc_cf7_job_apply_form_id', 0);
    if ($applyId <= 0 || !is_object($contact_form) || (int) $contact_form->id() !== $applyId) {
        return $form;
    }
    if (!is_string($form) || $form === '') {
        return $form;
    }

    $broken = [
        '[textarea applicant_comment class:career-details__textarea placeholder "COMMENT" rows:4]',
        "[textarea applicant_comment class:career-details__textarea placeholder 'COMMENT' rows:4]",
        '[textarea applicant_comment class:career-details__textarea rows:4 placeholder "COMMENT"]',
    ];
    // 40x4 = cols×rows (CF7); placeholder must be last quoted segment for parse_atts.
    $fixed = '[textarea applicant_comment 40x4 class:career-details__textarea placeholder "COMMENT"]';

    foreach ($broken as $bad) {
        if (strpos($form, $bad) !== false) {
            $form = str_replace($bad, $fixed, $form);
        }
    }

    // Legacy: swap visual class; editing is blocked in JS (no HTML readonly — reliable CF7 post).
    if (preg_match('/\[text\s+job_position\b/', $form)) {
        $form = preg_replace(
            '/(\[text\s+job_position\b[^\]]*?)class:career-details__input--readonly/',
            '$1class:career-details__input--position-lock',
            $form
        );
    }

    // [submit] renders <input> (no inner HTML). Use <button> + chevron SVG like career-details.html.
    // No has-spinner: theme uses .nsc-job-apply-loading (see nsc-job-apply-position-lock.js + _career_details.scss).
    $submitWithIcon = '<button type="submit" class="wpcf7-form-control wpcf7-submit career-details__submit">Submit application <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>';
    $legacySubmit = [
        '[submit class:career-details__submit "Submit application"]',
        "[submit class:career-details__submit 'Submit application']",
    ];
    foreach ($legacySubmit as $legacy) {
        if (strpos($form, $legacy) !== false) {
            $form = str_replace($legacy, $submitWithIcon, $form);
        }
    }

    // CV upload: label above .wpcf7-form-control-wrap (same pattern as other fields: label, then control).
    $uploadOld = '<div class="career-details__upload">[file cv_file id:career-cv class:career-details__upload-input filetypes:doc|docx|pdf limit:5242880]<label for="career-cv" class="career-details__upload-label"><span class="career-details__upload-icon" aria-hidden="true"><svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M24 8v20M16 16l8-8 8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 28v8a4 4 0 004 4h16a4 4 0 004-4v-8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span><span class="career-details__upload-text">Drop your CV here, or <strong>Browse</strong></span><span class="career-details__upload-hint">Support DOC, DOCX, PDF, max size: 5MB</span></label></div>';
    $uploadNew = '<div class="career-details__upload"><label for="career-cv" class="career-details__upload-label"><span class="career-details__upload-icon" aria-hidden="true"><svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M24 8v20M16 16l8-8 8 8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 28v8a4 4 0 004 4h16a4 4 0 004-4v-8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></span><span class="career-details__upload-text">Drop your CV here, or <strong>Browse</strong></span><span class="career-details__upload-hint">Support DOC, DOCX, PDF, max size: 5MB</span></label>[file cv_file id:career-cv class:career-details__upload-input filetypes:doc|docx|pdf limit:5242880]</div>';
    if (strpos($form, $uploadOld) !== false) {
        $form = str_replace($uploadOld, $uploadNew, $form);
    }

    // Drop CF7 has-spinner (custom loading row replaces .wpcf7-spinner).
    $form = preg_replace('/\s+has-spinner\b/', '', $form);

    return $form;
}, 5, 2);

/**
 * Position field: add lock class, never HTML readonly (value must post with the form).
 */
add_filter('wpcf7_form_tag', static function ($tag, $unused = null) {
    if (!function_exists('is_singular') || !is_singular('job')) {
        return $tag;
    }
    if (!is_object($tag) || !isset($tag->basetype, $tag->name)) {
        return $tag;
    }
    if ($tag->basetype !== 'text' || $tag->name !== 'job_position') {
        return $tag;
    }

    $cf = \WPCF7_ContactForm::get_current();
    if ($cf instanceof \WPCF7_ContactForm && function_exists('nsc_job_apply_cf7_form_id')) {
        $applyId = nsc_job_apply_cf7_form_id();
        if ($applyId > 0 && (int) $cf->id() !== $applyId) {
            return $tag;
        }
    }

    if (!is_array($tag->options)) {
        $tag->options = [];
    }
    $tag->options = array_values(array_filter($tag->options, static function ($opt) {
        return !is_string($opt) || !preg_match('/^readonly\b/i', $opt);
    }));
    if (!in_array('class:career-details__input--position-lock', $tag->options, true)) {
        $tag->options[] = 'class:career-details__input--position-lock';
    }

    return $tag;
}, 20, 2);

/**
 * Inject job title as input value after CF7 builds HTML (placeholder + value logic in text.php
 * cannot hold both title and placeholder via tag values alone).
 */
add_filter('wpcf7_form_elements', static function ($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }
    if (strpos($html, 'job_position') === false) {
        return $html;
    }

    $cf = \WPCF7_ContactForm::get_current();
    if ($cf instanceof \WPCF7_ContactForm && function_exists('nsc_job_apply_cf7_form_id')) {
        $applyId = nsc_job_apply_cf7_form_id();
        if ($applyId > 0 && (int) $cf->id() !== $applyId) {
            return $html;
        }
    }

    if (!function_exists('nsc_job_apply_current_job_post_id')) {
        return $html;
    }
    $jobId = nsc_job_apply_current_job_post_id();
    if ($jobId <= 0) {
        return $html;
    }

    $title = trim((string) get_the_title($jobId));
    if ($title === '') {
        return $html;
    }

    $v = esc_attr($title);

    $replaced = preg_replace_callback(
        '/<input\b[^>]*\bname=(["\'])job_position\1[^>]*>/i',
        static function (array $m) use ($v): string {
            $tag = $m[0];
            $tag = preg_replace('/\s+readonly(?:\s*=\s*(?:"readonly"|\'readonly\'|readonly))?/i', '', $tag);
            if (preg_match('/\svalue\s*=\s*"[^"]*"/i', $tag)) {
                $tag = preg_replace('/\svalue\s*=\s*"[^"]*"/i', ' value="' . $v . '"', $tag, 1);
            } elseif (preg_match('/\svalue\s*=\s*\'[^\']*\'/i', $tag)) {
                $tag = preg_replace('/\svalue\s*=\s*\'[^\']*\'/i', ' value="' . $v . '"', $tag, 1);
            } else {
                $tag = preg_replace('/^<input\b/i', '<input value="' . $v . '"', $tag, 1);
            }
            if (!preg_match('/\sdata-nsc-position-lock\s*=/i', $tag)) {
                if (preg_match('/\/>\s*$/', $tag)) {
                    $tag = preg_replace('/\/>\s*$/', ' data-nsc-position-lock="' . $v . '" />', $tag, 1);
                } else {
                    $tag = preg_replace('/>\s*$/', ' data-nsc-position-lock="' . $v . '">', $tag, 1);
                }
            }

            return $tag;
        },
        $html,
        1
    );

    return is_string($replaced) ? $replaced : $html;
}, 20, 1);
