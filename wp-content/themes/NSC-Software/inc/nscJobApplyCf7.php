<?php

declare(strict_types=1);

/**
 * Job apply CF7: prefill Position field on singular job posts (locked via JS/CSS, not readonly attr).
 *
 * Form field name must be `job_position` (see create-nsc-cf7-form.php apply form).
 */

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
    $submitWithIcon = '<button type="submit" class="wpcf7-form-control wpcf7-submit has-spinner career-details__submit">Submit application <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>';
    $legacySubmit = [
        '[submit class:career-details__submit "Submit application"]',
        "[submit class:career-details__submit 'Submit application']",
    ];
    foreach ($legacySubmit as $legacy) {
        if (strpos($form, $legacy) !== false) {
            $form = str_replace($legacy, $submitWithIcon, $form);
        }
    }

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
    if (!function_exists('is_singular') || !is_singular('job')) {
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

    $jobId = (int) get_queried_object_id();
    if ($jobId <= 0 || get_post_type($jobId) !== 'job' || get_post_status($jobId) !== 'publish') {
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
