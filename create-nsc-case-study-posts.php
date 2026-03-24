<?php
declare(strict_types=1);

/**
 * Seed up to 30 case_study posts with ACF flexible field `caseStudyComponents`
 * (Hero, Instruction, Quote, Main content). Matches theme single-case_study + case-study-details layout.
 * Idempotent by post slug (updates if exists).
 *
 * Usage:
 *   http://localhost/nsc/create-nsc-case-study-posts.php?token=nsc-create-case-studies-2026
 */

$requiredToken = 'nsc-create-case-studies-2026';
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

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

/**
 * @return array<string, int> filename => attachment ID
 */
function nscCaseStudySeedGetImageIds(): array
{
    $buildUri = get_template_directory_uri() . '/frontend/build';
    $files    = [
        'blog1.png', 'blog2.png', 'blog3.png', 'blog4.png', 'blog5.png', 'blog6.png', 'blog7.png', 'blog8.png',
        'gallery-1.png', 'gallery-2.png', 'gallery-3.png',
        'logo-black.png',
        'hero-light-case-study.png',
    ];
    $map      = [];

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
        update_post_meta((int) $id, 'nsc_build_asset', $filename);

        return (int) $id;
    };

    foreach ($files as $f) {
        $map[$f] = $getOrCreateId($f);
    }

    return $map;
}

function nsc_case_study_seed_hash_u(string $s): int
{
    return (int) sprintf('%u', crc32($s));
}

function nsc_case_study_seed_ensure_term(string $taxonomy, string $name): int
{
    $name = trim($name);
    if ($name === '') {
        return 0;
    }
    $term = get_term_by('name', $name, $taxonomy);
    if ($term instanceof WP_Term) {
        return (int) $term->term_id;
    }
    $r = wp_insert_term($name, $taxonomy);
    if (is_wp_error($r)) {
        return 0;
    }

    return (int) $r['term_id'];
}

function nsc_case_study_seed_category_id(string $label): int
{
    $term = get_term_by('name', $label, 'case_study_category');
    if ($term instanceof WP_Term) {
        return (int) $term->term_id;
    }
    $term = get_term_by('slug', sanitize_title($label), 'case_study_category');
    if ($term instanceof WP_Term) {
        return (int) $term->term_id;
    }

    return 0;
}

/**
 * Unique business-requirement + result copy per seeded post (deterministic by index).
 *
 * @return array{requirement: string, result: string}
 */
function nsc_case_study_seed_instruction_pair_for_index(int $index, string $title, int $seed): array
{
    $pairs = [
        [
            'req' => sprintf('The client wanted to modernize the product experience around %s without pausing revenue-critical releases. Legacy systems, uneven monitoring, and unclear handoffs between vendors were slowing every change.', $title),
            'res' => sprintf('NSC stood up a cross-functional squad, introduced CI/CD pipelines with gated promotions, and shipped %s incrementally. Incident volume dropped and the client could plan releases with predictable lead times.', $title),
        ],
        [
            'req' => sprintf('Regulated workflows and audit trails had to be first-class for %s. The team needed a partner who could translate policy language into concrete software controls and evidence packs.', $title),
            'res' => sprintf('We embedded security reviews into the sprint backlog, added structured logging and immutable audit events, and validated %s against the client’s compliance checklist before go-live.', $title),
        ],
        [
            'req' => sprintf('Peak traffic spikes and seasonal campaigns threatened SLAs for %s. The business asked for a cost-aware architecture that could scale out quickly and scale back when demand cooled.', $title),
            'res' => sprintf('NSC modeled load patterns, introduced autoscaling policies with guardrails, and tuned databases for %s. Load tests showed headroom for planned campaigns without runaway cloud spend.', $title),
        ],
        [
            'req' => sprintf('Mobile users accounted for most sessions, but the first release of %s still depended on brittle web views. The client needed native-grade performance and offline-tolerant flows.', $title),
            'res' => sprintf('We refactored critical paths, reduced payload sizes, and shipped a staged rollout for %s with analytics on crash-free sessions. App store ratings and retention improved within two quarters.', $title),
        ],
        [
            'req' => sprintf('Data silos meant executives saw different numbers for the same KPI while building %s. The mandate was to unify reporting without breaking existing departmental tools.', $title),
            'res' => sprintf('NSC defined canonical data contracts, built a governed layer for %s metrics, and delivered dashboards with lineage notes. Teams could finally agree on the definition of “active user.”', $title),
        ],
        [
            'req' => sprintf('The client’s API partners needed stable versioning and clear deprecation windows for %s. Breaking changes had caused painful rollbacks and partner churn in the past.', $title),
            'res' => sprintf('We introduced semantic versioning, consumer-driven contract tests, and sunset timelines for %s. Partner integrations stopped failing silently on release day.', $title),
        ],
        [
            'req' => sprintf('Onboarding new engineers took months because knowledge lived in chat threads and one-off scripts. The goal for %s was sustainable ownership inside the client’s team.', $title),
            'res' => sprintf('NSC paired documentation with code, ran playbooks for %s operations, and transitioned to client-led ceremonies with a defined support window. Hiring velocity improved measurably.', $title),
        ],
        [
            'req' => sprintf('The client needed to integrate %s with SAP and a legacy CRM without a multi-year rewrite. Batch windows were tight and failures had to be recoverable.', $title),
            'res' => sprintf('We implemented idempotent jobs, dead-letter queues, and replay tooling for %s. Finance and operations gained confidence in nightly reconciliation outcomes.', $title),
        ],
        [
            'req' => sprintf('Fraud and abuse patterns were evolving faster than manual reviews for %s. The ask was to combine rules with ML-assisted scoring without blocking legitimate users.', $title),
            'res' => sprintf('NSC delivered a layered detection stack for %s with explainable flags, human review queues, and continuous evaluation. False positives fell while catch rates stayed steady.', $title),
        ],
        [
            'req' => sprintf('The product roadmap for %s demanded experimentation: feature flags, cohort analysis, and safe rollouts to subsets of users across regions.', $title),
            'res' => sprintf('We wired feature flags, event tracking, and rollback switches for %s. Product could run A/B tests without waiting for a full redeploy.', $title),
        ],
        [
            'req' => sprintf('Accessibility and localization were non-negotiable for %s as the client expanded into new markets. Screen reader support and RTL layouts had to be validated continuously.', $title),
            'res' => sprintf('NSC added automated accessibility checks in CI, fixed keyboard flows for %s, and established translation workflows. Launch readiness reviews passed with fewer late surprises.', $title),
        ],
        [
            'req' => sprintf('The client wanted to sunset a monolith gradually while shipping %s with new microservices. The strangler pattern had to be coordinated across two teams.', $title),
            'res' => sprintf('We mapped bounded contexts, extracted the first service behind facades, and shipped %s with parallel runs until parity was proven. Risk stayed in the open with clear milestones.', $title),
        ],
        [
            'req' => sprintf('Video and real-time signaling were new territory for the client’s stack. %s required WebRTC expertise, TURN/STUN hardening, and graceful degradation on poor networks.', $title),
            'res' => sprintf('NSC implemented resilient media paths for %s, added telemetry for connection quality, and tuned bitrate ladders. Users saw fewer dropped calls during peak hours.', $title),
        ],
        [
            'req' => sprintf('The client needed to pass SOC-style readiness reviews for %s while keeping velocity. Evidence collection and access reviews could not be a last-minute scramble.', $title),
            'res' => sprintf('We aligned controls to stories, automated evidence exports for %s, and rehearsed access reviews quarterly. Auditors received clear narratives tied to actual changes.', $title),
        ],
        [
            'req' => sprintf('IoT devices produced noisy telemetry; the business wanted %s to surface actionable alerts without drowning operators in false positives.', $title),
            'res' => sprintf('NSC built filtering pipelines, anomaly thresholds, and escalation paths for %s. Mean time to detect improved while alert fatigue dropped.', $title),
        ],
        [
            'req' => sprintf('The client’s brand team wanted %s to match a refreshed design system without rebuilding every screen at once. Tokenized styles and rollout sequencing mattered.', $title),
            'res' => sprintf('We introduced design tokens, migrated high-traffic screens first, and shipped %s with visual regression tests. Designers and developers shared the same source of truth.', $title),
        ],
        [
            'req' => sprintf('Payments reconciliation for %s involved multiple PSPs and currencies. Discrepancies had to be traceable to a single transaction id.', $title),
            'res' => sprintf('NSC standardized reconciliation jobs for %s, added idempotent settlement writes, and dashboards for finance. Month-end close shortened materially.', $title),
        ],
        [
            'req' => sprintf('The client needed to harden %s against supply-chain attacks and dependency drift. SBOM visibility and upgrade cadence were new requirements from leadership.', $title),
            'res' => sprintf('We automated dependency scanning, pinned releases for %s, and introduced a monthly dependency review. Critical CVEs had owners and SLAs.', $title),
        ],
        [
            'req' => sprintf('Search quality for %s lagged behind competitors. The team wanted relevance tuning, synonym support, and analytics on zero-result queries.', $title),
            'res' => sprintf('NSC improved indexing, added ranking experiments for %s, and built a feedback loop from search logs. Conversion on search-driven journeys improved.', $title),
        ],
        [
            'req' => sprintf('The client needed to migrate %s from self-managed clusters to a managed platform without multi-day outages. Blue/green and rollback drills were mandatory.', $title),
            'res' => sprintf('We executed migration waves for %s, rehearsed failovers, and validated data integrity with checksum jobs. The cutover finished within the planned window.', $title),
        ],
        [
            'req' => sprintf('Support teams were overwhelmed by repetitive tickets tied to %s. The business wanted self-service diagnostics and in-app guidance.', $title),
            'res' => sprintf('NSC shipped guided troubleshooting flows for %s, embedded contextual help, and surfaced health checks to users. Ticket volume dropped on the highest-frequency issues.', $title),
        ],
        [
            'req' => sprintf('The client needed to integrate %s with a partner ecosystem via webhooks. Signature verification, retries, and idempotency keys were required from day one.', $title),
            'res' => sprintf('We implemented signed webhooks, exponential backoff, and replay protection for %s. Partners received predictable delivery semantics and fewer duplicate events.', $title),
        ],
        [
            'req' => sprintf('Content editors for %s needed a safer CMS workflow—preview, staging, and scheduled publishes without granting production DB access.', $title),
            'res' => sprintf('NSC separated content pipelines for %s, added preview environments, and role-based approvals. Publishing mistakes became rare and reversible.', $title),
        ],
        [
            'req' => sprintf('The client wanted to reduce cloud spend for %s without sacrificing reliability. Rightsizing, idle resource cleanup, and tagging discipline were all part of the mandate.', $title),
            'res' => sprintf('We implemented tagging budgets, scheduled non-prod shutdowns, and rightsized workloads for %s. Monthly bills fell while SLOs stayed green.', $title),
        ],
        [
            'req' => sprintf('Geographic expansion meant data residency constraints for %s. The architecture had to route data correctly and stay explainable to legal teams.', $title),
            'res' => sprintf('NSC designed region-aware routing, encryption standards, and data maps for %s. Legal sign-off came faster because controls were explicit in design docs.', $title),
        ],
        [
            'req' => sprintf('The client needed to unify identity for %s across SSO, social, and enterprise IdPs. Session security and step-up auth were part of the scope.', $title),
            'res' => sprintf('We integrated OAuth/OIDC flows, hardened session handling for %s, and added risk-based challenges. Support tickets dropped for login edge cases.', $title),
        ],
        [
            'req' => sprintf('Batch analytics jobs for %s were missing SLAs. Queues backed up, and downstream reports arrived late for business decisions.', $title),
            'res' => sprintf('NSC optimized pipelines for %s, partitioned workloads, and added alerts on queue lag. Report freshness returned to agreed windows.', $title),
        ],
        [
            'req' => sprintf('The client wanted to expose %s capabilities through a public API program. Rate limits, keys, and developer documentation had to be production-grade.', $title),
            'res' => sprintf('We shipped a developer portal, API keys with scopes, and usage dashboards for %s. External teams integrated faster with fewer support escalations.', $title),
        ],
        [
            'req' => sprintf('Quality for %s suffered from flaky CI and environment drift. The team needed deterministic builds and parity between staging and production.', $title),
            'res' => sprintf('NSC containerized services for %s, standardized environments, and fixed flaky tests. Lead time to production improved with fewer hotfixes.', $title),
        ],
        [
            'req' => sprintf('The client needed to integrate %s with a data warehouse for BI while keeping operational databases performant. CDC and schema evolution had to be planned.', $title),
            'res' => sprintf('We implemented change-data capture, contracts for %s events, and monitoring on ingestion lag. Analysts trusted dashboards without hammering OLTP.', $title),
        ],
        [
            'req' => sprintf('The roadmap for %s included offline-first use cases for field teams. Conflict resolution and sync strategies could not be an afterthought.', $title),
            'res' => sprintf('NSC designed sync strategies for %s, handled merge rules, and tested poor-network scenarios. Field adoption rose after the first pilot region.', $title),
        ],
    ];

    $n = count($pairs);
    $i = ($index - 1) % $n;
    // Optional: mix in seed so adjacent titles do not always map to adjacent templates when reordered.
    $i = ($i + ($seed % 7)) % $n;

    $p = $pairs[$i];

    return [
        'requirement' => $p['req'],
        'result'        => $p['res'],
    ];
}

/**
 * @param array{title: string, description: string, category: string} $study
 * @param array<string, int> $imageMap
 *
 * @return list<array<string, mixed>>
 */
function nsc_case_study_seed_component_rows(int $index, array $study, array $imageMap): array
{
    $title = $study['title'];
    $seed  = nsc_case_study_seed_hash_u($title);

    $intro = '<p>' . esc_html($study['description']) . '</p>';

    $instructionPair = nsc_case_study_seed_instruction_pair_for_index($index, $title, $seed);
    $biz = $instructionPair['requirement'];
    $res = $instructionPair['result'];

    $quotes = [
        ['We partnered with NSC to accelerate delivery. Communication was clear, engineering depth was strong, and they treated our outcomes as their own.', 'Alex Morgan', 'VP Engineering / Northbridge Labs'],
        ['The team shipped a complex integration on a tight timeline without sacrificing quality. We would work with NSC again.', 'Priya Shah', 'CTO / HelioPay'],
        ['NSC engineers integrated seamlessly with our product squad. Standups felt like one team, not a vendor handoff.', 'Daniel Köhler', 'Product Director / KiteWorks'],
        ['Outstanding collaboration across time zones. Documentation and handover exceeded what we typically see from partners.', 'Sarah Lin', 'Head of Platform / BlueRiver'],
        ['They brought structure to our backlog and turned ambiguous requirements into shippable increments every sprint.', 'Marco Rossi', 'COO / ItaliaRetail'],
        ['Reliability improved measurably after NSC helped harden our release process and observability baseline.', 'James Wright', 'Director of Engineering / Orbital'],
        ['Pragmatic architecture reviews kept us from overbuilding while still leaving room to scale.', 'Elena Vogt', 'Chief Architect / FernStack'],
        ['NSC’s team asked the right questions early, which saved us rework later in the program.', 'Hiro Tanaka', 'Program Lead / NamiTech'],
        ['We valued their transparency on risks and trade-offs—no surprises at go-live.', 'Olivia Bennett', 'COO / ClearPath'],
        ['From discovery to production support, NSC felt like an extension of our internal org.', 'Thomas Meyer', 'Head of IT / RheinWorks'],
        ['Strong automated testing discipline reduced regressions as we increased release frequency.', 'Anika Desai', 'VP Product / Monsoon'],
        ['They helped us untangle legacy constraints without a big-bang rewrite.', 'Chris O’Neill', 'CTO / HarborOne'],
        ['Security and compliance checkpoints were baked into delivery, not bolted on at the end.', 'Fatima Al-Rashid', 'CISO / GulfNet'],
        ['Performance work was grounded in real user journeys—not vanity benchmarks.', 'Kevin Ng', 'Lead Engineer / Streamline'],
        ['NSC’s Vietnam delivery hub gave us velocity with clear governance and reporting.', 'Laura Schmidt', 'PMO / EuroLink'],
        ['Excellent stakeholder facilitation: decisions were captured and followed through.', 'Michael Brooks', 'Product Owner / RidgeCo'],
        ['They helped us define SLIs/SLOs that the business actually understands.', 'Rachel Adams', 'SRE Manager / Skyline'],
        ['Our mobile and backend teams finally aligned on API contracts—fewer integration fires.', 'Diego Alvarez', 'Engineering Manager / Andes'],
        ['NSC brought mature DevOps practices without forcing a one-size-fits-all toolchain.', 'Yuki Sato', 'Head of Cloud / Zenith'],
        ['We shipped a regulated feature set with audit-friendly logging and traceability.', 'Nina Petrov', 'Compliance Lead / BalticPay'],
        ['Their code reviews raised the bar for our internal developers too.', 'Samuel Okonkwo', 'Tech Lead / LagosSoft'],
        ['NSC helped us prioritize debt paydown alongside features—velocity did not collapse.', 'Emily Carter', 'Director of Delivery / Northwind'],
        ['Great at turning fuzzy “AI” ideas into a concrete roadmap with realistic milestones.', 'Vikram Singh', 'Innovation Lead / Indus'],
        ['Support during hypercare was calm and systematic—exactly what we needed.', 'Grace O’Connor', 'Operations / Emerald'],
        ['They documented runbooks that our on-call team still uses months later.', 'Ben Harper', 'SRE / Copperfield'],
        ['NSC understood our enterprise procurement constraints and still kept momentum.', 'Julia Martins', 'Vendor Management / Atlas'],
        ['We cut cloud spend after their cost review—without sacrificing resilience.', 'Paul Richter', 'FinOps / Klaro'],
        ['The squad’s UX sensitivity showed up in edge cases, not only happy paths.', 'Amélie Dupont', 'Design Lead / Seine'],
        ['NSC helped us migrate services with minimal downtime and clear rollback plans.', 'Robert Kim', 'Platform Lead / Pacifica'],
        ['A partner that ships—and teaches—so we can own the system long term.', 'Zoe Mitchell', 'CEO / Deltablot'],
    ];
    $qi    = $quotes[$index % count($quotes)];

    $blockSets = [
        [
            ['blockTitle' => 'Key facts', 'blockLines' => "Long-term partnership focused on scalable delivery and continuous improvement.\nCross-functional collaboration between stakeholders, product, and engineering.\nTransparent reporting and milestone reviews throughout the engagement."],
            ['blockTitle' => 'Platform & integration', 'blockLines' => "API-first integrations with stable contracts and backward-compatible releases.\nOperational runbooks and on-call alignment with client processes.\nSecurity reviews folded into the definition of done for critical paths."],
            ['blockTitle' => 'Outcomes', 'blockLines' => "Faster release cadence with fewer production incidents on core journeys.\nLower mean time to recovery through shared dashboards and playbooks.\nClear roadmap for the next phase of automation and analytics."],
        ],
        [
            ['blockTitle' => 'Engagement model', 'blockLines' => "Embedded squads with shared rituals and a single backlog.\nArchitecture spikes early to de-risk the highest-unknown integrations.\nQuarterly planning that balances maintenance with new capabilities."],
            ['blockTitle' => 'Data & quality', 'blockLines' => "Automated tests on critical business rules and regression suites in CI.\nData validation hooks at ingestion to protect downstream consumers.\nPerformance budgets for APIs that customer-facing apps depend on."],
            ['blockTitle' => 'Cloud & operations', 'blockLines' => "Infrastructure as code with peer-reviewed changes.\nMonitoring and alerting mapped to customer-visible journeys.\nCost-aware scaling patterns for variable load."],
        ],
    ];
    $overviewBlocks = $blockSets[$index % count($blockSets)];

    $durations = [
        '12+ FTE, 2019–2024',
        '25 FTE, phased delivery',
        '80+ FTE, 2012–2019',
        '8–12 engineers, 14 months',
        'Core team 6 FTE, ongoing',
        'Discovery + build, 9 months',
    ];
    $sizeDuration = $durations[$seed % count($durations)];

    $galleryIds = [];
    foreach (['gallery-1.png', 'gallery-2.png', 'gallery-3.png'] as $gf) {
        $gid = $imageMap[$gf] ?? 0;
        if ($gid > 0) {
            $galleryIds[] = $gid;
        }
    }
    if (count($galleryIds) === 3 && ($seed % 5 === 0)) {
        $galleryIds = array_slice($galleryIds, 0, 2);
    }

    $logoId = $imageMap['logo-black.png'] ?? 0;

    $hero = [
        'acf_fc_layout'   => 'nscCaseStudyHero',
        'intro'           => $intro,
        'customerLabel'   => 'Customer:',
        'customerTagline' => 'AI Driven Software Development',
    ];
    if ($logoId > 0) {
        $hero['customerLogo'] = $logoId;
    }
    $heroBgId = $imageMap['hero-light-case-study.png'] ?? 0;
    if ($heroBgId > 0) {
        $hero['backgroundDesktop'] = $heroBgId;
        $hero['backgroundMobile']  = $heroBgId;
    }

    $instruction = [
        'acf_fc_layout'      => 'nscCaseStudyInstruction',
        'requirementHeading' => 'Business Requirement:',
        'requirementBody'    => '<p>' . esc_html($biz) . '</p>',
        'resultHeading'      => 'Result:',
        'resultBody'         => '<p>' . esc_html($res) . '</p>',
    ];

    $quote = [
        'acf_fc_layout' => 'nscCaseStudyQuote',
        'quoteText'     => $qi[0],
        'citeName'      => $qi[1],
        'citeRole'      => $qi[2],
    ];

    $main = [
        'acf_fc_layout'         => 'nscCaseStudyMain',
        'collaborationHeading'  => 'Collaboration overview',
        'overviewBlocks'        => $overviewBlocks,
        'sizeDuration'          => $sizeDuration,
        'gallery'               => $galleryIds,
    ];

    return [$hero, $instruction, $quote, $main];
}

$caseStudies = [
    ['title' => 'Smart Energy Monitor', 'description' => 'Tracks real-time electricity usage of home appliances and sends alerts when consumption exceeds thresholds.', 'category' => 'Technology'],
    ['title' => 'Automated Plant Care System', 'description' => 'Uses soil moisture and light sensors to water plants automatically and notify users via app.', 'category' => 'Fintech'],
    ['title' => 'Smart Parking Availability System', 'description' => 'Detects empty parking spots using sensors and shows availability on a live map.', 'category' => 'Blockchain'],
    ['title' => 'Home Air Quality Monitor', 'description' => 'Measures temperature, humidity, and air pollution levels, then alerts users when air quality drops.', 'category' => 'Web 3'],
    ['title' => 'Industrial Equipment Health Tracker', 'description' => 'Monitors vibration and temperature of machines to predict failures before breakdown occurs.', 'category' => 'Lifestyle'],
    ['title' => 'Smart Water Leak Detection', 'description' => 'Detects leaks in pipelines or homes and instantly sends alerts to prevent water damage.', 'category' => 'Lifestyle'],
    ['title' => 'Digital Wallet Platform', 'description' => 'Secure mobile payment solution with multi-currency support and real-time transaction tracking.', 'category' => 'Fintech'],
    ['title' => 'Blockchain Supply Chain Tracker', 'description' => 'Transparent tracking system for supply chains using distributed ledger technology.', 'category' => 'Blockchain'],
    ['title' => 'AI-Powered Learning Platform', 'description' => 'Personalized education platform that adapts to student learning patterns and progress.', 'category' => 'Education'],
    ['title' => 'SaaS Project Management Tool', 'description' => 'Collaborative project management platform with real-time updates and team communication.', 'category' => 'Saas'],
    ['title' => 'Smart Home Automation System', 'description' => 'Integrated IoT solution for controlling lights, temperature, and security systems.', 'category' => 'Technology'],
    ['title' => 'Fitness Tracking Mobile App', 'description' => 'Comprehensive fitness app with workout plans, nutrition tracking, and progress analytics.', 'category' => 'Lifestyle'],
    ['title' => 'Cryptocurrency Exchange Platform', 'description' => 'Secure trading platform for cryptocurrencies with advanced charting and portfolio management.', 'category' => 'Blockchain'],
    ['title' => 'NFT Marketplace', 'description' => 'Decentralized marketplace for buying and selling digital art and collectibles.', 'category' => 'Web 3'],
    ['title' => 'Online Course Platform', 'description' => 'Interactive learning platform with video courses, quizzes, and certification programs.', 'category' => 'Education'],
    ['title' => 'CRM Software Solution', 'description' => 'Customer relationship management system with sales pipeline and analytics dashboard.', 'category' => 'Saas'],
    ['title' => 'Payment Gateway Integration', 'description' => 'Secure payment processing system with support for multiple payment methods.', 'category' => 'Fintech'],
    ['title' => 'Smart City Traffic Management', 'description' => 'AI-powered traffic optimization system for reducing congestion and improving flow.', 'category' => 'Technology'],
    ['title' => 'DeFi Lending Platform', 'description' => 'Decentralized finance platform for peer-to-peer lending and borrowing.', 'category' => 'Blockchain'],
    ['title' => 'Virtual Reality Training Simulator', 'description' => 'Immersive VR training platform for professional skill development.', 'category' => 'Education'],
    ['title' => 'Cloud-Based Accounting Software', 'description' => 'Comprehensive accounting solution with invoicing, expense tracking, and reporting.', 'category' => 'Saas'],
    ['title' => 'Meditation and Wellness App', 'description' => 'Guided meditation app with sleep stories and mindfulness exercises.', 'category' => 'Lifestyle'],
    ['title' => 'Smart Grid Energy Management', 'description' => 'Intelligent energy distribution system for optimizing power consumption.', 'category' => 'Technology'],
    ['title' => 'Peer-to-Peer Payment App', 'description' => 'Mobile app for instant money transfers between users with low fees.', 'category' => 'Fintech'],
    ['title' => 'Smart Contract Platform', 'description' => 'Platform for creating and deploying automated smart contracts.', 'category' => 'Blockchain'],
    ['title' => 'Metaverse Virtual Events', 'description' => 'Virtual event platform for hosting conferences and meetings in 3D spaces.', 'category' => 'Web 3'],
    ['title' => 'Language Learning Platform', 'description' => 'Interactive language learning app with speech recognition and gamification.', 'category' => 'Education'],
    ['title' => 'Team Collaboration Tool', 'description' => 'Real-time collaboration platform with chat, video calls, and file sharing.', 'category' => 'Saas'],
    ['title' => 'IoT Fleet Management System', 'description' => 'Real-time tracking and management system for vehicle fleets.', 'category' => 'Technology'],
    ['title' => 'Personal Finance Manager', 'description' => 'Budgeting and expense tracking app with financial insights and recommendations.', 'category' => 'Fintech'],
];

$poolIndustry = ['EdTech', 'FinTech', 'Healthcare', 'Manufacturing', 'Retail', 'Logistics', 'Energy'];
$poolCountry  = ['USA', 'Vietnam', 'Germany', 'Australia', 'Singapore', 'United Kingdom'];
$poolSolution = ['Cloud solutions', 'BI and big data', 'Mobile apps', 'API platform', 'Advertising solution', 'IoT platform'];
$poolService  = ['CTO services & tech reviews', 'Software product management', 'Software testing', 'Support & maintenance', 'Dedicated team'];
$poolTech     = ['AWS', 'Kubernetes', 'Docker', 'PostgreSQL', 'React', 'Java', 'Kafka', 'Terraform', 'MongoDB', 'Spark', 'Angular', 'Redis'];

$imageMap = nscCaseStudySeedGetImageIds();
$blogFiles = ['blog1.png', 'blog2.png', 'blog3.png', 'blog4.png', 'blog5.png', 'blog6.png', 'blog7.png', 'blog8.png'];

$results = [];
$i       = 0;

foreach ($caseStudies as $study) {
    $i++;
    $title = $study['title'];
    $slug  = sanitize_title($title);

    $existing = get_posts([
        'post_type'      => 'case_study',
        'post_status'    => 'any',
        'name'           => $slug,
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ]);
    $postId = !empty($existing) ? (int) $existing[0] : 0;

    $components = nsc_case_study_seed_component_rows($i, $study, $imageMap);

    $postarr = [
        'post_title'   => $title,
        'post_name'    => $slug,
        'post_content' => '',
        'post_excerpt' => $study['description'],
        'post_status'  => 'publish',
        'post_type'    => 'case_study',
        'post_author'  => get_current_user_id() ?: 1,
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

    if (function_exists('update_field')) {
        update_field('caseStudyComponents', $components, $postId);
    } else {
        update_post_meta($postId, 'caseStudyComponents', $components);
    }

    $imgIdx  = ($i * 5 + 2) % count($blogFiles);
    $imgFile = $blogFiles[$imgIdx];
    $thumbId = $imageMap[$imgFile] ?? 0;
    if ($thumbId > 0) {
        set_post_thumbnail($postId, $thumbId);
    }

    $catId = nsc_case_study_seed_category_id($study['category']);
    if ($catId > 0) {
        wp_set_object_terms($postId, [$catId], 'case_study_category', false);
    }

    $seed = nsc_case_study_seed_hash_u($title);

    $industryIds = [];
    for ($k = 0; $k < 2; $k++) {
        $name = $poolIndustry[($seed + $k * 7) % count($poolIndustry)];
        $tid  = nsc_case_study_seed_ensure_term('case_study_industry', $name);
        if ($tid > 0) {
            $industryIds[] = $tid;
        }
    }
    $countryId = nsc_case_study_seed_ensure_term('case_study_country', $poolCountry[$seed % count($poolCountry)]);
    if ($countryId === 0) {
        $countryId = nsc_case_study_seed_ensure_term('case_study_country', 'USA');
    }
    $solIds = [];
    for ($k = 0; $k < 3; $k++) {
        $tid = nsc_case_study_seed_ensure_term('case_study_solution', $poolSolution[($seed + $k) % count($poolSolution)]);
        if ($tid > 0) {
            $solIds[] = $tid;
        }
    }
    $svcIds = [];
    for ($k = 0; $k < 3; $k++) {
        $tid = nsc_case_study_seed_ensure_term('case_study_service', $poolService[($seed + $k * 2) % count($poolService)]);
        if ($tid > 0) {
            $svcIds[] = $tid;
        }
    }
    $techIds = [];
    for ($k = 0; $k < 8; $k++) {
        $tid = nsc_case_study_seed_ensure_term('case_study_technology', $poolTech[($seed + $k * 3) % count($poolTech)]);
        if ($tid > 0) {
            $techIds[] = $tid;
        }
    }

    if (!empty($industryIds)) {
        wp_set_object_terms($postId, array_values(array_unique($industryIds)), 'case_study_industry', false);
    }
    if ($countryId > 0) {
        wp_set_object_terms($postId, [$countryId], 'case_study_country', false);
    }
    if (!empty($solIds)) {
        wp_set_object_terms($postId, array_values(array_unique($solIds)), 'case_study_solution', false);
    }
    if (!empty($svcIds)) {
        wp_set_object_terms($postId, array_values(array_unique($svcIds)), 'case_study_service', false);
    }
    if (!empty($techIds)) {
        wp_set_object_terms($postId, array_values(array_unique($techIds)), 'case_study_technology', false);
    }

    $results[] = [
        'slug'    => $slug,
        'status'  => $postId && !empty($existing) ? 'updated' : 'created',
        'message' => 'post_id=' . $postId . ', components=4, cat=' . $study['category'],
    ];
}

header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html><head><meta charset="utf-8"><title>NSC Case Studies Seed</title>';
echo '<style>body{font-family:Arial,sans-serif;padding:24px}table{border-collapse:collapse;width:100%;max-width:1100px}th,td{border:1px solid #ddd;padding:8px;font-size:13px}th{background:#f7f7f7;text-align:left}.ok{color:#0a7f2e}.error{color:#b00020}</style>';
echo '</head><body><h1>Case studies (case_study)</h1>';
echo '<p>Seeded or updated ' . count($results) . ' posts. ACF flexible field <code>caseStudyComponents</code> (Hero, Instruction, Quote, Main). Taxonomies + featured image. Re-save in WP if ACF field keys need sync.</p>';
echo '<table><thead><tr><th>Slug</th><th>Status</th><th>Details</th></tr></thead><tbody>';
foreach ($results as $row) {
    $cls = $row['status'] === 'error' ? 'error' : 'ok';
    echo '<tr><td>' . esc_html($row['slug']) . '</td><td class="' . esc_attr($cls) . '">' . esc_html($row['status']) . '</td><td>' . esc_html($row['message']) . '</td></tr>';
}
echo '</tbody></table></body></html>';
