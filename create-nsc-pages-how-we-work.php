<?php

declare(strict_types=1);

/**
 * Seeded ACF payloads for the How we work page (create-nsc-pages.php).
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

    $m1 = $img('why-nsc-built-meeting.png');
    $m2 = $img('why-nsc-built-office.png');
    $d1 = $img('why-nsc-different-col-1.webp');
    $d2 = $img('why-nsc-different-col-2.webp');
    $hs = $img('why-nsc-built-handshake.png');

    $bgD = $img('how-we-work-page-engagement-bg.png');
    $bgM = $img('how-we-work-page-engagement-bg-mob.png');

    $processStepDefs = [
        ['01.', 'Requirement Definition', 'We begin by clearly defining project requirements, deliverables, timelines, and success criteria to ensure full alignment from the start.', $m1],
        ['02.', 'Solution Planning', 'We translate requirements into architecture, milestones, and delivery plans your team can rely on.', $m2],
        ['03.', 'Development & Implementation', 'Senior engineers build in tight feedback loops with transparent progress and quality gates.', $d1],
        ['04.', 'Testing & Quality Assurance', 'Validation, regression, and release readiness so production rollouts stay predictable.', $d2],
        ['05.', 'Delivery & Deployment', 'Handover, documentation, and go-live support with clear ownership through launch.', $hs],
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
            'leadStrong'      => 'Clear goals, fixed requirements, and defined timelines for well-defined initiatives.',
            'leadParagraph'   => 'Ideal when scope is stable and outcomes can be agreed upfront — structured delivery with transparent checkpoints.',
            'bestSuitedLines' => array_map($line, [
                'MVP development',
                'Product feature implementation',
                'Platform migration milestones',
                'Well-documented change requests',
            ]),
            'keyBenefitLines' => array_map($line, [
                'Transparent costs and timelines',
                'Predictable delivery checkpoints',
                'Focused scope control',
                'Clear acceptance criteria',
            ]),
        ],
        [
            'tabNumber'       => '2',
            'tabLabel'        => 'Dedicated Team',
            'leadStrong'      => 'Build a long-term, fully managed engineering team aligned with your business objectives.',
            'leadParagraph'   => 'Leadership, process, and ownership embedded in NSC’s delivery model — ideal when you need sustained velocity and governance.',
            'bestSuitedLines' => array_map($line, [
                'Product roadmap execution',
                'Continuous delivery cadence',
                'Cross-functional squads',
                'Long-term capacity planning',
            ]),
            'keyBenefitLines' => array_map($line, [
                'Stable team continuity',
                'Aligned KPIs and outcomes',
                'Strong governance and reporting',
                'Scalable throughput',
            ]),
        ],
        [
            'tabNumber'       => '3',
            'tabLabel'        => 'Staff Augmentation',
            'leadStrong'      => 'Extend your in-house capacity with senior engineers who integrate with your tools and ceremonies.',
            'leadParagraph'   => 'NSC supports hiring quality and continuity while your team keeps day-to-day control and priorities.',
            'bestSuitedLines' => array_map($line, [
                'Short-term skill gaps',
                'Velocity ramp-up',
                'Specialist roles',
                'Hybrid product/engineering teams',
            ]),
            'keyBenefitLines' => array_map($line, [
                'Fast onboarding',
                'Direct collaboration',
                'Flexible scaling',
                'Senior-first staffing',
            ]),
        ],
        [
            'tabNumber'       => '4',
            'tabLabel'        => 'Managed Services',
            'leadStrong'      => 'Delegate selected operations to NSC under SLA-driven management.',
            'leadParagraph'   => 'Measurable service levels with engineering accountability — ideal for run, support, and continuous improvement.',
            'bestSuitedLines' => array_map($line, [
                'Run operations',
                'Support and maintenance',
                'Monitoring and incident response',
                'Continuous improvement cycles',
            ]),
            'keyBenefitLines' => array_map($line, [
                'Clear SLAs',
                'KPI visibility',
                'Risk-managed handover',
                'Predictable operational cost',
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

    $longFig = $hs > 0 ? $hs : '';
    $longterm = [
        'acf_fc_layout' => 'nscBlockHowWeWorkPageLongterm',
        'title'         => 'Built for Long-Term Partnerships',
        'body'          => 'We invest in communication clarity, delivery predictability, and engineering ownership — so collaboration feels like an extension of your team, not a handoff to a black box.',
        'options'       => ['theme' => ''],
    ];
    if ($headingIcon !== '') {
        $longterm['headingIcon'] = $headingIcon;
    }
    if ($longFig !== '') {
        $longterm['image'] = $longFig;
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
