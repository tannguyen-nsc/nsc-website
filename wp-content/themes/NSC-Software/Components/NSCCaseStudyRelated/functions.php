<?php

namespace NscSoftware\Components\NSCCaseStudyRelated;

use NscSoftware\FieldVariables;
use Timber\Timber;

function getACFLayout(): array
{
    return [
        'name' => 'nscCaseStudyRelated',
        'label' => __('NSC Case Study: Other case studies', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'label' => __('Heading', 'NscSoftware'),
                'name' => 'heading',
                'type' => 'text',
                'default_value' => __('Other case studies', 'NscSoftware'),
            ],
            [
                'label' => __('CTA label', 'NscSoftware'),
                'name' => 'ctaLabel',
                'type' => 'text',
                'default_value' => __('View other case studies', 'NscSoftware'),
            ],
            [
                'label' => __('Related items count', 'NscSoftware'),
                'name' => 'relatedCount',
                'type' => 'number',
                'default_value' => 3,
                'min' => 1,
                'max' => 12,
            ],
            [
                'label' => __('Options', 'NscSoftware'),
                'name' => 'optionsTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'label' => '',
                'name' => 'options',
                'type' => 'group',
                'layout' => 'row',
                'sub_fields' => [
                    FieldVariables\getHidden(),
                ],
            ],
        ],
    ];
}

\add_filter('NscSoftware/addComponentData?name=NSCCaseStudyRelated', static function ($data) {
    if (!is_array($data)) {
        $data = [];
    }

    $currentId = (int) \get_the_ID();
    $countRaw = isset($data['relatedCount']) ? (int) $data['relatedCount'] : 3;
    $count = max(1, min(12, $countRaw));

    $data['related_case_studies'] = Timber::get_posts([
        'post_type' => 'case_study',
        'post__not_in' => $currentId > 0 ? [$currentId] : [],
        'posts_per_page' => $count,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);

    if (\function_exists('nsc_resolve_page_permalink')) {
        $data['case_studies_archive_url'] = nsc_resolve_page_permalink('case-studies');
    } else {
        $caseStudiesPage = \get_page_by_path('case-studies', OBJECT, 'page');
        $data['case_studies_archive_url'] = $caseStudiesPage instanceof \WP_Post
            ? \get_permalink($caseStudiesPage)
            : \home_url('/case-studies/');
    }

    if (empty($data['heading'])) {
        $data['heading'] = \function_exists('nsc_pll_theme') ? nsc_pll_theme('Other case studies') : __('Other case studies', 'NscSoftware');
    }
    if (empty($data['ctaLabel'])) {
        $data['ctaLabel'] = \function_exists('nsc_pll_theme') ? nsc_pll_theme('View other case studies') : __('View other case studies', 'NscSoftware');
    }

    return $data;
});
