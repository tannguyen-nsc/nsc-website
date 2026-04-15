<?php

namespace NscSoftware\Components\NSCBlockJobsArchive;

use Timber\Timber;

const JOB_POST_TYPE = 'job';
const JOB_CATEGORY_TAXONOMY = 'job_category';
const JOB_EMPLOYMENT_TAXONOMY = 'job_employment';
const VUE_JOBS_MAX = 500;

/**
 * @return array<string, string>
 */
function default_jobs_archive_labels(): array
{
    return [
        'allPositionsLabel' => __('All Positions', 'NscSoftware'),
        'allPositionsMobileLabel' => __('All', 'NscSoftware'),
        'previous' => __('Previous', 'NscSoftware'),
        'next' => __('Next', 'NscSoftware'),
        'noPositionsFound' => __('No positions found.', 'NscSoftware'),
        'loadingText' => __('Loading positions...', 'NscSoftware'),
        'errorText' => __('Could not load positions.', 'NscSoftware'),
        'applyNow' => __('Apply Now', 'NscSoftware'),
    ];
}

/**
 * @param array<string, mixed> $raw
 * @return array<string, string>
 */
function normalize_jobs_archive_labels(array $raw): array
{
    $out = default_jobs_archive_labels();
    foreach ($out as $key => $default) {
        if (!array_key_exists($key, $raw)) {
            continue;
        }
        $v = $raw[$key];
        if (is_string($v) && $v !== '') {
            $out[$key] = $v;
        }
    }

    return $out;
}

/**
 * Primary job_category slug for Vue tab filter (empty = only visible under “All”).
 */
function primary_job_category_slug(int $postId): string
{
    $terms = wp_get_post_terms($postId, JOB_CATEGORY_TAXONOMY, [
        'orderby' => 'term_id',
        'order' => 'ASC',
    ]);
    if (is_wp_error($terms) || empty($terms)) {
        return '';
    }
    $t = $terms[0];

    return $t instanceof \WP_Term ? (string) $t->slug : '';
}

/**
 * All job_employment term names, joined (jobs can have Full time + Part time, etc.).
 */
function job_employment_types_display(int $postId): string
{
    $terms = wp_get_post_terms($postId, JOB_EMPLOYMENT_TAXONOMY, [
        'orderby' => 'name',
        'order' => 'ASC',
    ]);
    if (is_wp_error($terms) || empty($terms)) {
        return __('Full time', 'NscSoftware');
    }
    $names = [];
    foreach ($terms as $t) {
        if ($t instanceof \WP_Term && $t->name !== '') {
            $names[] = (string) $t->name;
        }
    }
    $names = array_values(array_unique($names));
    if ($names === []) {
        return __('Full time', 'NscSoftware');
    }
    if (count($names) === 1) {
        return $names[0];
    }

    // translators: separator between multiple employment types (e.g. Full time, Part time).
    return implode(__(', ', 'NscSoftware'), $names);
}

function format_job_listing_date(int $postId): string
{
    $raw = function_exists('get_field') ? get_field('nsc_job_listing_date', $postId) : '';
    if (is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        $dt = \DateTime::createFromFormat('Y-m-d', $raw);

        return $dt ? $dt->format('d/m/Y') : $raw;
    }

    return (string) get_the_date('d/m/Y', $postId);
}

function job_company_name(int $postId): string
{
    $co = function_exists('get_field') ? get_field('nsc_job_customer_company', $postId) : '';
    if (is_string($co) && $co !== '') {
        return $co;
    }

    return 'NSC SOFTWARE';
}

/**
 * @return list<array{id:int,title:string,date:string,company:string,type:string,category:string,link:string}>
 */
function build_vue_job_items(): array
{
    $args = array_merge(
        [
            'post_status' => 'publish',
            'post_type' => JOB_POST_TYPE,
            'posts_per_page' => VUE_JOBS_MAX,
            'orderby' => 'date',
            'order' => 'DESC',
            'ignore_sticky_posts' => 1,
        ],
        function_exists('nsc_polylang_frontend_lang_query_args') ? \nsc_polylang_frontend_lang_query_args() : []
    );
    $collection = Timber::get_posts($args);
    $posts = is_object($collection) && method_exists($collection, 'to_array')
        ? $collection->to_array()
        : (array) $collection;

    $items = [];
    foreach ($posts as $post) {
        if (!is_object($post) || !isset($post->ID)) {
            continue;
        }
        $id = (int) $post->ID;
        $items[] = [
            'id' => $id,
            'title' => html_entity_decode((string) $post->title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'date' => format_job_listing_date($id),
            'company' => job_company_name($id),
            'type' => job_employment_types_display($id),
            'category' => primary_job_category_slug($id),
            'link' => (string) $post->link,
        ];
    }

    return $items;
}

/**
 * @param array<string, string> $labels
 * @return list<array{id:string,label:string,fullLabel:string,mobileLabel:string}>
 */
function build_vue_tabs(array $labels): array
{
    $allFull = $labels['allPositionsLabel'];
    $allMobile = $labels['allPositionsMobileLabel'];
    $tabs = [
        [
            'id' => 'all',
            'label' => $allFull,
            'fullLabel' => $allFull,
            'mobileLabel' => $allMobile,
        ],
    ];

    $terms = get_terms(array_merge(
        [
            'taxonomy' => JOB_CATEGORY_TAXONOMY,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ],
        function_exists('nsc_polylang_frontend_lang_query_args') ? \nsc_polylang_frontend_lang_query_args() : []
    ));
    if (!is_wp_error($terms)) {
        foreach ($terms as $term) {
            if (!$term instanceof \WP_Term) {
                continue;
            }
            $name = (string) $term->name;
            $tabs[] = [
                'id' => (string) $term->slug,
                'label' => $name,
                'fullLabel' => $name,
                'mobileLabel' => $name,
            ];
        }
    }

    return $tabs;
}

add_filter('NscSoftware/addComponentData?name=NSCBlockJobsArchive', function ($data) {
    $data['uuid'] = $data['uuid'] ?? wp_generate_uuid4();

    $buildUri = trailingslashit(get_template_directory_uri()) . 'frontend/build';
    $data['buildUri'] = $buildUri;

    $opts = isset($data['options']) && is_array($data['options']) ? $data['options'] : [];
    $rawPerPage = $opts['jobsPerPage'] ?? null;
    $jobsPerPage = ($rawPerPage === null || $rawPerPage === '') ? 5 : (int) $rawPerPage;
    $jobsPerPage = max(1, min(50, $jobsPerPage));

    $defaultTab = isset($opts['defaultTab']) && is_string($opts['defaultTab']) ? $opts['defaultTab'] : 'all';

    $listLabels = normalize_jobs_archive_labels(
        is_array($data['listLabels'] ?? null) ? $data['listLabels'] : []
    );

    $vueJobs = build_vue_job_items();
    $tabs = build_vue_tabs($listLabels);

    $tabIds = array_map(static function (array $t): string {
        return (string) ($t['id'] ?? '');
    }, $tabs);
    if (!in_array($defaultTab, $tabIds, true)) {
        $defaultTab = 'all';
    }

    $vuePayload = [
        'jobs' => $vueJobs,
        'tabs' => $tabs,
        'perPage' => $jobsPerPage,
        'defaultTab' => $defaultTab,
        'labels' => [
            'loading' => $listLabels['loadingText'],
            'error' => $listLabels['errorText'],
            'empty' => $listLabels['noPositionsFound'],
            'previous' => $listLabels['previous'],
            'next' => $listLabels['next'],
            'applyNow' => $listLabels['applyNow'],
        ],
    ];

    $encoded = wp_json_encode(
        $vuePayload,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
    $data['job_vue_data_json'] = $encoded !== false
        ? $encoded
        : wp_json_encode(
            [
                'jobs' => [],
                'tabs' => build_vue_tabs(default_jobs_archive_labels()),
                'perPage' => $jobsPerPage,
                'defaultTab' => 'all',
                'labels' => [
                    'empty' => __('No positions found.', 'NscSoftware'),
                ],
            ],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
        );

    return $data;
});

function getACFLayout(): array
{
    return [
        'name' => 'nscBlockJobsArchive',
        'label' => __('NSC Block: Jobs (Open positions / Archive)', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'label' => __('Heading icon', 'NscSoftware'),
                'name' => 'headingIcon',
                'type' => 'image',
                'preview_size' => 'thumbnail',
                'return_format' => 'array',
            ],
            [
                'label' => __('Section heading', 'NscSoftware'),
                'name' => 'title',
                'type' => 'text',
                'default_value' => 'OPEN POSITIONS',
            ],
            [
                'label' => __('Intro / description', 'NscSoftware'),
                'name' => 'intro',
                'type' => 'wysiwyg',
                'toolbar' => 'basic',
                'media_upload' => 0,
                'instructions' => __('Shown under the heading.', 'NscSoftware'),
            ],
            [
                'label' => __('List labels & messages', 'NscSoftware'),
                'name' => 'listLabels',
                'type' => 'group',
                'instructions' => __('Tab “All” label, pagination, empty state, loading/error, Apply button.', 'NscSoftware'),
                'sub_fields' => [
                    [
                        'label' => __('“All positions” tab (desktop)', 'NscSoftware'),
                        'name' => 'allPositionsLabel',
                        'type' => 'text',
                        'default_value' => 'All Positions',
                    ],
                    [
                        'label' => __('“All positions” tab (mobile)', 'NscSoftware'),
                        'name' => 'allPositionsMobileLabel',
                        'type' => 'text',
                        'default_value' => 'All',
                    ],
                    [
                        'label' => __('Previous (pagination)', 'NscSoftware'),
                        'name' => 'previous',
                        'type' => 'text',
                        'default_value' => 'Previous',
                    ],
                    [
                        'label' => __('Next (pagination)', 'NscSoftware'),
                        'name' => 'next',
                        'type' => 'text',
                        'default_value' => 'Next',
                    ],
                    [
                        'label' => __('No positions found', 'NscSoftware'),
                        'name' => 'noPositionsFound',
                        'type' => 'text',
                        'default_value' => 'No positions found.',
                    ],
                    [
                        'label' => __('Loading message', 'NscSoftware'),
                        'name' => 'loadingText',
                        'type' => 'text',
                        'default_value' => 'Loading positions...',
                    ],
                    [
                        'label' => __('Error message', 'NscSoftware'),
                        'name' => 'errorText',
                        'type' => 'text',
                        'default_value' => 'Could not load positions.',
                    ],
                    [
                        'label' => __('Apply now (button)', 'NscSoftware'),
                        'name' => 'applyNow',
                        'type' => 'text',
                        'default_value' => 'Apply Now',
                    ],
                ],
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
                    [
                        'label' => __('Jobs per page', 'NscSoftware'),
                        'name' => 'jobsPerPage',
                        'type' => 'number',
                        'default_value' => 5,
                        'min' => 1,
                        'max' => 50,
                        'step' => 1,
                    ],
                    [
                        'label' => __('Default tab', 'NscSoftware'),
                        'name' => 'defaultTab',
                        'type' => 'select',
                        'choices' => [
                            'all' => __('All positions', 'NscSoftware'),
                            'engineering' => __('Engineering', 'NscSoftware'),
                            'management' => __('Management', 'NscSoftware'),
                            'business' => __('Business', 'NscSoftware'),
                        ],
                        'default_value' => 'all',
                    ],
                    \NscSoftware\FieldVariables\getHiddenJobsArchive(),
                ],
            ],
        ],
    ];
}
