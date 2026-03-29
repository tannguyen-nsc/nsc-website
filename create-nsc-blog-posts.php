<?php
declare(strict_types=1);

/**
 * Seed ~30 blog posts with HTML content, featured image, categories (Technology / Cultures),
 * sidebar fields (featured flag, related links). Idempotent by post slug (updates if exists).
 *
 * Prerequisites: run global options once so categories Technology & Cultures exist
 * (or this script assigns by slug and creates terms if missing).
 *
 * Usage:
 *   http://localhost/nsc/create-nsc-blog-posts.php?token=nsc-create-blog-posts-2026
 *   Optional seed_lang={slug}|all for Polylang-linked posts. Omit seed_lang for default-language only; seed_lang=all for every non-default language. (lang) lowercase prefix without Google API key; legacy [LANG] stripped when re-seeding.
 */

$requiredToken = 'nsc-create-blog-posts-2026';
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

$nscSeedPolylang = get_template_directory() . '/inc/nscSeedPolylang.php';
if (is_readable($nscSeedPolylang)) {
    require_once $nscSeedPolylang;
}

if (function_exists('nsc_seed_bootstrap_acf_polylang_default_language')) {
    nsc_seed_bootstrap_acf_polylang_default_language();
}

$baseUrl = home_url('/');

/**
 * @return array<int, int> filename => attachment ID
 */
function nscBlogSeedGetImageIds(): array
{
    $buildUri = get_template_directory_uri() . '/frontend/build';
    $files    = ['blog1.png', 'blog2.png', 'blog3.png', 'blog4.png', 'blog5.png', 'blog6.png', 'blog7.png', 'blog8.png'];
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

/**
 * @return array{technology: int, cultures: int}
 */
function nscBlogSeedEnsureCategories(): array
{
    $out = ['technology' => 0, 'cultures' => 0];
    foreach (['technology' => 'Technology', 'cultures' => 'Cultures'] as $slug => $name) {
        $term = get_term_by('slug', $slug, 'category');
        if ($term instanceof WP_Term) {
            $out[$slug] = (int) $term->term_id;
            continue;
        }

        $r = wp_insert_term($name, 'category', ['slug' => $slug]);
        if (!is_wp_error($r)) {
            $out[$slug] = (int) $r['term_id'];
        }
    }

    return $out;
}

function nscBlogSeedRelatedPool(string $baseUrl): array
{
    return [
        ['linkLabel' => 'NSC Home', 'linkUrl' => $baseUrl, 'openInNewTab' => 0],
        ['linkLabel' => 'About Us', 'linkUrl' => $baseUrl . 'about/', 'openInNewTab' => 0],
        ['linkLabel' => 'Our Services', 'linkUrl' => $baseUrl . 'our-services/', 'openInNewTab' => 0],
        ['linkLabel' => 'Technology Capabilities', 'linkUrl' => $baseUrl . 'technology-capabilities/', 'openInNewTab' => 0],
        ['linkLabel' => 'Contact', 'linkUrl' => $baseUrl . 'contact/', 'openInNewTab' => 0],
        ['linkLabel' => 'Careers', 'linkUrl' => $baseUrl . 'career/', 'openInNewTab' => 0],
        ['linkLabel' => 'Blog archive', 'linkUrl' => $baseUrl . 'blogs/', 'openInNewTab' => 0],
        ['linkLabel' => 'NSC on LinkedIn', 'linkUrl' => 'https://www.linkedin.com/company/nscsoftware/', 'openInNewTab' => 1],
    ];
}

$titles = [
    'Agile Mindset in Global Software Delivery',
    'Building Scalable Software for B2B Enterprises',
    'The Future of AI-Driven Quality Assurance',
    'Cultural Agility: Vietnam Tech Talent Goes Global',
    'Machine Learning Model Deployment at Scale',
    'Sustainable Work Practices for Remote Teams',
    'API Gateway Patterns for Microservices',
    'Community Tech Events That Matter',
    'Data Pipeline Architecture for Analytics',
    'Employee Wellness Initiatives That Stick',
    'Infrastructure as Code with Confidence',
    'Company Anniversary: Lessons from Growth',
    'Kubernetes Operations for Small Teams',
    'Mentoring Junior Engineers in Distributed Teams',
    'Security Reviews Without Slowing Delivery',
    'Design Systems That Scale Across Products',
    'Customer Feedback Loops in Product Teams',
    'Open Source Contributions as Culture',
    'Technical Debt: When to Pay It Down',
    'Observability Beyond Logs and Metrics',
    'Cross-Functional Teams in Enterprise IT',
    'Documentation Developers Actually Read',
    'Performance Budgets for Web Applications',
    'Accessibility as a Default, Not an Add-on',
    'Release Trains vs Continuous Delivery',
    'Incident Response Playbooks That Work',
    'Pair Programming in Hybrid Offices',
    'Cost Optimization in Cloud-Native Stacks',
    'Engineering Leadership Without Micromanagement',
    'Celebrating Wins: Team Rituals at NSC',
];

/**
 * Unique copy per seeded post so excerpts and body text differ in the blog list and single views.
 *
 * @return array{p1: string, p2: string, p3: string, bullets: list<string>}
 */
function nsc_blog_seed_block_for_index(int $i): array
{
    $blocks = [
        1 => [
            'p1' => 'Most agile rollouts stall when teams copy ceremonies without shortening the path from customer signal to shipped software. Geography amplifies that gap when product owners sit six time zones away from the engineers who implement.',
            'p2' => 'We have seen the strongest gains when leadership funds small, cross-site product triads—design, engineering, and domain expertise—with a shared definition of done that includes production telemetry, not just story acceptance.',
            'p3' => 'NSC pairs Vietnamese delivery squads with client stakeholders abroad using thin slices of value every sprint, so feedback arrives while context is still fresh rather than after a month-long UAT cycle.',
            'bullets' => ['Instrument releases before expanding scope', 'Keep retros focused on flow, not blame', 'Rotate facilitators so every site owns improvement'],
        ],
        2 => [
            'p1' => 'B2B buyers expect consumer-grade reliability even when workflows involve compliance, ERP handoffs, and legacy mainframes. Scaling in that world is less about raw headcount and more about boundaries between services.',
            'p2' => 'Strangler patterns, bulkheads, and explicit SLAs between domains let you grow transaction volume without turning every hotfix into a coordinated outage. The hard part is saying no to shared databases that couple teams.',
            'p3' => 'Our enterprise work often starts with a map of data ownership and a pragmatic API layer so new features do not inherit ten years of accidental coupling.',
            'bullets' => ['Name owners for each bounded context', 'Prefer async contracts at integration edges', 'Load-test the paths money actually travels'],
        ],
        3 => [
            'p1' => 'AI-assisted testing is shifting from novelty demos to pipelines that generate synthetic journeys and surface flaky suites before humans spend a day debugging them.',
            'p2' => 'The risk is treating generated checks as ground truth. Teams still need oracles, sampling strategies, and human review for regulated industries where an incorrect green build is worse than a slow one.',
            'p3' => 'We combine model-generated cases with contract tests on critical APIs so speed and safety move together instead of trading off.',
            'bullets' => ['Version training data like code', 'Keep humans in the loop for high-risk flows', 'Measure escaped defects, not only cycle time'],
        ],
        4 => [
            'p1' => 'Hanoi and Ho Chi Minh City engineers routinely collaborate with Dallas, Frankfurt, and Sydney on the same backlog. Cultural agility means more than English fluency—it is shared context about holidays, decision styles, and how conflict is expressed.',
            'p2' => 'Simple rituals help: rotating meeting hosts, written decision logs, and explicit “interruptible hours” so no single region carries the entire burden of late-night calls.',
            'p3' => 'NSC invests in bilingual leads who can translate both language and intent when requirements arrive as half-finished slides from a steering committee.',
            'bullets' => ['Publish a team working agreement', 'Celebrate local holidays in calendars', 'Prefer written summaries after noisy calls'],
        ],
        5 => [
            'p1' => 'Shipping a notebook model to production is a different discipline than winning a Kaggle medal. You need reproducible environments, drift monitors, and rollback paths when inference latency spikes.',
            'p2' => 'Feature stores, shadow deployments, and canary releases reduce the drama of promotion day. Without them, data scientists and SREs argue in circles about whose dashboard is “more right.”',
            'p3' => 'We help clients package models as versioned artifacts with the same rigor as application binaries, including signed images and automated promotion gates.',
            'bullets' => ['Track data and model versions together', 'Define SLOs for p95 latency upfront', 'Practice rollbacks quarterly'],
        ],
        6 => [
            'p1' => 'Remote-first fatigue shows up as shallow focus blocks and calendar soup. Sustainable practice is not unlimited PTO slogans—it is protecting maker time and measuring outcomes instead of hours online.',
            'p2' => 'Leaders who model async updates and decline redundant meetings give the rest of the org permission to do the same. Otherwise “flexibility” becomes always-on chat.',
            'p3' => 'NSC teams batch deep work in overlapping windows and reserve Fridays for learning spikes that do not ship to production but prevent skill rot.',
            'bullets' => ['Audit recurring meetings quarterly', 'Default to docs over meetings', 'Fund learning time like you fund features'],
        ],
        7 => [
            'p1' => 'An API gateway is not a silver bullet; it is a traffic cop that must understand auth, throttling, and which services are allowed to fan out. Misconfigured gateways become single points of mystery latency.',
            'p2' => 'We prefer policy-as-code, structured logging with correlation IDs, and clear ownership when a route spans multiple teams.',
            'p3' => 'Patterns that work for ten services often need revisiting at fifty—especially when mobile clients and partner integrations hit different rate limits.',
            'bullets' => ['Centralize auth, decentralize business logic', 'Expose RED metrics per route', 'Document breaking changes like product launches'],
        ],
        8 => [
            'p1' => 'Sponsor booths and swag piles are easy; events that grow careers are harder. The best community nights leave attendees with a repo to fork or a mentor match, not just pizza.',
            'p2' => 'Local meetups in Vietnam now mix English and Vietnamese tracks, live coding, and honest panels about salary and visa realities for global roles.',
            'p3' => 'NSC hosts smaller roundtables so junior developers can ask “how did you negotiate that?” without a thousand people watching.',
            'bullets' => ['Pair sponsors with hands-on labs', 'Record consent before streaming Q&A', 'Follow up with office hours, not only mailing lists'],
        ],
        9 => [
            'p1' => 'Analytics without a pipeline is a spreadsheet someone emails on Fridays. Reliable pipelines declare schemas, handle late data, and let analysts trust yesterday’s numbers this morning.',
            'p2' => 'Idempotent writes, dead-letter queues, and partition strategies matter as much as the chart color palette once finance depends on the dashboard.',
            'p3' => 'We design ingestion with the same SLAs as customer-facing APIs because internal consumers still make million-dollar decisions on those tables.',
            'bullets' => ['Treat data contracts like API contracts', 'Monitor freshness, not only volume', 'Name a single owner per dataset'],
        ],
        10 => [
            'p1' => 'Wellness washing—yoga coupons without workload relief—burns trust faster than offering nothing. Programs stick when managers protect capacity and measure burnout signals, not only attendance.',
            'p2' => 'Anonymous pulse surveys help only if leaders publish actions taken. Silence after feedback teaches people to stop answering honestly.',
            'p3' => 'NSC experiments with collective no-meeting blocks and mental health stipends that engineers can spend on therapy, gym, or childcare without justification essays.',
            'bullets' => ['Tie initiatives to delivery pace, not slogans', 'Train managers to spot overload early', 'Celebrate recovery, not only hero weeks'],
        ],
        11 => [
            'p1' => 'Infrastructure as code fails when repositories become junk drawers of copy-pasted Terraform nobody dares to fmt. Confidence comes from reviewable modules and environments that promotion pipelines actually touch.',
            'p2' => 'Policy checks in CI, cost guardrails, and automated drift detection turn IaC from a solo wizard’s laptop into a team sport.',
            'p3' => 'We standardize blueprints for VPCs, databases, and observability baselines so new projects start compliant instead of inventing firewalls at midnight.',
            'bullets' => ['Module boundaries beat mega-stacks', 'Plan destroy workflows before you need them', 'Tag resources for cost attribution on day one'],
        ],
        12 => [
            'p1' => 'Anniversaries tempt vanity metrics—headcount charts and office photos. The useful retrospective asks which bets paid off, which clients stayed, and which processes we would never repeat.',
            'p2' => 'Growth without guardrails creates accidental specialization silos where only two people understand the billing subsystem.',
            'p3' => 'NSC marks milestones by publishing internal playbooks we wish we had on day one: onboarding, escalation, and how we say no politely.',
            'bullets' => ['Interview alumni for honest lessons', 'Invest profits into tooling debt', 'Tell failure stories, not only wins'],
        ],
        13 => [
            'p1' => 'Kubernetes is not “install and chill” for teams under ten people. Without guardrails, you inherit YAML archaeology and clusters that nobody fully understands.',
            'p2' => 'Start with one environment, strong RBAC, and a single paved path for deploys. Fancy service meshes can wait until someone can explain the last outage.',
            'p3' => 'We help small teams adopt namespaces, quotas, and GitOps flows that feel boring because they work on weekends.',
            'bullets' => ['Automate upgrades on a schedule', 'Keep staging honest, not a toy', 'Document the happy path in one page'],
        ],
        14 => [
            'p1' => 'Junior engineers in distributed teams need crisp tasks, visible code review norms, and mentors who narrate trade-offs instead of dropping “just fix it” comments.',
            'p2' => 'Pairing across time zones works when sessions are recorded with consent and follow-up exercises reinforce what was live-coded.',
            'p3' => 'NSC assigns onboarding buddies outside the direct manager chain so newcomers can ask “dumb” questions without performance anxiety.',
            'bullets' => ['Define “ready for review” checklists', 'Rotate review partners weekly', 'Celebrate first production commits loudly'],
        ],
        15 => [
            'p1' => 'Security reviews become bottlenecks when they happen only at the end. Shifting left means threat modeling user stories, not scanning binaries once before release.',
            'p2' => 'Lightweight checklists per feature type beat a 40-page PDF nobody reads. Automation handles the repetitive; humans focus on novel attack paths.',
            'p3' => 'We embed security champions in squads so risk conversations happen in standups, not only in audit season.',
            'bullets' => ['Map assets to actual data flows', 'Practice tabletop exercises quarterly', 'Track time-to-patch as a product metric'],
        ],
        16 => [
            'p1' => 'Design systems collapse when they are a Figma shrine nobody ships. Tokens in code, accessible components, and versioning separate toys from products.',
            'p2' => 'Contributions need a bar: visual regression tests, keyboard paths, and documentation that a contractor can follow on day two.',
            'p3' => 'NSC treats the system as a product with its own roadmap, not a side project for one designer.',
            'bullets' => ['Name a design-dev partnership owner', 'Deprecate components on a schedule', 'Measure adoption, not only downloads'],
        ],
        17 => [
            'p1' => 'Feedback loops fail when insights live in sales decks that never reach engineering. Tight loops require shared tools, tagged tickets, and rituals that connect revenue signals to backlog order.',
            'p2' => 'Qualitative interviews matter as much as NPS charts—especially for B2B workflows where five users drive half the revenue.',
            'p3' => 'We coach teams to bring anonymized quotes into sprint reviews so builders hear tone, not only numbers.',
            'bullets' => ['Close the loop with customers when you ship fixes', 'Timebox research spikes', 'Protect roadmap from every loud voice'],
        ],
        18 => [
            'p1' => 'Open source culture is not resumes full of stars. It is time budgets, legal clarity, and managers who do not punish maintenance weeks.',
            'p2' => 'Companies that consume OSS without contributing strain the commons. Sustainable culture funds upstream fixes the way it funds cloud bills.',
            'p3' => 'NSC sponsors maintainers on tools we depend on and publishes internal forks only when upstream collaboration truly breaks down.',
            'bullets' => ['Track licenses like dependencies', 'Celebrate merged upstream PRs', 'Teach writing good issues as a skill'],
        ],
        19 => [
            'p1' => 'Technical debt is not evil; unmanaged debt is. Some shortcuts fund experiments; others mortgage your ability to hire because onboarding takes six months.',
            'p2' => 'Visible debt registers with interest rates and owners beat hidden shame lists in spreadsheets.',
            'p3' => 'We help product and engineering negotiate pay-down sprints the same way they negotiate feature trade-offs—with customer impact spelled out.',
            'bullets' => ['Quantify drag in lead time', 'Never pay down without a metric', 'Celebrate deleted code as value'],
        ],
        20 => [
            'p1' => 'Logs without structure are archaeology. Metrics without traces guess at causality. Modern observability ties user journeys to service graphs so on-call stops playing telephone.',
            'p2' => 'Sampling strategies must respect compliance while still catching tail latency that VIP users feel.',
            'p3' => 'NSC standardizes dashboards per service tier so a pager wakes someone for revenue paths, not debug noise.',
            'bullets' => ['Define SLOs before buying tools', 'Practice failure injection monthly', 'Rotate on-call with empathy budgets'],
        ],
        21 => [
            'p1' => 'Enterprise IT often confuses headcount alignment with outcome alignment. Cross-functional teams win when they share incentives, not only a weekly status deck.',
            'p2' => 'Embedding compliance and finance partners early prevents “surprise” gate reviews that erase quarters of work.',
            'p3' => 'We facilitate joint OKRs that include risk reduction, not only feature throughput.',
            'bullets' => ['Co-locate decisions with information', 'Use architecture reviews as coaching', 'Kill projects that fail pre-mortems'],
        ],
        22 => [
            'p1' => 'Documentation that developers read is short, searchable, and maintained like code. Anything else rots beside the wiki nobody trusts.',
            'p2' => 'Runbooks written during incidents—while adrenaline is fresh—beat polished fiction authored months later.',
            'p3' => 'NSC links docs to deploy pipelines so “update readme” can be a merge requirement for risky changes.',
            'bullets' => ['Prefer examples over abstract rules', 'Timebox doc debt like tech debt', 'Measure time-to-answer for common questions'],
        ],
        23 => [
            'p1' => 'Performance budgets only work when product agrees what “fast enough” means for the business. Engineers alone cannot defend skipping a marketing pixel.',
            'p2' => 'Real-user monitoring reveals markets on slow networks where lab tests lie.',
            'p3' => 'We tie budgets to conversion and support ticket rates so trade-offs show up in planning, not only in Lighthouse scores.',
            'bullets' => ['Budget third-party scripts separately', 'Test on mid-tier Android devices', 'Alert on regression, not only outages'],
        ],
        24 => [
            'p1' => 'Accessibility as default means designers specify focus order and engineers test keyboard paths in CI, not a checkbox the week before launch.',
            'p2' => 'Legal risk matters, but the moral case is simpler: excluding users excludes revenue and talent.',
            'p3' => 'NSC bakes axe checks, contrast tokens, and screen reader spot checks into definitions of done for customer-facing surfaces.',
            'bullets' => ['Hire reviewers with lived experience', 'Never ship custom controls without tests', 'Train support on assistive tech basics'],
        ],
        25 => [
            'p1' => 'Release trains add predictability for huge programs; continuous delivery optimizes for learning speed. Hybrid models often satisfy neither if governance is unclear.',
            'p2' => 'The right choice depends on regulatory windows, client change boards, and how painful rollbacks are.',
            'p3' => 'We map decision latency—how long from commit to learn—to recommend a rhythm executives can defend.',
            'bullets' => ['Automate what repeats every sprint', 'Keep humans on exceptional approvals', 'Measure batch size, not only frequency'],
        ],
        26 => [
            'p1' => 'Playbooks nobody rehearses are fantasy novels. Incident response improves when you simulate partial failures and rotate commanders.',
            'p2' => 'Blameless postmortems require psychological safety and executives who show up without firing questions.',
            'p3' => 'NSC templates include customer comms drafts and legal checkpoints so tired humans do not improvise under pressure.',
            'bullets' => ['Define severities with customer impact', 'Keep a live status page owner', 'Store timelines in one source of truth'],
        ],
        27 => [
            'p1' => 'Hybrid offices tempt managers to favor whoever is physically present. Pair programming policies need explicit fairness so remote engineers are not permanent drivers.',
            'p2' => 'Tooling—shared IDEs, async review habits, and crisp story splits—matters more than mandating two days in beige cubicles.',
            'p3' => 'We rotate pairs across sites weekly so context and trust compound instead of cliques.',
            'bullets' => ['Record pairing etiquette in writing', 'Measure WIP, not seat time', 'Offer stipends for home ergonomics'],
        ],
        28 => [
            'p1' => 'Cloud bills creep through orphaned volumes, idle environments, and services nobody can map to a team. Optimization starts with ownership tags, not only reserved instances.',
            'p2' => 'FinOps works when engineering leads see cost dashboards beside latency graphs.',
            'p3' => 'NSC runs quarterly “cost stand-downs” where squads delete unused assets with leadership cover to say no to zombie projects.',
            'bullets' => ['Rightsize before renegotiating contracts', 'Alert on week-over-week spikes', 'Tie budgets to business units'],
        ],
        29 => [
            'p1' => 'Micromanagement often masks unclear goals. Leaders who specify outcomes and constraints get autonomy without chaos.',
            'p2' => 'Skip-levels and career ladders need teeth—promotions tied to behaviors, not only tenure.',
            'p3' => 'NSC trains managers to delegate decisions with context packets so teams move without waiting for HQ time zones.',
            'bullets' => ['Publish decision logs for big bets', 'Coach with questions before edits', 'Measure team health quarterly'],
        ],
        30 => [
            'p1' => 'Rituals that feel forced die fast. Celebrations work when they reflect how teams actually win—shipping, learning, helping a customer breathe easier.',
            'p2' => 'Remote confetti emojis help, but so do bonuses tied to collective outcomes and public praise for glue work.',
            'p3' => 'NSC ends quarters with “shout-out walls” that include support, QA, and ops—not only commit heroes.',
            'bullets' => ['Rotate who runs ceremonies', 'Fund team meals with real budgets', 'Never let crisis mode erase gratitude'],
        ],
    ];

    $idx = max(1, min(30, $i));
    $b   = $blocks[$idx];

    return [
        'p1'      => $b['p1'],
        'p2'      => $b['p2'],
        'p3'      => $b['p3'],
        'bullets' => $b['bullets'],
    ];
}

/**
 * Pull quotes aligned with blog-details-style blockquotes (one per index, cycles).
 */
function nsc_blog_seed_pull_quotes(): array
{
    return [
        'Building for regulated environments means every design choice is tested against security, auditability, and long-term maintainability—not just feature velocity.',
        'The teams that win globally invest in thin slices of value, fast feedback loops, and documentation that still makes sense six months later.',
        'Culture is what people do when nobody is watching the dashboard—how they escalate, how they admit uncertainty, and how they ship fixes.',
        'AI in delivery is a force multiplier only when humans own the oracles, the sampling strategy, and the story you tell stakeholders.',
        'Scale is rarely a language problem; it is a boundaries problem between contexts, data ownership, and integration contracts.',
        'Remote work that lasts protects maker time, measures outcomes instead of hours online, and rotates the cost of late meetings.',
        'Security left-shifted is cheaper than security as a gate: lightweight checklists beat forty-page PDFs nobody reads.',
        'Observability is not more logs—it is tying user journeys to service graphs so on-call stops playing telephone.',
        'Technical debt is a portfolio: some bets buy speed, others mortgage your ability to hire—name the interest rate.',
        'Great rituals reflect how teams actually win: shipping, learning, and helping a customer breathe easier—not forced cheer.',
    ];
}

/**
 * Tag names for wp_set_post_tags (displayed as plain text on single).
 *
 * @return list<string>
 */
function nsc_blog_seed_tag_pool(): array
{
    return [
        'Agile', 'AI', 'APIs', 'Cloud', 'Culture', 'Data', 'DevOps', 'Engineering',
        'FinTech', 'Kubernetes', 'Leadership', 'Microservices', 'NSC', 'Observability',
        'Quality', 'Remote', 'Security', 'UX', 'Vietnam Tech', 'Delivery', 'Product',
        'Open Source', 'Documentation', 'Performance', 'Accessibility', 'Mentoring',
    ];
}

/**
 * @return list<string>
 */
function nsc_blog_seed_tags_for_post(int $index): array
{
    $pool = nsc_blog_seed_tag_pool();
    $n    = count($pool);
    $want = 3 + ($index % 3); // 3–5 tags
    $out  = [];
    $step = max(1, (int) floor($n / 7));
    $start = ($index * $step * 3) % $n;
    for ($t = 0; $t < $want; $t++) {
        $out[] = $pool[($start + $t * $step) % $n];
    }

    return array_values(array_unique($out));
}

/**
 * Unsigned crc32 for seeded “random” picks (stable per title + variant).
 */
function nsc_blog_seed_hash_u(string $s): int
{
    return (int) sprintf('%u', crc32($s));
}

/**
 * @return int Combined seed for this post’s body randomization.
 */
function nsc_blog_seed_body_seed(int $variant, string $title): int
{
    return nsc_blog_seed_hash_u($title) ^ ($variant * 2654435761);
}

function nsc_blog_seed_pick(string $salt, int $seed, int $percent): bool
{
    return (nsc_blog_seed_hash_u($salt . '|' . $seed) % 100) < max(0, min(100, $percent));
}

/**
 * Fisher–Yates shuffle with deterministic RNG.
 *
 * @param list<string> $keys
 * @return list<string>
 */
function nsc_blog_seed_shuffle_keys(array $keys, int $seed): array
{
    $a = array_values($keys);
    $n = count($a);
    for ($i = $n - 1; $i > 0; $i--) {
        $j = nsc_blog_seed_hash_u('shuffle|' . $seed . '|' . $i) % ($i + 1);
        $tmp = $a[$i];
        $a[$i] = $a[$j];
        $a[$j] = $tmp;
    }

    return $a;
}

/**
 * @return list<string>
 */
function nsc_blog_seed_h3_titles(): array
{
    return [
        'What changed in the last sprint',
        'Signals we watch in production',
        'How we run discovery with stakeholders',
        'Risks we surface early',
        'A note on tooling choices',
        'When to simplify the architecture',
        'Handover checklist we use',
        'Metrics that actually matter here',
        'Working agreements that stuck',
        'Security and compliance touchpoints',
        'Cost vs speed trade-offs',
        'What we would do differently next time',
    ];
}

/**
 * Rich HTML: random subset of blocks + random order (seeded by variant + title).
 * Adds optional h3, divider, second blockquote beyond static blog-details baseline.
 *
 * @param list<string> $bullets
 */
function nsc_blog_seed_rich_html(
    int $variant,
    string $title,
    string $p1,
    string $p2,
    string $p3,
    array $bullets,
    string $imgUrl1,
    string $imgUrl2,
    string $pullQuote,
    string $midHeading,
    string $closingHeading
): string {
    $seed = nsc_blog_seed_body_seed($variant, $title);
    $pick = static function (string $salt, int $pct) use ($seed): bool {
        return nsc_blog_seed_pick($salt, $seed, $pct);
    };

    $lead = wp_trim_words($p1, 26, '…');

    $ulItems = array_map(
        static function (string $t): string {
            return '<li>' . esc_html($t) . '</li>';
        },
        $bullets
    );
    $ul = '<ul class="blog-details__list">' . implode('', $ulItems) . '</ul>';

    $ordered = array_slice($bullets, 0, min(3, count($bullets)));
    $olItems = array_map(
        static function (string $t): string {
            return '<li>' . esc_html($t) . '</li>';
        },
        $ordered
    );
    $ol = '<ol class="blog-details__list">' . implode('', $olItems) . '</ol>';

    $wrapUp = 'If you are planning a similar initiative, start with a thin end-to-end slice, instrument it in production, and expand scope only when telemetry supports the next bet.';

    $quotes = nsc_blog_seed_pull_quotes();
    $q2 = $quotes[($variant + 3) % count($quotes)];
    $h3List = nsc_blog_seed_h3_titles();
    $h3Title = $h3List[nsc_blog_seed_hash_u('h3|' . $title . $seed) % count($h3List)];

    $alt1 = $title . ' — team collaboration';
    $alt2 = $title . ' — delivery context';

    $parts = [
        'lead' => '<p class="blog-details__lead">' . esc_html($lead) . '</p>',
        'p1' => '<p>' . esc_html($p1) . '</p>',
        'p2' => '<p>' . esc_html($p2) . '</p>',
        'p3' => '<p>' . esc_html($p3) . '</p>',
        'fig1' => sprintf(
            '<figure class="blog-details__figure wp-block-image size-large"><img src="%s" alt="%s" loading="lazy" width="1200" height="675" /></figure>',
            esc_url($imgUrl1),
            esc_attr($alt1)
        ),
        'fig2' => sprintf(
            '<figure class="blog-details__figure wp-block-image size-large"><img src="%s" alt="%s" loading="lazy" width="1200" height="675" /></figure>',
            esc_url($imgUrl2),
            esc_attr($alt2)
        ),
        'quote' => '<blockquote class="blog-details__blockquote"><p>' . esc_html($pullQuote) . '</p></blockquote>',
        'quote2' => '<blockquote class="blog-details__blockquote"><p>' . esc_html($q2) . '</p></blockquote>',
        'h2mid' => '<h2 class="blog-details__h2">' . esc_html($midHeading) . '</h2>',
        'h3' => '<h3 class="blog-details__h3">' . esc_html($h3Title) . '</h3>',
        'h2close' => '<h2 class="blog-details__h2">' . esc_html($closingHeading) . '</h2>',
        'ul' => $ul,
        'ol' => $ol,
        'p_wrap' => '<p>' . esc_html($wrapUp) . '</p>',
        'hr' => '<hr class="blog-details__divider" />',
    ];

    $include = ['p1'];
    if ($pick('inc_lead', 62)) {
        $include[] = 'lead';
    }

    if ($pick('inc_p2', 78)) {
        $include[] = 'p2';
    }

    if ($pick('inc_p3', 58)) {
        $include[] = 'p3';
    }

    if ($pick('inc_fig1', 64)) {
        $include[] = 'fig1';
    }

    if ($pick('inc_fig2', 42)) {
        $include[] = 'fig2';
    }

    if ($pick('inc_quote', 58)) {
        $include[] = 'quote';
    }

    if ($pick('inc_quote2', 28)) {
        $include[] = 'quote2';
    }

    if ($pick('inc_h2mid', 68)) {
        $include[] = 'h2mid';
    }

    if ($pick('inc_h3', 48)) {
        $include[] = 'h3';
    }

    if ($pick('inc_ul', 60)) {
        $include[] = 'ul';
    }

    if ($pick('inc_ol', 38)) {
        $include[] = 'ol';
    }

    if ($pick('inc_h2close', 55)) {
        $include[] = 'h2close';
    }

    if ($pick('inc_pwrap', 58)) {
        $include[] = 'p_wrap';
    }

    if ($pick('inc_hr', 32)) {
        $include[] = 'hr';
    }

    $include = nsc_blog_seed_shuffle_keys($include, $seed);

    $out = '';
    foreach ($include as $key) {
        $out .= $parts[$key] ?? '';
    }

    return $out;
}

$imageMap     = nscBlogSeedGetImageIds();
$categories   = nscBlogSeedEnsureCategories();
$relatedPool  = nscBlogSeedRelatedPool($baseUrl);
$blogFiles    = ['blog1.png', 'blog2.png', 'blog3.png', 'blog4.png', 'blog5.png', 'blog6.png', 'blog7.png', 'blog8.png'];
$imgUriBase   = trailingslashit(get_template_directory_uri()) . 'frontend/build/img/';

$results = [];
$i       = 0;

foreach ($titles as $title) {
    $i++;
    $slug = sanitize_title($title);
    $singleTarget = function_exists('nsc_seed_is_single_target_language_run') && nsc_seed_is_single_target_language_run();
    $langArgs     = function_exists('nsc_seed_polylang_get_explicit_lang_query_args') ? nsc_seed_polylang_get_explicit_lang_query_args() : [];
    $canonicalId  = 0;
    if ($singleTarget && function_exists('nsc_seed_get_canonical_post_by_type_and_slug')) {
        $cPost = nsc_seed_get_canonical_post_by_type_and_slug('post', $slug, true);
        if ($cPost instanceof WP_Post) {
            $canonicalId = (int) $cPost->ID;
        }

        if ($canonicalId <= 0) {
            $results[] = ['slug' => $slug, 'status' => 'skipped', 'message' => 'No default-language post; run without seed_lang first.'];
            continue;
        }
    }

    $existing = get_posts(array_merge([
        'post_type'      => 'post',
        'post_status'    => 'any',
        'name'           => $slug,
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ], $langArgs));
    $postId = !empty($existing) ? (int) $existing[0] : 0;

    $imgIdx1 = ($i * 5 + 2) % count($blogFiles);
    $imgIdx2 = ($imgIdx1 + 3) % count($blogFiles);
    $imgFile = $blogFiles[$imgIdx1];
    $imgFile2 = $blogFiles[$imgIdx2];
    $imgUrl1  = esc_url($imgUriBase . $imgFile);
    $imgUrl2  = esc_url($imgUriBase . $imgFile2);

    $block = nsc_blog_seed_block_for_index($i);
    $p1    = $block['p1'];
    $p2    = $block['p2'];
    $p3    = $block['p3'];

    $quotes   = nsc_blog_seed_pull_quotes();
    $pullQuote = $quotes[($i - 1) % count($quotes)];

    $midHeadings = [
        'What we are seeing in practice',
        'Patterns that hold up in delivery',
        'How teams reduce risk while moving faster',
        'From theory to shipped software',
        'What this means for your next quarter',
    ];
    $closeHeadings = [
        'Key takeaways',
        'Summary',
        'Next steps for leaders',
        'Closing notes',
    ];
    $midHeading    = $midHeadings[($i - 1) % count($midHeadings)];
    $closingHeading = $closeHeadings[($i - 1) % count($closeHeadings)];

    $content = nsc_blog_seed_rich_html(
        $i,
        $title,
        $p1,
        $p2,
        $p3,
        $block['bullets'],
        $imgUrl1,
        $imgUrl2,
        $pullQuote,
        $midHeading,
        $closingHeading
    );

    // Excerpt: unique per post (list + title context), not the old shared lorem lead.
    $excerptSource = $title . ' — ' . $p1 . ' ' . $p2;
    $excerpt       = wp_trim_words(wp_strip_all_tags($excerptSource), 42, '…');

    $postarr = [
        'post_title'   => $title,
        'post_name'    => $slug,
        'post_content' => $content,
        'post_excerpt' => $excerpt,
        'post_status'  => 'publish',
        'post_type'    => 'post',
        'post_author'  => get_current_user_id() ?: 1,
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

        $catKey = ($i % 2 === 0) ? 'cultures' : 'technology';
        $termId = $categories[$catKey] ?? 0;
        if ($termId > 0) {
            wp_set_post_categories($postId, [$termId], false);
        }

        $thumbId = $imageMap[$imgFile] ?? 0;
        if ($thumbId > 0) {
            set_post_thumbnail($postId, $thumbId);
        }

        wp_set_post_tags($postId, nsc_blog_seed_tags_for_post($i), false);

        $featured = ($i % 4 === 1) ? 1 : 0;

        $start   = ($i * 3) % count($relatedPool);
        $related = [];
        for ($k = 0; $k < 3; $k++) {
            $related[] = $relatedPool[($start + $k) % count($relatedPool)];
        }

        if (function_exists('update_field')) {
            update_field('nsc_featured_article', $featured, $postId);
            update_field('nsc_related_heading', 'Related content', $postId);
            update_field('nsc_related_links', $related, $postId);
        } else {
            update_post_meta($postId, 'nsc_featured_article', $featured);
            update_post_meta($postId, 'nsc_related_heading', 'Related content');
            update_post_meta($postId, 'nsc_related_links', $related);
        }
    } else {
        $catKey   = ($i % 2 === 0) ? 'cultures' : 'technology';
        $featured = ($i % 4 === 1) ? 1 : 0;
        $start    = ($i * 3) % count($relatedPool);
        $related  = [];
        for ($k = 0; $k < 3; $k++) {
            $related[] = $relatedPool[($start + $k) % count($relatedPool)];
        }
    }

    $syncSourceId = ($singleTarget && $canonicalId > 0) ? $canonicalId : $postId;
    if (function_exists('nsc_seed_should_run_translation_sync') && nsc_seed_should_run_translation_sync() && function_exists('nsc_seed_polylang_sync_post_with_taxonomies')) {
        nsc_seed_polylang_sync_post_with_taxonomies(
            $syncSourceId,
            'post',
            $title,
            $slug,
            $content,
            $excerpt,
            [
                'nsc_featured_article' => $featured,
                'nsc_related_heading' => 'Related content',
                'nsc_related_links' => $related,
            ],
            ['category', 'post_tag']
        );
    }

    $reportId = $postId;
    if ($singleTarget && $canonicalId > 0 && function_exists('nsc_seed_polylang_sync_target_slugs_for_request')) {
        $t0 = nsc_seed_polylang_sync_target_slugs_for_request()[0] ?? '';
        if ($t0 !== '' && function_exists('pll_get_post')) {
            $tp = (int) pll_get_post($canonicalId, $t0);
            if ($tp > 0) {
                $reportId = $tp;
            }
        }
    }

    $rowStatus = $singleTarget
        ? 'translation-updated'
        : ($postId && !empty($existing) ? 'updated' : 'created');
    $results[] = [
        'slug'   => $slug,
        'status' => $rowStatus,
        'message' => 'post_id=' . $reportId . ', cat=' . $catKey . ', featured=' . $featured . ', related=' . count($related),
    ];
}

header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html><head><meta charset="utf-8"><title>NSC Blog Posts Seed</title>';
echo '<style>body{font-family:Arial,sans-serif;padding:24px}table{border-collapse:collapse;width:100%;max-width:1100px}th,td{border:1px solid #ddd;padding:8px;font-size:13px}th{background:#f7f7f7;text-align:left}.ok{color:#0a7f2e}.error{color:#b00020}</style>';
echo '</head><body><h1>NSC Blog Posts</h1>';
echo '<p>Seeded or updated ' . count($results) . ' posts. Categories: Technology, Cultures. Rich HTML body (blog-details-style), tags, ACF featured + related links. Thumbnails from build images when sideload works. Optional <code>seed_lang</code> / <code>seed_lang=all</code> for Polylang duplicates (omit for default language only).</p>';
echo '<table><thead><tr><th>Slug</th><th>Status</th><th>Details</th></tr></thead><tbody>';
foreach ($results as $row) {
    $cls = $row['status'] === 'error' ? 'error' : 'ok';
    echo '<tr><td>' . esc_html($row['slug']) . '</td><td class="' . esc_attr($cls) . '">' . esc_html($row['status']) . '</td><td>' . esc_html($row['message']) . '</td></tr>';
}

echo '</tbody></table></body></html>';
