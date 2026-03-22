<?php

/**
 * Posts list table: filter by NSC "Featured article" (ACF nsc_featured_article).
 */

namespace NscSoftware\Admin;

add_action('restrict_manage_posts', static function (string $postType, string $which = ''): void {
    if ($postType !== 'post') {
        return;
    }

    $selected = isset($_GET['nsc_featured_filter']) ? sanitize_text_field((string) $_GET['nsc_featured_filter']) : '';
    ?>
    <select name="nsc_featured_filter" id="nsc_featured_filter">
        <option value=""><?php esc_html_e('All featured states', 'NscSoftware'); ?></option>
        <option value="yes" <?php selected($selected, 'yes'); ?>><?php esc_html_e('Featured', 'NscSoftware'); ?></option>
        <option value="no" <?php selected($selected, 'no'); ?>><?php esc_html_e('Not featured', 'NscSoftware'); ?></option>
    </select>
    <?php
}, 10, 1);

add_action('pre_get_posts', static function (\WP_Query $query): void {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    global $pagenow;
    if ($pagenow !== 'edit.php') {
        return;
    }

    $screenPostType = isset($_GET['post_type']) ? (string) $_GET['post_type'] : 'post';
    if ($screenPostType !== 'post') {
        return;
    }

    if (empty($_GET['nsc_featured_filter'])) {
        return;
    }

    $filter = sanitize_text_field((string) $_GET['nsc_featured_filter']);
    if ($filter !== 'yes' && $filter !== 'no') {
        return;
    }

    $metaQuery = (array) $query->get('meta_query');

    if ($filter === 'yes') {
        $metaQuery[] = [
            'key' => 'nsc_featured_article',
            'value' => '1',
            'compare' => '=',
            'type' => 'CHAR',
        ];
    } else {
        $metaQuery[] = [
            'relation' => 'OR',
            [
                'key' => 'nsc_featured_article',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => 'nsc_featured_article',
                'value' => '0',
                'compare' => '=',
                'type' => 'CHAR',
            ],
        ];
    }

    $query->set('meta_query', $metaQuery);
});
