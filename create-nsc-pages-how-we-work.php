<?php

declare(strict_types=1);

/**
 * Seeded ACF payloads for the How we work page (create-nsc-pages.php).
 * Aligned with frontend/src/how-we-work.html (synced from master.html).
 *
 * Note: `nscBlockHowWeWorkPageEngagement` exposes a single `processSteps` repeater; the Twig
 * template renders that same step list inside each engagement tab. Static HTML varies steps per
 * tab — seed uses tab 1 (Fixed-Scope) steps and hww-process-panel0-step*.png assets.
 *
 * Requires nscSideloadBuildImageByFilename() from create-nsc-pages.php.
 *
 * @return array<int, array<string, mixed>>
 */
function nsc_how_we_work_build_page_components(): array
{
    $img = static function (string $filename): int {
        return nscSideloadBuildImageByFilename($filename);
    };

    $hi = $img('heading-icon.webp');
    $headingIcon = $hi > 0 ? $hi : '';

    $heroDesk = $img('how-we-work-hero-desktop.png');
    $heroMob  = $img('how-we-work-hero-mob.png');

    $hero = [
        'acf_fc_layout' => 'nscBlockHowWeWorkPageHero',
        'titleHow'      => 'How',
        'titleWeWork'   => 'We Work',
        'lead'          => 'Flexible engagement models designed for transparency, efficiency, and long-term partnership.',
        'intro'         => '<p>At <span class="how-we-work-page-hero__brand">NSC Software</span>, we understand that every organization has different technical needs, team structures, and development goals. That&rsquo;s why we offer flexible collaboration models that allow clients to choose the level of control, scalability, and management support that best fits their project.</p><p>Our engagement models are designed to ensure clear communication, predictable delivery, and efficient collaboration throughout the entire development lifecycle.</p>',
        'options'       => ['theme' => ''],
    ];
    if ($heroDesk > 0) {
        $hero['imageDesktop'] = $heroDesk;
    }
    if ($heroMob > 0) {
        $hero['imageMobile'] = $heroMob;
    }

    $partnership = [
        'acf_fc_layout'         => 'nscBlockHowWeWorkPagePartnership',
        'headingLine1'          => 'A Partnership Approach',
        'headingMobileLine2a'   => 'to Software',
        'headingMobileLine2b'   => 'Development',
        'headingDesktopLine2'   => 'to Software Development',
        'body'                  => '<p>At NSC Software, we go beyond writing code. We partner with organizations to design, build, and operate reliable software systems that support long-term business growth.</p><p>Our teams combine technical expertise, structured delivery processes, and flexible collaboration models to support projects of different scales and complexity.</p>',
        'options'               => ['theme' => ''],
    ];
    if ($headingIcon !== '') {
        $partnership['headingIcon'] = $headingIcon;
    }

    $bgD = $img('how-we-work-page-engagement-bg.png');
    $bgM = $img('how-we-work-page-engagement-bg-mob.png');

    $p0 = $img('hww-process-panel0-step01.png');
    $p1 = $img('hww-process-panel0-step02.png');
    $p2 = $img('hww-process-panel0-step03.png');
    $p3 = $img('hww-process-panel0-step04.png');
    $p4 = $img('hww-process-panel0-step05.png');

    $processStepDefs = [
        ['01.', 'Requirement Definition', 'We begin by clearly defining project requirements, deliverables, timelines, and success criteria to ensure full alignment from the start.', $p0],
        ['02.', 'Solution Planning', 'Our team designs the system architecture, technical approach, and project roadmap based on the agreed scope.', $p1],
        ['03.', 'Development & Implementation', 'Engineers develop the product following the defined specifications while maintaining regular progress updates.', $p2],
        ['04.', 'Testing & Quality Assurance', 'Comprehensive testing is conducted to ensure the system meets performance, security, and quality standards.', $p3],
        ['05.', 'Delivery & Deployment', 'The final product is delivered according to the agreed timeline, with support during deployment and initial rollout.', $p4],
    ];
    $processSteps = [];
    foreach ($processStepDefs as $row) {
        $step = [
            'stepLabel'   => $row[0],
            'title'       => $row[1],
            'description' => $row[2],
        ];
        if ($row[3] > 0) {
            $step['image'] = $row[3];
        }
        $processSteps[] = $step;
    }

    $line = static function (string $t): array {
        return ['text' => $t];
    };

    $engagementModels = [
        [
            'tabNumber'       => '1',
            'tabLabel'        => 'Fixed-Scope Projects',
            'leadStrong'      => 'Clear goals, fixed requirements, and defined timelines, ideal for well-scoped initiatives.',
            'leadParagraph'   => "The Fixed-Scope model is designed for projects where objectives, features, and deliverables can be clearly defined from the beginning. This model works best for initiatives with stable requirements and a well-defined development roadmap.\n\nNSC Software takes full responsibility for project delivery, including planning, development, testing, and deployment, ensuring that the final product meets agreed requirements, quality standards, and delivery timelines.",
            'bestSuitedLines' => array_map($line, [
                'MVP development',
                'Product feature implementation',
                'System upgrades or migrations',
                'Well-defined digital transformation initiatives',
            ]),
            'keyBenefitLines' => array_map($line, [
                'Clear project scope and deliverables',
                'Predictable timeline and budget',
                'Structured project management',
                'Reduced operational overhead for clients',
            ]),
        ],
        [
            'tabNumber'       => '2',
            'tabLabel'        => 'Dedicated Team',
            'leadStrong'      => 'Build a long-term, fully managed engineering team aligned with your business objectives.',
            'leadParagraph'   => "The Dedicated Team model provides clients with a stable, long-term engineering team that works exclusively on their products or platforms.\n\nThe team is carefully assembled based on project requirements and works exclusively on the client's product. While operating as an extension of the client's internal team, the engineers are supported by NSC's engineering management, delivery processes, and operational infrastructure.",
            'bestSuitedLines' => array_map($line, [
                'Long-term product development',
                'Scaling technology teams',
                'Continuous innovation and feature development',
                'Companies building complex platforms',
            ]),
            'keyBenefitLines' => array_map($line, [
                'Stable and committed engineering team',
                'Deep product knowledge over time',
                'Flexible team scaling',
                'Long-term technical partnership',
            ]),
        ],
        [
            'tabNumber'       => '3',
            'tabLabel'        => 'Staff Augmentation',
            'leadStrong'      => 'Extend your in-house capacity with senior engineers working directly under your management.',
            'leadParagraph'   => "For organizations that already have established engineering teams but need additional capacity, the Staff Augmentation model provides experienced NSC engineers who integrate directly into the client's existing workflow.\n\nEngineers work under the client's technical leadership while maintaining access to NSC's internal knowledge and support network.",
            'bestSuitedLines' => array_map($line, [
                'Rapid team scaling',
                'Access to specialized technical expertise',
                'Supporting ongoing product development',
                'Addressing skill gaps within internal teams',
                'Increasing engineering capacity while maintaining internal control',
            ]),
            'keyBenefitLines' => array_map($line, [
                'Fast onboarding of experienced engineers',
                'Flexible resource allocation',
                'Full control over development processes',
                'Seamless integration with internal teams',
                'Flexible collaboration arrangements that can adapt to different client engagement structures.',
            ]),
        ],
        [
            'tabNumber'       => '4',
            'tabLabel'        => 'Managed Services',
            'leadStrong'      => 'Delegate selected operations to NSC under SLA-driven, KPI-measured management.',
            'leadParagraph'   => "Our Managed Services model allows organizations to outsource specific technical operations or ongoing software management to NSC Software.\n\nUnder this model, NSC takes ownership of defined responsibilities and delivers services based on clearly established Service Level Agreements (SLAs) and performance KPIs.",
            'bestSuitedLines' => array_map($line, [
                'Application maintenance and support',
                'Infrastructure management',
                'Long-term platform operation',
                'Ongoing software optimization',
            ]),
            'keyBenefitLines' => array_map($line, [
                'Predictable operational performance',
                'Continuous system monitoring and improvement',
                'Reduced internal workload',
                'Clear accountability through measurable KPIs',
            ]),
        ],
    ];

    $engagement = [
        'acf_fc_layout'         => 'nscBlockHowWeWorkPageEngagement',
        'sectionHeading'        => 'Our Engagement Models',
        'collaborationHeading'  => 'Our Collaboration Process',
        'boxBestSuitedTitle'    => 'Best suited for',
        'boxKeyBenefitsTitle'   => 'Key benefits',
        'engagementModels'      => $engagementModels,
        'processSteps'          => $processSteps,
        'options'               => ['theme' => '', 'repeaterItemLimit' => 4],
    ];
    if ($headingIcon !== '') {
        $engagement['headingIcon'] = $headingIcon;
    }
    if ($bgD > 0) {
        $engagement['backgroundDesktop'] = $bgD;
    }
    if ($bgM > 0) {
        $engagement['backgroundMobile'] = $bgM;
    }

    $longterm = [
        'acf_fc_layout'     => 'nscBlockHowWeWorkPageLongterm',
        'headingLineBlack'  => 'Built for',
        'title'             => 'Long-Term Partnerships',
        'body'              => 'Our goal is not just to complete projects, but to build lasting technology partnerships. By combining flexible engagement models with experienced engineering teams, NSC Software helps organizations scale development while maintaining high standards of quality and reliability.',
        'options'           => ['theme' => ''],
    ];
    if ($headingIcon !== '') {
        $longterm['headingIcon'] = $headingIcon;
    }

    $cta = [
        'acf_fc_layout' => 'nscBlockHowWeWorkPageCta',
        'title'         => 'Let\'s Build Together',
        'lead'          => 'Whether you need a fully managed development partner or additional engineering expertise, NSC Software provides flexible collaboration models tailored to your goals.',
        'emphasis'      => 'Talk with our team to explore the engagement model that best fits your project!',
        'button'        => [
            'label'         => 'Talk to Our Team',
            'url'           => '',
            'openInNewTab'  => 0,
        ],
        'options'       => ['theme' => ''],
    ];
    if ($headingIcon !== '') {
        $cta['headingIcon'] = $headingIcon;
    }

    return [
        $hero,
        $partnership,
        $engagement,
        $longterm,
        $cta,
    ];
}
