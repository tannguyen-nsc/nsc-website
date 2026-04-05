<?php

declare(strict_types=1);

namespace NscSoftware\PolylangNsc {

/**
 * Polylang: register theme strings in two groups (Admin CMS vs front-end), language link fallback, helpers.
 */

/** Polylang string group: wp-admin / ACF / editor UI */
const GROUP_ADMIN = 'NSC - Admin';

/** Polylang string group: visitor-facing site output */
const GROUP_FRONT = 'NSC - Front Page';

/**
 * @return array<string, array<string, string>> group name => [ unique key => default English string ]
 */
function get_registered_string_groups(): array
{
    return [
        GROUP_ADMIN => [
            // NSC Theme Options — field / tab labels (wp-admin)
            'nsc_opts_logos' => 'Logos',
            'nsc_opts_logo_desktop' => 'Logo (desktop)',
            'nsc_opts_logo_white' => 'Logo white (desktop, transparent header)',
            'nsc_opts_mobile_logo_white' => 'Mobile logo white',
            'nsc_opts_mobile_logo_colored' => 'Mobile logo colored',
            'nsc_opts_labels' => 'Labels',
            'nsc_opts_language_label' => 'Language label',
            'nsc_opts_aria_menu' => 'Aria label (menu)',
            'nsc_opts_company' => 'Company',
            'nsc_opts_company_name' => 'Company name',
            'nsc_opts_company_description' => 'Company description',
            'nsc_opts_email' => 'Email',
            'nsc_opts_certifications' => 'Certification badges',
            'nsc_opts_footer_sitemap' => 'Footer sitemap',
            'nsc_opts_social' => 'Social links',
            'nsc_opts_footer_policy_menu' => 'Footer policy links (menu location)',
            'nsc_opts_copyright' => 'Copyright line',
            'nsc_opts_offices' => 'Offices',
            // Page / flexible content builder (ACF in admin)
            'nsc_page_components' => 'Page Components',
            'nsc_add_component' => 'Add Component',
            'nsc_content' => 'Content',
            'nsc_options' => 'Options',
            'nsc_heading' => 'Heading',
            'nsc_description' => 'Description',
            'nsc_title' => 'Title',
            'nsc_image' => 'Image',
            'nsc_label' => 'Label',
            'nsc_url' => 'URL',
            'nsc_open_new_tab' => 'Open in new tab',
            // Case study single — flexible layout names in admin
            'nsc_csc_components' => 'Case study components',
            'nsc_csc_hero' => 'NSC Case Study: Hero',
            'nsc_csc_instruction' => 'NSC Case Study: Instruction',
            'nsc_csc_quote' => 'NSC Case Study: Quote',
            'nsc_csc_main' => 'NSC Case Study: Main content',
            // wp-admin menu (theme tweak)
            'nsc_cfdb7_submissions_menu' => 'Submissions',
            // register_nav_menus + options subpages (_x titles use same English msgid)
            'nsc_nav_location_main' => 'Navigation Main',
            'nsc_nav_location_footer' => 'Navigation Footer',
            'nsc_nav_location_sitemap' => 'Footer Sitemap',
            'nsc_opts_menu_title' => 'NSC Theme Options',
            'nsc_opts_cat_global' => 'Global',
            'nsc_opts_cat_blog' => 'Blog',
            'nsc_opts_cat_careers' => 'Careers',
            // NSCFooter (labels not covered above)
            'nsc_footer_logo_desktop' => 'Logo (desktop footer)',
            'nsc_footer_logo_mobile' => 'Logo (mobile footer)',
            'nsc_footer_certifications_tab' => 'Certifications',
            'nsc_footer_cert_instructions' => 'Upload certification images (e.g. ISO). They appear in the footer. Leave empty to use default ISO badges.',
            'nsc_footer_business_number' => 'Business number',
            'nsc_footer_bottom_tab' => 'Bottom',
            'nsc_footer_copyright_text' => 'Copyright text',
            'nsc_footer_phone_link_label' => 'Phone link (text only, e.g. +84 866 639 497)',
            'nsc_footer_phone_link_help' => 'Enter phone number as text. It will be used as a tel: link. Example: +84 866 639 497',
            'nsc_footer_platform' => 'Platform',
            'nsc_footer_aria_label' => 'Aria label',
            'nsc_footer_address_details' => 'Address / details',
            // NSCBlogSingle (Theme Options → Blog)
            'nsc_blog_about_heading_field' => 'About the author heading',
            'nsc_blog_about_heading_help' => 'Heading above the author card in the blog single sidebar.',
            'nsc_blog_avatar_logo' => 'Avatar / logo',
            'nsc_blog_profile_link' => 'Profile link',
            'nsc_blog_link_label' => 'Link label',
            'nsc_blog_view_full_profile' => 'View full profile',
            'nsc_blog_link_url' => 'Link URL',
            'nsc_blog_connect_box_tab' => 'Connect box (sidebar CTA)',
            'nsc_blog_cta_title' => 'CTA title',
            'nsc_blog_cta_partner' => 'Need an innovative and reliable tech partner?',
            'nsc_blog_cta_title_help' => 'Shown above the button in the blog single sidebar.',
            'nsc_blog_button_label' => 'Button label',
            'nsc_blog_lets_connect' => "Let's Connect",
            'nsc_blog_button_url' => 'Button URL',
            'nsc_blog_open_button_new_tab' => 'Open button in new tab',
            'nsc_blog_bg_optional' => 'Background image (optional)',
            'nsc_blog_bg_help' => 'Overrides the default connect-box graphic from the theme build when set.',
            'nsc_blog_share_read_tab' => 'Share & reading labels',
            'nsc_blog_read_suffix_1' => 'Reading time suffix (1 minute)',
            'nsc_blog_read_suffix_1_help' => 'Shown after the number when the read time is exactly one (e.g. “1 min read”).',
            'nsc_blog_read_suffix_n' => 'Reading time suffix (2+ minutes)',
            'nsc_blog_mins_read' => 'mins read',
            'nsc_blog_read_suffix_n_help' => 'Shown after the number when the read time is more than one (e.g. “5 mins read”).',
            'nsc_blog_related_tab' => 'Related posts (single)',
            'nsc_blog_related_articles_heading' => 'Related Articles',
            'nsc_blog_related_count' => 'Related articles count',
            'nsc_blog_related_count_help' => 'How many related posts to show (same category first; fills with latest posts if needed).',
            // NSCJobSingle (Theme Options → Careers)
            'nsc_job_sidebar_tab' => 'Sidebar',
            'nsc_job_why_us_title' => '“Why us?” block title',
            'nsc_job_why_us' => 'Why us?',
            'nsc_job_why_us_help' => 'First sidebar block on job single (career-details__side-block).',
            'nsc_job_why_us_bullets' => '“Why us?” bullet points',
            'nsc_job_add_line' => 'Add line',
            'nsc_job_why_us_empty_help' => 'When empty, the job single shows the same default lines as the static career-details design (domains, remote collaboration, engineering culture, quality, growth).',
            'nsc_job_line' => 'Line',
            'nsc_job_share_vacancy_title' => '“Share this vacancy” title',
            'nsc_job_share_vacancy' => 'Share this vacancy',
            'nsc_job_share_help' => 'Share icons use the same behavior as blog post detail (Facebook / LinkedIn popup with this page URL).',
            'nsc_job_detail_tab' => 'Job detail',
            'nsc_job_key_tech_label' => '“Key technologies” label',
            'nsc_job_key_tech_help' => 'Label before the comma-separated list on job single (shown when the job has key technologies). A colon is added after the label.',
            'nsc_job_apply_section' => 'Apply section',
            'nsc_job_apply_heading' => 'Apply heading',
            'nsc_job_apply_heading_help' => 'Heading above the apply form (#career-apply).',
            'nsc_job_cf7_shortcode' => 'Contact Form 7 shortcode',
            'nsc_job_cf7_help' => 'Optional. Paste a shortcode to override the default. If empty, the theme uses the form created by create-nsc-cf7-form.php with apply_only=1 (option nsc_cf7_job_apply_form_id). Requires Contact Form 7.',
            'nsc_job_privacy_url' => 'Privacy policy URL (apply consent link)',
            'nsc_job_privacy_help' => 'Used in the fallback form only. Leave empty to use the WordPress Privacy Policy page when available.',
            'nsc_job_why_default_1' => 'Diversity of domains and challenging projects',
            'nsc_job_why_default_2' => 'Remote-friendly collaboration across regions',
            'nsc_job_why_default_3' => 'Mature engineering culture and clear processes',
            'nsc_job_why_default_4' => 'Focus on quality, security, and sustainable delivery',
            'nsc_job_why_default_5' => 'Support for learning and professional growth',
        ],
        GROUP_FRONT => [
            // Header / mobile bar / a11y (public site)
            'nsc_menu_drawer_title' => 'Menu',
            'nsc_home' => 'Home',
            'nsc_blog' => 'Blog',
            'nsc_case_studies' => 'Case Studies',
            'nsc_careers' => 'Careers',
            'nsc_main_navigation' => 'Main navigation',
            'nsc_close_menu' => 'Close menu',
            'nsc_language_label_default' => 'Language: English',
            'nsc_languages' => 'Languages',
            // Case study single (related block)
            'nsc_other_case_studies' => 'Other case studies',
            'nsc_view_other_case_studies' => 'View other case studies',
            'nsc_read_more' => 'Read More',
            // Footer / CTAs often shown on front
            'nsc_business_number' => 'Business Number:',
            'nsc_contact_us' => 'Contact Us',
            'nsc_explore' => 'Explore',
            // Blog / job singles (sidebar & meta)
            'nsc_about_author' => 'About the author',
            'nsc_share_article' => 'Share article:',
            'nsc_min_read' => 'min read',
            'nsc_apply_now' => 'Apply now',
            'nsc_key_technologies' => 'Key technologies',
        ],
    ];
}

/**
 * Translate a static theme string via Polylang when available.
 */
function pll_theme(string $string): string
{
    if (\function_exists('pll__')) {
        return \pll__($string);
    }

    return \__($string, 'NscSoftware');
}

/**
 * Register strings for Polylang → Languages → Translations of strings (groups: NSC - Admin, NSC - Front Page).
 *
 * Filter: nsc_polylang_register_string_groups — (array<string, array<string,string>>) merge or replace bundles.
 */
function register_strings(): void
{
    if (!\function_exists('pll_register_string')) {
        return;
    }

    $groups = \apply_filters('nsc_polylang_register_string_groups', get_registered_string_groups());
    if (!\is_array($groups)) {
        return;
    }

    foreach ($groups as $groupName => $strings) {
        if (!\is_string($groupName) || $groupName === '' || !\is_array($strings)) {
            continue;
        }
        foreach ($strings as $name => $text) {
            if (!\is_string($name) || !\is_string($text) || $text === '') {
                continue;
            }
            \pll_register_string($name, $text, $groupName, false);
        }
    }
}

add_action('init', static function (): void {
    register_strings();
}, 25);

/**
 * If there is no translated post/page for the target language, use that language’s home URL.
 */
add_filter(
    'pll_the_language_link',
    static function ($url, $slug) {
        if ($url !== null && $url !== '' && $url !== false) {
            return $url;
        }
        if (\function_exists('pll_home_url')) {
            return \pll_home_url((string) $slug);
        }

        return $url;
    },
    10,
    2
);

/**
 * ACF options / fields follow Polylang’s current language (Theme Options per language).
 * Polylang free does not ship ACF integration; WPML integration in ACF does not apply here.
 */
add_filter(
    'acf/settings/current_language',
    static function ($language) {
        if (\function_exists('pll_current_language')) {
            $slug = \pll_current_language();
            if (\is_string($slug) && $slug !== '') {
                return $slug;
            }
        }

        return $language;
    },
    5
);

/**
 * Prevent Polylang from synchronizing NSC ACF flexible layouts across translations on save.
 *
 * With Languages → Settings → Synchronization → **Custom fields** enabled, Polylang copies
 * every public post meta from the saved post to its translations. That includes the full
 * `pageComponents` / `caseStudyComponents` / `reusableComponents` trees, so editing e.g.
 * the German front page overwrites the English home’s builder content.
 *
 * We only strip these keys when `$sync` is true (ongoing sync). When `$sync` is false
 * (duplicate / new translation), keys stay in the list so the first copy still works.
 *
 * @param string[]     $keys Meta keys Polylang would copy or synchronize.
 * @param bool         $sync True when syncing after save; false when duplicating a translation.
 * @param int          $from Source post ID.
 * @param int          $to   Target post ID.
 * @param string       $lang Target language slug.
 * @return string[]
 */
\add_filter(
    'pll_copy_post_metas',
    static function ($keys, $sync, $from, $to, $lang) {
        if ($sync !== true || !\is_array($keys)) {
            return $keys;
        }

        $roots = \apply_filters(
            'nsc_polylang_no_sync_flexible_meta_roots',
            [
                'pageComponents',
                'caseStudyComponents',
                'reusableComponents',
            ]
        );

        if (!\is_array($roots) || $roots === []) {
            return $keys;
        }

        $out = [];
        foreach ($keys as $key) {
            if (!\is_string($key) || $key === '') {
                continue;
            }
            $drop = false;
            foreach ($roots as $root) {
                if (!\is_string($root) || $root === '') {
                    continue;
                }
                if ($key === $root || $key === '_' . $root) {
                    $drop = true;
                    break;
                }
                if (\strpos($key, $root . '_') === 0 || \strpos($key, '_' . $root . '_') === 0) {
                    $drop = true;
                    break;
                }
            }
            if (!$drop) {
                $out[] = $key;
            }
        }

        return $out;
    },
    20,
    5
);

/**
 * Polylang URL presets (directory mode only): default language at site root; non-default home at /{lang}/, not /{lang}/page-slug/.
 * Sets the same options as Languages → Settings → URL modifications: hide default language prefix; front page uses language code only.
 *
 * Runs once per site when `force_lang` is directory-based (1) and pretty permalinks are on.
 *
 * Filter `nsc_polylang_apply_clean_home_url_presets` (default true) to disable. Delete option `nsc_polylang_clean_home_urls_v1` to run again.
 */
const CLEAN_HOME_URL_OPTION = 'nsc_polylang_clean_home_urls_v1';

add_action(
    'pll_init',
    static function (): void {
        if (!\apply_filters('nsc_polylang_apply_clean_home_url_presets', true)) {
            return;
        }
        if (1 === (int) \get_option(CLEAN_HOME_URL_OPTION, 0)) {
            return;
        }
        if (!\function_exists('PLL')) {
            return;
        }
        $pll = \PLL();
        $forceLang = (int) $pll->options['force_lang'];
        if (1 !== $forceLang || !$pll->links_model->using_permalinks) {
            \update_option(CLEAN_HOME_URL_OPTION, 1, false);

            return;
        }
        $changed = false;
        if (!$pll->options['hide_default']) {
            $pll->options['hide_default'] = true;
            $changed = true;
        }
        if (!$pll->options['redirect_lang']) {
            $pll->options['redirect_lang'] = true;
            $changed = true;
        }
        \update_option(CLEAN_HOME_URL_OPTION, 1, false);
        if ($changed) {
            \flush_rewrite_rules(false);
        }
    },
    20
);

} // namespace NscSoftware\PolylangNsc

namespace {

    if (!function_exists('nsc_pll_theme')) {
        /**
         * Polylang string translation when active; otherwise theme gettext.
         */
        function nsc_pll_theme(string $string): string
        {
            return \NscSoftware\PolylangNsc\pll_theme($string);
        }
    }

    \add_filter(
        'pll_get_post_types',
        static function (array $types, bool $is_settings): array {
            $types['wpcf7_contact_form'] = 'wpcf7_contact_form';

            return $types;
        },
        5,
        2
    );
}
