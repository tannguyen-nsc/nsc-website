<?php

declare(strict_types=1);

use Timber\Timber;

$context = Timber::context();
$post    = Timber::get_post();

if (!$post || $post->post_type !== 'case_study') {
    Timber::render('404.twig', $context);

    return;
}

$postId = (int) ($post->ID ?? 0);

$components = function_exists('get_field') ? get_field('caseStudyComponents', $postId) : null;
if (!is_array($components)) {
    $components = [];
}
$context['case_study_components'] = $components;

$context['post'] = $post;
$context['home_url'] = home_url('/');

if (function_exists('nsc_resolve_page_permalink')) {
    $context['case_studies_archive_url'] = nsc_resolve_page_permalink('case-studies');
} else {
    $caseStudiesPage = get_page_by_path('case-studies', OBJECT, 'page');
    $context['case_studies_archive_url'] = $caseStudiesPage instanceof WP_Post
        ? get_permalink($caseStudiesPage)
        : home_url('/case-studies/');
}

$context['related_heading'] = function_exists('nsc_pll_theme') ? nsc_pll_theme('Other case studies') : __('Other case studies', 'NscSoftware');
$context['related_cta_label'] = function_exists('nsc_pll_theme') ? nsc_pll_theme('View other case studies') : __('View other case studies', 'NscSoftware');

$related = Timber::get_posts([
    'post_type'      => 'case_study',
    'post__not_in'   => $postId > 0 ? [$postId] : [],
    'posts_per_page' => 3,
    'orderby'        => 'date',
    'order'          => 'DESC',
]);
$context['related_case_studies'] = $related;

Timber::render('templates/single-case-study.twig', $context);
