<?php

declare(strict_types=1);

use NscSoftware\BlogSingle;
use NscSoftware\NscReadingSession;
use NscSoftware\SingleRelatedBlogPosts;
use NscSoftware\Utils\Options;
use Timber\Timber;

$context = Timber::context();
$post    = Timber::get_post();
if (!$post) {
    Timber::render('404.twig', $context);

    return;
}

$context['post']         = $post;
$context['blog_single']  = BlogSingle\merge_blog_single_defaults(Options::getTranslatable('NSCBlogSingle') ?: []);
$context['home_url']     = home_url('/');

$pageForPosts = (int) get_option('page_for_posts');
$context['blog_archive_url'] = $pageForPosts > 0
    ? get_permalink($pageForPosts)
    : home_url('/blogs/');

$postId = (int) ($post->ID ?? $post->id ?? 0);

$primaryCategory = null;
$categories      = $postId > 0 ? get_the_category($postId) : [];
if (!empty($categories) && $categories[0] instanceof WP_Term) {
    $primaryCategory = $categories[0];
}
$context['primary_category'] = $primaryCategory;
$context['reading_minutes_display'] = NscReadingSession\display_read_minutes($postId);

$limit = Options::getTranslatable('NSCBlogSingle', 'relatedPostsLimit');
$limit = is_numeric($limit) ? (int) $limit : 3;
if ($limit < 1) {
    $limit = 3;
}
if ($limit > 12) {
    $limit = 12;
}

$context['related_blog_posts'] = SingleRelatedBlogPosts\get_timber_posts(
    $postId,
    $limit,
    $primaryCategory instanceof WP_Term ? $primaryCategory : null
);

$relatedHeading = Options::getTranslatable('NSCBlogSingle', 'relatedArticlesHeading');
$context['related_articles_heading'] = is_string($relatedHeading) && trim($relatedHeading) !== ''
    ? trim($relatedHeading)
    : __('Related Articles', 'NscSoftware');

$context['post_tags'] = get_the_tags($postId) ?: [];

Timber::render('templates/single.twig', $context);
