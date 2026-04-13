<?php

declare(strict_types=1);

/**
 * Seeded ACF payloads for the Why NSC page (create-nsc-pages.php).
 * Requires nscSideloadBuildImageByFilename() from create-nsc-pages.php.
 *
 * @return array<int, array<string, mixed>>
 */
function nsc_why_nsc_build_page_components(): array
{
    $img = static function (string $filename): int {
        return nscSideloadBuildImageByFilename($filename);
    };

    $heroDesk = $img('why-us-hero-desktop.webp');
    $heroMob  = $img('why-us-hero-mobile.webp');
    $heroRow  = [
        'acf_fc_layout' => 'nscBlockWhyNscHero',
        'titleWhy'      => 'Why',
        'titleBrand'    => 'NSC Software',
        'lead'          => 'Engineering Excellence Powered by Senior Talent',
        'intro'         => '<p>At NSC Software, we believe great software is built by experienced engineers, strong technical leadership, and efficient collaboration. By combining Vietnam\'s top technology talent with AI-enhanced development workflows, we help global companies build scalable, high-quality digital solutions.</p>',
        'missionPrefix' => 'Our mission is simple:',
        'missionEmphasis' => 'deliver world-class engineering quality while enabling businesses to move faster, smarter, and more efficiently.',
        'options'       => ['theme' => ''],
    ];
    if ($heroDesk > 0) {
        $heroRow['imageDesktop'] = $heroDesk;
    }
    if ($heroMob > 0) {
        $heroRow['imageMobile'] = $heroMob;
    }

    $hi = $img('heading-icon.webp');
    $headingIconField = $hi > 0 ? $hi : '';

    $trust = [
        'acf_fc_layout' => 'nscBlockWhyNscEngineeringTrust',
        'heading'       => 'Engineering You Can <br class="sm:hidden"> Trust',
        'stats'         => [
            ['num' => '01.', 'value' => 'TOP 7%', 'label' => 'Vietnamese IT talent'],
            ['num' => '02.', 'value' => '100%', 'label' => 'Senior-level engineers'],
            ['num' => '03.', 'value' => '6+ years', 'label' => 'of industry experience'],
            ['num' => '04.', 'value' => 'AI-enhanced', 'label' => 'Development workflows'],
            ['num' => '05.', 'value' => 'Global', 'label' => 'English Collaboration'],
            ['num' => '06.', 'value' => 'Time-zone', 'label' => 'EU, AU & US Alignment'],
        ],
        'options'       => ['theme' => ''],
    ];
    if ($headingIconField !== '') {
        $trust['headingIcon'] = $headingIconField;
    }

    $m1 = $img('why-nsc-built-meeting.png');
    $m2 = $img('why-nsc-built-office.png');
    $m3 = $img('why-nsc-built-handshake.png');

    $built = [
        'acf_fc_layout' => 'nscBlockWhyNscBuilt',
        'title'         => 'Built in Vietnam. <br class="hidden lg:block"> Delivered Globally.',
        'intro'         => '<p>Founded in Vietnam with a global vision, NSC Software provides high-quality software development and technology consulting for companies worldwide.</p>',
        'cards'         => [
            [
                'text_first' => 0,
                'image'      => $m1 > 0 ? $m1 : '',
                'body'       => '<p>Our team consists of experienced engineers, solution architects, and technology specialists who work together to design and deliver modern digital products.</p>',
            ],
            [
                'text_first' => 1,
                'image'      => $m2 > 0 ? $m2 : '',
                'body'       => '<p>With a culture focused on discipline, collaboration, and continuous improvement, we approach every project with both technical rigor and business understanding.</p>',
            ],
            [
                'text_first' => 0,
                'image'      => $m3 > 0 ? $m3 : '',
                'body'       => '<p>Through strong engineering leadership and global delivery standards, NSC Software helps organizations build reliable, scalable, and future-ready technology solutions.</p>',
            ],
        ],
        'options'       => ['theme' => ''],
    ];
    if ($headingIconField !== '') {
        $built['headingIcon'] = $headingIconField;
    }

    $f1 = $img('why-nsc-different-col-1.webp');
    $f2 = $img('why-nsc-different-col-2.webp');
    $f3 = $img('why-nsc-different-col-3.webp');
    $f4 = $img('why-nsc-different-col-4.webp');
    $f5 = $img('why-nsc-different-col-5.webp');
    $f6 = $img('why-nsc-different-col-6.webp');
    $f7 = $img('why-nsc-different-col-7.webp');

    $diffItems = [
        [
            'title' => 'Senior-Led, AI-Enhanced Development',
            'feature_alt' => 'NSC senior engineers collaborating in a modern office',
            'body'  => '<p>Every project at NSC is led by experienced senior engineers who take ownership of architecture, design decisions, and delivery quality.</p>'
                . '<p>To improve efficiency and development speed, our engineers leverage AI-assisted development tools for coding support, testing automation, and knowledge acceleration.</p>'
                . '<p>The result is a modern development process where human expertise and AI efficiency work together to deliver faster and better outcomes.</p>',
            'feature_image' => $f1 > 0 ? $f1 : '',
        ],
        [
            'title' => 'Access to Vietnam\'s Top 7% IT Talent',
            'feature_alt' => 'NSC engineering workspace in Vietnam',
            'body'  => '<p>Vietnam is one of the fastest-growing technology talent markets in Asia. NSC carefully selects engineers from the top tier of Vietnam\'s IT workforce, ensuring strong technical foundations and analytical problem-solving skills.</p>'
                . '<p>Our hiring process evaluates candidates through:</p><ul><li>Technical assessments</li><li>System design capability</li><li>Real-world development experience</li><li>Communication and collaboration skills</li></ul>'
                . '<p>This ensures our clients work with engineers who can think critically, solve complex challenges, and deliver reliable solutions.</p>',
            'feature_image' => $f2 > 0 ? $f2 : '',
        ],
        [
            'title' => '100% Senior-Level Engineering Team',
            'feature_alt' => 'NSC partnership and delivery commitment',
            'body'  => '<p>Unlike traditional outsourcing models that rely heavily on junior developers, NSC focuses on senior-level engineering talent.</p>'
                . '<p>All engineers in our team have extensive professional experience and the ability to independently handle complex technical problems.</p>'
                . '<p>This allows us to deliver:</p><ul><li>Faster development cycles</li><li>Higher code quality</li><li>Reduced project risks</li><li>Stronger technical decision-making</li></ul>',
            'feature_image' => $f3 > 0 ? $f3 : '',
        ],
        [
            'title' => 'Minimum 6+ Years of Professional Experience',
            'feature_alt' => 'NSC technology delivery',
            'body'  => '<p>Every NSC engineer brings at least six years of real-world development experience across a wide range of technologies and industries.</p>'
                . '<p>This experience enables our teams to:</p><ul><li>Design scalable system architectures</li><li>Identify potential risks early</li><li>Implement best practices in software engineering</li><li>Deliver stable and maintainable solutions</li></ul>'
                . '<p>Our engineers understand not just how to write code, but how to build systems that perform reliably in real-world environments.</p>',
            'feature_image' => $f4 > 0 ? $f4 : '',
        ],
        [
            'title' => 'Global Communication Standards',
            'feature_alt' => 'Global communication standards',
            'body'  => '<p>Clear communication is critical in international software projects. At NSC, our engineering teams are fully English-proficient, enabling smooth collaboration with global clients.</p>'
                . '<p>Our teams actively participate in:</p><ul><li>Technical planning meetings</li><li>Agile sprint ceremonies</li><li>Architecture discussions</li><li>Product and requirement reviews</li></ul>'
                . '<p>This ensures alignment, transparency, and faster decision-making throughout the project lifecycle.</p>',
            'feature_image' => $f5 > 0 ? $f5 : '',
        ],
        [
            'title' => 'Time-Zone Friendly Collaboration',
            'feature_alt' => 'Time-zone friendly NSC collaboration',
            'body'  => '<p>NSC Software operates in working hours that overlap with Europe, Australia, and North America, Asia enabling real-time communication with international clients.</p>'
                . '<p>This allows for:</p><ul><li>Faster feedback cycles</li><li>Immediate issue resolution</li><li>Efficient project coordination</li><li>Continuous development progress</li></ul>'
                . '<p>Clients benefit from the flexibility of global teams while maintaining the responsiveness of a close collaboration.</p>',
            'feature_image' => $f6 > 0 ? $f6 : '',
        ],
        [
            'title' => 'High Quality, Cost-Efficient Delivery',
            'feature_alt' => 'High quality software delivery with NSC',
            'body'  => '<p>One of the major advantages of working with NSC Software is the ability to access high-quality engineering talent at competitive development costs.</p>'
                . '<p>Vietnam\'s technology ecosystem allows companies to scale development capacity without the high operational costs typically found in Western markets.</p>'
                . '<p>NSC combines:</p><ul><li>Senior engineering expertise</li><li>Global development standards</li><li>Efficient cost structures</li></ul>'
                . '<p>This creates a model where companies receive premium engineering quality at a sustainable investment level.</p>',
            'feature_image' => $f7 > 0 ? $f7 : '',
        ],
    ];

    $bgDiff = $img('what-make-diff-bg.webp');
    $sheetBg = $img('why-nsc-different-col-bg.png');

    $different = [
        'acf_fc_layout' => 'nscBlockWhyNscDifferent',
        'titleLine1'    => 'WHAT MAKES NSC',
        'titleLine2'    => 'DIFFERENT',
        'items'         => $diffItems,
        'options'       => ['theme' => ''],
    ];
    if ($headingIconField !== '') {
        $different['headingIcon'] = $headingIconField;
    }
    if ($bgDiff > 0) {
        $different['backgroundImage'] = $bgDiff;
    }
    if ($sheetBg > 0) {
        $different['bottomSheetBg'] = $sheetBg;
    }

    $slides = [];
    for ($i = 1; $i <= 10; $i++) {
        $fn = sprintf('why-nsc-team-%02d.png', $i);
        $sid = $img($fn);
        if ($sid > 0) {
            $slides[] = ['image' => $sid];
        }
    }

    $team = [
        'acf_fc_layout'   => 'nscBlockWhyNscTeam',
        'titlePrefix'     => 'A Team Built on',
        'titleAccent1'    => 'Expertise',
        'titleConjunction'=> 'and',
        'titleAccent2'    => 'Culture',
        'lead'            => 'At NSC Software, we believe that great technology solutions come from great people. Our culture encourages collaboration, continuous learning, and technical excellence.',
        'cardText'        => '<p>Engineers at NSC <br> are empowered to take ownership, <br> explore new technologies, and <br> continuously improve their skills <br> while working on challenging <br> global projects.</p>',
        'sliderAriaLabel' => 'NSC Software team',
        'slides'          => $slides,
        'options'         => ['theme' => ''],
    ];
    if ($headingIconField !== '') {
        $team['headingIcon'] = $headingIconField;
    }

    $cta = [
        'acf_fc_layout' => 'nscBlockWhyNscCta',
        'title'         => 'Start Building with NSC Software',
        'body'          => 'Whether you\'re a startup building a new product or an enterprise scaling your technology platform, NSC Software provides the engineering expertise needed to bring your ideas to life.',
        'button'        => [
            'label'         => 'Let\'s build the future of technology together',
            'url'           => '',
            'openInNewTab'  => 0,
        ],
        'options'       => ['theme' => ''],
    ];

    return [$heroRow, $trust, $built, $different, $team, $cta];
}
