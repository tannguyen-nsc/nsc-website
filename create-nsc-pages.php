<?php
declare(strict_types=1);

/**
 * NSC page bootstrapper.
 *
 * Usage:
 *   http://localhost/nsc/create-nsc-pages.php?token=nsc-create-pages-2026
 *   http://localhost/nsc/create-nsc-pages.php?token=nsc-create-pages-2026&home_only=1
 *   http://localhost/nsc/create-nsc-pages.php?token=nsc-create-pages-2026&blogs_only=1
 *   http://localhost/nsc/create-nsc-pages.php?token=nsc-create-pages-2026&career_only=1
 *   http://localhost/nsc/create-nsc-pages.php?token=nsc-create-pages-2026&policies_only=1
 *   http://localhost/nsc/create-nsc-pages.php?token=nsc-create-pages-2026&case_studies_only=1
 *   http://localhost/nsc/create-nsc-pages.php?token=nsc-create-pages-2026&page_scope=home
 *   http://localhost/nsc/create-nsc-pages.php?token=nsc-create-pages-2026&page_scope=policies
 *
 * Notes:
 * - Runs idempotently (creates missing pages, updates existing by slug).
 * - Assigns the corresponding custom page template to each page.
 * - For the Home page: removes any existing pageComponents meta and seeds
 *   components to match frontend/src/index.html section order (Hero → Stats →
 *   Our Services → Why Us → How We Work → AI-Driven → Testimonials → Blogs →
 *   Contact Us). Home uses the default page template so it renders from components.
 * - For the Blogs page: default template + pageComponents = Hero (dark, blogs copy with links to
 *   Home / About / AI / Our Services / Technology Capabilities) + NSC Block: Blogs (Archive) with Vue list fed from WP posts.
 * - For the About page: seeds pageComponents to match frontend/build/about.html
 *   (Hero left-text → Company Snapshot → Our Story → Our Leaders → Why Us →
 *   Technology Capabilities → Global Presence → Contact). About uses the default page template.
 * - URL fields in the seed use home_url('/') so saved data passes ACF URL validation.
 * - Add home_only=1 to only create/update the Home page and set it as front page.
 * - Add blogs_only=1 to only create/update the Blogs page (default template + Hero + Blogs Archive components).
 * - Add career_only=1 to only create/update the Career page (default template + Hero + We are NSC + Core values + Jobs archive / Vue).
 * - Add policies_only=1 to only create/update Privacy Policy, Cookies Policy, and Terms of Use pages.
 * - Add case_studies_only=1 to only create/update the Case Studies page (default template + Hero + Case studies archive / Vue from CPT case_study).
 * - Add content_test=1 to prepend "[test] " to text (policy page titles only; policy HTML body preserved) so you can verify in the CMS.
 * - Optional seed_lang={slug}|all for Polylang-linked pages. Omit seed_lang for default-language only; seed_lang=all for every non-default language; seed_lang={non-default} updates that locale only from canonical content—seeder ignores admin-bar/cookie language so page IDs match the intended locale. (lang) lowercase prefix without Google API key; legacy [LANG] stripped when re-seeding.
 * - Privacy Policy, Cookies Policy, Terms of Use use default template and one NSC Block: Policy Page; content from policy-content/*.html. Each section intro uses one paragraph: <p>Heading text <br> body text</p> (legacy pairs of <p><strong>Heading</strong></p><p>body</p> are normalized when loading for live + Polylang translation sync).
 * - Footer policy links (Privacy Policy, Cookies Policy, Terms of Use) are set in runGlobalOptions.php (legalLinks).
 * - Blog seed (30 posts, categories, ACF sidebar): use create-nsc-blog-posts.php with token nsc-create-blog-posts-2026.
 * - Case studies seed (30 case_study posts, taxonomies, ACF gallery + size/duration): use create-nsc-case-study-posts.php with token nsc-create-case-studies-2026.
 * - Case Studies page uses default template + pageComponents (Case studies hero + Case studies archive); Vue list reads published case_study posts (run case_studies_only=1 after CPT seed).
 * - Nav menus (header + footer sitemap): use create-nsc-menus.php?token=nsc-create-menus-2026 (same seed_lang / rebuild options as here). Or runGlobalOptions also calls the shared menu seeder.
 * - page_scope={slug}|policies: update only that page (or all policy pages when "policies"). Omit or page_scope=all for full run (legacy home_only / blogs_only / … still work when page_scope is omitted).
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

$nscSeedPolylang = get_template_directory() . '/inc/nscSeedPolylang.php';
if (is_readable($nscSeedPolylang)) {
    require_once $nscSeedPolylang;
}
if (function_exists('nsc_seed_bootstrap_acf_polylang_default_language')) {
    nsc_seed_bootstrap_acf_polylang_default_language();
}

$pages = [
    ['title' => 'Home', 'slug' => 'home', 'template' => ''], // default = page.twig + pageComponents
    ['title' => 'About', 'slug' => 'about', 'template' => ''], // default = page.twig + pageComponents (About Us sections)
    ['title' => 'AI', 'slug' => 'ai', 'template' => ''], // default = page.twig + pageComponents (AI sections)
    ['title' => 'Blogs', 'slug' => 'blogs', 'template' => ''], // default + Hero + Blogs (Archive), Vue uses WP posts
    ['title' => 'Career', 'slug' => 'career', 'template' => ''], // default + pageComponents (frontend/src/career.html sections)
    ['title' => 'Case Studies', 'slug' => 'case-studies', 'template' => ''],
    ['title' => 'Contact', 'slug' => 'contact', 'template' => ''], // default = page.twig + pageComponents (Contact + Global Presence)
    ['title' => 'Technology Capabilities', 'slug' => 'technology-apabilities', 'template' => ''], // default = page.twig + pageComponents (Hero + Technology Capability)
    ['title' => 'Our Services', 'slug' => 'our-services', 'template' => ''], // default = page.twig + pageComponents (Hero + Services Details)
    ['title' => 'Privacy Policy', 'slug' => 'privacy-policy', 'template' => ''],
    ['title' => 'Cookies Policy', 'slug' => 'cookies-policy', 'template' => ''],
    ['title' => 'Terms of Use', 'slug' => 'terms-of-use', 'template' => ''],
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
 * Sideload one asset from theme frontend/build/img into the media library. Returns attachment ID or 0.
 */
function nscSideloadBuildImageByFilename(string $filename): int
{
    $buildUri = get_template_directory_uri() . '/frontend/build';
    $existing = get_posts([
        'post_type'      => 'attachment',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'meta_query'     => [['key' => 'nsc_build_asset', 'value' => $filename, 'compare' => '=']],
    ]);
    if (!empty($existing)) {
        return (int) $existing[0]->ID;
    }
    $url = $buildUri . '/img/' . rawurlencode($filename);
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
    update_post_meta((int) $id, 'nsc_build_asset', $filename);

    return (int) $id;
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
                ['number' => '200', 'suffix' => '+', 'title' => 'Senior Experts', 'subtitle' => '(90% Senior-Level)'],
                ['number' => '100', 'suffix' => '%', 'title' => 'English-Proficient Team', 'subtitle' => ''],
                ['number' => '100', 'suffix' => '+', 'title' => 'Successful Projects', 'subtitle' => ''],
                ['number' => '60', 'suffix' => '+', 'title' => "Global \n Clients", 'subtitle' => ''],
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
                ['title' => '90% Senior-Level Engineers', 'description' => 'All engineers are senior professionals with a minimum of 6 years of experience.'],
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
            'labels'               => [
                'featuredTitle'       => 'Featured Insights',
                'featuredDescription' => 'Explore in-depth perspectives from our experts on software development, digital transformation, and emerging technology trends.',
                'latestTitle'         => 'Latest Updates',
                'latestDescription'   => 'Keep up with our latest news, events, and knowledge sharing from the NSC team.',
                'readMore'            => 'Read More',
            ],
            'options'              => ['theme' => ''],
        ],
        // 9. Contact Us (section.contact-us)
        [
            'acf_fc_layout'  => 'nscBlockContactUs',
            'title'          => 'CONTACT',
            'contentLines'   => "Ideas that inspire.\nStories that shape the future.",
            'showForm'       => 1,
            'cf7Shortcode'   => '[contact-form-7 id="" title="NSC Main Contact Form"]',
            'formAction'     => $baseUrl,
            'options'        => ['theme' => ''],
        ],
    ];
}

/**
 * About page components matching frontend/build/about.html section order and content.
 * Live content by default; use content_test=1 to prepend "[test] " to text.
 *
 * @return array<int, array<string, mixed>>
 */
function getAboutPageComponents(): array
{
    $baseUrl = home_url('/');

    return [
        // 1. Hero (section.hero.left-text)
        [
            'acf_fc_layout'    => 'nscBlockHero',
            'heroStyle'        => 'left_text',
            'headline'         => '<sb>We\'re</sb> <span class="highlight primary">Vietnam\'s Premier</span> <br class="lg:hidden"> <sb>Software Development &</sb> <br> <sb>Consulting Company</sb>',
            'description'      => '<p>Combining Vietnam\'s <b>Top 7% IT talents</b> - all <b>senior-level</b> engineers - with <b>AI-enabled delivery</b>, NSC Software helps global enterprises design, build, and scale secure, high-performing, and future-ready software solutions that drive long-term business value.</p>',
            'button'           => ['label' => '', 'url' => '', 'openInNewTab' => 0],
            'options'          => ['theme' => ''],
        ],
        // 2. Company Snapshot (section.company-stats-full)
        [
            'acf_fc_layout'  => 'nscBlockCompanySnapshot',
            'title'          => 'Company Snapshot',
            'stats'          => [
                ['number' => '2021', 'suffix' => '', 'title' => "Founded \n Year", 'subtitle' => ''],
                ['number' => '200', 'suffix' => '+', 'title' => 'Senior Expert', 'subtitle' => '(90% Senior-Level)'],
                ['number' => '60', 'suffix' => '+', 'title' => "Global \n Clients", 'subtitle' => ''],
                ['number' => '100', 'suffix' => '+', 'title' => "Successful \n Projects", 'subtitle' => ''],
                ['number' => '100', 'suffix' => '%', 'title' => "English-Proficient \n Team", 'subtitle' => ''],
            ],
            'certImages'     => [],
            'options'        => ['theme' => ''],
        ],
        // 3. Our Story (section.our-story)
        [
            'acf_fc_layout' => 'nscBlockOurStory',
            'title'         => 'OUR STORY',
            'content'       => '<p>NSC Software was born from Vietnam\'s spirit of <br class="hidden lg:block"> <b>resilience, intelligence</b>, and <b>creativity</b> - the same qualities that have defined our nation\'s growth in the modern era.</p>',
            'columns'       => [
                ['title' => "Our name carries deep roots in \n Vietnamese identity", 'description' => 'And our logo draws inspiration from two of the country\'s most iconic symbols - the map of Vietnam and the letter "S" that represents both shape and strength. Together, they symbolize our mission to elevate Vietnamese engineering talent to the world stage.'],
                ['title' => "We take pride in representing \n Vietnam", 'description' => "A country rich in talent, discipline, and ambition - through our work with enterprises across Australia, the UK, Europe, and the US."],
                ['title' => "At NSC, all of our engineers are \n senior professionals", 'description' => "Carefully selected from Vietnam's Top 7% IT talents. We combine this exceptional human expertise with AI-enabled delivery to build software that is scalable, secure, and transformative."],
                ['title' => "Our purpose goes beyond \n building technology", 'description' => 'We help global organizations innovate with confidence, execute with precision, and grow with lasting value, while showcasing the world-class potential of Vietnamese engineers'],
            ],
            'options'       => ['theme' => ''],
        ],
        // 4. Our Leaders (section.our-leaders)
        [
            'acf_fc_layout' => 'nscBlockOurLeaders',
            'title'        => 'Our Management Team',
            'content'      => '<p>NSC Software is guided by experienced leaders committed to engineering excellence, innovation, <br class="hidden lg:block"> and <b>trusted partnerships worldwide.</b></p>',
            'leaders'      => [
                ['name' => 'Duc Vu', 'role' => 'CTO & Co-Founder'],
                ['name' => 'Thanh Nguyen', 'role' => 'CEO & Founder'],
                ['name' => 'Anthony Cursio', 'role' => 'Country Director, AU & NZ'],
                ['name' => 'Heiko Hellstern', 'role' => 'Country Director, Germany'],
            ],
            'options'      => ['theme' => ''],
        ],
        // 5. Why NSC (section.why-us)
        [
            'acf_fc_layout' => 'nscBlockWhyUs',
            'title'        => 'Why NSC Software?',
            'items'        => [
                ['title' => 'Senior-Led, AI-Driven Expertise', 'description' => 'Projects are guided by senior engineers and enhanced by AI for higher efficiency and quality.'],
                ['title' => 'Time-Zone Aligned Collaboration', 'description' => 'Teams operate in overlapping hours with Europe, Australia and the US for real-time coordination.'],
                ['title' => "Vietnam's Top 7% IT Talents", 'description' => 'Top 7% Vietnamese engineers, rigorously selected for technical excellence and problem-solving capability.'],
                ['title' => '100% English-Proficient Team', 'description' => 'Seamless communication and collaboration with clients across global regions.'],
                ['title' => '90% Senior-Level Engineers', 'description' => 'All engineers are senior professionals with a minimum of 6 years of experience.'],
                ['title' => '6+ Years Minimum Experience', 'description' => 'Depth of technical expertise and delivery maturity across complex enterprise systems.'],
                ['title' => 'High-Quality, Cost-Efficient Delivery', 'description' => 'Projects are guided by senior engineers and enhanced by AI for higher efficiency and quality.'],
            ],
            'options'      => ['theme' => ''],
        ],
        // 6. Technology Capabilities block (section.our-capabilities)
        [
            'acf_fc_layout' => 'nscBlockOurCapabilities',
            'title'        => 'Technology Capabilities',
            'titleLine1'    => "Full-Stack \n Engineering.",
            'titleLine2'    => 'Enterprise Delivery.',
            'button'        => ['label' => 'Explore Full Technology Capabilities', 'url' => $baseUrl . 'technology-apabilities/', 'openInNewTab' => 0],
            'paragraphs'    => '<p>NSC Software delivers end-to-end technology capabilities - from software development, system architecture, and managed services to data, AI, blockchain, and enterprise platforms.</p><p>Backed by Vietnam\'s <b class="text-primary">Top 7%</b> senior engineers and AI-enabled delivery, we help global organizations modernize legacy systems, build innovative products, and scale operations with confidence and precision.</p>',
            'options'       => ['theme' => ''],
        ],
        // 7. Global Presence (section.global-presence)
        [
            'acf_fc_layout' => 'nscBlockGlobalPresence',
            'title'        => 'GLOBAL PRESENCE',
            'locations'     => [
                ['label' => 'Dallas', 'title' => 'Dallas', 'address' => '4245 N Central Expy, #490, Dallas, TX, USA 75205', 'phoneLink' => '+1 (713) 428 2289'],
                ['label' => 'Germany', 'title' => 'Germany', 'address' => 'Am Hauptbahnhof 16, D-60306 Frankfurt am Main, Germany', 'phoneLink' => '(+49) 170 1633520'],
                ['label' => 'Ha Noi', 'title' => 'NSC Software Headquarters', 'address' => 'Level 22, PVI Tower, Pham Van Bach, Cau Giay, Hanoi, Vietnam', 'phoneLink' => '(+84) 866 639 497'],
                ['label' => 'Ho Chi Minh City', 'title' => 'Ho Chi Minh', 'address' => 'Level 10, Five Star Tower, 28 Bis, Ho Chi Minh, Vietnam', 'phoneLink' => '(+84) 866 639 497'],
                ['label' => 'Sydney', 'title' => 'Sydney', 'address' => 'Level 24, Three International Towers, 300 Barangaroo Avenue, Sydney NSW 2000, Australia', 'phoneLink' => '(+61) 0488 860 719'],
            ],
            'options'       => ['theme' => ''],
        ],
        // 8. Contact Us (section.contact-us)
        [
            'acf_fc_layout'  => 'nscBlockContactUs',
            'title'          => 'CONTACT',
            'contentLines'   => "Ideas that inspire.\nStories that shape the future.",
            'showForm'       => 1,
            'cf7Shortcode'   => '[contact-form-7 id="" title="NSC Main Contact Form"]',
            'formAction'     => $baseUrl,
            'options'        => ['theme' => ''],
        ],
    ];
}

/**
 * AI page components matching frontend/build/ai.html section order and content.
 * Live content by default; use content_test=1 to prepend "[test] " to text.
 *
 * @return array<int, array<string, mixed>>
 */
function getAiPageComponents(): array
{
    $baseUrl = home_url('/');

    return [
        // 1. Hero (section.hero.dark)
        [
            'acf_fc_layout'   => 'nscBlockHero',
            'heroStyle'       => 'dark',
            'headline'        => 'Engineering the Future with <br> <span class="text-primary">AI-Augmented Intelligence</span>',
            'description'     => '<p>We don\'t replace engineers with AI. We equip them with superpowers. Experience software delivery that is <b>faster</b>, <b>smarter</b>, and <b>cost-optimized</b> for the enterprise era.</p>',
            'button'          => ['label' => '', 'url' => '', 'openInNewTab' => 0],
            'options'         => ['theme' => ''],
        ],
        // 2. AI Banner (section.ai-banner)
        [
            'acf_fc_layout' => 'nscBlockAiBanner',
            'title'         => 'The 90% Seniority Advantage: Masters, <br class="hidden lg:block"> Not Apprentices',
            'content'       => '<p>AI generates code. Our Senior Engineers generate <b>architecture, judgment</b>, and <b>value</b>. <br class="hidden lg:block"> We don\'t use AI to hide junior talent, we use it to amplify expert intuition. </p>',
            'options'       => ['theme' => ''],
        ],
        // 3. AI Info (section.ai-info)
        [
            'acf_fc_layout' => 'nscBlockAiInfo',
            'items'         => [
                ['title' => 'AI Hallucinates, Seniors Validate', 'description' => 'Junior engineers accept AI suggestions blindly. Our seniors (avg. 5+ years exp) have the deep intuition to spot subtle AI bugs and security flaws instantly.'],
                ['title' => 'AI Writes Functions, Seniors Build Systems', 'description' => 'AI is tactical; it sees lines of code. Our experts are strategic; they see the entire system architecture, ensuring scalability and maintainability that AI cannot comprehend.'],
                ['title' => 'From "Coding" to "Orchestrating"', 'description' => 'With 90% senior talent, we skip the learning curve. Our engineers act as "Architects," orchestrating AI agents to deliver enterprise-grade solutions at 3x the speed of traditional teams.'],
            ],
            'options'       => ['theme' => ''],
        ],
        // 4. Timeline (section.timeline – Bionic Engineering Process)
        [
            'acf_fc_layout'    => 'nscBlockAiTimeline',
            'title'           => 'THE "BIONIC" <br class="hidden lg:block"> ENGINEERING PROCESS',
            'description'     => '<p>Integrating AI into every step of the SDLC to cut noise and amplify value.</p>',
            'phases'           => [
                ['milestone' => 'Phase 1', 'title' => 'Design & Specs', 'content' => '<p>Prompt-to-UI Prototyping.</p>'],
                ['milestone' => 'Phase 2', 'title' => 'Development', 'content' => '<p>AI-Assisted Coding (Cursor/Copilot) <br> Reducing boilerplate by 40%.</p>'],
                ['milestone' => 'Phase 3', 'title' => 'Quality Assurance', 'content' => '<p>Generative Testing <br> Autonomously covering 99% of edge cases.</p>'],
                ['milestone' => 'Phase 4', 'title' => 'Modernization', 'content' => '<p>AI-Driven Refactoring <br> Decoding and upgrading legacy systems.</p>'],
            ],
            'quote'           => '<p>"At NSC, AI is embedded in our DNA. We leverage advanced Generative AI agents to automate repetitive coding tasks, allowing our senior engineers to dedicate 100% of their intellect to your complex business logic."</p>',
            'options'         => ['theme' => ''],
        ],
        // 5. AI Impact (section.ai-impact)
        [
            'acf_fc_layout' => 'nscBlockAiImpact',
            'title'         => 'TURNING AI HYPE INTO BUSINESS IMPACT',
            'items'         => [
                ['num' => '01', 'title' => '<span>01.</span> Enterprise GenAI & RAG', 'content' => '<p>Securely integrate LLMs with your proprietary data. We build intelligent Knowledge Bases and Context-Aware Chatbots using Retrieval-Augmented Generation (RAG) architecture.</p>'],
                ['num' => '02', 'title' => '<span>02.</span> Computer Vision & OCR', 'content' => '<p>From automated document processing to intelligent surveillance. We deploy vision models that see, analyze, and act in real-time.</p>'],
                ['num' => '03', 'title' => '<span>03.</span> Predictive Analytics & Forecasting', 'content' => '<p>Move from reactive to proactive. Leverage Machine Learning to predict market trends, detect fraud, and personalize customer experiences at scale.</p>'],
                ['num' => '04', 'title' => '<span>04.</span> Software Development & Digital Solutions', 'content' => '<p>Prepare your infrastructure for the AI era. We clean your data, migrate workloads to the Cloud, and API-enable your legacy systems for seamless AI integration.</p>'],
            ],
            'options'        => ['theme' => ''],
        ],
        // 6. Capabilities Details (section.our-capabilities-details)
        [
            'acf_fc_layout' => 'nscBlockAiCapabilitiesDetails',
            'title'         => 'ORCHESTRATING A BEST-IN-CLASS ECOSYSTEM',
            'description'   => '<p>We partner with the world\'s leading platforms to build scalable, secure, and sovereign AI solutions.</p>',
            'rows'          => [
                ['title' => 'Foundation Models (LLMs)', 'badge' => '', 'images' => []],
                ['title' => 'Efficient & Edge AI (SLMs)', 'badge' => '💰 Cost Optimized', 'images' => []],
                ['title' => 'Development Frameworks', 'badge' => '', 'images' => []],
                ['title' => 'Vector Databases <br> (AI Memory)', 'badge' => '', 'images' => []],
                ['title' => 'Cloud & MLOps <br> Infrastructure', 'badge' => '', 'images' => []],
                ['title' => 'AI Coding Tools (Internal)', 'badge' => '', 'images' => []],
            ],
            'quote'          => '<p>We are platform-agnostic. We choose the right model and infrastructure for your specific constraints and budget.</p>',
            'options'        => ['theme' => ''],
        ],
        // 7. AI Security (section.ai-security)
        [
            'acf_fc_layout' => 'nscBlockAiSecurity',
            'title'         => 'ENTERPRIES-GRADE <br>TRUST & SECURITY',
            'subtitle'      => 'Three Pillars of Trust',
            'items'         => [
                ['title' => 'Zero-Data Retention', 'content' => 'Your data remains yours. We implement strict policies ensuring your proprietary information is never used to train public models.'],
                ['title' => 'Compliance Ready', 'content' => 'Our AI development lifecycle adheres to ISO 27001, SOC 2, and GDPR standards. We integrate automated security guardrails to prevent AI hallucinations and vulnerabilities.'],
                ['title' => 'Human-in-the-Loop', 'content' => 'AI proposes, Humans decide. We maintain rigorous human oversight on all critical code commits and strategic decisions.'],
            ],
            'options'       => ['theme' => ''],
        ],
        // 8. Contact Us (section.contact-us)
        [
            'acf_fc_layout'  => 'nscBlockContactUs',
            'title'          => 'CONTACT',
            'contentLines'   => "Ideas that inspire.\nStories that shape the future.",
            'showForm'       => 1,
            'cf7Shortcode'   => '[contact-form-7 id="" title="NSC Main Contact Form"]',
            'formAction'     => $baseUrl,
            'options'        => ['theme' => ''],
        ],
    ];
}

/**
 * Our Services page components matching frontend/build/our-services.html (Hero + Our Services Details).
 * Live content by default; use content_test=1 to prepend "[test] " to text.
 *
 * @return array<int, array<string, mixed>>
 */
function getOurServicesPageComponents(): array
{
    $baseUrl = home_url('/');

    return [
        // 1. Hero (section.hero.dark)
        [
            'acf_fc_layout' => 'nscBlockHero',
            'heroStyle'     => 'dark',
            'headline'      => '<span class="highlight primary">End-to-End</span> <br class="lg:hidden"> <sb>Technology Services</sb> <br> <sb>for</sb> <span class="highlight primary">Modern Enterprice</span>',
            'description'   => '<p>Powered by Vietnam\'s <b>Top 7% senior engineers</b> and <b>AI-enabled delivery</b>, NSC Software provides a comprehensive suite of technology services that help organizations innovate, modernize, and scale with confidence.</p>',
            'button'        => ['label' => '', 'url' => '', 'openInNewTab' => 0],
            'options'       => ['theme' => ''],
        ],
        // 2. Our Services Details (section.our-services-details)
        [
            'acf_fc_layout'    => 'nscBlockOurServicesDetails',
            'titleLine1'       => 'OUR SERVICES ',
            'titleLine2'       => 'YOUR STRATEGIC',
            'titleLine3'       => '<span class="highlight">ADVANTAGE</span>',
            'introParagraphs' => '<p>We deliver full-cycle technology services - from software development and system architecture to AI, cloud, and enterprise platforms.</p><p>Our 100% senior engineering teams combine deep technical expertise with disciplined execution to help global organizations build secure, scalable, and high-performing digital systems.</p>',
            'serviceItems'     => [
                [
                    'number'      => '1',
                    'title'      => 'Software Development & Digital Solutions',
                    'subtitle'   => 'Building scalable, secure, and user-centric software across web, mobile, and enterprise systems.',
                    'description' => 'We deliver end-to-end software development with a strong focus on performance, user experience, and long-term business value. Whether modernizing legacy platforms or building new digital products, our senior engineers ensure reliability, scalability, and clean architecture.',
                    'listItems'   => [
                        ['item' => 'Custom Enterprise Software Development'],
                        ['item' => 'Web Application Development'],
                        ['item' => 'Mobile Application Development'],
                        ['item' => 'Legacy System Modernization'],
                        ['item' => 'API Development & Integration'],
                        ['item' => 'Microservices Architecture'],
                        ['item' => 'Cloud-Native Solutions'],
                    ],
                ],
                [
                    'number'      => '2',
                    'title'      => 'Technology Consulting & Architecture',
                    'subtitle'   => 'Aligning technology with business strategy through expert guidance and enterprise-grade architecture.',
                    'description' => 'Our consulting practice helps organizations make the right technology decisions, design scalable systems, and plan for long-term modernization. We bring clarity, structure, and senior-level insight to complex technology landscapes.',
                    'listItems'   => [
                        ['item' => 'Technology Strategy & Roadmapping'],
                        ['item' => 'AI Strategy & Adoption'],
                        ['item' => 'Solution Architecture Design'],
                        ['item' => 'Modernization & Migration Consulting'],
                        ['item' => 'System Integration Consulting'],
                        ['item' => 'Cloud & DevOps Consulting'],
                        ['item' => 'Security Architecture & Compliance Design'],
                    ],
                ],
                [
                    'number'      => '3',
                    'title'      => 'Cloud & DevOps Services',
                    'subtitle'   => 'Cloud-native engineering and automated delivery pipelines for agility, security, and uptime.',
                    'description' => 'We help enterprises adopt cloud-native principles, optimize cloud costs, and automate delivery pipelines. Our DevOps experts ensure consistent, secure, and efficient deployment practices across AWS, Azure, and GCP.',
                    'listItems'   => [
                        ['item' => 'Cloud Architecture & Engineering (AWS, Azure, GCP)'],
                        ['item' => 'CI/CD Pipeline Setup & Automation'],
                        ['item' => 'Cloud Cost Optimization & Monitoring'],
                        ['item' => 'DevSecOps Implementation'],
                        ['item' => 'Containerization (Docker, Kubernetes)'],
                    ],
                ],
                [
                    'number'      => '4',
                    'title'      => 'Data Engineering & Analytics',
                    'subtitle'   => 'Transforming data into actionable insights and intelligent business decisions.',
                    'description' => 'We architect and build modern data systems to help organizations centralize, process, and analyze data effectively. Our solutions support both real-time and large-scale data workloads.',
                    'listItems'   => [
                        ['item' => 'Data pipeline, ETL/ELT'],
                        ['item' => 'Data lakes, data warehouse'],
                        ['item' => 'Real-time streaming'],
                        ['item' => 'BI dashboard, analytics'],
                        ['item' => 'Data quality, governance'],
                    ],
                ],
                [
                    'number'      => '5',
                    'title'      => 'AI & Machine Learning',
                    'subtitle'   => 'Leveraging AI to automate, optimize, and accelerate enterprise innovation.',
                    'description' => 'From machine learning to generative AI, we help organizations adopt, integrate, and operationalize AI solutions that drive automation, intelligence, and new value creation.',
                    'listItems'   => [
                        ['item' => 'AI Strategy & Adoption'],
                        ['item' => 'Machine Learning Solutions'],
                        ['item' => 'Computer Vision & OCR'],
                        ['item' => 'Natural Language Processing (NLP)'],
                        ['item' => 'Generative AI & LLM Solutions'],
                        ['item' => 'AI Deployment & MLOps'],
                        ['item' => 'AI Integration & Automation'],
                    ],
                ],
                [
                    'number'      => '6',
                    'title'      => 'Blockchain & Web3 Development',
                    'subtitle'   => 'Building decentralized solutions that deliver trust, transparency, and digital ownership.',
                    'description' => 'We develop secure and scalable Web3 applications, smart contracts, and cross-chain integrations. Our blockchain team has deep expertise in major networks and emerging decentralized ecosystems',
                    'listItems'   => [
                        ['item' => 'Smart Contract Development (Solidity, Rust)'],
                        ['item' => 'DeFi, GameFi, SocialFi Platforms'],
                        ['item' => 'NFT Marketplaces & Tokenization Platforms'],
                        ['item' => 'Wallet & Payment Integration'],
                        ['item' => 'Custom Blockchain Protocols & Tools'],
                        ['item' => 'Cross-chain & Layer 2 Integration'],
                    ],
                ],
                [
                    'number'      => '7',
                    'title'      => 'Quality Assurance & Testing Services',
                    'subtitle'   => 'Ensuring software reliability, security, and performance through rigorous QA practices.',
                    'description' => 'Our QA teams apply a mix of manual, automated, and performance testing to ensure every release meets enterprise-level standards for quality and security.',
                    'listItems'   => [
                        ['item' => 'Test Planning & QA Strategy'],
                        ['item' => 'Manual Testing'],
                        ['item' => 'Automation Testing'],
                        ['item' => 'Performance & Load Testing'],
                        ['item' => 'Security & Penetration Testing'],
                    ],
                ],
                [
                    'number'      => '8',
                    'title'      => 'ERP, CRM & Business Platform Services',
                    'subtitle'   => 'Streamlining operations and enabling growth through leading enterprise platforms.',
                    'description' => 'We implement, customize, and integrate modern ERP and CRM systems to help businesses improve efficiency, automate workflows, and unify operations.',
                    'listItems'   => [
                        ['item' => 'Odoo Development & Customization'],
                        ['item' => 'Microsoft Power Platform (PowerApps, Power Automate)'],
                        ['item' => 'Dynamics 365 Customization & Integration'],
                        ['item' => 'Salesforce Development'],
                        ['item' => 'SAP Extensions & Integrations'],
                        ['item' => 'Workflow Automation, RPA'],
                        ['item' => 'ERP/CRM Custom Modules'],
                        ['item' => 'Third-party System Integration'],
                    ],
                ],
            ],
            'banner' => [
                'title'       => 'Ready to Build High-Performing <br class="hidden lg:block"> Digital Solutions',
                'description' => '<p>Work with <b>Vietnam\'s Top 7%</b> senior <br class="lg:hidden"> engineers <br> to accelerate your technology  <br class="lg:hidden"> initiatives.</p>',
                'button'      => [
                    'label'        => 'Speak With Our Team',
                    'url'          => $baseUrl . 'contact/',
                    'openInNewTab' => 0,
                ],
            ],
            'options' => ['theme' => ''],
        ],
    ];
}

/**
 * Technology Capabilities page components matching frontend/build/technology-apabilities.html (Hero + Technology Capability).
 * Live content by default; use content_test=1 to prepend "[test] " to text. Row images are empty; add in CMS.
 *
 * @return array<int, array<string, mixed>>
 */
function getOurCapabilitiesPageComponents(): array
{
    return [
        // 1. Hero (section.hero.dark)
        [
            'acf_fc_layout' => 'nscBlockHero',
            'heroStyle'     => 'dark',
            'headline'      => '<span class="highlight primary">End-to-End</span> <br class="lg:hidden"> <sb>Technology Services</sb> <br> <sb>for</sb> <span class="highlight primary">Modern Enterprice</span>',
            'description'   => '<p>Powered by Vietnam\'s <b>Top 7% senior engineers</b> and <b>AI-enabled delivery</b>, NSC Software provides a comprehensive suite of technology services that help organizations innovate, modernize, and scale with confidence.</p>',
            'button'        => ['label' => '', 'url' => '', 'openInNewTab' => 0],
            'options'       => ['theme' => ''],
        ],
        // 2. Technology Capability (section.our-capabilities-details)
        [
            'acf_fc_layout' => 'nscBlockTechnologyCapability',
            'title'         => 'TECHNOLOGY <br class="lg:hidden"> CAPABILITY',
            'description'   => '<p class="center">Our expertise spans the full technology stack, from enterprise systems to emerging technologies, ensuring scalable and future-ready solutions.</p>',
            'rows'          => [
                ['title' => 'Backend Development', 'rightColClass' => '', 'imageGroups' => [['label' => '', 'images' => []]]],
                ['title' => 'Frontend Development', 'rightColClass' => '', 'imageGroups' => [['label' => '', 'images' => []]]],
                [
                    'title'         => 'Mobile Development',
                    'rightColClass' => '',
                    'imageGroups'   => [
                        ['label' => 'Android: ', 'images' => []],
                        ['label' => 'iOS: ', 'images' => []],
                        ['label' => 'Cross-platform: ', 'images' => []],
                    ],
                ],
                ['title' => 'Database', 'rightColClass' => '', 'imageGroups' => [['label' => '', 'images' => []]]],
                [
                    'title'         => 'Cloud & DevOps',
                    'rightColClass' => '',
                    'imageGroups'   => [
                        ['label' => 'Cloud & Infrastructure: ', 'images' => []],
                        ['label' => '', 'images' => []],
                        ['label' => 'Containers & Automation: ', 'images' => []],
                        ['label' => '', 'images' => []],
                        ['label' => 'Monitoring & Logging: ', 'images' => []],
                        ['label' => '', 'images' => []],
                    ],
                ],
                ['title' => 'Data Engineering', 'rightColClass' => '!gap-2', 'imageGroups' => [['label' => '', 'images' => []]]],
                ['title' => 'AI & Machine Learning', 'rightColClass' => '', 'imageGroups' => [['label' => '', 'images' => []]]],
                ['title' => 'Blockchain', 'rightColClass' => '!gap-2', 'imageGroups' => [['label' => '', 'images' => []]]],
                ['title' => 'Automation Quality Assurance', 'rightColClass' => '', 'imageGroups' => [['label' => '', 'images' => []]]],
                ['title' => 'Enterprise/Ecommerce Platforms', 'rightColClass' => '', 'imageGroups' => [['label' => '', 'images' => []]]],
            ],
            'options'       => ['theme' => ''],
        ],
    ];
}

/**
 * Merge legacy policy subsection markup into a single paragraph (live + translation source).
 * Pattern: <p><strong>Label</strong></p> + <p>body</p> → <p>Label <br> body</p>. Skips when the next
 * paragraph starts with <strong>. Runs until stable so a section title can merge after its
 * subsection’s strong+body pair has been merged (e.g. “The Cookies We Set” + first bullet).
 */
function nsc_seed_policy_normalize_paragraph_pairs(string $html): string
{
    $pattern = '#<p><strong>([^<]+)</strong></p>\s*<p>(?!\s*<strong>)(.*?)</p>#s';
    $prev = null;
    while ($html !== $prev) {
        $prev = $html;
        $merged = preg_replace($pattern, '<p>$1 <br> $2</p>', $html);
        $html = is_string($merged) ? $merged : $html;
    }

    return $html;
}

/**
 * Policy page (single block: heading + WYSIWYG content). Used for Privacy Policy, Cookies Policy, Terms of Use.
 * Content is loaded from policy-content/*.html. Use content_test=1 to prepend "[test] " to title only (content preserved).
 *
 * @return array<int, array<string, mixed>>
 */
function getPolicyPageComponents(string $title, string $contentHtml): array
{
    return [
        [
            'acf_fc_layout' => 'nscBlockPolicyPage',
            'title'         => $title,
            'content'       => $contentHtml,
            'options'       => ['theme' => ''],
        ],
    ];
}

/**
 * Blogs listing page: Hero (dark, same messaging as frontend/src/blogs.html) + archive block (Vue + WP posts).
 *
 * @return array<int, array<string, mixed>>
 */
function getBlogsPageComponents(): array
{
    $baseUrl = home_url('/');
    $description = '<p>At NSC Software, technology is only as powerful as the people building it. From engineering insights to culture and teamwork, our blog shows how NSC builds software — and how it connects to the wider story on our '
        . '<a href="' . esc_url($baseUrl) . '">Home</a>, '
        . '<a href="' . esc_url($baseUrl . 'about/') . '">About</a>, '
        . '<a href="' . esc_url($baseUrl . 'ai/') . '">AI</a>, '
        . '<a href="' . esc_url($baseUrl . 'our-services/') . '">Our Services</a>, '
        . 'and <a href="' . esc_url($baseUrl . 'technology-apabilities/') . '">Technology Capabilities</a> pages.</p>';

    return [
        [
            'acf_fc_layout' => 'nscBlockHero',
            'heroStyle' => 'dark',
            'headline' => '<sb>Discover the People Behind the Code</sb>',
            'description' => $description,
            'button' => ['label' => '', 'url' => '', 'openInNewTab' => 0],
            'options' => ['theme' => ''],
        ],
        [
            'acf_fc_layout' => 'nscBlockBlogsArchive',
            'title' => 'BLOGS',
            'description' => '<p>Browse articles from the NSC team. Category filters and search use live WordPress posts (featured area uses posts marked “Featured article” in the sidebar, or the latest posts).</p>',
            'postsPerPage' => 12,
            'listLabels' => [
                'blogsListHeading' => 'Blogs',
                'searchPlaceholder' => 'Search',
                'searchResultSingular' => 'result',
                'searchResultPlural' => 'results',
                'allCategoriesLabel' => 'All Categories',
                'readMore' => 'Read More',
                'previous' => 'Prev',
                'next' => 'Next',
                'noBlogFound' => 'No blog found.',
            ],
            'options' => ['theme' => ''],
        ],
    ];
}

/**
 * Case Studies listing page: Hero (case_studies) + archive (Vue + CPT case_study), matching frontend/src/case-studies.html.
 *
 * @return array<int, array<string, mixed>>
 */
function getCaseStudiesPageComponents(): array
{
    $heroImgId = nscSideloadBuildImageByFilename('hero-cs.png');

    $hero = [
        'acf_fc_layout' => 'nscBlockHero',
        'heroStyle' => 'case_studies',
        'headline' => 'Real Projects, Real Impact',
        'description' => '<p>See how NSC Software turns ideas into results. Our <br class="hidden xl:block">case studies showcase <b>innovative solutions, <br class="hidden xl:block"> international projects, and measurable impact, <br class="hidden xl:block"></b> giving you a front-row seat to how our team delivers <br class="hidden xl:block"> value to clients worldwide.</p>',
        'button' => ['label' => '', 'url' => '', 'openInNewTab' => 0],
        'options' => ['theme' => ''],
    ];
    if ($heroImgId > 0) {
        $hero['image'] = $heroImgId;
    }

    return [
        $hero,
        [
            'acf_fc_layout' => 'nscBlockCaseStudiesArchive',
            'title' => 'Case Studies',
            'listLabels' => [
                'allCategoriesLabel' => 'All Categories',
                'readMore' => 'Read More',
                'previous' => 'Previous',
                'next' => 'Next',
                'emptyList' => 'No case studies found in this category.',
            ],
            'options' => [
                'caseStudiesPerPage' => 6,
                'hidden' => 0,
            ],
        ],
    ];
}

/**
 * Career page: Hero (dark, career copy) + We are NSC + Core values + Open positions (Vue + CPT job / job_category).
 *
 * @return array<int, array<string, mixed>>
 */
function getCareerPageComponents(): array
{
    $heroImgId = nscSideloadBuildImageByFilename('hero-career.png');

    $weAreBody = '<p>Founded in Vietnam with a global vision, NSC Software delivers innovative software solutions that empower enterprises worldwide. We foster a <b class="text-primary">collaborative</b>, <b class="text-primary">performance-driven</b>, and <b class="text-primary">learning-focused culture</b>, where our disciplined and passionate team tackles challenges with precision.</p>'
        . '<p>Committed to <b class="text-primary">global standards</b> and continuous growth, we invest in technical and language training, enabling our team to excel on international projects. Our goal is to be <b class="text-primary">Asia’s most trusted partner in software development and consulting</b>, positioning Vietnam as a leading global IT hub.</p>';

    $hero = [
        'acf_fc_layout' => 'nscBlockHero',
        'heroStyle'     => 'dark',
        'headline'      => 'Careers at NSC Software <br> At NSC, we <span class="highlight primary">CARE</span> <br> about your Careers!',
        'description'   => '',
        'button'        => ['label' => '', 'url' => '', 'openInNewTab' => 0],
        'options'       => ['theme' => ''],
    ];
    if ($heroImgId > 0) {
        $hero['image'] = $heroImgId;
    }

    return [
        $hero,
        [
            'acf_fc_layout' => 'nscBlockCareerWeAreNsc',
            'heading'       => 'WE ARE NSC <br class="hidden xl:block"> SOFTWARE',
            'body'          => $weAreBody,
            'ctaText'       => 'Join NSC and help shape the future of software',
            'ctaUrl'        => '#open-positions-app',
            'options'       => ['theme' => ''],
        ],
        [
            'acf_fc_layout' => 'nscBlockCareerCoreValues',
            'title'         => 'CORE VALUES',
            'values'        => [
                ['valueTitle' => 'Premier', 'valueDescription' => 'We deliver premier software development and consulting services, ensuring excellence in every solution we provide.'],
                ['valueTitle' => 'Talented', 'valueDescription' => 'We empower Vietnam\'s top engineers to demonstrate their capabilities, innovate boldly, and make a global impact.'],
                ['valueTitle' => 'Innovative', 'valueDescription' => 'We embrace emerging technologies and creative thinking to build future-ready, impactful solutions for our clients.'],
                ['valueTitle' => 'Committed', 'valueDescription' => 'We are dedicated to our clients\' success, going the extra mile to deliver value, quality, and long-term partnerships.'],
                ['valueTitle' => 'Trusted', 'valueDescription' => 'We build long-lasting relationships through integrity, transparency, and consistent performance across every engagement.'],
            ],
            'options'       => ['theme' => ''],
        ],
        [
            'acf_fc_layout' => 'nscBlockJobsArchive',
            'title'         => 'OPEN POSITIONS',
            'intro'         => '<p>At NSC, we\'re always looking for passionate talents to grow with us. Explore our open roles and join a collaborative, global team shaping impactful engineering solutions.</p>',
            'listLabels'    => [
                'allPositionsLabel'       => 'All Positions',
                'allPositionsMobileLabel' => 'All',
                'previous'                => 'Previous',
                'next'                    => 'Next',
                'noPositionsFound'        => 'No positions found.',
                'loadingText'             => 'Loading positions...',
                'errorText'               => 'Could not load positions.',
                'applyNow'                => 'Apply Now',
            ],
            'options'       => [
                'jobsPerPage' => 5,
                'defaultTab'  => 'all',
                'theme'       => '',
            ],
        ],
    ];
}

/**
 * Contact page components matching frontend/build/contact.html (Contact section + Global Presence).
 * Live content by default; use content_test=1 to prepend "[test] " to text. formAction and phoneLink preserved.
 *
 * @return array<int, array<string, mixed>>
 */
function getContactPageComponents(): array
{
    $baseUrl = home_url('/');

    return [
        // 1. Contact (section.contact: form + offices)
        [
            'acf_fc_layout'  => 'nscBlockContactPage',
            'title'          => 'CONTACT US',
            'contentLines'   => "Ideas that inspire.\nStories that shape the future.",
            'showForm'       => 1,
            'formAction'     => $baseUrl,
            'cf7Shortcode'   => '[contact-form-7 id="" title="NSC Main Contact Form"]',
            'officesTitle'   => 'OUR OFFICES',
            'offices'        => [
                [
                    'title'        => 'NSC Software Headquarters',
                    'address'      => "Level 22, PVI Tower, Pham Van Bach, Cau Giay, Hanoi, Vietnam",
                    'phoneDisplay' => '(+84) 866 639 497',
                    'phoneLink'    => '+84866639497',
                ],
                [
                    'title'        => 'NSC Software Ho Chi Minh',
                    'address'      => "Level 10, Five Star Tower, 28 Bis, Ho Chi Minh, Vietnam",
                    'phoneDisplay' => '(+84) 866 639 497',
                    'phoneLink'    => '+84866639497',
                ],
                [
                    'title'        => 'NSC Software USA',
                    'address'      => '4245 N Central Expy, #490, Dallas, TX, USA 75205',
                    'phoneDisplay' => '+1 (713) 428 2289',
                    'phoneLink'    => '+17134282289',
                ],
                [
                    'title'        => 'NSC Software Australia',
                    'address'      => 'Level 24, Three International Towers, 300 Barangaroo Avenue, Sydney NSW 2000, Australia',
                    'phoneDisplay' => '+61 0488 860 719',
                    'phoneLink'    => '+61488860719',
                ],
                [
                    'title'        => 'NSC Software Europe',
                    'address'      => 'A16 Am Hauptbahnhof, D-60306 Frankfurt am Main, Germany',
                    'phoneDisplay' => '+49 170 1633520',
                    'phoneLink'    => '+491701633520',
                ],
            ],
            'options'        => ['theme' => ''],
        ],
        // 2. Global Presence (section.global-presence)
        [
            'acf_fc_layout' => 'nscBlockGlobalPresence',
            'title'        => 'GLOBAL PRESENCE',
            'locations'     => [
                ['label' => 'Dallas', 'title' => 'Dallas', 'address' => '4245 N Central Expy, #490, Dallas, TX, USA 75205', 'phoneLink' => '+1 (713) 428 2289'],
                ['label' => 'Germany', 'title' => 'Germany', 'address' => 'Am Hauptbahnhof 16, D-60306 Frankfurt am Main, Germany', 'phoneLink' => '(+49) 170 1633520'],
                ['label' => 'Ha Noi', 'title' => 'NSC Software Headquarters', 'address' => 'Level 22, PVI Tower, Pham Van Bach, Cau Giay, Hanoi, Vietnam', 'phoneLink' => '(+84) 866 639 497'],
                ['label' => 'Ho Chi Minh City', 'title' => 'Ho Chi Minh', 'address' => 'Level 10, Five Star Tower, 28 Bis, Ho Chi Minh, Vietnam', 'phoneLink' => '(+84) 866 639 497'],
                ['label' => 'Sydney', 'title' => 'Sydney', 'address' => 'Level 24, Three International Towers, 300 Barangaroo Avenue, Sydney NSW 2000, Australia', 'phoneLink' => '(+61) 0488 860 719'],
            ],
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
    $preserveKeys = ['acf_fc_layout', 'openInNewTab', 'showForm', 'url', 'formAction', 'buttonUrl', 'phoneLink', 'cf7Shortcode', 'content'];
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

/**
 * Persist flexible pageComponents (same pattern as blog/job/case study seeders: direct ACF/meta write).
 */
function nsc_seed_pages_persist_page_components(int $pageId, array $components): void
{
    if ($pageId <= 0) {
        return;
    }
    if (function_exists('update_field')) {
        update_field('pageComponents', [], $pageId);
        update_field('pageComponents', $components, $pageId);
    } else {
        delete_post_meta($pageId, 'pageComponents');
        update_post_meta($pageId, 'pageComponents', $components);
    }
}

$pageScopeRaw = isset($_GET['page_scope']) ? sanitize_key((string) $_GET['page_scope']) : '';
$pageScopeActive = ($pageScopeRaw !== '' && $pageScopeRaw !== 'all');

$homeOnly         = isset($_GET['home_only']) && $_GET['home_only'] === '1';
$blogsOnly        = isset($_GET['blogs_only']) && $_GET['blogs_only'] === '1';
$careerOnly       = isset($_GET['career_only']) && $_GET['career_only'] === '1';
$policiesOnly     = isset($_GET['policies_only']) && $_GET['policies_only'] === '1';
$caseStudiesOnly  = isset($_GET['case_studies_only']) && $_GET['case_studies_only'] === '1';
$contentTest   = isset($_GET['content_test']) && $_GET['content_test'] === '1';

if ($pageScopeActive) {
    if ($pageScopeRaw === 'policies') {
        $policySlugs = ['privacy-policy', 'cookies-policy', 'terms-of-use'];
        $pages = array_filter($pages, static function (array $p) use ($policySlugs) {
            return in_array($p['slug'], $policySlugs, true);
        });
    } else {
        $pages = array_filter($pages, static function (array $p) use ($pageScopeRaw) {
            return $p['slug'] === $pageScopeRaw;
        });
    }
} elseif ($homeOnly) {
    $pages = array_filter($pages, static function (array $p) {
        return $p['slug'] === 'home';
    });
} elseif ($blogsOnly) {
    $pages = array_filter($pages, static function (array $p) {
        return $p['slug'] === 'blogs';
    });
} elseif ($careerOnly) {
    $pages = array_filter($pages, static function (array $p) {
        return $p['slug'] === 'career';
    });
} elseif ($caseStudiesOnly) {
    $pages = array_filter($pages, static function (array $p) {
        return $p['slug'] === 'case-studies';
    });
} elseif ($policiesOnly) {
    $policySlugs = ['privacy-policy', 'cookies-policy', 'terms-of-use'];
    $pages = array_filter($pages, static function (array $p) use ($policySlugs) {
        return in_array($p['slug'], $policySlugs, true);
    });
}

$skipHomeFrontPageSetup = $policiesOnly
    || ($pageScopeActive && (
        $pageScopeRaw === 'policies'
        || in_array($pageScopeRaw, ['privacy-policy', 'cookies-policy', 'terms-of-use'], true)
    ));

$results = [];

foreach ($pages as $page) {
    $slug     = $page['slug'];
    $title    = $page['title'];
    $template = $page['template'];
    $seededPageComponents = null;

    $singleTarget = function_exists('nsc_seed_is_single_target_language_run') && nsc_seed_is_single_target_language_run();
    $canonicalId  = 0;
    if (function_exists('nsc_seed_get_canonical_post_by_type_and_slug')) {
        $canonicalPost = nsc_seed_get_canonical_post_by_type_and_slug('page', $slug, true);
        if ($canonicalPost instanceof WP_Post) {
            $canonicalId = (int) $canonicalPost->ID;
        }
    }

    if ($singleTarget) {
        if ($canonicalId <= 0) {
            $results[] = [
                'slug'    => $slug,
                'status'  => 'skipped',
                'message' => 'No default-language page for this slug; run without seed_lang first.',
            ];
            continue;
        }
        $pageId = $canonicalId;
        $action = 'translation-updated';
    } else {
        $langArgs = function_exists('nsc_seed_polylang_get_explicit_lang_query_args')
            ? nsc_seed_polylang_get_explicit_lang_query_args()
            : [];
        $existing = get_posts(array_merge([
            'post_type'      => 'page',
            'post_status'    => 'any',
            'name'           => $slug,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ], $langArgs));
        $pageId = !empty($existing) ? (int) $existing[0] : 0;

        if ($pageId > 0) {
            $u = wp_update_post([
                'ID'          => $pageId,
                'post_title'  => $title,
                'post_status' => 'publish',
            ], true);
            if (is_wp_error($u)) {
                $results[] = [
                    'slug'    => $slug,
                    'status'  => 'error',
                    'message' => $u->get_error_message(),
                ];
                continue;
            }
            $action = 'updated';
        } else {
            $ins = wp_insert_post([
                'post_type'    => 'page',
                'post_status' => 'publish',
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_content' => '',
                'post_author'  => get_current_user_id() ?: 1,
            ], true);

            if (is_wp_error($ins)) {
                $results[] = [
                    'slug'    => $slug,
                    'status'  => 'error',
                    'message' => $ins->get_error_message(),
                ];
                continue;
            }

            $pageId = (int) $ins;
            $action = 'created';
        }

        update_post_meta((int) $pageId, '_wp_page_template', $template);

        if (\function_exists('nsc_seed_polylang_set_default_language_on_post')) {
            nsc_seed_polylang_set_default_language_on_post((int) $pageId);
        }
    }

    // Home page: clear previous components and seed via ACF so layout order and sub fields are stored correctly.
    if ($slug === 'home') {
        $components = getHomePageComponents();
        if ($contentTest) {
            $components = applyContentTest($components);
        }
        $seededPageComponents = $components;
        if (!$singleTarget) {
            nsc_seed_pages_persist_page_components((int) $pageId, $components);
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
    } elseif ($slug === 'about') {
        // About page: seed components matching frontend/build/about.html (Hero→Company Snapshot→Our Story→Leaders→Why Us→Technology Capabilities→Global Presence→Contact).
        $components = getAboutPageComponents();
        if ($contentTest) {
            $components = applyContentTest($components);
        }
        $seededPageComponents = $components;
        if (!$singleTarget) {
            nsc_seed_pages_persist_page_components((int) $pageId, $components);
        }
        $msg = 'page_id=' . $pageId . ', template=default, pageComponents cleared and set (About sections)';
        if ($contentTest) {
            $msg .= ', content_test=1 (all text set to "[test]")';
        }
        $results[] = [
            'slug'    => $slug,
            'status'  => $action,
            'message' => $msg,
        ];
    } elseif ($slug === 'ai') {
        // AI page: seed components matching frontend/build/ai.html (Hero→AiBanner→AiInfo→Timeline→AiImpact→CapabilitiesDetails→AiSecurity→Contact).
        $components = getAiPageComponents();
        if ($contentTest) {
            $components = applyContentTest($components);
        }
        $seededPageComponents = $components;
        if (!$singleTarget) {
            nsc_seed_pages_persist_page_components((int) $pageId, $components);
        }
        $msg = 'page_id=' . $pageId . ', template=default, pageComponents cleared and set (AI sections)';
        if ($contentTest) {
            $msg .= ', content_test=1 (all text set to "[test]")';
        }
        $results[] = [
            'slug'    => $slug,
            'status'  => $action,
            'message' => $msg,
        ];
    } elseif ($slug === 'our-services') {
        // Our Services page: seed components matching frontend/build/our-services.html (Hero + Our Services Details).
        $components = getOurServicesPageComponents();
        if ($contentTest) {
            $components = applyContentTest($components);
        }
        $seededPageComponents = $components;
        if (!$singleTarget) {
            nsc_seed_pages_persist_page_components((int) $pageId, $components);
        }
        $msg = 'page_id=' . $pageId . ', template=default, pageComponents cleared and set (Our Services: Hero + Services Details)';
        if ($contentTest) {
            $msg .= ', content_test=1 (all text set to "[test]")';
        }
        $results[] = [
            'slug'    => $slug,
            'status'  => $action,
            'message' => $msg,
        ];
    } elseif ($slug === 'technology-apabilities') {
        // Technology Capabilities page: seed components matching frontend/build/technology-apabilities.html (Hero + Technology Capability).
        $components = getOurCapabilitiesPageComponents();
        if ($contentTest) {
            $components = applyContentTest($components);
        }
        $seededPageComponents = $components;
        if (!$singleTarget) {
            nsc_seed_pages_persist_page_components((int) $pageId, $components);
        }
        $msg = 'page_id=' . $pageId . ', template=default, pageComponents cleared and set (Technology Capabilities: Hero + Technology Capability)';
        if ($contentTest) {
            $msg .= ', content_test=1 (all text set to "[test]")';
        }
        $results[] = [
            'slug'    => $slug,
            'status'  => $action,
            'message' => $msg,
        ];
    } elseif ($slug === 'contact') {
        // Contact page: seed components matching frontend/build/contact.html (Contact + Global Presence).
        $components = getContactPageComponents();
        if ($contentTest) {
            $components = applyContentTest($components);
        }
        $seededPageComponents = $components;
        if (!$singleTarget) {
            nsc_seed_pages_persist_page_components((int) $pageId, $components);
        }
        $msg = 'page_id=' . $pageId . ', template=default, pageComponents cleared and set (Contact: Contact Page + Global Presence)';
        if ($contentTest) {
            $msg .= ', content_test=1 (all text set to "[test]")';
        }
        $results[] = [
            'slug'    => $slug,
            'status'  => $action,
            'message' => $msg,
        ];
    } elseif ($slug === 'blogs') {
        // Blogs page: Hero (dark) + Blogs (Archive); Vue list loads from WP posts (run create-nsc-blog-posts.php to seed).
        $components = getBlogsPageComponents();
        if ($contentTest) {
            $components = applyContentTest($components);
        }
        $seededPageComponents = $components;
        if (!$singleTarget) {
            nsc_seed_pages_persist_page_components((int) $pageId, $components);
        }
        $msg = 'page_id=' . $pageId . ', template=default, pageComponents cleared and set (Blogs: Hero + Archive / Vue)';
        if ($contentTest) {
            $msg .= ', content_test=1 (all text set to "[test]")';
        }
        $results[] = [
            'slug'    => $slug,
            'status'  => $action,
            'message' => $msg,
        ];
    } elseif ($slug === 'career') {
        // Career page: Hero + We are NSC + Core values + Jobs archive (Vue; tabs from job_category, rows from CPT job).
        $components = getCareerPageComponents();
        if ($contentTest) {
            $components = applyContentTest($components);
        }
        $seededPageComponents = $components;
        if (!$singleTarget) {
            nsc_seed_pages_persist_page_components((int) $pageId, $components);
        }
        $msg = 'page_id=' . $pageId . ', template=default, pageComponents cleared and set (Career: Hero + We are NSC + Core values + Jobs archive / Vue)';
        if ($contentTest) {
            $msg .= ', content_test=1 (all text set to "[test]")';
        }
        $results[] = [
            'slug'    => $slug,
            'status'  => $action,
            'message' => $msg,
        ];
    } elseif ($slug === 'case-studies') {
        $components = getCaseStudiesPageComponents();
        if ($contentTest) {
            $components = applyContentTest($components);
        }
        $seededPageComponents = $components;
        if (!$singleTarget) {
            nsc_seed_pages_persist_page_components((int) $pageId, $components);
        }
        $msg = 'page_id=' . $pageId . ', template=default, pageComponents cleared and set (Case Studies: Hero + Archive / Vue from case_study CPT)';
        if ($contentTest) {
            $msg .= ', content_test=1 (all text set to "[test]")';
        }
        $results[] = [
            'slug'    => $slug,
            'status'  => $action,
            'message' => $msg,
        ];
    } elseif ($slug === 'privacy-policy' || $slug === 'cookies-policy' || $slug === 'terms-of-use') {
        $policyTitles = [
            'privacy-policy'  => 'Privacy Policy for NSC Software',
            'cookies-policy'   => 'Cookies Policy',
            'terms-of-use'     => 'Terms of Use',
        ];
        $contentPath = __DIR__ . '/policy-content/' . $slug . '.html';
        $contentHtml = is_file($contentPath) ? file_get_contents($contentPath) : '<p>Content not found. Add ' . $slug . '.html to policy-content/.</p>';
        $contentHtml = nsc_seed_policy_normalize_paragraph_pairs($contentHtml);
        $components = getPolicyPageComponents($policyTitles[ $slug ], $contentHtml);
        if ($contentTest) {
            $components = applyContentTest($components);
        }
        $seededPageComponents = $components;
        if (!$singleTarget) {
            nsc_seed_pages_persist_page_components((int) $pageId, $components);
        }
        $msg = 'page_id=' . $pageId . ', template=default, pageComponents set (Policy: ' . $policyTitles[ $slug ] . ')';
        if ($contentTest) {
            $msg .= ', content_test=1 (title prefixed with "[test]")';
        }
        $results[] = [
            'slug'    => $slug,
            'status'  => $action,
            'message' => $msg,
        ];
    } else {
        $msgElse = 'page_id=' . $pageId . ', template=' . $template;
        $results[] = [
            'slug'    => $slug,
            'status'  => $action,
            'message' => $msgElse,
        ];
    }

    if (function_exists('nsc_seed_should_run_translation_sync') && nsc_seed_should_run_translation_sync() && function_exists('nsc_seed_polylang_sync_page_translations')) {
        $syncBase = ($singleTarget && $canonicalId > 0) ? $canonicalId : $pageId;
        nsc_seed_polylang_sync_page_translations((int) $syncBase, $slug, $title, $template, $seededPageComponents);
    }

    $logPageId = $pageId;
    if ($singleTarget && $canonicalId > 0 && function_exists('nsc_seed_polylang_sync_target_slugs_for_request')) {
        $tlangs = nsc_seed_polylang_sync_target_slugs_for_request();
        $t0 = $tlangs[0] ?? '';
        if ($t0 !== '' && function_exists('pll_get_post')) {
            $tp = (int) pll_get_post($canonicalId, $t0);
            if ($tp > 0) {
                $logPageId = $tp;
            }
        }
    }
    $ri = count($results) - 1;
    if ($ri >= 0 && isset($results[$ri]['slug'], $results[$ri]['message']) && $results[$ri]['slug'] === $slug && is_string($results[$ri]['message'])) {
        $results[$ri]['message'] = (string) preg_replace('/\bpage_id=\d+/', 'page_id=' . $logPageId, $results[$ri]['message'], 1);
    }
}

// Set Home as front page when not a policies-only style run (URL or page_scope). Always use
// default-language home ID; skip when seed_lang targets one translation only.
if (!$skipHomeFrontPageSetup && !(function_exists('nsc_seed_is_single_target_language_run') && nsc_seed_is_single_target_language_run())) {
    $homeLang = function_exists('nsc_seed_polylang_default_lang_query_args') ? nsc_seed_polylang_default_lang_query_args() : [];
    $homeIds  = get_posts(array_merge([
        'post_type'      => 'page',
        'post_status'    => 'any',
        'name'           => 'home',
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ], $homeLang));
    if (!empty($homeIds)) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', (int) $homeIds[0]);
    }
}

header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html><head><meta charset="utf-8"><title>NSC Page Setup</title>';
echo '<style>body{font-family:Arial,sans-serif;padding:24px}table{border-collapse:collapse;width:100%;max-width:900px}th,td{border:1px solid #ddd;padding:8px}th{background:#f7f7f7;text-align:left}.ok{color:#0a7f2e}.error{color:#b00020}</style>';
echo '</head><body>';
echo '<h1>NSC Pages Setup</h1>';
echo '<p>Done. Use <code>page_scope</code> (with a slug or <code>policies</code>) or legacy <code>home_only=1</code>, <code>blogs_only=1</code>, etc. Add <code>content_test=1</code> for test prefixes. <code>seed_lang</code> / Polylang: same as before. Jobs: <code>create-nsc-job-posts.php?token=nsc-create-job-posts-2026</code>. Case studies: <code>create-nsc-case-study-posts.php?token=nsc-create-case-studies-2026</code>. Menus: <code>create-nsc-menus.php?token=nsc-create-menus-2026</code>.</p>';
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
