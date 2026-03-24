<?php

namespace NscSoftware\Components\NSCCaseStudyMain;

use NscSoftware\FieldVariables;

function getACFLayout(): array
{
    return [
        'name' => 'nscCaseStudyMain',
        'label' => __('NSC Case Study: Main content', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'label' => __('Collaboration overview heading', 'NscSoftware'),
                'name' => 'collaborationHeading',
                'type' => 'text',
                'default_value' => 'Collaboration overview',
            ],
            [
                'label' => __('Overview blocks', 'NscSoftware'),
                'name' => 'overviewBlocks',
                'type' => 'repeater',
                'layout' => 'block',
                'button_label' => __('Add block', 'NscSoftware'),
                'sub_fields' => [
                    [
                        'label' => __('Block title', 'NscSoftware'),
                        'name' => 'blockTitle',
                        'type' => 'text',
                        'required' => 1,
                    ],
                    [
                        'label' => __('Bullet lines (one per line)', 'NscSoftware'),
                        'name' => 'blockLines',
                        'type' => 'textarea',
                        'rows' => 5,
                    ],
                ],
            ],
            [
                'label' => __('Size & duration', 'NscSoftware'),
                'name' => 'sizeDuration',
                'type' => 'text',
                'instructions' => __('Shown in the overview sidebar (e.g. 80+ FTE, 2012–2019).', 'NscSoftware'),
            ],
            [
                'label' => __('Gallery', 'NscSoftware'),
                'name' => 'gallery',
                'type' => 'gallery',
                'return_format' => 'array',
                'preview_size' => 'medium',
                'library' => 'all',
            ],
            [
                'label' => __('Options', 'NscSoftware'),
                'name' => 'optionsTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'label' => '',
                'name' => 'options',
                'type' => 'group',
                'layout' => 'row',
                'sub_fields' => [
                    FieldVariables\getHidden(),
                ],
            ],
        ],
    ];
}

/**
 * ACF gallery values may be attachment IDs (e.g. seeded via update_field) or image arrays missing `url`.
 * Twig expects each item to expose `url` (and optional `alt`).
 */
\add_filter('NscSoftware/addComponentData?name=NSCCaseStudyMain', static function ($data) {
    if (empty($data['gallery'])) {
        return $data;
    }

    $gallery = $data['gallery'];
    if (\is_string($gallery)) {
        $gallery = \array_filter(\array_map('intval', \array_map('trim', \explode(',', $gallery))));
    }
    if (!\is_array($gallery)) {
        $data['gallery'] = [];

        return $data;
    }

    $normalized = [];
    foreach ($gallery as $item) {
        if (\is_numeric($item)) {
            $id = (int) $item;
            $url = \wp_get_attachment_image_url($id, 'large');
            if ($url) {
                $normalized[] = [
                    'id' => $id,
                    'url' => $url,
                    'alt' => \trim((string) \get_post_meta($id, '_wp_attachment_image_alt', true)),
                ];
            }

            continue;
        }

        if (\is_array($item)) {
            $url = $item['url'] ?? '';
            if ($url === '' && !empty($item['ID'])) {
                $url = \wp_get_attachment_image_url((int) $item['ID'], 'large') ?: '';
            }
            if ($url === '' && !empty($item['id'])) {
                $url = \wp_get_attachment_image_url((int) $item['id'], 'large') ?: '';
            }
            if ($url !== '') {
                $item['url'] = $url;
                if (empty($item['alt']) && !empty($item['ID'])) {
                    $item['alt'] = \trim((string) \get_post_meta((int) $item['ID'], '_wp_attachment_image_alt', true));
                }
                $normalized[] = $item;
            }

            continue;
        }

        if (\is_object($item)) {
            $id = (int) ($item->ID ?? $item->id ?? 0);
            if ($id > 0) {
                $url = \wp_get_attachment_image_url($id, 'large');
                if ($url) {
                    $normalized[] = [
                        'id' => $id,
                        'url' => $url,
                        'alt' => \trim((string) ($item->alt ?? \get_post_meta($id, '_wp_attachment_image_alt', true))),
                    ];
                }
            }
        }
    }

    $data['gallery'] = $normalized;

    return $data;
});
