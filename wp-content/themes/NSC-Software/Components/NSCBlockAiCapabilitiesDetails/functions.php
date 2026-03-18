<?php

namespace NscSoftware\Components\NSCBlockAiCapabilitiesDetails;

use NscSoftware\FieldVariables;

/**
 * Ensure each row's gallery "images" is an array of image arrays with url/alt
 * (ACF/Timber can return IDs or mixed formats when stored in flexible content).
 */
add_filter('NscSoftware/addComponentData?name=NSCBlockAiCapabilitiesDetails', function (array $data): array {
    if (empty($data['rows']) || !is_array($data['rows'])) {
        return $data;
    }
    foreach ($data['rows'] as $i => $row) {
        $images = $row['images'] ?? null;
        if (!is_array($images)) {
            $data['rows'][$i]['images'] = [];
            continue;
        }
        $normalized = [];
        foreach ($images as $img) {
            $id = nscAiCapDetailsImageId($img);
            if ($id > 0) {
                $normalized[] = nscAiCapDetailsImageToArray($id);
            } elseif (is_array($img) && !empty($img['url'])) {
                $normalized[] = [
                    'url'  => $img['url'],
                    'alt'  => isset($img['alt']) ? (string) $img['alt'] : '',
                    'sizes' => $img['sizes'] ?? [],
                ];
            }
        }
        $data['rows'][$i]['images'] = $normalized;
    }
    return $data;
});

function nscAiCapDetailsImageId($value): int
{
    if ($value === null || $value === '') {
        return 0;
    }
    if (is_int($value) && $value > 0) {
        return $value;
    }
    if (is_array($value) && isset($value['ID'])) {
        return (int) $value['ID'];
    }
    if (is_array($value) && isset($value['id'])) {
        return (int) $value['id'];
    }
    if (is_numeric($value)) {
        return (int) $value;
    }
    if (is_object($value)) {
        if (isset($value->ID)) {
            return (int) $value->ID;
        }
        if (isset($value->id)) {
            return (int) $value->id;
        }
    }
    return 0;
}

function nscAiCapDetailsImageToArray(int $id): array
{
    $url = wp_get_attachment_image_url($id, 'full');
    $alt = get_post_meta($id, '_wp_attachment_image_alt', true);
    $src = wp_get_attachment_image_src($id, 'medium');
    return [
        'id'    => $id,
        'url'   => $url ?: '',
        'alt'   => is_string($alt) ? $alt : '',
        'sizes' => ['medium' => ['url' => $src ? $src[0] : '']],
    ];
}

function getACFLayout()
{
    return [
        'name' => 'nscBlockAiCapabilitiesDetails',
        'label' => __('NSC Block: AI Capabilities Details (Ecosystem)', 'NscSoftware'),
        'sub_fields' => [
            [
                'label' => __('Content', 'NscSoftware'),
                'name' => 'contentTab',
                'type' => 'tab',
                'placement' => 'top',
                'endpoint' => 0,
            ],
            [
                'label' => __('Heading icon', 'NscSoftware'),
                'name' => 'headingIcon',
                'type' => 'image',
                'preview_size' => 'thumbnail',
                'return_format' => 'array',
            ],
            [
                'label' => __('Title', 'NscSoftware'),
                'name' => 'title',
                'type' => 'text',
            ],
            [
                'label' => __('Description', 'NscSoftware'),
                'name' => 'description',
                'type' => 'wysiwyg',
            ],
            [
                'label' => __('Rows (title + images)', 'NscSoftware'),
                'name' => 'rows',
                'type' => 'repeater',
                'min' => 1,
                'layout' => 'block',
                'sub_fields' => [
                    [
                        'label' => __('Row title', 'NscSoftware'),
                        'name' => 'title',
                        'type' => 'text',
                    ],
                    [
                        'label' => __('Badge (optional)', 'NscSoftware'),
                        'name' => 'badge',
                        'type' => 'text',
                        'instructions' => __('e.g. 💰 Cost Optimized. Renders inside &lt;bandage&gt;.', 'NscSoftware'),
                    ],
                    [
                        'label' => __('Images', 'NscSoftware'),
                        'name' => 'images',
                        'type' => 'gallery',
                        'min' => 0,
                        'return_format' => 'array',
                        'preview_size' => 'thumbnail',
                    ],
                ],
            ],
            [
                'label' => __('Quote (below rows)', 'NscSoftware'),
                'name' => 'quote',
                'type' => 'wysiwyg',
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
