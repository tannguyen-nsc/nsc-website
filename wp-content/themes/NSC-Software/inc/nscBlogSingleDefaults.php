<?php

declare(strict_types=1);

namespace NscSoftware\BlogSingle;

/**
 * Default “About the author” data when NSC Theme Options → Blog has not been saved yet.
 *
 * @param array<string, mixed> $opts Raw options from Options::getTranslatable('NSCBlogSingle').
 * @return array<string, mixed>
 */
function merge_blog_single_defaults(array $opts): array
{
    $build_img = trailingslashit(get_template_directory_uri()) . 'frontend/build/img/mob-logo.webp';

    $defaultContent = '<p class="blog-details__author-name">'
        . esc_html__('NSC Software Co., LTD', 'NscSoftware')
        . '</p><p class="blog-details__author-subtitle">'
        . esc_html__('Vietnam\'s Premier Software Development and Consulting Company', 'NscSoftware')
        . '</p><p class="blog-details__author-lead">'
        . esc_html__('We\'re Vietnam Premier\'s Software Development & Consulting Company', 'NscSoftware')
        . '</p><p class="blog-details__author-desc">'
        . esc_html__(
            'Combining Vietnam\'s Top 7% IT talents - all senior-level engineers - with AI-enabled delivery, NSC Software helps global enterprises design, build, and scale secure, high-performing, and future-ready software solutions that drive long-term business value.',
            'NscSoftware'
        )
        . '</p>';

    $defaultLink = [
        'linkLabel' => __('LinkedIn Profile', 'NscSoftware'),
        'linkUrl' => 'https://www.linkedin.com/company/nscsoftware/',
        'openInNewTab' => 1,
    ];

    $defaultAvatar = [
        'url' => $build_img,
        'src' => $build_img,
        'alt' => __('NSC Software', 'NscSoftware'),
    ];

    $content = isset($opts['aboutAuthorContent']) ? (string) $opts['aboutAuthorContent'] : '';
    if (trim($content) === '') {
        $opts['aboutAuthorContent'] = $defaultContent;
    }

    $link = isset($opts['aboutAuthorLink']) && is_array($opts['aboutAuthorLink']) ? $opts['aboutAuthorLink'] : [];
    $linkUrl = '';
    if (isset($link['linkUrl'])) {
        $linkUrl = trim((string) $link['linkUrl']);
    } elseif (isset($link['link_url'])) {
        $linkUrl = trim((string) $link['link_url']);
    }

    if ($linkUrl === '') {
        $opts['aboutAuthorLink'] = $defaultLink;
    }

    $avatar = $opts['aboutAuthorAvatar'] ?? null;
    $avatarEmpty = $avatar === null || $avatar === false || $avatar === ''
        || (is_array($avatar) && empty($avatar['url']) && empty($avatar['src']));
    if ($avatarEmpty) {
        $opts['aboutAuthorAvatar'] = $defaultAvatar;
    }

    $labelDefaults = [
        'aboutAuthorHeading' => __('About the author', 'NscSoftware'),
        'shareArticleLabel' => __('Share article:', 'NscSoftware'),
        'readingTimeSuffixSingular' => __('min read', 'NscSoftware'),
        'readingTimeSuffixPlural' => __('mins read', 'NscSoftware'),
    ];
    foreach ($labelDefaults as $key => $default) {
        $v = isset($opts[$key]) ? trim((string) $opts[$key]) : '';
        if ($v === '') {
            $opts[$key] = $default;
        }
    }

    return $opts;
}
