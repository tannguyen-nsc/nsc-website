<?php
declare(strict_types=1);

/**
 * NSC page bootstrapper.
 *
 * Usage:
 *   http://localhost/nsc/create-nsc-pages.php?token=nsc-create-pages-2026
 *
 * Notes:
 * - Runs idempotently (creates missing pages, updates existing by slug).
 * - Assigns the corresponding custom page template to each page.
 */

$requiredToken = 'nsc-create-pages-2026';
$providedToken = isset($_GET['token']) ? (string) $_GET['token'] : '';

if ($providedToken !== $requiredToken) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden.\n";
    echo "Use: ?token={$requiredToken}\n";
    exit;
}

$wpLoadPath = __DIR__ . '/wp-load.php';
if (!file_exists($wpLoadPath)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "wp-load.php not found at: {$wpLoadPath}\n";
    exit;
}

require_once $wpLoadPath;

if (!function_exists('wp_insert_post')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "WordPress bootstrap failed.\n";
    exit;
}

$pages = [
    ['title' => 'Home', 'slug' => 'home', 'template' => 'template-home-index.php'],
    ['title' => 'About', 'slug' => 'about', 'template' => 'template-about.php'],
    ['title' => 'AI', 'slug' => 'ai', 'template' => 'template-ai.php'],
    ['title' => 'Blogs', 'slug' => 'blogs', 'template' => 'template-blogs.php'],
    ['title' => 'Career', 'slug' => 'career', 'template' => 'template-career.php'],
    ['title' => 'Case Studies', 'slug' => 'case-studies', 'template' => 'template-case-studies.php'],
    ['title' => 'Contact', 'slug' => 'contact', 'template' => 'template-contact.php'],
    ['title' => 'Our Capabilites', 'slug' => 'our-capabilites', 'template' => 'template-our-capabilites.php'],
    ['title' => 'Our Services', 'slug' => 'our-services', 'template' => 'template-our-services.php'],
    ['title' => 'Master', 'slug' => 'master', 'template' => 'template-master.php'],
    ['title' => 'Test', 'slug' => 'test', 'template' => 'template-test.php'],
];

$results = [];

foreach ($pages as $page) {
    $slug = $page['slug'];
    $title = $page['title'];
    $template = $page['template'];

    $existing = get_page_by_path($slug, OBJECT, 'page');

    if ($existing instanceof WP_Post) {
        $pageId = (int) $existing->ID;
        wp_update_post([
            'ID' => $pageId,
            'post_title' => $title,
            'post_status' => 'publish',
        ]);
        $action = 'updated';
    } else {
        $pageId = wp_insert_post([
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_title' => $title,
            'post_name' => $slug,
            'post_content' => '',
            'post_author' => get_current_user_id() ?: 1,
        ], true);

        if (is_wp_error($pageId)) {
            $results[] = [
                'slug' => $slug,
                'status' => 'error',
                'message' => $pageId->get_error_message(),
            ];
            continue;
        }

        $action = 'created';
    }

    update_post_meta((int) $pageId, '_wp_page_template', $template);

    $results[] = [
        'slug' => $slug,
        'status' => $action,
        'message' => "page_id={$pageId}, template={$template}",
    ];
}

// Optional: set Home as front page.
$homePage = get_page_by_path('home', OBJECT, 'page');
if ($homePage instanceof WP_Post) {
    update_option('show_on_front', 'page');
    update_option('page_on_front', (int) $homePage->ID);
}

header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html><head><meta charset="utf-8"><title>NSC Page Setup</title>';
echo '<style>body{font-family:Arial,sans-serif;padding:24px}table{border-collapse:collapse;width:100%;max-width:900px}th,td{border:1px solid #ddd;padding:8px}th{background:#f7f7f7;text-align:left}.ok{color:#0a7f2e}.error{color:#b00020}</style>';
echo '</head><body>';
echo '<h1>NSC Pages Setup</h1>';
echo '<p>Done. You can re-run safely; it updates existing pages by slug.</p>';
echo '<table><thead><tr><th>Slug</th><th>Status</th><th>Details</th></tr></thead><tbody>';
foreach ($results as $row) {
    $statusClass = $row['status'] === 'error' ? 'error' : 'ok';
    echo '<tr>';
    echo '<td>' . esc_html($row['slug']) . '</td>';
    echo '<td class="' . esc_attr($statusClass) . '">' . esc_html($row['status']) . '</td>';
    echo '<td>' . esc_html($row['message']) . '</td>';
    echo '</tr>';
}

echo '</tbody></table>';
echo '</body></html>';
