<?php
declare(strict_types=1);

/**
 * Seed 1 dummy Job opening (CPT `job`) with ACF fields + taxonomies.
 * Idempotent by post slug (updates if exists).
 *
 * Prerequisites: Advanced Custom Fields (theme registers field group `nscJobFields`).
 * Taxonomies: job_category, job_employment, post_tag on `job`.
 *
 * Usage:
 *   https://yoursite.test/create-nsc-job-posts.php?token=nsc-create-job-posts-2026
 *   Optional count={n} to control number of seeded jobs (1..100, default: 1).
 *   Optional with_skills=1|0 to include/exclude Required skills (default: 1).
 *   Optional seed_lang={slug}|all for Polylang-linked jobs. Omit seed_lang for default-language only; seed_lang=all for every non-default language. (lang) lowercase prefix without Google API key; legacy [LANG] stripped when re-seeding.
 */

$requiredToken = 'nsc-create-job-posts-2026';
$providedToken = isset($_GET['token']) ? (string) $_GET['token'] : '';

if ($providedToken !== $requiredToken) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden.\nUse: ?token={$requiredToken}\n";
    exit;
}

$wpLoadPath = __DIR__ . '/wp-load.php';
if (!file_exists($wpLoadPath)) {
    http_response_code(500);
    echo "wp-load.php not found.\n";
    exit;
}

require_once $wpLoadPath;

$nscSeedPolylang = get_template_directory() . '/inc/nscSeedPolylang.php';
if (is_readable($nscSeedPolylang)) {
    require_once $nscSeedPolylang;
}
if (function_exists('nsc_seed_bootstrap_acf_polylang_default_language')) {
    nsc_seed_bootstrap_acf_polylang_default_language();
}

/**
 * @return array{management: int, engineering: int, business: int}
 */
function nsc_job_seed_ensure_categories(): array
{
    $map = [
        'management' => 'Management',
        'engineering' => 'Engineering',
        'business' => 'Business',
    ];
    $ids = ['management' => 0, 'engineering' => 0, 'business' => 0];
    foreach ($map as $slug => $name) {
        $term = get_term_by('slug', $slug, 'job_category');
        if ($term instanceof WP_Term) {
            $ids[$slug] = (int) $term->term_id;
            continue;
        }
        $r = wp_insert_term($name, 'job_category', ['slug' => $slug]);
        if (!is_wp_error($r)) {
            $ids[$slug] = (int) $r['term_id'];
        }
    }

    return $ids;
}

/**
 * @return array{full_time: int, part_time: int}
 */
function nsc_job_seed_ensure_employment(): array
{
    $map = [
        'full-time' => 'Full time',
        'part-time' => 'Part time',
    ];
    $ids = ['full-time' => 0, 'part-time' => 0];
    foreach ($map as $slug => $name) {
        $term = get_term_by('slug', $slug, 'job_employment');
        if ($term instanceof WP_Term) {
            $ids[$slug] = (int) $term->term_id;
            continue;
        }
        $r = wp_insert_term($name, 'job_employment', ['slug' => $slug]);
        if (!is_wp_error($r)) {
            $ids[$slug] = (int) $r['term_id'];
        }
    }

    return $ids;
}

/**
 * @return list<string>
 */
function nsc_job_seed_tag_pool(): array
{
    return [
        'Remote', 'Hybrid', 'Europe', 'APAC', 'React', 'TypeScript', 'Go', 'Python',
        'Java', 'Kubernetes', 'AWS', 'Azure', 'PostgreSQL', 'Docker', 'FinTech',
        'Automotive', 'B2B', 'SaaS', 'Security', 'Agile', 'English', 'Senior',
    ];
}

/**
 * @return list<string>
 */
function nsc_job_seed_tags_for_index(int $index): array
{
    $pool = nsc_job_seed_tag_pool();
    $n = count($pool);
    $want = 2 + ($index % 3);
    $out = [];
    $start = ($index * 7) % $n;
    for ($t = 0; $t < $want; $t++) {
        $out[] = $pool[($start + $t * 5) % $n];
    }

    return array_values(array_unique($out));
}

function nsc_job_seed_wysiwyg_paragraphs(string $lead, int $paragraphs = 2): string
{
    $parts = ['<p>' . esc_html($lead) . '</p>'];
    $lorem = [
        'You will collaborate with product and engineering peers in a mature agile process with clear ownership and measurable outcomes.',
        'We value transparency, code quality, and sustainable delivery over heroics.',
        'The role suits someone who enjoys end-to-end ownership—from design discussions to production operations.',
    ];
    for ($i = 0; $i < $paragraphs; $i++) {
        $parts[] = '<p>' . esc_html($lorem[$i % count($lorem)]) . '</p>';
    }

    return implode("\n", $parts);
}

/**
 * @return list<array{skill_title: string, skill_expected_points: int, skill_total_points: int, skill_description: string}>
 */
function nsc_job_seed_skills_for_index(int $index): array
{
    $sets = [
        [
            ['React', 4, 4, '<p>Expert-level UI engineering with performance and accessibility in mind.</p>'],
            ['TypeScript', 4, 4, '<p>Strong typing across front-end and shared packages.</p>'],
            ['Node.js', 3, 4, '<p>APIs and services integration.</p>'],
        ],
        [
            ['Go', 4, 4, '<p>Concurrent services and cloud-native patterns.</p>'],
            ['PostgreSQL', 3, 4, '<p>Schema design and query tuning.</p>'],
            ['Docker', 3, 4, '<p>Containerized workloads.</p>'],
        ],
        [
            ['Python', 4, 4, '<p>Data pipelines and automation.</p>'],
            ['AWS', 3, 4, '<p>Core services and IaC familiarity.</p>'],
            ['Kubernetes', 2, 4, '<p>Basic operations and deployments.</p>'],
        ],
    ];
    $set = $sets[$index % count($sets)];

    return array_map(static function (array $row): array {
        return [
            'skill_title' => $row[0],
            'skill_expected_points' => $row[1],
            'skill_total_points' => $row[2],
            'skill_description' => $row[3],
        ];
    }, $set);
}

/**
 * @return list<array{technology_name: string}>
 */
function nsc_job_seed_tech_for_index(int $index): array
{
    $pools = [
        ['React', 'TypeScript', 'GraphQL', 'Jest'],
        ['Go', 'gRPC', 'PostgreSQL', 'Docker'],
        ['Java', 'Spring', 'Kafka', 'Redis'],
        ['Python', 'FastAPI', 'Celery', 'MongoDB'],
        ['.NET', 'Azure', 'SQL Server', 'RabbitMQ'],
    ];
    $pool = $pools[$index % count($pools)];

    return array_map(static fn (string $t): array => ['technology_name' => $t], $pool);
}

$titlePool = [
    'Senior Full Stack Engineer',
    'Principal Backend Engineer (Go)',
    'Staff Software Engineer — Platform',
    'Senior React / TypeScript Developer',
    'Lead Java Engineer',
    'Python Engineer — Data Services',
    'DevOps Engineer (Kubernetes)',
    'Site Reliability Engineer',
    'Cloud Solutions Architect',
    'Engineering Manager — Delivery',
    'Technical Product Manager',
    'Senior QA Automation Engineer',
    'Security Engineer (AppSec)',
    'Mobile Engineer (React Native)',
    '.NET Senior Developer',
    'Frontend Engineer — Design Systems',
    'Backend Engineer — Payments',
    'Data Engineer',
    'ML Engineer',
    'Solutions Consultant',
    'Business Analyst — Technology',
    'Scrum Master',
    'IT Project Manager',
    'Customer Success Manager',
    'Partnership Manager',
    'Operations Manager',
    'HR Business Partner',
    'Finance Systems Analyst',
    'Marketing Technology Specialist',
    'Pre-Sales Engineer',
];
$requestedCount = isset($_GET['count']) ? (int) $_GET['count'] : 1;
$requestedCount = max(1, min(100, $requestedCount));
$withSkillsRaw = isset($_GET['with_skills']) ? strtolower((string) $_GET['with_skills']) : '1';
$withSkills = !in_array($withSkillsRaw, ['0', 'false', 'no', 'off'], true);
$titles = array_slice($titlePool, 0, min($requestedCount, count($titlePool)));

$companies = [
    'Global Automotive Tech', 'FinScale Payments', 'HealthCloud Systems', 'RetailNext Analytics',
    'LogiTrack EU', 'SmartGrid Energy', 'EduLearn Platform', 'InsureTech Partners',
];

$categories = nsc_job_seed_ensure_categories();
$employment = nsc_job_seed_ensure_employment();

$catKeys = array_keys(array_filter($categories));
$results = [];
$i = 0;

foreach ($titles as $title) {
    $i++;
    $slug = sanitize_title($title) . '-' . $i;
    $singleTarget = function_exists('nsc_seed_is_single_target_language_run') && nsc_seed_is_single_target_language_run();
    $langArgs     = function_exists('nsc_seed_polylang_get_explicit_lang_query_args') ? nsc_seed_polylang_get_explicit_lang_query_args() : [];
    $canonicalId  = 0;
    if ($singleTarget && function_exists('nsc_seed_get_canonical_post_by_type_and_slug')) {
        $cPost = nsc_seed_get_canonical_post_by_type_and_slug('job', $slug, true);
        if ($cPost instanceof WP_Post) {
            $canonicalId = (int) $cPost->ID;
        }
        if ($canonicalId <= 0) {
            $results[] = ['slug' => $slug, 'status' => 'skipped', 'message' => 'No default-language job; run without seed_lang first.'];
            continue;
        }
    }

    $existing = get_posts(array_merge([
        'post_type' => 'job',
        'post_status' => 'any',
        'name' => $slug,
        'posts_per_page' => 1,
        'fields' => 'ids',
    ], $langArgs));
    $postId = !empty($existing) ? (int) $existing[0] : 0;

    $catKey = $catKeys[($i - 1) % count($catKeys)];
    $catTermId = $categories[$catKey] ?? 0;

    $empIds = [];
    if (($i % 3) !== 0 && !empty($employment['full-time'])) {
        $empIds[] = $employment['full-time'];
    }
    if (($i % 4) === 0 && !empty($employment['part-time'])) {
        $empIds[] = $employment['part-time'];
    }
    if ($empIds === [] && !empty($employment['full-time'])) {
        $empIds[] = $employment['full-time'];
    }

    $company = $companies[($i - 1) % count($companies)];
    $listingDate = gmdate('Y-m-d', strtotime(sprintf('-%d days', ($i * 11) % 120)));

    $overviewContent = nsc_job_seed_wysiwyg_paragraphs(
        sprintf('We are hiring a %s to strengthen our delivery organization.', $title),
        2
    );
    $responsibilityContent = nsc_job_seed_wysiwyg_paragraphs(
        'You will own features end-to-end, collaborate closely with product/design, and continuously improve code quality, reliability, and developer experience.',
        2
    );
    $excerpt = wp_trim_words(
        wp_strip_all_tags($title . ' — ' . $company . ' ' . $overviewContent),
        36,
        '…'
    );

    $postarr = [
        'post_title' => $title,
        'post_name' => $slug,
        'post_content' => '',
        'post_excerpt' => $excerpt,
        'post_status' => 'publish',
        'post_type' => 'job',
        'post_author' => get_current_user_id() ?: 1,
    ];

    $acfPayload = [
        'nsc_job_listing_date' => $listingDate,
        'nsc_job_customer_company' => $company,
        'nsc_job_required_skills' => $withSkills ? nsc_job_seed_skills_for_index($i) : [],
        'nsc_job_content_blocks' => [
            [
                'block_title' => 'Overview',
                'block_content' => $overviewContent,
            ],
            [
                'block_title' => 'Responsibilities',
                'block_content' => $responsibilityContent,
            ],
        ],
        'nsc_job_key_technologies' => nsc_job_seed_tech_for_index($i),
    ];

    if (!$singleTarget) {
        if ($postId > 0) {
            $postarr['ID'] = $postId;
            $r = wp_update_post($postarr, true);
        } else {
            $r = wp_insert_post($postarr, true);
        }

        if (is_wp_error($r)) {
            $results[] = ['slug' => $slug, 'status' => 'error', 'message' => $r->get_error_message()];
            continue;
        }

        $postId = (int) $r;

        if ($catTermId > 0) {
            wp_set_object_terms($postId, [$catTermId], 'job_category', false);
        }
        if ($empIds !== []) {
            wp_set_object_terms($postId, $empIds, 'job_employment', false);
        }
        wp_set_post_tags($postId, nsc_job_seed_tags_for_index($i), false);
        update_post_meta($postId, 'is_seeded', '1');

        $acfNote = '';
        if (function_exists('update_field')) {
            foreach ($acfPayload as $fname => $val) {
                update_field($fname, $val, $postId);
            }
        } else {
            foreach ($acfPayload as $fname => $val) {
                update_post_meta($postId, $fname, $val);
            }
            $acfNote = ' ACF update_field missing — saved as post_meta.';
        }
    } else {
        $acfNote = '';
    }

    $syncSourceId = ($singleTarget && $canonicalId > 0) ? $canonicalId : $postId;
    if (function_exists('nsc_seed_should_run_translation_sync') && nsc_seed_should_run_translation_sync() && function_exists('nsc_seed_polylang_sync_post_with_taxonomies')) {
        nsc_seed_polylang_sync_post_with_taxonomies(
            $syncSourceId,
            'job',
            $title,
            $slug,
            '',
            $excerpt,
            $acfPayload,
            ['job_category', 'job_employment', 'post_tag']
        );
    }

    $reportId = $postId;
    if ($singleTarget && $canonicalId > 0 && function_exists('nsc_seed_polylang_sync_target_slugs_for_request')) {
        $t0 = nsc_seed_polylang_sync_target_slugs_for_request()[0] ?? '';
        if ($t0 !== '' && function_exists('pll_get_post')) {
            $tp = (int) pll_get_post($canonicalId, $t0);
            if ($tp > 0) {
                $reportId = $tp;
                update_post_meta($reportId, 'is_seeded', '1');
            }
        }
    }

    $rowStatus = $singleTarget
        ? 'translation-updated'
        : ($postId && !empty($existing) ? 'updated' : 'created');
    $results[] = [
        'slug' => $slug,
        'status' => $rowStatus,
        'message' => 'post_id=' . $reportId . ', category=' . $catKey . ', employment=' . count($empIds) . $acfNote,
    ];
}

header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html><head><meta charset="utf-8"><title>NSC Job Posts Seed</title>';
echo '<style>body{font-family:Arial,sans-serif;padding:24px}table{border-collapse:collapse;width:100%;max-width:1100px}th,td{border:1px solid #ddd;padding:8px;font-size:13px}th{background:#f7f7f7;text-align:left}.ok{color:#0a7f2e}.error{color:#b00020}</style>';
echo '</head><body><h1>NSC Job openings</h1>';
echo '<p>Seeded or updated ' . count($results) . ' <code>job</code> post(s) (requested: ' . (int) $requestedCount . ', required skills: ' . ($withSkills ? 'on' : 'off') . '). Fields: listing date, customer company, optional skills repeater, content blocks (title + WYSIWYG), key tech. Taxonomies: job_category, job_employment, tags. Optional <code>count</code>, <code>with_skills</code>, and <code>seed_lang</code> / <code>seed_lang=all</code> for Polylang copies (omit for default language only).</p>';
echo '<table><thead><tr><th>Slug</th><th>Status</th><th>Details</th></tr></thead><tbody>';
foreach ($results as $row) {
    $cls = $row['status'] === 'error' ? 'error' : 'ok';
    echo '<tr><td>' . esc_html($row['slug']) . '</td><td class="' . esc_attr($cls) . '">' . esc_html($row['status']) . '</td><td>' . esc_html($row['message']) . '</td></tr>';
}
echo '</tbody></table></body></html>';
