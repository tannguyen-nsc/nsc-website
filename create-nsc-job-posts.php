<?php
declare(strict_types=1);

/**
 * Seed 30 dummy Job openings (CPT `job`) with ACF fields + taxonomies.
 * Idempotent by post slug (updates if exists).
 *
 * Prerequisites: Advanced Custom Fields (theme registers field group `nscJobFields`).
 * Taxonomies: job_category, job_employment, post_tag on `job`.
 *
 * Usage:
 *   https://yoursite.test/create-nsc-job-posts.php?token=nsc-create-job-posts-2026
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

$titles = [
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
    $existing = get_posts([
        'post_type' => 'job',
        'post_status' => 'any',
        'name' => $slug,
        'posts_per_page' => 1,
        'fields' => 'ids',
    ]);
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

    $jobDesc = nsc_job_seed_wysiwyg_paragraphs(
        sprintf('We are hiring a %s to strengthen our delivery organization.', $title),
        3
    );
    $customerContent = nsc_job_seed_wysiwyg_paragraphs(
        sprintf('%s partners with NSC on long-term product engineering. The environment is collaborative, quality-driven, and focused on customer outcomes.', $company),
        2
    );
    $projectHtml = nsc_job_seed_wysiwyg_paragraphs(
        'You will contribute to distributed systems and customer-facing applications with modern APIs, observability, and secure delivery practices.',
        2
    );

    $excerpt = wp_trim_words(
        wp_strip_all_tags($title . ' — ' . $company . ' ' . $jobDesc),
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

    $acfPayload = [
        'nsc_job_listing_date' => $listingDate,
        'nsc_job_required_skills' => nsc_job_seed_skills_for_index($i),
        'nsc_job_description' => $jobDesc,
        'nsc_job_customer_company' => $company,
        'nsc_job_customer_content' => $customerContent,
        'nsc_job_project' => $projectHtml,
        'nsc_job_key_technologies' => nsc_job_seed_tech_for_index($i),
    ];

    if (function_exists('update_field')) {
        foreach ($acfPayload as $fname => $val) {
            update_field($fname, $val, $postId);
        }
    } else {
        foreach ($acfPayload as $fname => $val) {
            update_post_meta($postId, $fname, $val);
        }
        $results[] = [
            'slug' => $slug,
            'status' => 'warning',
            'message' => 'post_id=' . $postId . ' (ACF update_field missing — meta may be incomplete)',
        ];
        continue;
    }

    $results[] = [
        'slug' => $slug,
        'status' => $postId && !empty($existing) ? 'updated' : 'created',
        'message' => 'post_id=' . $postId . ', category=' . $catKey . ', employment=' . count($empIds),
    ];
}

header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html><head><meta charset="utf-8"><title>NSC Job Posts Seed</title>';
echo '<style>body{font-family:Arial,sans-serif;padding:24px}table{border-collapse:collapse;width:100%;max-width:1100px}th,td{border:1px solid #ddd;padding:8px;font-size:13px}th{background:#f7f7f7;text-align:left}.ok{color:#0a7f2e}.error{color:#b00020}.warn{color:#a65f00}</style>';
echo '</head><body><h1>NSC Job openings</h1>';
echo '<p>Seeded or updated ' . count($results) . ' <code>job</code> posts. Fields: skills repeater, WYSIWYG blocks, customer, project, key tech, listing date. Taxonomies: job_category, job_employment, tags.</p>';
echo '<table><thead><tr><th>Slug</th><th>Status</th><th>Details</th></tr></thead><tbody>';
foreach ($results as $row) {
    $cls = $row['status'] === 'error' ? 'error' : ($row['status'] === 'warning' ? 'warn' : 'ok');
    echo '<tr><td>' . esc_html($row['slug']) . '</td><td class="' . esc_attr($cls) . '">' . esc_html($row['status']) . '</td><td>' . esc_html($row['message']) . '</td></tr>';
}
echo '</tbody></table></body></html>';
