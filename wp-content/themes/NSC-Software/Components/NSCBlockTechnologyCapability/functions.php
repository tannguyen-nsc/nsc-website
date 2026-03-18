<?php

namespace NscSoftware\Components\NSCBlockTechnologyCapability;

use NscSoftware\FieldVariables;

/**
 * Normalize each image group's gallery to array of { url, alt } (ACF can return IDs).
 */
add_filter('NscSoftware/addComponentData?name=NSCBlockTechnologyCapability', function (array $data): array {
    if (empty($data['rows']) || !is_array($data['rows'])) {
        return $data;
    }
    foreach ($data['rows'] as $ri => $row) {
        $groups = $row['imageGroups'] ?? null;
        if (!is_array($groups)) {
            continue;
        }
        foreach ($groups as $gi => $group) {
            $images = $group['images'] ?? null;
            if (!is_array($images)) {
                $data['rows'][$ri]['imageGroups'][$gi]['images'] = [];
                continue;
            }
            $normalized = [];
            foreach ($images as $img) {
                $id = nscTechCapImageId($img);
                if ($id > 0) {
                    $normalized[] = nscTechCapImageToArray($id, $row['title'] ?? '');
                } elseif (is_array($img) && !empty($img['url'])) {
                    $normalized[] = [
                        'url' => $img['url'],
                        'alt' => isset($img['alt']) ? (string) $img['alt'] : '',
                    ];
                }
            }
            $data['rows'][$ri]['imageGroups'][$gi]['images'] = $normalized;
        }
    }
    return $data;
});

function nscTechCapImageId($value): int
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
    if (is_object($value) && (isset($value->ID) || isset($value->id))) {
        return (int) ($value->ID ?? $value->id);
    }
    return 0;
}

function nscTechCapImageToArray(int $id, string $defaultAlt): array
{
    $url = wp_get_attachment_image_url($id, 'full');
    $alt = get_post_meta($id, '_wp_attachment_image_alt', true);
    return [
        'url' => $url ?: '',
        'alt' => is_string($alt) && $alt !== '' ? $alt : $defaultAlt,
    ];
}

function getACFLayout()
{
    return [
        'name' => 'nscBlockTechnologyCapability',
        'label' => __('NSC Block: Technology Capability (page)', 'NscSoftware'),
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
                'instructions' => __('Intro paragraph below the heading (e.g. centered).', 'NscSoftware'),
            ],
            [
                'label' => __('Capability rows', 'NscSoftware'),
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
                        'label' => __('Right column extra class', 'NscSoftware'),
                        'name' => 'rightColClass',
                        'type' => 'text',
                        'instructions' => __('e.g. !gap-2 for tighter spacing. Leave blank for default.', 'NscSoftware'),
                    ],
                    [
                        'label' => __('Image groups', 'NscSoftware'),
                        'name' => 'imageGroups',
                        'type' => 'repeater',
                        'min' => 1,
                        'layout' => 'block',
                        'instructions' => __('One group = one block of images. Add a label (e.g. "Android:") for grouped rows; leave label empty for a single flat list of images.', 'NscSoftware'),
                        'sub_fields' => [
                            [
                                'label' => __('Label (optional)', 'NscSoftware'),
                                'name' => 'label',
                                'type' => 'text',
                                'instructions' => __('e.g. "Android: ", "Cloud & Infrastructure: ". Shown before the images in this group.', 'NscSoftware'),
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
                ],
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
