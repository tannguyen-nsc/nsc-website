<?php

declare(strict_types=1);

namespace NscSoftware\PostEditorExcerptSidebar;

/**
 * Move the built-in Excerpt metabox to the right sidebar on post edit screen.
 */
add_action('add_meta_boxes', static function (string $postType): void {
    if ($postType !== 'post') {
        return;
    }

    \remove_meta_box('postexcerpt', 'post', 'normal');
    \add_meta_box(
        'postexcerpt',
        \__('Excerpt'),
        'post_excerpt_meta_box',
        'post',
        'side',
        'default'
    );
}, 99);

