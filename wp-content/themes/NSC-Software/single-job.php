<?php

declare(strict_types=1);

use NscSoftware\Utils\Options;
use Timber\Timber;

$context = Timber::context();
$post    = Timber::get_post();

if (!$post || $post->post_type !== 'job') {
    Timber::render('404.twig', $context);

    return;
}

$postId = (int) ($post->ID ?? 0);

$context['post']       = $post;
$context['home_url']   = home_url('/');
$context['job_single'] = nsc_job_single_merge_why_us_items(Options::getTranslatable('NSCJobSingle') ?: []);

$careerPage = get_page_by_path('career', OBJECT, 'page');
$context['career_archive_url'] = $careerPage instanceof WP_Post
    ? get_permalink($careerPage)
    : home_url('/career/');

$context['job_tags'] = get_the_tags($postId) ?: [];

$skillsRaw = function_exists('get_field') ? get_field('nsc_job_required_skills', $postId) : null;
$jobSkills = [];
if (is_array($skillsRaw)) {
    foreach ($skillsRaw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $title = isset($row['skill_title']) ? trim((string) $row['skill_title']) : '';
        if ($title === '') {
            continue;
        }
        $total = isset($row['skill_total_points']) ? (int) $row['skill_total_points'] : 4;
        $total = max(1, min(12, $total));
        $filled = isset($row['skill_expected_points']) ? (int) $row['skill_expected_points'] : $total;
        $filled = max(0, min($filled, $total));
        $jobSkills[] = [
            'title'  => $title,
            'total'  => $total,
            'filled' => $filled,
        ];
    }
}
$context['job_skills'] = $jobSkills;

$context['job_description'] = function_exists('get_field')
    ? (string) (get_field('nsc_job_description', $postId) ?: '')
    : '';
$context['job_customer_content'] = function_exists('get_field')
    ? (string) (get_field('nsc_job_customer_content', $postId) ?: '')
    : '';
$context['job_project'] = function_exists('get_field')
    ? (string) (get_field('nsc_job_project', $postId) ?: '')
    : '';

$keyTech = function_exists('get_field') ? get_field('nsc_job_key_technologies', $postId) : null;
$keyNames = [];
if (is_array($keyTech)) {
    foreach ($keyTech as $row) {
        if (!is_array($row) || empty($row['technology_name'])) {
            continue;
        }
        $keyNames[] = trim((string) $row['technology_name']);
    }
}
$context['job_key_technology_names'] = array_values(array_filter(array_unique($keyNames)));

$opts = $context['job_single'];
$shortcode = isset($opts['jobApplyCf7Shortcode']) ? trim((string) $opts['jobApplyCf7Shortcode']) : '';
if ($shortcode === '' && function_exists('do_shortcode')) {
    $applyId = (int) get_option('nsc_cf7_job_apply_form_id', 0);
    if ($applyId > 0) {
        $cf7Post = get_post($applyId);
        if ($cf7Post instanceof WP_Post
            && $cf7Post->post_type === 'wpcf7_contact_form'
            && $cf7Post->post_status === 'publish'
        ) {
            $shortcode = sprintf('[contact-form-7 id="%d"]', $applyId);
        }
    }
}
if ($shortcode !== '' && function_exists('do_shortcode')) {
    $context['apply_form_html'] = do_shortcode($shortcode);
} else {
    $context['apply_form_html'] = '';
}

$privacyId = (int) get_option('wp_page_for_privacy_policy');
$context['privacy_policy_url'] = '';
if (!empty($opts['jobApplyPrivacyPolicyUrl']) && is_string($opts['jobApplyPrivacyPolicyUrl'])) {
    $context['privacy_policy_url'] = trim($opts['jobApplyPrivacyPolicyUrl']);
}
if ($context['privacy_policy_url'] === '' && $privacyId > 0) {
    $context['privacy_policy_url'] = (string) get_permalink($privacyId);
}

Timber::render('templates/single-job.twig', $context);
