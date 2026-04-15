<?php

declare(strict_types=1);

namespace NscSoftware\SingleRelatedBlogPosts;

use Timber\Timber;

/**
 * Latest posts in the same category first, then fill with latest overall (excluding current).
 *
 * @return array<int, \Timber\Post>
 */
function get_timber_posts(int $postId, int $limit, ?\WP_Term $primaryCategory): array
{
    if ($postId < 1 || $limit < 1) {
        return [];
    }

    $idsOrdered = [];

    if ($primaryCategory instanceof \WP_Term) {
        $catIds = get_posts(array_merge(
            [
                'post_type'           => 'post',
                'post_status'         => 'publish',
                'posts_per_page'      => $limit,
                'post__not_in'        => [$postId],
                'category__in'        => [(int) $primaryCategory->term_id],
                'orderby'             => 'date',
                'order'               => 'DESC',
                'ignore_sticky_posts' => true,
                'fields'              => 'ids',
            ],
            function_exists('nsc_polylang_frontend_lang_query_args') ? \nsc_polylang_frontend_lang_query_args() : []
        ));
        foreach ($catIds as $id) {
            $idsOrdered[] = (int) $id;
        }
    }

    if (count($idsOrdered) < $limit) {
        $exclude = array_merge([$postId], $idsOrdered);
        $need = $limit - count($idsOrdered);
        $more = get_posts(array_merge(
            [
                'post_type'           => 'post',
                'post_status'         => 'publish',
                'posts_per_page'      => $need,
                'post__not_in'        => $exclude,
                'orderby'             => 'date',
                'order'               => 'DESC',
                'ignore_sticky_posts' => true,
                'fields'              => 'ids',
            ],
            function_exists('nsc_polylang_frontend_lang_query_args') ? \nsc_polylang_frontend_lang_query_args() : []
        ));
        foreach ($more as $id) {
            $idsOrdered[] = (int) $id;
        }
    }

    if ($idsOrdered === []) {
        return [];
    }

    $posts = Timber::get_posts(array_merge(
        [
            'post_type'      => 'post',
            'post__in'       => $idsOrdered,
            'orderby'        => 'post__in',
            'posts_per_page' => count($idsOrdered),
            'post_status'    => 'publish',
        ],
        function_exists('nsc_polylang_frontend_lang_query_args') ? \nsc_polylang_frontend_lang_query_args() : []
    ));

    return normalize_timber_posts_list($posts);
}

/**
 * @param mixed $posts
 * @return array<int, \Timber\Post>
 */
function normalize_timber_posts_list($posts): array
{
    if (is_array($posts)) {
        return array_values($posts);
    }
    if ($posts instanceof \Traversable) {
        return array_values(iterator_to_array($posts, false));
    }

    return [];
}
