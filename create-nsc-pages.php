<?php
declare(strict_types=1);

/**
 * NSC page bootstrapper.
 *
 * Usage:
 *   http://localhost/nsc/create-nsc-pages.php?token=nsc-create-pages-2026
 *   http://localhost/nsc/create-nsc-pages.php?token=nsc-create-pages-2026&home_only=1
 *
 * Notes:
 * - Runs idempotently (creates missing pages, updates existing by slug).
 * - Assigns the corresponding custom page template to each page.
 * - For the Home page: removes any existing pageComponents meta and seeds
 *   components to match frontend/src/index.html section order (Hero → Stats →
 *   Our Services → Why Us → How We Work → AI-Driven → Testimonials → Blogs →
 *   Contact Us). Home uses the default page template so it renders from components.
 * - URL fields in the seed use home_url('/') so saved data passes ACF URL validation.
 * - Add home_only=1 to only create/update the Home page and set it as front page.
 * - Add content_test=1 to prepend "[test] " to all text content so you can verify
 *   in the CMS that components load editable data while keeping live text (re-run without content_test=1 for live only).
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

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$pages = [
    ['title' => 'Home', 'slug' => 'home', 'template' => ''], // default = page.twig + pageComponents
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

/**
 * Ensure build images (logos, testimonial avatars) are in the media library; return attachment IDs.
 * Uses meta nsc_build_asset to avoid re-importing. Sideloads from theme frontend/build/img/ if missing.
 *
 * @return array{logo_ids: list<int>, testimonial_avatar_ids: list<int>}
 */
function nscGetTestimonialBuildImageIds(): array
{
    $buildUri   = get_template_directory_uri() . '/frontend/build';
    $logoFiles  = ['ACB.png', 'Petlinx.png', 'Atomworks.png', 'Greatscott.png', 'Healthcare.png', 'OCQ.png', 'visaplan.png', 'abri.png', 'anz.png', 'alawyer.png', 'hivello.png', 'commnia.png', 'STU.png', 'threat.png', 'littles.png', 'FW.png', 'monday.png', 'NUBO.png', 'salesbuildr.png', 'shft.png', 'dtails.png', 'timeblockr.png', 'Glassboxx.png', 'clowd.png', 'pl.png', 'Simpson.png', 'Timberyard.png', 'histofy.png', 'andromeda.png', 'endava.png', 'abouthealthcar.png', 'allshifts.png', 'Bruker.png'];
    $avatarFiles = ['testimonial1.png', 'testimonial2.png', 'testimonial3.png', 'testimonial4.png', 'testimonial5.png', 'testimonial6.png', 'testimonial7.png', 'testimonial8.png'];

    $getOrCreateId = static function (string $filename) use ($buildUri): int {
        $existing = get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'meta_query'     => [['key' => 'nsc_build_asset', 'value' => $filename, 'compare' => '=']],
        ]);
        if (!empty($existing)) {
            return (int) $existing[0]->ID;
        }
        $url = $buildUri . '/img/' . $filename;
        $tmp = download_url($url);
        if (is_wp_error($tmp)) {
            return 0;
        }
        $file = ['name' => $filename, 'tmp_name' => $tmp];
        $id   = media_handle_sideload($file, 0, $filename);
        if (is_file($tmp)) {
            @unlink($tmp);
        }
        if (is_wp_error($id)) {
            return 0;
        }
        update_post_meta($id, 'nsc_build_asset', $filename);
        return $id;
    };

    $logoIds   = [];
    $avatarIds = [];
    foreach ($logoFiles as $f) {
        $id = $getOrCreateId($f);
        if ($id > 0) {
            $logoIds[] = $id;
        }
    }
    foreach ($avatarFiles as $f) {
        $id = $getOrCreateId($f);
        $avatarIds[] = $id > 0 ? $id : 0;
    }
    return ['logo_ids' => $logoIds, 'testimonial_avatar_ids' => $avatarIds];
}

/**
 * Home page components matching frontend/build/index.html section order and content.
 * URL fields use home_url('/') so saved data passes ACF URL validation.
 *
 * @return array<int, array<string, mixed>>
 */
function getHomePageComponents(): array
{
    $baseUrl = home_url('/');
    $buildImages = nscGetTestimonialBuildImageIds();
    $logoIds = $buildImages['logo_ids'];
    $avatarIds = $buildImages['testimonial_avatar_ids'];

    return [
        // 1. Hero (section.hero.home) – from build index.html; heroStyle home enables wave.js
        [
            'acf_fc_layout' => 'nscBlockHero',
            'heroStyle'     => 'home',
            'headline'      => '<span class="highlight">AI-Driven</span> <br class="lg:hidden"> <sb>Software Development</sb> <br> <sb>Powered by</sb> <br class="lg:hidden"> <span class="highlight">Senior Engineers</span>',
            'description'   => '<p>Hire Vietnam\'s <b>Top 7% IT Talents</b> to <br> Deliver AI Enterprise Solutions</p>',
            'badgeImages'   => [],
            'button'        => ['label' => 'Explore', 'url' => $baseUrl, 'openInNewTab' => 0],
            'options'       => ['theme' => ''],
        ],
        // 2. Stats (section.stats)
        [
            'acf_fc_layout' => 'nscBlockStats',
            'stats'         => [
                ['number' => '200', 'suffix' => '+', 'title' => 'Senior Experts', 'subtitle' => '(100% Senior-Level)'],
                ['number' => '100', 'suffix' => '%', 'title' => 'English-Proficient Team', 'subtitle' => ''],
                ['number' => '100', 'suffix' => '+', 'title' => 'Successful Projects', 'subtitle' => ''],
                ['number' => '50', 'suffix' => '+', 'title' => "Global \n Clients", 'subtitle' => ''],
            ],
            'options'       => ['theme' => ''],
        ],
        // 3. Our Services (section.our-services) – copy from build
        [
            'acf_fc_layout'   => 'nscBlockOurServices',
            'title'           => 'Our Services',
            'introDescription' => 'NSC Software delivers AI-driven, end-to-end technology solutions that help businesses build modern digital products, optimize operations, & accelerate growth with a senior, high-quality engineering team.',
            'introButton'     => ['label' => 'Explore our services', 'url' => $baseUrl, 'openInNewTab' => 0],
            'services'        => [
                ['number' => '01', 'title' => 'Software Development & Digital Solutions', 'description' => 'Delivering full-cycle software development services - from product ideation and MVP to  enterprise-scale platforms - with a focus on scalability, performance, and long-term business value.'],
                ['number' => '02', 'title' => 'Technology Consulting & Architecture', 'description' => 'Providing strategic technology advisory and solution architecture design to help enterprises modernize systems, adopt emerging technologies, and align IT strategies with business objectives.'],
                ['number' => '03', 'title' => 'Cloud & DevOps Services', 'description' => 'Enabling agility and resilience through cloud-native engineering, infrastructure automation, and DevSecOps practices that accelerate deployment, enhance reliability, and optimize cost efficiency.'],
                ['number' => '04', 'title' => 'Data Engineering & Analytics', 'description' => 'Building secure and scalable data pipelines, lakes, and warehouses to transform raw data into actionable insights - empowering smarter decisions and data-driven innovation.'],
                ['number' => '05', 'title' => 'AI & Machine Learning', 'description' => 'Designing and deploying AI solutions - from predictive analytics and NLP to computer vision and generative AI - that enhance automation, intelligence, and business performance.'],
                ['number' => '06', 'title' => 'Blockchain & Web3 Development', 'description' => 'Developing decentralized applications and blockchain-based platforms to enable digital trust, transparency, and new models of value exchange across industries.'],
                ['number' => '07', 'title' => 'Quality Assurance & Testing Services', 'description' => 'Ensuring product quality, performance, and security through comprehensive manual and automated testing frameworks that integrate seamlessly into your delivery pipeline.'],
                ['number' => '08', 'title' => 'ERP, CRM & Business Platform Services', 'description' => 'Customizing and integrating enterprise platforms such as Odoo, Dynamics 365, Salesforce, and SAP to streamline operations, enhance workflows, and drive business growth.'],
            ],
            'options'         => ['theme' => ''],
        ],
        // 4. Why Us (section.why-us)
        [
            'acf_fc_layout' => 'nscBlockWhyUs',
            'title'         => 'Why NSC Software?',
            'items'         => [
                ['title' => 'Senior-Led, AI-Driven Expertise', 'description' => 'Projects are guided by senior engineers and enhanced by AI for higher efficiency and quality.'],
                ['title' => 'Time-Zone Aligned Collaboration', 'description' => 'Teams operate in overlapping hours with Europe, Australia and the US for real-time coordination.'],
                ['title' => 'Vietnam\'s Top 7% IT Talents', 'description' => 'Top 7% Vietnamese engineers, rigorously selected for technical excellence and problem-solving capability.'],
                ['title' => '100% English-Proficient Team', 'description' => 'Seamless communication and collaboration with clients across global regions.'],
                ['title' => '100% Senior-Level Engineers', 'description' => 'All engineers are senior professionals with a minimum of 6 years of experience.'],
                ['title' => 'High-Quality, Cost-Efficient Delivery', 'description' => 'Projects are guided by senior engineers and enhanced by AI for higher efficiency and quality.'],
                ['title' => '6+ Years Minimum Experience', 'description' => 'Depth of technical expertise and delivery maturity across complex enterprise systems.'],
            ],
            'options'       => ['theme' => ''],
        ],
        // 5. How We Work (section.how-we-work)
        [
            'acf_fc_layout' => 'nscBlockHowWeWork',
            'title'         => 'How We Work',
            'subheading'    => 'Flexible engagement models designed for:',
            'subtitle'      => 'Long-term partnership - Efficiency - Transparency',
            'items'         => [
                ['title' => "Fixed-Scope \n Projects", 'description' => 'Clear goals, fixed requirements, and defined timelines, ideal for well-scoped initiatives.'],
                ['title' => "Dedicated \n Team", 'description' => 'Build a long-term, fully managed engineering team aligned with your business objectives.'],
                ['title' => "Staff \n Augmentation", 'description' => 'Extend your in-house capacity with senior engineers working directly under your management.'],
                ['title' => "Managed \n Services", 'description' => 'Delegate selected operations to NSC under SLA-driven, KPI-measured management.'],
            ],
            'options'       => ['theme' => ''],
        ],
        // 6. AI-Driven (section.ai-driven)
        [
            'acf_fc_layout' => 'nscBlockAiDriven',
            'headline1'     => '<span>AI-Driven</span> <br> Software <br> Development',
            'headline2'     => 'Power by <br> <span>Senior <br class="hidden lg:block"> Engineers</span>',
            'description'   => '<p>NSC integrates AI across the entire delivery lifecycle to enhance productivity, consistency, and decision-making.</p><p>We combine leading AI frameworks with proprietary, privacy-controlled models to ensure secure, compliant, and efficient delivery for every client engagement.</p>',
            'button'        => ['label' => 'Learn How We Leverage AI', 'url' => $baseUrl, 'openInNewTab' => 0],
            'options'       => ['theme' => ''],
        ],
        // 7. Testimonials (section.testimonials) – logos and avatars from build images in media library
        [
            'acf_fc_layout'   => 'nscBlockTestimonials',
            'title'           => 'Beyond A Partner',
            'introDescription' => 'Empowering global enterprises with AI-driven engineering and senior-led delivery, built on trust, transparency, and long-term collaboration.',
            'introButton'     => ['label' => 'Explore Our Case Studies', 'url' => $baseUrl, 'openInNewTab' => 0],
            'logos'           => array_map(static fn ($id) => ['image' => $id], $logoIds),
            'testimonials'    => [
                [
                    'content'       => "We worked with NSC to source a senior Go developer and couldn't be happier with the results. The developer's work has been excellent, and the NSC team provided outstanding support every step of the way.",
                    'readMoreContent' => '',
                    'authorName'    => 'Nicolas Carpi',
                    'authorRole'    => 'CEO / Deltablot',
                    'image'         => $avatarIds[0] ?? 0,
                ],
                [
                    'content'       => 'We can highly recommend NSC. They have been exceptionally flexible, professional, and solution-oriented throughout our collaboration. As a startup, challenges often arise on short notice, but NSC has always responded with great understanding and a genuine willingness to help.',
                    'readMoreContent' => '<p>We can highly recommend NSC. They have been exceptionally flexible, professional, and solution-oriented throughout our collaboration. As a startup, challenges often arise on short notice, but NSC has always responded with great understanding and a genuine willingness to help.</p><p>Their proactive approach and strong sense of service make them a reliable and valued partner that we gladly recommend to others.</p><p>We can wholeheartedly recommend NSC without hesitation. Throughout our collaboration, they have proven to be highly flexible, professional, and solution-oriented. As a startup, unexpected challenges often arise at short notice, but NSC has consistently handled every situation with impressive understanding and a strong commitment to finding solutions that worked for both sides.</p><p>Their approach is characterized by excellent service, open communication, and a genuine interest in creating value for their clients. We have always felt confident working with them, and it is clear that they take pride in delivering quality and flexibility – even when things move quickly.</p><p>We can therefore warmly recommend NSC to any company looking for a reliable, dedicated, and collaborative partner</p>',
                    'authorName'    => 'Tony Motzfeldt',
                    'authorRole'    => 'CEO and Founder / SHFT',
                    'image'         => $avatarIds[1] ?? 0,
                ],
                [
                    'content'       => 'Working with the NSC team has been a great experience. Their developers are highly skilled, professional, and consistently deliver quality results on time. We truly appreciate their technical expertise and the level of support they provide, they\'ve been a reliable and capable partner throughout our collaboration.',
                    'readMoreContent' => '',
                    'authorName'    => 'Marcus Tan',
                    'authorRole'    => 'Senior Business Analyst / Ascent Lawyers',
                    'image'         => $avatarIds[2] ?? 0,
                ],
                [
                    'content'       => 'I worked with NCS for over a year, and my experience with their engineering team was consistently excellent. Their engineers demonstrated top-tier technical expertise, strong professionalism, and the ability to contribute effectively from day one.',
                    'readMoreContent' => '<p>I worked with NCS for over a year, and my experience with their engineering team was consistently excellent. Their engineers demonstrated top-tier technical expertise, strong professionalism, and the ability to contribute effectively from day one. They were also a genuine pleasure to collaborate with. I would confidently recommend their services to anyone seeking a highly capable and well-resourced team.</p>',
                    'authorName'    => 'Alex',
                    'authorRole'    => 'Solutions For Science',
                    'image'         => $avatarIds[3] ?? 0,
                ],
                [
                    'content'       => 'NCS team is a team that understands 100% what customers want. It has put us on the optimal path to the results our company wants',
                    'readMoreContent' => '',
                    'authorName'    => 'DongHee Shin',
                    'authorRole'    => 'PO / OGO Corp',
                    'image'         => $avatarIds[4] ?? 0,
                ],
                [
                    'content'       => 'We discovered NSC Software when we went searching for a more cost effective outsourcing business to develop our web application. They presented a great solution, which was completed within budget and fast! We now consider them an integral part of our development team, and have already re-engaged them on another project.',
                    'readMoreContent' => '',
                    'authorName'    => 'Laurence Smith',
                    'authorRole'    => 'CEO / Petlinx',
                    'image'         => $avatarIds[5] ?? 0,
                ],
                [
                    'content'       => 'We are very happy with the work that NSC did for us in developing our app for iOS and Android in Flutter. The collaboration was smooth and efficient, and the first version of the app was launched after just six months of collaboration. Won\'t hesitate to work with NSC again in another project!',
                    'readMoreContent' => '',
                    'authorName'    => 'Daniel Feldt',
                    'authorRole'    => 'CEO / Great Scott',
                    'image'         => $avatarIds[6] ?? 0,
                ],
                [
                    'content'       => 'My only regret was we didn\'t get to meet and work with NSC sooner, their team could have saved our company from months of wasted energy and resources working with the wrong people.',
                    'readMoreContent' => '',
                    'authorName'    => 'Eddie Huang',
                    'authorRole'    => 'CEO / Atomworks',
                    'image'         => $avatarIds[7] ?? 0,
                ],
            ],
            'options'         => ['theme' => ''],
        ],
        // 8. Blogs home (section.blogs) – content from build index.html
        [
            'acf_fc_layout'        => 'nscBlockBlogsHome',
            'title'                => 'BLOGS',
            'descriptionHeading'   => 'Ideas that inspire. Stories that shape the future.',
            'descriptionParagraph' => 'Stay updated with insights, stories, and perspectives from NSC Software, where we explore how technology, innovation, and people drive business transformation.',
            'joinConversation'     => [
                'title'       => 'Join Conversation',
                'paragraph'   => 'Follow our journey of growth, innovation, and collaboration, where every insight leads to a smarter tomorrow.',
                'buttonLabel' => 'Explore all articles on the NSC Blog',
                'buttonUrl'   => $baseUrl,
                'openInNewTab' => 0,
            ],
            'options'              => ['theme' => ''],
        ],
        // 9. Contact Us (section.contact-us)
        [
            'acf_fc_layout' => 'nscBlockContactUs',
            'title'         => 'CONTACT',
            'contentLines'  => "Ideas that inspire.\nStories that shape the future.",
            'showForm'      => 1,
            'formAction'    => $baseUrl,
            'options'       => ['theme' => ''],
        ],
    ];
}

/**
 * Recursively prepend "[test] " to all string values so you can verify the CMS loads editable data.
 * Preserves layout key, openInNewTab, showForm, and URL fields (url, formAction, buttonUrl, phoneLink).
 *
 * @param array<int|string, mixed> $components
 * @return array<int|string, mixed>
 */
function applyContentTest(array $components): array
{
    $preserveKeys = ['acf_fc_layout', 'openInNewTab', 'showForm', 'url', 'formAction', 'buttonUrl', 'phoneLink'];
    $out = [];
    foreach ($components as $k => $v) {
        if (in_array($k, $preserveKeys, true)) {
            $out[$k] = $v;
            continue;
        }
        if (is_array($v)) {
            $out[$k] = applyContentTest($v);
            continue;
        }
        if (is_string($v)) {
            $out[$k] = '[test] ' . $v;
            continue;
        }
        $out[$k] = $v;
    }
    return $out;
}

$homeOnly   = isset($_GET['home_only']) && $_GET['home_only'] === '1';
$contentTest = isset($_GET['content_test']) && $_GET['content_test'] === '1';
if ($homeOnly) {
    $pages = array_filter($pages, static function (array $p) {
        return $p['slug'] === 'home';
    });
}

$results = [];

foreach ($pages as $page) {
    $slug     = $page['slug'];
    $title    = $page['title'];
    $template = $page['template'];

    $existing = get_page_by_path($slug, OBJECT, 'page');

    if ($existing instanceof WP_Post) {
        $pageId  = (int) $existing->ID;
        wp_update_post([
            'ID'          => $pageId,
            'post_title'  => $title,
            'post_status' => 'publish',
        ]);
        $action = 'updated';
    } else {
        $pageId = wp_insert_post([
            'post_type'   => 'page',
            'post_status' => 'publish',
            'post_title'  => $title,
            'post_name'   => $slug,
            'post_content' => '',
            'post_author'  => get_current_user_id() ?: 1,
        ], true);

        if (is_wp_error($pageId)) {
            $results[] = [
                'slug'    => $slug,
                'status'  => 'error',
                'message' => $pageId->get_error_message(),
            ];
            continue;
        }

        $action = 'created';
    }

    update_post_meta((int) $pageId, '_wp_page_template', $template);

    // Home page: clear previous components and seed via ACF so layout order and sub fields are stored correctly.
    if ($slug === 'home') {
        $components = getHomePageComponents();
        if ($contentTest) {
            $components = applyContentTest($components);
        }
        if (function_exists('update_field')) {
            update_field('pageComponents', [], (int) $pageId);
            update_field('pageComponents', $components, (int) $pageId);
        } else {
            delete_post_meta((int) $pageId, 'pageComponents');
            update_post_meta((int) $pageId, 'pageComponents', $components);
        }
        $msg = 'page_id=' . $pageId . ', template=default, pageComponents cleared and set (Hero→…→Contact Us)';
        if ($contentTest) {
            $msg .= ', content_test=1 (all text set to "[test]")';
        }
        $results[] = [
            'slug'    => $slug,
            'status'  => $action,
            'message' => $msg,
        ];
    } else {
        $results[] = [
            'slug'    => $slug,
            'status'  => $action,
            'message' => 'page_id=' . $pageId . ', template=' . $template,
        ];
    }
}

// Set Home as front page (always when Home exists, so home_only runs work correctly).
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
echo '<p>Done. Home page uses default template and pageComponents matching <code>frontend/src/index.html</code>. URL fields use your site base URL. Re-run to refresh Home components. Add <code>home_only=1</code> to update only the Home page. Add <code>content_test=1</code> to prepend "[test] " to all text so you can verify the CMS loads editable data.</p>';
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
